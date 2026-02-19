<?php

namespace Tests\Feature;

use App\Models\MonitoredUrl;
use App\Models\Tenant;
use App\Models\UrlSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MonitoredUrlIndexAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_monitored_urls_index_renders_fleet_trends_with_drilldown_links(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-12 12:00:00', 'UTC'));

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $monitor = MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $owner->id,
            'url' => 'https://api.example.com/health',
            'display_name' => 'API health',
            'is_active' => true,
            'frequency_minutes' => 5,
            'timeout_seconds' => 20,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        UrlSnapshot::query()->create([
            'monitored_url_id' => $monitor->id,
            'fetched_at' => now()->subMinutes(50),
            'request_url' => $monitor->url,
            'final_url' => $monitor->url,
            'http_status' => 200,
            'headers' => [],
            'response_time_ms' => 120,
            'body_size' => 10,
            'body_sha256' => hash('sha256', 'ok-1'),
        ]);

        UrlSnapshot::query()->create([
            'monitored_url_id' => $monitor->id,
            'fetched_at' => now()->subMinutes(30),
            'request_url' => $monitor->url,
            'final_url' => $monitor->url,
            'http_status' => 200,
            'headers' => [],
            'response_time_ms' => 240,
            'body_size' => 10,
            'body_sha256' => hash('sha256', 'ok-2'),
        ]);

        UrlSnapshot::query()->create([
            'monitored_url_id' => $monitor->id,
            'fetched_at' => now()->subMinutes(5),
            'request_url' => $monitor->url,
            'final_url' => $monitor->url,
            'http_status' => 500,
            'headers' => [],
            'response_time_ms' => 700,
            'body_size' => 10,
            'body_sha256' => hash('sha256', 'fail'),
        ]);

        $this->actingAs($owner);

        $response = $this->get(route('monitored-urls.index'));

        $response->assertOk();
        $response->assertSee('Uptime trend (24 hours)');
        $response->assertSee('Response p95 trend (24 hours)');
        $response->assertSee('Hourly uptime ratio');
        $response->assertSee('Hourly response p95');
        $response->assertSee('Checks sampled');
        $response->assertSee('last_scanned_from=', false);
        $response->assertSee('last_scanned_to=', false);
    }
}

