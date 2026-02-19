<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class WidsTenantNotificationsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_notification_channels_can_be_updated_via_command(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $this->artisan('wids:tenant-notifications', [
            'tenant_id' => $tenant->id,
            '--slack_webhook_urls' => 'https://slack.example.com/a,https://slack.example.com/b',
            '--email_recipients' => 'ops@example.com,Alerts@example.com',
        ])->assertSuccessful();

        $tenant->refresh();

        $channels = is_array($tenant->notification_channels) ? $tenant->notification_channels : [];

        $this->assertSame(
            ['https://slack.example.com/a', 'https://slack.example.com/b'],
            $channels['slack_webhook_urls'] ?? []
        );

        $this->assertSame(
            ['ops@example.com', 'alerts@example.com'],
            $channels['email_recipients'] ?? []
        );
    }

    public function test_tenant_notification_routing_and_new_channels_can_be_updated_via_command(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $this->artisan('wids:tenant-notifications', [
            'tenant_id' => $tenant->id,
            '--teams_webhook_urls' => 'https://teams.example.com/a',
            '--discord_webhook_urls' => 'https://discord.example.com/a',
            '--webhook_human_readable' => 'true',
            '--teams_min_severity' => '70',
            '--discord_min_severity' => '80',
        ])->assertSuccessful();

        $tenant->refresh();

        $channels = is_array($tenant->notification_channels) ? $tenant->notification_channels : [];
        $routing = is_array($channels['routing'] ?? null) ? $channels['routing'] : [];

        $this->assertSame(
            ['https://teams.example.com/a'],
            $channels['teams_webhook_urls'] ?? []
        );
        $this->assertSame(
            ['https://discord.example.com/a'],
            $channels['discord_webhook_urls'] ?? []
        );
        $this->assertTrue((bool) ($channels['webhook_human_readable_enabled'] ?? false));
        $this->assertSame(70, $routing['teams_min_severity'] ?? null);
        $this->assertSame(80, $routing['discord_min_severity'] ?? null);
    }

    public function test_show_effective_option_includes_resolved_channels_with_fallbacks(): void
    {
        config()->set('wids.notifications.url_safety_enforced', false);
        config()->set('wids.notifications.global_fallback_enabled', true);
        config()->set('wids.notifications.slack.webhook_urls', ['https://slack.example.com/global']);
        config()->set('wids.notifications.webhook.human_readable_enabled', true);

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
            'notification_channels' => [],
        ]);

        $exitCode = Artisan::call('wids:tenant-notifications', [
            'tenant_id' => $tenant->id,
            '--show' => true,
            '--effective' => true,
        ]);

        $this->assertSame(0, $exitCode);

        $payload = json_decode(Artisan::output(), true);

        $this->assertIsArray($payload);
        $this->assertArrayHasKey('notification_channels', $payload);
        $this->assertArrayHasKey('effective_notification_channels', $payload);
        $this->assertSame(
            ['https://slack.example.com/global'],
            $payload['effective_notification_channels']['slack_webhook_urls'] ?? []
        );
        $this->assertTrue((bool) ($payload['effective_notification_channels']['webhook_human_readable_enabled'] ?? false));
    }
}
