<?php

namespace Tests\Feature;

use App\Models\MonitoredUrl;
use App\Models\Tenant;
use App\Models\UrlSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MonitoredUrlUptimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_monitored_url_show_displays_uptime_and_current_status(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-11 12:00:00', 'UTC'));

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $monitoredUrl = MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $user->id,
            'url' => 'https://example.com',
            'display_name' => 'Example',
            'is_active' => true,
            'frequency_minutes' => 1,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        UrlSnapshot::query()->create([
            'monitored_url_id' => $monitoredUrl->id,
            'fetched_at' => now()->subHour(),
            'request_url' => $monitoredUrl->url,
            'final_url' => $monitoredUrl->url,
            'http_status' => 200,
            'headers' => [],
            'response_time_ms' => 111,
            'body_size' => 10,
            'body_sha256' => hash('sha256', 'ok'),
        ]);

        UrlSnapshot::query()->create([
            'monitored_url_id' => $monitoredUrl->id,
            'fetched_at' => now(),
            'request_url' => $monitoredUrl->url,
            'final_url' => $monitoredUrl->url,
            'http_status' => 500,
            'headers' => [],
            'response_time_ms' => 222,
            'body_size' => 10,
            'body_sha256' => hash('sha256', 'fail'),
        ]);

        $this->actingAs($user);

        $response = $this->get(route('monitored-urls.show', $monitoredUrl));

        $response->assertOk();
        $response->assertSee('Uptime (24h)');
        $response->assertSee('50%');
        $response->assertSee('1/2 checks');
        $response->assertSee('http: 500');
    }
}

