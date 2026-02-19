<?php

namespace Tests\Feature;

use App\Models\MonitoredUrl;
use App\Models\PrivateProbe;
use App\Models\PrivateProbeScanTask;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PrivateProbeService;
use App\Services\TagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class WidsPrivateProbesDispatchCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatch_command_queues_due_private_probe_monitors_with_fair_distribution(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-12 16:45:00', 'UTC'));

        config()->set('wids.private_probes.enabled', true);
        config()->set('wids.private_probes.allowed_tiers', ['lite', 'pro', 'enterprise']);
        config()->set('wids.private_probes.monitor_tag', 'private_probe');
        config()->set('wids.private_probes.dispatch_cap_per_tenant', 0);
        config()->set('wids.private_probes.dispatch_max_per_tick', 20);
        config()->set('wids.private_probes.stale_after_minutes', 30);
        config()->set('wids.private_probes.dispatch_require_online', true);

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
            'plan_tier' => 'pro',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $probeA = app(PrivateProbeService::class)->createForTenant(
            tenant: $tenant,
            name: 'Probe A',
            createdByUserId: $owner->id,
            regionHint: 'eu-west'
        )['probe'];

        $probeB = app(PrivateProbeService::class)->createForTenant(
            tenant: $tenant,
            name: 'Probe B',
            createdByUserId: $owner->id,
            regionHint: 'eu-west'
        )['probe'];

        $probeA->forceFill(['last_seen_at' => now()])->save();
        $probeB->forceFill(['last_seen_at' => now()])->save();

        $dueA = $this->createPrivateProbeMonitor($tenant->id, $owner->id, 'https://internal.example/a', now()->subMinute());
        $dueB = $this->createPrivateProbeMonitor($tenant->id, $owner->id, 'https://internal.example/b', now()->subMinute());
        $dueC = $this->createPrivateProbeMonitor($tenant->id, $owner->id, 'https://internal.example/c', now()->subMinute());
        $this->createPublicMonitor($tenant->id, $owner->id, 'https://public.example/x', now()->subMinute());

        $this->artisan('wids:private-probes-dispatch')->assertExitCode(0);

        $tasks = PrivateProbeScanTask::query()
            ->where('tenant_id', $tenant->id)
            ->where('status', 'queued')
            ->orderBy('id')
            ->get();

        $this->assertCount(3, $tasks);
        $expectedMonitorIds = [$dueA->id, $dueB->id, $dueC->id];
        $actualMonitorIds = $tasks->pluck('monitored_url_id')->map(fn ($value): int => (int) $value)->all();
        sort($expectedMonitorIds);
        sort($actualMonitorIds);
        $this->assertSame($expectedMonitorIds, $actualMonitorIds);

        $byProbe = $tasks
            ->groupBy('private_probe_id')
            ->map(fn ($rows): int => $rows->count())
            ->all();

        $this->assertCount(2, $byProbe);
        $this->assertLessThanOrEqual(1, abs(((int) ($byProbe[$probeA->id] ?? 0)) - ((int) ($byProbe[$probeB->id] ?? 0))));

        $this->artisan('wids:private-probes-dispatch')->assertExitCode(0);
    }

    public function test_dispatch_command_honors_probe_pin_and_region_tags(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-12 17:05:00', 'UTC'));

        config()->set('wids.private_probes.enabled', true);
        config()->set('wids.private_probes.allowed_tiers', ['lite', 'pro', 'enterprise']);
        config()->set('wids.private_probes.monitor_tag', 'private_probe');
        config()->set('wids.private_probes.pin_tag_prefix', 'probe-id:');
        config()->set('wids.private_probes.region_tag_prefix', 'probe-region:');
        config()->set('wids.private_probes.region_fallback', false);
        config()->set('wids.private_probes.dispatch_cap_per_tenant', 0);
        config()->set('wids.private_probes.stale_after_minutes', 30);
        config()->set('wids.private_probes.dispatch_require_online', true);

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
            'plan_tier' => 'pro',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $probeEu = app(PrivateProbeService::class)->createForTenant(
            tenant: $tenant,
            name: 'Probe EU',
            createdByUserId: $owner->id,
            regionHint: 'eu-west'
        )['probe'];

        $probeUs = app(PrivateProbeService::class)->createForTenant(
            tenant: $tenant,
            name: 'Probe US',
            createdByUserId: $owner->id,
            regionHint: 'us-east'
        )['probe'];

        $probeEu->forceFill(['last_seen_at' => now()])->save();
        $probeUs->forceFill(['last_seen_at' => now()])->save();

        $pinned = $this->createPrivateProbeMonitor(
            $tenant->id,
            $owner->id,
            'https://internal.example/pinned',
            now()->subMinute(),
            ['private_probe', 'probe-id:'.$probeUs->id]
        );

        $regional = $this->createPrivateProbeMonitor(
            $tenant->id,
            $owner->id,
            'https://internal.example/regional',
            now()->subMinute(),
            ['private_probe', 'probe-region:eu-west']
        );

        $missingRegion = $this->createPrivateProbeMonitor(
            $tenant->id,
            $owner->id,
            'https://internal.example/missing',
            now()->subMinute(),
            ['private_probe', 'probe-region:ap-southeast']
        );

        $this->artisan('wids:private-probes-dispatch')->assertExitCode(0);

        $pinnedTask = PrivateProbeScanTask::query()->where('monitored_url_id', $pinned->id)->first();
        $regionalTask = PrivateProbeScanTask::query()->where('monitored_url_id', $regional->id)->first();
        $missingTask = PrivateProbeScanTask::query()->where('monitored_url_id', $missingRegion->id)->first();

        $this->assertNotNull($pinnedTask);
        $this->assertNotNull($regionalTask);
        $this->assertNull($missingTask);
        $this->assertSame($probeUs->id, (int) $pinnedTask->private_probe_id);
        $this->assertSame($probeEu->id, (int) $regionalTask->private_probe_id);
    }

    public function test_dispatch_command_enforces_residency_policy_for_probe_pool_and_requested_region(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-12 17:20:00', 'UTC'));

        config()->set('wids.private_probes.enabled', true);
        config()->set('wids.private_probes.allowed_tiers', ['lite', 'pro', 'enterprise']);
        config()->set('wids.private_probes.allowed_regions_by_tier', [
            'pro' => ['eu-west'],
            'default' => ['*'],
        ]);
        config()->set('wids.private_probes.monitor_tag', 'private_probe');
        config()->set('wids.private_probes.pin_tag_prefix', 'probe-id:');
        config()->set('wids.private_probes.region_tag_prefix', 'probe-region:');
        config()->set('wids.private_probes.region_fallback', true);
        config()->set('wids.private_probes.dispatch_cap_per_tenant', 0);
        config()->set('wids.private_probes.stale_after_minutes', 30);
        config()->set('wids.private_probes.dispatch_require_online', true);

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
            'plan_tier' => 'pro',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $probeEu = app(PrivateProbeService::class)->createForTenant(
            tenant: $tenant,
            name: 'Probe EU',
            createdByUserId: $owner->id,
            regionHint: 'eu-west'
        )['probe'];
        $probeEu->forceFill(['last_seen_at' => now()])->save();

        $token = Str::random(64);
        $probeUs = PrivateProbe::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $owner->id,
            'name' => 'Probe US',
            'region_hint' => 'us-east',
            'auth_token_hash' => hash('sha256', $token),
            'auth_token_last8' => substr($token, -8),
            'is_active' => true,
            'last_seen_at' => now(),
            'meta' => [],
        ]);

        $monitorAny = $this->createPrivateProbeMonitor(
            $tenant->id,
            $owner->id,
            'https://internal.example/any',
            now()->subMinute(),
            ['private_probe']
        );

        $monitorUsOnly = $this->createPrivateProbeMonitor(
            $tenant->id,
            $owner->id,
            'https://internal.example/us-only',
            now()->subMinute(),
            ['private_probe', 'probe-region:us-east']
        );

        $this->artisan('wids:private-probes-dispatch')->assertExitCode(0);

        $anyTask = PrivateProbeScanTask::query()->where('monitored_url_id', $monitorAny->id)->first();
        $usOnlyTask = PrivateProbeScanTask::query()->where('monitored_url_id', $monitorUsOnly->id)->first();

        $this->assertNotNull($anyTask);
        $this->assertSame($probeEu->id, (int) $anyTask->private_probe_id);
        $this->assertNull($usOnlyTask);
        $this->assertSame(0, PrivateProbeScanTask::query()->where('private_probe_id', $probeUs->id)->count());
    }

    private function createPrivateProbeMonitor(
        int $tenantId,
        int $ownerId,
        string $url,
        \DateTimeInterface $nextScanAt,
        array $tags = ['private_probe']
    ): MonitoredUrl {
        $monitor = MonitoredUrl::query()->create([
            'tenant_id' => $tenantId,
            'created_by_user_id' => $ownerId,
            'url' => $url,
            'display_name' => $url,
            'is_active' => true,
            'next_scan_at' => $nextScanAt,
            'frequency_minutes' => 5,
            'timeout_seconds' => 20,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
            'alert_after_failures' => 1,
        ]);

        app(TagService::class)->syncMonitoredUrlTags($monitor, $tags);

        return $monitor;
    }

    private function createPublicMonitor(int $tenantId, int $ownerId, string $url, \DateTimeInterface $nextScanAt): MonitoredUrl
    {
        return MonitoredUrl::query()->create([
            'tenant_id' => $tenantId,
            'created_by_user_id' => $ownerId,
            'url' => $url,
            'display_name' => $url,
            'is_active' => true,
            'next_scan_at' => $nextScanAt,
            'frequency_minutes' => 5,
            'timeout_seconds' => 20,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
            'alert_after_failures' => 1,
        ]);
    }
}
