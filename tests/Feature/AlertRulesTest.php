<?php

namespace Tests\Feature;

use App\Jobs\ScanMonitoredUrlJob;
use App\Models\MonitoredUrl;
use App\Models\Tenant;
use App\Models\UrlSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AlertRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_alert_rules_tracks_consecutive_failures_and_threshold(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-11 12:00:00', 'UTC'));

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $monitoredUrl = MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $user->id,
            'url' => 'https://example.com',
            'display_name' => 'Example',
            'is_active' => true,
            'frequency_minutes' => 5,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
            'alert_after_failures' => 2,
            'alert_cooldown_minutes' => 0,
            'consecutive_failures' => 0,
        ]);

        $job = new ScanMonitoredUrlJob($monitoredUrl->id);
        $method = new \ReflectionMethod(ScanMonitoredUrlJob::class, 'applyAlertRules');
        $method->setAccessible(true);

        $downSnapshot1 = UrlSnapshot::query()->create([
            'monitored_url_id' => $monitoredUrl->id,
            'fetched_at' => now(),
            'request_url' => $monitoredUrl->url,
            'final_url' => $monitoredUrl->url,
            'http_status' => 500,
            'headers' => [],
            'response_time_ms' => 123,
            'body_size' => 10,
            'body_sha256' => hash('sha256', 'err1'),
        ]);

        $state1 = $method->invoke($job, $monitoredUrl, $downSnapshot1);

        $this->assertTrue($state1['is_down']);
        $this->assertTrue($state1['suppress_content_signals']);
        $this->assertSame(1, $state1['consecutive_failures']);
        $this->assertFalse($state1['should_emit']);
        $this->assertSame(1, (int) $monitoredUrl->consecutive_failures);

        $downSnapshot2 = UrlSnapshot::query()->create([
            'monitored_url_id' => $monitoredUrl->id,
            'fetched_at' => now()->addMinute(),
            'request_url' => $monitoredUrl->url,
            'final_url' => $monitoredUrl->url,
            'http_status' => 500,
            'headers' => [],
            'response_time_ms' => 125,
            'body_size' => 10,
            'body_sha256' => hash('sha256', 'err2'),
        ]);

        $state2 = $method->invoke($job, $monitoredUrl, $downSnapshot2);

        $this->assertTrue($state2['is_down']);
        $this->assertSame(2, $state2['consecutive_failures']);
        $this->assertTrue($state2['should_emit']);
        $this->assertSame(2, (int) $monitoredUrl->consecutive_failures);

        $upSnapshot = UrlSnapshot::query()->create([
            'monitored_url_id' => $monitoredUrl->id,
            'fetched_at' => now()->addMinutes(2),
            'request_url' => $monitoredUrl->url,
            'final_url' => $monitoredUrl->url,
            'http_status' => 200,
            'headers' => [],
            'response_time_ms' => 120,
            'body_size' => 10,
            'body_sha256' => hash('sha256', 'ok'),
        ]);

        $state3 = $method->invoke($job, $monitoredUrl, $upSnapshot);

        $this->assertFalse($state3['is_down']);
        $this->assertFalse($state3['suppress_content_signals']);
        $this->assertSame(0, $state3['consecutive_failures']);
        $this->assertTrue($state3['should_emit']);
        $this->assertSame(0, (int) $monitoredUrl->consecutive_failures);
    }

    public function test_detect_incidents_skips_content_and_rules_when_suppressed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-11 12:00:00', 'UTC'));

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $monitoredUrl = MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $user->id,
            'url' => 'https://example.com',
            'display_name' => 'Example',
            'is_active' => true,
            'frequency_minutes' => 5,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
            'content_rules' => ['enabled' => true, 'deny_keywords' => ['hacked by']],
        ]);

        $previous = UrlSnapshot::query()->create([
            'monitored_url_id' => $monitoredUrl->id,
            'fetched_at' => now()->subMinute(),
            'request_url' => $monitoredUrl->url,
            'final_url' => $monitoredUrl->url,
            'http_status' => 200,
            'headers' => [],
            'response_time_ms' => 100,
            'body_size' => 10,
            'body_sha256' => hash('sha256', 'ok'),
            'screenshot_phash' => '00',
        ]);

        $current = UrlSnapshot::query()->create([
            'monitored_url_id' => $monitoredUrl->id,
            'fetched_at' => now(),
            'request_url' => $monitoredUrl->url,
            'final_url' => $monitoredUrl->url,
            'http_status' => 500,
            'headers' => [],
            'response_time_ms' => 120,
            'body_size' => 10,
            'body_sha256' => hash('sha256', 'changed'),
            'screenshot_phash' => 'ff',
        ]);

        $job = new ScanMonitoredUrlJob($monitoredUrl->id);
        $method = new \ReflectionMethod(ScanMonitoredUrlJob::class, 'detectIncidents');
        $method->setAccessible(true);

        /** @var list<array<string, mixed>> $incidents */
        $incidents = $method->invoke($job, $monitoredUrl, $current, $previous, null, 'hacked by', true);

        $kinds = collect($incidents)->pluck('kind')->values()->all();

        $this->assertContains('unexpected_status', $kinds);
        $this->assertNotContains('content_change', $kinds);
        $this->assertNotContains('appearance_change', $kinds);
        $this->assertNotContains('content_rule_violation', $kinds);
    }

    public function test_detect_incidents_filters_low_confidence_browser_signals(): void
    {
        config()->set('wids.incidents.noise_controls', [
            'browser_console_error_min_count' => 2,
            'browser_request_failed_min_count' => 2,
            'browser_request_failed_ignore_patterns' => ['net::ERR_ABORTED', 'favicon.ico'],
        ]);

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $monitoredUrl = MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $user->id,
            'url' => 'https://example.com',
            'display_name' => 'Example',
            'is_active' => true,
            'frequency_minutes' => 5,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        $current = UrlSnapshot::query()->create([
            'monitored_url_id' => $monitoredUrl->id,
            'fetched_at' => now(),
            'request_url' => $monitoredUrl->url,
            'final_url' => $monitoredUrl->url,
            'http_status' => 200,
            'headers' => [],
            'response_time_ms' => 80,
            'body_size' => 20,
            'body_sha256' => hash('sha256', 'ok'),
            'meta' => [
                'browser' => [
                    'console_errors' => ['single minor warning'],
                    'page_errors' => [],
                    'request_failed' => [
                        ['url' => 'https://example.com/app.js', 'error' => 'net::ERR_ABORTED'],
                    ],
                ],
            ],
        ]);

        $job = new ScanMonitoredUrlJob($monitoredUrl->id);
        $method = new \ReflectionMethod(ScanMonitoredUrlJob::class, 'detectIncidents');
        $method->setAccessible(true);

        /** @var list<array<string, mixed>> $incidents */
        $incidents = $method->invoke($job, $monitoredUrl, $current, null, null, null, false);

        $kinds = collect($incidents)->pluck('kind')->values()->all();

        $this->assertNotContains('browser_console_error', $kinds);
        $this->assertNotContains('browser_request_failed', $kinds);
    }

    public function test_detect_incidents_adds_alert_gate_and_scan_context_details(): void
    {
        config()->set('wids.incidents.noise_controls', [
            'browser_console_error_min_count' => 2,
            'browser_request_failed_min_count' => 2,
            'browser_request_failed_ignore_patterns' => ['source map'],
        ]);

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $monitoredUrl = MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $user->id,
            'url' => 'https://example.com',
            'display_name' => 'Example',
            'is_active' => true,
            'frequency_minutes' => 5,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
            'alert_after_failures' => 3,
        ]);
        $monitoredUrl->forceFill(['consecutive_failures' => 3])->save();

        $current = UrlSnapshot::query()->create([
            'monitored_url_id' => $monitoredUrl->id,
            'fetched_at' => now(),
            'request_url' => $monitoredUrl->url,
            'final_url' => $monitoredUrl->url,
            'http_status' => 200,
            'headers' => [],
            'response_time_ms' => 95,
            'body_size' => 20,
            'body_sha256' => hash('sha256', 'ok'),
            'meta' => [
                'browser' => [
                    'console_errors' => ['e1', 'e2'],
                    'page_errors' => ['e3'],
                    'request_failed' => [
                        ['url' => 'https://example.com/a.js', 'error' => 'ERR_CONNECTION_RESET'],
                        ['url' => 'https://example.com/b.js', 'error' => 'Timeout exceeded'],
                        ['url' => 'https://example.com/app.map', 'error' => 'source map 404'],
                    ],
                ],
            ],
        ]);

        $job = new ScanMonitoredUrlJob($monitoredUrl->id);
        $method = new \ReflectionMethod(ScanMonitoredUrlJob::class, 'detectIncidents');
        $method->setAccessible(true);

        /** @var list<array<string, mixed>> $incidents */
        $incidents = $method->invoke($job, $monitoredUrl, $current, null, null, null, false);

        $byKind = collect($incidents)->keyBy('kind');

        $this->assertTrue($byKind->has('browser_console_error'));
        $this->assertTrue($byKind->has('browser_request_failed'));

        $consoleDetails = $byKind->get('browser_console_error')['details'] ?? [];
        $requestDetails = $byKind->get('browser_request_failed')['details'] ?? [];

        $this->assertSame(3, $consoleDetails['signal_count'] ?? null);
        $this->assertSame(2, $requestDetails['request_failed_relevant_count'] ?? null);
        $this->assertSame(1, $requestDetails['request_failed_ignored_count'] ?? null);

        foreach (['browser_console_error', 'browser_request_failed'] as $kind) {
            $details = $byKind->get($kind)['details'] ?? [];
            $alertGate = is_array($details['alert_gate'] ?? null) ? $details['alert_gate'] : [];
            $scanContext = is_array($details['scan_context'] ?? null) ? $details['scan_context'] : [];

            $this->assertSame(3, $alertGate['alert_after_failures'] ?? null);
            $this->assertSame(3, $alertGate['consecutive_failures'] ?? null);
            $this->assertTrue($alertGate['emitted_after_threshold'] ?? false);

            $this->assertSame('http', $scanContext['scan_mode'] ?? null);
            $this->assertSame(200, $scanContext['http_status'] ?? null);
            $this->assertSame(95, $scanContext['response_time_ms'] ?? null);
            $this->assertFalse($scanContext['suppress_content_signals'] ?? true);
        }
    }
}
