<?php

namespace App\Console\Commands;

use App\Services\OpsTuneEnvFileService;
use App\Services\OpsTuningPlanService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class WidsOpsTunePlanCommand extends Command
{
    protected $signature = 'wids:ops-tune-plan {--format=text : text|json} {--window-hours=} {--current-workers=} {--write-snippet= : Save env overrides snippet under storage/app/<path>} {--apply : Apply suggested env overrides to target env file} {--env-file=.env : Target env file path} {--rollback-from= : Restore target env file from backup path}';

    protected $description = 'Generate queue and ops tuning suggestions from drill + autoscaling profile';

    public function handle(): int
    {
        $format = strtolower(trim((string) $this->option('format')));
        $format = in_array($format, ['text', 'json'], true) ? $format : 'text';
        $envService = app(OpsTuneEnvFileService::class);
        $envFilePath = $this->resolveEnvFilePath($this->option('env-file'));
        $rollbackFrom = $this->resolveRollbackPath($this->option('rollback-from'));

        if ($rollbackFrom !== null) {
            $rollbackResult = $envService->rollbackEnvFile($envFilePath, $rollbackFrom);

            if ($format === 'json') {
                $this->line((string) json_encode([
                    'action' => 'rollback',
                    'ok' => true,
                    'rollback_result' => $rollbackResult,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            } else {
                $this->info('Ops tune rollback completed.');
                $this->line('Target env: '.(string) ($rollbackResult['env_file'] ?? $envFilePath));
                $this->line('Restored from: '.(string) ($rollbackResult['backup_path'] ?? $rollbackFrom));
            }

            return self::SUCCESS;
        }

        $windowHoursOption = $this->option('window-hours');
        $windowHours = is_numeric($windowHoursOption) ? (int) $windowHoursOption : null;

        $currentWorkersOption = $this->option('current-workers');
        $currentWorkers = is_numeric($currentWorkersOption) ? max(0, (int) $currentWorkersOption) : null;

        $report = app(OpsTuningPlanService::class)->build($windowHours, $currentWorkers);
        $applyResult = null;
        $envOverrides = is_array($report['env_overrides'] ?? null) ? $report['env_overrides'] : [];

        if ((bool) $this->option('apply')) {
            $applyResult = $envService->applyEnvOverrides($envFilePath, $envOverrides);
            $report['apply_result'] = $applyResult;
        }

        $writeSnippetPath = $this->resolveSnippetPath($this->option('write-snippet'));
        $savedSnippetPath = null;

        if ($writeSnippetPath !== null) {
            $savedSnippetPath = $this->writeSnippet($writeSnippetPath, $report);
            $report['snippet_path'] = $savedSnippetPath;
        }

        if ($format === 'json') {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->renderTextReport($report);
        }

        if ($format === 'text' && is_string($savedSnippetPath)) {
            $this->line('Snippet saved: '.$savedSnippetPath);
        }

        if ($format === 'text' && is_array($applyResult)) {
            $this->line('');
            $this->info('Apply result:');
            $this->line('  Env file: '.(string) ($applyResult['env_file'] ?? $envFilePath));
            $this->line('  Backup: '.(string) ($applyResult['backup_path'] ?? 'n/a'));
            $this->line('  Keys updated: '.(int) ($applyResult['applied_count'] ?? 0));
            $this->line('  Rollback: php artisan wids:ops-tune-plan --rollback-from="'.(string) ($applyResult['backup_path'] ?? '').'" --env-file="'.(string) ($applyResult['env_file'] ?? $envFilePath).'"');
        }

        return self::SUCCESS;
    }

    /**
     * @param  array{
     *   generated_at: string,
     *   overall_status: string,
     *   scale_action: ?string,
     *   suggested_scale_command: string,
     *   env_overrides: array<string, array{current: int, suggested: int, reason: string}>,
     *   notes: list<string>
     * }  $report
     */
    private function renderTextReport(array $report): void
    {
        $this->info('Ops tuning plan');
        $this->line('Generated at: '.(string) ($report['generated_at'] ?? now()->toISOString()));
        $this->line('Drill overall status: '.strtoupper((string) ($report['overall_status'] ?? 'pass')));
        $this->line('');

        $scaleAction = (string) ($report['scale_action'] ?? '');
        if ($scaleAction !== '') {
            $this->line('Scale action: '.$scaleAction);
        }
        $this->line('Suggested scale command: '.(string) ($report['suggested_scale_command'] ?? 'n/a'));
        $this->line('');

        $envOverrides = is_array($report['env_overrides'] ?? null) ? $report['env_overrides'] : [];

        if ($envOverrides === []) {
            $this->line('Env overrides: none');
        } else {
            $this->line('Env overrides:');
            foreach ($envOverrides as $env => $override) {
                $this->line(sprintf(
                    '  - %s: %d -> %d (%s)',
                    (string) $env,
                    (int) ($override['current'] ?? 0),
                    (int) ($override['suggested'] ?? 0),
                    (string) ($override['reason'] ?? '')
                ));
            }
        }

        $notes = is_array($report['notes'] ?? null) ? $report['notes'] : [];

        if ($notes !== []) {
            $this->line('');
            $this->line('Notes:');
            foreach ($notes as $index => $note) {
                $this->line(sprintf('  %d. %s', $index + 1, (string) $note));
            }
        }
    }

    /**
     * @param  mixed  $optionValue
     */
    private function resolveSnippetPath(mixed $optionValue): ?string
    {
        if (! is_string($optionValue)) {
            return null;
        }

        $path = trim($optionValue);

        if ($path === '') {
            $path = 'ops-drills/tune-'.now()->utc()->format('Ymd_His').'.env';
        }

        return $path;
    }

    /**
     * @param  array{env_overrides?: array<string, array{suggested?: int}>}  $report
     */
    private function writeSnippet(string $relativePath, array $report): string
    {
        $envOverrides = is_array($report['env_overrides'] ?? null) ? $report['env_overrides'] : [];

        $lines = [
            '# Generated by wids:ops-tune-plan at '.((string) ($report['generated_at'] ?? now()->toISOString())),
        ];

        foreach ($envOverrides as $key => $override) {
            $lines[] = (string) $key.'='.(int) ($override['suggested'] ?? 0);
        }

        $payload = implode("\n", $lines)."\n";
        Storage::disk('local')->put($relativePath, $payload);

        return 'storage/app/'.$relativePath;
    }

    /**
     * @param  mixed  $optionValue
     */
    private function resolveEnvFilePath(mixed $optionValue): string
    {
        $path = is_string($optionValue) ? trim($optionValue) : '';
        $path = $path !== '' ? $path : '.env';

        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        return base_path($path);
    }

    /**
     * @param  mixed  $optionValue
     */
    private function resolveRollbackPath(mixed $optionValue): ?string
    {
        if (! is_string($optionValue)) {
            return null;
        }

        $path = trim($optionValue);

        if ($path === '') {
            return null;
        }

        if ($this->isAbsolutePath($path)) {
            return $path;
        }

        return base_path($path);
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || str_starts_with($path, '\\') || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }
}
