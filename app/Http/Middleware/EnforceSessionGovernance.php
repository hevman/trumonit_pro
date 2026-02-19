<?php

namespace App\Http\Middleware;

use App\Services\IdentityGovernanceService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceSessionGovernance
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $tenant = $user->tenant;
        if (! $tenant) {
            return $next($request);
        }

        $governance = app(IdentityGovernanceService::class);
        $policy = $governance->policyForTenant($tenant);

        if ($this->isSessionExpiredByPolicy($request, (int) ($policy['session_max_minutes'] ?? 0))) {
            return $this->logoutAndReject($request, 'Your session expired due to workspace security policy. Please sign in again.');
        }

        if ($governance->requiresSsoForUser($user)) {
            $ssoAuth = $request->session()->get('sso_auth');
            $provider = is_array($ssoAuth)
                ? strtolower(trim((string) ($ssoAuth['provider'] ?? '')))
                : '';

            if ($provider === '' || ! $governance->isSsoProviderAllowedForTenant($tenant, $provider)) {
                return $this->logoutAndReject($request, 'Your workspace requires SSO sign-in for this account.');
            }
        }

        return $next($request);
    }

    private function isSessionExpiredByPolicy(Request $request, int $sessionMaxMinutes): bool
    {
        $sessionMaxMinutes = min(43_200, max(0, $sessionMaxMinutes));
        if ($sessionMaxMinutes === 0) {
            return false;
        }

        $startedAt = (int) $request->session()->get('auth_session_started_at', 0);
        if ($startedAt <= 0) {
            $request->session()->put('auth_session_started_at', now()->timestamp);

            return false;
        }

        return (now()->timestamp - $startedAt) > ($sessionMaxMinutes * 60);
    }

    private function logoutAndReject(Request $request, string $message): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 401);
        }

        return redirect()
            ->route('login')
            ->withErrors([
                'email' => $message,
            ]);
    }
}
