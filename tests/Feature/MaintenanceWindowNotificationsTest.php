<?php

namespace Tests\Feature;

use App\Jobs\SendIncidentSlackJob;
use App\Models\Incident;
use App\Models\MaintenanceWindow;
use App\Models\MonitoredUrl;
use App\Models\Tenant;
use App\Models\UrlSnapshot;
use App\Models\User;
use App\Services\TagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class MaintenanceWindowNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifications_are_suppressed_during_monitor_maintenance_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-11 12:00:00', 'UTC'));

        config()->set('wids.notifications.respect_maintenance', true);
        config()->set('wids.notifications.slack.enabled', true);
        config()->set('wids.notifications.slack.webhook_urls', ['https://example.com/hook']);
        config()->set('wids.notifications.global_fallback_enabled', true);

        Bus::fake();

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

        MaintenanceWindow::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $user->id,
            'monitored_url_id' => $monitoredUrl->id,
            'tag' => null,
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addMinute(),
            'note' => 'Deploy',
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

        Incident::query()->create([
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

        Bus::assertNotDispatched(SendIncidentSlackJob::class);
    }

    public function test_notifications_are_suppressed_during_tag_maintenance_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-11 12:00:00', 'UTC'));

        config()->set('wids.notifications.respect_maintenance', true);
        config()->set('wids.notifications.slack.enabled', true);
        config()->set('wids.notifications.slack.webhook_urls', ['https://example.com/hook']);
        config()->set('wids.notifications.global_fallback_enabled', true);

        Bus::fake();

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

        app(TagService::class)->syncMonitoredUrlTags($monitoredUrl, ['deploy']);

        MaintenanceWindow::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $user->id,
            'monitored_url_id' => null,
            'tag' => 'deploy',
            'starts_at' => now()->subMinute(),
            'ends_at' => now()->addMinute(),
            'note' => 'Deploy',
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

        Incident::query()->create([
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

        Bus::assertNotDispatched(SendIncidentSlackJob::class);
    }

    public function test_notifications_are_sent_outside_of_maintenance_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-11 12:00:00', 'UTC'));

        config()->set('wids.notifications.respect_maintenance', true);
        config()->set('wids.notifications.slack.enabled', true);
        config()->set('wids.notifications.slack.webhook_urls', ['https://example.com/hook']);
        config()->set('wids.notifications.global_fallback_enabled', true);

        Bus::fake();

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

        MaintenanceWindow::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $user->id,
            'monitored_url_id' => $monitoredUrl->id,
            'tag' => null,
            'starts_at' => now()->subHours(2),
            'ends_at' => now()->subHour(),
            'note' => 'Past',
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

        Incident::query()->create([
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

        Bus::assertDispatched(SendIncidentSlackJob::class);
    }
}
