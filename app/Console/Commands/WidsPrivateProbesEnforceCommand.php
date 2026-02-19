<?php

namespace App\Console\Commands;

use App\Models\PrivateProbe;
use App\Models\Tenant;
use App\Services\PrivateProbeFleetPolicyService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class WidsPrivateProbesEnforceCommand extends Command
{
    protected $signature = 'wids:private-probes-enforce
        {--tenant_id= : Optional tenant scope}
        {--stale-hours= : Override stale disable threshold in hours}
        {--dry-run : Show actions without writing changes}
        {--format=text : text|json}
        {--force : Run even when private probes are disabled}';

    protected $description = 'Enforce private probe residency and managed fleet policies';

    public function handle(): int
    {
        $enabled = (bool) config('wids.private_probes.enabled', false);
        $force = (bool) $this->option('force');

        if (! $enabled && ! $force) {
            $this->info('Private probes are disabled.');

            return self::SUCCESS;
        }

        $tenantId = is_numeric($this->option('tenant_id')) ? (int) $this->option('tenant_id') : null;
        $tenantId = is_int($tenantId) && $tenantId > 0 ? $tenantId : null;

        $dryRun = (bool) $this->option('dry-run');
        $format = $this->resolveFormat();
        $policyService = app(PrivateProbeFleetPolicyService::class);
        $staleHours = $this->resolveStaleHours($policyService);
        $staleCutoff = $staleHours > 0 ? now()->subHours($staleHours) : null;
        $eventHistoryLimit = $policyService->eventHistoryLimit();

        $summary = [
            'enabled' => $enabled,
            'forced' => $force,
            'tenant_id' => $tenantId,
            'dry_run' => $dryRun,
            'stale_hours' => $staleHours,
            'tenants_processed' => 0,
            'active_scanned' => 0,
            'disabled_region_policy' => 0,
            'disabled_stale' => 0,
            'disabled_capacity' => 0,
            'would_disable_region_policy' => 0,
            'would_disable_stale' => 0,
            'would_disable_capacity' => 0,
            'errors' => 0,
        ];

        Tenant::query()
            ->when($tenantId !== null, fn ($query) => $query->where('id', $tenantId))
            ->whereHas('privateProbes', fn ($query) => $query->where('is_active', true))
            ->select(['id', 'plan_tier', 'slug', 'name'])
            ->orderBy('id')
            ->chunkById(100, function ($tenants) use (&$summary, $policyService, $staleCutoff, $eventHistoryLimit, $dryRun): void {
                foreach ($tenants as $tenant) {
                    /** @var Tenant $tenant */
                    $summary['tenants_processed']++;

                    $maxActive = $policyService->maxActiveForTenant($tenant);
                    $activeProbes = PrivateProbe::query()
                        ->where('tenant_id', $tenant->id)
                        ->where('is_active', true)
                        ->orderByDesc('last_seen_at')
                        ->orderBy('id')
                        ->get();

                    $summary['active_scanned'] += $activeProbes->count();

                    $eligibleAfterPolicy = [];

                    foreach ($activeProbes as $probe) {
                        /** @var PrivateProbe $probe */
                        if (! $policyService->isRegionAllowedForTenant($tenant, $probe->region_hint)) {
                            $this->applyDisable(
                                probe: $probe,
                                reason: 'region_not_allowed',
                                dryRun: $dryRun,
                                summaryKey: 'region_policy',
                                staleHours: null,
                                eventHistoryLimit: $eventHistoryLimit,
                                summary: $summary,
                            );
                            continue;
                        }

                        if ($staleCutoff !== null && ($probe->last_seen_at === null || $probe->last_seen_at->lt($staleCutoff))) {
                            $this->applyDisable(
                                probe: $probe,
                                reason: 'stale_probe',
                                dryRun: $dryRun,
                                summaryKey: 'stale',
                                staleHours: $summary['stale_hours'],
                                eventHistoryLimit: $eventHistoryLimit,
                                summary: $summary,
                            );
                            continue;
                        }

                        $eligibleAfterPolicy[] = $probe;
                    }

                    if ($maxActive > 0 && count($eligibleAfterPolicy) > $maxActive) {
                        usort($eligibleAfterPolicy, function (PrivateProbe $a, PrivateProbe $b): int {
                            $aSeen = $a->last_seen_at?->getTimestamp() ?? 0;
                            $bSeen = $b->last_seen_at?->getTimestamp() ?? 0;

                            if ($aSeen !== $bSeen) {
                                return $aSeen > $bSeen ? -1 : 1;
                            }

                            return (int) $a->id <=> (int) $b->id;
                        });

                        foreach (array_slice($eligibleAfterPolicy, $maxActive) as $overflowProbe) {
                            $this->applyDisable(
                                probe: $overflowProbe,
                                reason: 'capacity_limit',
                                dryRun: $dryRun,
                                summaryKey: 'capacity',
                                staleHours: null,
                                eventHistoryLimit: $eventHistoryLimit,
                                summary: $summary,
                            );
                        }
                    }
                }
            });

        if ($format === 'json') {
            $this->line((string) json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info('Private probe policy enforcement summary'.($dryRun ? ' (dry-run)' : '').':');
            $this->line('  Tenants processed: '.(int) $summary['tenants_processed']);
            $this->line('  Active probes scanned: '.(int) $summary['active_scanned']);
            $this->line('  Disabled (region policy): '.(int) $summary['disabled_region_policy']);
            $this->line('  Disabled (stale): '.(int) $summary['disabled_stale']);
            $this->line('  Disabled (capacity): '.(int) $summary['disabled_capacity']);

            if ($dryRun) {
                $this->line('  Would disable (region policy): '.(int) $summary['would_disable_region_policy']);
                $this->line('  Would disable (stale): '.(int) $summary['would_disable_stale']);
                $this->line('  Would disable (capacity): '.(int) $summary['would_disable_capacity']);
            }

            $this->line('  Errors: '.(int) $summary['errors']);
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, int|bool|null>  $summary
     */
    private function applyDisable(
        PrivateProbe $probe,
        string $reason,
        bool $dryRun,
        string $summaryKey,
        ?int $staleHours,
        int $eventHistoryLimit,
        array &$summary
    ): void {
        $disabledKey = 'disabled_'.$summaryKey;
        $wouldDisableKey = 'would_disable_'.$summaryKey;

        if (! isset($summary[$disabledKey], $summary[$wouldDisableKey])) {
            return;
        }

        if ($dryRun) {
            $summary[$wouldDisableKey] = (int) $summary[$wouldDisableKey] + 1;

            return;
        }

        $meta = is_array($probe->meta) ? $probe->meta : [];
        $fleetPolicy = is_array($meta['fleet_policy'] ?? null) ? $meta['fleet_policy'] : [];
        $events = is_array($fleetPolicy['events'] ?? null) ? array_values(array_filter($fleetPolicy['events'], 'is_array')) : [];

        $events[] = [
            'at' => now()->toISOString(),
            'reason' => $reason,
            'probe_id' => (int) $probe->id,
        ];

        if (count($events) > $eventHistoryLimit) {
            $events = array_slice($events, -$eventHistoryLimit);
        }

        $fleetPolicy['last_reason'] = $reason;
        $fleetPolicy['last_enforced_at'] = now()->toISOString();
        $fleetPolicy['auto_disabled'] = true;
        $fleetPolicy['events'] = $events;

        if ($staleHours !== null) {
            $fleetPolicy['stale_threshold_hours'] = $staleHours;
        }

        $meta['fleet_policy'] = $fleetPolicy;

        try {
            $probe->forceFill([
                'is_active' => false,
                'meta' => $meta,
            ])->save();
            $summary[$disabledKey] = (int) $summary[$disabledKey] + 1;
        } catch (\Throwable) {
            $summary['errors'] = (int) $summary['errors'] + 1;
        }
    }

    private function resolveFormat(): string
    {
        $format = strtolower(trim((string) $this->option('format')));

        return in_array($format, ['text', 'json'], true) ? $format : 'text';
    }

    private function resolveStaleHours(PrivateProbeFleetPolicyService $policyService): int
    {
        $option = $this->option('stale-hours');

        if (is_numeric($option)) {
            return max(0, min(8760, (int) $option));
        }

        return $policyService->staleDisableHours();
    }
}
