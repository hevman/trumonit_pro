<?php

namespace App\Services;

use App\Models\PrivateProbe;
use App\Models\Tenant;
use Illuminate\Support\Facades\Schema;

class PrivateProbeFleetPolicyService
{
    /**
     * @var array<int, string>
     */
    private array $tenantTierCache = [];

    /**
     * @var array<string, int>
     */
    private array $maxActiveCache = [];

    /**
     * @var array<string, array<string, true>|null>
     */
    private array $allowedRegionSetCache = [];

    public function tierForTenant(Tenant $tenant): string
    {
        return $this->normalizeTier((string) ($tenant->plan_tier ?? config('wids.default_plan_tier', 'lite')));
    }

    public function tierForTenantId(int $tenantId): string
    {
        if (isset($this->tenantTierCache[$tenantId])) {
            return $this->tenantTierCache[$tenantId];
        }

        $tier = 'lite';

        if ($tenantId > 0) {
            $limits = app(TenantQuotaService::class)->limitsForTenantId($tenantId);
            $tier = is_string($limits['tier'] ?? null) ? (string) $limits['tier'] : $tier;
        }

        $tier = $this->normalizeTier($tier);
        $this->tenantTierCache[$tenantId] = $tier;

        return $tier;
    }

    public function maxActiveForTenant(Tenant $tenant): int
    {
        return $this->maxActiveForTier($this->tierForTenant($tenant));
    }

    public function maxActiveForTenantId(int $tenantId): int
    {
        return $this->maxActiveForTier($this->tierForTenantId($tenantId));
    }

    public function maxActiveForTier(string $tier): int
    {
        $tier = $this->normalizeTier($tier);

        if (array_key_exists($tier, $this->maxActiveCache)) {
            return $this->maxActiveCache[$tier];
        }

        $byTier = config('wids.private_probes.max_active_by_tier', []);
        $byTier = is_array($byTier) ? $byTier : [];

        $maxActive = (int) ($byTier[$tier] ?? $byTier['default'] ?? 0);
        $maxActive = max(0, min(10_000, $maxActive));

        $this->maxActiveCache[$tier] = $maxActive;

        return $maxActive;
    }

    /**
     * @return array<string, true>|null
     */
    public function allowedRegionSetForTenant(Tenant $tenant): ?array
    {
        return $this->allowedRegionSetForTier($this->tierForTenant($tenant));
    }

    /**
     * @return array<string, true>|null
     */
    public function allowedRegionSetForTenantId(int $tenantId): ?array
    {
        return $this->allowedRegionSetForTier($this->tierForTenantId($tenantId));
    }

    /**
     * @return array<string, true>|null
     */
    public function allowedRegionSetForTier(string $tier): ?array
    {
        $tier = $this->normalizeTier($tier);

        if (array_key_exists($tier, $this->allowedRegionSetCache)) {
            return $this->allowedRegionSetCache[$tier];
        }

        $byTier = config('wids.private_probes.allowed_regions_by_tier', []);
        $byTier = is_array($byTier) ? $byTier : [];

        $raw = $byTier[$tier] ?? $byTier['default'] ?? ['*'];
        $set = $this->normalizeRegionSet($raw);

        $this->allowedRegionSetCache[$tier] = $set;

        return $set;
    }

    public function isRegionAllowedForTenant(Tenant $tenant, ?string $regionHint): bool
    {
        return $this->isRegionAllowedBySet($this->allowedRegionSetForTenant($tenant), $regionHint);
    }

    public function isRegionAllowedForTenantId(int $tenantId, ?string $regionHint): bool
    {
        return $this->isRegionAllowedBySet($this->allowedRegionSetForTenantId($tenantId), $regionHint);
    }

    public function canCreateProbeForTenant(Tenant $tenant): bool
    {
        $maxActive = $this->maxActiveForTenant($tenant);

        if ($maxActive <= 0) {
            return true;
        }

        $activeCount = (int) PrivateProbe::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->count();

        return $activeCount < $maxActive;
    }

    public function canActivateProbe(PrivateProbe $probe): bool
    {
        $maxActive = $this->maxActiveForTenantId((int) $probe->tenant_id);

        if ($maxActive <= 0) {
            return true;
        }

        $activeCount = (int) PrivateProbe::query()
            ->where('tenant_id', $probe->tenant_id)
            ->where('is_active', true)
            ->when($probe->exists, fn ($query) => $query->where('id', '!=', $probe->id))
            ->count();

        return $activeCount < $maxActive;
    }

    public function staleDisableHours(): int
    {
        return max(0, min(8760, (int) config('wids.private_probes.fleet_policy.stale_disable_hours', 72)));
    }

    public function eventHistoryLimit(): int
    {
        return max(1, min(200, (int) config('wids.private_probes.fleet_policy.event_history_limit', 20)));
    }

    public function normalizeRegion(?string $regionHint): ?string
    {
        if (! is_string($regionHint)) {
            return null;
        }

        $regionHint = strtolower(trim($regionHint));

        return $regionHint !== '' ? $regionHint : null;
    }

    public function hasMetaColumn(): bool
    {
        return Schema::hasColumn('private_probes', 'meta');
    }

    private function normalizeTier(string $tier): string
    {
        $tier = strtolower(trim($tier));

        return $tier !== '' ? $tier : 'lite';
    }

    /**
     * @param  mixed  $raw
     * @return array<string, true>|null
     */
    private function normalizeRegionSet(mixed $raw): ?array
    {
        $values = [];

        if (is_string($raw)) {
            $values = array_map('trim', explode(',', $raw));
        } elseif (is_array($raw)) {
            foreach ($raw as $value) {
                if (is_string($value)) {
                    $values[] = trim($value);
                }
            }
        }

        $set = [];

        foreach ($values as $value) {
            $value = strtolower(trim($value));

            if ($value === '') {
                continue;
            }

            if ($value === '*') {
                return null;
            }

            $set[$value] = true;
        }

        return $set !== [] ? $set : null;
    }

    /**
     * @param  array<string, true>|null  $allowedRegionSet
     */
    private function isRegionAllowedBySet(?array $allowedRegionSet, ?string $regionHint): bool
    {
        if ($allowedRegionSet === null) {
            return true;
        }

        $region = $this->normalizeRegion($regionHint);

        if ($region === null) {
            return false;
        }

        return isset($allowedRegionSet[$region]);
    }
}
