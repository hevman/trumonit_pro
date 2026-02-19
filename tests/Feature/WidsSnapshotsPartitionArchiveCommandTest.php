<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\MonitoredUrl;
use App\Models\Tenant;
use App\Models\UrlSnapshot;
use App\Models\UrlSnapshotArchive;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WidsSnapshotsPartitionArchiveCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_syncs_archive_rows_and_purges_only_safe_source_snapshots(): void
    {
        config()->set('wids.snapshot_partition.enabled', true);
        config()->set('wids.snapshot_partition.after_days', 1);

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
            'plan_tier' => 'lite',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $monitorPurge = $this->createMonitor($tenant->id, $owner->id, 'https://example.com/purge');
        $monitorBaseline = $this->createMonitor($tenant->id, $owner->id, 'https://example.com/baseline');
        $monitorIncident = $this->createMonitor($tenant->id, $owner->id, 'https://example.com/incident');
        $monitorLatestOnly = $this->createMonitor($tenant->id, $owner->id, 'https://example.com/latest');

        $purgeCandidate = $this->createArchivedSnapshot($monitorPurge->id, now()->subDays(5));
        $this->createArchivedSnapshot($monitorPurge->id, now());

        $baselineSnapshot = $this->createArchivedSnapshot($monitorBaseline->id, now()->subDays(5));
        $monitorBaseline->forceFill([
            'baseline_snapshot_id' => $baselineSnapshot->id,
            'baseline_set_at' => now()->subDays(4),
        ])->save();

        $incidentSnapshot = $this->createArchivedSnapshot($monitorIncident->id, now()->subDays(5));
        Incident::query()->create([
            'tenant_id' => $tenant->id,
            'monitored_url_id' => $monitorIncident->id,
            'current_snapshot_id' => $incidentSnapshot->id,
            'kind' => 'unexpected_status',
            'severity' => 70,
            'status' => 'open',
            'summary' => 'Incident reference',
            'details' => [],
            'detected_at' => now()->subDays(4),
        ]);

        $latestOnlySnapshot = $this->createArchivedSnapshot($monitorLatestOnly->id, now()->subDays(5));

        $this->artisan('wids:snapshots-partition-archive --purge-source')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('url_snapshots', ['id' => $purgeCandidate->id]);
        $this->assertDatabaseHas('url_snapshots', ['id' => $baselineSnapshot->id]);
        $this->assertDatabaseHas('url_snapshots', ['id' => $incidentSnapshot->id]);
        $this->assertDatabaseHas('url_snapshots', ['id' => $latestOnlySnapshot->id]);

        $this->assertDatabaseHas('url_snapshot_archives', ['original_snapshot_id' => $purgeCandidate->id]);
        $this->assertDatabaseHas('url_snapshot_archives', ['original_snapshot_id' => $baselineSnapshot->id]);
        $this->assertDatabaseHas('url_snapshot_archives', ['original_snapshot_id' => $incidentSnapshot->id]);
        $this->assertDatabaseHas('url_snapshot_archives', ['original_snapshot_id' => $latestOnlySnapshot->id]);
    }

    public function test_command_dry_run_does_not_write_archive_rows_or_delete_source_rows(): void
    {
        config()->set('wids.snapshot_partition.enabled', true);
        config()->set('wids.snapshot_partition.after_days', 1);

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
            'plan_tier' => 'lite',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $monitor = $this->createMonitor($tenant->id, $owner->id, 'https://example.com/dry-run');
        $snapshot = $this->createArchivedSnapshot($monitor->id, now()->subDays(10));

        $this->artisan('wids:snapshots-partition-archive --purge-source --dry-run')
            ->assertExitCode(0);

        $this->assertDatabaseHas('url_snapshots', ['id' => $snapshot->id]);
        $this->assertSame(0, UrlSnapshotArchive::query()->count());
    }

    private function createMonitor(int $tenantId, int $ownerId, string $url): MonitoredUrl
    {
        return MonitoredUrl::query()->create([
            'tenant_id' => $tenantId,
            'created_by_user_id' => $ownerId,
            'url' => $url,
            'display_name' => $url,
            'is_active' => true,
            'frequency_minutes' => 5,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);
    }

    private function createArchivedSnapshot(int $monitorId, \Illuminate\Support\Carbon $fetchedAt): UrlSnapshot
    {
        return UrlSnapshot::query()->create([
            'monitored_url_id' => $monitorId,
            'fetched_at' => $fetchedAt,
            'request_url' => 'https://example.com',
            'final_url' => 'https://example.com',
            'http_status' => 200,
            'headers' => [],
            'response_time_ms' => 45,
            'body_size' => 12,
            'body_bytes_stored' => 12,
            'body_sha256' => hash('sha256', (string) $monitorId.$fetchedAt->toISOString()),
            'body_storage_path' => 'wids/archive/body/'.$monitorId.'/'.uniqid('', true).'.html',
            'screenshot_storage_path' => 'wids/archive/screenshot/'.$monitorId.'/'.uniqid('', true).'.png',
            'screenshot_bytes_stored' => 33,
            'storage_tier' => 'archive',
            'archived_at' => $fetchedAt->copy()->addHour(),
            'meta' => [],
        ]);
    }
}
