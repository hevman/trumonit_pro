<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\MonitoredUrl;
use App\Models\PrivateProbeScanTask;
use App\Models\Tenant;
use App\Models\UrlSnapshot;
use App\Models\User;
use App\Services\PrivateProbeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PrivateProbeTaskContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_queue_task_probe_can_claim_and_submit_signed_result(): void
    {
        config()->set('wids.private_probes.enabled', true);
        config()->set('wids.private_probes.allowed_tiers', ['lite', 'pro', 'enterprise']);
        config()->set('wids.private_probes.result_signature_required', true);

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
            'url' => 'https://example.com/api',
            'display_name' => 'API',
            'is_active' => true,
            'frequency_minutes' => 5,
            'timeout_seconds' => 20,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 299,
            'alert_after_failures' => 1,
        ]);

        $createdProbe = app(PrivateProbeService::class)->createForTenant(
            tenant: $tenant,
            name: 'Probe A',
            createdByUserId: $owner->id,
            regionHint: 'eu-west',
            capabilities: ['http' => true]
        );

        $probe = $createdProbe['probe'];
        $probeToken = (string) $createdProbe['token'];

        Sanctum::actingAs($owner, ['wids.read', 'wids.write']);

        $queueResponse = $this->postJson('/api/v1/private-probes/'.$probe->id.'/tasks', [
            'monitored_url_id' => $monitor->id,
            'priority' => 10,
            'meta' => ['origin' => 'api-test'],
        ]);

        $queueResponse->assertStatus(201);
        $taskId = (int) $queueResponse->json('task.id');

        $claimResponse = $this
            ->withHeader('Authorization', 'Bearer '.$probeToken)
            ->postJson('/api/v1/private-probes/tasks/claim');

        $claimResponse->assertOk();
        $claimResponse->assertJsonPath('task.id', $taskId);
        $leaseToken = (string) $claimResponse->json('task.lease_token');

        $payload = [
            'lease_token' => $leaseToken,
            'result' => [
                'request_url' => $monitor->url,
                'final_url' => $monitor->url,
                'http_status' => 500,
                'response_time_ms' => 321,
                'fetched_at' => now()->toISOString(),
                'meta' => ['agent' => 'probe-a'],
            ],
        ];

        $rawBody = (string) json_encode($payload);
        $signature = hash_hmac('sha256', $rawBody, $leaseToken);

        $this->call(
            'POST',
            '/api/v1/private-probes/tasks/'.$taskId.'/result',
            [],
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer '.$probeToken,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_WIDS_TASK_SIGNATURE' => $signature,
            ],
            $rawBody
        )->assertOk()->assertJsonPath('task.status', 'completed');

        $task = PrivateProbeScanTask::query()->findOrFail($taskId);
        $snapshot = UrlSnapshot::query()->latest('id')->firstOrFail();
        $incident = Incident::query()->latest('id')->firstOrFail();

        $this->assertSame('completed', $task->status);
        $this->assertSame($monitor->id, $snapshot->monitored_url_id);
        $this->assertSame('private_probe', (string) data_get($snapshot->meta, 'source'));
        $this->assertSame($probe->id, (int) data_get($snapshot->meta, 'private_probe.probe_id'));
        $this->assertSame('unexpected_status', $incident->kind);
    }

    public function test_result_with_invalid_signature_is_rejected(): void
    {
        config()->set('wids.private_probes.enabled', true);
        config()->set('wids.private_probes.allowed_tiers', ['lite', 'pro', 'enterprise']);
        config()->set('wids.private_probes.result_signature_required', true);

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
            'url' => 'https://example.com/api',
            'display_name' => 'API',
            'is_active' => true,
            'frequency_minutes' => 5,
            'timeout_seconds' => 20,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 299,
            'alert_after_failures' => 1,
        ]);

        $createdProbe = app(PrivateProbeService::class)->createForTenant(
            tenant: $tenant,
            name: 'Probe A',
            createdByUserId: $owner->id,
            regionHint: 'eu-west'
        );

        $probe = $createdProbe['probe'];
        $probeToken = (string) $createdProbe['token'];

        Sanctum::actingAs($owner, ['wids.read', 'wids.write']);

        $taskId = (int) $this->postJson('/api/v1/private-probes/'.$probe->id.'/tasks', [
            'monitored_url_id' => $monitor->id,
        ])->json('task.id');

        $leaseToken = (string) $this
            ->withHeader('Authorization', 'Bearer '.$probeToken)
            ->postJson('/api/v1/private-probes/tasks/claim')
            ->json('task.lease_token');

        $payload = [
            'lease_token' => $leaseToken,
            'result' => [
                'request_url' => $monitor->url,
                'http_status' => 200,
            ],
        ];

        $rawBody = (string) json_encode($payload);

        $this->call(
            'POST',
            '/api/v1/private-probes/tasks/'.$taskId.'/result',
            [],
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer '.$probeToken,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_WIDS_TASK_SIGNATURE' => 'sha256=deadbeef',
            ],
            $rawBody
        )->assertStatus(422)->assertJsonValidationErrors('signature');

        $this->assertSame('leased', (string) PrivateProbeScanTask::query()->findOrFail($taskId)->status);
    }

    public function test_probe_can_reclaim_task_after_lease_expired(): void
    {
        config()->set('wids.private_probes.enabled', true);
        config()->set('wids.private_probes.allowed_tiers', ['lite', 'pro', 'enterprise']);

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
            'url' => 'https://example.com/api',
            'display_name' => 'API',
            'is_active' => true,
            'frequency_minutes' => 5,
            'timeout_seconds' => 20,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 299,
            'alert_after_failures' => 1,
        ]);

        $createdProbe = app(PrivateProbeService::class)->createForTenant(
            tenant: $tenant,
            name: 'Probe A',
            createdByUserId: $owner->id,
            regionHint: 'eu-west'
        );

        $probe = $createdProbe['probe'];
        $probeToken = (string) $createdProbe['token'];

        Sanctum::actingAs($owner, ['wids.read', 'wids.write']);

        $taskId = (int) $this->postJson('/api/v1/private-probes/'.$probe->id.'/tasks', [
            'monitored_url_id' => $monitor->id,
        ])->json('task.id');

        $task = PrivateProbeScanTask::query()->findOrFail($taskId);
        $task->forceFill([
            'status' => 'leased',
            'lease_token_hash' => hash('sha256', 'expired'),
            'lease_expires_at' => now()->subMinute(),
            'claimed_at' => now()->subMinutes(2),
        ])->save();

        $response = $this
            ->withHeader('Authorization', 'Bearer '.$probeToken)
            ->postJson('/api/v1/private-probes/tasks/claim');

        $response->assertOk();
        $response->assertJsonPath('task.id', $taskId);
        $response->assertJsonPath('task.status', 'leased');
        $this->assertNotEmpty((string) $response->json('task.lease_token'));

        $task->refresh();

        $this->assertSame('leased', (string) $task->status);
        $this->assertNotNull($task->lease_expires_at);
        $this->assertTrue($task->lease_expires_at->isFuture());
        $this->assertNotNull($task->claimed_at);
    }
}

