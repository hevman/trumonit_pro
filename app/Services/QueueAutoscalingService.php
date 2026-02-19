<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class QueueAutoscalingService
{
    /**
     * @return array<string, array{ready: int, delayed: int, reserved: int}>
     */
    public function collectQueueMetricsFromRedis(): array
    {
        if (config('queue.default') !== 'redis') {
            return [];
        }

        $connection = (string) config('queue.connections.redis.connection', 'default');
        $queueNames = app(ScanQueueRoutingService::class)->workerQueueOrder();

        if ($queueNames === []) {
            return [];
        }

        $metrics = [];

        try {
            $redis = Redis::connection($connection);

            foreach ($queueNames as $queueName) {
                $metrics[$queueName] = [
                    'ready' => (int) $redis->llen('queues:'.$queueName),
                    'delayed' => (int) $redis->zcard('queues:'.$queueName.':delayed'),
                    'reserved' => (int) $redis->zcard('queues:'.$queueName.':reserved'),
                ];
            }
        } catch (\Throwable) {
            return [];
        }

        return $metrics;
    }

    public function isEnabled(): bool
    {
        return (bool) config('wids.queue.autoscaling.enabled', true);
    }

    /**
     * @param  array<string, array{ready?: int, delayed?: int, reserved?: int}>  $queueMetrics
     * @return array{
     *   ready_total: int,
     *   weighted_ready_total: float,
     *   target_ready_jobs_per_worker: int,
     *   min_workers: int,
     *   max_workers: int,
     *   recommended_workers: int
     * }
     */
    public function recommendFromQueueMetrics(array $queueMetrics): array
    {
        $highQueueName = app(ScanQueueRoutingService::class)->highQueueName();
        $defaultQueueName = app(ScanQueueRoutingService::class)->defaultQueueName();
        $lowQueueName = app(ScanQueueRoutingService::class)->lowQueueName();

        $highWeight = max(0.1, min(10.0, (float) config('wids.queue.autoscaling.high_queue_weight', 1.8)));
        $defaultWeight = max(0.1, min(10.0, (float) config('wids.queue.autoscaling.default_queue_weight', 1.0)));
        $lowWeight = max(0.1, min(10.0, (float) config('wids.queue.autoscaling.low_queue_weight', 0.5)));

        $targetReadyJobsPerWorker = max(1, min(100_000, (int) config('wids.queue.autoscaling.target_ready_jobs_per_worker', 120)));
        $minWorkers = max(1, min(10_000, (int) config('wids.queue.autoscaling.min_workers', 1)));
        $maxWorkers = max($minWorkers, min(10_000, (int) config('wids.queue.autoscaling.max_workers', 12)));

        $readyTotal = 0;
        $weightedReadyTotal = 0.0;

        foreach ($queueMetrics as $queueName => $counts) {
            $ready = max(0, (int) ($counts['ready'] ?? 0));
            $readyTotal += $ready;

            $weight = $defaultWeight;

            if ((string) $queueName === $highQueueName) {
                $weight = $highWeight;
            } elseif ((string) $queueName === $lowQueueName) {
                $weight = $lowWeight;
            } elseif ((string) $queueName === $defaultQueueName) {
                $weight = $defaultWeight;
            }

            $weightedReadyTotal += $ready * $weight;
        }

        $recommendedWorkers = $minWorkers;

        if ($weightedReadyTotal > 0) {
            $recommendedWorkers = (int) ceil($weightedReadyTotal / $targetReadyJobsPerWorker);
            $recommendedWorkers = max($minWorkers, min($maxWorkers, $recommendedWorkers));
        }

        return [
            'ready_total' => $readyTotal,
            'weighted_ready_total' => round($weightedReadyTotal, 2),
            'target_ready_jobs_per_worker' => $targetReadyJobsPerWorker,
            'min_workers' => $minWorkers,
            'max_workers' => $maxWorkers,
            'recommended_workers' => $recommendedWorkers,
        ];
    }
}
