<?php

namespace App\Console\Commands;

use App\Services\QueueAutoscalingService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class WidsQueueAutoscaleApplyCommand extends Command
{
    protected $signature = 'wids:queue-autoscale-apply
        {--current-workers= : Current queue worker replicas}
        {--cooldown-minutes= : Min minutes between apply actions}
        {--max-step= : Max worker delta per apply}
        {--command-template= : Runtime scale command template, use {workers}}
        {--format=text : text|json}
        {--save : Save JSON artifact under storage/app/ops-drills/queue-autoscale}
        {--dry-run : Compute target and command, do not execute}
        {--force : Ignore auto-apply toggle and cooldown checks}';

    protected $description = 'Apply queue autoscaling recommendation with guardrails and optional runtime command execution';

    private const LAST_APPLIED_AT_CACHE_KEY = 'wids:queue-autoscaling:auto:last_applied_at';

    private const LAST_APPLIED_WORKERS_CACHE_KEY = 'wids:queue-autoscaling:auto:last_applied_workers';

    public function handle(): int
    {
        $format = strtolower(trim((string) $this->option('format')));
        $format = in_array($format, ['text', 'json'], true) ? $format : 'text';
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $saveArtifact = (bool) $this->option('save')
            || (bool) config('wids.queue.autoscaling.auto_apply_save_artifact', true);

        $autoscalingService = app(QueueAutoscalingService::class);

        if (! $autoscalingService->isEnabled()) {
            $this->warn('Queue autoscaling is disabled (WIDS_QUEUE_AUTOSCALING_ENABLED=false).');

            return self::SUCCESS;
        }

        $queueMetrics = $autoscalingService->collectQueueMetricsFromRedis();
        $plan = $autoscalingService->recommendFromQueueMetrics($queueMetrics);
        $recommendedWorkers = max(1, (int) ($plan['recommended_workers'] ?? 1));

        $currentWorkers = $this->resolveCurrentWorkers();
        $maxStep = $this->resolveMaxStep();
        $targetWorkers = $this->resolveTargetWorkers($currentWorkers, $recommendedWorkers, $maxStep);
        $scaleAction = $this->resolveScaleAction($currentWorkers, $targetWorkers);
        $cooldownMinutes = $this->resolveCooldownMinutes();
        $commandTemplate = $this->resolveCommandTemplate();
        $runtimeCommand = $this->buildRuntimeCommand($commandTemplate, $targetWorkers);
        $autoApplyEnabled = (bool) config('wids.queue.autoscaling.auto_apply_enabled', false);

        $applied = false;
        $skippedReason = null;
        $processOutput = null;
        $processError = null;
        $processExitCode = null;

        if ($scaleAction === 'keep') {
            $skippedReason = 'no_change';
        } elseif (! $dryRun && ! $force && ! $autoApplyEnabled) {
            $skippedReason = 'auto_apply_disabled';
        } elseif ($runtimeCommand === '') {
            $skippedReason = 'command_template_missing';
        } elseif (! $force && $this->cooldownActive($cooldownMinutes)) {
            $skippedReason = 'cooldown_active';
        } elseif ($dryRun) {
            $skippedReason = 'dry_run';
        } else {
            $result = Process::timeout(120)->run($runtimeCommand);
            $processOutput = trim($result->output());
            $processError = trim($result->errorOutput());
            $processExitCode = $result->exitCode();

            if ($result->successful()) {
                $applied = true;
                Cache::put(self::LAST_APPLIED_AT_CACHE_KEY, now()->toISOString(), now()->addDays(30));
                Cache::put(self::LAST_APPLIED_WORKERS_CACHE_KEY, $targetWorkers, now()->addDays(30));
            } else {
                $skippedReason = 'command_failed';
            }
        }

        $payload = [
            'generated_at' => now()->toISOString(),
            'queue_metrics' => $queueMetrics,
            'plan' => $plan,
            'current_workers' => $currentWorkers,
            'max_step' => $maxStep,
            'recommended_workers_raw' => $recommendedWorkers,
            'target_workers' => $targetWorkers,
            'scale_action' => $scaleAction,
            'cooldown_minutes' => $cooldownMinutes,
            'auto_apply_enabled' => $autoApplyEnabled,
            'dry_run' => $dryRun,
            'forced' => $force,
            'runtime_command' => $runtimeCommand,
            'applied' => $applied,
            'skipped_reason' => $skippedReason,
            'process_exit_code' => $processExitCode,
            'process_output' => $processOutput,
            'process_error' => $processError,
        ];

        if ($saveArtifact) {
            $payload['artifact_path'] = $this->storeArtifact($payload);
        }

        if ($format === 'json') {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->info('Queue autoscale apply summary:');
            $this->line('  Current workers: '.$currentWorkers);
            $this->line('  Recommended workers (raw): '.$recommendedWorkers);
            $this->line('  Target workers (guarded): '.$targetWorkers);
            $this->line('  Scale action: '.$scaleAction);
            $this->line('  Command: '.($runtimeCommand !== '' ? $runtimeCommand : 'n/a'));
            $this->line('  Applied: '.($applied ? 'yes' : 'no'));

            if ($skippedReason !== null) {
                $this->line('  Skipped reason: '.$skippedReason);
            }

            if (is_string($payload['artifact_path'] ?? null) && trim((string) $payload['artifact_path']) !== '') {
                $this->line('  Artifact: '.(string) $payload['artifact_path']);
            }
        }

        if (! $applied && $skippedReason === 'command_failed') {
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function resolveCurrentWorkers(): int
    {
        $option = $this->option('current-workers');

        if (is_numeric($option)) {
            return max(1, min(10_000, (int) $option));
        }

        $cached = Cache::get(self::LAST_APPLIED_WORKERS_CACHE_KEY);

        if (is_numeric($cached)) {
            return max(1, min(10_000, (int) $cached));
        }

        return max(1, min(10_000, (int) config('wids.queue.autoscaling.current_workers', 1)));
    }

    private function resolveCooldownMinutes(): int
    {
        $option = $this->option('cooldown-minutes');

        if (is_numeric($option)) {
            return max(0, min(10_080, (int) $option));
        }

        return max(0, min(10_080, (int) config('wids.queue.autoscaling.auto_apply_cooldown_minutes', 15)));
    }

    private function resolveMaxStep(): int
    {
        $option = $this->option('max-step');

        if (is_numeric($option)) {
            return max(1, min(1000, (int) $option));
        }

        return max(1, min(1000, (int) config('wids.queue.autoscaling.auto_apply_max_step', 2)));
    }

    private function resolveCommandTemplate(): string
    {
        $option = $this->option('command-template');

        if (is_string($option) && trim($option) !== '') {
            return trim($option);
        }

        return trim((string) config('wids.queue.autoscaling.auto_apply_command_template', 'docker compose up -d --scale queue={workers}'));
    }

    private function resolveTargetWorkers(int $currentWorkers, int $recommendedWorkers, int $maxStep): int
    {
        $diff = $recommendedWorkers - $currentWorkers;

        if ($diff > 0) {
            return $currentWorkers + min($maxStep, $diff);
        }

        if ($diff < 0) {
            return max(1, $currentWorkers - min($maxStep, abs($diff)));
        }

        return $currentWorkers;
    }

    private function resolveScaleAction(int $currentWorkers, int $targetWorkers): string
    {
        if ($targetWorkers > $currentWorkers) {
            return 'scale_up';
        }

        if ($targetWorkers < $currentWorkers) {
            return 'scale_down';
        }

        return 'keep';
    }

    private function buildRuntimeCommand(string $template, int $targetWorkers): string
    {
        $template = trim($template);

        if ($template === '') {
            return '';
        }

        return str_replace('{workers}', (string) $targetWorkers, $template);
    }

    private function cooldownActive(int $cooldownMinutes): bool
    {
        if ($cooldownMinutes <= 0) {
            return false;
        }

        $lastAppliedAt = Cache::get(self::LAST_APPLIED_AT_CACHE_KEY);

        if (! is_string($lastAppliedAt) || trim($lastAppliedAt) === '') {
            return false;
        }

        try {
            $timestamp = Carbon::parse($lastAppliedAt);
        } catch (\Throwable) {
            return false;
        }

        return $timestamp->addMinutes($cooldownMinutes)->isFuture();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function storeArtifact(array $payload): string
    {
        $timestamp = now()->utc()->format('Ymd_His_u');
        $path = 'ops-drills/queue-autoscale/auto-'.$timestamp.'.json';
        Storage::disk('local')->put($path, (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return 'storage/app/'.$path;
    }
}

