<?php

namespace Tests\Feature;

use App\Jobs\SendIncidentSlackJob;
use App\Models\Incident;
use App\Models\MonitoredUrl;
use App\Models\Tenant;
use App\Models\UrlSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class MutedNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_notifications_are_suppressed_when_monitor_is_muted(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-11 12:00:00', 'UTC'));

        config()->set('wids.notifications.respect_mute', true);
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
            'muted_until' => now()->addHour(),
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

    public function test_notifications_are_sent_when_monitor_is_not_muted(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-11 12:00:00', 'UTC'));

        config()->set('wids.notifications.respect_mute', true);
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

        Bus::assertDispatched(SendIncidentSlackJob::class, function (SendIncidentSlackJob $job): bool {
            return $job->incidentId > 0 && $job->webhookUrl === 'https://example.com/hook';
        });
    }
}
