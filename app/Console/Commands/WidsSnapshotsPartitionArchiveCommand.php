<?php

namespace App\Console\Commands;

use App\Models\Incident;
use App\Models\MonitoredUrl;
use App\Models\UrlSnapshot;
use App\Models\UrlSnapshotArchive;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class WidsSnapshotsPartitionArchiveCommand extends Command
{
    protected $signature = 'wids:snapshots-partition-archive
        {--tenant_id= : Optional tenant scope}
        {--after-days= : Snapshot age threshold for archive partition sync}
        {--chunk= : Chunk size for processing}
        {--purge-source : Delete source snapshots after archival sync}
        {--dry-run : Compute actions without writing changes}
        {--format=text : text|json}
        {--force : Run even when partitioning toggle is disabled}';

    protected $description = 'Sync archived snapshots into partition/archive table with optional safe source purge';

    public function handle(): int
    {
        $enabled = (bool) config('wids.snapshot_partition.enabled', false);
        $force = (bool) $this->option('force');

        if (! $enabled && ! $force) {
            $this->info('Snapshot partition/archive sync is disabled.');

            return self::SUCCESS;
        }

        $tenantIdOption = $this->option('tenant_id');
        $tenantId = is_numeric($tenantIdOption) ? (int) $tenantIdOption : null;
        $afterDays = $this->resolveAfterDays();
        $chunkSize = $this->resolveChunkSize();
        $purgeSource = (bool) $this->option('purge-source')
            || (bool) config('wids.snapshot_partition.purge_source', false);
        $dryRun = (bool) $this->option('dry-run');
        $format = $this->resolveFormat();
        $cutoff = now()->subDays($afterDays);

        $baselineIds = MonitoredUrl::query()
            ->whereNotNull('baseline_snapshot_id')
            ->pluck('baseline_snapshot_id')
            ->filter()
            ->map(fn ($value): int => (int) $value)
            ->values()
            ->all();
        $baselineSet = array_fill_keys($baselineIds, true);

        $totals = [
            'scanned' => 0,
            'archived_inserted' => 0,
            'archived_updated' => 0,
            'would_archive_rows' => 0,
            'purged_source_rows' => 0,
            'would_purge_source_rows' => 0,
            'skipped_purge_baseline' => 0,
            'skipped_purge_incident_ref' => 0,
            'skipped_purge_latest' => 0,
            'errors' => 0,
        ];

        UrlSnapshot::query()
            ->join('monitored_urls', 'url_snapshots.monitored_url_id', '=', 'monitored_urls.id')
            ->where('url_snapshots.storage_tier', 'archive')
            ->where('url_snapshots.fetched_at', '<', $cutoff)
            ->when($tenantId !== null, fn ($query) => $query->where('monitored_urls.tenant_id', $tenantId))
            ->orderBy('url_snapshots.id')
            ->select(['url_snapshots.*', 'monitored_urls.tenant_id as tenant_scope_id'])
            ->chunkById($chunkSize, function ($snapshots) use (&$totals, $dryRun, $purgeSource, $baselineSet): void {
                $snapshotIds = $snapshots->pluck('id')->map(fn ($value): int => (int) $value)->values()->all();
                $monitorIds = $snapshots->pluck('monitored_url_id')->map(fn ($value): int => (int) $value)->unique()->values()->all();

                $incidentReferenceSet = $purgeSource ? $this->collectIncidentReferenceSet($snapshotIds) : [];
                $latestSnapshotIdByMonitor = $purgeSource ? $this->latestSnapshotIdByMonitor($monitorIds) : collect();

                foreach ($snapshots as $snapshot) {
                    /** @var UrlSnapshot $snapshot */
                    $totals['scanned']++;

                    $saved = $this->syncArchiveRow($snapshot, $dryRun);

                    if (! $saved['ok']) {
                        $totals['errors']++;
                        continue;
                    }

                    if ($dryRun) {
                        $totals['would_archive_rows']++;
                    } else {
                        $totals[$saved['created'] ? 'archived_inserted' : 'archived_updated']++;
                    }

                    if (! $purgeSource) {
                        continue;
                    }

                    $snapshotId = (int) $snapshot->id;

                    if (isset($baselineSet[$snapshotId])) {
                        $totals['skipped_purge_baseline']++;
                        continue;
                    }

                    if (isset($incidentReferenceSet[$snapshotId])) {
                        $totals['skipped_purge_incident_ref']++;
                        continue;
                    }

                    $latestSnapshotId = (int) ($latestSnapshotIdByMonitor->get((int) $snapshot->monitored_url_id) ?? 0);

                    if ($latestSnapshotId === $snapshotId) {
                        $totals['skipped_purge_latest']++;
                        continue;
                    }

                    if ($dryRun) {
                        $totals['would_purge_source_rows']++;
                        continue;
                    }

                    $snapshot->delete();
                    $totals['purged_source_rows']++;
                }
            }, 'url_snapshots.id', 'id');

        $payload = [
            'generated_at' => now()->toISOString(),
            'enabled' => $enabled,
            'forced' => $force,
            'tenant_id' => $tenantId,
            'after_days' => $afterDays,
            'cutoff' => $cutoff->toISOString(),
            'chunk_size' => $chunkSize,
            'purge_source' => $purgeSource,
            'dry_run' => $dryRun,
            'totals' => $totals,
        ];

        if ($format === 'json') {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info('Snapshot partition/archive sync summary'.($dryRun ? ' (dry-run)' : '').':');
            $this->line('  Scanned rows: '.(int) $totals['scanned']);
            $this->line('  Archived inserted: '.(int) $totals['archived_inserted']);
            $this->line('  Archived updated: '.(int) $totals['archived_updated']);

            if ($dryRun) {
                $this->line('  Would archive rows: '.(int) $totals['would_archive_rows']);
            }

            $this->line('  Purged source rows: '.(int) $totals['purged_source_rows']);

            if ($dryRun) {
                $this->line('  Would purge source rows: '.(int) $totals['would_purge_source_rows']);
            }

            $this->line('  Skipped purge (baseline): '.(int) $totals['skipped_purge_baseline']);
            $this->line('  Skipped purge (incident refs): '.(int) $totals['skipped_purge_incident_ref']);
            $this->line('  Skipped purge (latest snapshot): '.(int) $totals['skipped_purge_latest']);
            $this->line('  Errors: '.(int) $totals['errors']);
        }

        return self::SUCCESS;
    }

    private function resolveAfterDays(): int
    {
        $option = $this->option('after-days');

        if (is_numeric($option)) {
            return max(1, min(3650, (int) $option));
        }

        return max(1, min(3650, (int) config('wids.snapshot_partition.after_days', 30)));
    }

    private function resolveChunkSize(): int
    {
        $option = $this->option('chunk');

        if (is_numeric($option)) {
            return max(50, min(5000, (int) $option));
        }

        return max(50, min(5000, (int) config('wids.snapshot_partition.chunk_size', 400)));
    }

    private function resolveFormat(): string
    {
        $format = strtolower(trim((string) $this->option('format')));

        return in_array($format, ['text', 'json'], true) ? $format : 'text';
    }

    /**
     * @return array{ok: bool, created: bool}
     */
    private function syncArchiveRow(UrlSnapshot $snapshot, bool $dryRun): array
    {
        $fetchedAt = $snapshot->fetched_at;
        $archivedMonth = $fetchedAt?->format('Y-m') ?? now()->format('Y-m');
        $tenantId = (int) ($snapshot->tenant_scope_id ?? 0);

        $payload = [
            'request_url' => $snapshot->request_url,
            'final_url' => $snapshot->final_url,
            'headers' => is_array($snapshot->headers) ? $snapshot->headers : [],
            'meta' => is_array($snapshot->meta) ? $snapshot->meta : [],
            'error_message' => $snapshot->error_message,
        ];

        if ($dryRun) {
            return ['ok' => true, 'created' => false];
        }

        try {
            $archive = UrlSnapshotArchive::query()->firstOrNew([
                'original_snapshot_id' => (int) $snapshot->id,
            ]);

            $created = ! $archive->exists;

            $archive->forceFill([
                'tenant_id' => $tenantId,
                'monitored_url_id' => (int) $snapshot->monitored_url_id,
                'archived_month' => $archivedMonth,
                'fetched_at' => $fetchedAt ?? now(),
                'http_status' => $snapshot->http_status,
                'response_time_ms' => $snapshot->response_time_ms,
                'body_size' => $snapshot->body_size,
                'body_bytes_stored' => max(0, (int) $snapshot->body_bytes_stored),
                'screenshot_bytes_stored' => max(0, (int) $snapshot->screenshot_bytes_stored),
                'body_storage_path' => $snapshot->body_storage_path,
                'screenshot_storage_path' => $snapshot->screenshot_storage_path,
                'storage_tier' => is_string($snapshot->storage_tier) && trim($snapshot->storage_tier) !== '' ? (string) $snapshot->storage_tier : 'archive',
                'payload' => $payload,
            ])->save();

            return ['ok' => true, 'created' => $created];
        } catch (\Throwable) {
            return ['ok' => false, 'created' => false];
        }
    }

    /**
     * @param  list<int>  $snapshotIds
     * @return array<int, true>
     */
    private function collectIncidentReferenceSet(array $snapshotIds): array
    {
        if ($snapshotIds === []) {
            return [];
        }

        $rows = Incident::query()
            ->whereIn('previous_snapshot_id', $snapshotIds)
            ->orWhereIn('current_snapshot_id', $snapshotIds)
            ->get(['previous_snapshot_id', 'current_snapshot_id']);

        $set = [];

        foreach ($rows as $row) {
            if ($row->previous_snapshot_id !== null) {
                $set[(int) $row->previous_snapshot_id] = true;
            }

            if ($row->current_snapshot_id !== null) {
                $set[(int) $row->current_snapshot_id] = true;
            }
        }

        return $set;
    }

    /**
     * @param  list<int>  $monitoredUrlIds
     * @return Collection<int, int>
     */
    private function latestSnapshotIdByMonitor(array $monitoredUrlIds): Collection
    {
        if ($monitoredUrlIds === []) {
            return collect();
        }

        return UrlSnapshot::query()
            ->whereIn('monitored_url_id', $monitoredUrlIds)
            ->selectRaw('monitored_url_id, MAX(id) as latest_snapshot_id')
            ->groupBy('monitored_url_id')
            ->pluck('latest_snapshot_id', 'monitored_url_id')
            ->map(fn ($value): int => (int) $value);
    }
}
