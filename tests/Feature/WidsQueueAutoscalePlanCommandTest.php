<?php

namespace Tests\Feature;

use App\Services\QueueAutoscalingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WidsQueueAutoscalePlanCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_outputs_text_plan(): void
    {
        app()->instance(QueueAutoscalingService::class, new class extends QueueAutoscalingService
        {
            public function collectQueueMetricsFromRedis(): array
            {
                return [
                    'wids-high' => ['ready' => 80, 'delayed' => 4, 'reserved' => 2],
                    'wids' => ['ready' => 120, 'delayed' => 3, 'reserved' => 1],
                ];
            }

            public function recommendFromQueueMetrics(array $queueMetrics): array
            {
                return [
                    'ready_total' => 200,
                    'weighted_ready_total' => 260.0,
                    'target_ready_jobs_per_worker' => 120,
                    'min_workers' => 1,
                    'max_workers' => 12,
                    'recommended_workers' => 3,
                ];
            }
        });

        $this->artisan('wids:queue-autoscale-plan --current-workers=2')
            ->expectsOutputToContain('Recommended workers: 3')
            ->expectsOutputToContain('scale_up')
            ->assertExitCode(0);
    }

    public function test_command_outputs_json_plan(): void
    {
        app()->instance(QueueAutoscalingService::class, new class extends QueueAutoscalingService
        {
            public function collectQueueMetricsFromRedis(): array
            {
                return [
                    'wids' => ['ready' => 10, 'delayed' => 0, 'reserved' => 0],
                ];
            }

            public function recommendFromQueueMetrics(array $queueMetrics): array
            {
                return [
                    'ready_total' => 10,
                    'weighted_ready_total' => 10.0,
                    'target_ready_jobs_per_worker' => 120,
                    'min_workers' => 1,
                    'max_workers' => 12,
                    'recommended_workers' => 1,
                ];
            }
        });

        $this->artisan('wids:queue-autoscale-plan --format=json')
            ->expectsOutputToContain('"recommended_workers": 1')
            ->assertExitCode(0);
    }
}
