<?php

namespace Tests\Feature;

use App\Models\PrivateProbe;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class WidsPrivateProbesEnforceCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_disables_probes_for_residency_stale_and_capacity_rules(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-12 18:00:00', 'UTC'));

        config()->set('wids.private_probes.enabled', true);
        config()->set('wids.private_probes.allowed_regions_by_tier', [
            'pro' => ['eu-west'],
            'default' => ['*'],
        ]);
        config()->set('wids.private_probes.max_active_by_tier', [
            'pro' => 1,
            'default' => 0,
        ]);
        config()->set('wids.private_probes.fleet_policy.stale_disable_hours', 24);
        config()->set('wids.private_probes.fleet_policy.event_history_limit', 8);

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
            'plan_tier' => 'pro',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $probeAllowedRecent = $this->createProbe(
            tenantId: $tenant->id,
            ownerId: $owner->id,
            name: 'Allowed recent',
            regionHint: 'eu-west',
            lastSeenAt: now()->subHour(),
        );

        $probeRegionViolation = $this->createProbe(
            tenantId: $tenant->id,
            ownerId: $owner->id,
            name: 'Region violation',
            regionHint: 'us-east',
            lastSeenAt: now()->subHour(),
        );

        $probeStale = $this->createProbe(
            tenantId: $tenant->id,
            ownerId: $owner->id,
            name: 'Stale probe',
            regionHint: 'eu-west',
            lastSeenAt: now()->subHours(40),
        );

        $probeCapacityOverflow = $this->createProbe(
            tenantId: $tenant->id,
            ownerId: $owner->id,
            name: 'Capacity overflow',
            regionHint: 'eu-west',
            lastSeenAt: now()->subHours(2),
        );

        $this->artisan('wids:private-probes-enforce')->assertExitCode(0);

        $probeAllowedRecent->refresh();
        $probeRegionViolation->refresh();
        $probeStale->refresh();
        $probeCapacityOverflow->refresh();

        $this->assertTrue((bool) $probeAllowedRecent->is_active);
        $this->assertFalse((bool) $probeRegionViolation->is_active);
        $this->assertFalse((bool) $probeStale->is_active);
        $this->assertFalse((bool) $probeCapacityOverflow->is_active);

        $this->assertSame('region_not_allowed', data_get($probeRegionViolation->meta, 'fleet_policy.last_reason'));
        $this->assertSame('stale_probe', data_get($probeStale->meta, 'fleet_policy.last_reason'));
        $this->assertSame('capacity_limit', data_get($probeCapacityOverflow->meta, 'fleet_policy.last_reason'));
    }

    public function test_command_dry_run_does_not_disable_any_probe(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-12 18:15:00', 'UTC'));

        config()->set('wids.private_probes.enabled', true);
        config()->set('wids.private_probes.allowed_regions_by_tier', [
            'pro' => ['eu-west'],
            'default' => ['*'],
        ]);
        config()->set('wids.private_probes.max_active_by_tier', [
            'pro' => 1,
            'default' => 0,
        ]);
        config()->set('wids.private_probes.fleet_policy.stale_disable_hours', 24);

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
            'plan_tier' => 'pro',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $probeA = $this->createProbe(
            tenantId: $tenant->id,
            ownerId: $owner->id,
            name: 'Probe A',
            regionHint: 'eu-west',
            lastSeenAt: now()->subHour(),
        );

        $probeB = $this->createProbe(
            tenantId: $tenant->id,
            ownerId: $owner->id,
            name: 'Probe B',
            regionHint: 'us-east',
            lastSeenAt: now()->subHours(30),
        );

        $this->artisan('wids:private-probes-enforce --dry-run')
            ->expectsOutputToContain('Would disable (region policy)')
            ->assertExitCode(0);

        $probeA->refresh();
        $probeB->refresh();

        $this->assertTrue((bool) $probeA->is_active);
        $this->assertTrue((bool) $probeB->is_active);
        $this->assertNull(data_get($probeB->meta, 'fleet_policy.last_reason'));
    }

    private function createProbe(
        int $tenantId,
        int $ownerId,
        string $name,
        string $regionHint,
        \Illuminate\Support\Carbon $lastSeenAt
    ): PrivateProbe {
        $token = Str::random(64);

        return PrivateProbe::query()->create([
            'tenant_id' => $tenantId,
            'created_by_user_id' => $ownerId,
            'name' => $name,
            'region_hint' => $regionHint,
            'auth_token_hash' => hash('sha256', $token),
            'auth_token_last8' => substr($token, -8),
            'is_active' => true,
            'last_seen_at' => $lastSeenAt,
            'meta' => [],
        ]);
    }
}
