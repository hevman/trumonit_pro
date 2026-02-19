<?php

namespace Tests\Feature;

use App\Jobs\SendIncidentSlackJob;
use App\Jobs\SendIncidentTeamsJob;
use App\Jobs\SendIncidentDiscordJob;
use App\Jobs\SendIncidentWebhookJob;
use App\Models\Incident;
use App\Models\MonitoredUrl;
use App\Models\Tenant;
use App\Models\UrlSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class TenantScopedNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('wids.notifications.url_safety_dns_strict', false);
    }

    public function test_slack_notifications_use_tenant_scoped_channels(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-11 12:00:00', 'UTC'));

        config()->set('wids.notifications.slack.enabled', true);
        config()->set('wids.notifications.global_fallback_enabled', false);
        config()->set('wids.notifications.slack.webhook_urls', ['https://slack.example.com/global']);

        Bus::fake();

        $tenantA = Tenant::query()->create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a',
            'notification_channels' => [
                'slack_webhook_urls' => ['https://slack.example.com/a'],
            ],
        ]);

        $tenantB = Tenant::query()->create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b',
            'notification_channels' => [
                'slack_webhook_urls' => ['https://slack.example.com/b'],
            ],
        ]);

        $this->createIncidentForTenant($tenantA, 'https://example.com/a');

        Bus::assertDispatchedTimes(SendIncidentSlackJob::class, 1);
        Bus::assertDispatched(SendIncidentSlackJob::class, fn (SendIncidentSlackJob $job): bool => $job->webhookUrl === 'https://slack.example.com/a');
        Bus::assertNotDispatched(SendIncidentSlackJob::class, fn (SendIncidentSlackJob $job): bool => $job->webhookUrl === 'https://slack.example.com/b');
        Bus::assertNotDispatched(SendIncidentSlackJob::class, fn (SendIncidentSlackJob $job): bool => $job->webhookUrl === 'https://slack.example.com/global');

        $this->createIncidentForTenant($tenantB, 'https://example.com/b');

        Bus::assertDispatched(SendIncidentSlackJob::class, fn (SendIncidentSlackJob $job): bool => $job->webhookUrl === 'https://slack.example.com/b');
    }

    public function test_global_channels_are_not_used_when_fallback_is_disabled(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-11 12:00:00', 'UTC'));

        config()->set('wids.notifications.slack.enabled', true);
        config()->set('wids.notifications.global_fallback_enabled', false);
        config()->set('wids.notifications.slack.webhook_urls', ['https://slack.example.com/global']);

        Bus::fake();

        $tenant = Tenant::query()->create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a',
            'notification_channels' => [],
        ]);

        $this->createIncidentForTenant($tenant, 'https://example.com/no-fallback');

        Bus::assertNotDispatched(SendIncidentSlackJob::class);
    }

    public function test_global_channels_can_be_used_as_explicit_fallback(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-11 12:00:00', 'UTC'));

        config()->set('wids.notifications.slack.enabled', true);
        config()->set('wids.notifications.global_fallback_enabled', true);
        config()->set('wids.notifications.slack.webhook_urls', ['https://slack.example.com/global']);

        Bus::fake();

        $tenant = Tenant::query()->create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a',
            'notification_channels' => [],
        ]);

        $this->createIncidentForTenant($tenant, 'https://example.com/with-fallback');

        Bus::assertDispatched(SendIncidentSlackJob::class, fn (SendIncidentSlackJob $job): bool => $job->webhookUrl === 'https://slack.example.com/global');
    }

    public function test_teams_and_discord_notifications_use_tenant_scoped_channels(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-11 12:00:00', 'UTC'));

        config()->set('wids.notifications.teams.enabled', true);
        config()->set('wids.notifications.discord.enabled', true);
        config()->set('wids.notifications.global_fallback_enabled', false);
        config()->set('wids.notifications.teams.webhook_urls', ['https://teams.example.com/global']);
        config()->set('wids.notifications.discord.webhook_urls', ['https://discord.example.com/global']);

        Bus::fake();

        $tenantA = Tenant::query()->create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a',
            'notification_channels' => [
                'teams_webhook_urls' => ['https://teams.example.com/a'],
                'discord_webhook_urls' => ['https://discord.example.com/a'],
            ],
        ]);

        $tenantB = Tenant::query()->create([
            'name' => 'Tenant B',
            'slug' => 'tenant-b',
            'notification_channels' => [
                'teams_webhook_urls' => ['https://teams.example.com/b'],
                'discord_webhook_urls' => ['https://discord.example.com/b'],
            ],
        ]);

        $this->createIncidentForTenant($tenantA, 'https://example.com/a');

        Bus::assertDispatched(SendIncidentTeamsJob::class, fn (SendIncidentTeamsJob $job): bool => $job->webhookUrl === 'https://teams.example.com/a');
        Bus::assertNotDispatched(SendIncidentTeamsJob::class, fn (SendIncidentTeamsJob $job): bool => $job->webhookUrl === 'https://teams.example.com/b');
        Bus::assertNotDispatched(SendIncidentTeamsJob::class, fn (SendIncidentTeamsJob $job): bool => $job->webhookUrl === 'https://teams.example.com/global');

        Bus::assertDispatched(SendIncidentDiscordJob::class, fn (SendIncidentDiscordJob $job): bool => $job->webhookUrl === 'https://discord.example.com/a');
        Bus::assertNotDispatched(SendIncidentDiscordJob::class, fn (SendIncidentDiscordJob $job): bool => $job->webhookUrl === 'https://discord.example.com/b');
        Bus::assertNotDispatched(SendIncidentDiscordJob::class, fn (SendIncidentDiscordJob $job): bool => $job->webhookUrl === 'https://discord.example.com/global');

        $this->createIncidentForTenant($tenantB, 'https://example.com/b');

        Bus::assertDispatched(SendIncidentTeamsJob::class, fn (SendIncidentTeamsJob $job): bool => $job->webhookUrl === 'https://teams.example.com/b');
        Bus::assertDispatched(SendIncidentDiscordJob::class, fn (SendIncidentDiscordJob $job): bool => $job->webhookUrl === 'https://discord.example.com/b');
    }

    public function test_notification_routing_applies_min_severity_thresholds(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-11 12:00:00', 'UTC'));

        config()->set('wids.notifications.slack.enabled', true);
        config()->set('wids.notifications.teams.enabled', true);
        config()->set('wids.notifications.global_fallback_enabled', false);

        Bus::fake();

        $tenant = Tenant::query()->create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a',
            'notification_channels' => [
                'slack_webhook_urls' => ['https://slack.example.com/a'],
                'teams_webhook_urls' => ['https://teams.example.com/a'],
                'routing' => [
                    'slack_min_severity' => 40,
                    'teams_min_severity' => 80,
                ],
            ],
        ]);

        $this->createIncidentForTenant($tenant, 'https://example.com/routed', severity: 60);

        Bus::assertDispatched(SendIncidentSlackJob::class, fn (SendIncidentSlackJob $job): bool => $job->webhookUrl === 'https://slack.example.com/a');
        Bus::assertNotDispatched(SendIncidentTeamsJob::class, fn (SendIncidentTeamsJob $job): bool => $job->webhookUrl === 'https://teams.example.com/a');
    }

    public function test_webhook_dispatch_uses_tenant_human_readable_override(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-11 12:00:00', 'UTC'));

        config()->set('wids.notifications.webhook.enabled', true);
        config()->set('wids.notifications.webhook.human_readable_enabled', false);
        config()->set('wids.notifications.global_fallback_enabled', false);

        Bus::fake();

        $tenant = Tenant::query()->create([
            'name' => 'Tenant A',
            'slug' => 'tenant-a',
            'notification_channels' => [
                'webhook_urls' => ['https://hooks.example.com/a'],
                'webhook_human_readable_enabled' => true,
            ],
        ]);

        $this->createIncidentForTenant($tenant, 'https://example.com/webhook');

        Bus::assertDispatched(SendIncidentWebhookJob::class, function (SendIncidentWebhookJob $job): bool {
            return $job->webhookUrl === 'https://hooks.example.com/a'
                && $job->humanReadableEnabled === true;
        });
    }

    private function createIncidentForTenant(Tenant $tenant, string $url, int $severity = 95): Incident
    {
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $monitoredUrl = MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $user->id,
            'url' => $url,
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
            'response_time_ms' => 120,
            'body_size' => 10,
            'body_sha256' => hash('sha256', 'body-'.$url),
        ]);

        return Incident::query()->create([
            'tenant_id' => $tenant->id,
            'monitored_url_id' => $monitoredUrl->id,
            'previous_snapshot_id' => null,
            'current_snapshot_id' => $snapshot->id,
            'kind' => 'unexpected_status',
            'severity' => $severity,
            'status' => 'open',
            'summary' => 'Unexpected HTTP status',
            'details' => ['http_status' => 500],
            'detected_at' => now(),
            'last_seen_at' => now(),
            'occurrences' => 1,
            'fingerprint' => hash('sha256', 'fp-'.$tenant->id.'-'.$url.'-'.(string) now()),
        ]);
    }
}
