<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\UrlSnapshot;
use App\Services\TenantQuotaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class WidsArchiveSnapshotsCommand extends Command
{
    protected $signature = 'wids:archive-snapshots {--tenant_id=} {--dry-run}';

    protected $description = 'Move old snapshot files to archive tier and mark snapshots as archived';

    public function handle(): int
    {
        if (! (bool) config('wids.storage_lifecycle.enabled', false)) {
            $this->info('Storage lifecycle is disabled.');

            return self::SUCCESS;
        }

        $tenantIdOption = $this->option('tenant_id');
        $tenantId = is_numeric($tenantIdOption) ? (int) $tenantIdOption : null;
        $dryRun = (bool) $this->option('dry-run');

        $archivePrefix = trim((string) config('wids.storage_lifecycle.archive_prefix', 'wids/archive'));
        $archivePrefix = $archivePrefix !== '' ? trim($archivePrefix, '/') : 'wids/archive';
        $chunkSize = max(50, min(1000, (int) config('wids.storage_lifecycle.chunk_size', 300)));

        $totals = [
            'archived_snapshots' => 0,
            'moved_files' => 0,
            'missing_files' => 0,
            'already_archived' => 0,
            'skipped_no_assets' => 0,
        ];

        Tenant::query()
            ->when($tenantId !== null, fn ($query) => $query->where('id', $tenantId))
            ->select(['id', 'name', 'slug', 'plan_tier'])
            ->orderBy('id')
            ->chunkById(100, function ($tenants) use ($dryRun, $archivePrefix, $chunkSize, &$totals): void {
                foreach ($tenants as $tenant) {
                    /** @var Tenant $tenant */
                    $archiveAfterDays = $this->archiveAfterDaysForTenant($tenant);

                    if ($archiveAfterDays <= 0) {
                        $this->line('Tenant '.$tenant->id.' skip: archive_after_days=0');
                        continue;
                    }

                    $cutoff = now()->subDays($archiveAfterDays);

                    $this->line(sprintf(
                        'Tenant %d (%s): archive cutoff %s (days=%d)',
                        $tenant->id,
                        $tenant->slug !== '' ? $tenant->slug : $tenant->name,
                        $cutoff->toDateTimeString(),
                        $archiveAfterDays
                    ));

                    UrlSnapshot::query()
                        ->join('monitored_urls', 'url_snapshots.monitored_url_id', '=', 'monitored_urls.id')
                        ->where('monitored_urls.tenant_id', $tenant->id)
                        ->where('url_snapshots.fetched_at', '<', $cutoff)
                        ->where(function ($query): void {
                            $query->whereNull('url_snapshots.storage_tier')
                                ->orWhere('url_snapshots.storage_tier', 'hot');
                        })
                        ->where(function ($query): void {
                            $query->whereNotNull('url_snapshots.body_storage_path')
                                ->orWhereNotNull('url_snapshots.screenshot_storage_path');
                        })
                        ->orderBy('url_snapshots.id')
                        ->select(['url_snapshots.*'])
                        ->chunkById($chunkSize, function ($snapshots) use ($dryRun, $archivePrefix, &$totals): void {
                            foreach ($snapshots as $snapshot) {
                                /** @var UrlSnapshot $snapshot */
                                $result = $this->archiveSnapshotAssets($snapshot, $archivePrefix, $dryRun);

                                $totals['archived_snapshots'] += $result['archived_snapshot'] ? 1 : 0;
                                $totals['moved_files'] += $result['moved_files'];
                                $totals['missing_files'] += $result['missing_files'];
                                $totals['already_archived'] += $result['already_archived'];
                                $totals['skipped_no_assets'] += $result['skipped_no_assets'];
                            }
                        }, 'url_snapshots.id', 'id');
                }
            });

        $this->info('Archive finished'.($dryRun ? ' (dry-run)' : '').'.');
        $this->info('Archived snapshots: '.(int) $totals['archived_snapshots']);
        $this->info('Moved files: '.(int) $totals['moved_files']);
        $this->info('Missing files: '.(int) $totals['missing_files']);
        $this->info('Already archived: '.(int) $totals['already_archived']);
        $this->info('Skipped no assets: '.(int) $totals['skipped_no_assets']);

        return self::SUCCESS;
    }

    private function archiveAfterDaysForTenant(Tenant $tenant): int
    {
        $limits = app(TenantQuotaService::class)->limitsForTenant($tenant);
        $tier = is_string($limits['tier'] ?? null) ? (string) $limits['tier'] : 'default';

        $byTier = config('wids.storage_lifecycle.archive_after_days_by_tier', []);
        $byTier = is_array($byTier) ? $byTier : [];

        $days = (int) ($byTier[$tier] ?? $byTier['default'] ?? 0);

        return max(0, min(3650, $days));
    }

    /**
     * @return array{
     *   archived_snapshot: bool,
     *   moved_files: int,
     *   missing_files: int,
     *   already_archived: int,
     *   skipped_no_assets: int
     * }
     */
    private function archiveSnapshotAssets(UrlSnapshot $snapshot, string $archivePrefix, bool $dryRun): array
    {
        $movedFiles = 0;
        $missingFiles = 0;
        $alreadyArchived = 0;
        $newBodyPath = $snapshot->body_storage_path;
        $newScreenshotPath = $snapshot->screenshot_storage_path;
        $hadAssets = false;

        foreach ([
            ['type' => 'body', 'path' => $snapshot->body_storage_path],
            ['type' => 'screenshot', 'path' => $snapshot->screenshot_storage_path],
        ] as $asset) {
            $type = (string) $asset['type'];
            $oldPath = is_string($asset['path']) ? trim($asset['path']) : '';

            if ($oldPath === '') {
                continue;
            }

            $hadAssets = true;

            if (str_starts_with($oldPath, $archivePrefix.'/')) {
                $alreadyArchived++;
                continue;
            }

            if (! Storage::exists($oldPath)) {
                $missingFiles++;
                continue;
            }

            $extension = pathinfo($oldPath, PATHINFO_EXTENSION);
            $extension = is_string($extension) && $extension !== '' ? '.'.$extension : '';

            $newPath = sprintf(
                '%s/%s/%d/%d%s',
                $archivePrefix,
                $type,
                (int) $snapshot->monitored_url_id,
                (int) $snapshot->id,
                $extension
            );

            if (! $dryRun) {
                if (Storage::exists($newPath)) {
                    Storage::delete($newPath);
                }

                $moved = Storage::move($oldPath, $newPath);

                if (! $moved && Storage::exists($oldPath)) {
                    $bytes = Storage::get($oldPath);

                    if (is_string($bytes) && $bytes !== '') {
                        Storage::put($newPath, $bytes);
                        Storage::delete($oldPath);
                        $moved = true;
                    }
                }

                if (! $moved) {
                    $missingFiles++;
                    continue;
                }
            }

            if ($type === 'body') {
                $newBodyPath = $newPath;
            } else {
                $newScreenshotPath = $newPath;
            }

            $movedFiles++;
        }

        if (! $hadAssets) {
            return [
                'archived_snapshot' => false,
                'moved_files' => 0,
                'missing_files' => 0,
                'already_archived' => 0,
                'skipped_no_assets' => 1,
            ];
        }

        $meta = is_array($snapshot->meta) ? $snapshot->meta : [];
        $meta['storage_tier'] = 'archive';
        $meta['archived_at'] = now()->toISOString();

        if (! $dryRun) {
            $snapshot->forceFill([
                'body_storage_path' => $newBodyPath,
                'screenshot_storage_path' => $newScreenshotPath,
                'storage_tier' => 'archive',
                'archived_at' => now(),
                'meta' => $meta,
            ])->save();
        }

        return [
            'archived_snapshot' => true,
            'moved_files' => $movedFiles,
            'missing_files' => $missingFiles,
            'already_archived' => $alreadyArchived,
            'skipped_no_assets' => 0,
        ];
    }
}
