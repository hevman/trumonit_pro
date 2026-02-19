<?php

namespace App\Console\Commands;

use App\Models\UrlSnapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class WidsStorageOrphanCleanupCommand extends Command
{
    protected $signature = 'wids:storage-orphan-cleanup
        {--dry-run : Compute orphan candidates without deleting files}
        {--prefix= : Archive prefix override (default from config)}
        {--grace-days= : Grace window before deleting orphan files}
        {--format=text : text|json}
        {--force : Run even when orphan cleanup toggle is disabled}';

    protected $description = 'Delete unreferenced archive files to control storage cost growth';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $format = $this->resolveFormat();
        $orphanCleanupEnabled = (bool) config('wids.storage_lifecycle.orphan_cleanup_enabled', false);

        if (! $force && ! $orphanCleanupEnabled) {
            $this->info('Storage orphan cleanup is disabled.');

            return self::SUCCESS;
        }

        $prefix = $this->resolvePrefix();
        $graceDays = $this->resolveGraceDays();
        $cutoff = now()->subDays($graceDays);
        $enforceRecencyGrace = $graceDays > 0;

        if ($prefix === '') {
            $this->error('Archive prefix is empty.');

            return self::FAILURE;
        }

        $referencedPaths = $this->collectReferencedArchivePaths($prefix);
        $archiveFiles = Storage::allFiles($prefix);

        $filesScanned = 0;
        $orphanCandidates = 0;
        $deletedFiles = 0;
        $wouldDeleteFiles = 0;
        $skippedRecent = 0;
        $errors = 0;

        foreach ($archiveFiles as $path) {
            $normalizedPath = $this->normalizePath((string) $path);

            if ($normalizedPath === '') {
                continue;
            }

            $filesScanned++;

            if (isset($referencedPaths[$normalizedPath])) {
                continue;
            }

            $orphanCandidates++;

            try {
                $lastModified = Carbon::createFromTimestampUTC((int) Storage::lastModified($normalizedPath));
            } catch (\Throwable) {
                $errors++;
                continue;
            }

            if ($enforceRecencyGrace && $lastModified->greaterThan($cutoff)) {
                $skippedRecent++;
                continue;
            }

            if ($dryRun) {
                $wouldDeleteFiles++;
                continue;
            }

            try {
                $deleted = Storage::delete($normalizedPath);
            } catch (\Throwable) {
                $deleted = false;
            }

            if ($deleted) {
                $deletedFiles++;
            } else {
                $errors++;
            }
        }

        $payload = [
            'generated_at' => now()->toISOString(),
            'prefix' => $prefix,
            'grace_days' => $graceDays,
            'cutoff' => $cutoff->toISOString(),
            'dry_run' => $dryRun,
            'forced' => $force,
            'orphan_cleanup_enabled' => $orphanCleanupEnabled,
            'referenced_files' => count($referencedPaths),
            'files_scanned' => $filesScanned,
            'orphan_candidates' => $orphanCandidates,
            'deleted_files' => $deletedFiles,
            'would_delete_files' => $wouldDeleteFiles,
            'skipped_recent' => $skippedRecent,
            'errors' => $errors,
        ];

        if ($format === 'json') {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info('Storage orphan cleanup summary'.($dryRun ? ' (dry-run)' : '').':');
            $this->line('  Prefix: '.$prefix);
            $this->line('  Grace days: '.$graceDays);
            $this->line('  Referenced files: '.count($referencedPaths));
            $this->line('  Files scanned: '.$filesScanned);
            $this->line('  Orphan candidates: '.$orphanCandidates);
            $this->line('  Deleted files: '.$deletedFiles);

            if ($dryRun) {
                $this->line('  Would delete files: '.$wouldDeleteFiles);
            }

            $this->line('  Skipped recent files: '.$skippedRecent);
            $this->line('  Errors: '.$errors);
        }

        return self::SUCCESS;
    }

    private function resolvePrefix(): string
    {
        $option = $this->option('prefix');

        if (is_string($option) && trim($option) !== '') {
            return trim(trim($option), '/');
        }

        $prefix = trim((string) config('wids.storage_lifecycle.archive_prefix', 'wids/archive'));

        return trim($prefix, '/');
    }

    private function resolveGraceDays(): int
    {
        $option = $this->option('grace-days');

        if (is_numeric($option)) {
            return max(0, min(3650, (int) $option));
        }

        return max(0, min(3650, (int) config('wids.storage_lifecycle.orphan_cleanup_grace_days', 3)));
    }

    private function resolveFormat(): string
    {
        $format = strtolower(trim((string) $this->option('format')));

        return in_array($format, ['text', 'json'], true) ? $format : 'text';
    }

    private function normalizePath(string $path): string
    {
        return trim(str_replace('\\', '/', trim($path)), '/');
    }

    /**
     * @return array<string, true>
     */
    private function collectReferencedArchivePaths(string $prefix): array
    {
        $prefixLike = $prefix.'/%';
        $pathSet = [];

        UrlSnapshot::query()
            ->where(function ($query) use ($prefixLike): void {
                $query->where('body_storage_path', 'like', $prefixLike)
                    ->orWhere('screenshot_storage_path', 'like', $prefixLike);
            })
            ->select(['id', 'body_storage_path', 'screenshot_storage_path'])
            ->orderBy('id')
            ->chunkById(1000, function ($snapshots) use (&$pathSet): void {
                foreach ($snapshots as $snapshot) {
                    /** @var UrlSnapshot $snapshot */
                    foreach ([$snapshot->body_storage_path, $snapshot->screenshot_storage_path] as $path) {
                        if (! is_string($path) || trim($path) === '') {
                            continue;
                        }

                        $normalized = $this->normalizePath($path);

                        if ($normalized !== '') {
                            $pathSet[$normalized] = true;
                        }
                    }
                }
            });

        return $pathSet;
    }
}
