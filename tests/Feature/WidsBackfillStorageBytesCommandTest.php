<?php

namespace Tests\Feature;

use App\Models\MonitoredUrl;
use App\Models\Tenant;
use App\Models\UrlSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WidsBackfillStorageBytesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_storage_bytes_updates_existing_snapshot_sizes(): void
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

        $bodyPath = 'wids/bodies/'.$monitor->id.'/1.html';
        $screenshotPath = 'wids/screenshots/'.$monitor->id.'/1.png';

        Storage::put($bodyPath, str_repeat('A', 123));
        Storage::put($screenshotPath, str_repeat('B', 77));

        $snapshot = UrlSnapshot::query()->create([
            'monitored_url_id' => $monitor->id,
            'fetched_at' => now(),
            'request_url' => $monitor->url,
            'final_url' => $monitor->url,
            'http_status' => 200,
            'headers' => [],
            'response_time_ms' => 100,
            'body_size' => 123,
            'body_sha256' => hash('sha256', 'body'),
            'body_storage_path' => $bodyPath,
            'screenshot_storage_path' => $screenshotPath,
            'meta' => [],
        ]);

        $this->artisan('wids:backfill-storage-bytes')
            ->assertExitCode(0);

        $snapshot->refresh();

        $this->assertSame(123, (int) $snapshot->body_bytes_stored);
        $this->assertSame(77, (int) $snapshot->screenshot_bytes_stored);
        $this->assertSame(123, (int) ($snapshot->meta['body_bytes_stored'] ?? 0));
        $this->assertSame(77, (int) ($snapshot->meta['screenshot']['bytes'] ?? 0));
    }

    public function test_backfill_storage_bytes_dry_run_does_not_persist_changes(): void
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

        $bodyPath = 'wids/bodies/'.$monitor->id.'/2.html';
        Storage::put($bodyPath, str_repeat('A', 50));

        $snapshot = UrlSnapshot::query()->create([
            'monitored_url_id' => $monitor->id,
            'fetched_at' => now(),
            'request_url' => $monitor->url,
            'final_url' => $monitor->url,
            'http_status' => 200,
            'headers' => [],
            'response_time_ms' => 100,
            'body_size' => 50,
            'body_sha256' => hash('sha256', 'body'),
            'body_storage_path' => $bodyPath,
            'meta' => [],
        ]);

        $this->artisan('wids:backfill-storage-bytes --dry-run')
            ->assertExitCode(0);

        $snapshot->refresh();

        $this->assertNull($snapshot->body_bytes_stored);
    }
}
