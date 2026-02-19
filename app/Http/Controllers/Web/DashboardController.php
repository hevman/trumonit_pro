<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\MonitoredUrl;
use App\Models\UrlSnapshot;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = $request->user()?->tenant_id;

        abort_if($tenantId === null, 400, 'User has no tenant.');

        $now = now();

        $monitors = MonitoredUrl::query()
            ->where('tenant_id', $tenantId)
            ->select([
                'id',
                'display_name',
                'url',
                'is_active',
                'muted_until',
                'expected_status_min',
                'expected_status_max',
            ])
            ->with([
                'latestSnapshot' => fn ($query) => $query->select([
                    'url_snapshots.id',
                    'url_snapshots.monitored_url_id',
                    'url_snapshots.http_status',
                    'url_snapshots.error_message',
                    'url_snapshots.response_time_ms',
                    'url_snapshots.fetched_at',
                ]),
            ])
            ->get();

        $urlsCount = $monitors->count();

        $isOwner = ($request->user()?->role ?? 'owner') !== 'member';

        $stateCounts = [
            'up' => 0,
            'down' => 0,
            'paused' => 0,
            'muted' => 0,
            'unknown' => 0,
        ];
        $healthyResponseTimes = [];

        foreach ($monitors as $monitor) {
            if (! (bool) $monitor->is_active) {
                $stateCounts['paused']++;

                continue;
            }

            if ($monitor->muted_until?->isFuture()) {
                $stateCounts['muted']++;

                continue;
            }

            $latestSnapshot = $monitor->latestSnapshot;

            if (! $latestSnapshot) {
                $stateCounts['unknown']++;

                continue;
            }

            if ($this->monitorIsUp($monitor)) {
                $stateCounts['up']++;

                if (is_numeric($latestSnapshot->response_time_ms)) {
                    $healthyResponseTimes[] = (int) $latestSnapshot->response_time_ms;
                }

                continue;
            }

            $stateCounts['down']++;
        }

        $uptimeBase = max(0, $urlsCount - $stateCounts['paused'] - $stateCounts['muted']);
        $uptimePercent = $uptimeBase > 0
            ? round(($stateCounts['up'] / $uptimeBase) * 100, 1)
            : null;

        $avgHealthyResponseMs = $healthyResponseTimes !== []
            ? (int) round(array_sum($healthyResponseTimes) / count($healthyResponseTimes))
            : null;

        $openIncidentsCount = Incident::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'open')
            ->count();

        $criticalOpenIncidentsCount = Incident::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'open')
            ->where('severity', '>=', 80)
            ->count();

        $newIncidents24hCount = Incident::query()
            ->where('tenant_id', $tenantId)
            ->where('detected_at', '>=', $now->copy()->subDay())
            ->count();

        $latestIncidents = Incident::query()
            ->where('tenant_id', $tenantId)
            ->with([
                'monitoredUrl' => fn ($query) => $query->select(['id', 'display_name', 'url']),
            ])
            ->orderByDesc('detected_at')
            ->limit(10)
            ->get();

        $incidentTrend = $this->buildIncidentTrend($tenantId);
        $responseTrend = $this->buildResponseTrend($tenantId);
        $topIncidentKinds = $this->buildTopIncidentKinds($tenantId);
        $topIncidentKindsMax = max(1, max(array_column($topIncidentKinds, 'total') ?: [0]));

        $stateDistribution = collect([
            ['key' => 'up', 'color_class' => 'bg-emerald-500'],
            ['key' => 'down', 'color_class' => 'bg-red-500'],
            ['key' => 'unknown', 'color_class' => 'bg-amber-500'],
            ['key' => 'muted', 'color_class' => 'bg-indigo-500'],
            ['key' => 'paused', 'color_class' => 'bg-gray-500'],
        ])
            ->map(function (array $row) use ($stateCounts, $urlsCount): array {
                $count = (int) ($stateCounts[$row['key']] ?? 0);
                $percent = $urlsCount > 0
                    ? (float) round(($count / $urlsCount) * 100, 1)
                    : 0.0;

                return [
                    'key' => $row['key'],
                    'count' => $count,
                    'percent' => $percent,
                    'color_class' => $row['color_class'],
                ];
            })
            ->values()
            ->all();

        return view('app.dashboard', [
            'urlsCount' => $urlsCount,
            'openIncidentsCount' => $openIncidentsCount,
            'criticalOpenIncidentsCount' => $criticalOpenIncidentsCount,
            'newIncidents24hCount' => $newIncidents24hCount,
            'latestIncidents' => $latestIncidents,
            'showOnboarding' => $isOwner && $urlsCount === 0,
            'isOwner' => $isOwner,
            'uptimePercent' => $uptimePercent,
            'uptimeBase' => $uptimeBase,
            'upMonitorsCount' => $stateCounts['up'],
            'avgHealthyResponseMs' => $avgHealthyResponseMs,
            'stateDistribution' => $stateDistribution,
            'incidentTrend' => $incidentTrend,
            'responseTrend' => $responseTrend,
            'topIncidentKinds' => $topIncidentKinds,
            'topIncidentKindsMax' => $topIncidentKindsMax,
        ]);
    }

    private function monitorIsUp(MonitoredUrl $monitor): bool
    {
        $snapshot = $monitor->latestSnapshot;

        if (! $snapshot) {
            return false;
        }

        if (is_string($snapshot->error_message) && trim($snapshot->error_message) !== '') {
            return false;
        }

        if (! is_numeric($snapshot->http_status)) {
            return false;
        }

        $status = (int) $snapshot->http_status;

        return $status >= (int) $monitor->expected_status_min
            && $status <= (int) $monitor->expected_status_max;
    }

    /**
     * @return array{labels: list<string>, dates: list<string>, values: list<int>, has_data: bool, total: int, line_points: string, area_points: string}
     */
    private function buildIncidentTrend(int $tenantId): array
    {
        $endDay = CarbonImmutable::now()->startOfDay();
        $startDay = $endDay->subDays(6);

        $buckets = [];

        for ($day = $startDay; $day->lessThanOrEqualTo($endDay); $day = $day->addDay()) {
            $buckets[$day->format('Y-m-d')] = [
                'label' => $day->format('D'),
                'value' => 0,
            ];
        }

        $rows = Incident::query()
            ->where('tenant_id', $tenantId)
            ->where('detected_at', '>=', $startDay)
            ->get(['detected_at']);

        foreach ($rows as $row) {
            $key = $row->detected_at?->toDateString();

            if ($key !== null && isset($buckets[$key])) {
                $buckets[$key]['value']++;
            }
        }

        $labels = array_values(array_column($buckets, 'label'));
        $dates = array_keys($buckets);
        $values = array_map('intval', array_values(array_column($buckets, 'value')));
        $total = array_sum($values);
        $chart = $this->buildSparkline($values);

        return [
            'labels' => $labels,
            'dates' => $dates,
            'values' => $values,
            'total' => $total,
            'has_data' => $total > 0,
            'line_points' => $chart['line_points'],
            'area_points' => $chart['area_points'],
        ];
    }

    /**
     * @return array{labels: list<string>, starts_at: list<string>, ends_at: list<string>, values: list<int>, has_data: bool, samples: int, latest_avg_ms: ?int, line_points: string, area_points: string}
     */
    private function buildResponseTrend(int $tenantId): array
    {
        $endHour = CarbonImmutable::now()->startOfHour();
        $startHour = $endHour->subHours(23);

        $buckets = [];

        for ($hour = $startHour; $hour->lessThanOrEqualTo($endHour); $hour = $hour->addHour()) {
            $buckets[$hour->format('Y-m-d H:00')] = [
                'label' => $hour->format('H:i'),
                'sum' => 0,
                'count' => 0,
            ];
        }

        $rows = UrlSnapshot::query()
            ->join('monitored_urls', 'monitored_urls.id', '=', 'url_snapshots.monitored_url_id')
            ->where('monitored_urls.tenant_id', $tenantId)
            ->where('url_snapshots.fetched_at', '>=', $startHour)
            ->whereNotNull('url_snapshots.response_time_ms')
            ->orderBy('url_snapshots.fetched_at')
            ->get([
                'url_snapshots.fetched_at',
                'url_snapshots.response_time_ms',
            ]);

        $samples = 0;

        foreach ($rows as $row) {
            $fetchedAt = $row->fetched_at;

            if (! $fetchedAt instanceof CarbonInterface) {
                $fetchedAt = CarbonImmutable::parse((string) $fetchedAt);
            }

            $key = $fetchedAt->copy()->startOfHour()->format('Y-m-d H:00');

            if (! isset($buckets[$key])) {
                continue;
            }

            $buckets[$key]['sum'] += (int) $row->response_time_ms;
            $buckets[$key]['count']++;
            $samples++;
        }

        $labels = [];
        $startsAt = [];
        $endsAt = [];
        $values = [];
        $latestAvgMs = null;

        foreach ($buckets as $bucketStart => $bucket) {
            $bucketStartAt = CarbonImmutable::parse($bucketStart);
            $labels[] = $bucket['label'];
            $startsAt[] = $bucketStartAt->toDateTimeString();
            $endsAt[] = $bucketStartAt->addHour()->subSecond()->toDateTimeString();
            $avg = $bucket['count'] > 0
                ? (int) round($bucket['sum'] / $bucket['count'])
                : 0;
            $values[] = $avg;

            if ($avg > 0) {
                $latestAvgMs = $avg;
            }
        }

        $chart = $this->buildSparkline($values);

        return [
            'labels' => $labels,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'values' => $values,
            'has_data' => $samples > 0,
            'samples' => $samples,
            'latest_avg_ms' => $latestAvgMs,
            'line_points' => $chart['line_points'],
            'area_points' => $chart['area_points'],
        ];
    }

    /**
     * @return list<array{kind: string, total: int}>
     */
    private function buildTopIncidentKinds(int $tenantId): array
    {
        return Incident::query()
            ->where('tenant_id', $tenantId)
            ->where('detected_at', '>=', CarbonImmutable::now()->subDays(7))
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
    }

    /**
     * @param  list<int>  $values
     * @return array{line_points: string, area_points: string}
     */
    private function buildSparkline(array $values): array
    {
        $width = 560;
        $height = 180;
        $padding = 16;
        $count = count($values);

        if ($count === 0) {
            return [
                'line_points' => '',
                'area_points' => '',
            ];
        }

        $min = min($values);
        $max = max($values);
        $range = max(1, $max - $min);
        $step = $count > 1
            ? ($width - ($padding * 2)) / ($count - 1)
            : 0.0;
        $plotHeight = $height - ($padding * 2);
        $baselineY = $height - $padding;

        $linePoints = [];
        $areaCorePoints = [];

        foreach (array_values($values) as $index => $value) {
            $x = (int) round($padding + ($step * $index));

            if ($max === $min) {
                $y = (int) round($padding + ($plotHeight / 2));
            } else {
                $normalized = ($value - $min) / $range;
                $y = (int) round($baselineY - ($normalized * $plotHeight));
            }

            $point = $x.','.$y;
            $linePoints[] = $point;
            $areaCorePoints[] = $point;
        }

        $firstX = $padding;
        $lastX = (int) round($padding + ($step * max(0, $count - 1)));
        $areaPoints = $firstX.','.$baselineY.' '.implode(' ', $areaCorePoints).' '.$lastX.','.$baselineY;

        return [
            'line_points' => implode(' ', $linePoints),
            'area_points' => $areaPoints,
        ];
    }
}
