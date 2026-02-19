<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationChannelSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('wids.notifications.url_safety_dns_strict', false);
    }

    public function test_owner_can_update_notification_channels_and_routing(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $this->actingAs($owner);

        $response = $this->put(route('notification-channels.update'), [
            'webhook_urls' => "https://hooks.example.com/a\nhttps://hooks.example.com/b",
            'slack_webhook_urls' => 'https://slack.example.com/a',
            'teams_webhook_urls' => 'https://teams.example.com/a',
            'discord_webhook_urls' => 'https://discord.example.com/a',
            'email_recipients' => "ops@example.com\nAlerts@example.com",
            'webhook_human_readable_enabled' => 1,
            'webhook_min_severity' => 10,
            'slack_min_severity' => 20,
            'teams_min_severity' => 70,
            'discord_min_severity' => 80,
            'email_min_severity' => 60,
        ]);

        $response->assertRedirect(route('notification-channels.edit'));

        $tenant->refresh();
        $channels = is_array($tenant->notification_channels) ? $tenant->notification_channels : [];

        $this->assertSame(
            ['https://hooks.example.com/a', 'https://hooks.example.com/b'],
            $channels['webhook_urls'] ?? []
        );
        $this->assertSame(
            ['https://slack.example.com/a'],
            $channels['slack_webhook_urls'] ?? []
        );
        $this->assertSame(
            ['https://teams.example.com/a'],
            $channels['teams_webhook_urls'] ?? []
        );
        $this->assertSame(
            ['https://discord.example.com/a'],
            $channels['discord_webhook_urls'] ?? []
        );
        $this->assertSame(
            ['ops@example.com', 'alerts@example.com'],
            $channels['email_recipients'] ?? []
        );
        $this->assertTrue((bool) ($channels['webhook_human_readable_enabled'] ?? false));
        $this->assertSame(10, $channels['routing']['webhook_min_severity'] ?? null);
        $this->assertSame(20, $channels['routing']['slack_min_severity'] ?? null);
        $this->assertSame(70, $channels['routing']['teams_min_severity'] ?? null);
        $this->assertSame(80, $channels['routing']['discord_min_severity'] ?? null);
        $this->assertSame(60, $channels['routing']['email_min_severity'] ?? null);

        $this->get(route('notification-channels.edit'))
            ->assertOk()
            ->assertSee(trans('messages.notifications.payload_mode'))
            ->assertSee(trans('messages.notifications.payload_mode_human_machine'));
    }

    public function test_member_cannot_manage_notification_channels(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $member = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'member',
        ]);

        $this->actingAs($member);

        $this->get(route('notification-channels.edit'))->assertForbidden();
        $this->put(route('notification-channels.update'), [
            'slack_webhook_urls' => 'https://slack.example.com/a',
        ])->assertForbidden();
    }

    public function test_owner_cannot_set_unsafe_notification_endpoint(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $this->actingAs($owner);

        $response = $this->from(route('notification-channels.edit'))
            ->put(route('notification-channels.update'), [
                'slack_webhook_urls' => 'http://127.0.0.1:8080/hook',
            ]);

        $response->assertRedirect(route('notification-channels.edit'));
        $response->assertSessionHasErrors('slack_webhook_urls');
    }

    public function test_webhook_payload_mode_defaults_to_machine_only_in_view(): void
    {
        config()->set('wids.notifications.webhook.human_readable_enabled', false);

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
            'notification_channels' => [
                'webhook_urls' => ['https://hooks.example.com/a'],
            ],
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $this->actingAs($owner);

        $this->get(route('notification-channels.edit'))
            ->assertOk()
            ->assertSee(trans('messages.notifications.payload_mode_machine_only'));
    }
}
