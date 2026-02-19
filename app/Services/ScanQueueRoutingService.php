<?php

namespace App\Services;

use App\Models\MonitoredUrl;

class ScanQueueRoutingService
{
    public const REASON_SCHEDULED = 'scheduled';

    public const REASON_MANUAL = 'manual';

    public const REASON_ONBOARDING = 'onboarding';

    public const REASON_API = 'api';

    public function highQueueName(): string
    {
        return $this->normalizeQueueName(
            (string) config('wids.queue.scan.high_queue', 'wids-high'),
            'wids-high'
        );
    }

    public function defaultQueueName(): string
    {
        return $this->normalizeQueueName(
            (string) config('wids.queue.scan.default_queue', 'wids'),
            'wids'
        );
    }

    public function lowQueueName(): string
    {
        return $this->normalizeQueueName(
            (string) config('wids.queue.scan.low_queue', 'wids-low'),
            'wids-low'
        );
    }

    /**
     * @return list<string>
     */
    public function workerQueueOrder(): array
    {
        $configured = config('wids.queue.worker.queue_order', []);
        $configured = is_array($configured) ? $configured : [];

        $fallback = [
            $this->highQueueName(),
            $this->defaultQueueName(),
            $this->lowQueueName(),
            'default',
        ];

        $result = [];

        foreach (($configured !== [] ? $configured : $fallback) as $queueName) {
            if (! is_string($queueName)) {
                continue;
            }

            $queueName = trim($queueName);

            if ($queueName === '' || in_array($queueName, $result, true)) {
                continue;
            }

            $result[] = $queueName;
        }

        if ($result === []) {
            return $fallback;
        }

        return $result;
    }

    public function tenantDispatchCapPerTick(): int
    {
        $value = (int) config('wids.queue.scan.tenant_dispatch_cap_per_tick', 0);

        return max(0, min(10_000, $value));
    }

    public function queueForReason(
        ?MonitoredUrl $monitoredUrl,
        string $reason = self::REASON_SCHEDULED,
        ?int $scanLagSeconds = null,
        ?string $planTier = null
    ): string {
        $reason = strtolower(trim($reason));

        if ($reason === self::REASON_MANUAL) {
            return $this->normalizeQueueName(
                (string) config('wids.queue.scan.manual_queue', $this->highQueueName()),
                $this->highQueueName()
            );
        }

        if ($reason === self::REASON_ONBOARDING) {
            return $this->normalizeQueueName(
                (string) config('wids.queue.scan.onboarding_queue', $this->highQueueName()),
                $this->highQueueName()
            );
        }

        if ($reason === self::REASON_API) {
            return $this->normalizeQueueName(
                (string) config('wids.queue.scan.api_queue', $this->highQueueName()),
                $this->highQueueName()
            );
        }

        return $this->queueForScheduled($monitoredUrl, $scanLagSeconds, $planTier);
    }

    private function queueForScheduled(?MonitoredUrl $monitoredUrl, ?int $scanLagSeconds, ?string $planTier): string
    {
        $queue = $this->defaultQueueName();

        if (! $monitoredUrl) {
            return $queue;
        }

        $scanLagSeconds = max(0, (int) ($scanLagSeconds ?? 0));
        $consecutiveFailures = max(0, (int) ($monitoredUrl->consecutive_failures ?? 0));
        $frequencyMinutes = max(1, (int) ($monitoredUrl->frequency_minutes ?? 1440));

        $highPriorityLagSeconds = max(30, min(86_400, (int) config('wids.queue.scan.high_priority_lag_seconds', 300)));
        $highPriorityFailures = max(1, min(100, (int) config('wids.queue.scan.high_priority_failures', 1)));

        $lowPriorityMinFrequencyMinutes = max(1, min(43_200, (int) config('wids.queue.scan.low_priority_min_frequency_minutes', 60)));
        $lowPriorityMaxLagSeconds = max(0, min(86_400, (int) config('wids.queue.scan.low_priority_max_lag_seconds', 120)));

        if ($scanLagSeconds >= $highPriorityLagSeconds || $consecutiveFailures >= $highPriorityFailures) {
            $queue = $this->highQueueName();
        } elseif ($frequencyMinutes >= $lowPriorityMinFrequencyMinutes && $scanLagSeconds <= $lowPriorityMaxLagSeconds) {
            $queue = $this->lowQueueName();
        }

        $resolvedPlanTier = $this->normalizeTier($planTier ?? (string) ($monitoredUrl->tenant?->plan_tier ?? ''));

        if (in_array($resolvedPlanTier, ['pro', 'enterprise'], true) && $queue === $this->lowQueueName()) {
            $queue = $this->defaultQueueName();
        }

        $enterpriseHighLagSeconds = max(30, min(86_400, (int) config('wids.queue.scan.enterprise_high_priority_lag_seconds', 180)));

        if ($resolvedPlanTier === 'enterprise' && $scanLagSeconds >= $enterpriseHighLagSeconds) {
            $queue = $this->highQueueName();
        }

        return $queue;
    }

    private function normalizeQueueName(string $queueName, string $fallback): string
    {
        $queueName = trim($queueName);

        return $queueName !== '' ? $queueName : $fallback;
    }

    private function normalizeTier(string $tier): string
    {
        $tier = strtolower(trim($tier));

        return $tier;
    }
}
