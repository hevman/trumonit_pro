<?php

namespace Tests\Feature;

use App\Models\MonitoredUrl;
use App\Models\Tenant;
use App\Models\UrlSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WidsArchiveSnapshotsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_archive_command_moves_old_snapshot_files_and_marks_archive_tier(): void
    {
        config()->set('wids.storage_lifecycle.enabled', true);
        config()->set('wids.storage_lifecycle.archive_prefix', 'wids/archive');
        config()->set('wids.storage_lifecycle.archive_after_days_by_tier', [
            'lite' => 1,
            'default' => 1,
        ]);

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
            'plan_tier' => 'lite',
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

        $bodyPath = 'wids/bodies/'.$monitor->id.'/old.html';
        $screenshotPath = 'wids/screenshots/'.$monitor->id.'/old.png';

        Storage::put($bodyPath, 'archive-body');
        Storage::put($screenshotPath, 'archive-screenshot');

        $snapshot = UrlSnapshot::query()->create([
            'monitored_url_id' => $monitor->id,
            'fetched_at' => now()->subDays(5),
            'request_url' => 'https://example.com',
            'final_url' => 'https://example.com',
            'http_status' => 200,
            'headers' => [],
            'response_time_ms' => 50,
            'body_size' => 12,
            'body_bytes_stored' => 12,
            'body_sha256' => hash('sha256', 'archive-body'),
            'body_storage_path' => $bodyPath,
            'screenshot_storage_path' => $screenshotPath,
            'screenshot_bytes_stored' => 17,
            'meta' => [],
        ]);

        $this->artisan('wids:archive-snapshots')->assertExitCode(0);

        $snapshot->refresh();

        $this->assertSame('archive', (string) $snapshot->storage_tier);
        $this->assertNotNull($snapshot->archived_at);
        $this->assertIsString($snapshot->body_storage_path);
        $this->assertIsString($snapshot->screenshot_storage_path);
        $this->assertStringStartsWith('wids/archive/body/'.$monitor->id.'/', (string) $snapshot->body_storage_path);
        $this->assertStringStartsWith('wids/archive/screenshot/'.$monitor->id.'/', (string) $snapshot->screenshot_storage_path);
        $this->assertFalse(Storage::exists($bodyPath));
        $this->assertFalse(Storage::exists($screenshotPath));
        $this->assertTrue(Storage::exists((string) $snapshot->body_storage_path));
        $this->assertTrue(Storage::exists((string) $snapshot->screenshot_storage_path));
        $this->assertSame('archive', (string) ($snapshot->meta['storage_tier'] ?? ''));
    }

    public function test_archive_command_dry_run_keeps_original_paths(): void
    {
        config()->set('wids.storage_lifecycle.enabled', true);
        config()->set('wids.storage_lifecycle.archive_prefix', 'wids/archive');
        config()->set('wids.storage_lifecycle.archive_after_days_by_tier', [
            'lite' => 1,
            'default' => 1,
        ]);

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
            'plan_tier' => 'lite',
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

        $bodyPath = 'wids/bodies/'.$monitor->id.'/dry-run.html';
        Storage::put($bodyPath, 'body');

        $snapshot = UrlSnapshot::query()->create([
            'monitored_url_id' => $monitor->id,
            'fetched_at' => now()->subDays(5),
            'request_url' => 'https://example.com',
            'final_url' => 'https://example.com',
            'http_status' => 200,
            'headers' => [],
            'response_time_ms' => 50,
            'body_size' => 4,
            'body_bytes_stored' => 4,
            'body_sha256' => hash('sha256', 'body'),
            'body_storage_path' => $bodyPath,
            'meta' => [],
        ]);

        $this->artisan('wids:archive-snapshots --dry-run')
            ->expectsOutputToContain('dry-run')
            ->assertExitCode(0);

        $snapshot->refresh();

        $this->assertSame('hot', (string) $snapshot->storage_tier);
        $this->assertNull($snapshot->archived_at);
        $this->assertSame($bodyPath, $snapshot->body_storage_path);
        $this->assertTrue(Storage::exists($bodyPath));
    }
}
