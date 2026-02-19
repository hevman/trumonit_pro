<?php

namespace Tests\Feature;

use App\Services\MultiRegionHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class WidsMultiRegionHealthCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_outputs_text_snapshot_with_region_health(): void
    {
        Cache::flush();

        config()->set('wids.multi_region.regions', ['us-east', 'eu-west']);
        config()->set('wids.multi_region.health_controls.enabled', true);
        config()->set('wids.multi_region.health_controls.min_samples', 1);
        config()->set('wids.multi_region.health_controls.max_failure_ratio', 0.5);

        $healthService = app(MultiRegionHealthService::class);
        $healthService->recordProbeResult('us-east', true);
        $healthService->recordProbeResult('eu-west', false);

        $this->artisan('wids:multi-region-health')
            ->expectsOutputToContain('Multi-region health snapshot')
            ->expectsOutputToContain('us-east')
            ->expectsOutputToContain('eu-west')
            ->assertExitCode(0);
    }

    public function test_command_outputs_json_snapshot(): void
    {
        Cache::flush();

        config()->set('wids.multi_region.regions', ['us-east', 'eu-west']);
        $exitCode = Artisan::call('wids:multi-region-health', ['--format' => 'json']);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"configured_regions"', $output);
        $this->assertStringContainsString('"region_health"', $output);
    }
}
