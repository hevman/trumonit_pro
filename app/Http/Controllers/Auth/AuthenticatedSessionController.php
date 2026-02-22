<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\TenantSsoProvider;
use App\Services\SamlAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();
        $request->session()->put('auth_session_started_at', now()->timestamp);
        $request->session()->forget('sso_auth');

        return redirect()->intended(route('monitored-urls.index', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        if (! $this->isLogoutRequestAuthorized($request)) {
            return redirect('/');
        }

        $samlSloRedirect = $this->startSamlSingleLogoutIfApplicable($request);
        if ($samlSloRedirect instanceof RedirectResponse) {
            return $samlSloRedirect;
        }

        return $this->performLocalLogout($request);
    }

    private function isLogoutRequestAuthorized(Request $request): bool
    {
        if (! $request->isMethod('get')) {
            return true;
        }

        $provided = (string) $request->query('_logout');
        $sessionToken = (string) $request->session()->token();

        return $provided !== '' && $sessionToken !== '' && hash_equals($sessionToken, $provided);
    }

    private function startSamlSingleLogoutIfApplicable(Request $request): ?RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        $ssoAuth = $request->session()->get('sso_auth');
        if (! is_array($ssoAuth) || (string) ($ssoAuth['provider'] ?? '') !== 'saml') {
            return null;
        }

        if ((int) ($ssoAuth['tenant_id'] ?? 0) !== (int) ($user->tenant_id ?? 0)) {
            return null;
        }

        $tenantSlug = strtolower(trim((string) ($ssoAuth['tenant_slug'] ?? ($user->tenant?->slug ?? ''))));
        if ($tenantSlug === '') {
            return null;
        }

        $provider = TenantSsoProvider::query()
            ->where('tenant_id', (int) $user->tenant_id)
            ->where('provider', 'saml')
            ->where('is_enabled', true)
            ->first();

        if (! $provider) {
            return null;
        }

        $samlAuth = app(SamlAuthService::class);
        if (! $samlAuth->canBeginSingleLogout($provider)) {
            return null;
        }

        $nameId = trim((string) ($ssoAuth['name_id'] ?? $ssoAuth['provider_subject'] ?? ''));
        if ($nameId === '') {
            return null;
        }

        $sessionIndex = trim((string) ($ssoAuth['session_index'] ?? ''));
        $sessionIndex = $sessionIndex !== '' ? $sessionIndex : null;

        try {
            return $samlAuth->beginSingleLogout(
                provider: $provider,
                request: $request,
                sloUrl: route('sso.saml.slo', ['tenantSlug' => $tenantSlug]),
                metadataUrl: route('sso.saml.metadata', ['tenantSlug' => $tenantSlug]),
                nameId: $nameId,
                sessionIndex: $sessionIndex,
            );
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function performLocalLogout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
