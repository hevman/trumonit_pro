<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;

class OpsDrillArtifactService
{
    /**
     * @param  array<string, mixed>  $report
     * @return array{path: string, storage_path: string, filename: string}
     */
    public function store(array $report): array
    {
        $timestamp = now()->utc()->format('Ymd_His');
        $path = 'ops-drills/drill-'.$timestamp.'.json';
        $payload = (string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        Storage::disk('local')->put($path, $payload);

        return [
            'path' => $path,
            'storage_path' => 'storage/app/'.$path,
            'filename' => basename($path),
        ];
    }

    /**
     * @return list<array{
     *   filename: string,
     *   relative_path: string,
     *   storage_path: string,
     *   size_bytes: int,
     *   modified_at_iso: string
     * }>
     */
    public function list(int $limit = 20): array
    {
        try {
            $files = Storage::disk('local')->files('ops-drills');
        } catch (\Throwable) {
            return [];
        }
        $jsonFiles = array_values(array_filter($files, fn (string $path): bool => str_ends_with(strtolower($path), '.json')));

        usort($jsonFiles, function (string $a, string $b): int {
            try {
                return Storage::disk('local')->lastModified($b) <=> Storage::disk('local')->lastModified($a);
            } catch (\Throwable) {
                return 0;
            }
        });

        $rows = [];

        foreach (array_slice($jsonFiles, 0, max(1, $limit)) as $path) {
            try {
                $lastModified = (int) Storage::disk('local')->lastModified($path);
                $sizeBytes = (int) Storage::disk('local')->size($path);
            } catch (\Throwable) {
                continue;
            }

            $modifiedAt = CarbonImmutable::createFromTimestampUTC($lastModified);
            $rows[] = [
                'filename' => basename($path),
                'relative_path' => $path,
                'storage_path' => 'storage/app/'.$path,
                'size_bytes' => $sizeBytes,
                'modified_at_iso' => $modifiedAt->toISOString(),
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function read(string $relativePath): ?array
    {
        $relativePath = trim($relativePath);

        if ($relativePath === '' || str_contains($relativePath, '..')) {
            return null;
        }

        if (! str_starts_with($relativePath, 'ops-drills/') || ! str_ends_with(strtolower($relativePath), '.json')) {
            return null;
        }

        try {
            if (! Storage::disk('local')->exists($relativePath)) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        try {
            $payload = Storage::disk('local')->get($relativePath);
        } catch (\Throwable) {
            return null;
        }
        $decoded = json_decode($payload, true);

        return is_array($decoded) ? $decoded : null;
    }
}
