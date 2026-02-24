<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\SyncSeoRiskTenantJob;
use App\Models\Incident;
use App\Models\MonitoredUrl;
use App\Models\SeoCoreUpdateEvent;
use App\Models\SeoIntegration;
use App\Models\SeoProperty;
use App\Models\Tenant;
use App\Services\AuditLogService;
use App\Services\SeoRisk\GoogleTokenService;
use App\Services\SeoRisk\SeoRiskPlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SeoRiskController extends Controller
{
    public function index(Request $request, SeoRiskPlanService $planService)
    {
        $tenant = $request->user()?->tenant;
        abort_if(! $tenant, 400, 'User has no tenant.');
        $canManage = ($request->user()?->role ?? null) !== 'member';
        $seoRiskPlan = $planService->capabilitiesForTenant($tenant);

        $integrations = collect(['gsc', 'ga4'])
            ->mapWithKeys(function (string $provider) use ($tenant): array {
                $integration = SeoIntegration::query()->firstOrNew(
                    [
                        'tenant_id' => (int) $tenant->id,
                        'provider' => $provider,
                    ],
                    [
                        'is_enabled' => false,
                    ],
                );

                return [$provider => $integration];
            });

        $properties = SeoProperty::query()
            ->where('tenant_id', (int) $tenant->id)
            ->with('monitoredUrl:id,display_name,url')
            ->orderBy('id')
            ->get();

        $monitoredUrls = MonitoredUrl::query()
            ->where('tenant_id', (int) $tenant->id)
            ->orderBy('display_name')
            ->orderBy('id')
            ->get(['id', 'display_name', 'url']);

        $recentCoreUpdates = SeoCoreUpdateEvent::query()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'title', 'source_url', 'published_at', 'effective_from', 'effective_to']);

        $criticalThreshold = max(1, min(100, (int) config('wids.seo_risk.action_cards.critical_severity_threshold', 80)));
        $actionCards = Incident::query()
            ->where('tenant_id', (int) $tenant->id)
            ->whereIn('status', ['open', 'ack'])
            ->where('kind', 'like', 'seo_%')
            ->orderByDesc('severity')
            ->orderByDesc('detected_at')
            ->limit(6)
            ->get(['id', 'kind', 'severity', 'status', 'summary', 'details', 'detected_at'])
            ->map(function (Incident $incident) use ($criticalThreshold): array {
                $nextStep = $this->firstPlaybookStep(is_array($incident->details) ? $incident->details : []);

                return [
                    'id' => (int) $incident->id,
                    'kind' => (string) $incident->kind,
                    'kind_label' => $this->seoIncidentKindLabel((string) $incident->kind),
                    'severity' => (int) $incident->severity,
                    'status' => (string) $incident->status,
                    'summary' => (string) $incident->summary,
                    'priority' => (int) $incident->severity >= $criticalThreshold ? 'critical' : 'warning',
                    'detected_at' => $incident->detected_at,
                    'next_step' => $nextStep,
                    'cta_url' => route('incidents.show', ['incident' => (int) $incident->id]),
                ];
            })
            ->values();

        return view('app.seo-risk.index', [
            'tenant' => $tenant,
            'canManage' => $canManage,
            'seoRiskPlan' => $seoRiskPlan,
            'isPropertyLimitReached' => (int) $seoRiskPlan['max_properties'] > 0
                && $properties->count() >= (int) $seoRiskPlan['max_properties'],
            'integrations' => $integrations,
            'properties' => $properties,
            'monitoredUrls' => $monitoredUrls,
            'recentCoreUpdates' => $recentCoreUpdates,
            'actionCards' => $actionCards,
        ]);
    }

    public function connectIntegration(Request $request, string $provider): RedirectResponse
    {
        $tenant = $request->user()?->tenant;
        abort_if(! $tenant, 400, 'User has no tenant.');

        $provider = $this->normalizeProvider($provider);
        $this->assertPlatformGoogleOAuthConfigured();

        SeoIntegration::query()->firstOrCreate([
            'tenant_id' => (int) $tenant->id,
            'provider' => $provider,
        ], ['is_enabled' => false]);

        $clientId = trim((string) config('wids.seo_risk.google_oauth_default_client_id', ''));

        $state = Str::random(48);
        $request->session()->put('seo_risk_oauth_state', $state);
        $request->session()->put('seo_risk_oauth_provider', $provider);
        $request->session()->put('seo_risk_oauth_tenant_id', (int) $tenant->id);

        $scopes = $this->oauthScopesForProvider($provider);
        $query = http_build_query([
            'client_id' => $clientId,
            'redirect_uri' => route('seo-risk.google.callback'),
            'response_type' => 'code',
            'scope' => implode(' ', $scopes),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
            'include_granted_scopes' => 'true',
        ]);

        $authorizeUrl = rtrim((string) config('wids.seo_risk.google_oauth_authorize_url', 'https://accounts.google.com/o/oauth2/v2/auth'), '?');

        return redirect()->away($authorizeUrl.'?'.$query);
    }

    public function googleCallback(Request $request, GoogleTokenService $tokenService): RedirectResponse
    {
        $tenant = $request->user()?->tenant;
        abort_if(! $tenant, 400, 'User has no tenant.');

        $expectedState = (string) $request->session()->pull('seo_risk_oauth_state', '');
        $state = trim((string) $request->query('state', ''));
        if ($expectedState === '' || ! hash_equals($expectedState, $state)) {
            throw ValidationException::withMessages([
                'oauth' => ['OAuth state mismatch. Retry integration connect.'],
            ]);
        }

        $provider = $this->normalizeProvider((string) $request->session()->pull('seo_risk_oauth_provider', ''));
        $tenantId = (int) $request->session()->pull('seo_risk_oauth_tenant_id', 0);
        if ($tenantId !== (int) $tenant->id) {
            throw ValidationException::withMessages([
                'oauth' => ['OAuth tenant mismatch. Retry integration connect.'],
            ]);
        }

        $code = trim((string) $request->query('code', ''));
        if ($code === '') {
            throw ValidationException::withMessages([
                'oauth' => ['Google did not return an authorization code.'],
            ]);
        }

        $integration = SeoIntegration::query()->firstOrNew([
            'tenant_id' => (int) $tenant->id,
            'provider' => $provider,
        ]);

        $payload = $tokenService->exchangeAuthorizationCode(
            $integration,
            $code,
            route('seo-risk.google.callback'),
        );

        $accessToken = trim((string) ($payload['access_token'] ?? ''));
        $refreshToken = trim((string) ($payload['refresh_token'] ?? ''));
        $expiresIn = max(60, (int) ($payload['expires_in'] ?? 3600));

        if ($accessToken === '') {
            throw ValidationException::withMessages([
                'oauth' => ['OAuth token exchange returned empty access token.'],
            ]);
        }

        $integration->access_token = $accessToken;
        $integration->access_token_expires_at = now()->addSeconds($expiresIn);
        if ($refreshToken !== '') {
            $integration->refresh_token = $refreshToken;
        }
        $integration->scopes = trim((string) ($payload['scope'] ?? $integration->scopes ?? ''));
        $integration->external_account_email = $this->fetchGoogleAccountEmail($accessToken);
        $integration->is_enabled = true;
        $integration->last_error = null;
        $integration->save();

        return redirect()->route('seo-risk.index')->with('status', strtoupper($provider).' connected via Google OAuth.');
    }

    public function disconnectIntegration(Request $request, string $provider): RedirectResponse
    {
        $tenant = $request->user()?->tenant;
        abort_if(! $tenant, 400, 'User has no tenant.');

        $provider = $this->normalizeProvider($provider);
        $integration = SeoIntegration::query()->where([
            'tenant_id' => (int) $tenant->id,
            'provider' => $provider,
        ])->first();

        if (! $integration) {
            return redirect()->route('seo-risk.index')->with('status', strtoupper($provider).' was not connected.');
        }

        $before = $this->integrationAuditPayload($integration);

        $integration->is_enabled = false;
        $integration->refresh_token = null;
        $integration->access_token = null;
        $integration->access_token_expires_at = null;
        $integration->scopes = null;
        $integration->external_account_email = null;
        $integration->last_error = null;
        $integration->save();

        app(AuditLogService::class)->log(
            tenantId: (int) $tenant->id,
            actorUserId: $request->user()?->id,
            action: 'tenant.seo_integration_disconnected',
            auditableType: Tenant::class,
            auditableId: (int) $tenant->id,
            data: [
                'provider' => $provider,
                'before' => $before,
                'after' => $this->integrationAuditPayload($integration),
            ],
            request: $request,
        );

        return redirect()->route('seo-risk.index')->with('status', strtoupper($provider).' disconnected.');
    }

    public function storeProperty(Request $request, SeoRiskPlanService $planService): RedirectResponse
    {
        $tenant = $request->user()?->tenant;
        abort_if(! $tenant, 400, 'User has no tenant.');

        $maxProperties = $planService->maxPropertiesForTenant($tenant);
        $currentProperties = SeoProperty::query()->where('tenant_id', (int) $tenant->id)->count();
        if ($maxProperties > 0 && $currentProperties >= $maxProperties) {
            throw ValidationException::withMessages([
                'name' => ['SEO property limit reached for current plan. Upgrade plan to add more properties.'],
            ]);
        }

        $data = $this->validatePropertyPayload($request, (int) $tenant->id);

        SeoProperty::query()->create($data + [
            'tenant_id' => (int) $tenant->id,
        ]);

        return redirect()->route('seo-risk.index')->with('status', 'SEO property created.');
    }

    public function updateProperty(Request $request, SeoProperty $seoProperty): RedirectResponse
    {
        $tenant = $request->user()?->tenant;
        abort_if(! $tenant, 400, 'User has no tenant.');
        abort_if((int) $seoProperty->tenant_id !== (int) $tenant->id, 404);

        $data = $this->validatePropertyPayload($request, (int) $tenant->id, (int) $seoProperty->id);
        $seoProperty->fill($data);
        $seoProperty->save();

        return redirect()->route('seo-risk.index')->with('status', 'SEO property updated.');
    }

    public function destroyProperty(Request $request, SeoProperty $seoProperty): RedirectResponse
    {
        $tenant = $request->user()?->tenant;
        abort_if(! $tenant, 400, 'User has no tenant.');
        abort_if((int) $seoProperty->tenant_id !== (int) $tenant->id, 404);

        $seoProperty->delete();

        return redirect()->route('seo-risk.index')->with('status', 'SEO property deleted.');
    }

    public function syncNow(Request $request): RedirectResponse
    {
        $tenant = $request->user()?->tenant;
        abort_if(! $tenant, 400, 'User has no tenant.');

        $metricDate = now()->subDay()->toDateString();
        SyncSeoRiskTenantJob::dispatch((int) $tenant->id, $metricDate);

        return redirect()->route('seo-risk.index')->with('status', 'SEO sync queued for yesterday data.');
    }

    /**
     * @return array{
     *   monitored_url_id: int,
     *   name: string,
     *   domain: string,
     *   gsc_site_url: string,
     *   ga4_property_id: string,
     *   is_enabled: bool,
     *   alert_clicks_drop_percent: int,
     *   alert_impressions_drop_percent: int,
     *   alert_sessions_drop_percent: int,
     *   baseline_days: int
     * }
     */
    private function validatePropertyPayload(Request $request, int $tenantId, ?int $currentPropertyId = null): array
    {
        $minBaseline = max(3, (int) config('wids.seo_risk.min_baseline_days', 3));
        $maxBaseline = min(90, max($minBaseline, (int) config('wids.seo_risk.max_baseline_days', 30)));
        $propertyId = $currentPropertyId;

        $data = $request->validate([
            'monitored_url_id' => ['required', 'integer', 'exists:monitored_urls,id'],
            'name' => ['required', 'string', 'max:120'],
            'domain' => [
                'required',
                'string',
                'max:255',
                Rule::unique('seo_properties', 'domain')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($propertyId),
            ],
            'gsc_site_url' => ['required', 'string', 'max:500'],
            'ga4_property_id' => ['required', 'string', 'max:64'],
            'is_enabled' => ['nullable', 'boolean'],
            'alert_clicks_drop_percent' => ['required', 'integer', 'min:5', 'max:90'],
            'alert_impressions_drop_percent' => ['required', 'integer', 'min:5', 'max:90'],
            'alert_sessions_drop_percent' => ['required', 'integer', 'min:5', 'max:90'],
            'baseline_days' => ['required', 'integer', 'min:'.$minBaseline, 'max:'.$maxBaseline],
        ]);

        $monitorBelongsToTenant = MonitoredUrl::query()
            ->where('id', (int) $data['monitored_url_id'])
            ->where('tenant_id', $tenantId)
            ->exists();

        if (! $monitorBelongsToTenant) {
            throw ValidationException::withMessages([
                'monitored_url_id' => ['Selected monitor does not belong to your tenant.'],
            ]);
        }

        $data['domain'] = strtolower(trim((string) $data['domain']));
        $data['gsc_site_url'] = trim((string) $data['gsc_site_url']);
        $data['ga4_property_id'] = trim((string) $data['ga4_property_id']);
        $data['is_enabled'] = (bool) ($data['is_enabled'] ?? false);

        return [
            'monitored_url_id' => (int) $data['monitored_url_id'],
            'name' => trim((string) $data['name']),
            'domain' => $data['domain'],
            'gsc_site_url' => $data['gsc_site_url'],
            'ga4_property_id' => $data['ga4_property_id'],
            'is_enabled' => $data['is_enabled'],
            'alert_clicks_drop_percent' => (int) $data['alert_clicks_drop_percent'],
            'alert_impressions_drop_percent' => (int) $data['alert_impressions_drop_percent'],
            'alert_sessions_drop_percent' => (int) $data['alert_sessions_drop_percent'],
            'baseline_days' => (int) $data['baseline_days'],
        ];
    }

    private function normalizeProvider(string $provider): string
    {
        $provider = strtolower(trim($provider));
        if (! in_array($provider, ['gsc', 'ga4'], true)) {
            abort(404);
        }

        return $provider;
    }

    /**
     * @return list<string>
     */
    private function oauthScopesForProvider(string $provider): array
    {
        $providerScope = (string) config('wids.seo_risk.google_scopes.'.$provider, '');
        $profileScopes = preg_split('/\s+/', trim((string) config('wids.seo_risk.google_scopes.profile', 'openid email profile'))) ?: [];

        $scopes = array_merge(
            $providerScope !== '' ? [$providerScope] : [],
            array_filter(array_map('trim', $profileScopes))
        );

        return array_values(array_unique(array_filter($scopes)));
    }

    private function assertPlatformGoogleOAuthConfigured(): void
    {
        $clientId = trim((string) config('wids.seo_risk.google_oauth_default_client_id', ''));
        $clientSecret = trim((string) config('wids.seo_risk.google_oauth_default_client_secret', ''));

        if ($clientId !== '' && $clientSecret !== '') {
            return;
        }

        throw ValidationException::withMessages([
            'oauth' => ['Google OAuth is not configured on platform level.'],
        ]);
    }

    private function fetchGoogleAccountEmail(string $accessToken): ?string
    {
        try {
            $response = Http::timeout(10)
                ->withToken($accessToken)
                ->get('https://www.googleapis.com/oauth2/v3/userinfo');

            if (! $response->successful()) {
                return null;
            }

            $payload = $response->json();
            if (! is_array($payload)) {
                return null;
            }

            $email = trim((string) ($payload['email'] ?? ''));

            return $email !== '' ? $email : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{
     *   is_enabled: bool,
     *   refresh_token_set: bool,
     *   access_token_set: bool,
     *   external_account_email: ?string,
     *   scopes: ?string
     * }
     */
    private function integrationAuditPayload(SeoIntegration $integration): array
    {
        return [
            'is_enabled' => (bool) $integration->is_enabled,
            'refresh_token_set' => is_string($integration->refresh_token) && trim($integration->refresh_token) !== '',
            'access_token_set' => is_string($integration->access_token) && trim($integration->access_token) !== '',
            'external_account_email' => is_string($integration->external_account_email) ? $integration->external_account_email : null,
            'scopes' => is_string($integration->scopes) ? $integration->scopes : null,
        ];
    }

    private function seoIncidentKindLabel(string $kind): string
    {
        $key = 'messages.seo_risk.incident_kinds.'.$kind;
        $label = trans($key);

        return $label === $key ? $kind : $label;
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function firstPlaybookStep(array $details): ?string
    {
        $steps = $details['playbook'] ?? null;
        if (! is_array($steps)) {
            return null;
        }

        foreach ($steps as $step) {
            if (is_string($step) && trim($step) !== '') {
                return trim($step);
            }
        }

        return null;
    }
}
