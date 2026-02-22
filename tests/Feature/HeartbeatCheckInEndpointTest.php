<?php

namespace Tests\Feature;

use App\Jobs\ScanMonitoredUrlJob;
use App\Models\MonitoredUrl;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class HeartbeatCheckInEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_heartbeat_check_in_updates_monitor_and_dispatches_scan(): void
    {
        Queue::fake();

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
            'url' => 'https://example.com/cron',
            'scan_mode' => 'heartbeat',
            'is_active' => true,
            'frequency_minutes' => 5,
            'timeout_seconds' => 5,
            'follow_redirects' => false,
            'expected_status_min' => 200,
            'expected_status_max' => 299,
            'content_rules' => [
                'heartbeat' => [
                    'token' => 'HeartbeatToken1234567890ABCDEF',
                    'grace_minutes' => 3,
                ],
            ],
        ]);

        $response = $this->getJson('/api/heartbeat/HeartbeatToken1234567890ABCDEF');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('monitor_id', $monitor->id);

        $monitor->refresh();
        $heartbeat = is_array($monitor->content_rules['heartbeat'] ?? null) ? $monitor->content_rules['heartbeat'] : [];

        $this->assertNotEmpty($heartbeat['last_check_in_at'] ?? null);

        Queue::assertPushed(ScanMonitoredUrlJob::class, function (ScanMonitoredUrlJob $job) use ($monitor): bool {
            return $job->monitoredUrlId === $monitor->id;
        });
    }
}

