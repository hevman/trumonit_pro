<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Incident;
use App\Models\IncidentComment;
use App\Models\MaintenanceWindow;
use App\Models\MonitoredUrl;
use App\Models\SavedView;
use App\Models\Tag;
use App\Models\User;
use App\Models\UrlSnapshot;
use App\Services\AuditLogService;
use App\Services\TagService;
use App\Services\TextDiffService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class IncidentController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 400, 'User has no tenant.');

        $filters = $request->validate([
            'status' => ['nullable', 'string', Rule::in(['open', 'ack', 'resolved'])],
            'kind' => ['nullable', 'string', 'max:50'],
            'monitored_url_id' => ['nullable', 'integer', 'min:1'],
            'assigned_user_id' => ['nullable', 'integer', 'min:1'],
            'snoozed' => ['nullable', 'string', Rule::in(['1', '0'])],
            'has_comments' => ['nullable', 'string', Rule::in(['1', '0'])],
            'tag' => ['nullable', 'string', 'max:64'],
            'severity_min' => ['nullable', 'integer', 'min:1', 'max:100'],
            'severity_max' => ['nullable', 'integer', 'min:1', 'max:100'],
            'occurrences_min' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'occurrences_max' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'detected_from' => ['nullable', 'date'],
            'detected_to' => ['nullable', 'date'],
            'resolved_from' => ['nullable', 'date'],
            'resolved_to' => ['nullable', 'date'],
            'q' => ['nullable', 'string', 'max:200'],
        ]);

        $status = $filters['status'] ?? null;
        $kind = $filters['kind'] ?? null;
        $monitoredUrlId = is_numeric($filters['monitored_url_id'] ?? null) ? (int) $filters['monitored_url_id'] : null;
        $assignedUserId = is_numeric($filters['assigned_user_id'] ?? null) ? (int) $filters['assigned_user_id'] : null;
        $snoozedFilter = is_string($filters['snoozed'] ?? null) ? (string) $filters['snoozed'] : null;
        $hasCommentsFilter = is_string($filters['has_comments'] ?? null) ? (string) $filters['has_comments'] : null;
        $tagInput = is_string($filters['tag'] ?? null) ? trim((string) $filters['tag']) : null;
        $tag = null;
        $severityMin = is_numeric($filters['severity_min'] ?? null) ? (int) $filters['severity_min'] : null;
        $severityMax = is_numeric($filters['severity_max'] ?? null) ? (int) $filters['severity_max'] : null;
        $occurrencesMin = is_numeric($filters['occurrences_min'] ?? null) ? (int) $filters['occurrences_min'] : null;
        $occurrencesMax = is_numeric($filters['occurrences_max'] ?? null) ? (int) $filters['occurrences_max'] : null;
        $detectedFrom = isset($filters['detected_from']) ? Carbon::parse((string) $filters['detected_from'])->startOfDay() : null;
        $detectedTo = isset($filters['detected_to']) ? Carbon::parse((string) $filters['detected_to'])->endOfDay() : null;
        $resolvedFrom = isset($filters['resolved_from']) ? Carbon::parse((string) $filters['resolved_from'])->startOfDay() : null;
        $resolvedTo = isset($filters['resolved_to']) ? Carbon::parse((string) $filters['resolved_to'])->endOfDay() : null;
        $search = is_string($filters['q'] ?? null) ? trim((string) $filters['q']) : null;
        $searchId = null;
        $now = now();

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

        if (is_int($severityMin) && is_int($severityMax) && $severityMin > $severityMax) {
            [$severityMin, $severityMax] = [$severityMax, $severityMin];
        }

        if (is_int($occurrencesMin) && is_int($occurrencesMax) && $occurrencesMin > $occurrencesMax) {
            [$occurrencesMin, $occurrencesMax] = [$occurrencesMax, $occurrencesMin];
        }

        if ($detectedFrom instanceof Carbon && $detectedTo instanceof Carbon && $detectedFrom->greaterThan($detectedTo)) {
            [$detectedFrom, $detectedTo] = [$detectedTo->copy()->startOfDay(), $detectedFrom->copy()->endOfDay()];
        }

        if ($resolvedFrom instanceof Carbon && $resolvedTo instanceof Carbon && $resolvedFrom->greaterThan($resolvedTo)) {
            [$resolvedFrom, $resolvedTo] = [$resolvedTo->copy()->startOfDay(), $resolvedFrom->copy()->endOfDay()];
        }

        $applySharedFilters = function ($query) use (
            $kind,
            $monitoredUrlId,
            $assignedUserId,
            $snoozedFilter,
            $now,
            $hasCommentsFilter,
            $tag,
            $severityMin,
            $severityMax,
            $occurrencesMin,
            $occurrencesMax,
            $detectedFrom,
            $detectedTo,
            $resolvedFrom,
            $resolvedTo,
            $search,
            $searchId,
        ): void {
            $query
                ->when(is_string($kind) && $kind !== '', fn ($q) => $q->where('kind', $kind))
                ->when($monitoredUrlId !== null, fn ($q) => $q->where('monitored_url_id', $monitoredUrlId))
                ->when($assignedUserId !== null, fn ($q) => $q->where('assigned_user_id', $assignedUserId))
                ->when($snoozedFilter === '1', fn ($q) => $q->whereNotNull('snoozed_until')->where('snoozed_until', '>', $now))
                ->when($snoozedFilter === '0', fn ($q) => $q->where(function ($qq) use ($now): void {
                    $qq->whereNull('snoozed_until')
                        ->orWhere('snoozed_until', '<=', $now);
                }))
                ->when($hasCommentsFilter === '1', fn ($q) => $q->whereHas('comments'))
                ->when($hasCommentsFilter === '0', fn ($q) => $q->whereDoesntHave('comments'))
                ->when(is_string($tag) && $tag !== '', fn ($q) => $q->whereHas('monitoredUrl.tags', fn ($tq) => $tq->where('name', $tag)))
                ->when($severityMin !== null, fn ($q) => $q->where('severity', '>=', $severityMin))
                ->when($severityMax !== null, fn ($q) => $q->where('severity', '<=', $severityMax))
                ->when($occurrencesMin !== null, fn ($q) => $q->where('occurrences', '>=', $occurrencesMin))
                ->when($occurrencesMax !== null, fn ($q) => $q->where('occurrences', '<=', $occurrencesMax))
                ->when($detectedFrom instanceof Carbon, fn ($q) => $q->where('detected_at', '>=', $detectedFrom))
                ->when($detectedTo instanceof Carbon, fn ($q) => $q->where('detected_at', '<=', $detectedTo))
                ->when($resolvedFrom instanceof Carbon, fn ($q) => $q->whereNotNull('resolved_at')->where('resolved_at', '>=', $resolvedFrom))
                ->when($resolvedTo instanceof Carbon, fn ($q) => $q->whereNotNull('resolved_at')->where('resolved_at', '<=', $resolvedTo))
                ->when(is_string($search) && $search !== '', function ($query) use ($search, $searchId): void {
                    $needle = mb_strtolower($search);

                    $query->where(function ($q) use ($needle, $searchId): void {
                        if (is_int($searchId)) {
                            $q->orWhere('id', $searchId);
                        }

                        $q->orWhereRaw('LOWER(summary) LIKE ?', ['%'.$needle.'%'])
                            ->orWhereRaw('LOWER(kind) LIKE ?', ['%'.$needle.'%'])
                            ->orWhereHas('monitoredUrl', function ($mq) use ($needle): void {
                                $mq->whereRaw('LOWER(url) LIKE ?', ['%'.$needle.'%'])
                                    ->orWhereRaw('LOWER(display_name) LIKE ?', ['%'.$needle.'%']);
                            });
                    });
                });
        };

        $incidentsQuery = Incident::query()
            ->with(['monitoredUrl', 'monitoredUrl.tags', 'assignedToUser'])
            ->where('tenant_id', $tenantId);
        $applySharedFilters($incidentsQuery);

        if (is_string($status) && $status !== '') {
            $incidentsQuery->where('status', $status);
        }

        $incidents = $incidentsQuery
            ->orderByDesc('detected_at')
            ->paginate(50)
            ->withQueryString();

        $analyticsBaseQuery = Incident::query()
            ->where('tenant_id', $tenantId);
        $applySharedFilters($analyticsBaseQuery);

        $statusCounts = [
            'open' => (clone $analyticsBaseQuery)->where('status', 'open')->count(),
            'ack' => (clone $analyticsBaseQuery)->where('status', 'ack')->count(),
            'resolved' => (clone $analyticsBaseQuery)->where('status', 'resolved')->count(),
        ];

        $criticalOpenCount = (clone $analyticsBaseQuery)
            ->where('status', 'open')
            ->where('severity', '>=', 80)
            ->count();

        $incidents24hCount = (clone $analyticsBaseQuery)
            ->where('detected_at', '>=', $now->copy()->subDay())
            ->count();

        $averageSeverity = (clone $analyticsBaseQuery)->avg('severity');
        $averageSeverity = $averageSeverity !== null ? round((float) $averageSeverity, 1) : null;

        $resolvedForMttr = (clone $analyticsBaseQuery)
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '>=', $now->copy()->subDays(30))
            ->get(['detected_at', 'resolved_at']);

        $mttrMinutes = null;

        if ($resolvedForMttr->count() > 0) {
            $sumMinutes = 0;
            $count = 0;

            foreach ($resolvedForMttr as $resolvedIncident) {
                if (! $resolvedIncident->detected_at || ! $resolvedIncident->resolved_at) {
                    continue;
                }

                $minutes = $resolvedIncident->detected_at->diffInMinutes($resolvedIncident->resolved_at, false);

                if ($minutes >= 0) {
                    $sumMinutes += $minutes;
                    $count++;
                }
            }

            if ($count > 0) {
                $mttrMinutes = (int) round($sumMinutes / $count);
            }
        }

        $trendEnd = now()->startOfDay();
        $trendStart = $trendEnd->copy()->subDays(13);
        $trendBuckets = [];

        for ($day = $trendStart->copy(); $day->lessThanOrEqualTo($trendEnd); $day->addDay()) {
            $trendBuckets[$day->toDateString()] = [
                'label' => $day->format('d M'),
                'value' => 0,
            ];
        }

        $trendRows = (clone $analyticsBaseQuery)
            ->where('detected_at', '>=', $trendStart)
            ->get(['detected_at']);

        foreach ($trendRows as $trendRow) {
            $key = $trendRow->detected_at?->toDateString();

            if ($key !== null && isset($trendBuckets[$key])) {
                $trendBuckets[$key]['value']++;
            }
        }

        $trendLabels = array_values(array_column($trendBuckets, 'label'));
        $trendDates = array_keys($trendBuckets);
        $trendValues = array_map('intval', array_values(array_column($trendBuckets, 'value')));
        $trendTotal = array_sum($trendValues);

        $resolvedTrendBuckets = [];

        for ($day = $trendStart->copy(); $day->lessThanOrEqualTo($trendEnd); $day->addDay()) {
            $resolvedTrendBuckets[$day->toDateString()] = [
                'label' => $day->format('d M'),
                'resolved_total' => 0,
                'mttr_sum_minutes' => 0,
                'mttr_count' => 0,
            ];
        }

        $resolvedTrendRows = (clone $analyticsBaseQuery)
            ->whereNotNull('resolved_at')
            ->where('resolved_at', '>=', $trendStart)
            ->get(['detected_at', 'resolved_at']);

        foreach ($resolvedTrendRows as $resolvedTrendRow) {
            $resolvedAt = $resolvedTrendRow->resolved_at;

            if (! $resolvedAt instanceof Carbon) {
                continue;
            }

            $dayKey = $resolvedAt->toDateString();

            if (! isset($resolvedTrendBuckets[$dayKey])) {
                continue;
            }

            $resolvedTrendBuckets[$dayKey]['resolved_total']++;

            if ($resolvedTrendRow->detected_at instanceof Carbon) {
                $minutes = $resolvedTrendRow->detected_at->diffInMinutes($resolvedAt, false);

                if ($minutes >= 0) {
                    $resolvedTrendBuckets[$dayKey]['mttr_sum_minutes'] += $minutes;
                    $resolvedTrendBuckets[$dayKey]['mttr_count']++;
                }
            }
        }

        $resolvedTrendLabels = array_values(array_column($resolvedTrendBuckets, 'label'));
        $resolvedTrendDates = array_keys($resolvedTrendBuckets);
        $resolvedTrendValues = array_map('intval', array_values(array_column($resolvedTrendBuckets, 'resolved_total')));
        $resolvedTrendTotal = array_sum($resolvedTrendValues);

        $mttrTrendValues = [];
        $mttrTrendHasData = false;
        $latestMttrMinutes = null;

        foreach ($resolvedTrendBuckets as $resolvedTrendBucket) {
            $bucketCount = (int) ($resolvedTrendBucket['mttr_count'] ?? 0);
            $bucketSum = (int) ($resolvedTrendBucket['mttr_sum_minutes'] ?? 0);
            $bucketValue = $bucketCount > 0
                ? (int) round($bucketSum / $bucketCount)
                : 0;

            $mttrTrendValues[] = $bucketValue;

            if ($bucketCount > 0) {
                $mttrTrendHasData = true;
                $latestMttrMinutes = $bucketValue;
            }
        }

        $kindDistribution = (clone $analyticsBaseQuery)
            ->selectRaw('kind, COUNT(*) as total')
            ->groupBy('kind')
            ->orderByDesc('total')
            ->limit(6)
            ->get()
            ->map(fn ($row): array => [
                'kind' => (string) $row->kind,
                'total' => (int) $row->total,
            ])
            ->values()
            ->all();

        $kindMax = max(1, max(array_column($kindDistribution, 'total') ?: [0]));

        $kinds = Incident::query()
            ->where('tenant_id', $tenantId)
            ->select('kind')
            ->distinct()
            ->orderBy('kind')
            ->limit(200)
            ->pluck('kind');

        $monitoredUrls = MonitoredUrl::query()
            ->where('tenant_id', $tenantId)
            ->orderByRaw("COALESCE(NULLIF(display_name, ''), url) ASC")
            ->limit(500)
            ->get(['id', 'display_name', 'url']);

        $tenantUsers = User::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->orderBy('id')
            ->limit(500)
            ->get(['id', 'name', 'email']);

        $tags = Tag::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->limit(500)
            ->pluck('name');

        $savedViews = SavedView::query()
            ->where('tenant_id', $tenantId)
            ->where('scope', 'incidents')
            ->orderBy('name')
            ->get(['id', 'scope', 'name', 'filters']);

        return view('app.incidents.index', [
            'incidents' => $incidents,
            'filters' => [
                'status' => $status,
                'kind' => $kind,
                'monitored_url_id' => $monitoredUrlId,
                'assigned_user_id' => $assignedUserId,
                'snoozed' => $snoozedFilter,
                'has_comments' => $hasCommentsFilter,
                'tag' => $tag,
                'severity_min' => $severityMin,
                'severity_max' => $severityMax,
                'occurrences_min' => $occurrencesMin,
                'occurrences_max' => $occurrencesMax,
                'detected_from' => $detectedFrom?->toDateString(),
                'detected_to' => $detectedTo?->toDateString(),
                'resolved_from' => $resolvedFrom?->toDateString(),
                'resolved_to' => $resolvedTo?->toDateString(),
                'q' => $search,
            ],
            'kinds' => $kinds,
            'tags' => $tags,
            'monitoredUrls' => $monitoredUrls,
            'tenantUsers' => $tenantUsers,
            'savedViews' => $savedViews,
            'analytics' => [
                'status_counts' => $statusCounts,
                'critical_open_count' => $criticalOpenCount,
                'incidents_24h_count' => $incidents24hCount,
                'average_severity' => $averageSeverity,
                'mttr_minutes_30d' => $mttrMinutes,
                'trend_labels_14d' => $trendLabels,
                'trend_dates_14d' => $trendDates,
                'trend_values_14d' => $trendValues,
                'trend_total_14d' => $trendTotal,
                'resolved_trend_labels_14d' => $resolvedTrendLabels,
                'resolved_trend_dates_14d' => $resolvedTrendDates,
                'resolved_trend_values_14d' => $resolvedTrendValues,
                'resolved_trend_total_14d' => $resolvedTrendTotal,
                'mttr_trend_values_14d' => $mttrTrendValues,
                'mttr_trend_has_data_14d' => $mttrTrendHasData,
                'mttr_latest_minutes_14d' => $latestMttrMinutes,
                'kind_distribution' => $kindDistribution,
                'kind_distribution_max' => $kindMax,
            ],
        ]);
    }

    public function show(Request $request, Incident $incident)
    {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 400, 'User has no tenant.');
        abort_if($incident->tenant_id !== $tenantId, 404);

        $incident->loadMissing(['monitoredUrl.tags', 'previousSnapshot', 'currentSnapshot', 'assignedToUser']);

        [$previousBodyPreview, $previousBodyTruncated] = $this->getSnapshotBodyPreview($incident->previousSnapshot);
        [$currentBodyPreview, $currentBodyTruncated] = $this->getSnapshotBodyPreview($incident->currentSnapshot);

        $diff = null;

        if (is_string($previousBodyPreview) && is_string($currentBodyPreview)) {
            $diff = app(TextDiffService::class)->diff($previousBodyPreview, $currentBodyPreview);
        }

        $changeSummary = $this->buildChangeSummary($incident->previousSnapshot, $incident->currentSnapshot);

        $maintenanceActive = false;

        if ((bool) config('wids.notifications.respect_maintenance', true) && Schema::hasTable('maintenance_windows')) {
            $now = now();
            $tagNames = $incident->monitoredUrl?->tags?->pluck('name')->values()->all() ?? [];

            $maintenanceActive = MaintenanceWindow::query()
                ->where('tenant_id', $tenantId)
                ->where('starts_at', '<=', $now)
                ->where('ends_at', '>=', $now)
                ->where(function ($q) use ($incident, $tagNames): void {
                    $q->where(function ($qq): void {
                        $qq->whereNull('monitored_url_id')
                            ->whereNull('tag');
                    })->orWhere('monitored_url_id', $incident->monitored_url_id);

                    if ($tagNames !== []) {
                        $q->orWhereIn('tag', $tagNames);
                    }
                })
                ->exists();
        }

        $incidentComments = IncidentComment::query()
            ->with('author')
            ->where('tenant_id', $tenantId)
            ->where('incident_id', $incident->id)
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $historyEntries = AuditLog::query()
            ->with('actor')
            ->where('tenant_id', $tenantId)
            ->where('auditable_type', Incident::class)
            ->where('auditable_id', $incident->id)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        $tenantUsers = User::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->orderBy('id')
            ->limit(500)
            ->get(['id', 'name', 'email', 'role']);

        return view('app.incidents.show', [
            'incident' => $incident,
            'previousBodyPreview' => $previousBodyPreview,
            'previousBodyTruncated' => $previousBodyTruncated,
            'currentBodyPreview' => $currentBodyPreview,
            'currentBodyTruncated' => $currentBodyTruncated,
            'diff' => $diff,
            'changeSummary' => $changeSummary,
            'maintenanceActive' => $maintenanceActive,
            'incidentComments' => $incidentComments,
            'historyEntries' => $historyEntries,
            'tenantUsers' => $tenantUsers,
        ]);
    }

    public function update(Request $request, Incident $incident)
    {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 400, 'User has no tenant.');
        abort_if($incident->tenant_id !== $tenantId, 404);

        $data = $request->validate([
            'status' => ['nullable', 'string', Rule::in(['open', 'ack', 'resolved'])],
            'assigned_user_id' => ['nullable', 'integer', 'min:1'],
            'clear_assignment' => ['nullable', 'boolean'],
            'snooze_minutes' => ['nullable', 'integer', 'min:0', 'max:10080'],
            'snoozed_until' => ['nullable', 'date', 'after:now'],
            'clear_snooze' => ['nullable', 'boolean'],
        ]);

        $statusProvided = array_key_exists('status', $data);
        $assignmentProvided = array_key_exists('assigned_user_id', $data) || ((bool) ($data['clear_assignment'] ?? false));
        $snoozeProvided = array_key_exists('snooze_minutes', $data)
            || array_key_exists('snoozed_until', $data)
            || ((bool) ($data['clear_snooze'] ?? false));

        if (! $statusProvided && ! $assignmentProvided && ! $snoozeProvided) {
            throw ValidationException::withMessages([
                'status' => ['Provide at least one update field.'],
            ]);
        }

        $beforeStatus = (string) $incident->status;
        $beforeAssignedUserId = $incident->assigned_user_id !== null ? (int) $incident->assigned_user_id : null;
        $beforeSnoozedUntilIso = $incident->snoozed_until?->toISOString();

        $updates = [];

        if ($statusProvided) {
            $newStatus = (string) $data['status'];
            $resolvedAt = $incident->resolved_at;

            if ($newStatus === 'resolved') {
                $resolvedAt ??= now();
            } else {
                $resolvedAt = null;
            }

            $updates['status'] = $newStatus;
            $updates['resolved_at'] = $resolvedAt;
        }

        if ($assignmentProvided) {
            $assignedUserId = null;

            if (! ((bool) ($data['clear_assignment'] ?? false)) && array_key_exists('assigned_user_id', $data) && $data['assigned_user_id'] !== null) {
                $candidateId = (int) $data['assigned_user_id'];

                $exists = User::query()
                    ->where('tenant_id', $tenantId)
                    ->where('id', $candidateId)
                    ->exists();

                if (! $exists) {
                    throw ValidationException::withMessages([
                        'assigned_user_id' => ['User not found in this workspace.'],
                    ]);
                }

                $assignedUserId = $candidateId;
            }

            $updates['assigned_user_id'] = $assignedUserId;
        }

        if ($snoozeProvided) {
            $snoozedUntil = null;

            if (! ((bool) ($data['clear_snooze'] ?? false))) {
                if (array_key_exists('snoozed_until', $data) && $data['snoozed_until'] !== null) {
                    $snoozedUntil = Carbon::parse((string) $data['snoozed_until']);
                } elseif (array_key_exists('snooze_minutes', $data)) {
                    $minutes = max(0, (int) ($data['snooze_minutes'] ?? 0));
                    $snoozedUntil = $minutes > 0 ? now()->addMinutes($minutes) : null;
                }
            }

            $updates['snoozed_until'] = $snoozedUntil;
        }

        if ($updates !== []) {
            $incident->forceFill($updates)->save();
        }

        if ($statusProvided && $beforeStatus !== $incident->status) {
            app(AuditLogService::class)->log(
                tenantId: $tenantId,
                actorUserId: $request->user()?->id,
                action: 'incident.status_changed',
                auditableType: Incident::class,
                auditableId: $incident->id,
                data: [
                    'before' => $beforeStatus,
                    'after' => $incident->status,
                ],
                request: $request,
            );
        }

        if ($assignmentProvided && $beforeAssignedUserId !== $incident->assigned_user_id) {
            app(AuditLogService::class)->log(
                tenantId: $tenantId,
                actorUserId: $request->user()?->id,
                action: 'incident.assignment_changed',
                auditableType: Incident::class,
                auditableId: $incident->id,
                data: [
                    'before' => $beforeAssignedUserId,
                    'after' => $incident->assigned_user_id,
                ],
                request: $request,
            );
        }

        $afterSnoozedUntilIso = $incident->snoozed_until?->toISOString();

        if ($snoozeProvided && $beforeSnoozedUntilIso !== $afterSnoozedUntilIso) {
            app(AuditLogService::class)->log(
                tenantId: $tenantId,
                actorUserId: $request->user()?->id,
                action: 'incident.snooze_changed',
                auditableType: Incident::class,
                auditableId: $incident->id,
                data: [
                    'before' => $beforeSnoozedUntilIso,
                    'after' => $afterSnoozedUntilIso,
                ],
                request: $request,
            );
        }

        return redirect()
            ->back()
            ->with('status', 'Incident updated.');
    }

    public function storeComment(Request $request, Incident $incident)
    {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 400, 'User has no tenant.');
        abort_if($incident->tenant_id !== $tenantId, 404);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:4000'],
        ]);

        $body = trim((string) $data['body']);

        if ($body === '') {
            throw ValidationException::withMessages([
                'body' => ['Comment cannot be empty.'],
            ]);
        }

        $comment = IncidentComment::query()->create([
            'tenant_id' => $tenantId,
            'incident_id' => $incident->id,
            'author_user_id' => $request->user()?->id,
            'body' => $body,
        ]);

        app(AuditLogService::class)->log(
            tenantId: $tenantId,
            actorUserId: $request->user()?->id,
            action: 'incident.comment_added',
            auditableType: Incident::class,
            auditableId: $incident->id,
            data: [
                'comment_id' => $comment->id,
                'body_preview' => mb_substr($comment->body, 0, 200),
            ],
            request: $request,
        );

        return redirect()
            ->back()
            ->with('status', 'Comment added.');
    }

    /**
     * @return array{0: ?string, 1: bool}
     */
    private function getSnapshotBodyPreview(?UrlSnapshot $snapshot): array
    {
        if (! $snapshot || ! $snapshot->body_storage_path) {
            return [null, false];
        }

        if (($snapshot->body_size ?? 0) > 200_000 || ! Storage::exists($snapshot->body_storage_path)) {
            return [null, false];
        }

        $body = Storage::get($snapshot->body_storage_path);

        $preview = mb_substr($body, 0, 8000);
        $truncated = mb_strlen($body) > 8000;

        return [$preview, $truncated];
    }

    /**
     * @return list<array{label: string, before: string, after: string}>
     */
    private function buildChangeSummary(?UrlSnapshot $previous, ?UrlSnapshot $current): array
    {
        if (! $previous || ! $current) {
            return [];
        }

        $changes = [];

        $this->addChangeRow($changes, 'HTTP status', $previous->http_status, $current->http_status);
        $this->addChangeRow($changes, 'Final URL', $previous->final_url, $current->final_url);
        $this->addChangeRow($changes, 'Response time (ms)', $previous->response_time_ms, $current->response_time_ms);
        $this->addChangeRow($changes, 'Body size', $previous->body_size, $current->body_size);
        $this->addChangeRow($changes, 'Body sha256', $previous->body_sha256, $current->body_sha256);

        if ($previous->screenshot_phash !== null && $current->screenshot_phash !== null) {
            $distance = $this->hammingDistanceHex($previous->screenshot_phash, $current->screenshot_phash);

            if ($distance !== null && $distance > 0) {
                $changes[] = [
                    'label' => 'Screenshot pHash distance',
                    'before' => (string) $previous->screenshot_phash,
                    'after' => (string) $current->screenshot_phash.' (distance='.$distance.')',
                ];
            }
        }

        $previousDns = is_array($previous->meta['dns'] ?? null) ? $previous->meta['dns'] : null;
        $currentDns = is_array($current->meta['dns'] ?? null) ? $current->meta['dns'] : null;

        if (is_array($previousDns) && is_array($currentDns)) {
            $dnsSummary = $this->summarizeDnsChanges($previousDns, $currentDns);

            if ($dnsSummary !== null) {
                $changes[] = [
                    'label' => 'DNS',
                    'before' => 'see details',
                    'after' => $dnsSummary,
                ];
            }
        }

        $previousTls = is_array($previous->meta['tls'] ?? null) ? $previous->meta['tls'] : null;
        $currentTls = is_array($current->meta['tls'] ?? null) ? $current->meta['tls'] : null;

        if (is_array($previousTls) && is_array($currentTls)) {
            $beforeFp = is_string($previousTls['fingerprint_sha256'] ?? null) ? (string) $previousTls['fingerprint_sha256'] : null;
            $afterFp = is_string($currentTls['fingerprint_sha256'] ?? null) ? (string) $currentTls['fingerprint_sha256'] : null;

            if ($beforeFp !== null && $afterFp !== null && $beforeFp !== $afterFp) {
                $changes[] = [
                    'label' => 'TLS fingerprint',
                    'before' => $beforeFp,
                    'after' => $afterFp,
                ];
            }
        }

        return $changes;
    }

    /**
     * @param  list<array{label: string, before: string, after: string}>  $changes
     * @param  mixed  $before
     * @param  mixed  $after
     */
    private function addChangeRow(array &$changes, string $label, mixed $before, mixed $after): void
    {
        $beforeString = $before === null ? '-' : (string) $before;
        $afterString = $after === null ? '-' : (string) $after;

        if ($beforeString === $afterString) {
            return;
        }

        $changes[] = [
            'label' => $label,
            'before' => $beforeString,
            'after' => $afterString,
        ];
    }

    private function hammingDistanceHex(string $hexA, string $hexB): ?int
    {
        $hexA = strtolower(trim($hexA));
        $hexB = strtolower(trim($hexB));

        if ($hexA === '' || $hexB === '' || strlen($hexA) !== strlen($hexB)) {
            return null;
        }

        $a = @hex2bin($hexA);
        $b = @hex2bin($hexB);

        if (! is_string($a) || ! is_string($b) || strlen($a) !== strlen($b)) {
            return null;
        }

        $xor = $a ^ $b;
        $len = strlen($xor);

        static $bitCounts = null;

        if ($bitCounts === null) {
            $bitCounts = [];
            for ($i = 0; $i < 256; $i++) {
                $bitCounts[$i] = substr_count(decbin($i), '1');
            }
        }

        $distance = 0;

        for ($i = 0; $i < $len; $i++) {
            $distance += $bitCounts[ord($xor[$i])];
        }

        return $distance;
    }

    private function summarizeDnsChanges(array $previousDns, array $currentDns): ?string
    {
        $types = ['a' => 'A', 'aaaa' => 'AAAA', 'cname' => 'CNAME'];
        $parts = [];

        foreach ($types as $key => $label) {
            $before = is_array($previousDns[$key] ?? null) ? array_values($previousDns[$key]) : [];
            $after = is_array($currentDns[$key] ?? null) ? array_values($currentDns[$key]) : [];

            $before = array_values(array_unique(array_filter(array_map('strval', $before))));
            $after = array_values(array_unique(array_filter(array_map('strval', $after))));

            sort($before);
            sort($after);

            if ($before === $after) {
                continue;
            }

            $added = array_values(array_diff($after, $before));
            $removed = array_values(array_diff($before, $after));

            $chunk = $label.':';

            if ($added !== []) {
                $chunk .= ' +'.implode(',', array_slice($added, 0, 5));
            }

            if ($removed !== []) {
                $chunk .= ' -'.implode(',', array_slice($removed, 0, 5));
            }

            $parts[] = $chunk;
        }

        if ($parts === []) {
            return null;
        }

        return implode(' | ', $parts);
    }
}
