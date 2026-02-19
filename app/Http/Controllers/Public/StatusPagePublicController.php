<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\StatusPage;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class StatusPagePublicController extends Controller
{
    public function show(Request $request, string $slug)
    {
        if (! Schema::hasTable('status_pages')) {
            abort(404);
        }

        $slug = strtolower(trim($slug));
        abort_if($slug === '' || strlen($slug) > 80, 404);

        $statusPage = StatusPage::query()
            ->where('slug', $slug)
            ->where('is_public', true)
            ->first();

        abort_if(! $statusPage, 404);

        return $this->renderStatusPageResponse(
            statusPage: $statusPage,
            cacheKeyPrefix: 'status_page_public:slug:'.$slug,
            incidentDayFilter: $this->normalizeIncidentDay($request->query('incident_day')),
            componentStateFilter: $this->normalizeComponentState($request->query('component_state')),
        );
    }

    public function showForRequestHost(Request $request): Response|null
    {
        if (! Schema::hasTable('status_pages')) {
            return null;
        }

        $host = $this->normalizeHost($request->getHost());

        if (! is_string($host) || $host === '') {
            return null;
        }

        $appHost = $this->normalizeHost((string) parse_url((string) config('app.url', ''), PHP_URL_HOST));

        if (is_string($appHost) && $appHost !== '' && $host === $appHost) {
            return null;
        }

        $statusPage = StatusPage::query()
            ->where('custom_domain', $host)
            ->where('is_public', true)
            ->first();

        if (! $statusPage) {
            return null;
        }

        return $this->renderStatusPageResponse(
            statusPage: $statusPage,
            cacheKeyPrefix: 'status_page_public:domain:'.$host,
            incidentDayFilter: $this->normalizeIncidentDay($request->query('incident_day')),
            componentStateFilter: $this->normalizeComponentState($request->query('component_state')),
        );
    }

    private function renderStatusPageResponse(
        StatusPage $statusPage,
        string $cacheKeyPrefix,
        ?string $incidentDayFilter = null,
        ?string $componentStateFilter = null
    ): Response
    {
        $ttlSeconds = $this->normalizeTtl((int) ($statusPage->cache_ttl_seconds ?? 30));

        $cacheKey = $cacheKeyPrefix
            .':status_page_id:'.$statusPage->id
            .':ttl:'.$ttlSeconds
            .':incident_day:'.($incidentDayFilter ?? 'all')
            .':component_state:'.($componentStateFilter ?? 'all')
            .':version:'.$statusPage->updated_at?->getTimestamp();

        $payload = Cache::remember($cacheKey, now()->addSeconds($ttlSeconds), function () use ($statusPage, $incidentDayFilter, $componentStateFilter): array {
            return $this->buildPayload($statusPage, $incidentDayFilter, $componentStateFilter);
        });

        $response = response()
            ->view('public.status-page', $payload, 200, [
                'Cache-Control' => sprintf(
                    'public, max-age=%d, stale-while-revalidate=%d, stale-if-error=120',
                    $ttlSeconds,
                    $ttlSeconds
                ),
                'Vary' => 'Accept-Encoding',
                'X-Status-Cache-TTL' => (string) $ttlSeconds,
            ]);

        return $response;
    }

    /**
     * @return array{
     *   statusPage: array<string, mixed>,
     *   monitors: list<array<string, mixed>>,
     *   componentStateCounts: array<string, int>,
     *   openIncidents: list<array<string, mixed>>,
     *   recentIncidents: list<array<string, mixed>>,
     *   incidentTrend: array{labels: list<string>, values: list<int>, dates: list<string>, total: int},
     *   selectedIncidentDay: ?string,
     *   selectedComponentState: ?string
     * }
     */
    private function buildPayload(
        StatusPage $statusPage,
        ?string $incidentDayFilter = null,
        ?string $componentStateFilter = null
    ): array
    {
        $statusPage->loadMissing(['monitoredUrls.latestSnapshot']);

        $allMonitors = [];

        foreach ($statusPage->monitoredUrls as $monitoredUrl) {
            $latest = $monitoredUrl->latestSnapshot;
            $meta = is_array($latest?->meta) ? $latest->meta : [];
            $journey = is_array($meta['journey'] ?? null) ? $meta['journey'] : null;

            $label = $monitoredUrl->display_name;
            $host = null;

            if (! is_string($label) || trim($label) === '') {
                $host = parse_url($monitoredUrl->url, PHP_URL_HOST);
                $label = is_string($host) && $host !== '' ? $host : $monitoredUrl->url;
            }

            $status = 'unknown';

            if (! $monitoredUrl->is_active) {
                $status = 'paused';
            } elseif (! $latest) {
                $status = 'unknown';
            } elseif (is_string($latest->error_message) && trim($latest->error_message) !== '') {
                $status = 'down';
            } elseif ($latest->http_status !== null &&
                ($latest->http_status < $monitoredUrl->expected_status_min || $latest->http_status > $monitoredUrl->expected_status_max)) {
                $status = 'down';
            } elseif (is_array($journey) && ($journey['ok'] ?? true) === false) {
                $status = 'down';
            } else {
                $status = 'up';
            }

            $allMonitors[] = [
                'id' => $monitoredUrl->id,
                'label' => $label,
                'url' => $monitoredUrl->url,
                'is_active' => (bool) $monitoredUrl->is_active,
                'status' => $status,
                'last_checked_at' => $latest?->fetched_at?->toISOString(),
                'response_time_ms' => $latest?->response_time_ms,
                'http_status' => $latest?->http_status,
            ];
        }

        $componentStateCounts = [
            'up' => 0,
            'down' => 0,
            'paused' => 0,
            'unknown' => 0,
        ];

        foreach ($allMonitors as $monitor) {
            $status = (string) ($monitor['status'] ?? 'unknown');

            if (array_key_exists($status, $componentStateCounts)) {
                $componentStateCounts[$status]++;
            }
        }

        $monitors = $allMonitors;

        if (is_string($componentStateFilter)) {
            $monitors = array_values(array_filter(
                $allMonitors,
                fn (array $monitor): bool => (string) ($monitor['status'] ?? 'unknown') === $componentStateFilter
            ));
        }

        $selectedMonitorIds = collect($monitors)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        $openIncidents = [];
        $recentIncidents = [];
        $incidentTrendBuckets = [];

        $trendStart = now()->startOfDay()->subDays(6);
        $trendEnd = now()->startOfDay();

        for ($day = $trendStart->copy(); $day->lessThanOrEqualTo($trendEnd); $day->addDay()) {
            $incidentTrendBuckets[$day->toDateString()] = [
                'label' => $day->format('d M'),
                'value' => 0,
            ];
        }

        if ($selectedMonitorIds !== []) {
            $openIncidents = Incident::query()
                ->where('tenant_id', $statusPage->tenant_id)
                ->whereIn('monitored_url_id', $selectedMonitorIds)
                ->whereIn('status', ['open', 'ack'])
                ->orderByDesc('detected_at')
                ->limit(50)
                ->get(['id', 'monitored_url_id', 'kind', 'severity', 'status', 'summary', 'detected_at'])
                ->map(fn (Incident $i) => [
                    'id' => $i->id,
                    'monitored_url_id' => $i->monitored_url_id,
                    'kind' => $i->kind,
                    'severity' => $i->severity,
                    'status' => $i->status,
                    'summary' => $i->summary,
                    'detected_at' => $i->detected_at?->toISOString(),
                ])
                ->values()
                ->all();

            $recentIncidentsQuery = Incident::query()
                ->where('tenant_id', $statusPage->tenant_id)
                ->whereIn('monitored_url_id', $selectedMonitorIds)
                ->where('detected_at', '>=', now()->subDays(7))
                ->when(
                    is_string($incidentDayFilter),
                    fn ($query) => $query->whereDate('detected_at', $incidentDayFilter)
                )
                ->orderByDesc('detected_at');

            $recentIncidents = $recentIncidentsQuery
                ->limit(50)
                ->get(['id', 'monitored_url_id', 'kind', 'severity', 'status', 'summary', 'detected_at', 'resolved_at'])
                ->map(fn (Incident $i) => [
                    'id' => $i->id,
                    'monitored_url_id' => $i->monitored_url_id,
                    'kind' => $i->kind,
                    'severity' => $i->severity,
                    'status' => $i->status,
                    'summary' => $i->summary,
                    'detected_at' => $i->detected_at?->toISOString(),
                    'resolved_at' => $i->resolved_at?->toISOString(),
                ])
                ->values()
                ->all();

            $trendRows = Incident::query()
                ->where('tenant_id', $statusPage->tenant_id)
                ->whereIn('monitored_url_id', $selectedMonitorIds)
                ->where('detected_at', '>=', $trendStart)
                ->where('detected_at', '<=', $trendEnd->copy()->endOfDay())
                ->selectRaw('DATE(detected_at) as detected_day, COUNT(*) as total')
                ->groupBy('detected_day')
                ->get();

            foreach ($trendRows as $trendRow) {
                $dayKey = is_string($trendRow->detected_day)
                    ? $trendRow->detected_day
                    : CarbonImmutable::parse((string) $trendRow->detected_day)->toDateString();

                if (! isset($incidentTrendBuckets[$dayKey])) {
                    continue;
                }

                $incidentTrendBuckets[$dayKey]['value'] = (int) $trendRow->total;
            }
        }

        $hasIssues = collect($allMonitors)->contains(fn ($m) => ($m['status'] ?? null) === 'down');
        $trendLabels = array_values(array_column($incidentTrendBuckets, 'label'));
        $trendValues = array_map('intval', array_values(array_column($incidentTrendBuckets, 'value')));
        $trendDates = array_keys($incidentTrendBuckets);

        return [
            'statusPage' => [
                'title' => $statusPage->title,
                'slug' => $statusPage->slug,
                'custom_domain' => $statusPage->custom_domain,
                'description' => $statusPage->description,
                'generated_at' => now()->toISOString(),
                'has_issues' => $hasIssues,
            ],
            'monitors' => $monitors,
            'componentStateCounts' => $componentStateCounts,
            'openIncidents' => $openIncidents,
            'recentIncidents' => $recentIncidents,
            'incidentTrend' => [
                'labels' => $trendLabels,
                'values' => $trendValues,
                'dates' => $trendDates,
                'total' => array_sum($trendValues),
            ],
            'selectedIncidentDay' => $incidentDayFilter,
            'selectedComponentState' => $componentStateFilter,
        ];
    }

    private function normalizeHost(?string $host): ?string
    {
        if (! is_string($host)) {
            return null;
        }

        $host = strtolower(trim($host));
        $host = rtrim($host, '.');

        return $host !== '' ? $host : null;
    }

    private function normalizeTtl(int $ttlSeconds): int
    {
        return min(600, max(5, $ttlSeconds));
    }

    private function normalizeIncidentDay(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if ($value === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            $day = CarbonImmutable::createFromFormat('Y-m-d', $value);
        } catch (\Throwable) {
            return null;
        }

        if (! $day) {
            return null;
        }

        $day = $day->startOfDay();
        $min = now()->startOfDay()->subDays(6);
        $max = now()->startOfDay();

        if ($day->lessThan($min) || $day->greaterThan($max)) {
            return null;
        }

        return $day->toDateString();
    }

    private function normalizeComponentState(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $state = strtolower(trim($value));

        if (! in_array($state, ['up', 'down', 'paused', 'unknown'], true)) {
            return null;
        }

        return $state;
    }
}
