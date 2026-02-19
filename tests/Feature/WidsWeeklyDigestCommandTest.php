<?php

namespace Tests\Feature;

use App\Jobs\SendWeeklyDigestEmailJob;
use App\Mail\WeeklyDigestMail;
use App\Models\Incident;
use App\Models\MonitoredUrl;
use App\Models\Tenant;
use App\Models\TenantUsageMetric;
use App\Models\UrlSnapshot;
use App\Models\User;
use App\Services\BillingUsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WidsWeeklyDigestCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_weekly_digest_command_dispatches_jobs_for_tenant_recipients(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-12 12:00:00', 'UTC'));

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
            'notification_channels' => [
                'email_recipients' => ['ops@example.com', 'cto@example.com'],
            ],
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
            'frequency_minutes' => 5,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        $snapshot = UrlSnapshot::query()->create([
            'monitored_url_id' => $monitor->id,
            'fetched_at' => now()->subDays(2),
            'request_url' => $monitor->url,
            'final_url' => $monitor->url,
            'http_status' => 200,
            'headers' => [],
            'response_time_ms' => 100,
            'body_size' => 10,
            'body_sha256' => hash('sha256', 'digest'),
        ]);

        Incident::query()->create([
            'tenant_id' => $tenant->id,
            'monitored_url_id' => $monitor->id,
            'previous_snapshot_id' => null,
            'current_snapshot_id' => $snapshot->id,
            'kind' => 'unexpected_status',
            'severity' => 90,
            'status' => 'resolved',
            'summary' => 'Digest incident',
            'details' => ['http_status' => 500],
            'detected_at' => now()->subDays(2)->subHour(),
            'last_seen_at' => now()->subDays(2)->subMinutes(30),
            'resolved_at' => now()->subDays(2),
            'occurrences' => 1,
            'fingerprint' => hash('sha256', 'digest-incident'),
        ]);

        TenantUsageMetric::query()->create([
            'tenant_id' => $tenant->id,
            'period_type' => BillingUsageService::PERIOD_DAY,
            'period_start_date' => now()->subDays(2)->toDateString(),
            'metric_key' => BillingUsageService::METRIC_SCANS_TOTAL,
            'value' => 12,
        ]);

        Queue::fake();

        $this->artisan('wids:weekly-digest', [
            '--tenant_id' => $tenant->id,
            '--week_start' => now()->subDays(6)->toDateString(),
            '--week_end' => now()->toDateString(),
        ])->assertSuccessful();

        Queue::assertPushed(SendWeeklyDigestEmailJob::class, 2);
        Queue::assertPushed(SendWeeklyDigestEmailJob::class, function (SendWeeklyDigestEmailJob $job) use ($tenant): bool {
            return $job->tenantId === (int) $tenant->id
                && in_array($job->recipient, ['ops@example.com', 'cto@example.com'], true);
        });
    }

    public function test_weekly_digest_command_dry_run_does_not_queue_any_job(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
            'notification_channels' => [
                'email_recipients' => ['ops@example.com'],
            ],
        ]);

        Queue::fake();

        $this->artisan('wids:weekly-digest', [
            '--tenant_id' => $tenant->id,
            '--week_start' => '2026-02-03',
            '--week_end' => '2026-02-09',
            '--dry-run' => true,
        ])->assertSuccessful();

        Queue::assertNothingPushed();
    }

    public function test_weekly_digest_command_sync_mode_sends_email_immediately(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-12 12:00:00', 'UTC'));

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
            'notification_channels' => [
                'email_recipients' => ['ops@example.com'],
            ],
        ]);

        Mail::fake();

        $this->artisan('wids:weekly-digest', [
            '--tenant_id' => $tenant->id,
            '--week_start' => '2026-02-03',
            '--week_end' => '2026-02-09',
            '--recipient' => ['digest@example.com'],
            '--sync' => true,
        ])->assertSuccessful();

        Mail::assertSent(WeeklyDigestMail::class, function (WeeklyDigestMail $mail): bool {
            $tenantName = (string) ($mail->digest['tenant']['name'] ?? '');
            $windowStart = (string) ($mail->digest['window']['start'] ?? '');
            $windowEnd = (string) ($mail->digest['window']['end'] ?? '');

            return $tenantName === 'Acme'
                && $windowStart === '2026-02-03'
                && $windowEnd === '2026-02-09';
        });
    }
}

