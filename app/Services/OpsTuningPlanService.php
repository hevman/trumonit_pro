<?php

namespace App\Services;

class OpsTuningPlanService
{
    /**
     * @return array{
     *   generated_at: string,
     *   window_hours: int,
     *   overall_status: string,
     *   drill: array<string, mixed>,
     *   queue_metrics: array<string, array{ready?: int, delayed?: int, reserved?: int}>,
     *   queue_autoscaling_plan: array<string, int|float>,
     *   current_workers: ?int,
     *   scale_action: ?string,
     *   suggested_scale_command: string,
     *   env_overrides: array<string, array{current: int, suggested: int, reason: string}>,
     *   notes: list<string>
     * }
     */
    public function build(?int $windowHours = null, ?int $currentWorkers = null): array
    {
        $drill = app(OpsDrillService::class)->analyze($windowHours);
        $queueAutoscalingService = app(QueueAutoscalingService::class);
        $queueMetrics = $queueAutoscalingService->collectQueueMetricsFromRedis();
        $queueAutoscalingPlan = $queueAutoscalingService->recommendFromQueueMetrics($queueMetrics);

        $recommendedWorkers = max(1, (int) ($queueAutoscalingPlan['recommended_workers'] ?? 1));
        $scaleAction = null;

        if (is_int($currentWorkers)) {
            if ($recommendedWorkers > $currentWorkers) {
                $scaleAction = 'scale_up';
            } elseif ($recommendedWorkers < $currentWorkers) {
                $scaleAction = 'scale_down';
            } else {
                $scaleAction = 'keep';
            }
        }

        $envOverrides = $this->suggestEnvOverrides($drill, $queueAutoscalingPlan);
        $notes = $this->buildNotes($drill, $recommendedWorkers, $currentWorkers, $scaleAction, $envOverrides);

        return [
            'generated_at' => now()->toISOString(),
            'window_hours' => (int) ($drill['window_hours'] ?? (int) config('ops.drill.default_window_hours', 24)),
            'overall_status' => (string) ($drill['overall_status'] ?? 'pass'),
            'drill' => $drill,
            'queue_metrics' => $queueMetrics,
            'queue_autoscaling_plan' => $queueAutoscalingPlan,
            'current_workers' => $currentWorkers,
            'scale_action' => $scaleAction,
            'suggested_scale_command' => 'docker compose up -d --scale queue='.$recommendedWorkers,
            'env_overrides' => $envOverrides,
            'notes' => $notes,
        ];
    }

    /**
     * @param  array<string, mixed>  $drill
     * @param  array<string, int|float>  $queueAutoscalingPlan
     * @return array<string, array{current: int, suggested: int, reason: string}>
     */
    private function suggestEnvOverrides(array $drill, array $queueAutoscalingPlan): array
    {
        $queueLagStatus = (string) data_get($drill, 'checks.queue_lag.status', 'pass');
        $queueWaitStatus = (string) data_get($drill, 'checks.queue_wait.status', 'pass');

        $dispatchCap = max(0, (int) config('wids.queue.scan.tenant_dispatch_cap_per_tick', 250));
        $targetReadyJobs = max(1, (int) config('wids.queue.autoscaling.target_ready_jobs_per_worker', 120));
        $maxWorkers = max(1, (int) config('wids.queue.autoscaling.max_workers', 12));
        $recommendedWorkers = max(1, (int) ($queueAutoscalingPlan['recommended_workers'] ?? 1));

        $overrides = [];

        if (in_array($queueWaitStatus, ['warn', 'fail'], true)) {
            $suggestedTarget = max(20, min(100_000, (int) floor($targetReadyJobs * 0.8)));

            if ($suggestedTarget !== $targetReadyJobs) {
                $overrides['WIDS_QUEUE_AUTOSCALING_TARGET_READY_JOBS_PER_WORKER'] = [
                    'current' => $targetReadyJobs,
                    'suggested' => $suggestedTarget,
                    'reason' => 'Queue wait p95 is elevated; scale workers earlier by lowering target ready jobs per worker.',
                ];
            }

            if ($queueWaitStatus === 'fail' && $dispatchCap > 0) {
                $suggestedDispatchCap = max(50, min(10_000, (int) floor($dispatchCap * 0.8)));

                if ($suggestedDispatchCap !== $dispatchCap) {
                    $overrides['WIDS_SCAN_TENANT_DISPATCH_CAP_PER_TICK'] = [
                        'current' => $dispatchCap,
                        'suggested' => $suggestedDispatchCap,
                        'reason' => 'Queue wait p95 is critical; reduce per-tick scheduler burst to protect queue latency.',
                    ];
                }
            }
        } elseif (in_array($queueLagStatus, ['warn', 'fail'], true) && $dispatchCap > 0) {
            $factor = $queueLagStatus === 'fail' ? 1.3 : 1.15;
            $suggestedDispatchCap = max(1, min(10_000, (int) ceil($dispatchCap * $factor)));

            if ($suggestedDispatchCap !== $dispatchCap) {
                $overrides['WIDS_SCAN_TENANT_DISPATCH_CAP_PER_TICK'] = [
                    'current' => $dispatchCap,
                    'suggested' => $suggestedDispatchCap,
                    'reason' => 'Queue lag is high while queue wait is healthy; increase scheduler dispatch throughput.',
                ];
            }
        }

        if ($recommendedWorkers >= $maxWorkers && in_array($queueWaitStatus, ['warn', 'fail'], true)) {
            $suggestedMaxWorkers = max($maxWorkers + 1, min(10_000, (int) ceil($maxWorkers * 1.5)));

            if ($suggestedMaxWorkers !== $maxWorkers) {
                $overrides['WIDS_QUEUE_AUTOSCALING_MAX_WORKERS'] = [
                    'current' => $maxWorkers,
                    'suggested' => $suggestedMaxWorkers,
                    'reason' => 'Autoscaling recommendation reached max workers; increase max worker cap to avoid saturation.',
                ];
            }
        }

        return $overrides;
    }

    /**
     * @param  array<string, mixed>  $drill
     * @param  array<string, array{current: int, suggested: int, reason: string}>  $envOverrides
     * @return list<string>
     */
    private function buildNotes(
        array $drill,
        int $recommendedWorkers,
        ?int $currentWorkers,
        ?string $scaleAction,
        array $envOverrides
    ): array {
        $notes = [];

        if ($currentWorkers !== null && $scaleAction !== null && $scaleAction !== 'keep') {
            $notes[] = sprintf(
                'Queue workers: %s from %d to %d.',
                $scaleAction === 'scale_up' ? 'scale up' : 'scale down',
                $currentWorkers,
                $recommendedWorkers
            );
        }

        if ($envOverrides !== []) {
            $notes[] = 'Apply suggested env overrides during next deploy and validate with another `wids:ops-drill` run.';
        } else {
            $notes[] = 'No env override suggestion generated from current drill and queue profile.';
        }

        $dueWithoutOnlineProbe = (int) data_get($drill, 'checks.private_probe_coverage.data.due_without_online_probe_total', 0);

        if ($dueWithoutOnlineProbe > 0) {
            $notes[] = 'Private probes: restore online probe heartbeat for affected tenants before raising dispatch volume.';
        }

        return array_values(array_unique($notes));
    }
}
