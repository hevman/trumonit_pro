<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\MonitoredUrl;
use App\Models\Tenant;
use App\Models\UrlSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IncidentDiffTest extends TestCase
{
    use RefreshDatabase;

    public function test_incident_show_renders_change_summary_and_text_diff_when_bodies_available(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-11 12:00:00', 'UTC'));

        Storage::fake();

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
            'frequency_minutes' => 5,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        $beforeBody = '<html><head><title>Old</title></head><body>Hello</body></html>';
        $afterBody = '<html><head><title>New</title></head><body>Hello world</body></html>';

        Storage::put('wids/bodies/before.html', $beforeBody);
        Storage::put('wids/bodies/after.html', $afterBody);

        $previousSnapshot = UrlSnapshot::query()->create([
            'monitored_url_id' => $monitoredUrl->id,
            'fetched_at' => now()->subMinute(),
            'request_url' => $monitoredUrl->url,
            'final_url' => $monitoredUrl->url,
            'http_status' => 200,
            'headers' => [],
            'response_time_ms' => 100,
            'body_size' => strlen($beforeBody),
            'body_sha256' => hash('sha256', $beforeBody),
            'body_storage_path' => 'wids/bodies/before.html',
        ]);

        $currentSnapshot = UrlSnapshot::query()->create([
            'monitored_url_id' => $monitoredUrl->id,
            'fetched_at' => now(),
            'request_url' => $monitoredUrl->url,
            'final_url' => $monitoredUrl->url,
            'http_status' => 200,
            'headers' => [],
            'response_time_ms' => 120,
            'body_size' => strlen($afterBody),
            'body_sha256' => hash('sha256', $afterBody),
            'body_storage_path' => 'wids/bodies/after.html',
        ]);

        $incident = Incident::query()->create([
            'tenant_id' => $tenant->id,
            'monitored_url_id' => $monitoredUrl->id,
            'previous_snapshot_id' => $previousSnapshot->id,
            'current_snapshot_id' => $currentSnapshot->id,
            'kind' => 'content_change',
            'severity' => 70,
            'status' => 'open',
            'summary' => 'Content changed',
            'details' => ['baseline_snapshot_id' => null],
            'detected_at' => now(),
            'last_seen_at' => now(),
            'occurrences' => 1,
            'fingerprint' => hash('sha256', 'x'),
        ]);

        $this->actingAs($user);

        $response = $this->get(route('incidents.show', $incident));

        $response->assertOk();
        $response->assertSee('What changed');
        $response->assertSee('Text diff (preview)');
        $response->assertSee('- <title>Old</title>');
        $response->assertSee('+ <title>New</title>');
    }
}

