<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TenantSsoProvider;
use App\Models\User;

class IdentityGovernanceService
{
    /**
     * @return array{
     *   enforce_sso_for: string,
     *   allow_password_login: bool,
     *   allowed_sso_providers: list<string>,
     *   session_max_minutes: int,
     *   default_sso_provider: string,
     *   sso_domain_routing: array<string, string>
     * }
     */
    public function policyForTenant(Tenant $tenant): array
    {
        $policy = is_array($tenant->identity_governance) ? $tenant->identity_governance : [];
        $enforceSsoFor = strtolower(trim((string) ($policy['enforce_sso_for'] ?? 'off')));
        if (! in_array($enforceSsoFor, ['off', 'owner', 'all'], true)) {
            $enforceSsoFor = 'off';
        }

        $allowPasswordLogin = ! array_key_exists('allow_password_login', $policy)
            ? true
            : filter_var($policy['allow_password_login'], FILTER_VALIDATE_BOOL);

        $allowedProviders = [];
        $rawAllowedProviders = is_array($policy['allowed_sso_providers'] ?? null)
            ? $policy['allowed_sso_providers']
            : ['oidc', 'saml'];

        foreach ($rawAllowedProviders as $provider) {
            if (! is_string($provider)) {
                continue;
            }

            $provider = strtolower(trim($provider));
            if (! in_array($provider, ['oidc', 'saml'], true)) {
                continue;
            }

            $allowedProviders[$provider] = true;
        }

        if ($allowedProviders === []) {
            $allowedProviders = ['oidc' => true, 'saml' => true];
        }

        $sessionMaxMinutes = max(
            0,
            min(43_200, (int) ($policy['session_max_minutes'] ?? 0))
        );

        $defaultSsoProvider = strtolower(trim((string) ($policy['default_sso_provider'] ?? 'auto')));
        if (! in_array($defaultSsoProvider, ['auto', 'oidc', 'saml'], true)) {
            $defaultSsoProvider = 'auto';
        }

        $ssoDomainRouting = [];
        $rawDomainRouting = is_array($policy['sso_domain_routing'] ?? null)
            ? $policy['sso_domain_routing']
            : [];

        foreach ($rawDomainRouting as $domain => $provider) {
            if (! is_string($domain) || ! is_string($provider)) {
                continue;
            }

            $domain = strtolower(trim($domain));
            $domain = ltrim($domain, '@');
            if ($domain === '' || preg_match('/^[a-z0-9][a-z0-9.-]*\.[a-z]{2,}$/', $domain) !== 1) {
                continue;
            }

            $provider = strtolower(trim($provider));
            if (! in_array($provider, ['oidc', 'saml'], true)) {
                continue;
            }

            $ssoDomainRouting[$domain] = $provider;
        }

        return [
            'enforce_sso_for' => $enforceSsoFor,
            'allow_password_login' => (bool) $allowPasswordLogin,
            'allowed_sso_providers' => array_values(array_keys($allowedProviders)),
            'session_max_minutes' => $sessionMaxMinutes,
            'default_sso_provider' => $defaultSsoProvider,
            'sso_domain_routing' => $ssoDomainRouting,
        ];
    }

    public function requiresSsoForUser(User $user): bool
    {
        $tenant = $user->tenant;
        if (! $tenant) {
            return false;
        }

        $policy = $this->policyForTenant($tenant);
        $mode = (string) ($policy['enforce_sso_for'] ?? 'off');

        if ($mode === 'all') {
            return true;
        }

        if ($mode === 'owner') {
            return (string) ($user->role ?? 'member') === 'owner';
        }

        return false;
    }

    public function isPasswordLoginAllowedForUser(User $user): bool
    {
        $tenant = $user->tenant;
        if (! $tenant) {
            return true;
        }

        $policy = $this->policyForTenant($tenant);
        if (! (bool) ($policy['allow_password_login'] ?? true)) {
            return false;
        }

        if ($this->requiresSsoForUser($user)) {
            return false;
        }

        return true;
    }

    public function isSsoProviderAllowedForTenant(Tenant $tenant, string $provider): bool
    {
        $provider = strtolower(trim($provider));
        if (! in_array($provider, ['oidc', 'saml'], true)) {
            return false;
        }

        $policy = $this->policyForTenant($tenant);
        $allowedProviders = is_array($policy['allowed_sso_providers'] ?? null)
            ? $policy['allowed_sso_providers']
            : ['oidc', 'saml'];

        return in_array($provider, $allowedProviders, true);
    }

    public function resolveSsoProviderForTenant(Tenant $tenant, ?string $email = null): ?string
    {
        $policy = $this->policyForTenant($tenant);
        $allowedProviders = is_array($policy['allowed_sso_providers'] ?? null)
            ? $policy['allowed_sso_providers']
            : ['oidc', 'saml'];
        $enabledProviders = $this->enabledSsoProvidersForTenant($tenant);
        $candidates = array_values(array_intersect($allowedProviders, $enabledProviders));

        if ($candidates === []) {
            return null;
        }

        $domain = $this->extractEmailDomain($email);
        $domainRouting = is_array($policy['sso_domain_routing'] ?? null)
            ? $policy['sso_domain_routing']
            : [];

        if ($domain !== null) {
            $mappedProvider = $domainRouting[$domain] ?? null;
            if (is_string($mappedProvider) && in_array($mappedProvider, $candidates, true)) {
                return $mappedProvider;
            }
        }

        $defaultProvider = strtolower(trim((string) ($policy['default_sso_provider'] ?? 'auto')));
        if ($defaultProvider !== 'auto' && in_array($defaultProvider, $candidates, true)) {
            return $defaultProvider;
        }

        if (count($candidates) === 1) {
            return $candidates[0];
        }

        if (in_array('oidc', $candidates, true)) {
            return 'oidc';
        }

        if (in_array('saml', $candidates, true)) {
            return 'saml';
        }

        return $candidates[0] ?? null;
    }

    /**
     * @return list<string>
     */
    private function enabledSsoProvidersForTenant(Tenant $tenant): array
    {
        $providers = TenantSsoProvider::query()
            ->where('tenant_id', (int) $tenant->id)
            ->where('is_enabled', true)
            ->whereIn('provider', ['oidc', 'saml'])
            ->pluck('provider')
            ->all();
        $enabled = [];

        foreach ($providers as $provider) {
            if (! is_string($provider)) {
                continue;
            }

            $provider = strtolower(trim($provider));
            if ($provider === 'oidc' && ! (bool) config('wids.sso.oidc.enabled', false)) {
                continue;
            }

            if ($provider === 'saml' && ! (bool) config('wids.sso.saml.enabled', false)) {
                continue;
            }

            if (in_array($provider, ['oidc', 'saml'], true)) {
                $enabled[$provider] = true;
            }
        }

        return array_values(array_keys($enabled));
    }

    private function extractEmailDomain(?string $email): ?string
    {
        if (! is_string($email)) {
            return null;
        }

        $email = strtolower(trim($email));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $domain = strtolower(trim((string) strstr($email, '@')));
        $domain = ltrim($domain, '@');

        return $domain !== '' ? $domain : null;
    }
}
