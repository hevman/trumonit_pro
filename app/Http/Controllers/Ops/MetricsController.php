<?php

namespace App\Http\Controllers\Ops;

use App\Models\Incident;
use App\Models\MonitoredUrl;
use App\Models\UrlSnapshot;
use App\Services\QueueAutoscalingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MetricsController
{
    public function __invoke(Request $request)
    {
        if (! (bool) config('ops.metrics.enabled', false)) {
            abort(404);
        }

        $requireToken = (bool) config('ops.metrics.require_token', true);
        $token = trim((string) config('ops.metrics.token', ''));

        if ($token !== '') {
            $provided = $request->bearerToken();

            if (! is_string($provided) || ! hash_equals($token, $provided)) {
                abort(403);
            }
        } elseif ($requireToken) {
            abort(503);
        }

        $cacheTtlSeconds = $this->normalizeMetricsCacheTtl((int) config('ops.metrics.cache_ttl_seconds', 5));

        if ($cacheTtlSeconds > 0) {
            $body = Cache::remember(
                'ops:metrics:prometheus:v1',
                now()->addSeconds($cacheTtlSeconds),
                fn () => $this->buildMetricsBody()
            );
        } else {
            $body = $this->buildMetricsBody();
        }

        return response($body, 200, [
            'Content-Type' => 'text/plain; version=0.0.4',
        ]);
    }

    private function buildMetricsBody(): string
    {
        $now = now();
        $window5m = $now->copy()->subMinutes(5);
        $window1h = $now->copy()->subHour();
        $window24h = $now->copy()->subDay();

        $monitorsTotal = MonitoredUrl::query()->count();
        $monitorsActive = MonitoredUrl::query()->where('is_active', true)->count();
        $monitorsDue = MonitoredUrl::query()
            ->where('is_active', true)
            ->where(function ($q) use ($now): void {
                $q->whereNull('next_scan_at')
                    ->orWhere('next_scan_at', '<=', $now);
            })
            ->count();

        $scanLagValues = [];
        MonitoredUrl::query()
            ->where('is_active', true)
            ->whereNotNull('next_scan_at')
            ->where('next_scan_at', '<=', $now)
            ->select(['id', 'next_scan_at'])
            ->orderBy('id')
            ->chunkById(1000, function ($rows) use ($now, &$scanLagValues): void {
                foreach ($rows as $row) {
                    /** @var MonitoredUrl $row */
                    if ($row->next_scan_at === null) {
                        continue;
                    }

                    $lag = $row->next_scan_at->diffInSeconds($now, false);

                    if ($lag > 0) {
                        $scanLagValues[] = (float) $lag;
                    }
                }
            });

        $scanLagP95 = $this->percentile($scanLagValues, 95.0);
        $scanLagMax = $scanLagValues !== [] ? max($scanLagValues) : null;

        $incidentCounts = Incident::query()
            ->select('status', DB::raw('COUNT(*) as c'))
            ->groupBy('status')
            ->pluck('c', 'status')
            ->all();

        $failedJobsTotal = null;

        if (Schema::hasTable('failed_jobs')) {
            $failedJobsTotal = (int) DB::table('failed_jobs')->count();
        }

        $snapshotsLast5mTotal = UrlSnapshot::query()->where('fetched_at', '>=', $window5m)->count();
        $snapshotsLast5mErrors = UrlSnapshot::query()
            ->where('fetched_at', '>=', $window5m)
            ->whereNotNull('error_message')
            ->count();
        $snapshotsLast5mOk = max(0, $snapshotsLast5mTotal - $snapshotsLast5mErrors);

        $snapshotsLast1hTotal = UrlSnapshot::query()->where('fetched_at', '>=', $window1h)->count();
        $snapshotsLast1hErrors = UrlSnapshot::query()
            ->where('fetched_at', '>=', $window1h)
            ->whereNotNull('error_message')
            ->count();

        $errorRateLast5m = $snapshotsLast5mTotal > 0 ? round($snapshotsLast5mErrors / $snapshotsLast5mTotal, 6) : 0.0;
        $errorRateLast1h = $snapshotsLast1hTotal > 0 ? round($snapshotsLast1hErrors / $snapshotsLast1hTotal, 6) : 0.0;

        $snapshotsLast5mAvgResponseMs = UrlSnapshot::query()
            ->where('fetched_at', '>=', $window5m)
            ->whereNull('error_message')
            ->whereNotNull('response_time_ms')
            ->avg('response_time_ms');
        $snapshotsLast5mAvgResponseMs = is_numeric($snapshotsLast5mAvgResponseMs) ? (float) $snapshotsLast5mAvgResponseMs : null;

        $queueWaitValues = [];
        $multiRegionVerificationsLast1h = 0;
        $multiRegionConfirmedLast1h = 0;
        $multiRegionSuppressedLast1h = 0;
        $multiRegionExcludedRegionsLast1h = 0;
        $multiRegionRegionAttempts = [];
        $multiRegionRegionFailures = [];
        UrlSnapshot::query()
            ->where('fetched_at', '>=', $window1h)
            ->whereNotNull('meta')
            ->orderByDesc('id')
            ->limit(10000)
            ->get(['meta'])
            ->each(function (UrlSnapshot $snapshot) use (&$queueWaitValues, &$multiRegionVerificationsLast1h, &$multiRegionConfirmedLast1h, &$multiRegionSuppressedLast1h, &$multiRegionExcludedRegionsLast1h, &$multiRegionRegionAttempts, &$multiRegionRegionFailures): void {
                $meta = is_array($snapshot->meta) ? $snapshot->meta : [];
                $queueWaitMs = $meta['queue_wait_ms'] ?? null;

                if (is_numeric($queueWaitMs)) {
                    $queueWaitValues[] = (float) $queueWaitMs;
                }

                $multiRegion = is_array($meta['multi_region'] ?? null) ? $meta['multi_region'] : null;

                if (is_array($multiRegion) && (bool) ($multiRegion['performed'] ?? false)) {
                    $multiRegionVerificationsLast1h++;

                    $excludedRegions = is_array($multiRegion['excluded_regions'] ?? null) ? $multiRegion['excluded_regions'] : [];
                    $multiRegionExcludedRegionsLast1h += count($excludedRegions);

                    $selectedRegions = is_array($multiRegion['selected_regions'] ?? null) ? $multiRegion['selected_regions'] : [];
                    $primaryRegion = is_string($selectedRegions[0] ?? null) ? strtolower(trim((string) $selectedRegions[0])) : null;

                    if (is_string($primaryRegion) && $primaryRegion !== '') {
                        $multiRegionRegionAttempts[$primaryRegion] = ((int) ($multiRegionRegionAttempts[$primaryRegion] ?? 0)) + 1;
                        $multiRegionRegionFailures[$primaryRegion] = ((int) ($multiRegionRegionFailures[$primaryRegion] ?? 0)) + 1;
                    }

                    $probeResults = is_array($multiRegion['probe_results'] ?? null) ? $multiRegion['probe_results'] : [];

                    foreach ($probeResults as $probeResult) {
                        if (! is_array($probeResult)) {
                            continue;
                        }

                        $region = is_string($probeResult['region'] ?? null) ? strtolower(trim((string) $probeResult['region'])) : '';

                        if ($region === '') {
                            continue;
                        }

                        $multiRegionRegionAttempts[$region] = ((int) ($multiRegionRegionAttempts[$region] ?? 0)) + 1;

                        if ((bool) ($probeResult['is_down'] ?? false)) {
                            $multiRegionRegionFailures[$region] = ((int) ($multiRegionRegionFailures[$region] ?? 0)) + 1;
                        }
                    }

                    if ((bool) ($multiRegion['confirmed'] ?? false)) {
                        $multiRegionConfirmedLast1h++;
                    } else {
                        $multiRegionSuppressedLast1h++;
                    }
                }
            });

        $queueWaitP95 = $this->percentile($queueWaitValues, 95.0);
        $queueWaitAvg = $queueWaitValues !== [] ? array_sum($queueWaitValues) / count($queueWaitValues) : null;

        $storageBytesTotal = (int) (UrlSnapshot::query()
            ->selectRaw('COALESCE(SUM(COALESCE(body_bytes_stored, 0) + COALESCE(screenshot_bytes_stored, 0)), 0) as bytes')
            ->value('bytes') ?? 0);

        $storageBytesLast24h = (int) (UrlSnapshot::query()
            ->where('fetched_at', '>=', $window24h)
            ->selectRaw('COALESCE(SUM(COALESCE(body_bytes_stored, 0) + COALESCE(screenshot_bytes_stored, 0)), 0) as bytes')
            ->value('bytes') ?? 0);

        $storageTierStats = UrlSnapshot::query()
            ->selectRaw("COALESCE(NULLIF(storage_tier, ''), 'hot') as tier")
            ->selectRaw('COUNT(*) as snapshots')
            ->selectRaw('COALESCE(SUM(COALESCE(body_bytes_stored, 0) + COALESCE(screenshot_bytes_stored, 0)), 0) as bytes')
            ->groupBy('tier')
            ->get()
            ->map(fn ($row): array => [
                'tier' => is_string($row->tier) && trim($row->tier) !== '' ? trim((string) $row->tier) : 'hot',
                'snapshots' => max(0, (int) $row->snapshots),
                'bytes' => max(0, (int) $row->bytes),
            ])
            ->all();

        $storageByTenant = [];
        $archiveRowsTotal = 0;
        $archiveBytesTotal = 0;

        if (Schema::hasTable('tenants')) {
            $defaultTier = (string) config('wids.default_plan_tier', 'lite');

            $storageByTenant = UrlSnapshot::query()
                ->join('monitored_urls', 'url_snapshots.monitored_url_id', '=', 'monitored_urls.id')
                ->join('tenants', 'monitored_urls.tenant_id', '=', 'tenants.id')
                ->select([
                    'monitored_urls.tenant_id as tenant_id',
                    'tenants.plan_tier as plan_tier',
                ])
                ->selectRaw('COALESCE(SUM(COALESCE(url_snapshots.body_bytes_stored, 0) + COALESCE(url_snapshots.screenshot_bytes_stored, 0)), 0) as bytes')
                ->groupBy('monitored_urls.tenant_id', 'tenants.plan_tier')
                ->get()
                ->map(function ($row) use ($defaultTier): array {
                    $tier = is_string($row->plan_tier) && trim($row->plan_tier) !== '' ? strtolower(trim($row->plan_tier)) : $defaultTier;

                    return [
                        'tenant_id' => (int) $row->tenant_id,
                        'plan_tier' => $tier,
                        'bytes' => max(0, (int) $row->bytes),
                    ];
                })
                ->all();
        }

        if (Schema::hasTable('url_snapshot_archives')) {
            $archiveRowsTotal = (int) DB::table('url_snapshot_archives')->count();
            $archiveBytesTotal = (int) (DB::table('url_snapshot_archives')
                ->selectRaw('COALESCE(SUM(COALESCE(body_bytes_stored, 0) + COALESCE(screenshot_bytes_stored, 0)), 0) as bytes')
                ->value('bytes') ?? 0);
        }

        $privateProbesTotal = 0;
        $privateProbesActive = 0;
        $privateProbesOnline = 0;
        $privateProbesStale = 0;
        $privateProbesPolicyAutoDisabled = 0;
        $privateProbesResidencyViolations = 0;
        $privateProbeTaskCounts = [];
        $privateProbeLeasesExpired = 0;
        $privateProbeMonitorsTotal = 0;
        $privateProbeMonitorsDue = 0;
        $privateProbeMonitorsDueWithoutOnlineProbe = 0;

        if (Schema::hasTable('private_probes')) {
            $privateProbesTotal = (int) DB::table('private_probes')->count();
            $privateProbesActive = (int) DB::table('private_probes')->where('is_active', true)->count();

            $staleAfterMinutes = max(1, min(10_080, (int) config('wids.private_probes.stale_after_minutes', 10)));
            $staleCutoff = $now->copy()->subMinutes($staleAfterMinutes);

            $privateProbesStale = (int) DB::table('private_probes')
                ->where('is_active', true)
                ->where(function ($query) use ($staleCutoff): void {
                    $query->whereNull('last_seen_at')
                        ->orWhere('last_seen_at', '<', $staleCutoff);
                })
                ->count();

            $privateProbesOnline = max(0, $privateProbesActive - $privateProbesStale);

            $policyService = app(\App\Services\PrivateProbeFleetPolicyService::class);
            $privateProbeRows = DB::table('private_probes')
                ->select(['tenant_id', 'is_active', 'region_hint', 'meta'])
                ->get();

            foreach ($privateProbeRows as $row) {
                $meta = $row->meta;

                if (is_string($meta)) {
                    $decoded = json_decode($meta, true);
                    $meta = is_array($decoded) ? $decoded : [];
                }

                if (! is_array($meta)) {
                    $meta = [];
                }

                if ((bool) data_get($meta, 'fleet_policy.auto_disabled', false)) {
                    $privateProbesPolicyAutoDisabled++;
                }

                $isActive = (bool) $row->is_active;
                $regionHint = is_string($row->region_hint) ? $row->region_hint : null;

                if ($isActive && ! $policyService->isRegionAllowedForTenantId((int) $row->tenant_id, $regionHint)) {
                    $privateProbesResidencyViolations++;
                }
            }
        }

        if (Schema::hasTable('private_probe_scan_tasks')) {
            $privateProbeTaskCounts = DB::table('private_probe_scan_tasks')
                ->select('status', DB::raw('COUNT(*) as c'))
                ->groupBy('status')
                ->pluck('c', 'status')
                ->all();

            $privateProbeLeasesExpired = (int) DB::table('private_probe_scan_tasks')
                ->where('status', 'leased')
                ->whereNotNull('lease_expires_at')
                ->where('lease_expires_at', '<', $now)
                ->count();
        }

        if (Schema::hasTable('tags') && Schema::hasTable('monitored_url_tag')) {
            $privateProbeMonitorTag = strtolower(trim((string) config('wids.private_probes.monitor_tag', 'private_probe')));

            if ($privateProbeMonitorTag !== '') {
                $privateProbeMonitorBase = DB::table('monitored_urls')
                    ->join('monitored_url_tag', 'monitored_urls.id', '=', 'monitored_url_tag.monitored_url_id')
                    ->join('tags', function ($join) use ($privateProbeMonitorTag): void {
                        $join->on('tags.id', '=', 'monitored_url_tag.tag_id')
                            ->where('tags.name', '=', $privateProbeMonitorTag);
                    })
                    ->where('monitored_urls.is_active', true);

                $privateProbeMonitorsTotal = (int) (clone $privateProbeMonitorBase)
                    ->distinct()
                    ->count('monitored_urls.id');

                $privateProbeDueBase = (clone $privateProbeMonitorBase)
                    ->where(function ($query) use ($now): void {
                        $query->whereNull('monitored_urls.next_scan_at')
                            ->orWhere('monitored_urls.next_scan_at', '<=', $now);
                    });

                $privateProbeMonitorsDue = (int) (clone $privateProbeDueBase)
                    ->distinct()
                    ->count('monitored_urls.id');

                if ($privateProbeMonitorsDue > 0) {
                    $onlineProbeTenantIds = [];

                    if (Schema::hasTable('private_probes')) {
                        $staleAfterMinutes = max(1, min(10_080, (int) config('wids.private_probes.stale_after_minutes', 10)));
                        $staleCutoff = $now->copy()->subMinutes($staleAfterMinutes);

                        $onlineProbeTenantIds = DB::table('private_probes')
                            ->where('is_active', true)
                            ->whereNotNull('last_seen_at')
                            ->where('last_seen_at', '>=', $staleCutoff)
                            ->select('tenant_id')
                            ->distinct()
                            ->pluck('tenant_id')
                            ->map(fn ($value): int => (int) $value)
                            ->values()
                            ->all();
                    }

                    $privateProbeMonitorsDueWithoutOnlineProbe = (int) (clone $privateProbeDueBase)
                        ->when(
                            $onlineProbeTenantIds !== [],
                            fn ($query) => $query->whereNotIn('monitored_urls.tenant_id', $onlineProbeTenantIds)
                        )
                        ->distinct()
                        ->count('monitored_urls.id');
                }
            }
        }

        $queueAutoscalingService = app(QueueAutoscalingService::class);
        $queueMetrics = $queueAutoscalingService->collectQueueMetricsFromRedis();
        $queueAutoscalingPlan = null;

        if ($queueAutoscalingService->isEnabled() && $queueMetrics !== []) {
            $queueAutoscalingPlan = $queueAutoscalingService->recommendFromQueueMetrics($queueMetrics);
        }

        $lines = [];

        $lines[] = '# HELP TruMonit_monitored_urls_total Total monitored URLs (all tenants).';
        $lines[] = '# TYPE TruMonit_monitored_urls_total gauge';
        $lines[] = 'TruMonit_monitored_urls_total '.$monitorsTotal;

        $lines[] = '# HELP TruMonit_monitored_urls_active_total Active monitored URLs (all tenants).';
        $lines[] = '# TYPE TruMonit_monitored_urls_active_total gauge';
        $lines[] = 'TruMonit_monitored_urls_active_total '.$monitorsActive;

        $lines[] = '# HELP TruMonit_monitored_urls_due_total Due monitored URLs (by next_scan_at).';
        $lines[] = '# TYPE TruMonit_monitored_urls_due_total gauge';
        $lines[] = 'TruMonit_monitored_urls_due_total '.$monitorsDue;

        if ($scanLagP95 !== null) {
            $lines[] = '# HELP TruMonit_scan_lag_seconds_p95 P95 scan lag for due monitors (now - next_scan_at).';
            $lines[] = '# TYPE TruMonit_scan_lag_seconds_p95 gauge';
            $lines[] = 'TruMonit_scan_lag_seconds_p95 '.round($scanLagP95, 2);
        }

        if ($scanLagMax !== null) {
            $lines[] = '# HELP TruMonit_scan_lag_seconds_max Max scan lag for due monitors (now - next_scan_at).';
            $lines[] = '# TYPE TruMonit_scan_lag_seconds_max gauge';
            $lines[] = 'TruMonit_scan_lag_seconds_max '.round($scanLagMax, 2);
        }

        $lines[] = '# HELP TruMonit_incidents_total Incidents by status (all tenants).';
        $lines[] = '# TYPE TruMonit_incidents_total gauge';
        foreach (['open', 'ack', 'resolved'] as $status) {
            $value = (int) ($incidentCounts[$status] ?? 0);
            $lines[] = 'TruMonit_incidents_total{status="'.$this->label((string) $status).'"} '.$value;
        }

        if ($failedJobsTotal !== null) {
            $lines[] = '# HELP TruMonit_failed_jobs_total Failed queue jobs (DB).';
            $lines[] = '# TYPE TruMonit_failed_jobs_total gauge';
            $lines[] = 'TruMonit_failed_jobs_total '.$failedJobsTotal;
        }

        $lines[] = '# HELP TruMonit_url_snapshots_last_5m_total URL snapshots created in the last 5 minutes.';
        $lines[] = '# TYPE TruMonit_url_snapshots_last_5m_total gauge';
        $lines[] = 'TruMonit_url_snapshots_last_5m_total '.$snapshotsLast5mTotal;

        $lines[] = '# HELP TruMonit_url_snapshots_last_5m_errors_total URL snapshots with errors in the last 5 minutes.';
        $lines[] = '# TYPE TruMonit_url_snapshots_last_5m_errors_total gauge';
        $lines[] = 'TruMonit_url_snapshots_last_5m_errors_total '.$snapshotsLast5mErrors;

        $lines[] = '# HELP TruMonit_url_snapshots_last_5m_ok_total URL snapshots without errors in the last 5 minutes.';
        $lines[] = '# TYPE TruMonit_url_snapshots_last_5m_ok_total gauge';
        $lines[] = 'TruMonit_url_snapshots_last_5m_ok_total '.$snapshotsLast5mOk;

        if ($snapshotsLast5mAvgResponseMs !== null) {
            $lines[] = '# HELP TruMonit_url_snapshots_last_5m_avg_response_time_ms Average HTTP response_time_ms in the last 5 minutes (ok only).';
            $lines[] = '# TYPE TruMonit_url_snapshots_last_5m_avg_response_time_ms gauge';
            $lines[] = 'TruMonit_url_snapshots_last_5m_avg_response_time_ms '.round($snapshotsLast5mAvgResponseMs, 2);
        }

        $lines[] = '# HELP TruMonit_error_rate_last_5m Snapshot error ratio in last 5 minutes (0..1).';
        $lines[] = '# TYPE TruMonit_error_rate_last_5m gauge';
        $lines[] = 'TruMonit_error_rate_last_5m '.round($errorRateLast5m, 6);

        $lines[] = '# HELP TruMonit_error_rate_last_1h Snapshot error ratio in last 1 hour (0..1).';
        $lines[] = '# TYPE TruMonit_error_rate_last_1h gauge';
        $lines[] = 'TruMonit_error_rate_last_1h '.round($errorRateLast1h, 6);

        if ($queueWaitAvg !== null) {
            $lines[] = '# HELP TruMonit_queue_wait_ms_last_1h_avg Average queue wait for scans in last 1 hour.';
            $lines[] = '# TYPE TruMonit_queue_wait_ms_last_1h_avg gauge';
            $lines[] = 'TruMonit_queue_wait_ms_last_1h_avg '.round($queueWaitAvg, 2);
        }

        if ($queueWaitP95 !== null) {
            $lines[] = '# HELP TruMonit_queue_wait_ms_last_1h_p95 P95 queue wait for scans in last 1 hour.';
            $lines[] = '# TYPE TruMonit_queue_wait_ms_last_1h_p95 gauge';
            $lines[] = 'TruMonit_queue_wait_ms_last_1h_p95 '.round($queueWaitP95, 2);
        }

        $lines[] = '# HELP TruMonit_multi_region_verifications_last_1h_total Multi-region outage verification attempts in last 1 hour.';
        $lines[] = '# TYPE TruMonit_multi_region_verifications_last_1h_total gauge';
        $lines[] = 'TruMonit_multi_region_verifications_last_1h_total '.$multiRegionVerificationsLast1h;

        $lines[] = '# HELP TruMonit_multi_region_confirmed_last_1h_total Multi-region outage confirmations in last 1 hour.';
        $lines[] = '# TYPE TruMonit_multi_region_confirmed_last_1h_total gauge';
        $lines[] = 'TruMonit_multi_region_confirmed_last_1h_total '.$multiRegionConfirmedLast1h;

        $lines[] = '# HELP TruMonit_multi_region_suppressed_last_1h_total Multi-region suppressed down events in last 1 hour.';
        $lines[] = '# TYPE TruMonit_multi_region_suppressed_last_1h_total gauge';
        $lines[] = 'TruMonit_multi_region_suppressed_last_1h_total '.$multiRegionSuppressedLast1h;

        $lines[] = '# HELP TruMonit_multi_region_excluded_regions_last_1h_total Regions excluded by health policy in last 1 hour.';
        $lines[] = '# TYPE TruMonit_multi_region_excluded_regions_last_1h_total gauge';
        $lines[] = 'TruMonit_multi_region_excluded_regions_last_1h_total '.$multiRegionExcludedRegionsLast1h;

        $lines[] = '# HELP TruMonit_multi_region_region_attempts_last_1h Multi-region verification attempts by region in last 1 hour.';
        $lines[] = '# TYPE TruMonit_multi_region_region_attempts_last_1h gauge';
        $lines[] = '# HELP TruMonit_multi_region_region_failures_last_1h Multi-region failures by region in last 1 hour.';
        $lines[] = '# TYPE TruMonit_multi_region_region_failures_last_1h gauge';
        $lines[] = '# HELP TruMonit_multi_region_region_failure_ratio_last_1h Multi-region failure ratio by region in last 1 hour (0..1).';
        $lines[] = '# TYPE TruMonit_multi_region_region_failure_ratio_last_1h gauge';

        $configuredMultiRegionRegions = config('wids.multi_region.regions', []);
        $configuredMultiRegionRegions = is_array($configuredMultiRegionRegions) ? $configuredMultiRegionRegions : [];

        $allMultiRegionRegions = [];

        foreach ($configuredMultiRegionRegions as $region) {
            if (! is_string($region)) {
                continue;
            }

            $value = strtolower(trim($region));

            if ($value === '') {
                continue;
            }

            $allMultiRegionRegions[$value] = true;
        }

        foreach (array_keys($multiRegionRegionAttempts) as $region) {
            $value = strtolower(trim((string) $region));

            if ($value === '') {
                continue;
            }

            $allMultiRegionRegions[$value] = true;
        }

        ksort($allMultiRegionRegions);

        foreach (array_keys($allMultiRegionRegions) as $region) {
            $attempts = max(0, (int) ($multiRegionRegionAttempts[$region] ?? 0));
            $failures = max(0, (int) ($multiRegionRegionFailures[$region] ?? 0));
            $ratio = $attempts > 0 ? round($failures / $attempts, 6) : 0.0;

            $lines[] = 'TruMonit_multi_region_region_attempts_last_1h{region="'.$this->label((string) $region).'"} '.$attempts;
            $lines[] = 'TruMonit_multi_region_region_failures_last_1h{region="'.$this->label((string) $region).'"} '.$failures;
            $lines[] = 'TruMonit_multi_region_region_failure_ratio_last_1h{region="'.$this->label((string) $region).'"} '.$ratio;
        }

        $lines[] = '# HELP TruMonit_storage_bytes_total Stored bytes for snapshots and screenshots.';
        $lines[] = '# TYPE TruMonit_storage_bytes_total gauge';
        $lines[] = 'TruMonit_storage_bytes_total '.$storageBytesTotal;

        $lines[] = '# HELP TruMonit_storage_bytes_last_24h Stored bytes added in last 24h.';
        $lines[] = '# TYPE TruMonit_storage_bytes_last_24h gauge';
        $lines[] = 'TruMonit_storage_bytes_last_24h '.$storageBytesLast24h;

        if ($storageTierStats !== []) {
            $lines[] = '# HELP TruMonit_storage_snapshots_total Number of snapshots by storage tier.';
            $lines[] = '# TYPE TruMonit_storage_snapshots_total gauge';

            foreach ($storageTierStats as $row) {
                $lines[] = 'TruMonit_storage_snapshots_total{tier="'.$this->label((string) $row['tier']).'"} '.(int) $row['snapshots'];
            }

            $lines[] = '# HELP TruMonit_storage_bytes_by_tier Stored bytes by storage tier.';
            $lines[] = '# TYPE TruMonit_storage_bytes_by_tier gauge';

            foreach ($storageTierStats as $row) {
                $lines[] = 'TruMonit_storage_bytes_by_tier{tier="'.$this->label((string) $row['tier']).'"} '.(int) $row['bytes'];
            }
        }

        $lines[] = '# HELP TruMonit_private_probes_total Total private probes.';
        $lines[] = '# TYPE TruMonit_private_probes_total gauge';
        $lines[] = 'TruMonit_private_probes_total '.$privateProbesTotal;

        $lines[] = '# HELP TruMonit_private_probes_active_total Active private probes.';
        $lines[] = '# TYPE TruMonit_private_probes_active_total gauge';
        $lines[] = 'TruMonit_private_probes_active_total '.$privateProbesActive;

        $lines[] = '# HELP TruMonit_private_probes_online_total Active private probes seen within stale threshold.';
        $lines[] = '# TYPE TruMonit_private_probes_online_total gauge';
        $lines[] = 'TruMonit_private_probes_online_total '.$privateProbesOnline;

        $lines[] = '# HELP TruMonit_private_probes_stale_total Active private probes stale or never seen.';
        $lines[] = '# TYPE TruMonit_private_probes_stale_total gauge';
        $lines[] = 'TruMonit_private_probes_stale_total '.$privateProbesStale;

        $lines[] = '# HELP TruMonit_private_probes_policy_auto_disabled_total Private probes auto-disabled by managed fleet policy.';
        $lines[] = '# TYPE TruMonit_private_probes_policy_auto_disabled_total gauge';
        $lines[] = 'TruMonit_private_probes_policy_auto_disabled_total '.$privateProbesPolicyAutoDisabled;

        $lines[] = '# HELP TruMonit_private_probes_residency_violations_total Active private probes violating residency policy.';
        $lines[] = '# TYPE TruMonit_private_probes_residency_violations_total gauge';
        $lines[] = 'TruMonit_private_probes_residency_violations_total '.$privateProbesResidencyViolations;

        if ($privateProbeTaskCounts !== []) {
            $lines[] = '# HELP TruMonit_private_probe_scan_tasks_total Private probe scan tasks by status.';
            $lines[] = '# TYPE TruMonit_private_probe_scan_tasks_total gauge';

            foreach ($privateProbeTaskCounts as $status => $count) {
                $lines[] = 'TruMonit_private_probe_scan_tasks_total{status="'.$this->label((string) $status).'"} '.(int) $count;
            }
        }

        $lines[] = '# HELP TruMonit_private_probe_leases_expired_total Leased private probe scan tasks past lease expiry.';
        $lines[] = '# TYPE TruMonit_private_probe_leases_expired_total gauge';
        $lines[] = 'TruMonit_private_probe_leases_expired_total '.$privateProbeLeasesExpired;

        $lines[] = '# HELP TruMonit_private_probe_monitors_total Active monitors tagged for private probes.';
        $lines[] = '# TYPE TruMonit_private_probe_monitors_total gauge';
        $lines[] = 'TruMonit_private_probe_monitors_total '.$privateProbeMonitorsTotal;

        $lines[] = '# HELP TruMonit_private_probe_monitors_due_total Due active monitors tagged for private probes.';
        $lines[] = '# TYPE TruMonit_private_probe_monitors_due_total gauge';
        $lines[] = 'TruMonit_private_probe_monitors_due_total '.$privateProbeMonitorsDue;

        $lines[] = '# HELP TruMonit_private_probe_monitors_due_without_online_probe_total Due private-probe monitors where tenant has no online probe.';
        $lines[] = '# TYPE TruMonit_private_probe_monitors_due_without_online_probe_total gauge';
        $lines[] = 'TruMonit_private_probe_monitors_due_without_online_probe_total '.$privateProbeMonitorsDueWithoutOnlineProbe;

        if ($storageByTenant !== []) {
            $lines[] = '# HELP TruMonit_storage_bytes_by_tenant Stored bytes by tenant and plan tier.';
            $lines[] = '# TYPE TruMonit_storage_bytes_by_tenant gauge';

            foreach ($storageByTenant as $row) {
                $lines[] = 'TruMonit_storage_bytes_by_tenant{tenant_id="'.$this->label((string) $row['tenant_id']).'",plan_tier="'.$this->label((string) $row['plan_tier']).'"} '.$row['bytes'];
            }
        }

        $lines[] = '# HELP TruMonit_storage_archive_rows_total Rows in snapshot archive table.';
        $lines[] = '# TYPE TruMonit_storage_archive_rows_total gauge';
        $lines[] = 'TruMonit_storage_archive_rows_total '.$archiveRowsTotal;

        $lines[] = '# HELP TruMonit_storage_archive_bytes_total Stored bytes tracked in snapshot archive table.';
        $lines[] = '# TYPE TruMonit_storage_archive_bytes_total gauge';
        $lines[] = 'TruMonit_storage_archive_bytes_total '.$archiveBytesTotal;

        if ($queueMetrics !== []) {
            $lines[] = '# HELP TruMonit_redis_queue_jobs_total Redis queue sizes by queue and state.';
            $lines[] = '# TYPE TruMonit_redis_queue_jobs_total gauge';

            foreach ($queueMetrics as $queue => $counts) {
                foreach (['ready', 'delayed', 'reserved'] as $state) {
                    $value = (int) ($counts[$state] ?? 0);
                    $lines[] = 'TruMonit_redis_queue_jobs_total{queue="'.$this->label((string) $queue).'",state="'.$this->label($state).'"} '.$value;
                }
            }
        }

        if (is_array($queueAutoscalingPlan)) {
            $lines[] = '# HELP TruMonit_queue_ready_jobs_weighted_total Weighted ready backlog used for autoscaling.';
            $lines[] = '# TYPE TruMonit_queue_ready_jobs_weighted_total gauge';
            $lines[] = 'TruMonit_queue_ready_jobs_weighted_total '.(float) ($queueAutoscalingPlan['weighted_ready_total'] ?? 0);

            $lines[] = '# HELP TruMonit_queue_ready_jobs_per_worker_target Target ready jobs per queue worker.';
            $lines[] = '# TYPE TruMonit_queue_ready_jobs_per_worker_target gauge';
            $lines[] = 'TruMonit_queue_ready_jobs_per_worker_target '.(int) ($queueAutoscalingPlan['target_ready_jobs_per_worker'] ?? 0);

            $lines[] = '# HELP TruMonit_queue_workers_recommended Recommended queue worker count from autoscaling plan.';
            $lines[] = '# TYPE TruMonit_queue_workers_recommended gauge';
            $lines[] = 'TruMonit_queue_workers_recommended '.(int) ($queueAutoscalingPlan['recommended_workers'] ?? 0);

            $lines[] = '# HELP TruMonit_queue_workers_min_configured Minimum configured queue worker count for autoscaling.';
            $lines[] = '# TYPE TruMonit_queue_workers_min_configured gauge';
            $lines[] = 'TruMonit_queue_workers_min_configured '.(int) ($queueAutoscalingPlan['min_workers'] ?? 0);

            $lines[] = '# HELP TruMonit_queue_workers_max_configured Maximum configured queue worker count for autoscaling.';
            $lines[] = '# TYPE TruMonit_queue_workers_max_configured gauge';
            $lines[] = 'TruMonit_queue_workers_max_configured '.(int) ($queueAutoscalingPlan['max_workers'] ?? 0);
        }

        $lines[] = '# HELP TruMonit_metrics_generated_at_seconds Unix timestamp when metrics were generated.';
        $lines[] = '# TYPE TruMonit_metrics_generated_at_seconds gauge';
        $lines[] = 'TruMonit_metrics_generated_at_seconds '.$now->getTimestamp();

        return implode("\n", $lines)."\n";
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

    private function label(string $value): string
    {
        return str_replace(['\\', '"', "\n"], ['\\\\', '\\"', ' '], $value);
    }

    private function normalizeMetricsCacheTtl(int $ttlSeconds): int
    {
        return min(60, max(0, $ttlSeconds));
    }
}
