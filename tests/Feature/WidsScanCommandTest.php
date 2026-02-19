<?php

namespace Tests\Feature;

use App\Jobs\ScanMonitoredUrlJob;
use App\Models\MonitoredUrl;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class WidsScanCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_wids_scan_dispatches_only_due_monitors_by_next_scan_at(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-11 12:00:00', 'UTC'));

        Bus::fake();

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $due = MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $user->id,
            'url' => 'https://example.com/due',
            'display_name' => 'Due',
            'is_active' => true,
            'next_scan_at' => now()->subMinute(),
            'frequency_minutes' => 5,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        $future = MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $user->id,
            'url' => 'https://example.com/future',
            'display_name' => 'Future',
            'is_active' => true,
            'next_scan_at' => now()->addMinute(),
            'frequency_minutes' => 5,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        $inactiveDue = MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $user->id,
            'url' => 'https://example.com/inactive',
            'display_name' => 'Inactive',
            'is_active' => false,
            'next_scan_at' => now()->subMinute(),
            'frequency_minutes' => 5,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        $this->artisan('wids:scan')->assertExitCode(0);

        Bus::assertDispatched(ScanMonitoredUrlJob::class, 1);
        Bus::assertDispatched(ScanMonitoredUrlJob::class, fn (ScanMonitoredUrlJob $job) => $job->monitoredUrlId === $due->id);
        Bus::assertNotDispatched(ScanMonitoredUrlJob::class, fn (ScanMonitoredUrlJob $job) => $job->monitoredUrlId === $future->id);
        Bus::assertNotDispatched(ScanMonitoredUrlJob::class, fn (ScanMonitoredUrlJob $job) => $job->monitoredUrlId === $inactiveDue->id);
    }

    public function test_wids_scan_force_dispatches_all_active_monitors(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-11 12:00:00', 'UTC'));

        Cache::flush();
        Bus::fake();

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $due = MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $user->id,
            'url' => 'https://example.com/due',
            'display_name' => 'Due',
            'is_active' => true,
            'next_scan_at' => now()->subMinute(),
            'frequency_minutes' => 5,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        $future = MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $user->id,
            'url' => 'https://example.com/future',
            'display_name' => 'Future',
            'is_active' => true,
            'next_scan_at' => now()->addDay(),
            'frequency_minutes' => 5,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        $inactive = MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $user->id,
            'url' => 'https://example.com/inactive',
            'display_name' => 'Inactive',
            'is_active' => false,
            'next_scan_at' => now()->subMinute(),
            'frequency_minutes' => 5,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        $this->artisan('wids:scan --force')->assertExitCode(0);

        Bus::assertDispatched(ScanMonitoredUrlJob::class, 2);
        Bus::assertDispatched(ScanMonitoredUrlJob::class, fn (ScanMonitoredUrlJob $job) => in_array($job->monitoredUrlId, [$due->id, $future->id], true));
        Bus::assertNotDispatched(ScanMonitoredUrlJob::class, fn (ScanMonitoredUrlJob $job) => $job->monitoredUrlId === $inactive->id);
    }

    public function test_wids_scan_skips_private_probe_tagged_monitors_when_private_probes_enabled(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-12 18:00:00', 'UTC'));

        Cache::flush();
        Bus::fake();

        config()->set('wids.private_probes.enabled', true);
        config()->set('wids.private_probes.monitor_tag', 'private_probe');

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $publicMonitor = MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $owner->id,
            'url' => 'https://example.com/public',
            'display_name' => 'Public',
            'is_active' => true,
            'next_scan_at' => now()->subMinute(),
            'frequency_minutes' => 5,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        $privateMonitor = MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $owner->id,
            'url' => 'https://internal.example/private',
            'display_name' => 'Private',
            'is_active' => true,
            'next_scan_at' => now()->subMinute(),
            'frequency_minutes' => 5,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        app(TagService::class)->syncMonitoredUrlTags($privateMonitor, ['private_probe']);

        $this->artisan('wids:scan')->assertExitCode(0);

        Bus::assertDispatched(ScanMonitoredUrlJob::class, 1);
        Bus::assertDispatched(ScanMonitoredUrlJob::class, fn (ScanMonitoredUrlJob $job) => $job->monitoredUrlId === $publicMonitor->id);
        Bus::assertNotDispatched(ScanMonitoredUrlJob::class, fn (ScanMonitoredUrlJob $job) => $job->monitoredUrlId === $privateMonitor->id);
    }
}
