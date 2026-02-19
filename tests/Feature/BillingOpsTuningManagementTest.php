<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Services\OpsDrillService;
use App\Services\OpsTuneEnvFileService;
use App\Services\QueueAutoscalingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingOpsTuningManagementTest extends TestCase
{
    use RefreshDatabase;

    private string $testBackupDir = 'framework/testing/ops-tune-backups';

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('ops.tuning.backup_dir', $this->testBackupDir);
        $this->cleanBackupDirectory();
    }

    public function test_owner_can_apply_ops_tuning_from_ops_center(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $envFilePath = $this->createTempEnvFile([
            'APP_NAME=TruMonit',
            'WIDS_QUEUE_AUTOSCALING_TARGET_READY_JOBS_PER_WORKER=120',
            'WIDS_SCAN_TENANT_DISPATCH_CAP_PER_TICK=250',
            'WIDS_QUEUE_AUTOSCALING_MAX_WORKERS=12',
            '',
        ]);

        config()->set('ops.tuning.env_file', $envFilePath);
        config()->set('wids.queue.scan.tenant_dispatch_cap_per_tick', 250);
        config()->set('wids.queue.autoscaling.target_ready_jobs_per_worker', 120);
        config()->set('wids.queue.autoscaling.max_workers', 12);

        $this->fakeOpsTuneSignals();
        $this->actingAs($owner);

        try {
            $response = $this->post(route('ops-center.tune.apply'), [
                'ops_window_hours' => 24,
                'ops_current_workers' => 4,
            ]);

            $response->assertRedirect(route('ops-center.index', [
                'ops_window_hours' => 24,
                'ops_current_workers' => 4,
            ]));
            $response->assertSessionHas('status', 'Ops tuning overrides applied to configured env file.');
            $response->assertSessionHas('ops_tune_apply_result');

            $updatedEnv = file_get_contents($envFilePath);
            $this->assertIsString($updatedEnv);
            $this->assertStringContainsString('WIDS_QUEUE_AUTOSCALING_TARGET_READY_JOBS_PER_WORKER=96', $updatedEnv);
            $this->assertStringContainsString('WIDS_SCAN_TENANT_DISPATCH_CAP_PER_TICK=200', $updatedEnv);
            $this->assertStringContainsString('WIDS_QUEUE_AUTOSCALING_MAX_WORKERS=18', $updatedEnv);

            $this->assertDatabaseHas('audit_logs', [
                'tenant_id' => $tenant->id,
                'actor_user_id' => $owner->id,
                'action' => 'tenant.ops_tune_applied',
            ]);

            $this->get(route('ops-center.index', [
                'ops_window_hours' => 24,
                'ops_current_workers' => 4,
            ]))
                ->assertOk()
                ->assertSee('Tune plan')
                ->assertSee('Apply overrides');
        } finally {
            $this->cleanupFile($envFilePath);
            $this->cleanBackupDirectory();
        }
    }

    public function test_owner_can_rollback_ops_tuning_from_backup(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $envFilePath = $this->createTempEnvFile([
            'APP_NAME=TruMonit',
            'WIDS_QUEUE_AUTOSCALING_TARGET_READY_JOBS_PER_WORKER=120',
            'WIDS_SCAN_TENANT_DISPATCH_CAP_PER_TICK=250',
            'WIDS_QUEUE_AUTOSCALING_MAX_WORKERS=12',
            '',
        ]);

        config()->set('ops.tuning.env_file', $envFilePath);
        config()->set('wids.queue.scan.tenant_dispatch_cap_per_tick', 250);
        config()->set('wids.queue.autoscaling.target_ready_jobs_per_worker', 120);
        config()->set('wids.queue.autoscaling.max_workers', 12);

        $this->fakeOpsTuneSignals();
        $this->actingAs($owner);

        try {
            $this->post(route('ops-center.tune.apply'), [
                'ops_window_hours' => 24,
                'ops_current_workers' => 4,
            ])->assertRedirect();

            $backupId = (string) (app(OpsTuneEnvFileService::class)->listBackups(limit: 1)[0]['id'] ?? '');
            $this->assertNotSame('', $backupId);

            file_put_contents($envFilePath, implode("\n", [
                'APP_NAME=TruMonit',
                'WIDS_QUEUE_AUTOSCALING_TARGET_READY_JOBS_PER_WORKER=1',
                'WIDS_SCAN_TENANT_DISPATCH_CAP_PER_TICK=1',
                'WIDS_QUEUE_AUTOSCALING_MAX_WORKERS=1',
                '',
            ]));

            $response = $this->post(route('ops-center.tune.rollback'), [
                'backup_id' => $backupId,
                'ops_window_hours' => 24,
                'ops_current_workers' => 4,
            ]);

            $response->assertRedirect(route('ops-center.index', [
                'ops_window_hours' => 24,
                'ops_current_workers' => 4,
            ]));
            $response->assertSessionHas('status', 'Ops tuning rollback completed.');
            $response->assertSessionHas('ops_tune_rollback_result');

            $restoredEnv = file_get_contents($envFilePath);
            $this->assertIsString($restoredEnv);
            $this->assertStringContainsString('WIDS_QUEUE_AUTOSCALING_TARGET_READY_JOBS_PER_WORKER=120', $restoredEnv);
            $this->assertStringContainsString('WIDS_SCAN_TENANT_DISPATCH_CAP_PER_TICK=250', $restoredEnv);
            $this->assertStringContainsString('WIDS_QUEUE_AUTOSCALING_MAX_WORKERS=12', $restoredEnv);

            $this->assertDatabaseHas('audit_logs', [
                'tenant_id' => $tenant->id,
                'actor_user_id' => $owner->id,
                'action' => 'tenant.ops_tune_rollback',
            ]);
        } finally {
            $this->cleanupFile($envFilePath);
            $this->cleanBackupDirectory();
        }
    }

    public function test_member_cannot_apply_or_rollback_ops_tuning(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $member = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'member',
        ]);

        $this->actingAs($member);

        $this->post(route('ops-center.tune.apply'), [
            'ops_window_hours' => 24,
            'ops_current_workers' => 4,
        ])->assertForbidden();

        $this->post(route('ops-center.tune.rollback'), [
            'backup_id' => 'env-20260212_000000_000000.bak',
        ])->assertForbidden();
    }

    private function fakeOpsTuneSignals(): void
    {
        app()->instance(OpsDrillService::class, new class extends OpsDrillService
        {
            public function analyze(?int $windowHours = null): array
            {
                return [
                    'generated_at' => now()->toISOString(),
                    'window_hours' => $windowHours ?? 24,
                    'overall_status' => 'fail',
                    'checks' => [
                        'queue_lag' => [
                            'status' => 'fail',
                            'message' => 'Queue lag is above critical threshold.',
                            'data' => ['scan_lag_p95_seconds' => 1200],
                        ],
                        'queue_wait' => [
                            'status' => 'fail',
                            'message' => 'Queue wait is above critical threshold.',
                            'data' => ['queue_wait_p95_ms' => 15000],
                        ],
                        'private_probe_coverage' => [
                            'status' => 'pass',
                            'message' => 'Private probes healthy.',
                            'data' => ['due_without_online_probe_total' => 0],
                        ],
                    ],
                    'recommendations' => [],
                ];
            }
        });

        app()->instance(QueueAutoscalingService::class, new class extends QueueAutoscalingService
        {
            public function collectQueueMetricsFromRedis(): array
            {
                return [
                    'wids-high' => ['ready' => 200, 'delayed' => 3, 'reserved' => 2],
                    'wids' => ['ready' => 340, 'delayed' => 10, 'reserved' => 4],
                ];
            }

            public function recommendFromQueueMetrics(array $queueMetrics): array
            {
                return [
                    'ready_total' => 540,
                    'weighted_ready_total' => 700.0,
                    'target_ready_jobs_per_worker' => 120,
                    'min_workers' => 1,
                    'max_workers' => 12,
                    'recommended_workers' => 12,
                ];
            }
        });
    }

    /**
     * @param  list<string>  $lines
     */
    private function createTempEnvFile(array $lines): string
    {
        $dir = storage_path('framework/testing/ops-tune-web');

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $path = $dir.'/'.uniqid('billing-ops-', true).'.env';
        file_put_contents($path, implode("\n", $lines));

        return $path;
    }

    private function cleanupFile(string $path): void
    {
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function cleanBackupDirectory(): void
    {
        $dir = storage_path('app/'.ltrim($this->testBackupDir, '/\\'));

        if (! is_dir($dir)) {
            return;
        }

        $files = glob($dir.DIRECTORY_SEPARATOR.'*.bak');
        $files = is_array($files) ? $files : [];

        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
}
