<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MonitoredUrl;
use App\Models\Tenant;
use App\Rules\PublicUrl;
use App\Services\AuditLogService;
use App\Services\ExpectedStatusRangeService;
use App\Services\TenantQuotaService;
use App\Services\TagService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MonitoredUrlBulkController extends Controller
{
    public function index(Request $request)
    {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 400, 'User has no tenant.');

        $count = MonitoredUrl::query()
            ->where('tenant_id', $tenantId)
            ->count();

        $limits = app(TenantQuotaService::class)->limitsForTenant($request->user()?->tenant);
        $limit = (int) ($limits['max_monitored_urls'] ?? 0);

        return view('app.monitored-urls.bulk', [
            'count' => $count,
            'limit' => $limit,
            'planTier' => $limits['tier'],
        ]);
    }

    public function export(Request $request)
    {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 400, 'User has no tenant.');
        $defaultAlertAfterFailures = min(10, max(1, (int) config('wids.alerts.default_after_failures', 2)));
        $defaultAlertCooldownMinutes = min(10080, max(0, (int) config('wids.alerts.default_cooldown_minutes', 10)));

        $tenant = Tenant::query()->find($tenantId);

        $filenameTenant = $tenant?->slug ?: ('tenant-'.$tenantId);
        $filename = 'TruMonit-monitors-'.$filenameTenant.'-'.now()->format('Ymd-His').'.json';

        app(AuditLogService::class)->log(
            tenantId: $tenantId,
            actorUserId: $request->user()?->id,
            action: 'monitored_url.bulk_exported',
            auditableType: null,
            auditableId: null,
            data: null,
            request: $request,
        );

        $exportedAt = now()->toISOString();

        return response()->streamDownload(function () use ($tenantId, $tenant, $exportedAt, $defaultAlertAfterFailures, $defaultAlertCooldownMinutes): void {
            $header = [
                'version' => 1,
                'exported_at' => $exportedAt,
                'tenant' => $tenant ? [
                    'id' => $tenant->id,
                    'slug' => $tenant->slug,
                    'name' => $tenant->name,
                ] : [
                    'id' => $tenantId,
                ],
            ];

            echo "{\n";
            echo '  "version": '.json_encode($header['version'], JSON_UNESCAPED_SLASHES).",\n";
            echo '  "exported_at": '.json_encode($header['exported_at'], JSON_UNESCAPED_SLASHES).",\n";
            echo '  "tenant": '.json_encode($header['tenant'], JSON_UNESCAPED_SLASHES).",\n";
            echo "  \"monitors\": [\n";

            $first = true;

            MonitoredUrl::query()
                ->where('tenant_id', $tenantId)
                ->with('tags')
                ->orderBy('id')
                ->chunkById(200, function ($monitoredUrls) use (&$first, $defaultAlertAfterFailures, $defaultAlertCooldownMinutes): void {
                    foreach ($monitoredUrls as $monitoredUrl) {
                        /** @var MonitoredUrl $monitoredUrl */
                        $payload = [
                            'url' => $monitoredUrl->url,
                            'display_name' => $monitoredUrl->display_name,
                            'is_active' => (bool) $monitoredUrl->is_active,
                            'frequency_minutes' => (int) $monitoredUrl->frequency_minutes,
                            'timeout_seconds' => (int) $monitoredUrl->timeout_seconds,
                            'follow_redirects' => (bool) $monitoredUrl->follow_redirects,
                            'expected_status_min' => (int) $monitoredUrl->expected_status_min,
                            'expected_status_max' => (int) $monitoredUrl->expected_status_max,
                            'scan_mode' => $monitoredUrl->scan_mode,
                            'appearance_min_distance' => $monitoredUrl->appearance_min_distance,
                            'alert_after_failures' => (int) ($monitoredUrl->alert_after_failures ?? $defaultAlertAfterFailures),
                            'alert_cooldown_minutes' => (int) ($monitoredUrl->alert_cooldown_minutes ?? $defaultAlertCooldownMinutes),
                            'journey_steps' => $monitoredUrl->journey_steps,
                            'content_rules' => $monitoredUrl->content_rules,
                            'tags' => $monitoredUrl->tags->pluck('name')->sort()->values()->all(),
                        ];

                        $json = json_encode($payload, JSON_UNESCAPED_SLASHES);

                        if (! is_string($json)) {
                            continue;
                        }

                        if (! $first) {
                            echo ",\n";
                        }

                        $first = false;
                        echo '    '.$json;
                    }
                });

            echo "\n  ]\n";
            echo "}\n";
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }

    public function import(Request $request)
    {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 400, 'User has no tenant.');

        $data = $request->validate([
            'json' => ['nullable', 'string', 'max:10000000'],
            'json_file' => ['nullable', 'file', 'max:10240'],
        ]);

        $raw = null;

        if ($request->hasFile('json_file')) {
            $raw = file_get_contents((string) $request->file('json_file')?->getRealPath());
        } elseif (is_string($data['json'] ?? null)) {
            $raw = (string) $data['json'];
        }

        $raw = is_string($raw) ? trim($raw) : '';

        if ($raw === '') {
            throw ValidationException::withMessages([
                'json' => ['Provide JSON via textarea or upload a .json file.'],
            ]);
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'json' => ['Invalid JSON.'],
            ]);
        }

        $monitors = $decoded['monitors'] ?? $decoded;

        if (! is_array($monitors)) {
            throw ValidationException::withMessages([
                'json' => ['Invalid format: expected `monitors` array.'],
            ]);
        }

        if (count($monitors) > 5000) {
            throw ValidationException::withMessages([
                'json' => ['Too many monitors in one import (max 5000).'],
            ]);
        }

        $limits = app(TenantQuotaService::class)->limitsForTenant($request->user()?->tenant);
        $minFrequencyMinutes = max(1, (int) ($limits['min_frequency_minutes'] ?? 1));

        $validated = [];
        $errors = [];

        foreach (array_values($monitors) as $i => $monitor) {
            if (! is_array($monitor)) {
                $errors['monitors.'.$i] = ['Invalid monitor entry.'];
                continue;
            }

            $validator = Validator::make($monitor, [
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
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->messages() as $field => $messages) {
                    $errors['monitors.'.$i.'.'.$field] = $messages;
                }
                continue;
            }

            $row = $validator->validated();

            try {
                $row['tags'] = app(TagService::class)->normalizeManyFromArray($row['tags'] ?? null);
            } catch (\InvalidArgumentException $e) {
                $errors['monitors.'.$i.'.tags'] = [$e->getMessage()];
                continue;
            }

            $validated[] = $row;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $limits = app(TenantQuotaService::class)->limitsForTenant($request->user()?->tenant);
        $limit = (int) ($limits['max_monitored_urls'] ?? 0);
        $existingCount = null;
        $existingByUrl = [];

        if ($limit > 0) {
            $existingCount = MonitoredUrl::query()->where('tenant_id', $tenantId)->count();
            $urls = array_values(array_unique(array_map(fn ($row) => (string) $row['url'], $validated)));

            $existingByUrl = MonitoredUrl::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('url', $urls)
                ->pluck('id', 'url')
                ->all();

            $newCount = count($urls) - count($existingByUrl);

            if ($existingCount + $newCount > $limit) {
                throw ValidationException::withMessages([
                    'json' => [app(TenantQuotaService::class)->monitorLimitMessage($limits)],
                ]);
            }
        }

        $created = 0;
        $updated = 0;

        DB::transaction(function () use ($tenantId, $validated, $existingByUrl, $request, &$created, &$updated): void {
            foreach ($validated as $row) {
                $url = (string) $row['url'];
                $tagNames = is_array($row['tags'] ?? null) ? $row['tags'] : [];

                $monitoredUrl = isset($existingByUrl[$url])
                    ? MonitoredUrl::query()->find((int) $existingByUrl[$url])
                    : MonitoredUrl::query()
                        ->where('tenant_id', $tenantId)
                        ->where('url', $url)
                        ->orderBy('id')
                        ->first();

                if ($monitoredUrl) {
                    $wasActive = (bool) $monitoredUrl->is_active;
                    $oldFrequencyMinutes = (int) $monitoredUrl->frequency_minutes;

                    $scanMode = $row['scan_mode'] ?? $monitoredUrl->scan_mode ?? 'http';
                    $contentRules = array_key_exists('content_rules', $row)
                        ? (is_array($row['content_rules']) ? $row['content_rules'] : [])
                        : (is_array($monitoredUrl->content_rules) ? $monitoredUrl->content_rules : []);

                    if ($scanMode === 'heartbeat') {
                        $heartbeatRules = is_array($contentRules['heartbeat'] ?? null) ? $contentRules['heartbeat'] : [];
                        $heartbeatRules['token'] = is_string($heartbeatRules['token'] ?? null) && trim((string) $heartbeatRules['token']) !== ''
                            ? trim((string) $heartbeatRules['token'])
                            : Str::random(48);
                        $heartbeatRules['grace_minutes'] = min(10080, max(0, (int) ($heartbeatRules['grace_minutes'] ?? 3)));
                        $contentRules['heartbeat'] = $heartbeatRules;
                    }

                    if ($contentRules === []) {
                        $contentRules = null;
                    }

                    try {
                        [$expectedStatusMin, $expectedStatusMax] = app(ExpectedStatusRangeService::class)->resolve(
                            array_key_exists('expected_status_min', $row) ? (int) $row['expected_status_min'] : null,
                            array_key_exists('expected_status_max', $row) ? (int) $row['expected_status_max'] : null,
                            (int) $monitoredUrl->expected_status_min,
                            (int) $monitoredUrl->expected_status_max,
                        );
                    } catch (\InvalidArgumentException $e) {
                        throw ValidationException::withMessages([
                            'json' => ['Invalid expected status range for monitor URL: '.$url],
                        ]);
                    }

                    $newIsActive = array_key_exists('is_active', $row) ? (bool) $row['is_active'] : $wasActive;
                    $newFrequencyMinutes = array_key_exists('frequency_minutes', $row) ? (int) $row['frequency_minutes'] : $oldFrequencyMinutes;
                    $newFrequencyMinutes = min(43200, max(1, $newFrequencyMinutes));

                    $nextScanAt = $monitoredUrl->next_scan_at;

                    if (! $newIsActive) {
                        $nextScanAt = null;
                    } elseif ($wasActive === false && $newIsActive === true) {
                        $nextScanAt = now();
                    } elseif (array_key_exists('frequency_minutes', $row) && $newFrequencyMinutes !== $oldFrequencyMinutes) {
                        $nextScanAt = $monitoredUrl->last_scanned_at !== null
                            ? $monitoredUrl->last_scanned_at->copy()->addMinutes($newFrequencyMinutes)
                            : now();
                    }

                    $monitoredUrl->fill([
                        'display_name' => array_key_exists('display_name', $row) ? $row['display_name'] : $monitoredUrl->display_name,
                        'frequency_minutes' => array_key_exists('frequency_minutes', $row) ? $row['frequency_minutes'] : $monitoredUrl->frequency_minutes,
                        'timeout_seconds' => array_key_exists('timeout_seconds', $row)
                            ? $row['timeout_seconds']
                            : ($scanMode === 'port' ? 10 : ($scanMode === 'heartbeat' ? 5 : $monitoredUrl->timeout_seconds)),
                        'follow_redirects' => $scanMode === 'http' || $scanMode === 'journey'
                            ? (array_key_exists('follow_redirects', $row) ? $row['follow_redirects'] : $monitoredUrl->follow_redirects)
                            : false,
                        'expected_status_min' => $expectedStatusMin,
                        'expected_status_max' => $expectedStatusMax,
                        'is_active' => $newIsActive,
                        'next_scan_at' => $nextScanAt,
                        'scan_mode' => $scanMode,
                        'appearance_min_distance' => $scanMode === 'http' || $scanMode === 'journey'
                            ? (array_key_exists('appearance_min_distance', $row) ? $row['appearance_min_distance'] : $monitoredUrl->appearance_min_distance)
                            : null,
                        'alert_after_failures' => array_key_exists('alert_after_failures', $row) ? $row['alert_after_failures'] : $monitoredUrl->alert_after_failures,
                        'alert_cooldown_minutes' => array_key_exists('alert_cooldown_minutes', $row) ? $row['alert_cooldown_minutes'] : $monitoredUrl->alert_cooldown_minutes,
                        'journey_steps' => $scanMode === 'journey' ? ($row['journey_steps'] ?? $monitoredUrl->journey_steps) : null,
                        'content_rules' => $contentRules,
                    ]);
                    $monitoredUrl->save();

                    app(TagService::class)->syncMonitoredUrlTags($monitoredUrl, $tagNames);

                    $updated++;
                    continue;
                }

                $isActive = array_key_exists('is_active', $row) ? (bool) $row['is_active'] : true;
                $scanMode = $row['scan_mode'] ?? 'http';
                $contentRules = is_array($row['content_rules'] ?? null) ? $row['content_rules'] : [];
                if ($scanMode === 'heartbeat') {
                    $heartbeatRules = is_array($contentRules['heartbeat'] ?? null) ? $contentRules['heartbeat'] : [];
                    $heartbeatRules['token'] = is_string($heartbeatRules['token'] ?? null) && trim((string) $heartbeatRules['token']) !== ''
                        ? trim((string) $heartbeatRules['token'])
                        : Str::random(48);
                    $heartbeatRules['grace_minutes'] = min(10080, max(0, (int) ($heartbeatRules['grace_minutes'] ?? 3)));
                    $contentRules['heartbeat'] = $heartbeatRules;
                }
                if ($contentRules === []) {
                    $contentRules = null;
                }
                $defaultAlertAfterFailures = min(10, max(1, (int) config('wids.alerts.default_after_failures', 2)));
                $defaultAlertCooldownMinutes = min(10080, max(0, (int) config('wids.alerts.default_cooldown_minutes', 10)));

                try {
                    [$expectedStatusMin, $expectedStatusMax] = app(ExpectedStatusRangeService::class)->resolve(
                        array_key_exists('expected_status_min', $row) ? (int) $row['expected_status_min'] : null,
                        array_key_exists('expected_status_max', $row) ? (int) $row['expected_status_max'] : null,
                        200,
                        399,
                    );
                } catch (\InvalidArgumentException $e) {
                    throw ValidationException::withMessages([
                        'json' => ['Invalid expected status range for monitor URL: '.$url],
                    ]);
                }

                $createdUrl = MonitoredUrl::query()->create([
                    'tenant_id' => $tenantId,
                    'created_by_user_id' => $request->user()?->id,
                    'url' => $url,
                    'display_name' => $row['display_name'] ?? null,
                    'is_active' => $isActive,
                    'next_scan_at' => $isActive ? now() : null,
                    'frequency_minutes' => $row['frequency_minutes'] ?? 1440,
                    'timeout_seconds' => $row['timeout_seconds'] ?? ($scanMode === 'port' ? 10 : ($scanMode === 'heartbeat' ? 5 : 30)),
                    'follow_redirects' => $scanMode === 'http' || $scanMode === 'journey'
                        ? ($row['follow_redirects'] ?? true)
                        : false,
                    'expected_status_min' => $expectedStatusMin,
                    'expected_status_max' => $expectedStatusMax,
                    'scan_mode' => $scanMode,
                    'appearance_min_distance' => $scanMode === 'http' || $scanMode === 'journey'
                        ? ($row['appearance_min_distance'] ?? null)
                        : null,
                    'alert_after_failures' => $row['alert_after_failures'] ?? $defaultAlertAfterFailures,
                    'alert_cooldown_minutes' => $row['alert_cooldown_minutes'] ?? $defaultAlertCooldownMinutes,
                    'journey_steps' => $scanMode === 'journey' ? ($row['journey_steps'] ?? null) : null,
                    'content_rules' => $contentRules,
                ]);

                app(TagService::class)->syncMonitoredUrlTags($createdUrl, $tagNames);

                $created++;
            }
        });

        app(AuditLogService::class)->log(
            tenantId: $tenantId,
            actorUserId: $request->user()?->id,
            action: 'monitored_url.bulk_imported',
            auditableType: null,
            auditableId: null,
            data: [
                'created' => $created,
                'updated' => $updated,
                'total' => count($validated),
            ],
            request: $request,
        );

        return redirect()
            ->route('monitored-urls.bulk')
            ->with('status', 'Import finished. Created: '.$created.', updated: '.$updated.'.');
    }

    public function actions(Request $request)
    {
        $tenantId = $request->user()?->tenant_id;
        abort_if($tenantId === null, 400, 'User has no tenant.');

        $limits = app(TenantQuotaService::class)->limitsForTenant($request->user()?->tenant);
        $minFrequencyMinutes = max(1, (int) ($limits['min_frequency_minutes'] ?? 1));

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer', 'min:1'],
            'action' => ['required', 'string', Rule::in([
                'pause',
                'resume',
                'set_frequency',
                'mute',
                'unmute',
                'add_tags',
                'set_tags',
                'remove_tags',
            ])],
            'frequency_minutes' => ['nullable', 'integer', 'min:'.$minFrequencyMinutes, 'max:43200'],
            'mute_minutes' => ['nullable', 'integer', 'min:5', 'max:10080'],
            'tags' => ['nullable', 'string', 'max:500'],
        ]);

        $ids = array_values(array_unique(array_map('intval', $data['ids'])));
        $action = (string) $data['action'];

        $monitoredUrls = MonitoredUrl::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $ids)
            ->with('tags')
            ->get();

        if ($monitoredUrls->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'ids' => ['Some monitors were not found in this workspace.'],
            ]);
        }

        $now = now();

        $changed = 0;

        DB::transaction(function () use ($monitoredUrls, $action, $data, $now, &$changed): void {
            if ($action === 'pause') {
                $idsToUpdate = $monitoredUrls->pluck('id')->map(fn ($v) => (int) $v)->all();

                $changed = MonitoredUrl::query()
                    ->whereIn('id', $idsToUpdate)
                    ->update([
                        'is_active' => false,
                        'next_scan_at' => null,
                        'updated_at' => $now,
                    ]);

                return;
            }

            if ($action === 'resume') {
                $idsToUpdate = $monitoredUrls->pluck('id')->map(fn ($v) => (int) $v)->all();

                $changed = MonitoredUrl::query()
                    ->whereIn('id', $idsToUpdate)
                    ->update([
                        'is_active' => true,
                        'next_scan_at' => $now,
                        'updated_at' => $now,
                    ]);

                return;
            }

            if ($action === 'set_frequency') {
                $frequencyMinutes = isset($data['frequency_minutes']) ? (int) $data['frequency_minutes'] : null;

                if (! is_int($frequencyMinutes)) {
                    throw ValidationException::withMessages([
                        'frequency_minutes' => ['Frequency is required for this action.'],
                    ]);
                }

                $frequencyMinutes = min(43200, max($minFrequencyMinutes, $frequencyMinutes));

                foreach ($monitoredUrls as $monitoredUrl) {
                    /** @var MonitoredUrl $monitoredUrl */
                    $nextScanAt = $monitoredUrl->is_active
                        ? (($monitoredUrl->last_scanned_at?->copy()->addMinutes($frequencyMinutes)) ?? $now)
                        : null;

                    $monitoredUrl->forceFill([
                        'frequency_minutes' => $frequencyMinutes,
                        'next_scan_at' => $nextScanAt,
                    ])->save();

                    $changed++;
                }

                return;
            }

            if ($action === 'mute') {
                $minutes = isset($data['mute_minutes']) ? (int) $data['mute_minutes'] : 60;
                $minutes = min(10080, max(5, $minutes));

                $mutedUntil = $now->copy()->addMinutes($minutes);

                $idsToUpdate = $monitoredUrls->pluck('id')->map(fn ($v) => (int) $v)->all();

                $changed = MonitoredUrl::query()
                    ->whereIn('id', $idsToUpdate)
                    ->update([
                        'muted_until' => $mutedUntil,
                        'updated_at' => $now,
                    ]);

                return;
            }

            if ($action === 'unmute') {
                $idsToUpdate = $monitoredUrls->pluck('id')->map(fn ($v) => (int) $v)->all();

                $changed = MonitoredUrl::query()
                    ->whereIn('id', $idsToUpdate)
                    ->update([
                        'muted_until' => null,
                        'updated_at' => $now,
                    ]);

                return;
            }

            if (in_array($action, ['add_tags', 'set_tags', 'remove_tags'], true)) {
                $input = is_string($data['tags'] ?? null) ? (string) $data['tags'] : null;

                try {
                    $tagNames = app(TagService::class)->normalizeManyFromString($input);
                } catch (\InvalidArgumentException $e) {
                    throw ValidationException::withMessages([
                        'tags' => [$e->getMessage()],
                    ]);
                }

                if ($action !== 'set_tags' && $tagNames === []) {
                    throw ValidationException::withMessages([
                        'tags' => ['Provide at least one valid tag.'],
                    ]);
                }

                foreach ($monitoredUrls as $monitoredUrl) {
                    /** @var MonitoredUrl $monitoredUrl */
                    $existing = $monitoredUrl->tags->pluck('name')->values()->all();

                    if ($action === 'set_tags') {
                        $final = $tagNames;
                    } elseif ($action === 'add_tags') {
                        $final = array_values(array_unique(array_merge($existing, $tagNames)));
                    } else {
                        $remove = array_fill_keys($tagNames, true);
                        $final = array_values(array_filter($existing, fn ($t) => ! isset($remove[$t])));
                    }

                    app(TagService::class)->syncMonitoredUrlTags($monitoredUrl, $final);
                    $changed++;
                }

                return;
            }

            throw ValidationException::withMessages([
                'action' => ['Invalid action.'],
            ]);
        });

        app(AuditLogService::class)->log(
            tenantId: $tenantId,
            actorUserId: $request->user()?->id,
            action: 'monitored_url.bulk_action',
            auditableType: null,
            auditableId: null,
            data: [
                'action' => $action,
                'count' => count($ids),
                'changed' => $changed,
                'sample_ids' => array_slice($ids, 0, 25),
            ],
            request: $request,
        );

        return redirect()
            ->back()
            ->with('status', 'Bulk action applied. Changed: '.$changed.'.');
    }
}
