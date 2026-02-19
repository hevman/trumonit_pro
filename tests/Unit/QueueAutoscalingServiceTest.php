<?php

namespace Tests\Unit;

use App\Services\QueueAutoscalingService;
use Tests\TestCase;

class QueueAutoscalingServiceTest extends TestCase
{
    public function test_recommendation_uses_weights_and_target(): void
    {
        config()->set('wids.queue.scan.high_queue', 'wids-high');
        config()->set('wids.queue.scan.default_queue', 'wids');
        config()->set('wids.queue.scan.low_queue', 'wids-low');
        config()->set('wids.queue.autoscaling.high_queue_weight', 2.0);
        config()->set('wids.queue.autoscaling.default_queue_weight', 1.0);
        config()->set('wids.queue.autoscaling.low_queue_weight', 0.5);
        config()->set('wids.queue.autoscaling.target_ready_jobs_per_worker', 100);
        config()->set('wids.queue.autoscaling.min_workers', 1);
        config()->set('wids.queue.autoscaling.max_workers', 10);

        $service = app(QueueAutoscalingService::class);

        $plan = $service->recommendFromQueueMetrics([
            'wids-high' => ['ready' => 100],
            'wids' => ['ready' => 50],
            'wids-low' => ['ready' => 200],
        ]);

        $this->assertSame(350, $plan['ready_total']);
        $this->assertSame(350.0, $plan['weighted_ready_total']);
        $this->assertSame(4, $plan['recommended_workers']);
    }

    public function test_recommendation_clamps_between_min_and_max_workers(): void
    {
        config()->set('wids.queue.scan.high_queue', 'wids-high');
        config()->set('wids.queue.scan.default_queue', 'wids');
        config()->set('wids.queue.scan.low_queue', 'wids-low');
        config()->set('wids.queue.autoscaling.high_queue_weight', 2.0);
        config()->set('wids.queue.autoscaling.default_queue_weight', 1.0);
        config()->set('wids.queue.autoscaling.low_queue_weight', 0.5);
        config()->set('wids.queue.autoscaling.target_ready_jobs_per_worker', 100);
        config()->set('wids.queue.autoscaling.min_workers', 2);
        config()->set('wids.queue.autoscaling.max_workers', 5);

        $service = app(QueueAutoscalingService::class);

        $emptyPlan = $service->recommendFromQueueMetrics([
            'wids-high' => ['ready' => 0],
            'wids' => ['ready' => 0],
            'wids-low' => ['ready' => 0],
        ]);

        $this->assertSame(2, $emptyPlan['recommended_workers']);

        $busyPlan = $service->recommendFromQueueMetrics([
            'wids-high' => ['ready' => 600],
            'wids' => ['ready' => 400],
            'wids-low' => ['ready' => 400],
        ]);

        $this->assertSame(5, $busyPlan['recommended_workers']);
    }
}
