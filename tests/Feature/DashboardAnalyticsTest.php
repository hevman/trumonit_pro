<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\MonitoredUrl;
use App\Models\Tenant;
use App\Models\UrlSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DashboardAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_panels_and_charts_with_real_data(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-12 12:00:00', 'UTC'));

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $up = MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $user->id,
            'url' => 'https://up.example.com',
            'display_name' => 'UP',
            'is_active' => true,
            'frequency_minutes' => 5,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        $down = MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $user->id,
            'url' => 'https://down.example.com',
            'display_name' => 'DOWN',
            'is_active' => true,
            'frequency_minutes' => 5,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        $muted = MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $user->id,
            'url' => 'https://muted.example.com',
            'display_name' => 'MUTED',
            'is_active' => true,
            'frequency_minutes' => 5,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
            'muted_until' => now()->addHour(),
        ]);

        MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $user->id,
            'url' => 'https://paused.example.com',
            'display_name' => 'PAUSED',
            'is_active' => false,
            'frequency_minutes' => 5,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $user->id,
            'url' => 'https://unknown.example.com',
            'display_name' => 'UNKNOWN',
            'is_active' => true,
            'frequency_minutes' => 5,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        $upSnapshot = UrlSnapshot::query()->create([
            'monitored_url_id' => $up->id,
            'fetched_at' => now()->subMinutes(5),
            'request_url' => $up->url,
            'final_url' => $up->url,
            'http_status' => 200,
            'headers' => [],
            'response_time_ms' => 120,
            'body_size' => 100,
            'body_sha256' => hash('sha256', 'up'),
        ]);

        $downSnapshot = UrlSnapshot::query()->create([
            'monitored_url_id' => $down->id,
            'fetched_at' => now()->subMinutes(3),
            'request_url' => $down->url,
            'final_url' => $down->url,
            'http_status' => 500,
            'headers' => [],
            'response_time_ms' => 600,
            'body_size' => 100,
            'body_sha256' => hash('sha256', 'down'),
        ]);

        UrlSnapshot::query()->create([
            'monitored_url_id' => $muted->id,
            'fetched_at' => now()->subMinutes(2),
            'request_url' => $muted->url,
            'final_url' => $muted->url,
            'http_status' => 200,
            'headers' => [],
            'response_time_ms' => 110,
            'body_size' => 100,
            'body_sha256' => hash('sha256', 'muted'),
        ]);

        Incident::query()->create([
            'tenant_id' => $tenant->id,
            'monitored_url_id' => $down->id,
            'previous_snapshot_id' => null,
            'current_snapshot_id' => $downSnapshot->id,
            'kind' => 'unexpected_status',
            'severity' => 90,
            'status' => 'open',
            'summary' => 'Down right now',
            'details' => ['http_status' => 500],
            'detected_at' => now()->subHours(2),
            'last_seen_at' => now()->subHours(2),
            'occurrences' => 1,
            'fingerprint' => hash('sha256', 'incident-a'),
        ]);

        Incident::query()->create([
            'tenant_id' => $tenant->id,
            'monitored_url_id' => $up->id,
            'previous_snapshot_id' => null,
            'current_snapshot_id' => $upSnapshot->id,
            'kind' => 'content_change',
            'severity' => 45,
            'status' => 'open',
            'summary' => 'Minor content change',
            'details' => ['changed' => true],
            'detected_at' => now()->subHours(3),
            'last_seen_at' => now()->subHours(3),
            'occurrences' => 1,
            'fingerprint' => hash('sha256', 'incident-b'),
        ]);

        Incident::query()->create([
            'tenant_id' => $tenant->id,
            'monitored_url_id' => $up->id,
            'previous_snapshot_id' => null,
            'current_snapshot_id' => $upSnapshot->id,
            'kind' => 'dns_change',
            'severity' => 20,
            'status' => 'resolved',
            'summary' => 'DNS stabilized',
            'details' => ['dns' => 'ok'],
            'detected_at' => now()->subDays(2),
            'last_seen_at' => now()->subDays(2),
            'resolved_at' => now()->subDay(),
            'occurrences' => 1,
            'fingerprint' => hash('sha256', 'incident-c'),
        ]);

        $this->actingAs($user);

        $response = $this->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Incident trend (7 days)');
        $response->assertSee('Response trend (24 hours)');
        $response->assertSee('Monitor state distribution');
        $response->assertSee('Top incident kinds (7 days)');
        $response->assertSee('Daily incidents');
        $response->assertSee('Average response by hour');
        $response->assertSee('33.3%');
        $response->assertSee('1/3');
        $response->assertSee('Critical open');
        $response->assertSee('New incidents (24h)');
        $response->assertSee('unexpected_status');
        $response->assertSee('detected_from='.now()->toDateString(), false);
        $response->assertSee('detected_to='.now()->toDateString(), false);
        $response->assertSee('last_scanned_from=', false);
        $response->assertSee('last_scanned_to=', false);
    }
}
