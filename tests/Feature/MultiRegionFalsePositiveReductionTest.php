<?php

namespace Tests\Feature;

use App\Jobs\ScanMonitoredUrlJob;
use App\Models\Incident;
use App\Models\MonitoredUrl;
use App\Models\Tenant;
use App\Models\UrlSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MultiRegionFalsePositiveReductionTest extends TestCase
{
    use RefreshDatabase;

    public function test_primary_down_without_quorum_is_suppressed(): void
    {
        config()->set('wids.multi_region.enabled', true);
        config()->set('wids.multi_region.regions', ['us-east', 'eu-west', 'ap-southeast']);
        config()->set('wids.multi_region.max_regions_by_tier', [
            'lite' => 3,
            'default' => 3,
        ]);
        config()->set('wids.multi_region.quorum_failures', 2);
        config()->set('wids.screenshot.enabled', false);

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
            'plan_tier' => 'lite',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $monitor = MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $owner->id,
            'url' => 'http://1.1.1.1',
            'display_name' => 'API',
            'is_active' => true,
            'frequency_minutes' => 5,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 299,
        ]);

        Http::fake(function ($request) {
            $regionHeader = is_array($request->header('X-Wids-Probe-Region')) ? (string) ($request->header('X-Wids-Probe-Region')[0] ?? '') : '';

            if ($regionHeader !== '') {
                return Http::response('ok', 200);
            }

            return Http::response('down', 500);
        });

        (new ScanMonitoredUrlJob($monitor->id))->handle();

        $monitor->refresh();
        $snapshot = UrlSnapshot::query()->latest('id')->firstOrFail();

        $this->assertSame(0, Incident::query()->count());
        $this->assertSame(0, (int) $monitor->consecutive_failures);
        $this->assertFalse((bool) data_get($snapshot->meta, 'multi_region.confirmed'));
        $this->assertSame('quorum_not_met', data_get($snapshot->meta, 'multi_region.reason'));
    }

    public function test_primary_down_with_quorum_creates_incident(): void
    {
        config()->set('wids.multi_region.enabled', true);
        config()->set('wids.multi_region.regions', ['us-east', 'eu-west', 'ap-southeast']);
        config()->set('wids.multi_region.max_regions_by_tier', [
            'lite' => 3,
            'default' => 3,
        ]);
        config()->set('wids.multi_region.quorum_failures', 2);
        config()->set('wids.screenshot.enabled', false);

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
            'plan_tier' => 'lite',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $monitor = MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $owner->id,
            'url' => 'http://1.1.1.1',
            'display_name' => 'API',
            'is_active' => true,
            'frequency_minutes' => 5,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 299,
        ]);

        Http::fake(function ($request) {
            $regionHeader = is_array($request->header('X-Wids-Probe-Region')) ? (string) ($request->header('X-Wids-Probe-Region')[0] ?? '') : '';

            if ($regionHeader === 'eu-west') {
                return Http::response('down', 500);
            }

            if ($regionHeader === 'ap-southeast') {
                return Http::response('ok', 200);
            }

            return Http::response('down', 500);
        });

        (new ScanMonitoredUrlJob($monitor->id))->handle();

        $snapshot = UrlSnapshot::query()->latest('id')->firstOrFail();
        $incident = Incident::query()->latest('id')->first();

        $this->assertNotNull($incident);
        $this->assertSame('unexpected_status', $incident?->kind);
        $this->assertTrue((bool) data_get($snapshot->meta, 'multi_region.confirmed'));
        $this->assertSame('quorum_met', data_get($snapshot->meta, 'multi_region.reason'));
    }
}
