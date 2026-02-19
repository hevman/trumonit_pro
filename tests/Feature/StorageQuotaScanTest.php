<?php

namespace Tests\Feature;

use App\Jobs\ScanMonitoredUrlJob;
use App\Models\MonitoredUrl;
use App\Models\Tenant;
use App\Models\UrlSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorageQuotaScanTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_skips_body_storage_when_tenant_quota_is_exceeded(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-11 12:00:00', 'UTC'));

        config()->set('wids.plan_tiers.lite.storage_quota_mb', 1);
        config()->set('wids.screenshot.enabled', false);
        config()->set('wids.http.max_body_bytes', 2_000_000);

        Storage::fake('local');

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
            'url' => 'https://example.com/quota-test',
            'display_name' => 'Quota Test',
            'is_active' => true,
            'frequency_minutes' => 5,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
            'scan_mode' => 'http',
        ]);

        $body = str_repeat('A', 1_500_000);

        Http::fake([
            'https://example.com/*' => Http::response($body, 200, ['Content-Type' => 'text/html']),
        ]);

        $job = new ScanMonitoredUrlJob($monitor->id, 1000, 0);
        $job->handle();

        $snapshot = UrlSnapshot::query()
            ->where('monitored_url_id', $monitor->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(200, $snapshot->http_status);
        $this->assertGreaterThan(1_000_000, (int) $snapshot->body_size);
        $this->assertSame(0, (int) ($snapshot->body_bytes_stored ?? 0));
        $this->assertNull($snapshot->body_storage_path);
        $this->assertTrue((bool) ($snapshot->meta['storage_quota_exceeded'] ?? false));
    }
}
