<?php

namespace App\Http\Controllers\Ops;

use App\Models\Incident;
use App\Models\MonitoredUrl;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BusinessSummaryController
{
    public function __invoke(Request $request): JsonResponse
    {
        if (! (bool) config('ops.business.enabled', false)) {
            abort(404);
        }
        $this->assertIpAllowed($request);

        $requireToken = (bool) config('ops.business.require_token', true);
        $token = trim((string) config('ops.business.token', ''));

        if ($token !== '') {
            $provided = $request->bearerToken();

            if (! is_string($provided) || ! hash_equals($token, $provided)) {
                abort(403);
            }
        } elseif ($requireToken) {
            abort(503);
        }

        $cacheTtlSeconds = $this->normalizeCacheTtl((int) config('ops.business.cache_ttl_seconds', 30));

        if ($cacheTtlSeconds > 0) {
            $payload = Cache::remember(
                'ops:business-summary:v1',
                now()->addSeconds($cacheTtlSeconds),
                fn (): array => $this->buildPayload()
            );
        } else {
            $payload = $this->buildPayload();
        }

        return response()->json($payload);
    }

    /**
     * @return array{
     *   service: string,
     *   generated_at: string,
     *   accounts_total: int,
     *   subscribed_accounts_total: int,
     *   free_accounts_total: int,
     *   users_total: int,
     *   plans: list<array{plan:string,accounts:int}>,
     *   counters: array{
     *      tenants_total:int,
     *      monitored_urls_total:int,
     *      open_incidents_total:int
     *   }
     * }
     */
    private function buildPayload(): array
    {
        $defaultTier = strtolower(trim((string) config('wids.default_plan_tier', 'lite')));
        $defaultTier = $defaultTier !== '' ? $defaultTier : 'lite';

        $paidStatuses = config('ops.business.paid_subscription_statuses', ['active', 'trialing', 'manual_active', 'past_due']);
        $paidStatuses = is_array($paidStatuses)
            ? array_values(array_filter(array_map(
                static fn ($status): string => strtolower(trim((string) $status)),
                $paidStatuses
            )))
            : [];

        $tenantsTotal = (int) Tenant::query()->count();
        $usersTotal = (int) User::query()->count();
        $monitoredUrlsTotal = (int) MonitoredUrl::query()->count();
        $openIncidentsTotal = (int) Incident::query()->where('status', 'open')->count();

        $plans = Tenant::query()
            ->selectRaw('LOWER(COALESCE(NULLIF(plan_tier, \'\'), ?)) as plan_tier', [$defaultTier])
            ->selectRaw('COUNT(*) as accounts')
            ->groupBy('plan_tier')
            ->orderByDesc('accounts')
            ->get()
            ->map(static fn (Tenant $row): array => [
                'plan' => is_string($row->plan_tier) && trim((string) $row->plan_tier) !== '' ? trim((string) $row->plan_tier) : $defaultTier,
                'accounts' => max(0, (int) $row->accounts),
            ])
            ->values()
            ->all();

        $subscribedAccountsTotal = (int) Tenant::query()
            ->where(function ($query) use ($defaultTier, $paidStatuses): void {
                $query->whereRaw(
                    'LOWER(COALESCE(NULLIF(plan_tier, \'\'), ?)) <> ?',
                    [$defaultTier, $defaultTier]
                );

                if ($paidStatuses !== []) {
                    $query->orWhereIn('billing_subscription_status', $paidStatuses);
                }
            })
            ->count();

        $subscribedAccountsTotal = min($subscribedAccountsTotal, $tenantsTotal);
        $freeAccountsTotal = max(0, $tenantsTotal - $subscribedAccountsTotal);

        return [
            'service' => 'TruMonit',
            'generated_at' => now()->toISOString(),
            'accounts_total' => $tenantsTotal,
            'subscribed_accounts_total' => $subscribedAccountsTotal,
            'free_accounts_total' => $freeAccountsTotal,
            'users_total' => $usersTotal,
            'plans' => $plans,
            'counters' => [
                'tenants_total' => $tenantsTotal,
                'monitored_urls_total' => $monitoredUrlsTotal,
                'open_incidents_total' => $openIncidentsTotal,
            ],
        ];
    }

    private function normalizeCacheTtl(int $ttlSeconds): int
    {
        return min(300, max(0, $ttlSeconds));
    }

    private function assertIpAllowed(Request $request): void
    {
        $configured = config('ops.allowed_ips', []);
        if (! is_array($configured) || $configured === []) {
            return;
        }

        $allowed = array_values(array_filter(array_map(
            static fn ($ip): string => trim((string) $ip),
            $configured
        )));

        if ($allowed === []) {
            return;
        }

        $clientIp = trim((string) $request->ip());

        if ($clientIp === '' || ! in_array($clientIp, $allowed, true)) {
            abort(403);
        }
    }
}
