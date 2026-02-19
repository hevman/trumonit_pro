<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\MonitoredUrl;
use App\Models\Tenant;
use App\Models\TenantUsageMetric;
use App\Models\UrlSnapshot;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

class WeeklyDigestService
{
    /**
     * @return array{start: Carbon, end: Carbon}
     */
    public function defaultWindow(?Carbon $reference = null): array
    {
        $reference ??= now();

        $start = $reference
            ->copy()
            ->startOfWeek(Carbon::MONDAY)
            ->subWeek()
            ->startOfDay();

        $end = $start
            ->copy()
            ->endOfWeek(Carbon::SUNDAY)
            ->endOfDay();

        return [
            'start' => $start,
            'end' => $end,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function buildTenantDigest(Tenant $tenant, Carbon $weekStart, Carbon $weekEnd): array
    {
        $tenantId = (int) $tenant->id;
        $monitorsTotal = MonitoredUrl::query()
            ->where('tenant_id', $tenantId)
            ->count();

        $activeMonitors = MonitoredUrl::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->count();

        $scansBase = UrlSnapshot::query()
            ->join('monitored_urls', 'monitored_urls.id', '=', 'url_snapshots.monitored_url_id')
            ->where('monitored_urls.tenant_id', $tenantId)
            ->whereBetween('url_snapshots.fetched_at', [$weekStart, $weekEnd]);

        $scansTotal = (clone $scansBase)->count();

        $scansErrorTotal = (clone $scansBase)
            ->where(function ($query): void {
                $query->whereRaw("(url_snapshots.error_message IS NOT NULL AND TRIM(url_snapshots.error_message) <> '')")
                    ->orWhereNull('url_snapshots.http_status')
                    ->orWhereColumn('url_snapshots.http_status', '<', 'monitored_urls.expected_status_min')
                    ->orWhereColumn('url_snapshots.http_status', '>', 'monitored_urls.expected_status_max');
            })
            ->count();

        $upChecks = (clone $scansBase)
            ->whereRaw("(url_snapshots.error_message IS NULL OR TRIM(url_snapshots.error_message) = '')")
            ->whereNotNull('url_snapshots.http_status')
            ->whereColumn('url_snapshots.http_status', '>=', 'monitored_urls.expected_status_min')
            ->whereColumn('url_snapshots.http_status', '<=', 'monitored_urls.expected_status_max')
            ->count();

        $uptimePercent = $scansTotal > 0
            ? round(($upChecks / $scansTotal) * 100, 2)
            : null;

        $incidentsOpened = Incident::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('detected_at', [$weekStart, $weekEnd])
            ->count();

        $criticalIncidents = Incident::query()
            ->where('tenant_id', $tenantId)
            ->where('severity', '>=', 80)
            ->whereBetween('detected_at', [$weekStart, $weekEnd])
            ->count();

        $incidentsResolved = Incident::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('resolved_at')
            ->whereBetween('resolved_at', [$weekStart, $weekEnd])
            ->count();

        $mttrMinutes = $this->resolvedMttrMinutes($tenantId, $weekStart, $weekEnd);

        $topIncidentKinds = Incident::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('detected_at', [$weekStart, $weekEnd])
            ->selectRaw('kind, COUNT(*) as total')
            ->groupBy('kind')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn ($row): array => [
                'kind' => (string) $row->kind,
                'total' => (int) $row->total,
            ])
            ->values()
            ->all();

        $unstableMonitors = UrlSnapshot::query()
            ->join('monitored_urls', 'monitored_urls.id', '=', 'url_snapshots.monitored_url_id')
            ->where('monitored_urls.tenant_id', $tenantId)
            ->whereBetween('url_snapshots.fetched_at', [$weekStart, $weekEnd])
            ->selectRaw('monitored_urls.id as monitored_url_id')
            ->selectRaw('monitored_urls.display_name as display_name')
            ->selectRaw('monitored_urls.url as url')
            ->selectRaw('COUNT(*) as total_checks')
            ->selectRaw("
                SUM(
                    CASE
                        WHEN (url_snapshots.error_message IS NOT NULL AND TRIM(url_snapshots.error_message) <> '')
                             OR url_snapshots.http_status IS NULL
                             OR url_snapshots.http_status < monitored_urls.expected_status_min
                             OR url_snapshots.http_status > monitored_urls.expected_status_max
                        THEN 1
                        ELSE 0
                    END
                ) as down_checks
            ")
            ->selectRaw("
                AVG(
                    CASE
                        WHEN (url_snapshots.error_message IS NULL OR TRIM(url_snapshots.error_message) = '')
                             AND url_snapshots.response_time_ms IS NOT NULL
                        THEN url_snapshots.response_time_ms
                        ELSE NULL
                    END
                ) as avg_response_ms
            ")
            ->groupBy('monitored_urls.id', 'monitored_urls.display_name', 'monitored_urls.url')
            ->orderByDesc('down_checks')
            ->orderByDesc('total_checks')
            ->limit(8)
            ->get()
            ->map(function ($row): ?array {
                $totalChecks = max(0, (int) ($row->total_checks ?? 0));
                $downChecks = max(0, (int) ($row->down_checks ?? 0));

                if ($totalChecks <= 0 || $downChecks <= 0) {
                    return null;
                }

                return [
                    'monitored_url_id' => (int) $row->monitored_url_id,
                    'label' => trim((string) ($row->display_name ?? '')) !== ''
                        ? (string) $row->display_name
                        : (string) ($row->url ?? '-'),
                    'url' => (string) ($row->url ?? '-'),
                    'total_checks' => $totalChecks,
                    'down_checks' => $downChecks,
                    'down_ratio_percent' => round(($downChecks / $totalChecks) * 100, 1),
                    'avg_response_ms' => $row->avg_response_ms !== null
                        ? (int) round((float) $row->avg_response_ms)
                        : null,
                ];
            })
            ->filter()
            ->values()
            ->all();

        $dailyUsageSeries = $this->dailyUsageSeries($tenantId, $weekStart, $weekEnd);

        return [
            'tenant' => [
                'id' => $tenantId,
                'name' => (string) $tenant->name,
                'slug' => (string) $tenant->slug,
                'plan_tier' => (string) ($tenant->plan_tier ?? ''),
            ],
            'window' => [
                'start' => $weekStart->toDateString(),
                'end' => $weekEnd->toDateString(),
                'generated_at' => now()->toISOString(),
            ],
            'overview' => [
                'monitors_total' => $monitorsTotal,
                'active_monitors' => $activeMonitors,
                'scans_total' => $scansTotal,
                'scans_error_total' => $scansErrorTotal,
                'uptime_percent' => $uptimePercent,
                'incidents_opened_total' => $incidentsOpened,
                'incidents_resolved_total' => $incidentsResolved,
                'incidents_critical_total' => $criticalIncidents,
                'mttr_minutes' => $mttrMinutes,
            ],
            'top_incident_kinds' => $topIncidentKinds,
            'unstable_monitors' => $unstableMonitors,
            'daily_usage' => $dailyUsageSeries,
        ];
    }

    private function resolvedMttrMinutes(int $tenantId, Carbon $weekStart, Carbon $weekEnd): ?int
    {
        $resolvedRows = Incident::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('resolved_at')
            ->whereBetween('resolved_at', [$weekStart, $weekEnd])
            ->get(['detected_at', 'resolved_at']);

        if ($resolvedRows->isEmpty()) {
            return null;
        }

        $sum = 0;
        $count = 0;

        foreach ($resolvedRows as $row) {
            if (! $row->detected_at instanceof Carbon || ! $row->resolved_at instanceof Carbon) {
                continue;
            }

            $minutes = $row->detected_at->diffInMinutes($row->resolved_at, false);

            if ($minutes < 0) {
                continue;
            }

            $sum += $minutes;
            $count++;
        }

        if ($count <= 0) {
            return null;
        }

        return (int) round($sum / $count);
    }

    /**
     * @return array{
     *   labels: list<string>,
     *   dates: list<string>,
     *   scans: list<int>,
     *   incidents: list<int>,
     *   api_requests: list<int>
     * }
     */
    private function dailyUsageSeries(int $tenantId, Carbon $weekStart, Carbon $weekEnd): array
    {
        $labels = [];
        $dates = [];
        $scans = [];
        $incidents = [];
        $apiRequests = [];

        if (! Schema::hasTable('tenant_usage_metrics')) {
            for ($date = $weekStart->copy()->startOfDay(); $date->lessThanOrEqualTo($weekEnd->copy()->startOfDay()); $date->addDay()) {
                $labels[] = $date->format('D');
                $dates[] = $date->toDateString();
                $scans[] = 0;
                $incidents[] = 0;
                $apiRequests[] = 0;
            }

            return [
                'labels' => $labels,
                'dates' => $dates,
                'scans' => $scans,
                'incidents' => $incidents,
                'api_requests' => $apiRequests,
            ];
        }

        $buckets = [];

        for ($date = $weekStart->copy()->startOfDay(); $date->lessThanOrEqualTo($weekEnd->copy()->startOfDay()); $date->addDay()) {
            $day = $date->toDateString();
            $buckets[$day] = [
                'label' => $date->format('D'),
                BillingUsageService::METRIC_SCANS_TOTAL => 0,
                BillingUsageService::METRIC_INCIDENTS_TOTAL => 0,
                BillingUsageService::METRIC_API_REQUESTS_TOTAL => 0,
            ];
        }

        $rows = TenantUsageMetric::query()
            ->where('tenant_id', $tenantId)
            ->where('period_type', BillingUsageService::PERIOD_DAY)
            ->whereBetween('period_start_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->whereIn('metric_key', [
                BillingUsageService::METRIC_SCANS_TOTAL,
                BillingUsageService::METRIC_INCIDENTS_TOTAL,
                BillingUsageService::METRIC_API_REQUESTS_TOTAL,
            ])
            ->get(['period_start_date', 'metric_key', 'value']);

        foreach ($rows as $row) {
            $day = (string) $row->period_start_date;
            $metricKey = (string) $row->metric_key;

            if (! isset($buckets[$day])) {
                continue;
            }

            $buckets[$day][$metricKey] = max(0, (int) $row->value);
        }

        foreach ($buckets as $day => $bucket) {
            $labels[] = (string) $bucket['label'];
            $dates[] = (string) $day;
            $scans[] = (int) ($bucket[BillingUsageService::METRIC_SCANS_TOTAL] ?? 0);
            $incidents[] = (int) ($bucket[BillingUsageService::METRIC_INCIDENTS_TOTAL] ?? 0);
            $apiRequests[] = (int) ($bucket[BillingUsageService::METRIC_API_REQUESTS_TOTAL] ?? 0);
        }

        return [
            'labels' => $labels,
            'dates' => $dates,
            'scans' => $scans,
            'incidents' => $incidents,
            'api_requests' => $apiRequests,
        ];
    }
}

