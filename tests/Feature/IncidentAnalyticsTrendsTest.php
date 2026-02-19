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

class IncidentAnalyticsTrendsTest extends TestCase
{
    use RefreshDatabase;

    public function test_incidents_index_renders_resolved_and_mttr_trends_with_drilldown(): void
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
            'url' => 'https://example.com',
            'display_name' => 'Example',
            'is_active' => true,
            'frequency_minutes' => 5,
            'timeout_seconds' => 20,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        $snapshot = UrlSnapshot::query()->create([
            'monitored_url_id' => $monitor->id,
            'fetched_at' => now()->subHour(),
            'request_url' => $monitor->url,
            'final_url' => $monitor->url,
            'http_status' => 500,
            'headers' => [],
            'response_time_ms' => 230,
            'body_size' => 10,
            'body_sha256' => hash('sha256', 'incident'),
        ]);

        Incident::query()->create([
            'tenant_id' => $tenant->id,
            'monitored_url_id' => $monitor->id,
            'previous_snapshot_id' => null,
            'current_snapshot_id' => $snapshot->id,
            'kind' => 'unexpected_status',
            'severity' => 90,
            'status' => 'resolved',
            'summary' => 'Resolved outage',
            'details' => ['http_status' => 500],
            'detected_at' => now()->subDay()->subMinutes(30),
            'last_seen_at' => now()->subDay(),
            'resolved_at' => now()->subDay(),
            'occurrences' => 2,
            'fingerprint' => hash('sha256', 'resolved-a'),
        ]);

        $this->actingAs($owner);

        $response = $this->get(route('incidents.index'));

        $response->assertOk();
        $response->assertSee('Resolved trend (14 days)');
        $response->assertSee('MTTR trend (14 days)');
        $response->assertSee('Daily resolved incidents');
        $response->assertSee('Daily mean time to resolve');
        $response->assertSee('resolved_from=', false);
        $response->assertSee('resolved_to=', false);
    }

    public function test_incidents_index_can_filter_by_resolved_date_range(): void
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
            'url' => 'https://example.com',
            'display_name' => 'Example',
            'is_active' => true,
            'frequency_minutes' => 5,
            'timeout_seconds' => 20,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        $snapshot = UrlSnapshot::query()->create([
            'monitored_url_id' => $monitor->id,
            'fetched_at' => now()->subHour(),
            'request_url' => $monitor->url,
            'final_url' => $monitor->url,
            'http_status' => 500,
            'headers' => [],
            'response_time_ms' => 230,
            'body_size' => 10,
            'body_sha256' => hash('sha256', 'incident'),
        ]);

        Incident::query()->create([
            'tenant_id' => $tenant->id,
            'monitored_url_id' => $monitor->id,
            'previous_snapshot_id' => null,
            'current_snapshot_id' => $snapshot->id,
            'kind' => 'unexpected_status',
            'severity' => 80,
            'status' => 'resolved',
            'summary' => 'Resolved in range',
            'details' => ['http_status' => 500],
            'detected_at' => Carbon::parse('2026-02-10 09:00:00', 'UTC'),
            'last_seen_at' => Carbon::parse('2026-02-10 09:10:00', 'UTC'),
            'resolved_at' => Carbon::parse('2026-02-10 10:00:00', 'UTC'),
            'occurrences' => 1,
            'fingerprint' => hash('sha256', 'resolved-in-range'),
        ]);

        Incident::query()->create([
            'tenant_id' => $tenant->id,
            'monitored_url_id' => $monitor->id,
            'previous_snapshot_id' => null,
            'current_snapshot_id' => $snapshot->id,
            'kind' => 'unexpected_status',
            'severity' => 80,
            'status' => 'resolved',
            'summary' => 'Resolved out of range',
            'details' => ['http_status' => 500],
            'detected_at' => Carbon::parse('2026-02-05 09:00:00', 'UTC'),
            'last_seen_at' => Carbon::parse('2026-02-05 09:10:00', 'UTC'),
            'resolved_at' => Carbon::parse('2026-02-05 10:00:00', 'UTC'),
            'occurrences' => 1,
            'fingerprint' => hash('sha256', 'resolved-out-of-range'),
        ]);

        $this->actingAs($owner);

        $response = $this->get(route('incidents.index', [
            'status' => 'resolved',
            'resolved_from' => '2026-02-09',
            'resolved_to' => '2026-02-11',
        ]));

        $response->assertOk();
        $response->assertSee('Resolved in range');
        $response->assertDontSee('Resolved out of range');
    }
}

