<?php

namespace Tests\Feature;

use App\Jobs\ScanMonitoredUrlJob;
use App\Models\MonitoredUrl;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class WidsScanQueuePriorityTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduler_routes_due_monitors_into_priority_queues(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-12 10:00:00', 'UTC'));
        Cache::flush();
        Bus::fake();

        config()->set('wids.queue.scan.high_priority_lag_seconds', 300);
        config()->set('wids.queue.scan.high_priority_failures', 3);
        config()->set('wids.queue.scan.low_priority_min_frequency_minutes', 60);
        config()->set('wids.queue.scan.low_priority_max_lag_seconds', 120);
        config()->set('wids.queue.scan.tenant_dispatch_cap_per_tick', 0);

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $high = MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $owner->id,
            'url' => 'https://example.com/high',
            'display_name' => 'High',
            'is_active' => true,
            'next_scan_at' => now()->subMinutes(10),
            'frequency_minutes' => 5,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
            'consecutive_failures' => 0,
        ]);

        $normal = MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $owner->id,
            'url' => 'https://example.com/normal',
            'display_name' => 'Normal',
            'is_active' => true,
            'next_scan_at' => now()->subSeconds(30),
            'frequency_minutes' => 5,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
            'consecutive_failures' => 0,
        ]);

        $low = MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $owner->id,
            'url' => 'https://example.com/low',
            'display_name' => 'Low',
            'is_active' => true,
            'next_scan_at' => now()->subSeconds(30),
            'frequency_minutes' => 120,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
            'consecutive_failures' => 0,
        ]);

        $this->artisan('wids:scan')->assertExitCode(0);

        Bus::assertDispatched(ScanMonitoredUrlJob::class, 3);
        Bus::assertDispatched(ScanMonitoredUrlJob::class, function (ScanMonitoredUrlJob $job) use ($high): bool {
            return $job->monitoredUrlId === $high->id && $job->queue === 'wids-high';
        });
        Bus::assertDispatched(ScanMonitoredUrlJob::class, function (ScanMonitoredUrlJob $job) use ($normal): bool {
            return $job->monitoredUrlId === $normal->id && $job->queue === 'wids';
        });
        Bus::assertDispatched(ScanMonitoredUrlJob::class, function (ScanMonitoredUrlJob $job) use ($low): bool {
            return $job->monitoredUrlId === $low->id && $job->queue === 'wids-low';
        });
    }

    public function test_scheduler_respects_per_tenant_dispatch_cap(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-12 10:00:00', 'UTC'));
        Cache::flush();
        Bus::fake();

        config()->set('wids.queue.scan.tenant_dispatch_cap_per_tick', 1);
        config()->set('wids.queue.scan.high_priority_failures', 3);

        $tenantA = Tenant::query()->create([
            'name' => 'A',
            'slug' => 'a',
        ]);

        $tenantB = Tenant::query()->create([
            'name' => 'B',
            'slug' => 'b',
        ]);

        $ownerA = User::factory()->create([
            'tenant_id' => $tenantA->id,
            'role' => 'owner',
        ]);

        $ownerB = User::factory()->create([
            'tenant_id' => $tenantB->id,
            'role' => 'owner',
        ]);

        $a1 = MonitoredUrl::query()->create([
            'tenant_id' => $tenantA->id,
            'created_by_user_id' => $ownerA->id,
            'url' => 'https://example.com/a1',
            'display_name' => 'A1',
            'is_active' => true,
            'next_scan_at' => now()->subMinute(),
            'frequency_minutes' => 5,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        $a2 = MonitoredUrl::query()->create([
            'tenant_id' => $tenantA->id,
            'created_by_user_id' => $ownerA->id,
            'url' => 'https://example.com/a2',
            'display_name' => 'A2',
            'is_active' => true,
            'next_scan_at' => now()->subMinute(),
            'frequency_minutes' => 5,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        $b1 = MonitoredUrl::query()->create([
            'tenant_id' => $tenantB->id,
            'created_by_user_id' => $ownerB->id,
            'url' => 'https://example.com/b1',
            'display_name' => 'B1',
            'is_active' => true,
            'next_scan_at' => now()->subMinute(),
            'frequency_minutes' => 5,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        $this->artisan('wids:scan')
            ->expectsOutputToContain('skipped_tenant_cap=1')
            ->assertExitCode(0);

        Bus::assertDispatched(ScanMonitoredUrlJob::class, 2);
        Bus::assertDispatched(ScanMonitoredUrlJob::class, fn (ScanMonitoredUrlJob $job) => $job->monitoredUrlId === $a1->id);
        Bus::assertDispatched(ScanMonitoredUrlJob::class, fn (ScanMonitoredUrlJob $job) => $job->monitoredUrlId === $b1->id);
        Bus::assertNotDispatched(ScanMonitoredUrlJob::class, fn (ScanMonitoredUrlJob $job) => $job->monitoredUrlId === $a2->id);
    }
}
