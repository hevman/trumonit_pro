<?php

namespace App\Console\Commands;

use App\Services\QueueAutoscalingService;
use Illuminate\Console\Command;

class WidsQueueAutoscalePlanCommand extends Command
{
    protected $signature = 'wids:queue-autoscale-plan {--format=text : text|json} {--current-workers=}';

    protected $description = 'Compute recommended queue worker scale based on current Redis backlog';

    public function handle(): int
    {
        $format = strtolower(trim((string) $this->option('format')));
        $format = in_array($format, ['text', 'json'], true) ? $format : 'text';

        $currentWorkers = $this->option('current-workers');
        $currentWorkers = is_numeric($currentWorkers) ? max(0, (int) $currentWorkers) : null;

        $autoscalingService = app(QueueAutoscalingService::class);

        if (! $autoscalingService->isEnabled()) {
            $this->warn('Queue autoscaling is disabled (WIDS_QUEUE_AUTOSCALING_ENABLED=false).');

            return self::SUCCESS;
        }

        $queueMetrics = $autoscalingService->collectQueueMetricsFromRedis();
        $plan = $autoscalingService->recommendFromQueueMetrics($queueMetrics);

        $scaleAction = null;

        if (is_int($currentWorkers)) {
            $recommended = (int) ($plan['recommended_workers'] ?? 0);

            if ($recommended > $currentWorkers) {
                $scaleAction = 'scale_up';
            } elseif ($recommended < $currentWorkers) {
                $scaleAction = 'scale_down';
            } else {
                $scaleAction = 'keep';
            }
        }

        if ($format === 'json') {
            $payload = [
                'generated_at' => now()->toISOString(),
                'queue_metrics' => $queueMetrics,
                'plan' => $plan,
                'current_workers' => $currentWorkers,
                'scale_action' => $scaleAction,
                'suggested_command' => 'docker compose up -d --scale queue='.(int) ($plan['recommended_workers'] ?? 1),
            ];

            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        if ($queueMetrics === []) {
            $this->warn('No Redis queue metrics available. Check Redis connectivity and queue driver.');
        } else {
            $this->info('Queue backlog (ready/delayed/reserved):');

            foreach ($queueMetrics as $queueName => $counts) {
                $this->line(sprintf(
                    '- %s: ready=%d delayed=%d reserved=%d',
                    (string) $queueName,
                    (int) ($counts['ready'] ?? 0),
                    (int) ($counts['delayed'] ?? 0),
                    (int) ($counts['reserved'] ?? 0)
                ));
            }
        }

        $this->line('');
        $this->info('Autoscaling plan:');
        $this->line('  Weighted ready backlog: '.(float) ($plan['weighted_ready_total'] ?? 0));
        $this->line('  Target ready jobs/worker: '.(int) ($plan['target_ready_jobs_per_worker'] ?? 0));
        $this->line('  Min workers: '.(int) ($plan['min_workers'] ?? 0));
        $this->line('  Max workers: '.(int) ($plan['max_workers'] ?? 0));
        $this->line('  Recommended workers: '.(int) ($plan['recommended_workers'] ?? 0));
        $this->line('  Suggested command: docker compose up -d --scale queue='.(int) ($plan['recommended_workers'] ?? 1));

        if ($scaleAction !== null) {
            $this->line('  Compared to current workers: '.$scaleAction.' (current='.$currentWorkers.')');
        }

        return self::SUCCESS;
    }
}
