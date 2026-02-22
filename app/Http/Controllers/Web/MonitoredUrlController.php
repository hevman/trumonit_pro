<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Jobs\ScanMonitoredUrlJob;
use App\Models\Incident;
use App\Models\MonitoredUrl;
use App\Models\SavedView;
use App\Models\Tag;
use App\Models\UrlSnapshot;
use App\Rules\PublicUrl;
use App\Services\AuditLogService;
use App\Services\ExpectedStatusRangeService;
use App\Services\MonitorTemplateService;
use App\Services\ScanQueueRoutingService;
use App\Services\TagService;
use App\Services\TenantQuotaService;
use App\Services\TenantNotificationChannelService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MonitoredUrlController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 400, 'User has no tenant.');

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'tag' => ['nullable', 'string', 'max:64'],
            'scan_mode' => ['nullable', 'string', Rule::in(['http', 'journey', 'port', 'heartbeat'])],
            'is_active' => ['nullable', 'string', Rule::in(['1', '0'])],
            'muted' => ['nullable', 'string', Rule::in(['1', '0'])],
            'state' => ['nullable', 'string', Rule::in(['up', 'down', 'paused', 'muted'])],
            'frequency_min' => ['nullable', 'integer', 'min:1', 'max:43200'],
            'frequency_max' => ['nullable', 'integer', 'min:1', 'max:43200'],
            'timeout_min' => ['nullable', 'integer', 'min:1', 'max:120'],
            'timeout_max' => ['nullable', 'integer', 'min:1', 'max:120'],
            'last_scanned_from' => ['nullable', 'date'],
            'last_scanned_to' => ['nullable', 'date'],
        ]);

        $search = is_string($filters['q'] ?? null) ? trim((string) $filters['q']) : null;
        $tagInput = is_string($filters['tag'] ?? null) ? trim((string) $filters['tag']) : null;
        $scanMode = is_string($filters['scan_mode'] ?? null) ? trim((string) $filters['scan_mode']) : null;
        $isActiveFilter = is_string($filters['is_active'] ?? null) ? trim((string) $filters['is_active']) : null;
        $mutedFilter = is_string($filters['muted'] ?? null) ? trim((string) $filters['muted']) : null;
        $stateFilter = is_string($filters['state'] ?? null) ? trim((string) $filters['state']) : null;
        $frequencyMin = is_numeric($filters['frequency_min'] ?? null) ? (int) $filters['frequency_min'] : null;
        $frequencyMax = is_numeric($filters['frequency_max'] ?? null) ? (int) $filters['frequency_max'] : null;
        $timeoutMin = is_numeric($filters['timeout_min'] ?? null) ? (int) $filters['timeout_min'] : null;
        $timeoutMax = is_numeric($filters['timeout_max'] ?? null) ? (int) $filters['timeout_max'] : null;
        $lastScannedFromRaw = isset($filters['last_scanned_from']) ? trim((string) $filters['last_scanned_from']) : null;
        $lastScannedToRaw = isset($filters['last_scanned_to']) ? trim((string) $filters['last_scanned_to']) : null;
        $lastScannedFrom = is_string($lastScannedFromRaw) && $lastScannedFromRaw !== ''
            ? Carbon::parse($lastScannedFromRaw)
            : null;
        $lastScannedTo = is_string($lastScannedToRaw) && $lastScannedToRaw !== ''
            ? Carbon::parse($lastScannedToRaw)
            : null;
        $tag = null;
        $searchId = null;
        $now = now();

        if ($lastScannedFrom instanceof Carbon && strlen((string) $lastScannedFromRaw) <= 10) {
            $lastScannedFrom = $lastScannedFrom->copy()->startOfDay();
        }

        if ($lastScannedTo instanceof Carbon && strlen((string) $lastScannedToRaw) <= 10) {
            $lastScannedTo = $lastScannedTo->copy()->endOfDay();
        }

        if (is_string($search) && $search !== '') {
            $candidate = ltrim($search, '#');

            if (ctype_digit($candidate)) {
                $searchId = (int) $candidate;
            }
        }

        if (is_string($tagInput) && $tagInput !== '') {
            $tag = app(TagService::class)->normalize($tagInput);

            if ($tag === null) {
                throw ValidationException::withMessages([
                    'tag' => ['Invalid tag. Use a-z0-9:_- (max 32 chars).'],
                ]);
            }
        }

        if (is_int($frequencyMin) && is_int($frequencyMax) && $frequencyMin > $frequencyMax) {
            [$frequencyMin, $frequencyMax] = [$frequencyMax, $frequencyMin];
        }

        if (is_int($timeoutMin) && is_int($timeoutMax) && $timeoutMin > $timeoutMax) {
            [$timeoutMin, $timeoutMax] = [$timeoutMax, $timeoutMin];
        }

        if ($lastScannedFrom instanceof Carbon && $lastScannedTo instanceof Carbon && $lastScannedFrom->greaterThan($lastScannedTo)) {
            $swapFrom = $lastScannedTo->copy();
            $swapTo = $lastScannedFrom->copy();

            if (is_string($lastScannedToRaw) && strlen($lastScannedToRaw) <= 10) {
                $swapFrom = $swapFrom->startOfDay();
            }

            if (is_string($lastScannedFromRaw) && strlen($lastScannedFromRaw) <= 10) {
                $swapTo = $swapTo->endOfDay();
            }

            [$lastScannedFrom, $lastScannedTo] = [$swapFrom, $swapTo];
        }

        $monitoredUrls = MonitoredUrl::query()
            ->where('tenant_id', $tenantId)
            ->with(['latestSnapshot', 'tags'])
            ->when(is_string($search) && $search !== '', function ($query) use ($search, $searchId): void {
                $needle = mb_strtolower($search);

                $query->where(function ($q) use ($needle, $searchId): void {
                    if (is_int($searchId)) {
                        $q->orWhere('id', $searchId);
                    }

                    $q->orWhereRaw('LOWER(url) LIKE ?', ['%'.$needle.'%'])
                        ->orWhereRaw('LOWER(display_name) LIKE ?', ['%'.$needle.'%']);
                });
            })
            ->when(is_string($scanMode) && $scanMode !== '', fn ($query) => $query->where('scan_mode', $scanMode))
            ->when($isActiveFilter === '1', fn ($query) => $query->where('is_active', true))
            ->when($isActiveFilter === '0', fn ($query) => $query->where('is_active', false))
            ->when($mutedFilter === '1', fn ($query) => $query->whereNotNull('muted_until')->where('muted_until', '>', $now))
            ->when($mutedFilter === '0', fn ($query) => $query->where(function ($q) use ($now): void {
                $q->whereNull('muted_until')
                    ->orWhere('muted_until', '<=', $now);
            }))
            ->when($frequencyMin !== null, fn ($query) => $query->where('frequency_minutes', '>=', $frequencyMin))
            ->when($frequencyMax !== null, fn ($query) => $query->where('frequency_minutes', '<=', $frequencyMax))
            ->when($timeoutMin !== null, fn ($query) => $query->where('timeout_seconds', '>=', $timeoutMin))
            ->when($timeoutMax !== null, fn ($query) => $query->where('timeout_seconds', '<=', $timeoutMax))
            ->when($lastScannedFrom instanceof Carbon, fn ($query) => $query->where('last_scanned_at', '>=', $lastScannedFrom))
            ->when($lastScannedTo instanceof Carbon, fn ($query) => $query->where('last_scanned_at', '<=', $lastScannedTo))
            ->when($stateFilter === 'paused', fn ($query) => $query->where('is_active', false))
            ->when($stateFilter === 'muted', fn ($query) => $query->where('is_active', true)->whereNotNull('muted_until')->where('muted_until', '>', $now))
            ->when($stateFilter === 'up', function ($query): void {
                $query->where('is_active', true)
                    ->whereHas('latestSnapshot', function ($sq): void {
                        $sq->whereNull('error_message')
                            ->whereNotNull('http_status')
                            ->whereColumn('url_snapshots.http_status', '>=', 'monitored_urls.expected_status_min')
                            ->whereColumn('url_snapshots.http_status', '<=', 'monitored_urls.expected_status_max');
                    });
            })
            ->when($stateFilter === 'down', function ($query): void {
                $query->where('is_active', true)
                    ->where(function ($qq): void {
                        $qq->whereDoesntHave('latestSnapshot')
                            ->orWhereHas('latestSnapshot', function ($sq): void {
                                $sq->whereNotNull('error_message')
                                    ->orWhereNull('http_status')
                                    ->orWhereColumn('url_snapshots.http_status', '<', 'monitored_urls.expected_status_min')
                                    ->orWhereColumn('url_snapshots.http_status', '>', 'monitored_urls.expected_status_max');
                            });
                    });
            })
            ->when(is_string($tag) && $tag !== '', function ($query) use ($tag): void {
                $query->whereHas('tags', fn ($q) => $q->where('name', $tag));
            })
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        $visibleMonitors = $monitoredUrls->getCollection();
        $monitorIds = $visibleMonitors
            ->pluck('id')
            ->map(fn ($value): int => (int) $value)
            ->values()
            ->all();

        $stateCounts = [
            'up' => 0,
            'down' => 0,
            'paused' => 0,
            'muted' => 0,
            'unknown' => 0,
        ];
        $latestHealthyResponseSamples = [];

        foreach ($visibleMonitors as $monitor) {
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

            if ($this->snapshotIsUp($monitor, $latestSnapshot) === true) {
                $stateCounts['up']++;

                if (is_numeric($latestSnapshot->response_time_ms)) {
                    $latestHealthyResponseSamples[] = (int) $latestSnapshot->response_time_ms;
                }

                continue;
            }

            $stateCounts['down']++;
        }

        $openIncidentsByMonitor = [];
        $openIncidentsVisibleTotal = 0;

        if ($monitorIds !== []) {
            $openIncidentsByMonitor = Incident::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'open')
                ->whereIn('monitored_url_id', $monitorIds)
                ->selectRaw('monitored_url_id, COUNT(*) as total')
                ->groupBy('monitored_url_id')
                ->pluck('total', 'monitored_url_id')
                ->map(fn ($value): int => (int) $value)
                ->all();

            $openIncidentsVisibleTotal = array_sum($openIncidentsByMonitor);
        }

        $monitorHealthAnalytics = [];
        $fleetHealthAnalytics = [
            'uptime_trend' => [
                'labels' => [],
                'starts_at' => [],
                'ends_at' => [],
                'values' => [],
                'has_data' => false,
                'total_samples' => 0,
            ],
            'response_p95_trend' => [
                'labels' => [],
                'starts_at' => [],
                'ends_at' => [],
                'values' => [],
                'has_data' => false,
                'samples' => 0,
                'latest_p95_ms' => null,
            ],
        ];
        $since = $now->copy()->subDay();
        $trendStartHour = $now->copy()->startOfHour()->subHours(23);
        $trendEndHour = $now->copy()->startOfHour();
        $hourlyBuckets = [];

        for ($hour = $trendStartHour->copy(); $hour->lessThanOrEqualTo($trendEndHour); $hour->addHour()) {
            $key = $hour->format('Y-m-d H:00');
            $hourlyBuckets[$key] = [
                'label' => $hour->format('H:i'),
                'start_at' => $hour->toDateTimeString(),
                'end_at' => $hour->copy()->addHour()->subSecond()->toDateTimeString(),
                'total' => 0,
                'up' => 0,
                'response_times' => [],
            ];
        }

        if ($monitorIds !== []) {
            $recentSnapshots = UrlSnapshot::query()
                ->whereIn('monitored_url_id', $monitorIds)
                ->where('fetched_at', '>=', $since)
                ->orderBy('monitored_url_id')
                ->orderBy('fetched_at')
                ->get([
                    'monitored_url_id',
                    'fetched_at',
                    'http_status',
                    'error_message',
                    'response_time_ms',
                ]);

            $snapshotsByMonitor = $recentSnapshots->groupBy('monitored_url_id');
            $monitorById = $visibleMonitors->keyBy('id');
            $bucketCount = 12;
            $windowSeconds = (24 * 60 * 60) / $bucketCount;
            $sinceTs = $since->getTimestamp();
            $nowTs = now()->getTimestamp();

            foreach ($monitorIds as $monitorId) {
                /** @var MonitoredUrl|null $monitor */
                $monitor = $monitorById->get($monitorId);

                if (! $monitor) {
                    continue;
                }

                $snapshots = $snapshotsByMonitor->get($monitorId, collect());

                $up = 0;
                $total = 0;
                $responseSamples = [];
                $buckets = array_fill(0, $bucketCount, ['sum' => 0, 'count' => 0]);

                foreach ($snapshots as $snapshot) {
                    $total++;

                    $isUp = $this->snapshotIsUp($monitor, $snapshot) === true;
                    if ($isUp) {
                        $up++;
                    }

                    $hasHealthyResponseSample = is_numeric($snapshot->response_time_ms)
                        && ! (is_string($snapshot->error_message) && trim($snapshot->error_message) !== '');

                    if ($hasHealthyResponseSample) {
                        $responseSamples[] = (int) $snapshot->response_time_ms;
                    }

                    $fetchedAtTs = $snapshot->fetched_at?->getTimestamp() ?? $sinceTs;
                    $offset = max(0, min(max(0, $nowTs - $sinceTs - 1), $fetchedAtTs - $sinceTs));
                    $index = (int) floor($offset / $windowSeconds);
                    $index = max(0, min($bucketCount - 1, $index));

                    $buckets[$index]['sum'] += $isUp ? 100 : 0;
                    $buckets[$index]['count']++;

                    $hourKey = $snapshot->fetched_at?->copy()->startOfHour()->format('Y-m-d H:00');

                    if (is_string($hourKey) && isset($hourlyBuckets[$hourKey])) {
                        $hourlyBuckets[$hourKey]['total']++;

                        if ($isUp) {
                            $hourlyBuckets[$hourKey]['up']++;
                        }

                        if ($hasHealthyResponseSample) {
                            $hourlyBuckets[$hourKey]['response_times'][] = (int) $snapshot->response_time_ms;
                        }
                    }
                }

                $sparkline = [];

                foreach ($buckets as $bucket) {
                    if (($bucket['count'] ?? 0) <= 0) {
                        $sparkline[] = null;

                        continue;
                    }

                    $sparkline[] = (int) round($bucket['sum'] / $bucket['count']);
                }

                $monitorHealthAnalytics[$monitorId] = [
                    'uptime_percent_24h' => $total > 0 ? round(($up / $total) * 100, 1) : null,
                    'samples_24h' => $total,
                    'avg_response_ms_24h' => $responseSamples !== []
                        ? (int) round(array_sum($responseSamples) / count($responseSamples))
                        : null,
                    'sparkline_24h' => $sparkline,
                ];
            }

            $uptimeLabels = [];
            $uptimeStartsAt = [];
            $uptimeEndsAt = [];
            $uptimeValues = [];
            $uptimeTotalSamples = 0;
            $responseP95Values = [];
            $responseSampleCount = 0;
            $latestP95 = null;

            foreach ($hourlyBuckets as $hourBucket) {
                $labelsValue = (string) ($hourBucket['label'] ?? '');
                $startValue = (string) ($hourBucket['start_at'] ?? '');
                $endValue = (string) ($hourBucket['end_at'] ?? '');
                $totalSamples = (int) ($hourBucket['total'] ?? 0);
                $upSamples = (int) ($hourBucket['up'] ?? 0);
                $responseTimes = is_array($hourBucket['response_times'] ?? null) ? $hourBucket['response_times'] : [];

                $uptimeLabels[] = $labelsValue;
                $uptimeStartsAt[] = $startValue;
                $uptimeEndsAt[] = $endValue;
                $uptimeValues[] = $totalSamples > 0
                    ? round(($upSamples / $totalSamples) * 100, 1)
                    : 0;
                $uptimeTotalSamples += $totalSamples;
                $responseSampleCount += count($responseTimes);

                $hourP95 = $this->percentileInt($responseTimes, 95.0);
                $responseP95Values[] = $hourP95 ?? 0;

                if (is_int($hourP95)) {
                    $latestP95 = $hourP95;
                }
            }

            $fleetHealthAnalytics = [
                'uptime_trend' => [
                    'labels' => $uptimeLabels,
                    'starts_at' => $uptimeStartsAt,
                    'ends_at' => $uptimeEndsAt,
                    'values' => $uptimeValues,
                    'has_data' => $uptimeTotalSamples > 0,
                    'total_samples' => $uptimeTotalSamples,
                ],
                'response_p95_trend' => [
                    'labels' => $uptimeLabels,
                    'starts_at' => $uptimeStartsAt,
                    'ends_at' => $uptimeEndsAt,
                    'values' => $responseP95Values,
                    'has_data' => $responseSampleCount > 0,
                    'samples' => $responseSampleCount,
                    'latest_p95_ms' => $latestP95,
                ],
            ];
        }

        $activeVisible = max(0, $visibleMonitors->count() - $stateCounts['paused'] - $stateCounts['muted']);
        $visibleUptime = $activeVisible > 0
            ? round(($stateCounts['up'] / $activeVisible) * 100, 1)
            : null;
        $latestHealthyResponseMs = $latestHealthyResponseSamples !== []
            ? (int) round(array_sum($latestHealthyResponseSamples) / count($latestHealthyResponseSamples))
            : null;

        $tags = Tag::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->limit(500)
            ->pluck('name');

        $savedViews = SavedView::query()
            ->where('tenant_id', $tenantId)
            ->where('scope', 'monitored_urls')
            ->orderBy('name')
            ->get(['id', 'scope', 'name', 'filters']);

        return view('app.monitored-urls.index', [
            'monitoredUrls' => $monitoredUrls,
            'filters' => [
                'q' => $search,
                'tag' => $tag,
                'scan_mode' => $scanMode,
                'is_active' => $isActiveFilter,
                'muted' => $mutedFilter,
                'state' => $stateFilter,
                'frequency_min' => $frequencyMin,
                'frequency_max' => $frequencyMax,
                'timeout_min' => $timeoutMin,
                'timeout_max' => $timeoutMax,
                'last_scanned_from' => $lastScannedFrom?->toDateTimeString(),
                'last_scanned_to' => $lastScannedTo?->toDateTimeString(),
            ],
            'tags' => $tags,
            'savedViews' => $savedViews,
            'summary' => [
                'total_results' => (int) $monitoredUrls->total(),
                'visible_count' => (int) $visibleMonitors->count(),
                'state_counts' => $stateCounts,
                'visible_uptime_percent' => $visibleUptime,
                'active_visible_count' => $activeVisible,
                'latest_healthy_response_ms' => $latestHealthyResponseMs,
                'open_incidents_visible_total' => $openIncidentsVisibleTotal,
            ],
            'openIncidentsByMonitor' => $openIncidentsByMonitor,
            'monitorHealthAnalytics' => $monitorHealthAnalytics,
            'fleetHealthAnalytics' => $fleetHealthAnalytics,
        ]);
    }

    /**
     * @param  list<int>  $values
     */
    private function percentileInt(array $values, float $percentile): ?int
    {
        if ($values === []) {
            return null;
        }

        sort($values, SORT_NUMERIC);

        $count = count($values);

        if ($count === 1) {
            return (int) round((float) $values[0]);
        }

        $boundedPercentile = min(100.0, max(0.0, $percentile));
        $position = ($boundedPercentile / 100.0) * ($count - 1);
        $lowerIndex = (int) floor($position);
        $upperIndex = (int) ceil($position);

        if ($lowerIndex === $upperIndex) {
            return (int) round((float) $values[$lowerIndex]);
        }

        $weight = $position - $lowerIndex;
        $lowerValue = (float) $values[$lowerIndex];
        $upperValue = (float) $values[$upperIndex];
        $interpolated = $lowerValue + (($upperValue - $lowerValue) * $weight);

        return (int) round($interpolated);
    }

    public function create(Request $request, MonitorTemplateService $monitorTemplateService)
    {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 400, 'User has no tenant.');

        $tenant = $request->user()?->tenant;
        $limits = app(TenantQuotaService::class)->limitsForTenant($tenant);
        $currentCount = MonitoredUrl::query()
            ->where('tenant_id', $tenantId)
            ->count();

        return view('app.monitored-urls.create', [
            'monitorTemplates' => $monitorTemplateService->frontendTemplates(),
            'quota' => [
                'tier' => $limits['tier'],
                'max_monitored_urls' => $limits['max_monitored_urls'],
                'current_monitored_urls' => $currentCount,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 400, 'User has no tenant.');

        $limits = app(TenantQuotaService::class)->limitsForTenant($request->user()?->tenant);
        $limit = (int) ($limits['max_monitored_urls'] ?? 0);

        if ($limit > 0) {
            $count = MonitoredUrl::query()->where('tenant_id', $tenantId)->count();

            if ($count >= $limit) {
                throw ValidationException::withMessages([
                    'url' => [app(TenantQuotaService::class)->monitorLimitMessage($limits)],
                ]);
            }
        }

        $monitorTemplateService = app(MonitorTemplateService::class);
        $templateKeys = $monitorTemplateService->keys();

        $data = $request->validate([
            'url' => ['required', 'string', 'max:4096', 'url', new PublicUrl],
            'display_name' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'string', 'max:500'],
            'template' => ['nullable', 'string', Rule::in($templateKeys)],
            'from_onboarding' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'frequency_minutes' => ['nullable', 'integer', 'min:1', 'max:43200'],
            'timeout_seconds' => ['nullable', 'integer', 'min:1', 'max:120'],
            'follow_redirects' => ['nullable', 'boolean'],
            'expected_status_min' => ['nullable', 'integer', 'min:100', 'max:599'],
            'expected_status_max' => ['nullable', 'integer', 'min:100', 'max:599', 'gte:expected_status_min'],
            'scan_mode' => ['nullable', 'string', Rule::in(['http', 'journey', 'port', 'heartbeat'])],
            'appearance_min_distance' => ['nullable', 'integer', 'min:0', 'max:64'],
            'alert_after_failures' => ['nullable', 'integer', 'min:1', 'max:10'],
            'alert_cooldown_minutes' => ['nullable', 'integer', 'min:0', 'max:10080'],
            'journey_steps' => ['nullable', 'string', 'max:20000'],
            'content_rules' => ['nullable', 'string', 'max:20000'],
            'keyword_must_include' => ['nullable', 'string', 'max:255'],
            'port_number' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'heartbeat_grace_minutes' => ['nullable', 'integer', 'min:0', 'max:10080'],
        ]);

        $templateKey = is_string($data['template'] ?? null) ? $data['template'] : null;
        $data = $monitorTemplateService->applyDefaults($data, $templateKey);

        try {
            [$expectedStatusMin, $expectedStatusMax] = app(ExpectedStatusRangeService::class)->resolve(
                array_key_exists('expected_status_min', $data) ? (int) $data['expected_status_min'] : null,
                array_key_exists('expected_status_max', $data) ? (int) $data['expected_status_max'] : null,
                200,
                399,
            );
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'expected_status_max' => [$e->getMessage()],
            ]);
        }

        try {
            $tagNames = app(TagService::class)->normalizeManyFromString($data['tags'] ?? null);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'tags' => [$e->getMessage()],
            ]);
        }

        $scanMode = $data['scan_mode'] ?? 'http';

        $journeySteps = null;
        if (is_string($data['journey_steps'] ?? null) && trim((string) $data['journey_steps']) !== '') {
            $decoded = json_decode((string) $data['journey_steps'], true);

            if (! is_array($decoded)) {
                throw ValidationException::withMessages([
                    'journey_steps' => ['Invalid JSON.'],
                ]);
            }

            $journeySteps = $decoded;
        }

        if ($scanMode !== 'journey') {
            $journeySteps = null;
        }

        $contentRules = null;
        if (is_string($data['content_rules'] ?? null) && trim((string) $data['content_rules']) !== '') {
            $decoded = json_decode((string) $data['content_rules'], true);

            if (! is_array($decoded)) {
                throw ValidationException::withMessages([
                    'content_rules' => ['Invalid JSON.'],
                ]);
            }

            $contentRules = $decoded;
        }
        $contentRules = is_array($contentRules) ? $contentRules : [];

        $keywordList = $this->normalizeKeywordList($data['keyword_must_include'] ?? null);
        if ($keywordList !== []) {
            $existingAllow = is_array($contentRules['allow_keywords'] ?? null) ? $contentRules['allow_keywords'] : [];
            $contentRules['enabled'] = $contentRules['enabled'] ?? true;
            $contentRules['allow_keywords'] = array_values(array_unique(array_merge($existingAllow, $keywordList)));
        }

        $portNumber = is_numeric($data['port_number'] ?? null) ? (int) $data['port_number'] : null;
        if ($scanMode === 'port' && $portNumber !== null) {
            $portRules = is_array($contentRules['port'] ?? null) ? $contentRules['port'] : [];
            $portRules['port'] = $portNumber;
            $contentRules['port'] = $portRules;
        }

        if ($scanMode === 'heartbeat') {
            $heartbeatRules = is_array($contentRules['heartbeat'] ?? null) ? $contentRules['heartbeat'] : [];
            $heartbeatRules['token'] = is_string($heartbeatRules['token'] ?? null) && trim((string) $heartbeatRules['token']) !== ''
                ? trim((string) $heartbeatRules['token'])
                : Str::random(48);
            $heartbeatRules['grace_minutes'] = is_numeric($data['heartbeat_grace_minutes'] ?? null)
                ? min(10080, max(0, (int) $data['heartbeat_grace_minutes']))
                : min(10080, max(0, (int) ($heartbeatRules['grace_minutes'] ?? 3)));
            $contentRules['heartbeat'] = $heartbeatRules;
        }

        if ($contentRules === []) {
            $contentRules = null;
        }

        $defaultAlertAfterFailures = min(10, max(1, (int) config('wids.alerts.default_after_failures', 2)));
        $defaultAlertCooldownMinutes = min(10080, max(0, (int) config('wids.alerts.default_cooldown_minutes', 10)));

        $monitoredUrl = MonitoredUrl::query()->create([
            'tenant_id' => $tenantId,
            'created_by_user_id' => $request->user()?->id,
            'url' => $data['url'],
            'display_name' => $data['display_name'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'next_scan_at' => ($data['is_active'] ?? true) ? now() : null,
            'frequency_minutes' => $data['frequency_minutes'] ?? 1440,
            'timeout_seconds' => $data['timeout_seconds'] ?? ($scanMode === 'port' ? 10 : ($scanMode === 'heartbeat' ? 5 : 30)),
            'follow_redirects' => $scanMode === 'http' || $scanMode === 'journey'
                ? ($data['follow_redirects'] ?? true)
                : false,
            'expected_status_min' => $expectedStatusMin,
            'expected_status_max' => $expectedStatusMax,
            'scan_mode' => $scanMode,
            'appearance_min_distance' => $scanMode === 'http' || $scanMode === 'journey'
                ? ($data['appearance_min_distance'] ?? null)
                : null,
            'alert_after_failures' => $data['alert_after_failures'] ?? $defaultAlertAfterFailures,
            'alert_cooldown_minutes' => $data['alert_cooldown_minutes'] ?? $defaultAlertCooldownMinutes,
            'journey_steps' => $journeySteps,
            'content_rules' => $contentRules,
        ]);

        app(TagService::class)->syncMonitoredUrlTags($monitoredUrl, $tagNames);

        app(AuditLogService::class)->log(
            tenantId: $tenantId,
            actorUserId: $request->user()?->id,
            action: 'monitored_url.created',
            auditableType: MonitoredUrl::class,
            auditableId: $monitoredUrl->id,
            data: [
                'url' => $monitoredUrl->url,
                'display_name' => $monitoredUrl->display_name,
                'tags' => $tagNames,
                'template' => $templateKey,
            ],
            request: $request,
        );

        $fromOnboarding = (bool) ($data['from_onboarding'] ?? false);

        if ($fromOnboarding) {
            $queueName = app(ScanQueueRoutingService::class)->queueForReason(
                $monitoredUrl,
                ScanQueueRoutingService::REASON_ONBOARDING
            );

            ScanMonitoredUrlJob::dispatch($monitoredUrl->id)
                ->onQueue($queueName);
        }

        $statusMessage = $fromOnboarding
            ? __('messages.monitored_urls.flash_created_dispatched')
            : __('messages.monitored_urls.flash_created');

        return redirect()
            ->route('monitored-urls.show', $monitoredUrl)
            ->with('status', $statusMessage)
            ->with('monitor_created', 1)
            ->with('first_check_dispatched', $fromOnboarding ? 1 : 0);
    }

    public function show(Request $request, MonitoredUrl $monitoredUrl)
    {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 400, 'User has no tenant.');
        abort_if($monitoredUrl->tenant_id !== $tenantId, 404);

        $monitoredUrl->loadMissing(['baselineSnapshot', 'tags']);

        $snapshots = UrlSnapshot::query()
            ->where('monitored_url_id', $monitoredUrl->id)
            ->orderByDesc('fetched_at')
            ->limit(25)
            ->get();

        $latestSnapshot = $snapshots->first();
        $latestIsUp = $this->snapshotIsUp($monitoredUrl, $latestSnapshot);

        $stats24h = $this->uptimeStatsSince($monitoredUrl, now()->subHours(24));
        $stats7d = $this->uptimeStatsSince($monitoredUrl, now()->subDays(7));

        $incidents = Incident::query()
            ->where('monitored_url_id', $monitoredUrl->id)
            ->orderByDesc('detected_at')
            ->limit(25)
            ->get();

        $channels = app(TenantNotificationChannelService::class)->channelsForTenantId((int) $tenantId);
        $hasNotificationChannels = false;
        if (is_array($channels)) {
            $hasNotificationChannels = (count($channels['webhook_urls'] ?? [])
                    + count($channels['slack_webhook_urls'] ?? [])
                    + count($channels['teams_webhook_urls'] ?? [])
                    + count($channels['discord_webhook_urls'] ?? [])
                    + count($channels['email_recipients'] ?? [])) > 0;
        }

        $appearanceMinDistance = $monitoredUrl->appearance_min_distance;
        $shouldShowBaselinePanel = ($monitoredUrl->scan_mode ?? 'http') === 'journey'
            || (is_int($appearanceMinDistance) && $appearanceMinDistance >= 0 && $appearanceMinDistance < 64);

        $contentRules = is_array($monitoredUrl->content_rules) ? $monitoredUrl->content_rules : [];
        $heartbeatRules = is_array($contentRules['heartbeat'] ?? null) ? $contentRules['heartbeat'] : [];
        $heartbeatToken = is_string($heartbeatRules['token'] ?? null) ? trim((string) $heartbeatRules['token']) : '';
        $heartbeatCheckInUrl = null;

        if (($monitoredUrl->scan_mode ?? 'http') === 'heartbeat' && $heartbeatToken !== '') {
            $heartbeatCheckInUrl = route('api.heartbeat.check_in', ['token' => $heartbeatToken]);
        }

        return view('app.monitored-urls.show', [
            'monitoredUrl' => $monitoredUrl,
            'snapshots' => $snapshots,
            'latestSnapshot' => $latestSnapshot,
            'latestIsUp' => $latestIsUp,
            'stats24h' => $stats24h,
            'stats7d' => $stats7d,
            'incidents' => $incidents,
            'hasNotificationChannels' => $hasNotificationChannels,
            'shouldShowBaselinePanel' => $shouldShowBaselinePanel,
            'heartbeatCheckInUrl' => $heartbeatCheckInUrl,
        ]);
    }

    private function snapshotIsUp(MonitoredUrl $monitoredUrl, ?UrlSnapshot $snapshot): ?bool
    {
        if (! $snapshot) {
            return null;
        }

        if (is_string($snapshot->error_message) && trim($snapshot->error_message) !== '') {
            return false;
        }

        if ($snapshot->http_status === null || ! is_numeric($snapshot->http_status)) {
            return false;
        }

        $status = (int) $snapshot->http_status;

        return $status >= $monitoredUrl->expected_status_min
            && $status <= $monitoredUrl->expected_status_max;
    }

    /**
     * @return array{total: int, up: int, uptime_percent: ?float, avg_response_time_ms: ?int}
     */
    private function uptimeStatsSince(MonitoredUrl $monitoredUrl, \DateTimeInterface $since): array
    {
        $row = UrlSnapshot::query()
            ->where('monitored_url_id', $monitoredUrl->id)
            ->where('fetched_at', '>=', $since)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw(
                'SUM(CASE WHEN error_message IS NULL AND http_status IS NOT NULL AND http_status BETWEEN ? AND ? THEN 1 ELSE 0 END) as up',
                [$monitoredUrl->expected_status_min, $monitoredUrl->expected_status_max],
            )
            ->selectRaw('AVG(CASE WHEN error_message IS NULL THEN response_time_ms END) as avg_response_time_ms')
            ->first();

        $total = (int) ($row?->total ?? 0);
        $up = (int) ($row?->up ?? 0);
        $avg = $row?->avg_response_time_ms !== null ? (int) round((float) $row->avg_response_time_ms) : null;

        return [
            'total' => $total,
            'up' => $up,
            'uptime_percent' => $total > 0 ? round(($up / $total) * 100, 2) : null,
            'avg_response_time_ms' => $avg,
        ];
    }

    public function edit(Request $request, MonitoredUrl $monitoredUrl)
    {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 400, 'User has no tenant.');
        abort_if($monitoredUrl->tenant_id !== $tenantId, 404);

        $monitoredUrl->loadMissing('tags');

        return view('app.monitored-urls.edit', [
            'monitoredUrl' => $monitoredUrl,
        ]);
    }

    public function update(Request $request, MonitoredUrl $monitoredUrl)
    {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 400, 'User has no tenant.');
        abort_if($monitoredUrl->tenant_id !== $tenantId, 404);

        $monitoredUrl->loadMissing('tags');

        $before = $monitoredUrl->only([
            'display_name',
            'is_active',
            'frequency_minutes',
            'timeout_seconds',
            'follow_redirects',
            'expected_status_min',
            'expected_status_max',
            'scan_mode',
            'appearance_min_distance',
            'alert_after_failures',
            'alert_cooldown_minutes',
        ]);

        $before['tags'] = $monitoredUrl->tags->pluck('name')->sort()->values()->all();

        $data = $request->validate([
            'display_name' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
            'frequency_minutes' => ['nullable', 'integer', 'min:1', 'max:43200'],
            'timeout_seconds' => ['nullable', 'integer', 'min:1', 'max:120'],
            'follow_redirects' => ['nullable', 'boolean'],
            'expected_status_min' => ['nullable', 'integer', 'min:100', 'max:599'],
            'expected_status_max' => ['nullable', 'integer', 'min:100', 'max:599', 'gte:expected_status_min'],
            'scan_mode' => ['nullable', 'string', Rule::in(['http', 'journey', 'port', 'heartbeat'])],
            'appearance_min_distance' => ['nullable', 'integer', 'min:0', 'max:64'],
            'alert_after_failures' => ['nullable', 'integer', 'min:1', 'max:10'],
            'alert_cooldown_minutes' => ['nullable', 'integer', 'min:0', 'max:10080'],
            'journey_steps' => ['nullable', 'string', 'max:20000'],
            'content_rules' => ['nullable', 'string', 'max:20000'],
            'keyword_must_include' => ['nullable', 'string', 'max:255'],
            'port_number' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'heartbeat_grace_minutes' => ['nullable', 'integer', 'min:0', 'max:10080'],
        ]);

        $tagNames = $before['tags'];

        if (array_key_exists('tags', $data)) {
            try {
                $tagNames = app(TagService::class)->normalizeManyFromString($data['tags'] ?? null);
            } catch (\InvalidArgumentException $e) {
                throw ValidationException::withMessages([
                    'tags' => [$e->getMessage()],
                ]);
            }
        }

        $scanMode = $data['scan_mode'] ?? $monitoredUrl->scan_mode ?? 'http';

        $journeySteps = $monitoredUrl->journey_steps;
        if (array_key_exists('journey_steps', $data)) {
            $journeySteps = null;

            if (is_string($data['journey_steps']) && trim($data['journey_steps']) !== '') {
                $decoded = json_decode((string) $data['journey_steps'], true);

                if (! is_array($decoded)) {
                    throw ValidationException::withMessages([
                        'journey_steps' => ['Invalid JSON.'],
                    ]);
                }

                $journeySteps = $decoded;
            }
        }

        if ($scanMode !== 'journey') {
            $journeySteps = null;
        }

        $contentRules = $monitoredUrl->content_rules;
        if (array_key_exists('content_rules', $data)) {
            $contentRules = null;

            if (is_string($data['content_rules']) && trim($data['content_rules']) !== '') {
                $decoded = json_decode((string) $data['content_rules'], true);

                if (! is_array($decoded)) {
                    throw ValidationException::withMessages([
                        'content_rules' => ['Invalid JSON.'],
                    ]);
                }

                $contentRules = $decoded;
            }
        }

        $contentRules = is_array($contentRules) ? $contentRules : [];

        if (array_key_exists('keyword_must_include', $data)) {
            $keywordList = $this->normalizeKeywordList($data['keyword_must_include'] ?? null);

            if ($keywordList === []) {
                unset($contentRules['allow_keywords']);
            } else {
                $contentRules['enabled'] = $contentRules['enabled'] ?? true;
                $contentRules['allow_keywords'] = $keywordList;
            }
        }

        if ($scanMode === 'port' && array_key_exists('port_number', $data) && is_numeric($data['port_number'])) {
            $portRules = is_array($contentRules['port'] ?? null) ? $contentRules['port'] : [];
            $portRules['port'] = (int) $data['port_number'];
            $contentRules['port'] = $portRules;
        }

        if ($scanMode === 'heartbeat') {
            $heartbeatRules = is_array($contentRules['heartbeat'] ?? null) ? $contentRules['heartbeat'] : [];
            $heartbeatRules['token'] = is_string($heartbeatRules['token'] ?? null) && trim((string) $heartbeatRules['token']) !== ''
                ? trim((string) $heartbeatRules['token'])
                : Str::random(48);

            if (array_key_exists('heartbeat_grace_minutes', $data) && is_numeric($data['heartbeat_grace_minutes'])) {
                $heartbeatRules['grace_minutes'] = min(10080, max(0, (int) $data['heartbeat_grace_minutes']));
            } else {
                $heartbeatRules['grace_minutes'] = min(10080, max(0, (int) ($heartbeatRules['grace_minutes'] ?? 3)));
            }

            $contentRules['heartbeat'] = $heartbeatRules;
        }

        if ($contentRules === []) {
            $contentRules = null;
        }

        try {
            [$expectedStatusMin, $expectedStatusMax] = app(ExpectedStatusRangeService::class)->resolve(
                array_key_exists('expected_status_min', $data) ? (int) $data['expected_status_min'] : null,
                array_key_exists('expected_status_max', $data) ? (int) $data['expected_status_max'] : null,
                (int) $monitoredUrl->expected_status_min,
                (int) $monitoredUrl->expected_status_max,
            );
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'expected_status_max' => [$e->getMessage()],
            ]);
        }

        $wasActive = (bool) $monitoredUrl->is_active;
        $oldFrequencyMinutes = (int) $monitoredUrl->frequency_minutes;

        $newIsActive = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : $wasActive;
        $newFrequencyMinutes = array_key_exists('frequency_minutes', $data) ? (int) $data['frequency_minutes'] : $oldFrequencyMinutes;
        $newFrequencyMinutes = min(43200, max(1, $newFrequencyMinutes));

        $nextScanAt = $monitoredUrl->next_scan_at;

        if (! $newIsActive) {
            $nextScanAt = null;
        } elseif ($wasActive === false && $newIsActive === true) {
            $nextScanAt = now();
        } elseif (array_key_exists('frequency_minutes', $data) && $newFrequencyMinutes !== $oldFrequencyMinutes) {
            $nextScanAt = $monitoredUrl->last_scanned_at !== null
                ? $monitoredUrl->last_scanned_at->copy()->addMinutes($newFrequencyMinutes)
                : now();
        }

        $monitoredUrl->fill([
            'display_name' => $data['display_name'] ?? $monitoredUrl->display_name,
            'is_active' => $data['is_active'] ?? $monitoredUrl->is_active,
            'next_scan_at' => $nextScanAt,
            'frequency_minutes' => $data['frequency_minutes'] ?? $monitoredUrl->frequency_minutes,
            'timeout_seconds' => $data['timeout_seconds'] ?? (
                $scanMode === 'port'
                    ? 10
                    : ($scanMode === 'heartbeat' ? 5 : $monitoredUrl->timeout_seconds)
            ),
            'follow_redirects' => $scanMode === 'http' || $scanMode === 'journey'
                ? ($data['follow_redirects'] ?? $monitoredUrl->follow_redirects)
                : false,
            'expected_status_min' => $expectedStatusMin,
            'expected_status_max' => $expectedStatusMax,
            'scan_mode' => $scanMode,
            'appearance_min_distance' => $scanMode === 'http' || $scanMode === 'journey'
                ? ($data['appearance_min_distance'] ?? $monitoredUrl->appearance_min_distance)
                : null,
            'alert_after_failures' => $data['alert_after_failures'] ?? $monitoredUrl->alert_after_failures,
            'alert_cooldown_minutes' => $data['alert_cooldown_minutes'] ?? $monitoredUrl->alert_cooldown_minutes,
            'journey_steps' => $journeySteps,
            'content_rules' => $contentRules,
        ]);
        $monitoredUrl->save();

        if (array_key_exists('tags', $data)) {
            app(TagService::class)->syncMonitoredUrlTags($monitoredUrl, $tagNames);
        }

        $after = $monitoredUrl->only(array_diff(array_keys($before), ['tags']));
        $after['tags'] = $monitoredUrl->tags()->pluck('name')->sort()->values()->all();

        app(AuditLogService::class)->log(
            tenantId: $tenantId,
            actorUserId: $request->user()?->id,
            action: 'monitored_url.updated',
            auditableType: MonitoredUrl::class,
            auditableId: $monitoredUrl->id,
            data: [
                'before' => $before,
                'after' => $after,
            ],
            request: $request,
        );

        return redirect()
            ->route('monitored-urls.show', $monitoredUrl)
            ->with('status', 'Monitored URL updated.');
    }

    public function destroy(Request $request, MonitoredUrl $monitoredUrl)
    {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 400, 'User has no tenant.');
        abort_if($monitoredUrl->tenant_id !== $tenantId, 404);

        $auditData = [
            'url' => $monitoredUrl->url,
            'display_name' => $monitoredUrl->display_name,
        ];

        $monitoredUrl->delete();

        app(AuditLogService::class)->log(
            tenantId: $tenantId,
            actorUserId: $request->user()?->id,
            action: 'monitored_url.deleted',
            auditableType: MonitoredUrl::class,
            auditableId: $monitoredUrl->id,
            data: $auditData,
            request: $request,
        );

        return redirect()
            ->route('monitored-urls.index')
            ->with('status', 'Monitored URL deleted.');
    }

    public function scan(Request $request, MonitoredUrl $monitoredUrl)
    {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 400, 'User has no tenant.');
        abort_if($monitoredUrl->tenant_id !== $tenantId, 404);

        $queueName = app(ScanQueueRoutingService::class)->queueForReason(
            $monitoredUrl,
            ScanQueueRoutingService::REASON_MANUAL
        );

        ScanMonitoredUrlJob::dispatch($monitoredUrl->id)
            ->onQueue($queueName);

        app(AuditLogService::class)->log(
            tenantId: $tenantId,
            actorUserId: $request->user()?->id,
            action: 'monitored_url.scan_dispatched',
            auditableType: MonitoredUrl::class,
            auditableId: $monitoredUrl->id,
            data: [
                'url' => $monitoredUrl->url,
            ],
            request: $request,
        );

        return redirect()
            ->back()
            ->with('status', 'Scan dispatched.');
    }

    public function baseline(Request $request, MonitoredUrl $monitoredUrl)
    {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 400, 'User has no tenant.');
        abort_if($monitoredUrl->tenant_id !== $tenantId, 404);

        $data = $request->validate([
            'snapshot_id' => ['required', 'integer', 'min:1'],
        ]);

        $snapshotId = (int) $data['snapshot_id'];

        $snapshot = UrlSnapshot::query()
            ->where('monitored_url_id', $monitoredUrl->id)
            ->findOrFail($snapshotId);

        $monitoredUrl->forceFill([
            'baseline_snapshot_id' => $snapshot->id,
            'baseline_set_at' => now(),
        ])->save();

        app(AuditLogService::class)->log(
            tenantId: $tenantId,
            actorUserId: $request->user()?->id,
            action: 'monitored_url.baseline_set',
            auditableType: MonitoredUrl::class,
            auditableId: $monitoredUrl->id,
            data: [
                'snapshot_id' => $snapshot->id,
            ],
            request: $request,
        );

        return redirect()
            ->back()
            ->with('status', 'Baseline updated.');
    }

    public function mute(Request $request, MonitoredUrl $monitoredUrl)
    {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 400, 'User has no tenant.');
        abort_if($monitoredUrl->tenant_id !== $tenantId, 404);

        $data = $request->validate([
            'minutes' => ['nullable', 'integer', 'min:5', 'max:10080'],
        ]);

        $minutes = isset($data['minutes']) ? (int) $data['minutes'] : 60;
        $mutedUntil = now()->addMinutes($minutes);

        $monitoredUrl->forceFill([
            'muted_until' => $mutedUntil,
        ])->save();

        app(AuditLogService::class)->log(
            tenantId: $tenantId,
            actorUserId: $request->user()?->id,
            action: 'monitored_url.muted',
            auditableType: MonitoredUrl::class,
            auditableId: $monitoredUrl->id,
            data: [
                'minutes' => $minutes,
                'muted_until' => $mutedUntil->toISOString(),
            ],
            request: $request,
        );

        return redirect()
            ->back()
            ->with('status', 'Alerts muted until '.$mutedUntil->toDateTimeString().'.');
    }

    public function unmute(Request $request, MonitoredUrl $monitoredUrl)
    {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 400, 'User has no tenant.');
        abort_if($monitoredUrl->tenant_id !== $tenantId, 404);

        $monitoredUrl->forceFill([
            'muted_until' => null,
        ])->save();

        app(AuditLogService::class)->log(
            tenantId: $tenantId,
            actorUserId: $request->user()?->id,
            action: 'monitored_url.unmuted',
            auditableType: MonitoredUrl::class,
            auditableId: $monitoredUrl->id,
            data: null,
            request: $request,
        );

        return redirect()
            ->back()
            ->with('status', 'Alerts unmuted.');
    }

    /**
     * @return list<string>
     */
    private function normalizeKeywordList(mixed $rawValue): array
    {
        if (! is_string($rawValue)) {
            return [];
        }

        $parts = preg_split('/[,\\n]/', $rawValue) ?: [];
        $keywords = [];

        foreach ($parts as $part) {
            if (! is_string($part)) {
                continue;
            }

            $normalized = trim($part);

            if ($normalized === '') {
                continue;
            }

            $keywords[] = mb_substr($normalized, 0, 120);
        }

        return array_values(array_unique($keywords));
    }
}
