<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\IdentityGovernanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SsoSessionController extends Controller
{
    public function redirectByTenantSlug(Request $request): RedirectResponse
    {
        $tenantSlug = strtolower(trim((string) $request->query('tenant', '')));
        $email = strtolower(trim((string) $request->query('email', '')));

        if (preg_match('/^[a-z0-9-]{2,80}$/', $tenantSlug) !== 1) {
            return $this->loginError('Enter a valid workspace slug to continue with SSO.', $tenantSlug, $email);
        }

        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->loginError('Enter a valid email address for SSO routing.', $tenantSlug, $email);
        }

        $tenant = Tenant::query()->where('slug', $tenantSlug)->first();
        if (! $tenant) {
            return $this->loginError('Workspace not found for provided slug.', $tenantSlug, $email);
        }

        $provider = app(IdentityGovernanceService::class)->resolveSsoProviderForTenant($tenant, $email);
        if (! is_string($provider) || $provider === '') {
            return $this->loginError('No allowed SSO provider is enabled for this workspace.', $tenantSlug, $email);
        }

        $targetRoute = match ($provider) {
            'saml' => route('sso.saml.redirect', ['tenant' => (string) $tenant->slug]),
            default => route('sso.oidc.redirect', ['tenant' => (string) $tenant->slug]),
        };

        return redirect()->to($targetRoute);
    }

    private function loginError(string $message, string $tenantSlug, string $email): RedirectResponse
    {
        $input = [
            'tenant' => $tenantSlug,
        ];

        if ($email !== '') {
            $input['sso_email'] = $email;
        }

        return redirect()
            ->route('login')
            ->withInput($input)
            ->withErrors([
                'email' => $message,
            ]);
    }
}
