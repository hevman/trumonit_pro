<?php

namespace Tests\Feature;

use App\Models\MonitoredUrl;
use App\Models\Tenant;
use App\Models\UrlSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MonitoredUrlAdvancedFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_monitored_urls_index_supports_state_and_mode_filters(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-11 12:00:00', 'UTC'));

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $up = MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $owner->id,
            'url' => 'https://up.example.com',
            'display_name' => 'UP monitor',
            'is_active' => true,
            'frequency_minutes' => 5,
            'timeout_seconds' => 10,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
            'scan_mode' => 'journey',
        ]);

        UrlSnapshot::query()->create([
            'monitored_url_id' => $up->id,
            'fetched_at' => now(),
            'request_url' => $up->url,
            'final_url' => $up->url,
            'http_status' => 200,
            'headers' => [],
            'response_time_ms' => 120,
            'body_size' => 10,
            'body_sha256' => hash('sha256', 'up'),
            'error_message' => null,
        ]);

        $down = MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $owner->id,
            'url' => 'https://down.example.com',
            'display_name' => 'DOWN monitor',
            'is_active' => true,
            'frequency_minutes' => 60,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 299,
            'scan_mode' => 'http',
        ]);

        UrlSnapshot::query()->create([
            'monitored_url_id' => $down->id,
            'fetched_at' => now(),
            'request_url' => $down->url,
            'final_url' => $down->url,
            'http_status' => 500,
            'headers' => [],
            'response_time_ms' => 400,
            'body_size' => 10,
            'body_sha256' => hash('sha256', 'down'),
        ]);

        MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $owner->id,
            'url' => 'https://paused.example.com',
            'display_name' => 'PAUSED monitor',
            'is_active' => false,
            'frequency_minutes' => 1440,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
            'scan_mode' => 'http',
        ]);

        $this->actingAs($owner);

        $response = $this->get(route('monitored-urls.index', [
            'state' => 'up',
            'scan_mode' => 'journey',
        ]));

        $response->assertOk();
        $response->assertSee('Health (24h)');
        $response->assertSee('UP monitor');
        $response->assertDontSee('DOWN monitor');
        $response->assertDontSee('PAUSED monitor');
    }

    public function test_web_monitored_urls_index_supports_numeric_range_filters(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-11 12:00:00', 'UTC'));

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $owner->id,
            'url' => 'https://small.example.com',
            'display_name' => 'Small range',
            'is_active' => true,
            'frequency_minutes' => 5,
            'timeout_seconds' => 10,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $owner->id,
            'url' => 'https://large.example.com',
            'display_name' => 'Large range',
            'is_active' => true,
            'frequency_minutes' => 120,
            'timeout_seconds' => 60,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        $this->actingAs($owner);

        $response = $this->get(route('monitored-urls.index', [
            'frequency_min' => 30,
            'timeout_min' => 20,
        ]));

        $response->assertOk();
        $response->assertSee('Large range');
        $response->assertDontSee('Small range');
    }

    public function test_web_monitored_urls_index_supports_last_scanned_datetime_filters(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-11 12:00:00', 'UTC'));

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $owner->id,
            'url' => 'https://old-scan.example.com',
            'display_name' => 'Old scan',
            'is_active' => true,
            'frequency_minutes' => 5,
            'timeout_seconds' => 10,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
            'last_scanned_at' => now()->subDays(2),
        ]);

        MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $owner->id,
            'url' => 'https://recent-scan.example.com',
            'display_name' => 'Recent scan',
            'is_active' => true,
            'frequency_minutes' => 5,
            'timeout_seconds' => 10,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
            'last_scanned_at' => now()->subMinutes(20),
        ]);

        $this->actingAs($owner);

        $response = $this->get(route('monitored-urls.index', [
            'last_scanned_from' => now()->subHour()->toDateTimeString(),
            'last_scanned_to' => now()->toDateTimeString(),
        ]));

        $response->assertOk();
        $response->assertSee('Recent scan');
        $response->assertDontSee('Old scan');
    }
}
