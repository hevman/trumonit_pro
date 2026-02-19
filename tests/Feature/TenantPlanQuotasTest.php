<?php

namespace Tests\Feature;

use App\Models\MonitoredUrl;
use App\Models\Tenant;
use App\Models\UrlSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TenantPlanQuotasTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_store_enforces_monitor_limit_from_plan_tier(): void
    {
        config()->set('wids.limits.max_monitored_urls_per_tenant', 0);
        config()->set('wids.plan_tiers.lite.max_monitored_urls', 1);

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
            'plan_tier' => 'lite',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $owner->id,
            'url' => 'https://example.com/one',
            'display_name' => 'One',
            'is_active' => true,
            'frequency_minutes' => 5,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        $this->actingAs($owner);

        $response = $this->post(route('monitored-urls.store'), [
            'url' => 'https://example.com/two',
            'display_name' => 'Two',
            'is_active' => true,
            'scan_mode' => 'http',
        ]);

        $response->assertSessionHasErrors('url');
        $response->assertSessionHasErrors([
            'url' => 'Monitored URL limit (1) reached for LITE plan. Upgrade plan to add more monitors.',
        ]);
    }

    public function test_cleanup_respects_retention_days_per_tenant_plan(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-11 12:00:00', 'UTC'));

        config()->set('wids.retention.enabled', true);
        config()->set('wids.plan_tiers.lite.retention_days', 30);
        config()->set('wids.plan_tiers.pro.retention_days', 90);

        $liteTenant = Tenant::query()->create([
            'name' => 'Lite',
            'slug' => 'lite',
            'plan_tier' => 'lite',
        ]);

        $proTenant = Tenant::query()->create([
            'name' => 'Pro',
            'slug' => 'pro',
            'plan_tier' => 'pro',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $liteTenant->id,
            'role' => 'owner',
        ]);

        $proUser = User::factory()->create([
            'tenant_id' => $proTenant->id,
            'role' => 'owner',
        ]);

        $liteMonitor = MonitoredUrl::query()->create([
            'tenant_id' => $liteTenant->id,
            'created_by_user_id' => $user->id,
            'url' => 'https://example.com/lite',
            'display_name' => 'Lite',
            'is_active' => true,
            'frequency_minutes' => 5,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        $proMonitor = MonitoredUrl::query()->create([
            'tenant_id' => $proTenant->id,
            'created_by_user_id' => $proUser->id,
            'url' => 'https://example.com/pro',
            'display_name' => 'Pro',
            'is_active' => true,
            'frequency_minutes' => 5,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        $liteSnapshot = UrlSnapshot::query()->create([
            'monitored_url_id' => $liteMonitor->id,
            'fetched_at' => now()->subDays(40),
            'request_url' => $liteMonitor->url,
            'final_url' => $liteMonitor->url,
            'http_status' => 200,
            'headers' => [],
            'response_time_ms' => 100,
            'body_size' => 10,
            'body_bytes_stored' => 10,
            'body_sha256' => hash('sha256', 'lite'),
        ]);

        $proSnapshot = UrlSnapshot::query()->create([
            'monitored_url_id' => $proMonitor->id,
            'fetched_at' => now()->subDays(40),
            'request_url' => $proMonitor->url,
            'final_url' => $proMonitor->url,
            'http_status' => 200,
            'headers' => [],
            'response_time_ms' => 100,
            'body_size' => 10,
            'body_bytes_stored' => 10,
            'body_sha256' => hash('sha256', 'pro'),
        ]);

        $this->artisan('wids:cleanup')->assertExitCode(0);

        $this->assertDatabaseMissing('url_snapshots', ['id' => $liteSnapshot->id]);
        $this->assertDatabaseHas('url_snapshots', ['id' => $proSnapshot->id]);
    }
}
