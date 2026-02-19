<?php

namespace Tests\Unit;

use App\Models\MonitoredUrl;
use App\Services\MultiRegionHealthService;
use App\Services\MultiRegionOutageConfirmationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MultiRegionOutageConfirmationServiceTest extends TestCase
{
    public function test_returns_confirmed_when_feature_disabled(): void
    {
        config()->set('wids.multi_region.enabled', false);

        $monitor = new MonitoredUrl([
            'url' => 'https://1.1.1.1',
            'expected_status_min' => 200,
            'expected_status_max' => 299,
        ]);

        $result = app(MultiRegionOutageConfirmationService::class)->confirmDownState($monitor, 'fetch_error');

        $this->assertFalse($result['enabled']);
        $this->assertFalse($result['performed']);
        $this->assertTrue($result['confirmed']);
    }

    public function test_quorum_not_met_suppresses_down_state(): void
    {
        config()->set('wids.multi_region.enabled', true);
        config()->set('wids.multi_region.regions', ['us-east', 'eu-west', 'ap-southeast']);
        config()->set('wids.multi_region.max_regions_by_tier', [
            'default' => 3,
        ]);
        config()->set('wids.multi_region.quorum_failures', 2);

        Http::fake([
            '*' => Http::response('ok', 200),
        ]);

        $monitor = new MonitoredUrl([
            'url' => 'https://1.1.1.1',
            'expected_status_min' => 200,
            'expected_status_max' => 299,
        ]);

        $result = app(MultiRegionOutageConfirmationService::class)->confirmDownState($monitor, 'fetch_error');

        $this->assertTrue($result['enabled']);
        $this->assertTrue($result['performed']);
        $this->assertFalse($result['confirmed']);
        $this->assertSame('quorum_not_met', $result['reason']);
        $this->assertCount(1, $result['failed_regions']);
    }

    public function test_quorum_met_confirms_down_state(): void
    {
        config()->set('wids.multi_region.enabled', true);
        config()->set('wids.multi_region.regions', ['us-east', 'eu-west', 'ap-southeast']);
        config()->set('wids.multi_region.max_regions_by_tier', [
            'default' => 3,
        ]);
        config()->set('wids.multi_region.quorum_failures', 2);

        Http::fake(function ($request) {
            $region = is_array($request->header('X-Wids-Probe-Region')) ? (string) ($request->header('X-Wids-Probe-Region')[0] ?? '') : '';

            if ($region === 'eu-west') {
                return Http::response('err', 500);
            }

            return Http::response('ok', 200);
        });

        $monitor = new MonitoredUrl([
            'url' => 'https://1.1.1.1',
            'expected_status_min' => 200,
            'expected_status_max' => 299,
        ]);

        $result = app(MultiRegionOutageConfirmationService::class)->confirmDownState($monitor, 'unexpected_status');

        $this->assertTrue($result['enabled']);
        $this->assertTrue($result['performed']);
        $this->assertTrue($result['confirmed']);
        $this->assertSame('quorum_met', $result['reason']);
        $this->assertGreaterThanOrEqual(2, count($result['failed_regions']));
    }

    public function test_health_policy_can_exclude_unhealthy_region_from_confirmation_set(): void
    {
        Cache::flush();

        config()->set('wids.multi_region.enabled', true);
        config()->set('wids.multi_region.regions', ['us-east', 'eu-west', 'ap-southeast']);
        config()->set('wids.multi_region.max_regions_by_tier', [
            'default' => 3,
        ]);
        config()->set('wids.multi_region.quorum_failures', 2);
        config()->set('wids.multi_region.health_controls.enabled', true);
        config()->set('wids.multi_region.health_controls.min_samples', 3);
        config()->set('wids.multi_region.health_controls.max_failure_ratio', 0.6);
        config()->set('wids.multi_region.health_controls.exclude_unhealthy_regions', true);
        config()->set('wids.multi_region.health_controls.strict_min_regions', false);

        $healthService = app(MultiRegionHealthService::class);
        $healthService->recordProbeResult('eu-west', true);
        $healthService->recordProbeResult('eu-west', true);
        $healthService->recordProbeResult('eu-west', true);
        $healthService->recordProbeResult('ap-southeast', false);
        $healthService->recordProbeResult('ap-southeast', false);
        $healthService->recordProbeResult('ap-southeast', false);

        Http::fake([
            '*' => Http::response('ok', 200),
        ]);

        $monitor = new MonitoredUrl([
            'url' => 'https://1.1.1.1',
            'expected_status_min' => 200,
            'expected_status_max' => 299,
        ]);

        $result = app(MultiRegionOutageConfirmationService::class)->confirmDownState($monitor, 'fetch_error');

        $this->assertTrue($result['enabled']);
        $this->assertTrue($result['performed']);
        $this->assertFalse($result['confirmed']);
        $this->assertTrue((bool) $result['health_controls_enabled']);
        $this->assertContains('us-east', $result['selected_regions']);
        $this->assertContains('ap-southeast', $result['selected_regions']);
        $this->assertNotContains('eu-west', $result['selected_regions']);
        $this->assertSame('eu-west', data_get($result, 'excluded_regions.0.region'));
    }

    public function test_strict_health_policy_blocks_confirmation_when_too_few_regions_remain(): void
    {
        Cache::flush();

        config()->set('wids.multi_region.enabled', true);
        config()->set('wids.multi_region.regions', ['us-east', 'eu-west', 'ap-southeast']);
        config()->set('wids.multi_region.max_regions_by_tier', [
            'default' => 3,
        ]);
        config()->set('wids.multi_region.quorum_failures', 2);
        config()->set('wids.multi_region.health_controls.enabled', true);
        config()->set('wids.multi_region.health_controls.min_samples', 2);
        config()->set('wids.multi_region.health_controls.max_failure_ratio', 0.4);
        config()->set('wids.multi_region.health_controls.exclude_unhealthy_regions', true);
        config()->set('wids.multi_region.health_controls.min_regions_after_filter', 2);
        config()->set('wids.multi_region.health_controls.strict_min_regions', true);

        $healthService = app(MultiRegionHealthService::class);
        $healthService->recordProbeResult('eu-west', true);
        $healthService->recordProbeResult('eu-west', true);
        $healthService->recordProbeResult('ap-southeast', true);
        $healthService->recordProbeResult('ap-southeast', true);

        Http::fake([
            '*' => Http::response('ok', 200),
        ]);

        $monitor = new MonitoredUrl([
            'url' => 'https://1.1.1.1',
            'expected_status_min' => 200,
            'expected_status_max' => 299,
        ]);

        $result = app(MultiRegionOutageConfirmationService::class)->confirmDownState($monitor, 'fetch_error');

        $this->assertTrue($result['enabled']);
        $this->assertFalse($result['performed']);
        $this->assertTrue($result['confirmed']);
        $this->assertSame('not_enough_healthy_regions', $result['reason']);
        $this->assertSame(['us-east'], $result['selected_regions']);
        $this->assertCount(2, $result['excluded_regions']);
    }
}
