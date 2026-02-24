<?php

namespace App\Console\Commands;

use App\Jobs\SyncSeoRiskTenantJob;
use App\Models\Tenant;
use App\Services\SeoRisk\CoreUpdateSignalService;
use App\Services\SeoRisk\SeoRiskPlanService;
use App\Services\SeoRisk\SeoRiskSyncService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WidsSeoRiskSyncCommand extends Command
{
    protected $signature = 'wids:seo-risk-sync
        {--tenant_id= : Limit sync to one tenant}
        {--date= : Metric date in Y-m-d format (default: yesterday)}
        {--sync : Run in-process instead of dispatching queue jobs}';

    protected $description = 'Synchronize SEO Risk daily metrics (GSC + GA4) and generate incidents.';

    public function handle(
        SeoRiskSyncService $syncService,
        CoreUpdateSignalService $coreUpdateService,
        SeoRiskPlanService $planService
    ): int
    {
        if (! (bool) config('wids.seo_risk.enabled', true)) {
            $this->info('SEO Risk sync is disabled.');

            return self::SUCCESS;
        }

        $metricDate = $this->resolveMetricDate();
        if (! $metricDate) {
            $this->error('Invalid --date value. Expected Y-m-d.');

            return self::INVALID;
        }

        $tenantId = is_numeric($this->option('tenant_id')) ? (int) $this->option('tenant_id') : null;
        $runSync = (bool) $this->option('sync');

        if ((bool) config('wids.seo_risk.core_updates.enabled', true)) {
            try {
                $eventsSynced = $coreUpdateService->syncFeed();
                Cache::put('ops:seo_risk:core_updates:last_status', 'ok', now()->addDays(30));
                Cache::put('ops:seo_risk:core_updates:last_success_at', now()->getTimestamp(), now()->addDays(30));
                Cache::forget('ops:seo_risk:core_updates:last_error');
                Cache::forget('ops:seo_risk:core_updates:last_error_at');
                $this->line(sprintf('Core update feed synced: %d event(s) changed.', $eventsSynced));
            } catch (\Throwable $e) {
                $error = trim($e->getMessage());
                Log::error('seo_core_update_feed_sync_failed', [
                    'error' => $error,
                    'metric_date' => $metricDate->toDateString(),
                    'command' => 'wids:seo-risk-sync',
                ]);
                Cache::put('ops:seo_risk:core_updates:last_status', 'failed', now()->addDays(30));
                Cache::put('ops:seo_risk:core_updates:last_error', mb_substr($error, 0, 500), now()->addDays(30));
                Cache::put('ops:seo_risk:core_updates:last_error_at', now()->getTimestamp(), now()->addDays(30));
                Cache::increment('ops:seo_risk:core_updates:error_total');
                $this->warn('Core update feed sync failed: '.$e->getMessage());
            }
        }

        $tenantQuery = Tenant::query()
            ->whereHas('seoProperties', fn ($query) => $query->where('is_enabled', true))
            ->orderBy('id');

        if (is_int($tenantId) && $tenantId > 0) {
            $tenantQuery->where('id', $tenantId);
        }

        $processed = 0;
        $dispatched = 0;
        $incidents = 0;
        $skippedByPlan = 0;

        $tenantQuery->chunkById(100, function ($tenants) use ($runSync, $metricDate, $syncService, $planService, &$processed, &$dispatched, &$incidents, &$skippedByPlan): void {
            foreach ($tenants as $tenant) {
                $processed++;

                if (! $planService->allowsAutoSyncForTenant($tenant)) {
                    $skippedByPlan++;
                    continue;
                }

                if ($runSync) {
                    $summary = $syncService->syncTenant($tenant, $metricDate);
                    $incidents += (int) ($summary['incidents_created'] ?? 0);
                    $this->line(sprintf(
                        'Synced tenant #%d (%d/%d properties, incidents=%d).',
                        $tenant->id,
                        (int) ($summary['properties_synced'] ?? 0),
                        (int) ($summary['properties_total'] ?? 0),
                        (int) ($summary['incidents_created'] ?? 0),
                    ));

                    continue;
                }

                SyncSeoRiskTenantJob::dispatch((int) $tenant->id, $metricDate->toDateString());
                $dispatched++;
            }
        });

        if ($runSync) {
            $this->info(sprintf(
                'SEO Risk sync complete. tenants=%d incidents=%d skipped_by_plan=%d date=%s',
                $processed,
                $incidents,
                $skippedByPlan,
                $metricDate->toDateString(),
            ));
        } else {
            $this->info(sprintf(
                'SEO Risk sync dispatched. tenants=%d jobs=%d skipped_by_plan=%d date=%s',
                $processed,
                $dispatched,
                $skippedByPlan,
                $metricDate->toDateString(),
            ));
        }

        return self::SUCCESS;
    }

    private function resolveMetricDate(): ?CarbonImmutable
    {
        $dateOption = is_string($this->option('date')) ? trim((string) $this->option('date')) : '';
        if ($dateOption === '') {
            return CarbonImmutable::now()->subDay()->startOfDay();
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $dateOption)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
