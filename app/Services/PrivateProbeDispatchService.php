<?php

namespace App\Services;

use App\Models\MonitoredUrl;
use App\Models\PrivateProbe;
use App\Models\PrivateProbeScanTask;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class PrivateProbeDispatchService
{
    /**
     * @return array{
     *   enabled: bool,
     *   monitor_tag: string,
     *   processed: int,
     *   queued_total: int,
     *   available_probes_total: int,
     *   available_probe_tenants_total: int,
     *   queued_by_probe: array<int, int>,
     *   queued_by_tenant: array<int, int>,
     *   skipped_inflight: int,
     *   skipped_no_probe: int,
     *   skipped_tenant_cap: int,
     *   skipped_validation: int,
     *   skipped_pinned_probe_missing: int,
     *   skipped_requested_region_policy: int,
     *   probes_filtered_residency_policy: int,
     *   region_fallback_total: int,
     *   max_tasks: int,
     *   tenant_dispatch_cap: int
     * }
     */
    public function dispatchDueMonitors(?int $tenantId = null, bool $force = false, ?int $maxTasks = null): array
    {
        $enabled = (bool) config('wids.private_probes.enabled', false);
        $monitorTag = $this->normalizeTag((string) config('wids.private_probes.monitor_tag', 'private_probe'), 'private_probe');

        $summary = [
            'enabled' => $enabled,
            'monitor_tag' => $monitorTag,
            'processed' => 0,
            'queued_total' => 0,
            'available_probes_total' => 0,
            'available_probe_tenants_total' => 0,
            'queued_by_probe' => [],
            'queued_by_tenant' => [],
            'skipped_inflight' => 0,
            'skipped_no_probe' => 0,
            'skipped_tenant_cap' => 0,
            'skipped_validation' => 0,
            'skipped_pinned_probe_missing' => 0,
            'skipped_requested_region_policy' => 0,
            'probes_filtered_residency_policy' => 0,
            'region_fallback_total' => 0,
            'max_tasks' => 0,
            'tenant_dispatch_cap' => 0,
        ];

        if (! $enabled) {
            return $summary;
        }

        $now = now();
        [$probesByTenant, $probesById, $filteredByResidency] = $this->loadAvailableProbes($tenantId, $now);
        $probeLoads = $this->loadProbeLoads(array_keys($probesById), $now);
        $probeDispatched = [];
        $tenantDispatched = [];
        $policyService = app(PrivateProbeFleetPolicyService::class);

        $summary['available_probes_total'] = count($probesById);
        $summary['available_probe_tenants_total'] = count($probesByTenant);
        $summary['probes_filtered_residency_policy'] = $filteredByResidency;

        $maxTasks = is_int($maxTasks) ? $maxTasks : (int) config('wids.private_probes.dispatch_max_per_tick', 500);
        $maxTasks = max(1, min(10_000, $maxTasks));

        $tenantDispatchCap = (int) config('wids.private_probes.dispatch_cap_per_tenant', 250);
        $tenantDispatchCap = max(0, min(10_000, $tenantDispatchCap));

        $summary['max_tasks'] = $maxTasks;
        $summary['tenant_dispatch_cap'] = $tenantDispatchCap;

        $pinTagPrefix = $this->normalizePrefix((string) config('wids.private_probes.pin_tag_prefix', 'probe-id:'), 'probe-id:');
        $regionTagPrefix = $this->normalizePrefix((string) config('wids.private_probes.region_tag_prefix', 'probe-region:'), 'probe-region:');
        $regionFallback = (bool) config('wids.private_probes.region_fallback', true);

        $taskService = app(PrivateProbeTaskService::class);

        $query = MonitoredUrl::query()
            ->where('is_active', true)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->whereHas('tags', fn ($tagQuery) => $tagQuery->where('name', $monitorTag))
            ->when(! $force, function ($q) use ($now): void {
                $q->where(function ($dueQuery) use ($now): void {
                    $dueQuery->whereNull('next_scan_at')
                        ->orWhere('next_scan_at', '<=', $now);
                });
            })
            ->with(['tags:id,tenant_id,name'])
            ->select([
                'id',
                'tenant_id',
                'url',
                'display_name',
                'next_scan_at',
                'frequency_minutes',
                'consecutive_failures',
                'alert_after_failures',
            ])
            ->orderBy('id');

        $query->chunkById(250, function ($monitors) use (
            &$summary,
            &$probeLoads,
            &$probeDispatched,
            &$tenantDispatched,
            $probesByTenant,
            $now,
            $maxTasks,
            $tenantDispatchCap,
            $pinTagPrefix,
            $regionTagPrefix,
            $regionFallback,
            $policyService,
            $force,
            $taskService
        ) {
            if ($summary['queued_total'] >= $maxTasks) {
                return false;
            }

            $monitorIds = $monitors
                ->pluck('id')
                ->map(fn ($value): int => (int) $value)
                ->values()
                ->all();

            $inflightMap = $this->loadInflightMonitorMap($monitorIds, $now);

            foreach ($monitors as $monitor) {
                /** @var MonitoredUrl $monitor */
                if ($summary['queued_total'] >= $maxTasks) {
                    return false;
                }

                $summary['processed']++;

                if (isset($inflightMap[(int) $monitor->id])) {
                    $summary['skipped_inflight']++;
                    continue;
                }

                $tenantKey = (int) $monitor->tenant_id;

                if ($tenantDispatchCap > 0 && ($tenantDispatched[$tenantKey] ?? 0) >= $tenantDispatchCap) {
                    $summary['skipped_tenant_cap']++;
                    continue;
                }

                $candidates = $probesByTenant[$tenantKey] ?? [];

                if ($candidates === []) {
                    $summary['skipped_no_probe']++;
                    continue;
                }

                $hints = $this->extractRoutingHints($monitor, $pinTagPrefix, $regionTagPrefix);
                $pinnedProbeId = $hints['pin_probe_id'];
                $requestedRegion = $hints['region_hint'];

                if ($pinnedProbeId !== null) {
                    $candidates = array_values(array_filter(
                        $candidates,
                        fn (PrivateProbe $probe): bool => (int) $probe->id === $pinnedProbeId
                    ));

                    if ($candidates === []) {
                        $summary['skipped_no_probe']++;
                        $summary['skipped_pinned_probe_missing']++;
                        continue;
                    }
                } elseif ($requestedRegion !== null) {
                    if (! $policyService->isRegionAllowedForTenantId($tenantKey, $requestedRegion)) {
                        $summary['skipped_no_probe']++;
                        $summary['skipped_requested_region_policy']++;
                        continue;
                    }

                    $regional = array_values(array_filter(
                        $candidates,
                        fn (PrivateProbe $probe): bool => $this->normalizeRegionHint($probe->region_hint) === $requestedRegion
                    ));

                    if ($regional !== []) {
                        $candidates = $regional;
                    } elseif ($regionFallback) {
                        $summary['region_fallback_total']++;
                    } else {
                        $summary['skipped_no_probe']++;
                        continue;
                    }
                }

                $selectedProbe = $this->pickProbe($candidates, $probeLoads, $probeDispatched);

                if (! $selectedProbe) {
                    $summary['skipped_no_probe']++;
                    continue;
                }

                $scanLagSeconds = $this->scanLagSeconds($monitor, $now);
                $priority = $this->resolvePriority($monitor, $scanLagSeconds);

                try {
                    $taskService->queueTask(
                        privateProbe: $selectedProbe,
                        monitoredUrl: $monitor,
                        requestedByUserId: null,
                        priority: $priority,
                        meta: [
                            'auto_dispatch' => [
                                'source' => 'wids:private-probes-dispatch',
                                'dispatched_at' => $now->toISOString(),
                                'force' => $force,
                                'scan_lag_seconds' => $scanLagSeconds,
                                'pin_probe_id' => $pinnedProbeId,
                                'requested_region' => $requestedRegion,
                            ],
                        ],
                    );
                } catch (ValidationException) {
                    $summary['skipped_validation']++;
                    continue;
                }

                $summary['queued_total']++;
                $summary['queued_by_probe'][$selectedProbe->id] = ((int) ($summary['queued_by_probe'][$selectedProbe->id] ?? 0)) + 1;
                $summary['queued_by_tenant'][$tenantKey] = ((int) ($summary['queued_by_tenant'][$tenantKey] ?? 0)) + 1;
                $probeLoads[$selectedProbe->id] = ((int) ($probeLoads[$selectedProbe->id] ?? 0)) + 1;
                $probeDispatched[$selectedProbe->id] = ((int) ($probeDispatched[$selectedProbe->id] ?? 0)) + 1;
                $tenantDispatched[$tenantKey] = ((int) ($tenantDispatched[$tenantKey] ?? 0)) + 1;
            }

            return true;
        });

        return $summary;
    }

    /**
     * @return array{0: array<int, list<PrivateProbe>>, 1: array<int, PrivateProbe>, 2: int}
     */
    private function loadAvailableProbes(?int $tenantId, Carbon $now): array
    {
        $staleAfterMinutes = max(1, min(10_080, (int) config('wids.private_probes.stale_after_minutes', 10)));
        $staleCutoff = $now->copy()->subMinutes($staleAfterMinutes);
        $requireOnline = (bool) config('wids.private_probes.dispatch_require_online', true);
        $policyService = app(PrivateProbeFleetPolicyService::class);

        $probes = PrivateProbe::query()
            ->where('is_active', true)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId))
            ->select(['id', 'tenant_id', 'name', 'region_hint', 'last_seen_at', 'is_active'])
            ->get()
            ->when($requireOnline, fn ($collection) => $collection->filter(
                fn (PrivateProbe $probe): bool => $probe->last_seen_at !== null && $probe->last_seen_at->gte($staleCutoff)
            ))
            ->values();

        $filteredByResidency = 0;
        $probes = $probes
            ->filter(function (PrivateProbe $probe) use ($policyService, &$filteredByResidency): bool {
                $allowed = $policyService->isRegionAllowedForTenantId((int) $probe->tenant_id, $probe->region_hint);

                if (! $allowed) {
                    $filteredByResidency++;
                }

                return $allowed;
            })
            ->values();

        $byTenant = [];
        $byId = [];

        foreach ($probes as $probe) {
            $tenantKey = (int) $probe->tenant_id;
            $byTenant[$tenantKey][] = $probe;
            $byId[(int) $probe->id] = $probe;
        }

        return [$byTenant, $byId, $filteredByResidency];
    }

    /**
     * @param  list<int>  $probeIds
     * @return array<int, int>
     */
    private function loadProbeLoads(array $probeIds, Carbon $now): array
    {
        if ($probeIds === []) {
            return [];
        }

        $counts = PrivateProbeScanTask::query()
            ->whereIn('private_probe_id', $probeIds)
            ->where(function ($query) use ($now): void {
                $query->where('status', 'queued')
                    ->orWhere(function ($leaseQuery) use ($now): void {
                        $leaseQuery->where('status', 'leased')
                            ->where(function ($q) use ($now): void {
                                $q->whereNull('lease_expires_at')
                                    ->orWhere('lease_expires_at', '>', $now);
                            });
                    });
            })
            ->selectRaw('private_probe_id, COUNT(*) as c')
            ->groupBy('private_probe_id')
            ->pluck('c', 'private_probe_id')
            ->all();

        $result = [];

        foreach ($counts as $probeId => $count) {
            $result[(int) $probeId] = max(0, (int) $count);
        }

        return $result;
    }

    /**
     * @param  list<int>  $monitorIds
     * @return array<int, true>
     */
    private function loadInflightMonitorMap(array $monitorIds, Carbon $now): array
    {
        if ($monitorIds === []) {
            return [];
        }

        $monitorIdValues = PrivateProbeScanTask::query()
            ->whereIn('monitored_url_id', $monitorIds)
            ->where(function ($query) use ($now): void {
                $query->where('status', 'queued')
                    ->orWhere(function ($leaseQuery) use ($now): void {
                        $leaseQuery->where('status', 'leased')
                            ->where(function ($q) use ($now): void {
                                $q->whereNull('lease_expires_at')
                                    ->orWhere('lease_expires_at', '>', $now);
                            });
                    });
            })
            ->pluck('monitored_url_id')
            ->map(fn ($value): int => (int) $value)
            ->values()
            ->all();

        $map = [];

        foreach ($monitorIdValues as $monitorId) {
            $map[$monitorId] = true;
        }

        return $map;
    }

    private function pickProbe(array $candidates, array $probeLoads, array $probeDispatched): ?PrivateProbe
    {
        if ($candidates === []) {
            return null;
        }

        usort($candidates, function (PrivateProbe $a, PrivateProbe $b) use ($probeLoads, $probeDispatched): int {
            $aLoad = (int) ($probeLoads[$a->id] ?? 0);
            $bLoad = (int) ($probeLoads[$b->id] ?? 0);

            if ($aLoad !== $bLoad) {
                return $aLoad <=> $bLoad;
            }

            $aDispatched = (int) ($probeDispatched[$a->id] ?? 0);
            $bDispatched = (int) ($probeDispatched[$b->id] ?? 0);

            if ($aDispatched !== $bDispatched) {
                return $aDispatched <=> $bDispatched;
            }

            $aSeen = $a->last_seen_at?->getTimestamp() ?? 0;
            $bSeen = $b->last_seen_at?->getTimestamp() ?? 0;

            if ($aSeen !== $bSeen) {
                return $aSeen > $bSeen ? -1 : 1;
            }

            return (int) $a->id <=> (int) $b->id;
        });

        return $candidates[0] ?? null;
    }

    /**
     * @return array{pin_probe_id: ?int, region_hint: ?string}
     */
    private function extractRoutingHints(MonitoredUrl $monitor, string $pinTagPrefix, string $regionTagPrefix): array
    {
        $pinProbeId = null;
        $regionHint = null;
        $pinPrefixLength = strlen($pinTagPrefix);
        $regionPrefixLength = strlen($regionTagPrefix);

        foreach ($monitor->tags as $tag) {
            $name = strtolower(trim((string) $tag->name));

            if ($name === '') {
                continue;
            }

            if ($pinProbeId === null && str_starts_with($name, $pinTagPrefix)) {
                $suffix = trim(substr($name, $pinPrefixLength));

                if ($suffix !== '' && ctype_digit($suffix)) {
                    $pinProbeId = (int) $suffix;
                }

                continue;
            }

            if ($regionHint === null && str_starts_with($name, $regionTagPrefix)) {
                $suffix = $this->normalizeRegionHint(substr($name, $regionPrefixLength));

                if ($suffix !== null) {
                    $regionHint = $suffix;
                }
            }
        }

        return [
            'pin_probe_id' => $pinProbeId,
            'region_hint' => $regionHint,
        ];
    }

    private function scanLagSeconds(MonitoredUrl $monitor, Carbon $now): ?int
    {
        if ($monitor->next_scan_at === null) {
            return null;
        }

        return max(0, $monitor->next_scan_at->diffInSeconds($now, false));
    }

    private function resolvePriority(MonitoredUrl $monitor, ?int $scanLagSeconds): int
    {
        $priority = 50;
        $lowPriorityMinFrequencyMinutes = max(1, min(43_200, (int) config('wids.queue.scan.low_priority_min_frequency_minutes', 60)));
        $lowPriorityMaxLagSeconds = max(0, min(86_400, (int) config('wids.queue.scan.low_priority_max_lag_seconds', 120)));

        if ((int) ($monitor->frequency_minutes ?? 0) >= $lowPriorityMinFrequencyMinutes && (($scanLagSeconds ?? 0) <= $lowPriorityMaxLagSeconds)) {
            $priority = 80;
        }

        $highPriorityLagSeconds = max(30, min(86_400, (int) config('wids.queue.scan.high_priority_lag_seconds', 300)));
        if ($scanLagSeconds !== null && $scanLagSeconds >= $highPriorityLagSeconds) {
            $priority = 20;
        }

        $highPriorityFailures = max(1, min(100, (int) config('wids.queue.scan.high_priority_failures', 1)));
        if ((int) ($monitor->consecutive_failures ?? 0) >= $highPriorityFailures) {
            $priority = min($priority, 10);
        }

        return max(1, min(100, $priority));
    }

    private function normalizeTag(string $value, string $fallback): string
    {
        $value = strtolower(trim($value));

        if ($value === '' || strlen($value) > 64 || ! preg_match('/\A[a-z0-9][a-z0-9:_-]{0,63}\z/', $value)) {
            return $fallback;
        }

        return $value;
    }

    private function normalizePrefix(string $value, string $fallback): string
    {
        $value = strtolower(trim($value));

        return $value !== '' ? $value : $fallback;
    }

    private function normalizeRegionHint(?string $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = strtolower(trim($value));

        return $value !== '' ? $value : null;
    }
}
