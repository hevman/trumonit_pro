<?php

namespace Tests\Feature;

use App\Services\QueueAutoscalingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class WidsQueueAutoscaleApplyCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_dry_run_respects_step_guardrail_and_does_not_execute_runtime_command(): void
    {
        config()->set('wids.queue.autoscaling.auto_apply_enabled', true);
        config()->set('wids.queue.autoscaling.auto_apply_max_step', 2);
        config()->set('wids.queue.autoscaling.auto_apply_save_artifact', false);
        config()->set('wids.queue.autoscaling.auto_apply_command_template', 'docker compose up -d --scale queue={workers}');

        app()->instance(QueueAutoscalingService::class, new class extends QueueAutoscalingService
        {
            public function collectQueueMetricsFromRedis(): array
            {
                return [
                    'wids-high' => ['ready' => 800, 'delayed' => 10, 'reserved' => 3],
                ];
            }

            public function recommendFromQueueMetrics(array $queueMetrics): array
            {
                return [
                    'ready_total' => 800,
                    'weighted_ready_total' => 900.0,
                    'target_ready_jobs_per_worker' => 120,
                    'min_workers' => 1,
                    'max_workers' => 20,
                    'recommended_workers' => 9,
                ];
            }
        });

        Process::fake();

        $this->artisan('wids:queue-autoscale-apply --dry-run --current-workers=3')
            ->expectsOutputToContain('Target workers (guarded): 5')
            ->expectsOutputToContain('Scale action: scale_up')
            ->expectsOutputToContain('Skipped reason: dry_run')
            ->assertExitCode(0);

        Process::assertNothingRan();
    }

    public function test_command_applies_runtime_scale_and_updates_cache_state(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-12 14:30:00', 'UTC'));

        config()->set('wids.queue.autoscaling.auto_apply_enabled', true);
        config()->set('wids.queue.autoscaling.auto_apply_save_artifact', false);
        config()->set('wids.queue.autoscaling.auto_apply_command_template', 'docker compose up -d --scale queue={workers}');
        config()->set('wids.queue.autoscaling.current_workers', 2);

        app()->instance(QueueAutoscalingService::class, new class extends QueueAutoscalingService
        {
            public function collectQueueMetricsFromRedis(): array
            {
                return [
                    'wids' => ['ready' => 140, 'delayed' => 2, 'reserved' => 1],
                ];
            }

            public function recommendFromQueueMetrics(array $queueMetrics): array
            {
                return [
                    'ready_total' => 140,
                    'weighted_ready_total' => 140.0,
                    'target_ready_jobs_per_worker' => 120,
                    'min_workers' => 1,
                    'max_workers' => 20,
                    'recommended_workers' => 6,
                ];
            }
        });

        Process::fake([
            'docker compose up -d --scale queue=6' => Process::result(),
        ]);

        $this->artisan('wids:queue-autoscale-apply --max-step=20')
            ->expectsOutputToContain('Applied: yes')
            ->assertExitCode(0);

        Process::assertRan('docker compose up -d --scale queue=6');
        $this->assertSame(6, (int) Cache::get('wids:queue-autoscaling:auto:last_applied_workers'));
        $this->assertIsString(Cache::get('wids:queue-autoscaling:auto:last_applied_at'));
    }

    public function test_command_respects_cooldown_without_force(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-12 15:00:00', 'UTC'));

        config()->set('wids.queue.autoscaling.auto_apply_enabled', true);
        config()->set('wids.queue.autoscaling.auto_apply_save_artifact', false);
        config()->set('wids.queue.autoscaling.auto_apply_cooldown_minutes', 30);
        config()->set('wids.queue.autoscaling.auto_apply_command_template', 'docker compose up -d --scale queue={workers}');

        Cache::put('wids:queue-autoscaling:auto:last_applied_at', now()->toISOString(), now()->addHour());
        Cache::put('wids:queue-autoscaling:auto:last_applied_workers', 4, now()->addHour());

        app()->instance(QueueAutoscalingService::class, new class extends QueueAutoscalingService
        {
            public function collectQueueMetricsFromRedis(): array
            {
                return [
                    'wids' => ['ready' => 320, 'delayed' => 4, 'reserved' => 2],
                ];
            }

            public function recommendFromQueueMetrics(array $queueMetrics): array
            {
                return [
                    'ready_total' => 320,
                    'weighted_ready_total' => 320.0,
                    'target_ready_jobs_per_worker' => 120,
                    'min_workers' => 1,
                    'max_workers' => 20,
                    'recommended_workers' => 7,
                ];
            }
        });

        Process::fake();

        $this->artisan('wids:queue-autoscale-apply --current-workers=4')
            ->expectsOutputToContain('Skipped reason: cooldown_active')
            ->expectsOutputToContain('Applied: no')
            ->assertExitCode(0);

        Process::assertNothingRan();
    }
}
