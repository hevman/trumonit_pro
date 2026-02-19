<?php

namespace App\Console\Commands;

use App\Models\Incident;
use App\Models\MonitoredUrl;
use App\Models\Tenant;
use App\Models\UrlSnapshot;
use App\Services\TenantQuotaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class WidsCleanupCommand extends Command
{
    protected $signature = 'wids:cleanup {--dry-run}';

    protected $description = 'Cleanup old wIDS snapshots and stored bodies/screenshots (retention)';

    public function handle(): int
    {
        $enabled = (bool) config('wids.retention.enabled', true);

        if (! $enabled) {
            $this->info('Retention is disabled.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $quotaService = app(TenantQuotaService::class);
        $now = now();

        $baselineIds = MonitoredUrl::query()
            ->whereNotNull('baseline_snapshot_id')
            ->pluck('baseline_snapshot_id')
            ->filter()
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values()
            ->all();

        $baselineIdSet = array_fill_keys($baselineIds, true);

        $deletedSnapshots = 0;
        $deletedFiles = 0;
        $skippedReferenced = 0;
        $skippedBaseline = 0;

        Tenant::query()
            ->select(['id', 'name', 'slug', 'plan_tier'])
            ->orderBy('id')
            ->chunkById(100, function ($tenants) use (
                $quotaService,
                $now,
                $dryRun,
                &$deletedSnapshots,
                &$deletedFiles,
                &$skippedReferenced,
                &$skippedBaseline,
                $baselineIdSet
            ): void {
                foreach ($tenants as $tenant) {
                    /** @var Tenant $tenant */
                    $limits = $quotaService->limitsForTenant($tenant);
                    $retentionDays = $quotaService->normalizeDays((int) ($limits['retention_days'] ?? 30));
                    $cutoff = $now->copy()->subDays($retentionDays);

                    $label = $tenant->slug !== '' ? $tenant->slug : ('tenant-'.$tenant->id);
                    $this->info('Tenant '.$label.': cleanup cutoff '.$cutoff->toDateTimeString().' (days='.$retentionDays.')');

                    UrlSnapshot::query()
                        ->whereIn('monitored_url_id', MonitoredUrl::query()->select('id')->where('tenant_id', $tenant->id))
                        ->where('fetched_at', '<', $cutoff)
                        ->orderBy('id')
                        ->chunkById(500, function ($snapshots) use ($dryRun, &$deletedSnapshots, &$deletedFiles, &$skippedReferenced, &$skippedBaseline, $baselineIdSet): void {
                            $snapshotIds = $snapshots->pluck('id')->map(fn ($v) => (int) $v)->values()->all();
                            $candidateIds = array_values(array_filter($snapshotIds, fn (int $id) => ! isset($baselineIdSet[$id])));

                            $referencedIdSet = [];

                            if ($candidateIds !== []) {
                                $incidentRefs = Incident::query()
                                    ->whereIn('previous_snapshot_id', $candidateIds)
                                    ->orWhereIn('current_snapshot_id', $candidateIds)
                                    ->get(['previous_snapshot_id', 'current_snapshot_id']);

                                foreach ($incidentRefs as $incidentRef) {
                                    /** @var Incident $incidentRef */
                                    if ($incidentRef->previous_snapshot_id !== null) {
                                        $referencedIdSet[(int) $incidentRef->previous_snapshot_id] = true;
                                    }

                                    if ($incidentRef->current_snapshot_id !== null) {
                                        $referencedIdSet[(int) $incidentRef->current_snapshot_id] = true;
                                    }
                                }
                            }

                            foreach ($snapshots as $snapshot) {
                                /** @var UrlSnapshot $snapshot */
                                if (isset($baselineIdSet[$snapshot->id])) {
                                    $skippedBaseline++;
                                    continue;
                                }

                                if (isset($referencedIdSet[$snapshot->id])) {
                                    $skippedReferenced++;
                                    continue;
                                }

                                if (! $dryRun) {
                                    foreach ([$snapshot->body_storage_path, $snapshot->screenshot_storage_path] as $path) {
                                        if (is_string($path) && $path !== '' && Storage::exists($path)) {
                                            Storage::delete($path);
                                            $deletedFiles++;
                                        }
                                    }

                                    $snapshot->delete();
                                }

                                $deletedSnapshots++;
                            }
                        });
                }
            });

        $this->info('Deleted snapshots: '.$deletedSnapshots.($dryRun ? ' (dry-run)' : ''));
        $this->info('Deleted files: '.$deletedFiles.($dryRun ? ' (dry-run)' : ''));
        $this->info('Skipped baseline snapshots: '.$skippedBaseline);
        $this->info('Skipped referenced snapshots: '.$skippedReferenced);

        return self::SUCCESS;
    }
}
