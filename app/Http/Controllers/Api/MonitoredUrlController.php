<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ScanMonitoredUrlJob;
use App\Models\MonitoredUrl;
use App\Models\UrlSnapshot;
use App\Rules\PublicUrl;
use App\Services\AuditLogService;
use App\Services\ExpectedStatusRangeService;
use App\Services\ScanQueueRoutingService;
use App\Services\TagService;
use App\Services\TenantQuotaService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MonitoredUrlController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = $request->user()?->tenant_id;

        if ($tenantId === null) {
            return response()->json(['message' => 'User has no tenant.'], 400);
        }

        $pagination = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $perPage = (int) ($pagination['per_page'] ?? 50);

        return MonitoredUrl::query()
            ->where('tenant_id', $tenantId)
            ->with('tags')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function store(Request $request)
    {
        $tenantId = $request->user()?->tenant_id;

        if ($tenantId === null) {
            return response()->json(['message' => 'User has no tenant.'], 400);
        }

        $limits = app(TenantQuotaService::class)->limitsForTenant($request->user()?->tenant);
        $limit = (int) ($limits['max_monitored_urls'] ?? 0);
        $minFrequencyMinutes = max(1, (int) ($limits['min_frequency_minutes'] ?? 1));

        if ($limit > 0) {
            $count = MonitoredUrl::query()->where('tenant_id', $tenantId)->count();

            if ($count >= $limit) {
                throw ValidationException::withMessages([
                    'url' => [app(TenantQuotaService::class)->monitorLimitMessage($limits)],
                ]);
            }
        }

        $data = $request->validate([
            'url' => ['required', 'string', 'max:4096', 'url', new PublicUrl],
            'display_name' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:32'],
            'frequency_minutes' => ['nullable', 'integer', 'min:'.$minFrequencyMinutes, 'max:43200'],
            'timeout_seconds' => ['nullable', 'integer', 'min:1', 'max:120'],
            'follow_redirects' => ['nullable', 'boolean'],
            'expected_status_min' => ['nullable', 'integer', 'min:100', 'max:599'],
            'expected_status_max' => ['nullable', 'integer', 'min:100', 'max:599', 'gte:expected_status_min'],
            'is_active' => ['nullable', 'boolean'],
            'scan_mode' => ['nullable', 'string', Rule::in(['http', 'journey', 'port', 'heartbeat'])],
            'appearance_min_distance' => ['nullable', 'integer', 'min:0', 'max:64'],
            'alert_after_failures' => ['nullable', 'integer', 'min:1', 'max:10'],
            'alert_cooldown_minutes' => ['nullable', 'integer', 'min:0', 'max:10080'],
            'journey_steps' => ['nullable', 'array', 'max:30'],
            'content_rules' => ['nullable', 'array'],
            'keyword_must_include' => ['nullable', 'string', 'max:255'],
            'port_number' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'heartbeat_grace_minutes' => ['nullable', 'integer', 'min:0', 'max:10080'],
        ]);

        $scanMode = $data['scan_mode'] ?? 'http';
        $journeySteps = $scanMode === 'journey' ? ($data['journey_steps'] ?? null) : null;
        $contentRules = is_array($data['content_rules'] ?? null) ? $data['content_rules'] : [];

        $keywordList = $this->normalizeKeywordList($data['keyword_must_include'] ?? null);
        if ($keywordList !== []) {
            $existingAllow = is_array($contentRules['allow_keywords'] ?? null) ? $contentRules['allow_keywords'] : [];
            $contentRules['enabled'] = $contentRules['enabled'] ?? true;
            $contentRules['allow_keywords'] = array_values(array_unique(array_merge($existingAllow, $keywordList)));
        }

        if ($scanMode === 'port' && is_numeric($data['port_number'] ?? null)) {
            $portRules = is_array($contentRules['port'] ?? null) ? $contentRules['port'] : [];
            $portRules['port'] = (int) $data['port_number'];
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
            $tagNames = app(TagService::class)->normalizeManyFromArray($data['tags'] ?? null);
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'tags' => [$e->getMessage()],
            ]);
        }

        $defaultAlertAfterFailures = min(10, max(1, (int) config('wids.alerts.default_after_failures', 2)));
        $defaultAlertCooldownMinutes = min(10080, max(0, (int) config('wids.alerts.default_cooldown_minutes', 10)));

        $monitoredUrl = MonitoredUrl::query()->create([
            'tenant_id' => $tenantId,
            'created_by_user_id' => $request->user()?->id,
            'url' => $data['url'],
            'display_name' => $data['display_name'] ?? null,
            'frequency_minutes' => $data['frequency_minutes'] ?? 1440,
            'timeout_seconds' => $data['timeout_seconds'] ?? ($scanMode === 'port' ? 10 : ($scanMode === 'heartbeat' ? 5 : 30)),
            'follow_redirects' => $scanMode === 'http' || $scanMode === 'journey'
                ? ($data['follow_redirects'] ?? true)
                : false,
            'expected_status_min' => $expectedStatusMin,
            'expected_status_max' => $expectedStatusMax,
            'is_active' => $data['is_active'] ?? true,
            'next_scan_at' => ($data['is_active'] ?? true) ? now() : null,
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
            ],
            request: $request,
        );

        return response()->json($monitoredUrl->load('tags'), 201);
    }

    public function show(Request $request, int $id)
    {
        $tenantId = $request->user()?->tenant_id;

        if ($tenantId === null) {
            return response()->json(['message' => 'User has no tenant.'], 400);
        }

        $monitoredUrl = MonitoredUrl::query()
            ->where('tenant_id', $tenantId)
            ->with('tags')
            ->findOrFail($id);

        return response()->json($monitoredUrl);
    }

    public function update(Request $request, int $id)
    {
        $tenantId = $request->user()?->tenant_id;

        if ($tenantId === null) {
            return response()->json(['message' => 'User has no tenant.'], 400);
        }

        $limits = app(TenantQuotaService::class)->limitsForTenant($request->user()?->tenant);
        $minFrequencyMinutes = max(1, (int) ($limits['min_frequency_minutes'] ?? 1));

        $data = $request->validate([
            'display_name' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'array', 'max:20'],
            'tags.*' => ['string', 'max:32'],
            'frequency_minutes' => ['nullable', 'integer', 'min:'.$minFrequencyMinutes, 'max:43200'],
            'timeout_seconds' => ['nullable', 'integer', 'min:1', 'max:120'],
            'follow_redirects' => ['nullable', 'boolean'],
            'expected_status_min' => ['nullable', 'integer', 'min:100', 'max:599'],
            'expected_status_max' => ['nullable', 'integer', 'min:100', 'max:599', 'gte:expected_status_min'],
            'is_active' => ['nullable', 'boolean'],
            'scan_mode' => ['nullable', 'string', Rule::in(['http', 'journey', 'port', 'heartbeat'])],
            'appearance_min_distance' => ['nullable', 'integer', 'min:0', 'max:64'],
            'alert_after_failures' => ['nullable', 'integer', 'min:1', 'max:10'],
            'alert_cooldown_minutes' => ['nullable', 'integer', 'min:0', 'max:10080'],
            'journey_steps' => ['nullable', 'array', 'max:30'],
            'content_rules' => ['nullable', 'array'],
            'keyword_must_include' => ['nullable', 'string', 'max:255'],
            'port_number' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'heartbeat_grace_minutes' => ['nullable', 'integer', 'min:0', 'max:10080'],
        ]);

        $monitoredUrl = MonitoredUrl::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);

        $beforeTags = $monitoredUrl->tags()->pluck('name')->sort()->values()->all();

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
        $before['tags'] = $beforeTags;

        $scanMode = $data['scan_mode'] ?? $monitoredUrl->scan_mode ?? 'http';
        $contentRules = is_array($data['content_rules'] ?? null) ? $data['content_rules'] : $monitoredUrl->content_rules;
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
            $heartbeatRules['grace_minutes'] = array_key_exists('heartbeat_grace_minutes', $data) && is_numeric($data['heartbeat_grace_minutes'])
                ? min(10080, max(0, (int) $data['heartbeat_grace_minutes']))
                : min(10080, max(0, (int) ($heartbeatRules['grace_minutes'] ?? 3)));
            $contentRules['heartbeat'] = $heartbeatRules;
        }

        if ($contentRules === []) {
            $contentRules = null;
        }

        $tagNames = $beforeTags;

        if (array_key_exists('tags', $data)) {
            try {
                $tagNames = app(TagService::class)->normalizeManyFromArray($data['tags'] ?? null);
            } catch (\InvalidArgumentException $e) {
                throw ValidationException::withMessages([
                    'tags' => [$e->getMessage()],
                ]);
            }
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
            'display_name' => array_key_exists('display_name', $data) ? $data['display_name'] : $monitoredUrl->display_name,
            'frequency_minutes' => array_key_exists('frequency_minutes', $data) ? $data['frequency_minutes'] : $monitoredUrl->frequency_minutes,
            'timeout_seconds' => array_key_exists('timeout_seconds', $data)
                ? $data['timeout_seconds']
                : ($scanMode === 'port' ? 10 : ($scanMode === 'heartbeat' ? 5 : $monitoredUrl->timeout_seconds)),
            'follow_redirects' => $scanMode === 'http' || $scanMode === 'journey'
                ? (array_key_exists('follow_redirects', $data) ? $data['follow_redirects'] : $monitoredUrl->follow_redirects)
                : false,
            'expected_status_min' => $expectedStatusMin,
            'expected_status_max' => $expectedStatusMax,
            'is_active' => array_key_exists('is_active', $data) ? $data['is_active'] : $monitoredUrl->is_active,
            'next_scan_at' => $nextScanAt,
            'scan_mode' => $scanMode,
            'appearance_min_distance' => $scanMode === 'http' || $scanMode === 'journey'
                ? (array_key_exists('appearance_min_distance', $data) ? $data['appearance_min_distance'] : $monitoredUrl->appearance_min_distance)
                : null,
            'alert_after_failures' => array_key_exists('alert_after_failures', $data) ? $data['alert_after_failures'] : $monitoredUrl->alert_after_failures,
            'alert_cooldown_minutes' => array_key_exists('alert_cooldown_minutes', $data) ? $data['alert_cooldown_minutes'] : $monitoredUrl->alert_cooldown_minutes,
            'journey_steps' => $scanMode === 'journey' ? ($data['journey_steps'] ?? $monitoredUrl->journey_steps) : null,
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

        return response()->json($monitoredUrl->load('tags'));
    }

    public function destroy(Request $request, int $id)
    {
        $tenantId = $request->user()?->tenant_id;

        if ($tenantId === null) {
            return response()->json(['message' => 'User has no tenant.'], 400);
        }

        $monitoredUrl = MonitoredUrl::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);

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

        return response()->noContent();
    }

    public function scan(Request $request, int $id)
    {
        $tenantId = $request->user()?->tenant_id;

        if ($tenantId === null) {
            return response()->json(['message' => 'User has no tenant.'], 400);
        }

        $monitoredUrl = MonitoredUrl::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);

        $queueName = app(ScanQueueRoutingService::class)->queueForReason(
            $monitoredUrl,
            ScanQueueRoutingService::REASON_API
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

        return response()->json(['message' => 'Scan dispatched.'], 202);
    }

    public function baseline(Request $request, int $id)
    {
        $tenantId = $request->user()?->tenant_id;

        if ($tenantId === null) {
            return response()->json(['message' => 'User has no tenant.'], 400);
        }

        $data = $request->validate([
            'snapshot_id' => ['required', 'integer', 'min:1'],
        ]);

        $monitoredUrl = MonitoredUrl::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);

        $snapshot = UrlSnapshot::query()
            ->where('monitored_url_id', $monitoredUrl->id)
            ->findOrFail((int) $data['snapshot_id']);

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

        return response()->json([
            'message' => 'Baseline updated.',
            'monitored_url' => $monitoredUrl,
        ]);
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
