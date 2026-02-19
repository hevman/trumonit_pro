<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class MultiRegionHealthService
{
    /**
     * @return array{
     *   enabled: bool,
     *   window_hours: int,
     *   min_samples: int,
     *   max_failure_ratio: float,
     *   exclude_unhealthy_regions: bool,
     *   min_regions_after_filter: int,
     *   strict_min_regions: bool,
     *   cache_key_prefix: string
     * }
     */
    public function controls(): array
    {
        $prefix = trim((string) config('wids.multi_region.health_controls.cache_key_prefix', 'wids:multi-region:health'));
        $prefix = $prefix !== '' ? $prefix : 'wids:multi-region:health';

        return [
            'enabled' => (bool) config('wids.multi_region.health_controls.enabled', true),
            'window_hours' => max(1, min(168, (int) config('wids.multi_region.health_controls.window_hours', 24))),
            'min_samples' => max(1, min(100_000, (int) config('wids.multi_region.health_controls.min_samples', 12))),
            'max_failure_ratio' => max(0.05, min(1.0, (float) config('wids.multi_region.health_controls.max_failure_ratio', 0.75))),
            'exclude_unhealthy_regions' => (bool) config('wids.multi_region.health_controls.exclude_unhealthy_regions', true),
            'min_regions_after_filter' => max(1, min(16, (int) config('wids.multi_region.health_controls.min_regions_after_filter', 2))),
            'strict_min_regions' => (bool) config('wids.multi_region.health_controls.strict_min_regions', false),
            'cache_key_prefix' => $prefix,
        ];
    }

    public function recordProbeResult(string $region, bool $isDown): void
    {
        $region = $this->normalizeRegion($region);

        if ($region === null) {
            return;
        }

        $controls = $this->controls();
        $bucket = now()->utc()->format('YmdH');
        $prefix = (string) $controls['cache_key_prefix'];
        $windowHours = (int) $controls['window_hours'];
        $ttl = now()->addHours($windowHours + 6);

        $attemptKey = $this->bucketKey($prefix, $region, 'attempts', $bucket);
        $failureKey = $this->bucketKey($prefix, $region, 'failures', $bucket);

        Cache::add($attemptKey, 0, $ttl);
        Cache::increment($attemptKey);

        if ($isDown) {
            Cache::add($failureKey, 0, $ttl);
            Cache::increment($failureKey);
        }
    }

    /**
     * @return array{
     *   selected_regions: list<string>,
     *   excluded_regions: list<array{region: string, reason: string, attempts: int, failures: int, failure_ratio: float, unhealthy: bool}>,
     *   region_health: array<string, array{attempts: int, failures: int, failure_ratio: float, unhealthy: bool}>,
     *   policy_relaxed: bool,
     *   strict_blocked: bool,
     *   controls_enabled: bool
     * }
     */
    public function applyRegionPolicy(array $regions): array
    {
        $normalized = $this->normalizeRegions($regions);
        $controls = $this->controls();

        if ($normalized === [] || ! $controls['enabled']) {
            return [
                'selected_regions' => $normalized,
                'excluded_regions' => [],
                'region_health' => $this->healthMapForRegions($normalized),
                'policy_relaxed' => false,
                'strict_blocked' => false,
                'controls_enabled' => (bool) $controls['enabled'],
            ];
        }

        $regionHealth = $this->healthMapForRegions($normalized);
        $primary = $normalized[0];
        $selected = [$primary];
        $excluded = [];

        foreach (array_slice($normalized, 1) as $region) {
            $stats = $regionHealth[$region] ?? [
                'attempts' => 0,
                'failures' => 0,
                'failure_ratio' => 0.0,
                'unhealthy' => false,
            ];

            if ($stats['unhealthy'] && $controls['exclude_unhealthy_regions']) {
                $excluded[] = [
                    'region' => $region,
                    'reason' => 'unhealthy_ratio',
                    'attempts' => (int) $stats['attempts'],
                    'failures' => (int) $stats['failures'],
                    'failure_ratio' => (float) $stats['failure_ratio'],
                    'unhealthy' => true,
                ];
                continue;
            }

            $selected[] = $region;
        }

        $minRegionsAfterFilter = (int) $controls['min_regions_after_filter'];
        $policyRelaxed = false;
        $strictBlocked = false;

        if (count($selected) < $minRegionsAfterFilter) {
            if ($controls['strict_min_regions']) {
                $strictBlocked = true;
            } else {
                $policyRelaxed = true;
                $selected = $normalized;
                $excluded = [];
            }
        }

        return [
            'selected_regions' => $selected,
            'excluded_regions' => $excluded,
            'region_health' => $regionHealth,
            'policy_relaxed' => $policyRelaxed,
            'strict_blocked' => $strictBlocked,
            'controls_enabled' => true,
        ];
    }

    /**
     * @return array{
     *   attempts: int,
     *   failures: int,
     *   failure_ratio: float,
     *   unhealthy: bool
     * }
     */
    public function statsForRegion(string $region): array
    {
        $normalizedRegion = $this->normalizeRegion($region);

        if ($normalizedRegion === null) {
            return [
                'attempts' => 0,
                'failures' => 0,
                'failure_ratio' => 0.0,
                'unhealthy' => false,
            ];
        }

        $controls = $this->controls();
        $windowHours = (int) $controls['window_hours'];
        $prefix = (string) $controls['cache_key_prefix'];

        $attempts = 0;
        $failures = 0;

        for ($offset = 0; $offset < $windowHours; $offset++) {
            $bucket = now()->utc()->subHours($offset)->format('YmdH');
            $attempts += max(0, (int) Cache::get($this->bucketKey($prefix, $normalizedRegion, 'attempts', $bucket), 0));
            $failures += max(0, (int) Cache::get($this->bucketKey($prefix, $normalizedRegion, 'failures', $bucket), 0));
        }

        $failureRatio = $attempts > 0 ? round($failures / $attempts, 6) : 0.0;
        $unhealthy = $attempts >= (int) $controls['min_samples'] && $failureRatio > (float) $controls['max_failure_ratio'];

        return [
            'attempts' => $attempts,
            'failures' => $failures,
            'failure_ratio' => $failureRatio,
            'unhealthy' => $unhealthy,
        ];
    }

    /**
     * @param  list<string>  $regions
     * @return array<string, array{attempts: int, failures: int, failure_ratio: float, unhealthy: bool}>
     */
    public function healthMapForRegions(array $regions): array
    {
        $map = [];

        foreach ($this->normalizeRegions($regions) as $region) {
            $map[$region] = $this->statsForRegion($region);
        }

        return $map;
    }

    /**
     * @param  mixed  $regions
     * @return list<string>
     */
    private function normalizeRegions(mixed $regions): array
    {
        if (! is_array($regions)) {
            return [];
        }

        $normalized = [];

        foreach ($regions as $region) {
            if (! is_string($region)) {
                continue;
            }

            $value = $this->normalizeRegion($region);

            if ($value === null || in_array($value, $normalized, true)) {
                continue;
            }

            $normalized[] = $value;
        }

        return $normalized;
    }

    private function normalizeRegion(string $region): ?string
    {
        $region = strtolower(trim($region));

        return $region !== '' ? $region : null;
    }

    private function bucketKey(string $prefix, string $region, string $kind, string $bucket): string
    {
        return $prefix.':'.$region.':'.$kind.':'.$bucket;
    }
}
