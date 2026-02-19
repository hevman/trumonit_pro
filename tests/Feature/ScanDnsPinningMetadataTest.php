<?php

namespace Tests\Feature;

use App\Jobs\ScanMonitoredUrlJob;
use App\Models\MonitoredUrl;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UrlSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScanDnsPinningMetadataTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_records_resolved_targets_metadata(): void
    {
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
            'url' => 'https://example.com/pinning',
            'display_name' => 'Pinning',
            'is_active' => true,
            'frequency_minutes' => 5,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
            'next_scan_at' => now(),
        ]);

        Http::fake([
            'https://example.com/*' => Http::response('<html>ok</html>', 200, [
                'Content-Type' => 'text/html',
            ]),
        ]);

        (new ScanMonitoredUrlJob($monitoredUrl->id))->handle();

        $snapshot = UrlSnapshot::query()
            ->where('monitored_url_id', $monitoredUrl->id)
            ->latest('id')
            ->firstOrFail();

        $meta = is_array($snapshot->meta) ? $snapshot->meta : [];
        $resolvedTargets = is_array($meta['resolved_targets'] ?? null) ? $meta['resolved_targets'] : [];

        $this->assertNotEmpty($resolvedTargets);
        $this->assertSame('example.com', $resolvedTargets[0]['host'] ?? null);
        $this->assertIsString($resolvedTargets[0]['connect_ip'] ?? null);
        $this->assertNotSame('', $resolvedTargets[0]['connect_ip'] ?? '');
    }
}
