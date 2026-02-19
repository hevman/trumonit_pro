<?php

namespace Tests\Feature;

use App\Services\QueueAutoscalingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpsQueueAutoscalingMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_metrics_endpoint_includes_queue_autoscaling_metrics_when_available(): void
    {
        config()->set('ops.metrics.enabled', true);
        config()->set('ops.metrics.token', 'secret-token');

        app()->instance(QueueAutoscalingService::class, new class extends QueueAutoscalingService
        {
            public function collectQueueMetricsFromRedis(): array
            {
                return [
                    'wids-high' => ['ready' => 50, 'delayed' => 1, 'reserved' => 0],
                    'wids' => ['ready' => 40, 'delayed' => 0, 'reserved' => 0],
                    'wids-low' => ['ready' => 20, 'delayed' => 0, 'reserved' => 0],
                ];
            }

            public function recommendFromQueueMetrics(array $queueMetrics): array
            {
                return [
                    'ready_total' => 110,
                    'weighted_ready_total' => 140.0,
                    'target_ready_jobs_per_worker' => 120,
                    'min_workers' => 1,
                    'max_workers' => 12,
                    'recommended_workers' => 2,
                ];
            }
        });

        $response = $this
            ->withHeader('Authorization', 'Bearer secret-token')
            ->get('/ops/metrics');

        $response->assertOk();
        $response->assertSee('TruMonit_queue_ready_jobs_weighted_total 140');
        $response->assertSee('TruMonit_queue_workers_recommended 2');
        $response->assertSee('TruMonit_queue_workers_max_configured 12');
    }
}
