<?php

namespace Tests\Feature;

use App\Services\OpsDrillService;
use App\Services\QueueAutoscalingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WidsOpsTunePlanCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_outputs_json_with_env_overrides_when_queue_wait_is_high(): void
    {
        config()->set('wids.queue.scan.tenant_dispatch_cap_per_tick', 250);
        config()->set('wids.queue.autoscaling.target_ready_jobs_per_worker', 120);
        config()->set('wids.queue.autoscaling.max_workers', 12);

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
                            'data' => [
                                'scan_lag_p95_seconds' => 1200,
                            ],
                        ],
                        'queue_wait' => [
                            'status' => 'fail',
                            'message' => 'Queue wait is above critical threshold.',
                            'data' => [
                                'queue_wait_p95_ms' => 15000,
                            ],
                        ],
                        'private_probe_coverage' => [
                            'status' => 'pass',
                            'message' => 'Private probes healthy.',
                            'data' => [
                                'due_without_online_probe_total' => 0,
                            ],
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

        $exitCode = Artisan::call('wids:ops-tune-plan', [
            '--format' => 'json',
            '--current-workers' => 4,
        ]);

        $this->assertSame(0, $exitCode);

        $report = json_decode(Artisan::output(), true);

        $this->assertIsArray($report);
        $this->assertSame('scale_up', (string) ($report['scale_action'] ?? ''));
        $this->assertSame(96, (int) data_get($report, 'env_overrides.WIDS_QUEUE_AUTOSCALING_TARGET_READY_JOBS_PER_WORKER.suggested'));
        $this->assertSame(200, (int) data_get($report, 'env_overrides.WIDS_SCAN_TENANT_DISPATCH_CAP_PER_TICK.suggested'));
        $this->assertSame(18, (int) data_get($report, 'env_overrides.WIDS_QUEUE_AUTOSCALING_MAX_WORKERS.suggested'));
    }

    public function test_command_can_write_env_snippet(): void
    {
        Storage::fake('local');

        config()->set('wids.queue.scan.tenant_dispatch_cap_per_tick', 250);
        config()->set('wids.queue.autoscaling.target_ready_jobs_per_worker', 120);
        config()->set('wids.queue.autoscaling.max_workers', 12);

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
                            'status' => 'pass',
                            'message' => 'ok',
                            'data' => [],
                        ],
                        'queue_wait' => [
                            'status' => 'warn',
                            'message' => 'warn',
                            'data' => [],
                        ],
                        'private_probe_coverage' => [
                            'status' => 'pass',
                            'message' => 'ok',
                            'data' => [
                                'due_without_online_probe_total' => 0,
                            ],
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
                return [];
            }

            public function recommendFromQueueMetrics(array $queueMetrics): array
            {
                return [
                    'ready_total' => 0,
                    'weighted_ready_total' => 0.0,
                    'target_ready_jobs_per_worker' => 120,
                    'min_workers' => 1,
                    'max_workers' => 12,
                    'recommended_workers' => 2,
                ];
            }
        });

        $snippetPath = 'ops-drills/tune-test.env';

        $exitCode = Artisan::call('wids:ops-tune-plan', [
            '--format' => 'json',
            '--write-snippet' => $snippetPath,
        ]);

        $this->assertSame(0, $exitCode);
        $report = json_decode(Artisan::output(), true);
        $this->assertIsArray($report);
        $this->assertSame('storage/app/'.$snippetPath, (string) ($report['snippet_path'] ?? ''));

        Storage::disk('local')->assertExists($snippetPath);

        $snippet = Storage::disk('local')->get($snippetPath);
        $this->assertStringContainsString('WIDS_QUEUE_AUTOSCALING_TARGET_READY_JOBS_PER_WORKER=96', $snippet);
    }

    public function test_command_can_apply_env_overrides_to_selected_env_file(): void
    {
        config()->set('ops.tuning.backup_dir', 'framework/testing/ops-tune-cli-backups');
        config()->set('wids.queue.scan.tenant_dispatch_cap_per_tick', 250);
        config()->set('wids.queue.autoscaling.target_ready_jobs_per_worker', 120);
        config()->set('wids.queue.autoscaling.max_workers', 12);

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
                            'data' => [
                                'scan_lag_p95_seconds' => 1200,
                            ],
                        ],
                        'queue_wait' => [
                            'status' => 'fail',
                            'message' => 'Queue wait is above critical threshold.',
                            'data' => [
                                'queue_wait_p95_ms' => 15000,
                            ],
                        ],
                        'private_probe_coverage' => [
                            'status' => 'pass',
                            'message' => 'Private probes healthy.',
                            'data' => [
                                'due_without_online_probe_total' => 0,
                            ],
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

        $dir = storage_path('framework/testing/ops-tune');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $envFilePath = $dir.'/'.uniqid('apply-', true).'.env';
        $originalPayload = implode("\n", [
            'APP_NAME=TruMonit',
            'WIDS_QUEUE_AUTOSCALING_TARGET_READY_JOBS_PER_WORKER=120',
            'WIDS_SCAN_TENANT_DISPATCH_CAP_PER_TICK=250',
            'WIDS_QUEUE_AUTOSCALING_MAX_WORKERS=12',
            '',
        ]);

        file_put_contents($envFilePath, $originalPayload);
        $backupPath = null;

        try {
            $exitCode = Artisan::call('wids:ops-tune-plan', [
                '--format' => 'json',
                '--current-workers' => 4,
                '--apply' => true,
                '--env-file' => $envFilePath,
            ]);

            $this->assertSame(0, $exitCode);

            $report = json_decode(Artisan::output(), true);
            $this->assertIsArray($report);

            $this->assertSame($envFilePath, (string) data_get($report, 'apply_result.env_file'));
            $this->assertSame(3, (int) data_get($report, 'apply_result.applied_count'));

            $backupPath = (string) data_get($report, 'apply_result.backup_path');
            $this->assertNotSame('', $backupPath);
            $this->assertFileExists($backupPath);

            $updatedEnv = file_get_contents($envFilePath);
            $this->assertIsString($updatedEnv);
            $this->assertStringContainsString('WIDS_QUEUE_AUTOSCALING_TARGET_READY_JOBS_PER_WORKER=96', $updatedEnv);
            $this->assertStringContainsString('WIDS_SCAN_TENANT_DISPATCH_CAP_PER_TICK=200', $updatedEnv);
            $this->assertStringContainsString('WIDS_QUEUE_AUTOSCALING_MAX_WORKERS=18', $updatedEnv);

            $backupEnv = file_get_contents($backupPath);
            $this->assertIsString($backupEnv);
            $this->assertStringContainsString('WIDS_QUEUE_AUTOSCALING_TARGET_READY_JOBS_PER_WORKER=120', $backupEnv);
            $this->assertStringContainsString('WIDS_SCAN_TENANT_DISPATCH_CAP_PER_TICK=250', $backupEnv);
            $this->assertStringContainsString('WIDS_QUEUE_AUTOSCALING_MAX_WORKERS=12', $backupEnv);
        } finally {
            if (is_file($envFilePath)) {
                unlink($envFilePath);
            }

            if (is_string($backupPath) && $backupPath !== '' && is_file($backupPath)) {
                unlink($backupPath);
            }
        }
    }

    public function test_command_can_rollback_env_file_from_backup(): void
    {
        config()->set('ops.tuning.backup_dir', 'framework/testing/ops-tune-cli-backups');
        config()->set('wids.queue.scan.tenant_dispatch_cap_per_tick', 250);
        config()->set('wids.queue.autoscaling.target_ready_jobs_per_worker', 120);
        config()->set('wids.queue.autoscaling.max_workers', 12);

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
                            'data' => [
                                'scan_lag_p95_seconds' => 1200,
                            ],
                        ],
                        'queue_wait' => [
                            'status' => 'fail',
                            'message' => 'Queue wait is above critical threshold.',
                            'data' => [
                                'queue_wait_p95_ms' => 15000,
                            ],
                        ],
                        'private_probe_coverage' => [
                            'status' => 'pass',
                            'message' => 'Private probes healthy.',
                            'data' => [
                                'due_without_online_probe_total' => 0,
                            ],
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

        $dir = storage_path('framework/testing/ops-tune');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $envFilePath = $dir.'/'.uniqid('rollback-', true).'.env';
        $originalPayload = implode("\n", [
            'APP_NAME=TruMonit',
            'WIDS_QUEUE_AUTOSCALING_TARGET_READY_JOBS_PER_WORKER=120',
            'WIDS_SCAN_TENANT_DISPATCH_CAP_PER_TICK=250',
            'WIDS_QUEUE_AUTOSCALING_MAX_WORKERS=12',
            '',
        ]);

        file_put_contents($envFilePath, $originalPayload);
        $backupPath = null;

        try {
            $applyExit = Artisan::call('wids:ops-tune-plan', [
                '--format' => 'json',
                '--current-workers' => 4,
                '--apply' => true,
                '--env-file' => $envFilePath,
            ]);

            $this->assertSame(0, $applyExit);
            $applyReport = json_decode(Artisan::output(), true);
            $this->assertIsArray($applyReport);

            $backupPath = (string) data_get($applyReport, 'apply_result.backup_path');
            $this->assertNotSame('', $backupPath);
            $this->assertFileExists($backupPath);

            $rollbackExit = Artisan::call('wids:ops-tune-plan', [
                '--format' => 'json',
                '--rollback-from' => $backupPath,
                '--env-file' => $envFilePath,
            ]);

            $this->assertSame(0, $rollbackExit);
            $rollbackReport = json_decode(Artisan::output(), true);
            $this->assertIsArray($rollbackReport);
            $this->assertSame('rollback', (string) ($rollbackReport['action'] ?? ''));
            $this->assertTrue((bool) ($rollbackReport['ok'] ?? false));

            $restoredEnv = file_get_contents($envFilePath);
            $this->assertIsString($restoredEnv);
            $this->assertStringContainsString('WIDS_QUEUE_AUTOSCALING_TARGET_READY_JOBS_PER_WORKER=120', $restoredEnv);
            $this->assertStringContainsString('WIDS_SCAN_TENANT_DISPATCH_CAP_PER_TICK=250', $restoredEnv);
            $this->assertStringContainsString('WIDS_QUEUE_AUTOSCALING_MAX_WORKERS=12', $restoredEnv);
        } finally {
            if (is_file($envFilePath)) {
                unlink($envFilePath);
            }

            if (is_string($backupPath) && $backupPath !== '' && is_file($backupPath)) {
                unlink($backupPath);
            }
        }
    }
}
