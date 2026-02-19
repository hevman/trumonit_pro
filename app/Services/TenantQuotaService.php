<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\UrlSnapshot;

class TenantQuotaService
{
    /**
     * @return array{
     *   tier: string,
     *   max_monitored_urls: int,
     *   tenant_rpm: int,
     *   storage_quota_bytes: int,
     *   retention_days: int
     * }
     */
    public function limitsForTenant(?Tenant $tenant): array
    {
        $defaultTier = $this->sanitizeTier((string) config('wids.default_plan_tier', 'lite'));
        $configuredTiers = config('wids.plan_tiers', []);
        $configuredTiers = is_array($configuredTiers) ? $configuredTiers : [];

        $tier = $this->sanitizeTier(is_string($tenant?->plan_tier) ? $tenant->plan_tier : $defaultTier);
        $tierConfig = is_array($configuredTiers[$tier] ?? null) ? $configuredTiers[$tier] : [];

        if ($tierConfig === [] && is_array($configuredTiers[$defaultTier] ?? null)) {
            $tier = $defaultTier;
            $tierConfig = $configuredTiers[$tier];
        }

        $globalMaxUrls = $this->normalizeLimit((int) config('wids.limits.max_monitored_urls_per_tenant', 0));
        $globalTenantRpm = $this->normalizeLimit((int) config('wids.rate_limits.tenant_requests_per_minute', 0));
        $globalStorageQuotaMb = $this->normalizeLimit((int) config('wids.limits.storage_quota_mb', 0));
        $globalRetentionDays = $this->normalizeDays((int) config('wids.retention.days', 30));

        $maxUrls = $this->normalizeLimit((int) ($tierConfig['max_monitored_urls'] ?? $globalMaxUrls));
        $tenantRpm = $this->normalizeLimit((int) ($tierConfig['tenant_rpm'] ?? $globalTenantRpm));
        $storageQuotaMb = $this->normalizeLimit((int) ($tierConfig['storage_quota_mb'] ?? $globalStorageQuotaMb));
        $retentionDays = $this->normalizeDays((int) ($tierConfig['retention_days'] ?? $globalRetentionDays));

        if ($globalMaxUrls > 0 && ($maxUrls <= 0 || $globalMaxUrls < $maxUrls)) {
            $maxUrls = $globalMaxUrls;
        }

        if ($globalStorageQuotaMb > 0 && ($storageQuotaMb <= 0 || $globalStorageQuotaMb < $storageQuotaMb)) {
            $storageQuotaMb = $globalStorageQuotaMb;
        }

        return [
            'tier' => $tier,
            'max_monitored_urls' => $maxUrls,
            'tenant_rpm' => $tenantRpm,
            'storage_quota_bytes' => $this->mbToBytes($storageQuotaMb),
            'retention_days' => $retentionDays,
        ];
    }

    public function limitsForTenantId(int $tenantId): array
    {
        $tenant = Tenant::query()
            ->select(['id', 'plan_tier'])
            ->find($tenantId);

        return $this->limitsForTenant($tenant);
    }

    public function monitorLimitMessage(array $limits): string
    {
        $tierLabel = strtoupper((string) ($limits['tier'] ?? 'plan'));
        $limit = (int) ($limits['max_monitored_urls'] ?? 0);

        if ($limit <= 0) {
            return 'Plan limit reached. Please contact support to increase limits.';
        }

        return sprintf(
            'Monitored URL limit (%d) reached for %s plan. Upgrade plan to add more monitors.',
            $limit,
            $tierLabel
        );
    }

    public function storageLimitMessage(array $limits): string
    {
        $tierLabel = strtoupper((string) ($limits['tier'] ?? 'plan'));
        $quotaBytes = (int) ($limits['storage_quota_bytes'] ?? 0);

        if ($quotaBytes <= 0) {
            return 'Storage quota reached. Please contact support to increase limits.';
        }

        $quotaMb = (int) floor($quotaBytes / 1_048_576);

        return sprintf(
            'Storage quota (%d MB) reached for %s plan. Upgrade plan or increase retention cleanup.',
            $quotaMb,
            $tierLabel
        );
    }

    public function storageUsageBytesForTenantId(int $tenantId): int
    {
        $value = UrlSnapshot::query()
            ->join('monitored_urls', 'url_snapshots.monitored_url_id', '=', 'monitored_urls.id')
            ->where('monitored_urls.tenant_id', $tenantId)
            ->selectRaw('COALESCE(SUM(COALESCE(url_snapshots.body_bytes_stored, 0) + COALESCE(url_snapshots.screenshot_bytes_stored, 0)), 0) as bytes')
            ->value('bytes');

        return max(0, (int) ($value ?? 0));
    }

    public function exceedsStorageQuota(int $tenantId, int $additionalBytes = 0): bool
    {
        $additionalBytes = max(0, $additionalBytes);
        $limits = $this->limitsForTenantId($tenantId);
        $quotaBytes = (int) ($limits['storage_quota_bytes'] ?? 0);

        if ($quotaBytes <= 0) {
            return false;
        }

        $usage = $this->storageUsageBytesForTenantId($tenantId);

        return ($usage + $additionalBytes) > $quotaBytes;
    }

    public function normalizeDays(int $days): int
    {
        return min(3650, max(1, $days));
    }

    private function sanitizeTier(string $tier): string
    {
        $normalized = strtolower(trim($tier));

        return $normalized !== '' ? $normalized : 'lite';
    }

    private function normalizeLimit(int $value): int
    {
        return max(0, $value);
    }

    private function mbToBytes(int $mb): int
    {
        $mb = $this->normalizeLimit($mb);

        if ($mb <= 0) {
            return 0;
        }

        return $mb * 1_048_576;
    }
}
