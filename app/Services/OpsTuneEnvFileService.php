<?php

namespace App\Services;

use Carbon\CarbonImmutable;

class OpsTuneEnvFileService
{
    /**
     * @param  array<string, array{suggested?: int|float|string}>  $envOverrides
     * @return array{
     *   env_file: string,
     *   backup_path: string,
     *   backup_id: string,
     *   applied_count: int,
     *   applied_keys: list<string>
     * }
     */
    public function applyEnvOverrides(string $envFilePath, array $envOverrides): array
    {
        if ($envOverrides === []) {
            return [
                'env_file' => $envFilePath,
                'backup_path' => '',
                'backup_id' => '',
                'applied_count' => 0,
                'applied_keys' => [],
            ];
        }

        if (! file_exists($envFilePath)) {
            throw new \RuntimeException('Target env file not found: '.$envFilePath);
        }

        $original = file_get_contents($envFilePath);

        if (! is_string($original)) {
            throw new \RuntimeException('Unable to read env file: '.$envFilePath);
        }

        $backupPath = $this->createBackup($original);
        $backupId = basename($backupPath);

        $eol = str_contains($original, "\r\n") ? "\r\n" : "\n";
        $lines = preg_split("/\r\n|\n|\r/", $original);
        $lines = is_array($lines) ? $lines : [];
        $replaced = [];
        $normalizedOverrides = [];

        foreach ($envOverrides as $key => $override) {
            $normalizedKey = trim((string) $key);

            if ($normalizedKey === '') {
                continue;
            }

            $normalizedOverrides[$normalizedKey] = (string) (int) ($override['suggested'] ?? 0);
            $replaced[$normalizedKey] = false;
        }

        foreach ($lines as $index => $line) {
            foreach ($normalizedOverrides as $key => $value) {
                if ($replaced[$key] === true) {
                    continue;
                }

                if (preg_match('/^\s*(?:export\s+)?'.preg_quote($key, '/').'\s*=/', (string) $line) === 1) {
                    $lines[$index] = $key.'='.$value;
                    $replaced[$key] = true;
                }
            }
        }

        foreach ($replaced as $key => $wasReplaced) {
            if ($wasReplaced === false) {
                $lines[] = $key.'='.$normalizedOverrides[$key];
                $replaced[$key] = true;
            }
        }

        $updated = implode($eol, $lines);

        if ($updated === '' || ! str_ends_with($updated, $eol)) {
            $updated .= $eol;
        }

        try {
            if (file_put_contents($envFilePath, $updated) === false) {
                throw new \RuntimeException('Unable to write env file: '.$envFilePath);
            }
        } catch (\Throwable $e) {
            @file_put_contents($envFilePath, $original);
            throw $e;
        }

        $appliedKeys = array_values(array_keys($normalizedOverrides));

        return [
            'env_file' => $envFilePath,
            'backup_path' => $backupPath,
            'backup_id' => $backupId,
            'applied_count' => count($appliedKeys),
            'applied_keys' => $appliedKeys,
        ];
    }

    /**
     * @return array{env_file: string, backup_path: string, backup_id: string}
     */
    public function rollbackEnvFile(string $envFilePath, string $backupPath): array
    {
        if (! file_exists($backupPath)) {
            throw new \RuntimeException('Rollback backup file not found: '.$backupPath);
        }

        $backupContent = file_get_contents($backupPath);

        if (! is_string($backupContent)) {
            throw new \RuntimeException('Unable to read rollback backup file: '.$backupPath);
        }

        if (file_put_contents($envFilePath, $backupContent) === false) {
            throw new \RuntimeException('Unable to restore env file: '.$envFilePath);
        }

        return [
            'env_file' => $envFilePath,
            'backup_path' => $backupPath,
            'backup_id' => basename($backupPath),
        ];
    }

    public function resolveConfiguredEnvFilePath(): string
    {
        $configuredPath = trim((string) config('ops.tuning.env_file', '.env'));
        $configuredPath = $configuredPath !== '' ? $configuredPath : '.env';

        if ($this->isAbsolutePath($configuredPath)) {
            return $configuredPath;
        }

        return base_path($configuredPath);
    }

    /**
     * @return list<array{
     *   id: string,
     *   path: string,
     *   size_bytes: int,
     *   modified_at_iso: string
     * }>
     */
    public function listBackups(int $limit = 20): array
    {
        $backupDir = $this->backupDirectoryPath();

        if (! is_dir($backupDir)) {
            return [];
        }

        $files = glob($backupDir.DIRECTORY_SEPARATOR.'*.bak');
        $files = is_array($files) ? $files : [];

        usort($files, function (string $a, string $b): int {
            return (filemtime($b) ?: 0) <=> (filemtime($a) ?: 0);
        });

        $rows = [];

        foreach (array_slice($files, 0, max(1, $limit)) as $file) {
            $modifiedAt = CarbonImmutable::createFromTimestampUTC((int) (filemtime($file) ?: time()));
            $rows[] = [
                'id' => basename($file),
                'path' => $file,
                'size_bytes' => (int) (filesize($file) ?: 0),
                'modified_at_iso' => $modifiedAt->toISOString(),
            ];
        }

        return $rows;
    }

    public function resolveBackupPathById(string $backupId): ?string
    {
        $backupId = trim($backupId);

        if ($backupId === '' || str_contains($backupId, '/') || str_contains($backupId, '\\')) {
            return null;
        }

        if (! str_ends_with(strtolower($backupId), '.bak')) {
            return null;
        }

        $backupPath = $this->backupDirectoryPath().DIRECTORY_SEPARATOR.$backupId;

        return file_exists($backupPath) ? $backupPath : null;
    }

    private function backupDirectoryPath(): string
    {
        $configuredPath = trim((string) config('ops.tuning.backup_dir', 'ops-drills/env-backups'));
        $configuredPath = $configuredPath !== '' ? $configuredPath : 'ops-drills/env-backups';

        if ($this->isAbsolutePath($configuredPath)) {
            return $configuredPath;
        }

        return storage_path('app/'.ltrim($configuredPath, '/\\'));
    }

    private function createBackup(string $payload): string
    {
        $backupDir = $this->backupDirectoryPath();

        if (! is_dir($backupDir) && ! mkdir($backupDir, 0775, true) && ! is_dir($backupDir)) {
            throw new \RuntimeException('Unable to create backup directory: '.$backupDir);
        }

        $backupPath = $backupDir.'/env-'.now()->utc()->format('Ymd_His_u').'.bak';

        if (file_put_contents($backupPath, $payload) === false) {
            throw new \RuntimeException('Unable to create env backup: '.$backupPath);
        }

        return $backupPath;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || str_starts_with($path, '\\') || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }
}
