<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantSsoProvider;
use App\Services\AuditLogService;
use App\Services\IdentityGovernanceService;
use App\Services\OidcDiscoveryService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SsoSettingsController extends Controller
{
    public function edit(Request $request)
    {
        $tenant = $request->user()?->tenant;
        abort_if(! $tenant, 400, 'User has no tenant.');

        $provider = TenantSsoProvider::query()->firstOrNew(
            [
                'tenant_id' => (int) $tenant->id,
                'provider' => 'oidc',
            ],
            [
                'is_enabled' => false,
                'scopes' => (string) config('wids.sso.oidc.default_scopes', 'openid profile email'),
                'email_claim' => 'email',
                'name_claim' => 'name',
                'subject_claim' => 'sub',
                'auto_provision' => false,
                'auto_provision_role' => 'member',
                'enforce_verified_email' => true,
            ]
        );

        $domainAllowlist = is_array($provider->domain_allowlist) ? array_values($provider->domain_allowlist) : [];
        $identityPolicy = app(IdentityGovernanceService::class)->policyForTenant($tenant);

        return view('app.sso.edit', [
            'provider' => $provider,
            'domainAllowlistText' => implode(PHP_EOL, $domainAllowlist),
            'oidcGlobalEnabled' => (bool) config('wids.sso.oidc.enabled', false),
            'callbackUrl' => route('sso.oidc.callback', ['tenantSlug' => (string) $tenant->slug]),
            'loginStartUrl' => route('sso.oidc.redirect', ['tenant' => (string) $tenant->slug]),
            'identityPolicy' => $identityPolicy,
        ]);
    }

    public function update(Request $request)
    {
        $tenant = $request->user()?->tenant;
        abort_if(! $tenant, 400, 'User has no tenant.');

        $data = $request->validate([
            'oidc_enabled' => ['nullable', 'boolean'],
            'issuer_url' => ['nullable', 'url', 'max:2048'],
            'authorization_endpoint' => ['nullable', 'url', 'max:2048'],
            'token_endpoint' => ['nullable', 'url', 'max:2048'],
            'userinfo_endpoint' => ['nullable', 'url', 'max:2048'],
            'jwks_uri' => ['nullable', 'url', 'max:2048'],
            'client_id' => ['nullable', 'string', 'max:255'],
            'client_secret' => ['nullable', 'string', 'max:5000'],
            'scopes' => ['nullable', 'string', 'max:255'],
            'email_claim' => ['nullable', 'string', 'max:64'],
            'name_claim' => ['nullable', 'string', 'max:64'],
            'subject_claim' => ['nullable', 'string', 'max:64'],
            'domain_allowlist' => ['nullable', 'string', 'max:5000'],
            'auto_provision' => ['nullable', 'boolean'],
            'auto_provision_role' => ['nullable', 'in:member,owner'],
            'enforce_verified_email' => ['nullable', 'boolean'],
            'identity_enforce_sso_for' => ['nullable', 'in:off,owner,all'],
            'identity_allow_password_login' => ['nullable', 'boolean'],
            'identity_allow_oidc' => ['nullable', 'boolean'],
            'identity_allow_saml' => ['nullable', 'boolean'],
            'identity_default_sso_provider' => ['nullable', 'in:auto,oidc,saml'],
            'identity_sso_domain_routing' => ['nullable', 'string', 'max:5000'],
            'identity_session_max_minutes' => ['nullable', 'integer', 'min:0', 'max:43200'],
        ]);

        $provider = TenantSsoProvider::query()->firstOrNew([
            'tenant_id' => (int) $tenant->id,
            'provider' => 'oidc',
        ]);

        $before = $this->auditPayload($provider);
        $identityGovernanceBefore = app(IdentityGovernanceService::class)->policyForTenant($tenant);

        $oidcEnabled = (bool) ($data['oidc_enabled'] ?? false);
        $issuerUrl = $this->normalizeOptionalString($data['issuer_url'] ?? null);
        $authorizationEndpoint = $this->normalizeOptionalString($data['authorization_endpoint'] ?? null);
        $tokenEndpoint = $this->normalizeOptionalString($data['token_endpoint'] ?? null);
        $userinfoEndpoint = $this->normalizeOptionalString($data['userinfo_endpoint'] ?? null);
        $jwksUri = $this->normalizeOptionalString($data['jwks_uri'] ?? null);
        $clientId = $this->normalizeOptionalString($data['client_id'] ?? null);
        $clientSecret = $this->normalizeOptionalString($data['client_secret'] ?? null);
        $scopes = $this->normalizeOptionalString($data['scopes'] ?? null);
        $emailClaim = $this->normalizeOptionalString($data['email_claim'] ?? null);
        $nameClaim = $this->normalizeOptionalString($data['name_claim'] ?? null);
        $subjectClaim = $this->normalizeOptionalString($data['subject_claim'] ?? null);
        $domainAllowlist = $this->parseDomainAllowlist((string) ($data['domain_allowlist'] ?? ''));
        $autoProvision = (bool) ($data['auto_provision'] ?? false);
        $autoProvisionRole = $autoProvision ? (string) ($data['auto_provision_role'] ?? 'member') : 'member';
        $enforceVerifiedEmail = (bool) ($data['enforce_verified_email'] ?? false);
        $identityEnforceSsoFor = strtolower(trim((string) ($data['identity_enforce_sso_for'] ?? 'off')));
        if (! in_array($identityEnforceSsoFor, ['off', 'owner', 'all'], true)) {
            $identityEnforceSsoFor = 'off';
        }

        $identityAllowPasswordLogin = (bool) ($data['identity_allow_password_login'] ?? true);
        $identityAllowSsoOidc = (bool) ($data['identity_allow_oidc'] ?? true);
        $identityAllowSsoSaml = (bool) ($data['identity_allow_saml'] ?? true);
        $identityDefaultSsoProvider = strtolower(trim((string) ($data['identity_default_sso_provider'] ?? 'auto')));
        if (! in_array($identityDefaultSsoProvider, ['auto', 'oidc', 'saml'], true)) {
            $identityDefaultSsoProvider = 'auto';
        }

        $identitySsoDomainRouting = $this->parseSsoDomainRouting((string) ($data['identity_sso_domain_routing'] ?? ''));
        $identitySessionMaxMinutes = max(0, min(43_200, (int) ($data['identity_session_max_minutes'] ?? 0)));
        $identityAllowedSsoProviders = [];
        if ($identityAllowSsoOidc) {
            $identityAllowedSsoProviders['oidc'] = true;
        }

        if ($identityAllowSsoSaml) {
            $identityAllowedSsoProviders['saml'] = true;
        }

        $identityAllowedSsoProviders = array_values(array_keys($identityAllowedSsoProviders));

        if (
            $identityDefaultSsoProvider !== 'auto'
            && ! in_array($identityDefaultSsoProvider, $identityAllowedSsoProviders, true)
        ) {
            throw ValidationException::withMessages([
                'identity_default_sso_provider' => ['Default SSO provider must be enabled in allowed providers list.'],
            ]);
        }

        foreach ($identitySsoDomainRouting as $domain => $providerForDomain) {
            if (! in_array($providerForDomain, $identityAllowedSsoProviders, true)) {
                throw ValidationException::withMessages([
                    'identity_sso_domain_routing' => ['Domain route "'.$domain.'" points to provider not allowed by policy.'],
                ]);
            }
        }

        if (
            ($identityEnforceSsoFor !== 'off' || ! $identityAllowPasswordLogin)
            && ! $this->hasAvailableSsoProviderForPolicy($tenant, $identityAllowedSsoProviders, $oidcEnabled)
        ) {
            throw ValidationException::withMessages([
                'identity_enforce_sso_for' => ['At least one allowed and enabled SSO provider is required when password login is restricted.'],
            ]);
        }

        $provider->is_enabled = $oidcEnabled;
        $provider->issuer_url = $issuerUrl;
        $provider->authorization_endpoint = $authorizationEndpoint;
        $provider->token_endpoint = $tokenEndpoint;
        $provider->userinfo_endpoint = $userinfoEndpoint;
        $provider->jwks_uri = $jwksUri;
        $provider->client_id = $clientId;
        $provider->scopes = $scopes !== '' ? $scopes : (string) config('wids.sso.oidc.default_scopes', 'openid profile email');
        $provider->email_claim = $emailClaim !== '' ? $emailClaim : 'email';
        $provider->name_claim = $nameClaim !== '' ? $nameClaim : 'name';
        $provider->subject_claim = $subjectClaim !== '' ? $subjectClaim : 'sub';
        $provider->domain_allowlist = $domainAllowlist;
        $provider->auto_provision = $autoProvision;
        $provider->auto_provision_role = $autoProvisionRole === 'owner' ? 'owner' : 'member';
        $provider->enforce_verified_email = $enforceVerifiedEmail;
        $provider->last_error = null;

        if ($clientSecret !== '') {
            $provider->client_secret = $clientSecret;
        }

        if ($oidcEnabled) {
            $this->validateRequiredForEnabledProvider($provider);
        }

        if (is_string($provider->issuer_url) && trim($provider->issuer_url) !== '') {
            try {
                $discovered = app(OidcDiscoveryService::class)->discover((string) $provider->issuer_url);

                if ($provider->authorization_endpoint === null || trim((string) $provider->authorization_endpoint) === '') {
                    $provider->authorization_endpoint = (string) ($discovered['authorization_endpoint'] ?? '');
                }

                if ($provider->token_endpoint === null || trim((string) $provider->token_endpoint) === '') {
                    $provider->token_endpoint = (string) ($discovered['token_endpoint'] ?? '');
                }

                if ($provider->userinfo_endpoint === null || trim((string) $provider->userinfo_endpoint) === '') {
                    $provider->userinfo_endpoint = (string) ($discovered['userinfo_endpoint'] ?? '');
                }

                if ($provider->jwks_uri === null || trim((string) $provider->jwks_uri) === '') {
                    $provider->jwks_uri = (string) ($discovered['jwks_uri'] ?? '');
                }

                $provider->last_tested_at = now();
                $provider->last_error = null;
            } catch (\Throwable $e) {
                if ($oidcEnabled) {
                    throw ValidationException::withMessages([
                        'issuer_url' => ['OIDC discovery failed: '.$e->getMessage()],
                    ]);
                }

                $provider->last_error = $e->getMessage();
            }
        }

        if ($oidcEnabled) {
            $this->validateRequiredForEnabledProvider($provider);
        }

        $provider->save();
        $tenant->identity_governance = [
            'enforce_sso_for' => $identityEnforceSsoFor,
            'allow_password_login' => $identityAllowPasswordLogin,
            'allowed_sso_providers' => $identityAllowedSsoProviders,
            'session_max_minutes' => $identitySessionMaxMinutes,
            'default_sso_provider' => $identityDefaultSsoProvider,
            'sso_domain_routing' => $identitySsoDomainRouting,
        ];
        $tenant->save();
        $identityGovernanceAfter = app(IdentityGovernanceService::class)->policyForTenant($tenant);

        app(AuditLogService::class)->log(
            tenantId: (int) $tenant->id,
            actorUserId: $request->user()?->id,
            action: 'tenant.sso.oidc_updated',
            auditableType: Tenant::class,
            auditableId: (int) $tenant->id,
            data: [
                'before' => $before,
                'after' => $this->auditPayload($provider),
                'identity_governance_before' => $identityGovernanceBefore,
                'identity_governance_after' => $identityGovernanceAfter,
            ],
            request: $request,
        );

        return redirect()
            ->route('sso.edit')
            ->with('status', 'SSO settings updated.');
    }

    private function validateRequiredForEnabledProvider(TenantSsoProvider $provider): void
    {
        $missing = [];

        if (! is_string($provider->issuer_url) || trim($provider->issuer_url) === '') {
            $missing[] = 'issuer_url';
        }

        if (! is_string($provider->client_id) || trim($provider->client_id) === '') {
            $missing[] = 'client_id';
        }

        if (! is_string($provider->client_secret) || trim((string) $provider->client_secret) === '') {
            $missing[] = 'client_secret';
        }

        if ($missing !== []) {
            $messages = [];

            foreach ($missing as $field) {
                $messages[$field] = ['This field is required when OIDC SSO is enabled.'];
            }

            throw ValidationException::withMessages($messages);
        }
    }

    /**
     * @return list<string>
     */
    private function parseDomainAllowlist(string $input): array
    {
        $domains = [];
        $parts = preg_split('/[\r\n,;]+/', $input) ?: [];

        foreach ($parts as $part) {
            $domain = strtolower(trim((string) $part));
            $domain = ltrim($domain, '@');

            if ($domain === '') {
                continue;
            }

            if (preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain) !== 1) {
                throw ValidationException::withMessages([
                    'domain_allowlist' => ['Invalid domain: '.$domain],
                ]);
            }

            $domains[$domain] = true;
        }

        return array_values(array_keys($domains));
    }

    /**
     * @return array{
     *   is_enabled: bool,
     *   issuer_url: ?string,
     *   authorization_endpoint: ?string,
     *   token_endpoint: ?string,
     *   userinfo_endpoint: ?string,
     *   jwks_uri: ?string,
     *   client_id: ?string,
     *   client_secret_set: bool,
     *   scopes: ?string,
     *   email_claim: ?string,
     *   name_claim: ?string,
     *   subject_claim: ?string,
     *   domain_allowlist: list<string>,
     *   auto_provision: bool,
     *   auto_provision_role: string,
     *   enforce_verified_email: bool,
     *   last_error: ?string
     * }
     */
    private function auditPayload(TenantSsoProvider $provider): array
    {
        return [
            'is_enabled' => (bool) $provider->is_enabled,
            'issuer_url' => $provider->issuer_url,
            'authorization_endpoint' => $provider->authorization_endpoint,
            'token_endpoint' => $provider->token_endpoint,
            'userinfo_endpoint' => $provider->userinfo_endpoint,
            'jwks_uri' => $provider->jwks_uri,
            'client_id' => $provider->client_id,
            'client_secret_set' => is_string($provider->client_secret) && trim($provider->client_secret) !== '',
            'scopes' => $provider->scopes,
            'email_claim' => $provider->email_claim,
            'name_claim' => $provider->name_claim,
            'subject_claim' => $provider->subject_claim,
            'domain_allowlist' => is_array($provider->domain_allowlist) ? array_values($provider->domain_allowlist) : [],
            'auto_provision' => (bool) $provider->auto_provision,
            'auto_provision_role' => is_string($provider->auto_provision_role) ? $provider->auto_provision_role : 'member',
            'enforce_verified_email' => (bool) $provider->enforce_verified_email,
            'last_error' => is_string($provider->last_error) ? $provider->last_error : null,
        ];
    }

    private function normalizeOptionalString(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return trim($value);
    }

    /**
     * @return array<string, string>
     */
    private function parseSsoDomainRouting(string $input): array
    {
        $result = [];
        $lines = preg_split('/[\r\n]+/', $input) ?: [];

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            if (! preg_match('/^([^\s=:;]+)\s*[:=;\s]\s*(oidc|saml)$/i', $line, $matches)) {
                throw ValidationException::withMessages([
                    'identity_sso_domain_routing' => ['Invalid domain routing row: "'.$line.'". Use format "domain provider".'],
                ]);
            }

            $domain = strtolower(trim((string) ($matches[1] ?? '')));
            $domain = ltrim($domain, '@');
            $provider = strtolower(trim((string) ($matches[2] ?? '')));

            if ($domain === '' || preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain) !== 1) {
                throw ValidationException::withMessages([
                    'identity_sso_domain_routing' => ['Invalid domain in routing row: "'.$line.'".'],
                ]);
            }

            $result[$domain] = $provider;
        }

        return $result;
    }

    /**
     * @param  list<string>  $allowedProviders
     */
    private function hasAvailableSsoProviderForPolicy(Tenant $tenant, array $allowedProviders, bool $pendingOidcEnabled): bool
    {
        foreach ($allowedProviders as $provider) {
            if (! is_string($provider)) {
                continue;
            }

            $provider = strtolower(trim($provider));
            if ($provider === 'oidc') {
                if (! (bool) config('wids.sso.oidc.enabled', false)) {
                    continue;
                }

                if ($pendingOidcEnabled) {
                    return true;
                }

                $oidcEnabled = TenantSsoProvider::query()
                    ->where('tenant_id', (int) $tenant->id)
                    ->where('provider', 'oidc')
                    ->where('is_enabled', true)
                    ->exists();

                if ($oidcEnabled) {
                    return true;
                }

                continue;
            }

            if ($provider === 'saml') {
                if (! (bool) config('wids.sso.saml.enabled', false)) {
                    continue;
                }

                $samlEnabled = TenantSsoProvider::query()
                    ->where('tenant_id', (int) $tenant->id)
                    ->where('provider', 'saml')
                    ->where('is_enabled', true)
                    ->exists();

                if ($samlEnabled) {
                    return true;
                }
            }
        }

        return false;
    }
}
