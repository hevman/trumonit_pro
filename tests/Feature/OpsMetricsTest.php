<?php

namespace Tests\Feature;

use App\Models\MonitoredUrl;
use App\Models\Tenant;
use App\Models\UrlSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OpsMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_metrics_endpoint_is_disabled_by_default(): void
    {
        $this->get('/ops/metrics')->assertNotFound();
    }

    public function test_metrics_endpoint_requires_token_when_configured(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-11 12:00:00', 'UTC'));

        config()->set('ops.metrics.enabled', true);
        config()->set('ops.metrics.require_token', true);
        config()->set('ops.metrics.token', 'secret-token');

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
            'url' => 'https://example.com',
            'display_name' => 'Example',
            'is_active' => true,
            'next_scan_at' => now()->subMinute(),
            'frequency_minutes' => 5,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        UrlSnapshot::query()->create([
            'monitored_url_id' => $monitor->id,
            'fetched_at' => now()->subMinutes(2),
            'request_url' => 'https://example.com',
            'final_url' => 'https://example.com',
            'http_status' => 500,
            'headers' => [],
            'response_time_ms' => 120,
            'body_size' => 20,
            'body_bytes_stored' => 20,
            'body_sha256' => hash('sha256', 'error'),
            'error_message' => 'Timeout',
            'meta' => [
                'queue_wait_ms' => 1500,
            ],
        ]);

        $this->get('/ops/metrics')->assertForbidden();

        $response = $this
            ->withHeader('Authorization', 'Bearer secret-token')
            ->get('/ops/metrics');

        $response->assertOk();
        $response->assertSee('TruMonit_monitored_urls_total');
        $response->assertSee('TruMonit_monitored_urls_due_total');
        $response->assertSee('TruMonit_scan_lag_seconds_p95');
        $response->assertSee('TruMonit_queue_wait_ms_last_1h_p95');
        $response->assertSee('TruMonit_error_rate_last_5m');
        $response->assertSee('TruMonit_storage_bytes_total');
        $response->assertSee('TruMonit_storage_snapshots_total');
        $response->assertSee('TruMonit_storage_bytes_by_tier');
        $response->assertSee('TruMonit_multi_region_verifications_last_1h_total');
        $response->assertSee('TruMonit_multi_region_excluded_regions_last_1h_total');
        $response->assertSee('TruMonit_multi_region_region_attempts_last_1h');
        $response->assertSee('TruMonit_multi_region_region_failure_ratio_last_1h');
        $response->assertSee('TruMonit_private_probes_total');
        $response->assertSee('TruMonit_private_probes_online_total');
        $response->assertSee('TruMonit_private_probes_stale_total');
        $response->assertSee('TruMonit_private_probes_policy_auto_disabled_total');
        $response->assertSee('TruMonit_private_probes_residency_violations_total');
        $response->assertSee('TruMonit_private_probe_monitors_total');
        $response->assertSee('TruMonit_private_probe_monitors_due_total');
        $response->assertSee('TruMonit_private_probe_monitors_due_without_online_probe_total');
    }

    public function test_metrics_endpoint_fails_closed_when_token_is_required_but_missing(): void
    {
        config()->set('ops.metrics.enabled', true);
        config()->set('ops.metrics.require_token', true);
        config()->set('ops.metrics.token', null);

        $this->get('/ops/metrics')->assertStatus(503);
    }

    public function test_metrics_endpoint_can_be_public_only_when_explicitly_allowed(): void
    {
        config()->set('ops.metrics.enabled', true);
        config()->set('ops.metrics.require_token', false);
        config()->set('ops.metrics.token', null);

        $response = $this->get('/ops/metrics');

        $response->assertOk();
        $response->assertSee('TruMonit_metrics_generated_at_seconds');
    }

    public function test_metrics_endpoint_respects_ip_allowlist(): void
    {
        config()->set('ops.metrics.enabled', true);
        config()->set('ops.metrics.require_token', false);
        config()->set('ops.metrics.token', null);
        config()->set('ops.allowed_ips', ['10.10.10.10']);

        $this
            ->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->get('/ops/metrics')
            ->assertForbidden();

        $this
            ->withServerVariables(['REMOTE_ADDR' => '10.10.10.10'])
            ->get('/ops/metrics')
            ->assertOk();
    }

    public function test_metrics_endpoint_can_return_cached_payload(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-11 12:00:00', 'UTC'));
        Cache::flush();

        config()->set('ops.metrics.enabled', true);
        config()->set('ops.metrics.require_token', true);
        config()->set('ops.metrics.token', 'secret-token');
        config()->set('ops.metrics.cache_ttl_seconds', 10);

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $owner->id,
            'url' => 'https://example.com/one',
            'display_name' => 'One',
            'is_active' => true,
            'next_scan_at' => now(),
            'frequency_minutes' => 5,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        $first = $this
            ->withHeader('Authorization', 'Bearer secret-token')
            ->get('/ops/metrics');

        $first->assertOk();
        $first->assertSee('TruMonit_monitored_urls_total 1');

        MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $owner->id,
            'url' => 'https://example.com/two',
            'display_name' => 'Two',
            'is_active' => true,
            'next_scan_at' => now(),
            'frequency_minutes' => 5,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        $cached = $this
            ->withHeader('Authorization', 'Bearer secret-token')
            ->get('/ops/metrics');

        $cached->assertOk();
        $cached->assertSee('TruMonit_monitored_urls_total 1');
    }
}
