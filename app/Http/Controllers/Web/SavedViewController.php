<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\SavedView;
use App\Services\AuditLogService;
use App\Services\TagService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SavedViewController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 400, 'User has no tenant.');

        $scope = $request->validate([
            'scope' => ['nullable', 'string', Rule::in(['incidents', 'monitored_urls'])],
        ])['scope'] ?? null;

        $savedViews = SavedView::query()
            ->where('tenant_id', $tenantId)
            ->when(is_string($scope) && $scope !== '', fn ($q) => $q->where('scope', $scope))
            ->with('createdByUser:id,name,email')
            ->orderBy('scope')
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        return view('app.saved-views.index', [
            'savedViews' => $savedViews,
            'scope' => $scope,
        ]);
    }

    public function store(Request $request)
    {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 400, 'User has no tenant.');

        $data = $request->validate([
            'scope' => ['required', 'string', Rule::in(['incidents', 'monitored_urls'])],
            'name' => ['required', 'string', 'max:120'],
            'filters' => ['required', 'array'],
        ]);

        $scope = (string) $data['scope'];
        $name = trim((string) $data['name']);
        $filters = $this->sanitizeFilters($scope, $data['filters'] ?? []);

        if ($filters === []) {
            throw ValidationException::withMessages([
                'filters' => ['Select at least one filter before saving a view.'],
            ]);
        }

        $savedView = SavedView::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'scope' => $scope,
                'name' => $name,
            ],
            [
                'created_by_user_id' => $request->user()?->id,
                'filters' => $filters,
            ],
        );

        app(AuditLogService::class)->log(
            tenantId: $tenantId,
            actorUserId: $request->user()?->id,
            action: 'saved_view.upserted',
            auditableType: SavedView::class,
            auditableId: $savedView->id,
            data: [
                'scope' => $scope,
                'name' => $name,
                'filters' => $filters,
            ],
            request: $request,
        );

        return redirect()
            ->to($this->scopeRoute($scope, $filters))
            ->with('status', 'Saved view updated.');
    }

    public function destroy(Request $request, SavedView $savedView)
    {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 400, 'User has no tenant.');
        abort_if($savedView->tenant_id !== $tenantId, 404);

        $scope = $savedView->scope;
        $name = $savedView->name;

        $savedView->delete();

        app(AuditLogService::class)->log(
            tenantId: $tenantId,
            actorUserId: $request->user()?->id,
            action: 'saved_view.deleted',
            auditableType: SavedView::class,
            auditableId: $savedView->id,
            data: [
                'scope' => $scope,
                'name' => $name,
            ],
            request: $request,
        );

        return redirect()
            ->to($this->scopeRoute($scope, []))
            ->with('status', 'Saved view deleted.');
    }

    public function update(Request $request, SavedView $savedView)
    {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 400, 'User has no tenant.');
        abort_if($savedView->tenant_id !== $tenantId, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'filters' => ['nullable', 'array'],
        ]);

        $name = trim((string) $data['name']);

        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => ['Name is required.'],
            ]);
        }

        $exists = SavedView::query()
            ->where('tenant_id', $tenantId)
            ->where('scope', $savedView->scope)
            ->where('name', $name)
            ->where('id', '!=', $savedView->id)
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => ['A saved search with this name already exists in this scope.'],
            ]);
        }

        $before = [
            'name' => $savedView->name,
            'filters' => $savedView->filters,
        ];

        $updates = [
            'name' => $name,
        ];

        if (array_key_exists('filters', $data) && is_array($data['filters'])) {
            $filters = $this->sanitizeFilters($savedView->scope, $data['filters']);

            if ($filters === []) {
                throw ValidationException::withMessages([
                    'filters' => ['Select at least one filter before saving a view.'],
                ]);
            }

            $updates['filters'] = $filters;
        }

        $savedView->forceFill($updates)->save();

        app(AuditLogService::class)->log(
            tenantId: $tenantId,
            actorUserId: $request->user()?->id,
            action: 'saved_view.updated',
            auditableType: SavedView::class,
            auditableId: $savedView->id,
            data: [
                'scope' => $savedView->scope,
                'before' => $before,
                'after' => [
                    'name' => $savedView->name,
                    'filters' => $savedView->filters,
                ],
            ],
            request: $request,
        );

        return redirect()
            ->back()
            ->with('status', 'Saved view updated.');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, int|string>
     */
    private function sanitizeFilters(string $scope, array $filters): array
    {
        $result = [];

        if ($scope === 'monitored_urls') {
            $q = trim((string) ($filters['q'] ?? ''));
            if ($q !== '') {
                $result['q'] = mb_substr($q, 0, 200);
            }

            $tagInput = trim((string) ($filters['tag'] ?? ''));
            if ($tagInput !== '') {
                $tag = app(TagService::class)->normalize($tagInput);
                if ($tag === null) {
                    throw ValidationException::withMessages([
                        'filters' => ['Invalid tag filter for saved view.'],
                    ]);
                }
                $result['tag'] = $tag;
            }

            $scanMode = trim((string) ($filters['scan_mode'] ?? ''));
            if (in_array($scanMode, ['http', 'journey', 'port', 'heartbeat'], true)) {
                $result['scan_mode'] = $scanMode;
            }

            $isActive = trim((string) ($filters['is_active'] ?? ''));
            if (in_array($isActive, ['1', '0'], true)) {
                $result['is_active'] = $isActive;
            }

            $muted = trim((string) ($filters['muted'] ?? ''));
            if (in_array($muted, ['1', '0'], true)) {
                $result['muted'] = $muted;
            }

            $state = trim((string) ($filters['state'] ?? ''));
            if (in_array($state, ['up', 'down', 'paused', 'muted'], true)) {
                $result['state'] = $state;
            }

            $frequencyMin = $filters['frequency_min'] ?? null;
            $frequencyMax = $filters['frequency_max'] ?? null;

            if (is_numeric($frequencyMin)) {
                $result['frequency_min'] = min(43200, max(1, (int) $frequencyMin));
            }

            if (is_numeric($frequencyMax)) {
                $result['frequency_max'] = min(43200, max(1, (int) $frequencyMax));
            }

            if (isset($result['frequency_min'], $result['frequency_max']) && $result['frequency_min'] > $result['frequency_max']) {
                [$result['frequency_min'], $result['frequency_max']] = [$result['frequency_max'], $result['frequency_min']];
            }

            $timeoutMin = $filters['timeout_min'] ?? null;
            $timeoutMax = $filters['timeout_max'] ?? null;

            if (is_numeric($timeoutMin)) {
                $result['timeout_min'] = min(120, max(1, (int) $timeoutMin));
            }

            if (is_numeric($timeoutMax)) {
                $result['timeout_max'] = min(120, max(1, (int) $timeoutMax));
            }

            if (isset($result['timeout_min'], $result['timeout_max']) && $result['timeout_min'] > $result['timeout_max']) {
                [$result['timeout_min'], $result['timeout_max']] = [$result['timeout_max'], $result['timeout_min']];
            }

            return $result;
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if (in_array($status, ['open', 'ack', 'resolved'], true)) {
            $result['status'] = $status;
        }

        $kind = trim((string) ($filters['kind'] ?? ''));
        if ($kind !== '') {
            $result['kind'] = mb_substr($kind, 0, 50);
        }

        $monitorId = $filters['monitored_url_id'] ?? null;
        if (is_numeric($monitorId) && (int) $monitorId > 0) {
            $result['monitored_url_id'] = (int) $monitorId;
        }

        $assignedUserId = $filters['assigned_user_id'] ?? null;
        if (is_numeric($assignedUserId) && (int) $assignedUserId > 0) {
            $result['assigned_user_id'] = (int) $assignedUserId;
        }

        $snoozed = trim((string) ($filters['snoozed'] ?? ''));
        if (in_array($snoozed, ['1', '0'], true)) {
            $result['snoozed'] = $snoozed;
        }

        $hasComments = trim((string) ($filters['has_comments'] ?? ''));
        if (in_array($hasComments, ['1', '0'], true)) {
            $result['has_comments'] = $hasComments;
        }

        $tagInput = trim((string) ($filters['tag'] ?? ''));
        if ($tagInput !== '') {
            $tag = app(TagService::class)->normalize($tagInput);
            if ($tag === null) {
                throw ValidationException::withMessages([
                    'filters' => ['Invalid tag filter for saved view.'],
                ]);
            }
            $result['tag'] = $tag;
        }

        $severityMin = $filters['severity_min'] ?? null;
        $severityMax = $filters['severity_max'] ?? null;

        if (is_numeric($severityMin)) {
            $severityMin = min(100, max(1, (int) $severityMin));
            $result['severity_min'] = $severityMin;
        } else {
            $severityMin = null;
        }

        if (is_numeric($severityMax)) {
            $severityMax = min(100, max(1, (int) $severityMax));
            $result['severity_max'] = $severityMax;
        } else {
            $severityMax = null;
        }

        if ($severityMin !== null && $severityMax !== null && $severityMin > $severityMax) {
            $result['severity_min'] = $severityMax;
            $result['severity_max'] = $severityMin;
        }

        $occurrencesMin = $filters['occurrences_min'] ?? null;
        $occurrencesMax = $filters['occurrences_max'] ?? null;

        if (is_numeric($occurrencesMin)) {
            $result['occurrences_min'] = min(1_000_000, max(1, (int) $occurrencesMin));
        }

        if (is_numeric($occurrencesMax)) {
            $result['occurrences_max'] = min(1_000_000, max(1, (int) $occurrencesMax));
        }

        if (isset($result['occurrences_min'], $result['occurrences_max']) && $result['occurrences_min'] > $result['occurrences_max']) {
            [$result['occurrences_min'], $result['occurrences_max']] = [$result['occurrences_max'], $result['occurrences_min']];
        }

        $detectedFrom = trim((string) ($filters['detected_from'] ?? ''));
        if ($detectedFrom !== '' && strtotime($detectedFrom) !== false) {
            $result['detected_from'] = date('Y-m-d', strtotime($detectedFrom));
        }

        $detectedTo = trim((string) ($filters['detected_to'] ?? ''));
        if ($detectedTo !== '' && strtotime($detectedTo) !== false) {
            $result['detected_to'] = date('Y-m-d', strtotime($detectedTo));
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $result['q'] = mb_substr($q, 0, 200);
        }

        return $result;
    }

    /**
     * @param  array<string, int|string>  $filters
     */
    private function scopeRoute(string $scope, array $filters): string
    {
        if ($scope === 'monitored_urls') {
            return route('monitored-urls.index', $filters);
        }

        return route('incidents.index', $filters);
    }
}
