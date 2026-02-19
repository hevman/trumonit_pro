<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\IncidentComment;
use App\Models\MonitoredUrl;
use App\Models\Tenant;
use App\Models\UrlSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class IncidentFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_incidents_index_can_filter_by_multiple_fields_and_search(): void
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

        $alpha = MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $user->id,
            'url' => 'https://alpha.example.com',
            'display_name' => 'Alpha',
            'is_active' => true,
            'frequency_minutes' => 1440,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        $beta = MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $user->id,
            'url' => 'https://beta.example.com',
            'display_name' => 'Beta',
            'is_active' => true,
            'frequency_minutes' => 1440,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        $alphaSnapshot = UrlSnapshot::query()->create([
            'monitored_url_id' => $alpha->id,
            'fetched_at' => now(),
            'request_url' => $alpha->url,
            'final_url' => $alpha->url,
            'http_status' => 200,
            'headers' => [],
            'response_time_ms' => 123,
            'body_size' => 10,
            'body_sha256' => hash('sha256', 'alpha'),
        ]);

        $betaSnapshot = UrlSnapshot::query()->create([
            'monitored_url_id' => $beta->id,
            'fetched_at' => now(),
            'request_url' => $beta->url,
            'final_url' => $beta->url,
            'http_status' => 200,
            'headers' => [],
            'response_time_ms' => 234,
            'body_size' => 10,
            'body_sha256' => hash('sha256', 'beta'),
        ]);

        Incident::query()->create([
            'tenant_id' => $tenant->id,
            'monitored_url_id' => $alpha->id,
            'previous_snapshot_id' => null,
            'current_snapshot_id' => $alphaSnapshot->id,
            'kind' => 'content_change',
            'severity' => 70,
            'status' => 'open',
            'summary' => 'Something happened',
            'details' => ['foo' => 'bar'],
            'detected_at' => now(),
            'last_seen_at' => now(),
            'occurrences' => 1,
            'fingerprint' => hash('sha256', 'i-alpha'),
        ]);

        Incident::query()->create([
            'tenant_id' => $tenant->id,
            'monitored_url_id' => $beta->id,
            'previous_snapshot_id' => null,
            'current_snapshot_id' => $betaSnapshot->id,
            'kind' => 'unexpected_status',
            'severity' => 30,
            'status' => 'resolved',
            'summary' => 'Beta down',
            'details' => ['http_status' => 500],
            'detected_at' => now(),
            'last_seen_at' => now(),
            'occurrences' => 1,
            'fingerprint' => hash('sha256', 'i-beta'),
            'resolved_at' => now(),
        ]);

        $this->actingAs($user);

        $response = $this->get('/incidents?status=open&kind=content_change&severity_min=60&severity_max=80&monitored_url_id='.$alpha->id.'&q=alpha');

        $response->assertOk();
        $response->assertSee('Incident trend (14 days)');
        $response->assertSee('Daily incidents');
        $response->assertSee('Top incident kinds');
        $response->assertSee('detected_from=2026-02-11', false);
        $response->assertSee('detected_to=2026-02-11', false);
        $response->assertSee('Something happened', escape: false);
        $response->assertDontSee('Beta down', escape: false);
    }

    public function test_web_incident_resolution_sets_and_clears_resolved_at(): void
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
            'frequency_minutes' => 1440,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        $snapshot = UrlSnapshot::query()->create([
            'monitored_url_id' => $monitoredUrl->id,
            'fetched_at' => now(),
            'request_url' => $monitoredUrl->url,
            'final_url' => $monitoredUrl->url,
            'http_status' => 500,
            'headers' => [],
            'response_time_ms' => 123,
            'body_size' => 10,
            'body_sha256' => hash('sha256', '<html></html>'),
        ]);

        $incident = Incident::query()->create([
            'tenant_id' => $tenant->id,
            'monitored_url_id' => $monitoredUrl->id,
            'previous_snapshot_id' => null,
            'current_snapshot_id' => $snapshot->id,
            'kind' => 'unexpected_status',
            'severity' => 95,
            'status' => 'open',
            'summary' => 'Unexpected HTTP status',
            'details' => ['http_status' => 500],
            'detected_at' => now(),
            'last_seen_at' => now(),
            'occurrences' => 1,
            'fingerprint' => hash('sha256', 'y'),
        ]);

        $this->actingAs($user);

        $this->patch(route('incidents.update', $incident), ['status' => 'resolved'])->assertRedirect();
        $incident->refresh();
        $this->assertSame('resolved', $incident->status);
        $this->assertNotNull($incident->resolved_at);

        $this->patch(route('incidents.update', $incident), ['status' => 'open'])->assertRedirect();
        $incident->refresh();
        $this->assertSame('open', $incident->status);
        $this->assertNull($incident->resolved_at);
    }

    public function test_web_incidents_index_supports_advanced_filters(): void
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

        $monitor = MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $user->id,
            'url' => 'https://example.com',
            'display_name' => 'Example',
            'is_active' => true,
            'frequency_minutes' => 1440,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        $snapshot = UrlSnapshot::query()->create([
            'monitored_url_id' => $monitor->id,
            'fetched_at' => now(),
            'request_url' => $monitor->url,
            'final_url' => $monitor->url,
            'http_status' => 500,
            'headers' => [],
            'response_time_ms' => 123,
            'body_size' => 10,
            'body_sha256' => hash('sha256', '<html></html>'),
        ]);

        $matchingIncident = Incident::query()->create([
            'tenant_id' => $tenant->id,
            'monitored_url_id' => $monitor->id,
            'previous_snapshot_id' => null,
            'current_snapshot_id' => $snapshot->id,
            'kind' => 'unexpected_status',
            'severity' => 90,
            'status' => 'open',
            'summary' => 'Has comments and many occurrences',
            'details' => ['http_status' => 500],
            'detected_at' => Carbon::parse('2026-02-11 10:00:00', 'UTC'),
            'last_seen_at' => now(),
            'occurrences' => 7,
            'fingerprint' => hash('sha256', 'advanced-a'),
        ]);

        IncidentComment::query()->create([
            'tenant_id' => $tenant->id,
            'incident_id' => $matchingIncident->id,
            'author_user_id' => $user->id,
            'body' => 'Need investigation.',
        ]);

        Incident::query()->create([
            'tenant_id' => $tenant->id,
            'monitored_url_id' => $monitor->id,
            'previous_snapshot_id' => null,
            'current_snapshot_id' => $snapshot->id,
            'kind' => 'unexpected_status',
            'severity' => 90,
            'status' => 'open',
            'summary' => 'No comments and low occurrences',
            'details' => ['http_status' => 500],
            'detected_at' => Carbon::parse('2026-02-05 10:00:00', 'UTC'),
            'last_seen_at' => now(),
            'occurrences' => 1,
            'fingerprint' => hash('sha256', 'advanced-b'),
        ]);

        $this->actingAs($user);

        $response = $this->get(route('incidents.index', [
            'has_comments' => '1',
            'occurrences_min' => 5,
            'detected_from' => '2026-02-10',
            'detected_to' => '2026-02-12',
        ]));

        $response->assertOk();
        $response->assertSee('Has comments and many occurrences');
        $response->assertDontSee('No comments and low occurrences');
    }
}
