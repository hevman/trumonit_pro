<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Services\SeoRisk\SeoRiskSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncSeoRiskTenantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [30, 180, 600];

    public function __construct(
        public readonly int $tenantId,
        public readonly string $metricDate,
    ) {
        $queue = trim((string) config('wids.seo_risk.sync_queue', 'wids'));
        $this->onQueue($queue !== '' ? $queue : 'wids');
    }

    public function handle(SeoRiskSyncService $syncService): void
    {
        if (! (bool) config('wids.seo_risk.enabled', true)) {
            return;
        }

        $tenant = Tenant::query()->find($this->tenantId);
        if (! $tenant) {
            return;
        }

        $metricDate = CarbonImmutable::parse($this->metricDate);
        $summary = $syncService->syncTenant($tenant, $metricDate);

        Log::info('SEO risk sync finished.', $summary);
    }
}
