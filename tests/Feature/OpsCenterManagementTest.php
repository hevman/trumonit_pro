<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use App\Services\OpsDrillService;
use App\Services\OpsTuneEnvFileService;
use App\Services\OpsTuningPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OpsCenterManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_open_ops_center(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        app()->instance(OpsDrillService::class, new class extends OpsDrillService
        {
            public function analyze(?int $windowHours = null): array
            {
                return [
                    'generated_at' => now()->toISOString(),
                    'window_hours' => $windowHours ?? 24,
                    'overall_status' => 'warn',
                    'checks' => [
                        'queue_lag' => [
                            'status' => 'warn',
                            'message' => 'Queue lag needs tuning.',
                            'data' => ['scan_lag_p95_seconds' => 123.4],
                        ],
                        'queue_wait' => [
                            'status' => 'pass',
                            'message' => 'Queue wait is within thresholds.',
                            'data' => ['queue_wait_p95_ms' => 900],
                        ],
                        'storage_quota' => [
                            'status' => 'pass',
                            'message' => 'Storage quota usage is within thresholds.',
                            'data' => ['max_usage_percent' => 71.2],
                        ],
                        'private_probe_coverage' => [
                            'status' => 'pass',
                            'message' => 'Private probe coverage is healthy.',
                            'data' => ['due_without_online_probe_total' => 0],
                        ],
                    ],
                    'recommendations' => ['Tune queue throughput.'],
                ];
            }
        });

        app()->instance(OpsTuningPlanService::class, new class extends OpsTuningPlanService
        {
            public function build(?int $windowHours = null, ?int $currentWorkers = null): array
            {
                return [
                    'generated_at' => now()->toISOString(),
                    'window_hours' => $windowHours ?? 24,
                    'overall_status' => 'warn',
                    'drill' => [],
                    'queue_metrics' => [],
                    'queue_autoscaling_plan' => [],
                    'current_workers' => $currentWorkers,
                    'scale_action' => 'scale_up',
                    'suggested_scale_command' => 'docker compose up -d --scale queue=6',
                    'env_overrides' => [
                        'WIDS_QUEUE_AUTOSCALING_MAX_WORKERS' => [
                            'current' => 12,
                            'suggested' => 18,
                            'reason' => 'test',
                        ],
                    ],
                    'notes' => [],
                ];
            }
        });

        $this->actingAs($owner);

        $this->get(route('ops-center.index', [
            'ops_window_hours' => 48,
            'ops_current_workers' => 4,
        ]))
            ->assertOk()
            ->assertSee('Ops Center')
            ->assertSee('Ops profile')
            ->assertSee('Operational ownership')
            ->assertSee('Drill outcomes (recent)')
            ->assertSee('Queue lag trend (drills)')
            ->assertSee('Queue wait trend (drills)')
            ->assertSee('Drill checks')
            ->assertSee('Tune plan')
            ->assertSee('Drill artifacts')
            ->assertSee('Env backup history')
            ->assertSee('ACTION REQUIRED')
            ->assertSee('docker compose up -d --scale queue=6')
            ->assertSee('WIDS_QUEUE_AUTOSCALING_MAX_WORKERS');
    }

    public function test_ops_center_marks_readiness_ready_when_ownership_and_escalation_are_configured(): void
    {
        Storage::fake('local');

        Storage::disk('local')->put('ops-drills/drill-20260213_100000.json', json_encode([
            'overall_status' => 'pass',
            'checks' => [],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        config()->set('ops.ownership.service_owner', 'platform-owner@example.test');
        config()->set('ops.ownership.primary_on_call', 'oncall-primary@example.test');
        config()->set('ops.ownership.secondary_on_call', 'oncall-secondary@example.test');
        config()->set('ops.ownership.incident_commander', 'incident-commander@example.test');
        config()->set('ops.escalation.channels.sev1', '#sev1-war-room');
        config()->set('ops.escalation.channels.sev2', '#sev2-ops');
        config()->set('ops.escalation.channels.status_updates', '#status-updates');
        config()->set('ops.escalation.targets.customer_updates', 'status-page + email');
        config()->set('ops.escalation.targets.internal_updates', 'slack + incident timeline');

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        app()->instance(OpsDrillService::class, new class extends OpsDrillService
        {
            public function analyze(?int $windowHours = null): array
            {
                return [
                    'generated_at' => now()->toISOString(),
                    'window_hours' => $windowHours ?? 24,
                    'overall_status' => 'pass',
                    'checks' => [],
                    'recommendations' => [],
                ];
            }
        });

        app()->instance(OpsTuningPlanService::class, new class extends OpsTuningPlanService
        {
            public function build(?int $windowHours = null, ?int $currentWorkers = null): array
            {
                return [
                    'generated_at' => now()->toISOString(),
                    'window_hours' => $windowHours ?? 24,
                    'overall_status' => 'pass',
                    'drill' => [],
                    'queue_metrics' => [],
                    'queue_autoscaling_plan' => [],
                    'current_workers' => $currentWorkers,
                    'scale_action' => 'none',
                    'suggested_scale_command' => '',
                    'env_overrides' => [],
                    'notes' => [],
                ];
            }
        });

        $this->actingAs($owner);

        $this->get(route('ops-center.index'))
            ->assertOk()
            ->assertSee('Operational ownership')
            ->assertSee('READY')
            ->assertDontSee('ACTION REQUIRED')
            ->assertDontSee('MISSING')
            ->assertSee('#sev1-war-room')
            ->assertSee('status-page + email');
    }

    public function test_ops_center_uses_recent_artifacts_for_trend_charts(): void
    {
        Storage::fake('local');

        Storage::disk('local')->put('ops-drills/drill-20260212_100000.json', json_encode([
            'overall_status' => 'pass',
            'checks' => [
                'queue_lag' => ['data' => ['scan_lag_p95_seconds' => 45]],
                'queue_wait' => ['data' => ['queue_wait_p95_ms' => 800]],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        Storage::disk('local')->put('ops-drills/drill-20260212_120000.json', json_encode([
            'overall_status' => 'fail',
            'checks' => [
                'queue_lag' => ['data' => ['scan_lag_p95_seconds' => 910]],
                'queue_wait' => ['data' => ['queue_wait_p95_ms' => 15200]],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        app()->instance(OpsDrillService::class, new class extends OpsDrillService
        {
            public function analyze(?int $windowHours = null): array
            {
                return [
                    'generated_at' => now()->toISOString(),
                    'window_hours' => $windowHours ?? 24,
                    'overall_status' => 'warn',
                    'checks' => [],
                    'recommendations' => [],
                ];
            }
        });

        app()->instance(OpsTuningPlanService::class, new class extends OpsTuningPlanService
        {
            public function build(?int $windowHours = null, ?int $currentWorkers = null): array
            {
                return [
                    'generated_at' => now()->toISOString(),
                    'window_hours' => $windowHours ?? 24,
                    'overall_status' => 'warn',
                    'drill' => [],
                    'queue_metrics' => [],
                    'queue_autoscaling_plan' => [],
                    'current_workers' => $currentWorkers,
                    'scale_action' => 'none',
                    'suggested_scale_command' => '',
                    'env_overrides' => [],
                    'notes' => [],
                ];
            }
        });

        $this->actingAs($owner);

        $this->get(route('ops-center.index'))
            ->assertOk()
            ->assertSee('PASS 1')
            ->assertSee('FAIL 1')
            ->assertSee('queue_lag p95 seconds')
            ->assertSee('queue_wait p95 ms');
    }

    public function test_owner_can_run_manual_drill_and_store_artifact(): void
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

        app()->instance(OpsDrillService::class, new class extends OpsDrillService
        {
            public function analyze(?int $windowHours = null): array
            {
                return [
                    'generated_at' => now()->toISOString(),
                    'window_hours' => $windowHours ?? 24,
                    'overall_status' => 'pass',
                    'checks' => [],
                    'recommendations' => [],
                ];
            }
        });

        $this->actingAs($owner);

        $response = $this->post(route('ops-center.drill.run'), [
            'ops_window_hours' => 24,
            'ops_current_workers' => 4,
        ]);

        $response->assertRedirect(route('ops-center.index', [
            'ops_window_hours' => 24,
            'ops_current_workers' => 4,
        ]));
        $response->assertSessionHas('status', 'Ops drill run completed and artifact saved.');

        $files = Storage::disk('local')->allFiles('ops-drills');
        $this->assertCount(1, $files);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'actor_user_id' => $owner->id,
            'action' => 'tenant.ops_drill_manual_run',
        ]);
    }

    public function test_owner_can_apply_tuning_from_ops_center(): void
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
            '',
        ]);

        config()->set('ops.tuning.env_file', $envFilePath);
        config()->set('ops.tuning.backup_dir', 'framework/testing/ops-center-backups');

        app()->instance(OpsTuningPlanService::class, new class extends OpsTuningPlanService
        {
            public function build(?int $windowHours = null, ?int $currentWorkers = null): array
            {
                return [
                    'generated_at' => now()->toISOString(),
                    'window_hours' => $windowHours ?? 24,
                    'overall_status' => 'warn',
                    'drill' => [],
                    'queue_metrics' => [],
                    'queue_autoscaling_plan' => [],
                    'current_workers' => $currentWorkers,
                    'scale_action' => 'scale_up',
                    'suggested_scale_command' => 'docker compose up -d --scale queue=6',
                    'env_overrides' => [
                        'WIDS_QUEUE_AUTOSCALING_TARGET_READY_JOBS_PER_WORKER' => [
                            'current' => 120,
                            'suggested' => 96,
                            'reason' => 'test',
                        ],
                        'WIDS_SCAN_TENANT_DISPATCH_CAP_PER_TICK' => [
                            'current' => 250,
                            'suggested' => 200,
                            'reason' => 'test',
                        ],
                    ],
                    'notes' => [],
                ];
            }
        });

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

            $updatedEnv = file_get_contents($envFilePath);
            $this->assertIsString($updatedEnv);
            $this->assertStringContainsString('WIDS_QUEUE_AUTOSCALING_TARGET_READY_JOBS_PER_WORKER=96', $updatedEnv);
            $this->assertStringContainsString('WIDS_SCAN_TENANT_DISPATCH_CAP_PER_TICK=200', $updatedEnv);

            $backupRows = app(OpsTuneEnvFileService::class)->listBackups(limit: 5);
            $this->assertNotEmpty($backupRows);
        } finally {
            if (is_file($envFilePath)) {
                @unlink($envFilePath);
            }
        }
    }

    public function test_owner_can_open_drill_artifact_json(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('ops-drills/drill-20260212_120000.json', json_encode([
            'overall_status' => 'pass',
            'checks' => [
                'queue_lag' => ['status' => 'pass'],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $this->actingAs($owner);

        $this->get(route('ops-center.drills.show', [
            'filename' => 'drill-20260212_120000.json',
        ]))
            ->assertOk()
            ->assertJsonPath('overall_status', 'pass')
            ->assertJsonPath('checks.queue_lag.status', 'pass');
    }

    public function test_member_cannot_access_ops_center(): void
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

        $this->get(route('ops-center.index'))->assertForbidden();
        $this->post(route('ops-center.drill.run'))->assertForbidden();
        $this->get(route('ops-center.drills.show', ['filename' => 'drill-20260212_120000.json']))->assertForbidden();
    }

    /**
     * @param  list<string>  $lines
     */
    private function createTempEnvFile(array $lines): string
    {
        $dir = storage_path('framework/testing/ops-center');

        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $path = $dir.'/'.uniqid('ops-center-', true).'.env';
        file_put_contents($path, implode("\n", $lines));

        return $path;
    }
}
