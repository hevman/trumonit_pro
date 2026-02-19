<?php

namespace App\Services;

use App\Models\MonitoredUrl;
use App\Models\PrivateProbe;
use App\Models\Tenant;
use App\Models\UrlSnapshot;

class OpsDrillService
{
    /**
     * @return array{
     *   generated_at: string,
     *   window_hours: int,
     *   overall_status: string,
     *   checks: array<string, array{status: string, message: string, data: array<string, mixed>}>,
     *   recommendations: list<string>
     * }
     */
    public function analyze(?int $windowHours = null): array
    {
        $now = now();
        $windowHours = $this->normalizeWindowHours($windowHours);
        $windowStart = $now->copy()->subHours($windowHours);

        $checks = [
            'queue_lag' => $this->analyzeQueueLag($now),
            'queue_wait' => $this->analyzeQueueWait($windowStart),
            'storage_quota' => $this->analyzeStorageQuota(),
            'private_probe_coverage' => $this->analyzePrivateProbeCoverage($now),
        ];

        $overallStatus = 'pass';
        $recommendations = [];

        foreach ($checks as $checkKey => $check) {
            $overallStatus = $this->maxStatus($overallStatus, (string) ($check['status'] ?? 'pass'));
            $recommendations = array_merge($recommendations, $this->recommendationsForCheck($checkKey, $check));
        }

        return [
            'generated_at' => $now->toISOString(),
            'window_hours' => $windowHours,
            'overall_status' => $overallStatus,
            'checks' => $checks,
            'recommendations' => array_values(array_unique($recommendations)),
        ];
    }

    /**
     * @return array{status: string, message: string, data: array<string, mixed>}
     */
    private function analyzeQueueLag(\Illuminate\Support\Carbon $now): array
    {
        $warnSeconds = max(30, min(86_400, (int) config('ops.drill.queue_lag_warn_seconds', 300)));
        $failSeconds = max($warnSeconds, min(172_800, (int) config('ops.drill.queue_lag_fail_seconds', 900)));

        $dueBaseQuery = MonitoredUrl::query()
            ->where('is_active', true)
            ->where(function ($query) use ($now): void {
                $query->whereNull('next_scan_at')
                    ->orWhere('next_scan_at', '<=', $now);
            });

        $dueTotal = (int) (clone $dueBaseQuery)->count();
        $dueWithoutSchedule = (int) (clone $dueBaseQuery)->whereNull('next_scan_at')->count();

        $lagValues = [];
        MonitoredUrl::query()
            ->where('is_active', true)
            ->whereNotNull('next_scan_at')
            ->where('next_scan_at', '<=', $now)
            ->select(['id', 'next_scan_at'])
            ->orderBy('id')
            ->chunkById(1000, function ($rows) use ($now, &$lagValues): void {
                foreach ($rows as $row) {
                    /** @var MonitoredUrl $row */
                    if ($row->next_scan_at === null) {
                        continue;
                    }

                    $lag = max(0, $row->next_scan_at->diffInSeconds($now, false));

                    if ($lag > 0) {
                        $lagValues[] = (float) $lag;
                    }
                }
            });

        $lagP95 = $this->percentile($lagValues, 95.0);
        $status = 'pass';

        if ($lagP95 !== null && $lagP95 >= $failSeconds) {
            $status = 'fail';
        } elseif ($lagP95 !== null && $lagP95 >= $warnSeconds) {
            $status = 'warn';
        }

        if ($dueWithoutSchedule > 0) {
            $status = $this->maxStatus($status, 'warn');
        }

        $message = 'Queue lag is within thresholds.';
        if ($status === 'warn') {
            $message = 'Queue lag needs tuning.';
        } elseif ($status === 'fail') {
            $message = 'Queue lag is above critical threshold.';
        }

        return [
            'status' => $status,
            'message' => $message,
            'data' => [
                'due_monitors_total' => $dueTotal,
                'due_without_schedule_total' => $dueWithoutSchedule,
                'lag_samples' => count($lagValues),
                'scan_lag_p95_seconds' => $lagP95 !== null ? round($lagP95, 2) : null,
                'warn_threshold_seconds' => $warnSeconds,
                'fail_threshold_seconds' => $failSeconds,
            ],
        ];
    }

    /**
     * @return array{status: string, message: string, data: array<string, mixed>}
     */
    private function analyzeQueueWait(\Illuminate\Support\Carbon $windowStart): array
    {
        $warnMs = max(100, min(600_000, (int) config('ops.drill.queue_wait_warn_ms', 2000)));
        $failMs = max($warnMs, min(900_000, (int) config('ops.drill.queue_wait_fail_ms', 10000)));

        $queueWaitValues = [];

        UrlSnapshot::query()
            ->where('fetched_at', '>=', $windowStart)
            ->whereNotNull('meta')
            ->select(['id', 'meta'])
            ->orderBy('id')
            ->chunkById(1000, function ($rows) use (&$queueWaitValues): void {
                foreach ($rows as $snapshot) {
                    /** @var UrlSnapshot $snapshot */
                    $meta = is_array($snapshot->meta) ? $snapshot->meta : [];
                    $queueWaitMs = $meta['queue_wait_ms'] ?? null;

                    if (is_numeric($queueWaitMs)) {
                        $queueWaitValues[] = max(0.0, (float) $queueWaitMs);
                    }
                }
            });

        $sampleCount = count($queueWaitValues);
        $queueWaitP95 = $this->percentile($queueWaitValues, 95.0);
        $status = 'pass';
        $message = 'Queue wait is within thresholds.';

        if ($sampleCount === 0) {
            $status = 'warn';
            $message = 'No queue wait samples in drill window.';
        } elseif ($queueWaitP95 !== null && $queueWaitP95 >= $failMs) {
            $status = 'fail';
            $message = 'Queue wait is above critical threshold.';
        } elseif ($queueWaitP95 !== null && $queueWaitP95 >= $warnMs) {
            $status = 'warn';
            $message = 'Queue wait needs tuning.';
        }

        return [
            'status' => $status,
            'message' => $message,
            'data' => [
                'sample_count' => $sampleCount,
                'queue_wait_p95_ms' => $queueWaitP95 !== null ? round($queueWaitP95, 2) : null,
                'warn_threshold_ms' => $warnMs,
                'fail_threshold_ms' => $failMs,
            ],
        ];
    }

    /**
     * @return array{status: string, message: string, data: array<string, mixed>}
     */
    private function analyzeStorageQuota(): array
    {
        $warnPercent = max(1.0, min(1000.0, (float) config('ops.drill.storage_usage_warn_percent', 85.0)));
        $failPercent = max($warnPercent, min(5000.0, (float) config('ops.drill.storage_usage_fail_percent', 100.0)));

        $usageByTenant = UrlSnapshot::query()
            ->join('monitored_urls', 'url_snapshots.monitored_url_id', '=', 'monitored_urls.id')
            ->selectRaw('monitored_urls.tenant_id as tenant_id')
            ->selectRaw('COALESCE(SUM(COALESCE(url_snapshots.body_bytes_stored, 0) + COALESCE(url_snapshots.screenshot_bytes_stored, 0)), 0) as bytes')
            ->groupBy('monitored_urls.tenant_id')
            ->pluck('bytes', 'tenant_id')
            ->all();

        $quotaService = app(TenantQuotaService::class);
        $tenants = Tenant::query()->select(['id', 'plan_tier'])->orderBy('id')->get();

        $tenantWithQuotaCount = 0;
        $overWarnCount = 0;
        $overFailCount = 0;
        $maxUsagePercent = 0.0;
        $topTenants = [];

        foreach ($tenants as $tenant) {
            $limits = $quotaService->limitsForTenant($tenant);
            $quotaBytes = (int) ($limits['storage_quota_bytes'] ?? 0);

            if ($quotaBytes <= 0) {
                continue;
            }

            $tenantWithQuotaCount++;
            $usageBytes = max(0, (int) ($usageByTenant[$tenant->id] ?? 0));
            $usagePercent = $quotaBytes > 0 ? round(($usageBytes / $quotaBytes) * 100, 2) : 0.0;
            $maxUsagePercent = max($maxUsagePercent, $usagePercent);

            if ($usagePercent >= $failPercent) {
                $overFailCount++;
            } elseif ($usagePercent >= $warnPercent) {
                $overWarnCount++;
            }

            $topTenants[] = [
                'tenant_id' => (int) $tenant->id,
                'plan_tier' => (string) ($limits['tier'] ?? 'lite'),
                'usage_bytes' => $usageBytes,
                'quota_bytes' => $quotaBytes,
                'usage_percent' => $usagePercent,
            ];
        }

        usort($topTenants, fn (array $a, array $b): int => ((float) ($b['usage_percent'] ?? 0)) <=> ((float) ($a['usage_percent'] ?? 0)));
        $topTenants = array_slice($topTenants, 0, 5);

        $status = 'pass';
        $message = 'Storage quota usage is within thresholds.';

        if ($overFailCount > 0) {
            $status = 'fail';
            $message = 'One or more tenants exceeded storage quota threshold.';
        } elseif ($overWarnCount > 0) {
            $status = 'warn';
            $message = 'One or more tenants are close to storage quota limit.';
        }

        return [
            'status' => $status,
            'message' => $message,
            'data' => [
                'tenants_with_quota_total' => $tenantWithQuotaCount,
                'tenants_over_warn_total' => $overWarnCount,
                'tenants_over_fail_total' => $overFailCount,
                'max_usage_percent' => round($maxUsagePercent, 2),
                'warn_threshold_percent' => $warnPercent,
                'fail_threshold_percent' => $failPercent,
                'top_tenants' => $topTenants,
            ],
        ];
    }

    /**
     * @return array{status: string, message: string, data: array<string, mixed>}
     */
    private function analyzePrivateProbeCoverage(\Illuminate\Support\Carbon $now): array
    {
        $enabled = (bool) config('wids.private_probes.enabled', false);

        if (! $enabled) {
            return [
                'status' => 'pass',
                'message' => 'Private probes are disabled.',
                'data' => [
                    'enabled' => false,
                    'active_probes_total' => 0,
                    'stale_probes_total' => 0,
                    'stale_ratio' => 0.0,
                    'due_private_probe_monitors_total' => 0,
                    'due_without_online_probe_total' => 0,
                ],
            ];
        }

        $staleAfterMinutes = max(1, min(10_080, (int) config('wids.private_probes.stale_after_minutes', 10)));
        $staleCutoff = $now->copy()->subMinutes($staleAfterMinutes);
        $staleWarnRatio = max(0.0, min(1.0, (float) config('ops.drill.private_probe_stale_warn_ratio', 0.4)));
        $staleFailRatio = max($staleWarnRatio, min(1.0, (float) config('ops.drill.private_probe_stale_fail_ratio', 0.7)));
        $monitorTag = strtolower(trim((string) config('wids.private_probes.monitor_tag', 'private_probe')));
        $monitorTag = $monitorTag !== '' ? $monitorTag : 'private_probe';

        $activeProbesTotal = (int) PrivateProbe::query()
            ->where('is_active', true)
            ->count();

        $staleProbesTotal = (int) PrivateProbe::query()
            ->where('is_active', true)
            ->where(function ($query) use ($staleCutoff): void {
                $query->whereNull('last_seen_at')
                    ->orWhere('last_seen_at', '<', $staleCutoff);
            })
            ->count();

        $staleRatio = $activeProbesTotal > 0 ? ($staleProbesTotal / $activeProbesTotal) : 0.0;

        $onlineProbeTenantIds = PrivateProbe::query()
            ->where('is_active', true)
            ->whereNotNull('last_seen_at')
            ->where('last_seen_at', '>=', $staleCutoff)
            ->select('tenant_id')
            ->distinct()
            ->pluck('tenant_id')
            ->map(fn ($value): int => (int) $value)
            ->values()
            ->all();

        $dueBaseQuery = MonitoredUrl::query()
            ->where('is_active', true)
            ->whereHas('tags', fn ($query) => $query->where('name', $monitorTag))
            ->where(function ($query) use ($now): void {
                $query->whereNull('next_scan_at')
                    ->orWhere('next_scan_at', '<=', $now);
            });

        $duePrivateProbeMonitorsTotal = (int) (clone $dueBaseQuery)->count();
        $dueWithoutOnlineProbeTotal = (int) (clone $dueBaseQuery)
            ->when(
                $onlineProbeTenantIds !== [],
                fn ($query) => $query->whereNotIn('tenant_id', $onlineProbeTenantIds)
            )
            ->count();

        $status = 'pass';
        $message = 'Private probe coverage is healthy.';

        if ($dueWithoutOnlineProbeTotal > 0) {
            $status = 'fail';
            $message = 'Due private-probe monitors have no online probe.';
        } elseif ($activeProbesTotal > 0 && $staleRatio >= $staleFailRatio) {
            $status = 'fail';
            $message = 'Private probe stale ratio is critical.';
        } elseif ($activeProbesTotal > 0 && $staleRatio >= $staleWarnRatio) {
            $status = 'warn';
            $message = 'Private probe stale ratio needs attention.';
        }

        return [
            'status' => $status,
            'message' => $message,
            'data' => [
                'enabled' => true,
                'monitor_tag' => $monitorTag,
                'active_probes_total' => $activeProbesTotal,
                'stale_probes_total' => $staleProbesTotal,
                'stale_ratio' => round($staleRatio, 4),
                'stale_warn_ratio' => $staleWarnRatio,
                'stale_fail_ratio' => $staleFailRatio,
                'online_probe_tenants_total' => count($onlineProbeTenantIds),
                'due_private_probe_monitors_total' => $duePrivateProbeMonitorsTotal,
                'due_without_online_probe_total' => $dueWithoutOnlineProbeTotal,
            ],
        ];
    }

    /**
     * @param  array{status?: string, data?: array<string, mixed>}  $check
     * @return list<string>
     */
    private function recommendationsForCheck(string $key, array $check): array
    {
        $status = (string) ($check['status'] ?? 'pass');
        $data = is_array($check['data'] ?? null) ? $check['data'] : [];

        if ($status === 'pass') {
            return [];
        }

        return match ($key) {
            'queue_lag' => [
                'Tune queue throughput: scale workers (`wids:queue-autoscale-plan`) or adjust scan dispatch caps.',
            ],
            'queue_wait' => [
                'Reduce queue wait p95 by increasing worker capacity or lowering scan burst pressure.',
            ],
            'storage_quota' => [
                'Run `wids:archive-snapshots` and review plan quotas/retention for high-usage tenants.',
            ],
            'private_probe_coverage' => $this->privateProbeRecommendations($status, $data),
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function privateProbeRecommendations(string $status, array $data): array
    {
        $recommendations = [];
        $dueWithoutOnline = (int) ($data['due_without_online_probe_total'] ?? 0);

        if ($dueWithoutOnline > 0) {
            $recommendations[] = 'Restore probe heartbeat for affected tenants or remove private-probe tags from blocked monitors.';
        }

        if ($status !== 'pass' && $dueWithoutOnline <= 0) {
            $recommendations[] = 'Rotate/redeploy stale probes and verify heartbeat interval against stale threshold.';
        }

        return $recommendations;
    }

    private function normalizeWindowHours(?int $windowHours): int
    {
        $default = (int) config('ops.drill.default_window_hours', 24);
        $default = min(24 * 30, max(1, $default));
        $windowHours = is_int($windowHours) ? $windowHours : $default;

        return min(24 * 30, max(1, $windowHours));
    }

    private function maxStatus(string $left, string $right): string
    {
        $rank = [
            'pass' => 0,
            'warn' => 1,
            'fail' => 2,
        ];

        return ($rank[$right] ?? 0) > ($rank[$left] ?? 0) ? $right : $left;
    }

    /**
     * @param  list<float>  $values
     */
    private function percentile(array $values, float $percentile): ?float
    {
        if ($values === []) {
            return null;
        }

        sort($values);

        $count = count($values);
        $position = ($percentile / 100) * ($count - 1);
        $floor = (int) floor($position);
        $ceil = (int) ceil($position);

        if ($floor === $ceil) {
            return $values[$floor];
        }

        $lower = $values[$floor];
        $upper = $values[$ceil];
        $weight = $position - $floor;

        return $lower + (($upper - $lower) * $weight);
    }
}
