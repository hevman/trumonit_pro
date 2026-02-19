<?php

namespace Tests\Feature;

use App\Jobs\ScanMonitoredUrlJob;
use App\Models\Incident;
use App\Models\MonitoredUrl;
use App\Models\Tenant;
use App\Models\UrlSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AutoResolveIncidentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_auto_resolves_unexpected_status_on_recovery(): void
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
            'http_status' => 200,
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
            'detected_at' => now()->subMinute(),
            'last_seen_at' => now()->subMinute(),
            'occurrences' => 1,
            'fingerprint' => hash('sha256', 'x'),
        ]);

        $job = new ScanMonitoredUrlJob($monitoredUrl->id);
        $method = new \ReflectionMethod(ScanMonitoredUrlJob::class, 'autoResolveRecoveredIncidents');
        $method->setAccessible(true);

        $method->invoke($job, $monitoredUrl, $snapshot, []);

        $incident->refresh();

        $this->assertSame('resolved', $incident->status);
        $this->assertNotNull($incident->resolved_at);
        $this->assertSame('2026-02-11 12:00:00', $incident->resolved_at->format('Y-m-d H:i:s'));
    }
}

