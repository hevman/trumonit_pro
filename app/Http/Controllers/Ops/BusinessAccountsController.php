<?php

namespace App\Http\Controllers\Ops;

use App\Models\Tenant;
use App\Services\BillingPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BusinessAccountsController
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
                'ops:business-accounts:v1',
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
     *   service:string,
     *   generated_at:string,
     *   accounts_total:int,
     *   accounts:list<array{
     *      account_id:string,
     *      tenant_id:int,
     *      tenant_slug:string,
     *      name:string,
     *      email:?string,
     *      plan_tier:string,
     *      subscription_status:?string,
     *      payment_status:string,
     *      paid_until_at:?string,
     *      last_payment_at:?string,
     *      billing_provider:?string
     *   }>
     * }
     */
    private function buildPayload(): array
    {
        $maxAccounts = min(5000, max(1, (int) config('ops.business.max_accounts_export', 1000)));
        $billingPlanService = app(BillingPlanService::class);

        $tenants = Tenant::query()
            ->with([
                'users' => fn ($query) => $query
                    ->select(['id', 'tenant_id', 'name', 'email', 'role'])
                    ->orderByRaw("CASE WHEN role = 'owner' THEN 0 WHEN role = 'admin' THEN 1 ELSE 2 END")
                    ->orderBy('id'),
            ])
            ->orderBy('id')
            ->limit($maxAccounts)
            ->get([
                'id',
                'name',
                'slug',
                'plan_tier',
                'billing_provider',
                'billing_subscription_status',
                'billing_plan_changed_at',
            ]);

        $accounts = $tenants->map(function (Tenant $tenant) use ($billingPlanService): array {
            $owner = $tenant->users->first();
            $planTier = $billingPlanService->effectivePlanTierForTenant($tenant);

            $subscriptionStatus = strtolower(trim((string) ($tenant->billing_subscription_status ?? '')));
            $subscriptionStatus = $subscriptionStatus !== '' ? $subscriptionStatus : null;

            $paymentStatus = $subscriptionStatus === null
                ? 'free'
                : ($billingPlanService->isPaidSubscriptionStatus($subscriptionStatus) ? 'paid' : 'unpaid');

            return [
                'account_id' => 'tenant-'.$tenant->id,
                'tenant_id' => (int) $tenant->id,
                'tenant_slug' => (string) $tenant->slug,
                'name' => trim((string) ($tenant->name ?: ($owner?->name ?? 'tenant-'.$tenant->id))),
                'email' => $owner?->email ? trim((string) $owner->email) : null,
                'plan_tier' => $planTier,
                'subscription_status' => $subscriptionStatus,
                'payment_status' => $paymentStatus,
                'paid_until_at' => null,
                'last_payment_at' => $tenant->billing_plan_changed_at?->toISOString(),
                'billing_provider' => $tenant->billing_provider ? strtolower(trim((string) $tenant->billing_provider)) : null,
            ];
        })->values()->all();

        return [
            'service' => 'TruMonit',
            'generated_at' => now()->toISOString(),
            'accounts_total' => count($accounts),
            'accounts' => $accounts,
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
