<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantSsoProvider;
use App\Models\User;
use App\Models\UserIdentity;
use App\Services\AuditLogService;
use App\Services\IdentityGovernanceService;
use App\Services\SamlAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SamlSessionController extends Controller
{
    public function redirectByTenantSlug(Request $request): RedirectResponse
    {
        $tenantSlug = strtolower(trim((string) $request->query('tenant', '')));

        if (preg_match('/^[a-z0-9-]{2,80}$/', $tenantSlug) !== 1) {
            return $this->loginError('Enter a valid workspace slug to continue with SAML SSO.', $tenantSlug);
        }

        $tenant = Tenant::query()->where('slug', $tenantSlug)->first();

        if (! $tenant) {
            return $this->loginError('Workspace not found for provided slug.', $tenantSlug);
        }

        if (! app(IdentityGovernanceService::class)->isSsoProviderAllowedForTenant($tenant, 'saml')) {
            return $this->loginError('SAML SSO is disabled by workspace policy.', $tenantSlug);
        }

        $provider = $this->enabledProviderForTenant((int) $tenant->id);

        if (! $provider) {
            return $this->loginError('SAML SSO is not enabled for this workspace.', $tenantSlug);
        }

        try {
            return app(SamlAuthService::class)->beginAuthorization(
                provider: $provider,
                request: $request,
                acsUrl: route('sso.saml.acs', ['tenantSlug' => (string) $tenant->slug]),
                metadataUrl: route('sso.saml.metadata', ['tenantSlug' => (string) $tenant->slug]),
            );
        } catch (\Throwable $e) {
            return $this->loginError($e->getMessage(), $tenantSlug);
        }
    }

    public function acs(Request $request, string $tenantSlug): RedirectResponse
    {
        $tenantSlug = strtolower(trim($tenantSlug));
        $tenant = Tenant::query()->where('slug', $tenantSlug)->first();

        if (! $tenant) {
            return $this->loginError('Workspace not found for SAML callback.', $tenantSlug);
        }

        if (! app(IdentityGovernanceService::class)->isSsoProviderAllowedForTenant($tenant, 'saml')) {
            return $this->loginError('SAML SSO is disabled by workspace policy.', $tenantSlug);
        }

        $provider = $this->enabledProviderForTenant((int) $tenant->id);

        if (! $provider) {
            return $this->loginError('SAML SSO is not enabled for this workspace.', $tenantSlug);
        }

        try {
            $callbackInfo = app(SamlAuthService::class)->consumeAcsCallback(
                provider: $provider,
                request: $request,
                acsUrl: route('sso.saml.acs', ['tenantSlug' => (string) $tenant->slug]),
                metadataUrl: route('sso.saml.metadata', ['tenantSlug' => (string) $tenant->slug]),
            );

            $user = $this->resolveUserFromSamlProfile($tenant, $provider, $callbackInfo);

            Auth::login($user);
            $request->session()->regenerate();
            $request->session()->put('auth_session_started_at', now()->timestamp);
            $request->session()->put('sso_auth', [
                'provider' => 'saml',
                'tenant_id' => (int) $tenant->id,
                'tenant_slug' => (string) $tenant->slug,
                'provider_subject' => (string) ($callbackInfo['provider_subject'] ?? ''),
                'name_id' => (string) ($callbackInfo['name_id'] ?? ''),
                'session_index' => (string) ($callbackInfo['session_index'] ?? ''),
            ]);

            app(AuditLogService::class)->log(
                tenantId: (int) $tenant->id,
                actorUserId: (int) $user->id,
                action: 'auth.sso.saml_login',
                auditableType: User::class,
                auditableId: (int) $user->id,
                data: [
                    'provider' => 'saml',
                    'provider_subject' => (string) ($callbackInfo['provider_subject'] ?? ''),
                    'name_id' => (string) ($callbackInfo['name_id'] ?? ''),
                    'session_index' => (string) ($callbackInfo['session_index'] ?? ''),
                    'email' => (string) ($callbackInfo['email'] ?? ''),
                    'request_id' => (string) ($callbackInfo['request_id'] ?? ''),
                    'response_bytes' => (int) ($callbackInfo['response_bytes'] ?? 0),
                ],
                request: $request,
            );

            return redirect()->intended(route('monitored-urls.index', absolute: false));
        } catch (\Throwable $e) {
            return $this->loginError($e->getMessage(), $tenantSlug);
        }
    }

    public function slo(Request $request, string $tenantSlug): RedirectResponse
    {
        $tenantSlug = strtolower(trim($tenantSlug));
        $tenant = Tenant::query()->where('slug', $tenantSlug)->first();

        if (! $tenant) {
            return $this->loginError('Workspace not found for SAML logout callback.', $tenantSlug);
        }

        $provider = $this->enabledProviderForTenant((int) $tenant->id);

        if (! $provider) {
            return $this->loginError('SAML SSO is not enabled for this workspace.', $tenantSlug);
        }

        $samlRequestPayload = trim((string) $request->input('SAMLRequest', ''));
        if ($samlRequestPayload !== '') {
            return $this->handleIdpInitiatedLogoutRequest($request, $tenant, $provider);
        }

        return $this->handleSingleLogoutResponse($request, $tenant, $provider);
    }

    private function handleSingleLogoutResponse(
        Request $request,
        Tenant $tenant,
        TenantSsoProvider $provider,
    ): RedirectResponse {
        $actorUserId = null;
        $actorUser = $request->user();
        if ($actorUser && (int) $actorUser->tenant_id === (int) $tenant->id) {
            $actorUserId = (int) $actorUser->id;
        }

        try {
            $callbackInfo = app(SamlAuthService::class)->consumeSingleLogoutCallback(
                provider: $provider,
                request: $request,
                sloUrl: route('sso.saml.slo', ['tenantSlug' => (string) $tenant->slug]),
            );

            $this->performLocalLogout($request);

            app(AuditLogService::class)->log(
                tenantId: (int) $tenant->id,
                actorUserId: $actorUserId,
                action: 'auth.sso.saml_logout',
                auditableType: User::class,
                auditableId: $actorUserId,
                data: [
                    'provider' => 'saml',
                    'flow' => 'sp_initiated',
                    'request_id' => (string) ($callbackInfo['request_id'] ?? ''),
                    'response_bytes' => (int) ($callbackInfo['response_bytes'] ?? 0),
                ],
                request: $request,
            );

            return redirect()
                ->route('login')
                ->with('status', 'You are signed out.');
        } catch (\Throwable $e) {
            $this->performLocalLogout($request);

            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => $e->getMessage(),
                ]);
        }
    }

    private function handleIdpInitiatedLogoutRequest(
        Request $request,
        Tenant $tenant,
        TenantSsoProvider $provider,
    ): RedirectResponse {
        $actorUserId = null;
        $localLogoutPerformed = false;
        $localLogoutReason = 'not_authenticated';

        try {
            $logoutRequestInfo = app(SamlAuthService::class)->consumeIdpInitiatedLogoutRequest(
                provider: $provider,
                request: $request,
                sloUrl: route('sso.saml.slo', ['tenantSlug' => (string) $tenant->slug]),
            );

            [$actorUserId, $localLogoutPerformed, $localLogoutReason] = $this->applyLocalLogoutForIdpRequest(
                request: $request,
                tenant: $tenant,
                logoutRequestInfo: $logoutRequestInfo,
            );

            app(AuditLogService::class)->log(
                tenantId: (int) $tenant->id,
                actorUserId: $actorUserId,
                action: 'auth.sso.saml_logout',
                auditableType: User::class,
                auditableId: $actorUserId,
                data: [
                    'provider' => 'saml',
                    'flow' => 'idp_initiated',
                    'request_id' => (string) ($logoutRequestInfo['request_id'] ?? ''),
                    'request_bytes' => (int) ($logoutRequestInfo['request_bytes'] ?? 0),
                    'name_id' => (string) ($logoutRequestInfo['name_id'] ?? ''),
                    'session_index' => (string) ($logoutRequestInfo['session_index'] ?? ''),
                    'local_logout_performed' => $localLogoutPerformed,
                    'local_logout_reason' => $localLogoutReason,
                ],
                request: $request,
            );

            return app(SamlAuthService::class)->buildIdpInitiatedLogoutResponse(
                provider: $provider,
                metadataUrl: route('sso.saml.metadata', ['tenantSlug' => (string) $tenant->slug]),
                inResponseTo: (string) ($logoutRequestInfo['request_id'] ?? ''),
                relayState: (string) ($logoutRequestInfo['relay_state'] ?? ''),
            );
        } catch (\Throwable $e) {
            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => $e->getMessage(),
                ]);
        }
    }

    /**
     * @param  array{
     *   request_id: string,
     *   relay_state: string,
     *   request_bytes: int,
     *   name_id: string,
     *   session_index: string
     * }  $logoutRequestInfo
     * @return array{0: ?int, 1: bool, 2: string}
     */
    private function applyLocalLogoutForIdpRequest(Request $request, Tenant $tenant, array $logoutRequestInfo): array
    {
        $actorUser = $request->user();
        if (! $actorUser || (int) $actorUser->tenant_id !== (int) $tenant->id) {
            return [null, false, 'not_authenticated'];
        }

        $actorUserId = (int) $actorUser->id;
        $ssoAuth = $request->session()->get('sso_auth');

        if (! is_array($ssoAuth) || (string) ($ssoAuth['provider'] ?? '') !== 'saml') {
            return [$actorUserId, false, 'non_saml_session'];
        }

        if ((int) ($ssoAuth['tenant_id'] ?? 0) !== (int) $tenant->id) {
            return [$actorUserId, false, 'tenant_mismatch'];
        }

        $sessionNameId = trim((string) ($ssoAuth['name_id'] ?? $ssoAuth['provider_subject'] ?? ''));
        $requestNameId = trim((string) ($logoutRequestInfo['name_id'] ?? ''));

        if (
            $sessionNameId !== ''
            && $requestNameId !== ''
            && ! hash_equals(strtolower($sessionNameId), strtolower($requestNameId))
        ) {
            return [$actorUserId, false, 'name_id_mismatch'];
        }

        $this->performLocalLogout($request);

        return [$actorUserId, true, 'saml_session_match'];
    }

    private function performLocalLogout(Request $request): void
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    public function metadata(Request $request, string $tenantSlug)
    {
        $tenantSlug = strtolower(trim($tenantSlug));
        $tenant = Tenant::query()->where('slug', $tenantSlug)->first();

        if (! $tenant) {
            abort(404, 'Workspace not found.');
        }

        $provider = TenantSsoProvider::query()->firstOrNew(
            [
                'tenant_id' => (int) $tenant->id,
                'provider' => 'saml',
            ],
            [
                'is_enabled' => false,
                'metadata' => [
                    'nameid_format' => (string) config('wids.sso.saml.nameid_format_default', 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress'),
                    'enforce_signed_assertion' => (bool) config('wids.sso.saml.require_signed_assertion_default', true),
                    'enforce_signed_logout_request' => (bool) config('wids.sso.saml.require_signed_logout_request_default', true),
                    'enforce_signed_logout_response' => (bool) config('wids.sso.saml.require_signed_logout_response_default', false),
                    'foundation_mode' => false,
                ],
            ]
        );

        $xml = app(SamlAuthService::class)->metadataXml(
            provider: $provider,
            acsUrl: route('sso.saml.acs', ['tenantSlug' => (string) $tenant->slug]),
            metadataUrl: route('sso.saml.metadata', ['tenantSlug' => (string) $tenant->slug]),
            sloUrl: route('sso.saml.slo', ['tenantSlug' => (string) $tenant->slug]),
        );

        return response($xml, 200, [
            'Content-Type' => 'application/samlmetadata+xml; charset=UTF-8',
        ]);
    }

    /**
     * @param  array{
     *   provider_subject: string,
     *   name_id: string,
     *   session_index: string,
     *   email: string,
     *   name: string,
     *   claims: array<string, mixed>,
     *   request_id: string,
     *   relay_state: string,
     *   response_bytes: int
     * }  $profile
     */
    private function resolveUserFromSamlProfile(Tenant $tenant, TenantSsoProvider $provider, array $profile): User
    {
        $providerKey = 'saml';
        $subject = trim((string) ($profile['provider_subject'] ?? ''));
        $email = strtolower(trim((string) ($profile['email'] ?? '')));
        $name = trim((string) ($profile['name'] ?? ''));
        $claims = is_array($profile['claims'] ?? null) ? $profile['claims'] : [];

        if ($subject === '' || $email === '') {
            throw new \RuntimeException('SAML profile is incomplete.');
        }

        $domainAllowlist = is_array($provider->domain_allowlist) ? array_values($provider->domain_allowlist) : [];
        if ($domainAllowlist !== [] && ! $this->isEmailDomainAllowed($email, $domainAllowlist)) {
            throw new \RuntimeException('Email domain is not allowed for this workspace.');
        }

        $identity = UserIdentity::query()
            ->where('tenant_id', (int) $tenant->id)
            ->where('provider', $providerKey)
            ->where('provider_subject', $subject)
            ->first();

        $user = null;

        if ($identity) {
            $user = $identity->user;
        }

        if (! $user) {
            $user = User::query()
                ->where('tenant_id', (int) $tenant->id)
                ->whereRaw('LOWER(email) = ?', [$email])
                ->first();
        }

        if (! $user) {
            $existingGlobal = User::query()
                ->whereRaw('LOWER(email) = ?', [$email])
                ->first();

            if ($existingGlobal && (int) $existingGlobal->tenant_id !== (int) $tenant->id) {
                throw new \RuntimeException('This email is already assigned to another workspace.');
            }

            if (! (bool) ($provider->auto_provision ?? false)) {
                throw new \RuntimeException('No workspace user found for this email. Ask admin to invite your account.');
            }

            $user = User::query()->create([
                'tenant_id' => (int) $tenant->id,
                'role' => (string) ($provider->auto_provision_role === 'owner' ? 'owner' : 'member'),
                'is_active' => true,
                'name' => $name !== '' ? $name : ucfirst((string) strstr($email, '@', true)),
                'email' => $email,
                'email_verified_at' => now(),
                'password' => Str::random(64),
            ]);
        }

        if (! (bool) $user->is_active) {
            throw new \RuntimeException('Workspace account is disabled.');
        }

        if ($name !== '' && trim((string) $user->name) === '') {
            $user->name = $name;
            $user->save();
        }

        $identity = UserIdentity::query()->updateOrCreate(
            [
                'tenant_id' => (int) $tenant->id,
                'provider' => $providerKey,
                'provider_subject' => $subject,
            ],
            [
                'user_id' => (int) $user->id,
                'email' => $email,
                'name' => $name !== '' ? $name : (string) $user->name,
                'last_claims' => $claims,
                'last_login_at' => now(),
            ]
        );

        if ((int) $identity->user_id !== (int) $user->id) {
            throw new \RuntimeException('SAML identity conflict detected for this workspace.');
        }

        return $user;
    }

    /**
     * @param  list<string>  $domainAllowlist
     */
    private function isEmailDomainAllowed(string $email, array $domainAllowlist): bool
    {
        $domain = strtolower(trim((string) strstr($email, '@')));
        $domain = ltrim($domain, '@');

        if ($domain === '') {
            return false;
        }

        foreach ($domainAllowlist as $allowedDomain) {
            if (! is_string($allowedDomain)) {
                continue;
            }

            $allowedDomain = strtolower(trim($allowedDomain));
            $allowedDomain = ltrim($allowedDomain, '@');

            if ($allowedDomain !== '' && $allowedDomain === $domain) {
                return true;
            }
        }

        return false;
    }

    private function loginError(string $message, string $tenantSlug): RedirectResponse
    {
        return redirect()
            ->route('login')
            ->withInput([
                'tenant' => $tenantSlug,
            ])
            ->withErrors([
                'email' => $message,
            ]);
    }

    private function enabledProviderForTenant(int $tenantId): ?TenantSsoProvider
    {
        return TenantSsoProvider::query()
            ->where('tenant_id', $tenantId)
            ->where('provider', 'saml')
            ->where('is_enabled', true)
            ->first();
    }
}
