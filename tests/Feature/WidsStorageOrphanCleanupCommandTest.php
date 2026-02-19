<?php

namespace Tests\Feature;

use App\Models\MonitoredUrl;
use App\Models\Tenant;
use App\Models\UrlSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WidsStorageOrphanCleanupCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_removes_orphan_files_and_keeps_referenced_files(): void
    {
        config()->set('wids.storage_lifecycle.orphan_cleanup_enabled', true);
        config()->set('wids.storage_lifecycle.archive_prefix', 'wids/archive');
        config()->set('wids.storage_lifecycle.orphan_cleanup_grace_days', 0);

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

        $referencedPath = 'wids/archive/body/'.$monitor->id.'/referenced.html';
        $oldOrphanPath = 'wids/archive/body/'.$monitor->id.'/old-orphan.html';

        Storage::put($referencedPath, 'referenced');
        Storage::put($oldOrphanPath, 'old orphan');

        UrlSnapshot::query()->create([
            'monitored_url_id' => $monitor->id,
            'fetched_at' => now()->subDays(3),
            'request_url' => 'https://example.com',
            'final_url' => 'https://example.com',
            'http_status' => 200,
            'headers' => [],
            'response_time_ms' => 40,
            'body_size' => 10,
            'body_bytes_stored' => 10,
            'body_sha256' => hash('sha256', 'referenced'),
            'body_storage_path' => $referencedPath,
            'storage_tier' => 'archive',
            'archived_at' => now()->subDays(2),
            'meta' => [],
        ]);

        $this->artisan('wids:storage-orphan-cleanup --grace-days=0')
            ->assertExitCode(0);

        $this->assertTrue(Storage::exists($referencedPath));
        $this->assertFalse(Storage::exists($oldOrphanPath));
    }

    public function test_command_dry_run_does_not_delete_old_orphans(): void
    {
        config()->set('wids.storage_lifecycle.orphan_cleanup_enabled', true);
        config()->set('wids.storage_lifecycle.archive_prefix', 'wids/archive');
        config()->set('wids.storage_lifecycle.orphan_cleanup_grace_days', 0);

        $orphanPath = 'wids/archive/screenshot/1/orphan.png';

        Storage::put($orphanPath, 'orphan');

        $this->artisan('wids:storage-orphan-cleanup --dry-run --grace-days=0')
            ->assertExitCode(0);

        $this->assertTrue(Storage::exists($orphanPath));
    }
}
