<?php

namespace Tests\Unit;

use App\Models\MonitoredUrl;
use App\Services\ScanQueueRoutingService;
use Tests\TestCase;

class ScanQueueRoutingServiceTest extends TestCase
{
    public function test_manual_api_and_onboarding_use_high_priority_queue(): void
    {
        config()->set('wids.queue.scan.high_queue', 'wids-high');
        config()->set('wids.queue.scan.manual_queue', 'wids-high');
        config()->set('wids.queue.scan.api_queue', 'wids-high');
        config()->set('wids.queue.scan.onboarding_queue', 'wids-high');

        $service = app(ScanQueueRoutingService::class);

        $this->assertSame('wids-high', $service->queueForReason(null, ScanQueueRoutingService::REASON_MANUAL));
        $this->assertSame('wids-high', $service->queueForReason(null, ScanQueueRoutingService::REASON_API));
        $this->assertSame('wids-high', $service->queueForReason(null, ScanQueueRoutingService::REASON_ONBOARDING));
    }

    public function test_scheduled_queue_is_selected_from_lag_and_frequency(): void
    {
        config()->set('wids.queue.scan.high_queue', 'wids-high');
        config()->set('wids.queue.scan.default_queue', 'wids');
        config()->set('wids.queue.scan.low_queue', 'wids-low');
        config()->set('wids.queue.scan.high_priority_lag_seconds', 300);
        config()->set('wids.queue.scan.high_priority_failures', 2);
        config()->set('wids.queue.scan.low_priority_min_frequency_minutes', 60);
        config()->set('wids.queue.scan.low_priority_max_lag_seconds', 120);

        $service = app(ScanQueueRoutingService::class);

        $monitor = new MonitoredUrl([
            'frequency_minutes' => 120,
            'consecutive_failures' => 0,
        ]);

        $this->assertSame(
            'wids-low',
            $service->queueForReason($monitor, ScanQueueRoutingService::REASON_SCHEDULED, 60, 'lite')
        );

        $this->assertSame(
            'wids',
            $service->queueForReason($monitor, ScanQueueRoutingService::REASON_SCHEDULED, 60, 'enterprise')
        );

        $this->assertSame(
            'wids-high',
            $service->queueForReason($monitor, ScanQueueRoutingService::REASON_SCHEDULED, 600, 'lite')
        );
    }
}
