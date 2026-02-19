<?php

namespace App\Console\Commands;

use App\Jobs\ScanMonitoredUrlJob;
use App\Models\MonitoredUrl;
use App\Services\ScanQueueRoutingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class WidsScanCommand extends Command
{
    protected $signature = 'wids:scan {--url_id=} {--tenant_id=} {--force}';

    protected $description = 'Dispatch scan jobs for due monitored URLs';

    public function handle(): int
    {
        $urlId = $this->option('url_id') !== null ? (int) $this->option('url_id') : null;
        $tenantId = $this->option('tenant_id') !== null ? (int) $this->option('tenant_id') : null;
        $force = (bool) $this->option('force');
        $queueRouting = app(ScanQueueRoutingService::class);

        if ($urlId !== null) {
            $queueName = $queueRouting->queueForReason(
                null,
                ScanQueueRoutingService::REASON_MANUAL
            );

            ScanMonitoredUrlJob::dispatch($urlId, $this->currentTimeMs(), null)
                ->onQueue($queueName);

            $this->info(sprintf('Dispatched scan job for monitored_url_id=%d on queue=%s', $urlId, $queueName));

            return self::SUCCESS;
        }

        $now = now();

        $query = MonitoredUrl::query()
            ->where('is_active', true)
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId));

        if ((bool) config('wids.private_probes.enabled', false)) {
            $privateProbeTag = strtolower(trim((string) config('wids.private_probes.monitor_tag', 'private_probe')));

            if ($privateProbeTag !== '') {
                $query->whereDoesntHave('tags', fn ($tagQuery) => $tagQuery->where('name', $privateProbeTag));
            }
        }

        if (! $force) {
            $query->where(function ($q) use ($now): void {
                $q->whereNull('next_scan_at')
                    ->orWhere('next_scan_at', '<=', $now);
            });
        }

        $dispatched = 0;
        $skippedByTenantCap = 0;
        $perTenantDispatched = [];
        $dispatchedByQueue = [];
        $tenantDispatchCap = $queueRouting->tenantDispatchCapPerTick();

        $query
            ->with(['tenant:id,plan_tier'])
            ->select(['id', 'tenant_id', 'frequency_minutes', 'next_scan_at', 'consecutive_failures'])
            ->orderBy('id')
            ->chunkById(500, function ($monitoredUrls) use (
                &$dispatched,
                &$skippedByTenantCap,
                &$perTenantDispatched,
                &$dispatchedByQueue,
                $tenantDispatchCap,
                $now,
                $queueRouting
            ): void {
                foreach ($monitoredUrls as $monitoredUrl) {
                    /** @var MonitoredUrl $monitoredUrl */
                    $tenantKey = (int) $monitoredUrl->tenant_id;

                    if ($tenantDispatchCap > 0 && ($perTenantDispatched[$tenantKey] ?? 0) >= $tenantDispatchCap) {
                        $skippedByTenantCap++;
                        continue;
                    }

                    $dispatchTtlSeconds = min(600, max(60, ((int) $monitoredUrl->frequency_minutes) * 60));

                    if (! Cache::add('wids:scan:dispatched:monitored_url:'.$monitoredUrl->id, 1, $dispatchTtlSeconds)) {
                        continue;
                    }

                    $scanLagSeconds = null;

                    if ($monitoredUrl->next_scan_at !== null) {
                        $scanLagSeconds = max(0, $monitoredUrl->next_scan_at->diffInSeconds($now, false));
                    }

                    $queueName = $queueRouting->queueForReason(
                        $monitoredUrl,
                        ScanQueueRoutingService::REASON_SCHEDULED,
                        $scanLagSeconds,
                        is_string($monitoredUrl->tenant?->plan_tier) ? $monitoredUrl->tenant->plan_tier : null
                    );

                    ScanMonitoredUrlJob::dispatch($monitoredUrl->id, $this->currentTimeMs(), $scanLagSeconds)
                        ->onQueue($queueName);

                    $dispatched++;
                    $perTenantDispatched[$tenantKey] = ($perTenantDispatched[$tenantKey] ?? 0) + 1;
                    $dispatchedByQueue[$queueName] = ($dispatchedByQueue[$queueName] ?? 0) + 1;
                }
            });

        $message = 'Dispatched '.$dispatched.' scan job(s).';

        if ($dispatchedByQueue !== []) {
            ksort($dispatchedByQueue);

            $queueSummary = [];
            foreach ($dispatchedByQueue as $queueName => $count) {
                $queueSummary[] = $queueName.'='.$count;
            }

            $message .= ' queue_split=['.implode(', ', $queueSummary).']';
        }

        if ($skippedByTenantCap > 0) {
            $message .= ' skipped_tenant_cap='.$skippedByTenantCap;
        }

        $this->info($message);

        return self::SUCCESS;
    }

    private function currentTimeMs(): int
    {
        return (int) floor(microtime(true) * 1000);
    }
}
