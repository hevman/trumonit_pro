<?php

namespace Tests\Feature;

use App\Models\MonitoredUrl;
use App\Models\Tenant;
use App\Models\UrlSnapshot;
use App\Models\User;
use App\Services\TagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WidsOpsDrillCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_ops_drill_json_reports_fail_when_private_probe_coverage_is_broken(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-12 19:10:00', 'UTC'));

        config()->set('wids.private_probes.enabled', true);
        config()->set('wids.private_probes.monitor_tag', 'private_probe');
        config()->set('ops.drill.queue_lag_warn_seconds', 60);
        config()->set('ops.drill.queue_lag_fail_seconds', 120);
        config()->set('ops.drill.queue_wait_warn_ms', 100);
        config()->set('ops.drill.queue_wait_fail_ms', 200);

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
            'plan_tier' => 'pro',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $monitor = MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $owner->id,
            'url' => 'https://internal.example/probe-only',
            'display_name' => 'Probe only',
            'is_active' => true,
            'next_scan_at' => now()->subMinutes(5),
            'frequency_minutes' => 5,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        app(TagService::class)->syncMonitoredUrlTags($monitor, ['private_probe']);

        UrlSnapshot::query()->create([
            'monitored_url_id' => $monitor->id,
            'fetched_at' => now()->subMinute(),
            'request_url' => $monitor->url,
            'final_url' => $monitor->url,
            'http_status' => 200,
            'headers' => [],
            'response_time_ms' => 120,
            'body_size' => 0,
            'body_sha256' => hash('sha256', 'ok'),
            'meta' => [
                'queue_wait_ms' => 500,
            ],
        ]);

        $exitCode = Artisan::call('wids:ops-drill', [
            '--format' => 'json',
        ]);

        $this->assertSame(0, $exitCode);

        $report = json_decode(Artisan::output(), true);

        $this->assertIsArray($report);
        $this->assertSame('fail', (string) ($report['overall_status'] ?? ''));
        $this->assertSame('fail', (string) data_get($report, 'checks.private_probe_coverage.status'));
        $this->assertSame(1, (int) data_get($report, 'checks.private_probe_coverage.data.due_without_online_probe_total'));
        $this->assertSame(500.0, (float) data_get($report, 'checks.queue_wait.data.queue_wait_p95_ms'));
    }

    public function test_ops_drill_text_reports_pass_for_healthy_baseline(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-12 19:20:00', 'UTC'));

        config()->set('wids.private_probes.enabled', false);
        config()->set('ops.drill.queue_lag_warn_seconds', 300);
        config()->set('ops.drill.queue_lag_fail_seconds', 900);
        config()->set('ops.drill.queue_wait_warn_ms', 500);
        config()->set('ops.drill.queue_wait_fail_ms', 1000);

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $monitor = MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $owner->id,
            'url' => 'https://example.com/ok',
            'display_name' => 'Public',
            'is_active' => true,
            'next_scan_at' => now()->subSeconds(30),
            'frequency_minutes' => 5,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        UrlSnapshot::query()->create([
            'monitored_url_id' => $monitor->id,
            'fetched_at' => now()->subMinute(),
            'request_url' => $monitor->url,
            'final_url' => $monitor->url,
            'http_status' => 200,
            'headers' => [],
            'response_time_ms' => 80,
            'body_size' => 0,
            'body_sha256' => hash('sha256', 'ok'),
            'meta' => [
                'queue_wait_ms' => 100,
            ],
        ]);

        $this->artisan('wids:ops-drill')
            ->expectsOutputToContain('- queue_lag: PASS')
            ->expectsOutputToContain('- queue_wait: PASS')
            ->expectsOutputToContain('- private_probe_coverage: PASS')
            ->expectsOutputToContain('Overall: PASS')
            ->assertExitCode(0);
    }

    public function test_ops_drill_can_save_report_artifact(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-12 19:30:00', 'UTC'));
        Storage::fake('local');

        $this->artisan('wids:ops-drill --format=json --save')
            ->expectsOutputToContain('Artifact saved:')
            ->assertExitCode(0);

        $files = Storage::disk('local')->allFiles('ops-drills');

        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.json', $files[0]);
    }
}
