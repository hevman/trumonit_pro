<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantSsoProvider;
use App\Models\User;
use App\Models\UserIdentity;
use App\Services\AuditLogService;
use App\Services\IdentityGovernanceService;
use App\Services\OidcAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class OidcSessionController extends Controller
{
    public function redirectByTenantSlug(Request $request): RedirectResponse
    {
        $tenantSlug = strtolower(trim((string) $request->query('tenant', '')));

        if (preg_match('/^[a-z0-9-]{2,80}$/', $tenantSlug) !== 1) {
            return $this->loginError('Enter a valid workspace slug to continue with SSO.', $tenantSlug);
        }

        $tenant = Tenant::query()->where('slug', $tenantSlug)->first();

        if (! $tenant) {
            return $this->loginError('Workspace not found for provided slug.', $tenantSlug);
        }

        if (! app(IdentityGovernanceService::class)->isSsoProviderAllowedForTenant($tenant, 'oidc')) {
            return $this->loginError('OIDC SSO is disabled by workspace policy.', $tenantSlug);
        }

        $provider = $this->enabledProviderForTenant((int) $tenant->id);

        if (! $provider) {
            return $this->loginError('SSO is not enabled for this workspace.', $tenantSlug);
        }

        try {
            return app(OidcAuthService::class)->beginAuthorization(
                provider: $provider,
                request: $request,
                callbackUrl: route('sso.oidc.callback', ['tenantSlug' => (string) $tenant->slug]),
            );
        } catch (\Throwable $e) {
            return $this->loginError($e->getMessage(), $tenantSlug);
        }
    }

    public function callback(Request $request, string $tenantSlug): RedirectResponse
    {
        $tenantSlug = strtolower(trim($tenantSlug));
        $tenant = Tenant::query()->where('slug', $tenantSlug)->first();

        if (! $tenant) {
            return $this->loginError('Workspace not found for SSO callback.', $tenantSlug);
        }

        if (! app(IdentityGovernanceService::class)->isSsoProviderAllowedForTenant($tenant, 'oidc')) {
            return $this->loginError('OIDC SSO is disabled by workspace policy.', $tenantSlug);
        }

        $provider = $this->enabledProviderForTenant((int) $tenant->id);

        if (! $provider) {
            return $this->loginError('SSO is not enabled for this workspace.', $tenantSlug);
        }

        try {
            $profile = app(OidcAuthService::class)->consumeAuthorizationCode(
                provider: $provider,
                request: $request,
                callbackUrl: route('sso.oidc.callback', ['tenantSlug' => (string) $tenant->slug]),
            );

            $user = $this->resolveUserFromOidcProfile($tenant, $provider, $profile);

            Auth::login($user);
            $request->session()->regenerate();
            $request->session()->put('auth_session_started_at', now()->timestamp);
            $request->session()->put('sso_auth', [
                'provider' => 'oidc',
                'tenant_id' => (int) $tenant->id,
                'tenant_slug' => (string) $tenant->slug,
                'provider_subject' => (string) ($profile['provider_subject'] ?? ''),
            ]);

            app(AuditLogService::class)->log(
                tenantId: (int) $tenant->id,
                actorUserId: (int) $user->id,
                action: 'auth.sso.oidc_login',
                auditableType: User::class,
                auditableId: (int) $user->id,
                data: [
                    'provider' => (string) ($provider->provider ?? 'oidc'),
                    'provider_subject' => (string) ($profile['provider_subject'] ?? ''),
                    'email' => (string) ($profile['email'] ?? ''),
                ],
                request: $request,
            );

            return redirect()->intended(route('dashboard', absolute: false));
        } catch (\Throwable $e) {
            return $this->loginError($e->getMessage(), $tenantSlug);
        }
    }

    /**
     * @param  array{
     *   provider_subject: string,
     *   email: string,
     *   name: string,
     *   claims: array<string, mixed>
     * }  $profile
     */
    private function resolveUserFromOidcProfile(Tenant $tenant, TenantSsoProvider $provider, array $profile): User
    {
        $providerKey = trim((string) ($provider->provider ?? 'oidc')) ?: 'oidc';
        $subject = trim((string) ($profile['provider_subject'] ?? ''));
        $email = strtolower(trim((string) ($profile['email'] ?? '')));
        $name = trim((string) ($profile['name'] ?? ''));
        $claims = is_array($profile['claims'] ?? null) ? $profile['claims'] : [];

        if ($subject === '' || $email === '') {
            throw new \RuntimeException('OIDC profile is incomplete.');
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
            throw new \RuntimeException('OIDC identity conflict detected for this workspace.');
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
            ->where('provider', 'oidc')
            ->where('is_enabled', true)
            ->first();
    }
}
