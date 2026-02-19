<?php

namespace Tests\Feature;

use App\Models\PrivateProbe;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PrivateProbesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_list_update_and_rotate_private_probe(): void
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

        Sanctum::actingAs($owner, ['wids.read', 'wids.write']);

        $createResponse = $this->postJson('/api/v1/private-probes', [
            'name' => 'Warsaw Probe',
            'region_hint' => 'eu-central',
            'capabilities' => [
                'http' => true,
                'journey' => false,
            ],
        ]);

        $createResponse->assertStatus(201);
        $createResponse->assertJsonPath('private_probe.name', 'Warsaw Probe');
        $createResponse->assertJsonPath('private_probe.region_hint', 'eu-central');
        $createResponse->assertJsonPath('private_probe.is_active', true);
        $createResponse->assertJsonPath('private_probe.capabilities.http', true);
        $createResponse->assertJsonStructure(['private_probe', 'auth_token', 'message']);

        $token = (string) $createResponse->json('auth_token');
        $probeId = (int) $createResponse->json('private_probe.id');

        $probe = PrivateProbe::query()->findOrFail($probeId);

        $this->assertNotSame($token, $probe->auth_token_hash);
        $this->assertSame(hash('sha256', $token), $probe->auth_token_hash);

        $this->getJson('/api/v1/private-probes')
            ->assertOk()
            ->assertJsonPath('private_probes.0.id', $probeId)
            ->assertJsonPath('private_probes.0.name', 'Warsaw Probe')
            ->assertJsonPath('private_probes.0.auth_token_last8', $probe->auth_token_last8);

        $this->patchJson('/api/v1/private-probes/'.$probeId, [
            'name' => 'Warsaw Probe 2',
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('private_probe.name', 'Warsaw Probe 2')
            ->assertJsonPath('private_probe.is_active', false);

        $previousLast8 = (string) $probe->auth_token_last8;

        $rotateResponse = $this->postJson('/api/v1/private-probes/'.$probeId.'/rotate-token');
        $rotateResponse->assertOk();
        $rotateResponse->assertJsonStructure(['private_probe', 'auth_token', 'message']);

        $probe->refresh();
        $this->assertTrue($probe->is_active);
        $this->assertNotSame($previousLast8, (string) $probe->auth_token_last8);
    }

    public function test_member_cannot_manage_private_probes(): void
    {
        config()->set('wids.private_probes.enabled', true);
        config()->set('wids.private_probes.allowed_tiers', ['lite', 'pro', 'enterprise']);

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
            'plan_tier' => 'lite',
        ]);

        $member = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'member',
        ]);

        Sanctum::actingAs($member, ['wids.read', 'wids.write']);

        $this->postJson('/api/v1/private-probes', [
            'name' => 'Member Probe',
        ])->assertForbidden();
    }

    public function test_owner_cannot_create_probe_for_disallowed_tier(): void
    {
        config()->set('wids.private_probes.enabled', true);
        config()->set('wids.private_probes.allowed_tiers', ['enterprise']);

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
            'plan_tier' => 'lite',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        Sanctum::actingAs($owner, ['wids.read', 'wids.write']);

        $this->postJson('/api/v1/private-probes', [
            'name' => 'Blocked Probe',
        ])->assertStatus(422)
            ->assertJsonValidationErrors('plan_tier');
    }

    public function test_owner_must_follow_residency_and_capacity_policy_when_creating_and_activating_probe(): void
    {
        config()->set('wids.private_probes.enabled', true);
        config()->set('wids.private_probes.allowed_tiers', ['lite', 'pro', 'enterprise']);
        config()->set('wids.private_probes.allowed_regions_by_tier', [
            'lite' => ['eu-west'],
            'default' => ['*'],
        ]);
        config()->set('wids.private_probes.max_active_by_tier', [
            'lite' => 1,
            'default' => 0,
        ]);

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
            'plan_tier' => 'lite',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        Sanctum::actingAs($owner, ['wids.read', 'wids.write']);

        $this->postJson('/api/v1/private-probes', [
            'name' => 'Blocked Region',
            'region_hint' => 'us-east',
        ])->assertStatus(422)->assertJsonValidationErrors('region_hint');

        $first = $this->postJson('/api/v1/private-probes', [
            'name' => 'Allowed One',
            'region_hint' => 'eu-west',
        ])->assertStatus(201);

        $firstProbeId = (int) $first->json('private_probe.id');

        $this->postJson('/api/v1/private-probes', [
            'name' => 'Second Active',
            'region_hint' => 'eu-west',
        ])->assertStatus(422)->assertJsonValidationErrors('private_probes');

        $this->patchJson('/api/v1/private-probes/'.$firstProbeId, [
            'is_active' => false,
        ])->assertOk();

        $second = $this->postJson('/api/v1/private-probes', [
            'name' => 'Allowed Two',
            'region_hint' => 'eu-west',
        ])->assertStatus(201);

        $secondProbeId = (int) $second->json('private_probe.id');

        $this->patchJson('/api/v1/private-probes/'.$firstProbeId, [
            'is_active' => true,
        ])->assertStatus(422)->assertJsonValidationErrors('private_probes');

        $this->patchJson('/api/v1/private-probes/'.$secondProbeId, [
            'region_hint' => 'us-east',
            'is_active' => true,
        ])->assertStatus(422)->assertJsonValidationErrors('region_hint');
    }
}

