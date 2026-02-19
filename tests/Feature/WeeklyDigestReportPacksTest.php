<?php

namespace Tests\Feature;

use App\Jobs\SendWeeklyDigestEmailJob;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WeeklyDigestReport;
use App\Services\WeeklyDigestReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WeeklyDigestReportPacksTest extends TestCase
{
    use RefreshDatabase;

    public function test_weekly_digest_command_stores_report_pack_json_and_csv(): void
    {
        Storage::fake('local');
        Queue::fake();
        Carbon::setTestNow(Carbon::parse('2026-02-12 12:00:00', 'UTC'));

        config()->set('wids.reports.weekly_digest.store_report', true);
        config()->set('wids.reports.weekly_digest.report_pack_retention_days', 3650);

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
            'notification_channels' => [
                'email_recipients' => ['ops@example.com'],
            ],
        ]);

        $this->artisan('wids:weekly-digest', [
            '--tenant_id' => $tenant->id,
            '--week_start' => '2026-02-03',
            '--week_end' => '2026-02-09',
        ])->assertSuccessful();

        Queue::assertPushed(SendWeeklyDigestEmailJob::class, 1);

        $report = WeeklyDigestReport::query()->firstOrFail();
        $this->assertSame((int) $tenant->id, (int) $report->tenant_id);
        $this->assertSame('2026-02-03', $report->week_start_date?->toDateString());
        $this->assertSame('2026-02-09', $report->week_end_date?->toDateString());
        $this->assertSame('queue', (string) $report->dispatch_mode);
        Storage::disk('local')->assertExists((string) $report->json_storage_path);
        Storage::disk('local')->assertExists((string) $report->csv_storage_path);

        $jsonPayload = Storage::disk('local')->get((string) $report->json_storage_path);
        $decoded = json_decode($jsonPayload, true);

        $this->assertIsArray($decoded);
        $this->assertSame('2026-02-03', (string) data_get($decoded, 'window.start'));
        $this->assertSame('2026-02-09', (string) data_get($decoded, 'window.end'));

        $csvPayload = Storage::disk('local')->get((string) $report->csv_storage_path);
        $this->assertIsString($csvPayload);
        $this->assertStringContainsString('overview', $csvPayload);
    }

    public function test_owner_can_list_and_download_weekly_report_pack_in_billing(): void
    {
        Storage::fake('local');

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $otherTenant = Tenant::query()->create([
            'name' => 'Other',
            'slug' => 'other',
        ]);

        $otherOwner = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'role' => 'owner',
        ]);

        $digest = [
            'tenant' => [
                'id' => (int) $tenant->id,
                'name' => (string) $tenant->name,
                'slug' => (string) $tenant->slug,
            ],
            'window' => [
                'start' => '2026-02-03',
                'end' => '2026-02-09',
                'generated_at' => now()->toISOString(),
            ],
            'overview' => [
                'monitors_total' => 4,
                'active_monitors' => 4,
                'scans_total' => 120,
                'scans_error_total' => 4,
                'uptime_percent' => 96.67,
                'incidents_opened_total' => 3,
                'incidents_resolved_total' => 2,
                'incidents_critical_total' => 1,
                'mttr_minutes' => 18,
            ],
            'top_incident_kinds' => [],
            'unstable_monitors' => [],
            'daily_usage' => [
                'labels' => ['Mon'],
                'dates' => ['2026-02-03'],
                'scans' => [10],
                'incidents' => [1],
                'api_requests' => [100],
            ],
        ];

        $report = app(WeeklyDigestReportService::class)->storeReport(
            tenant: $tenant,
            digest: $digest,
            recipients: ['ops@example.com'],
            dispatchMode: 'sync',
        );

        $this->actingAs($owner);

        $this->get(route('billing.index'))
            ->assertOk()
            ->assertSee('Weekly report packs')
            ->assertSee('2026-02-03')
            ->assertSee('96.67%');

        $this->get(route('billing.weekly-digest-reports.download', [
            'weeklyDigestReport' => $report->id,
            'format' => 'json',
        ]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json; charset=UTF-8')
            ->assertSee('"window"', false);

        $this->get(route('billing.weekly-digest-reports.download', [
            'weeklyDigestReport' => $report->id,
            'format' => 'csv',
        ]))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertSee('daily_usage');

        $this->actingAs($otherOwner);

        $this->get(route('billing.weekly-digest-reports.download', [
            'weeklyDigestReport' => $report->id,
            'format' => 'json',
        ]))->assertNotFound();
    }
}
