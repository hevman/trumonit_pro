<?php

namespace App\Services;

use App\Models\BillingCheckoutSession;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BillingPlanService
{
    /**
     * @return list<string>
     */
    public function availablePlanTiers(): array
    {
        $tiers = config('wids.plan_tiers', []);
        $tiers = is_array($tiers) ? array_keys($tiers) : [];
        $normalized = [];

        foreach ($tiers as $tier) {
            if (! is_string($tier)) {
                continue;
            }

            $tier = $this->normalizePlanTier($tier);

            if ($tier !== '') {
                $normalized[$tier] = true;
            }
        }

        return array_values(array_keys($normalized));
    }

    public function currentPlanTierForTenant(Tenant $tenant): string
    {
        $current = $this->normalizePlanTier((string) ($tenant->plan_tier ?? ''));

        if ($current === '') {
            $current = $this->normalizePlanTier((string) config('wids.default_plan_tier', 'lite'));
        }

        return $current !== '' ? $current : 'lite';
    }

    public function requestPlanChange(Tenant $tenant, int $actorUserId, string $targetPlanTier, ?Request $request = null): BillingCheckoutSession
    {
        $targetPlanTier = $this->normalizePlanTier($targetPlanTier);
        $availablePlanTiers = $this->availablePlanTiers();

        if ($targetPlanTier === '' || ! in_array($targetPlanTier, $availablePlanTiers, true)) {
            throw new \InvalidArgumentException('Selected plan tier is invalid.');
        }

        $currentPlanTier = $this->currentPlanTierForTenant($tenant);

        if ($targetPlanTier === $currentPlanTier) {
            throw new \InvalidArgumentException('Selected plan tier is already active.');
        }

        return DB::transaction(function () use ($tenant, $actorUserId, $targetPlanTier, $currentPlanTier, $request): BillingCheckoutSession {
            $provider = app(BillingPaymentService::class)->providerKey();

            $session = BillingCheckoutSession::query()->create([
                'tenant_id' => $tenant->id,
                'created_by_user_id' => $actorUserId > 0 ? $actorUserId : null,
                'provider' => $provider,
                'target_plan_tier' => $targetPlanTier,
                'status' => 'pending',
                'metadata' => [
                    'source' => 'billing.plan_change',
                    'from_plan_tier' => $currentPlanTier,
                ],
            ]);

            app(AuditLogService::class)->log(
                tenantId: (int) $tenant->id,
                actorUserId: $actorUserId > 0 ? $actorUserId : null,
                action: 'billing.checkout_session_created',
                auditableType: BillingCheckoutSession::class,
                auditableId: (int) $session->id,
                data: [
                    'provider' => $provider,
                    'from_plan_tier' => $currentPlanTier,
                    'target_plan_tier' => $targetPlanTier,
                ],
                request: $request,
            );

            $result = app(BillingPaymentService::class)->startPlanChangeCheckout($session, $tenant);
            $resultProvider = $this->normalizeProvider((string) ($result['provider'] ?? $provider));
            $resultStatus = $this->normalizeSessionStatus((string) ($result['status'] ?? 'pending'));

            $metadata = is_array($session->metadata) ? $session->metadata : [];
            $resultMetadata = is_array($result['metadata'] ?? null) ? $result['metadata'] : [];
            $metadata = array_merge($metadata, $resultMetadata);

            $session->forceFill([
                'provider' => $resultProvider,
                'status' => $resultStatus,
                'external_session_id' => $this->normalizeNullableString($result['external_session_id'] ?? null, 191),
                'checkout_url' => $this->normalizeNullableString($result['checkout_url'] ?? null),
                'expires_at' => $result['expires_at'] ?? null,
                'completed_at' => $result['completed_at'] ?? null,
                'metadata' => $metadata,
            ])->save();

            if ($session->status === 'completed') {
                $this->applyPlanTierFromSession(
                    session: $session,
                    actorUserId: $actorUserId > 0 ? $actorUserId : null,
                    request: $request,
                );
            }

            return $session->fresh();
        });
    }

    public function applyPlanTierFromSession(BillingCheckoutSession $session, ?int $actorUserId = null, ?Request $request = null): void
    {
        $tenant = $session->tenant()->first();
        if (! $tenant) {
            return;
        }

        $targetPlanTier = $this->normalizePlanTier((string) $session->target_plan_tier);
        if ($targetPlanTier === '' || ! in_array($targetPlanTier, $this->availablePlanTiers(), true)) {
            return;
        }

        $before = [
            'plan_tier' => $this->currentPlanTierForTenant($tenant),
            'billing_provider' => $tenant->billing_provider,
            'billing_subscription_status' => $tenant->billing_subscription_status,
            'billing_plan_changed_at' => optional($tenant->billing_plan_changed_at)->toISOString(),
        ];

        if ($before['plan_tier'] === $targetPlanTier) {
            return;
        }

        $metadata = is_array($session->metadata) ? $session->metadata : [];
        $provider = $this->normalizeProvider((string) $session->provider);

        $customerRef = null;
        $subscriptionRef = null;

        if ($provider === 'stripe') {
            $customerRef = is_scalar($metadata['stripe_customer_ref'] ?? null) ? trim((string) $metadata['stripe_customer_ref']) : null;
            $subscriptionRef = is_scalar($metadata['stripe_subscription_ref'] ?? null) ? trim((string) $metadata['stripe_subscription_ref']) : null;

            $customerRef = $customerRef !== '' ? mb_substr($customerRef, 0, 191) : null;
            $subscriptionRef = $subscriptionRef !== '' ? mb_substr($subscriptionRef, 0, 191) : null;
        }

        $tenant->forceFill([
            'plan_tier' => $targetPlanTier,
            'billing_provider' => $provider,
            'billing_customer_ref' => $customerRef ?? $tenant->billing_customer_ref,
            'billing_subscription_ref' => $subscriptionRef ?? $tenant->billing_subscription_ref,
            'billing_subscription_status' => $provider === 'manual' ? 'manual_active' : 'active',
            'billing_plan_changed_at' => now(),
        ])->save();

        $after = [
            'plan_tier' => $this->currentPlanTierForTenant($tenant),
            'billing_provider' => $tenant->billing_provider,
            'billing_subscription_status' => $tenant->billing_subscription_status,
            'billing_plan_changed_at' => optional($tenant->billing_plan_changed_at)->toISOString(),
        ];

        app(AuditLogService::class)->log(
            tenantId: (int) $tenant->id,
            actorUserId: $actorUserId,
            action: 'tenant.plan_tier_changed',
            auditableType: Tenant::class,
            auditableId: (int) $tenant->id,
            data: [
                'checkout_session_id' => (int) $session->id,
                'before' => $before,
                'after' => $after,
            ],
            request: $request,
        );
    }

    private function normalizePlanTier(string $tier): string
    {
        $tier = strtolower(trim($tier));

        return $tier !== '' ? $tier : '';
    }

    private function normalizeProvider(string $provider): string
    {
        $provider = strtolower(trim($provider));

        return $provider !== '' ? $provider : 'manual';
    }

    private function normalizeSessionStatus(string $status): string
    {
        $status = strtolower(trim($status));

        if (! in_array($status, ['pending', 'completed', 'canceled', 'expired', 'failed'], true)) {
            return 'pending';
        }

        return $status;
    }

    private function normalizeNullableString(mixed $value, int $max = 0): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if ($max > 0 && mb_strlen($value) > $max) {
            return mb_substr($value, 0, $max);
        }

        return $value;
    }
}
