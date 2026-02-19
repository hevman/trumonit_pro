<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Services\PrivateProbeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivateProbeHeartbeatTest extends TestCase
{
    use RefreshDatabase;

    public function test_probe_heartbeat_updates_last_seen_and_meta(): void
    {
        config()->set('wids.private_probes.enabled', true);
        config()->set('wids.private_probes.allowed_tiers', ['lite', 'pro', 'enterprise']);

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
            'plan_tier' => 'lite',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $created = app(PrivateProbeService::class)->createForTenant(
            tenant: $tenant,
            name: 'Agent Probe',
            createdByUserId: $owner->id,
            regionHint: 'eu-west',
            capabilities: ['http' => true]
        );

        $probe = $created['probe'];
        $token = (string) $created['token'];

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/private-probes/heartbeat', [
                'version' => '1.2.3',
                'region' => 'eu-west',
                'checks_running' => 12,
                'queue_ready' => 3,
                'tags' => ['k8s', 'zone-a'],
            ]);

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('probe.id', $probe->id);

        $probe->refresh();

        $this->assertNotNull($probe->last_seen_at);
        $this->assertSame('eu-west', (string) data_get($probe->meta, 'heartbeat.region'));
        $this->assertSame(12, (int) data_get($probe->meta, 'heartbeat.checks_running'));
        $this->assertSame(3, (int) data_get($probe->meta, 'heartbeat.queue_ready'));
    }

    public function test_invalid_probe_token_is_rejected(): void
    {
        config()->set('wids.private_probes.enabled', true);

        $this->withHeader('Authorization', 'Bearer invalid-token')
            ->postJson('/api/v1/private-probes/heartbeat', [
                'version' => '1.0.0',
            ])->assertStatus(401);
    }

    public function test_disabled_private_probes_return_not_found_for_heartbeat(): void
    {
        config()->set('wids.private_probes.enabled', false);

        $this->withHeader('Authorization', 'Bearer whatever')
            ->postJson('/api/v1/private-probes/heartbeat', [])
            ->assertStatus(404);
    }
}
