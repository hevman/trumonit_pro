<?php

namespace App\Services\SeoRisk;

use App\Models\Tenant;

class SeoRiskPlanService
{
    /**
     * @return array{
     *   tier: string,
     *   max_properties: int,
     *   auto_sync: bool,
     *   backlinks_enabled: bool
     * }
     */
    public function capabilitiesForTenant(Tenant $tenant): array
    {
        $tier = $this->currentPlanTier($tenant);
        $tierConfig = $this->resolveTierConfig($tier);

        return [
            'tier' => $tier,
            'max_properties' => max(0, (int) ($tierConfig['max_properties'] ?? 0)),
            'auto_sync' => (bool) ($tierConfig['auto_sync'] ?? false),
            'backlinks_enabled' => (bool) ($tierConfig['backlinks_enabled'] ?? false),
        ];
    }

    public function maxPropertiesForTenant(Tenant $tenant): int
    {
        $capabilities = $this->capabilitiesForTenant($tenant);

        return (int) $capabilities['max_properties'];
    }

    public function allowsAutoSyncForTenant(Tenant $tenant): bool
    {
        $capabilities = $this->capabilitiesForTenant($tenant);

        return (bool) $capabilities['auto_sync'];
    }

    public function allowsBacklinksForTenant(Tenant $tenant): bool
    {
        $capabilities = $this->capabilitiesForTenant($tenant);

        return (bool) $capabilities['backlinks_enabled'];
    }

    public function currentPlanTier(Tenant $tenant): string
    {
        $tier = strtolower(trim((string) ($tenant->plan_tier ?? '')));
        if ($tier === '') {
            $tier = strtolower(trim((string) config('wids.default_plan_tier', 'lite')));
        }

        return $tier !== '' ? $tier : 'lite';
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveTierConfig(string $tier): array
    {
        $all = config('wids.seo_risk.plan_tiers', []);
        if (! is_array($all) || $all === []) {
            return [
                'max_properties' => 1,
                'auto_sync' => false,
                'backlinks_enabled' => false,
            ];
        }

        $tierConfig = $all[$tier] ?? null;
        if (is_array($tierConfig)) {
            return $tierConfig;
        }

        $freeFallback = $all['free'] ?? null;
        if (is_array($freeFallback)) {
            return $freeFallback;
        }

        return [
            'max_properties' => 1,
            'auto_sync' => false,
            'backlinks_enabled' => false,
        ];
    }
}

