<?php

namespace App\Providers;

use App\Services\TenantQuotaService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\RateLimiter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('register', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('api-auth', function (Request $request) {
            $email = (string) $request->input('email');

            return Limit::perMinute(20)->by(strtolower($email).'|'.$request->ip());
        });

        RateLimiter::for('api-v1-public', function (Request $request) {
            $publicPerMinute = max(1, (int) config('wids.api.rate_limits.public_requests_per_minute', 60));
            $email = trim(strtolower((string) $request->input('email')));
            $key = 'api-v1-public|'.$request->ip();

            if ($email !== '') {
                $key .= '|'.$email;
            }

            return Limit::perMinute($publicPerMinute)->by($key);
        });

        RateLimiter::for('api-v1', function (Request $request) {
            $user = $request->user();
            $defaultPerMinute = max(1, (int) config('wids.api.rate_limits.min_authenticated_requests_per_minute', 60));

            if ($user === null) {
                return Limit::perMinute($defaultPerMinute)->by('api-v1-anon|'.$request->ip());
            }

            $tenantId = (int) ($user->tenant_id ?? 0);
            $userId = (int) ($user->id ?? 0);
            $tenantRpm = 0;

            if ($tenantId > 0) {
                $limits = app(TenantQuotaService::class)->limitsForTenantId($tenantId);
                $tenantRpm = max(0, (int) ($limits['tenant_rpm'] ?? 0));
            }

            $tenantSharePercent = min(100, max(1, (int) config('wids.api.rate_limits.tenant_rpm_share_percent', 50)));
            $perUserSharePercent = min(100, max(1, (int) config('wids.api.rate_limits.per_user_share_percent', 50)));
            $unlimitedTenantPerMinute = max(
                $defaultPerMinute,
                (int) config('wids.api.rate_limits.unlimited_tenant_requests_per_minute', 1200)
            );

            $tenantPerMinute = $tenantRpm > 0
                ? max($defaultPerMinute, (int) ceil($tenantRpm * $tenantSharePercent / 100))
                : $unlimitedTenantPerMinute;

            $userPerMinute = max(1, (int) ceil($tenantPerMinute * $perUserSharePercent / 100));

            $limits = [
                Limit::perMinute($tenantPerMinute)->by('api-v1-tenant|'.$tenantId),
            ];

            if ($userId > 0) {
                $limits[] = Limit::perMinute($userPerMinute)->by('api-v1-user|'.$userId);
            }

            return $limits;
        });

        RateLimiter::for('oidc-auth', function (Request $request) {
            $tenantSlug = strtolower(trim((string) ($request->route('tenantSlug') ?? $request->query('tenant', ''))));
            $tenantSlug = $tenantSlug !== '' ? $tenantSlug : 'unknown';

            return Limit::perMinute(30)->by('oidc-auth|'.$tenantSlug.'|'.$request->ip());
        });

        RateLimiter::for('saml-auth', function (Request $request) {
            $tenantSlug = strtolower(trim((string) ($request->route('tenantSlug') ?? $request->query('tenant', ''))));
            $tenantSlug = $tenantSlug !== '' ? $tenantSlug : 'unknown';

            return Limit::perMinute(30)->by('saml-auth|'.$tenantSlug.'|'.$request->ip());
        });
    }
}
