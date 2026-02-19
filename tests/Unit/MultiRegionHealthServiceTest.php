<?php

namespace Tests\Unit;

use App\Services\MultiRegionHealthService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MultiRegionHealthServiceTest extends TestCase
{
    public function test_apply_region_policy_excludes_unhealthy_regions(): void
    {
        Cache::flush();

        config()->set('wids.multi_region.health_controls.enabled', true);
        config()->set('wids.multi_region.health_controls.min_samples', 2);
        config()->set('wids.multi_region.health_controls.max_failure_ratio', 0.5);
        config()->set('wids.multi_region.health_controls.exclude_unhealthy_regions', true);
        config()->set('wids.multi_region.health_controls.min_regions_after_filter', 2);
        config()->set('wids.multi_region.health_controls.strict_min_regions', false);

        $service = app(MultiRegionHealthService::class);
        $service->recordProbeResult('eu-west', true);
        $service->recordProbeResult('eu-west', true);
        $service->recordProbeResult('ap-southeast', false);
        $service->recordProbeResult('ap-southeast', false);

        $policy = $service->applyRegionPolicy(['us-east', 'eu-west', 'ap-southeast']);

        $this->assertSame(['us-east', 'ap-southeast'], $policy['selected_regions']);
        $this->assertFalse((bool) $policy['policy_relaxed']);
        $this->assertFalse((bool) $policy['strict_blocked']);
        $this->assertSame('eu-west', data_get($policy, 'excluded_regions.0.region'));
        $this->assertTrue((bool) data_get($policy, 'region_health.eu-west.unhealthy'));
    }

    public function test_apply_region_policy_relaxes_when_strict_mode_disabled(): void
    {
        Cache::flush();

        config()->set('wids.multi_region.health_controls.enabled', true);
        config()->set('wids.multi_region.health_controls.min_samples', 1);
        config()->set('wids.multi_region.health_controls.max_failure_ratio', 0.2);
        config()->set('wids.multi_region.health_controls.exclude_unhealthy_regions', true);
        config()->set('wids.multi_region.health_controls.min_regions_after_filter', 3);
        config()->set('wids.multi_region.health_controls.strict_min_regions', false);

        $service = app(MultiRegionHealthService::class);
        $service->recordProbeResult('eu-west', true);
        $service->recordProbeResult('ap-southeast', true);

        $policy = $service->applyRegionPolicy(['us-east', 'eu-west', 'ap-southeast']);

        $this->assertTrue((bool) $policy['policy_relaxed']);
        $this->assertFalse((bool) $policy['strict_blocked']);
        $this->assertSame(['us-east', 'eu-west', 'ap-southeast'], $policy['selected_regions']);
        $this->assertSame([], $policy['excluded_regions']);
    }

    public function test_apply_region_policy_strictly_blocks_when_min_region_not_met(): void
    {
        Cache::flush();

        config()->set('wids.multi_region.health_controls.enabled', true);
        config()->set('wids.multi_region.health_controls.min_samples', 1);
        config()->set('wids.multi_region.health_controls.max_failure_ratio', 0.2);
        config()->set('wids.multi_region.health_controls.exclude_unhealthy_regions', true);
        config()->set('wids.multi_region.health_controls.min_regions_after_filter', 2);
        config()->set('wids.multi_region.health_controls.strict_min_regions', true);

        $service = app(MultiRegionHealthService::class);
        $service->recordProbeResult('eu-west', true);
        $service->recordProbeResult('ap-southeast', true);

        $policy = $service->applyRegionPolicy(['us-east', 'eu-west', 'ap-southeast']);

        $this->assertFalse((bool) $policy['policy_relaxed']);
        $this->assertTrue((bool) $policy['strict_blocked']);
        $this->assertSame(['us-east'], $policy['selected_regions']);
        $this->assertCount(2, $policy['excluded_regions']);
    }
}
