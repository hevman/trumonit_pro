<?php

namespace App\Console\Commands;

use App\Models\UrlSnapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class WidsBackfillStorageBytesCommand extends Command
{
    protected $signature = 'wids:backfill-storage-bytes {--dry-run}';

    protected $description = 'Backfill body/screenshot stored byte columns for existing snapshots';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $updatedSnapshots = 0;
        $updatedBodyBytes = 0;
        $updatedScreenshotBytes = 0;
        $missingFiles = 0;

        UrlSnapshot::query()
            ->where(function ($q): void {
                $q->where(function ($inner): void {
                    $inner->whereNotNull('body_storage_path')
                        ->where(function ($size): void {
                            $size->whereNull('body_bytes_stored')
                                ->orWhere('body_bytes_stored', 0);
                        });
                })->orWhere(function ($inner): void {
                    $inner->whereNotNull('screenshot_storage_path')
                        ->where(function ($size): void {
                            $size->whereNull('screenshot_bytes_stored')
                                ->orWhere('screenshot_bytes_stored', 0);
                        });
                });
            })
            ->orderBy('id')
            ->chunkById(500, function ($snapshots) use ($dryRun, &$updatedSnapshots, &$updatedBodyBytes, &$updatedScreenshotBytes, &$missingFiles): void {
                foreach ($snapshots as $snapshot) {
                    /** @var UrlSnapshot $snapshot */
                    $dirty = false;

                    $meta = is_array($snapshot->meta) ? $snapshot->meta : [];

                    if (is_string($snapshot->body_storage_path) && $snapshot->body_storage_path !== '' && (int) ($snapshot->body_bytes_stored ?? 0) <= 0) {
                        if (Storage::exists($snapshot->body_storage_path)) {
                            $size = (int) Storage::size($snapshot->body_storage_path);
                            $size = max(0, $size);

                            $snapshot->body_bytes_stored = $size;
                            $meta['body_bytes_stored'] = $size;

                            $dirty = true;
                            $updatedBodyBytes++;
                        } else {
                            $missingFiles++;
                        }
                    }

                    if (is_string($snapshot->screenshot_storage_path) && $snapshot->screenshot_storage_path !== '' && (int) ($snapshot->screenshot_bytes_stored ?? 0) <= 0) {
                        if (Storage::exists($snapshot->screenshot_storage_path)) {
                            $size = (int) Storage::size($snapshot->screenshot_storage_path);
                            $size = max(0, $size);

                            $snapshot->screenshot_bytes_stored = $size;

                            $meta['screenshot'] = is_array($meta['screenshot'] ?? null) ? $meta['screenshot'] : [];
                            $meta['screenshot']['bytes'] = $size;

                            $dirty = true;
                            $updatedScreenshotBytes++;
                        } else {
                            $missingFiles++;
                        }
                    }

                    if (! $dirty) {
                        continue;
                    }

                    $snapshot->meta = $meta;
                    $updatedSnapshots++;

                    if (! $dryRun) {
                        $snapshot->save();
                    }
                }
            });

        $this->info('Backfill finished'.($dryRun ? ' (dry-run)' : '').'.');
        $this->info('Updated snapshots: '.$updatedSnapshots);
        $this->info('Updated body bytes: '.$updatedBodyBytes);
        $this->info('Updated screenshot bytes: '.$updatedScreenshotBytes);
        $this->info('Missing files: '.$missingFiles);

        return self::SUCCESS;
    }
}
