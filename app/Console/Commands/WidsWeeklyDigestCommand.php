<?php

namespace App\Console\Commands;

use App\Jobs\SendWeeklyDigestEmailJob;
use App\Mail\WeeklyDigestMail;
use App\Models\Tenant;
use App\Services\TenantNotificationChannelService;
use App\Services\WeeklyDigestService;
use App\Services\WeeklyDigestReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class WidsWeeklyDigestCommand extends Command
{
    protected $signature = 'wids:weekly-digest
        {--tenant_id= : Limit to one tenant}
        {--week_start= : Start date (Y-m-d)}
        {--week_end= : End date (Y-m-d)}
        {--recipient=* : Override recipient list}
        {--store-report : Persist weekly digest report pack (JSON + CSV)}
        {--no-store-report : Disable weekly digest report pack persistence}
        {--report-retention-days= : Override report pack retention days}
        {--sync : Send immediately (no queue)}
        {--dry-run : Print recipients and summary only}';

    protected $description = 'Send weekly digest reports (non-SLA trend analytics) to tenant email recipients.';

    public function handle(): int
    {
        if (! (bool) config('wids.reports.weekly_digest.enabled', true)) {
            $this->info('Weekly digest is disabled.');

            return self::SUCCESS;
        }

        $window = $this->resolveWindow();

        if ($window === null) {
            return self::INVALID;
        }

        $weekStart = $window['start'];
        $weekEnd = $window['end'];
        $tenantId = is_numeric($this->option('tenant_id')) ? (int) $this->option('tenant_id') : null;
        $dryRun = (bool) $this->option('dry-run');
        $sync = (bool) $this->option('sync');
        $storeReport = $this->resolveStoreReportOption();
        $reportRetentionDays = $this->resolveReportRetentionDays();

        $overrideRecipients = collect((array) $this->option('recipient'))
            ->map(fn ($value) => is_string($value) ? trim($value) : '')
            ->filter(fn (string $value): bool => $value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL))
            ->map(fn (string $value) => strtolower($value))
            ->unique()
            ->values()
            ->all();

        $tenantQuery = Tenant::query()
            ->select(['id', 'name', 'slug', 'plan_tier'])
            ->orderBy('id');

        if (is_int($tenantId) && $tenantId > 0) {
            $tenantQuery->where('id', $tenantId);
        }

        $tenantNotificationChannelService = app(TenantNotificationChannelService::class);
        $digestService = app(WeeklyDigestService::class);
        $tenantsProcessed = 0;
        $emailsQueued = 0;
        $emailsSent = 0;
        $tenantsSkippedNoRecipients = 0;
        $reportsStored = 0;
        $reportService = app(WeeklyDigestReportService::class);

        $tenantQuery->chunkById(100, function ($tenants) use (
            $overrideRecipients,
            $tenantNotificationChannelService,
            $digestService,
            $weekStart,
            $weekEnd,
            $dryRun,
            $sync,
            $storeReport,
            $reportService,
            &$tenantsProcessed,
            &$emailsQueued,
            &$emailsSent,
            &$tenantsSkippedNoRecipients,
            &$reportsStored
        ): void {
            foreach ($tenants as $tenant) {
                /** @var Tenant $tenant */
                $tenantsProcessed++;

                $recipients = $overrideRecipients;

                if ($recipients === []) {
                    $channels = $tenantNotificationChannelService->channelsForTenantId((int) $tenant->id);
                    $recipients = is_array($channels['email_recipients'] ?? null)
                        ? array_values(array_unique(array_map('strtolower', $channels['email_recipients'])))
                        : [];
                }

                if ($recipients === []) {
                    $tenantsSkippedNoRecipients++;

                    $this->line('Skipping tenant #'.$tenant->id.' (no weekly digest email recipients).');

                    continue;
                }

                $digest = $digestService->buildTenantDigest($tenant, $weekStart, $weekEnd);
                $tenantLabel = trim((string) $tenant->slug) !== '' ? (string) $tenant->slug : ('tenant-'.$tenant->id);

                if (! $dryRun && $storeReport) {
                    $reportService->storeReport(
                        tenant: $tenant,
                        digest: $digest,
                        recipients: $recipients,
                        dispatchMode: $sync ? 'sync' : 'queue',
                    );
                    $reportsStored++;
                }

                if ($dryRun) {
                    $this->line(sprintf(
                        'DRY-RUN %s: recipients=%d scans=%d incidents_opened=%d incidents_resolved=%d',
                        $tenantLabel,
                        count($recipients),
                        (int) ($digest['overview']['scans_total'] ?? 0),
                        (int) ($digest['overview']['incidents_opened_total'] ?? 0),
                        (int) ($digest['overview']['incidents_resolved_total'] ?? 0),
                    ));

                    continue;
                }

                foreach ($recipients as $recipient) {
                    if ($sync) {
                        Mail::to($recipient)->send(new WeeklyDigestMail($digest));
                        $emailsSent++;

                        continue;
                    }

                    SendWeeklyDigestEmailJob::dispatch(
                        tenantId: (int) $tenant->id,
                        recipient: $recipient,
                        weekStartDate: $weekStart->toDateString(),
                        weekEndDate: $weekEnd->toDateString(),
                    );
                    $emailsQueued++;
                }
            }
        });

        $reportsDeleted = 0;

        if (! $dryRun && $storeReport && $reportRetentionDays > 0) {
            $reportsDeleted = $reportService->cleanupExpired($reportRetentionDays);
        }

        $summaryPrefix = $dryRun ? 'Weekly digest dry-run complete.' : 'Weekly digest dispatch complete.';

        $this->info(sprintf(
            '%s tenants=%d queued=%d sent_sync=%d skipped_no_recipients=%d reports_stored=%d reports_deleted=%d window=%s..%s',
            $summaryPrefix,
            $tenantsProcessed,
            $emailsQueued,
            $emailsSent,
            $tenantsSkippedNoRecipients,
            $reportsStored,
            $reportsDeleted,
            $weekStart->toDateString(),
            $weekEnd->toDateString(),
        ));

        return self::SUCCESS;
    }

    /**
     * @return array{start: Carbon, end: Carbon}|null
     */
    private function resolveWindow(): ?array
    {
        $weekStartOption = is_string($this->option('week_start')) ? trim((string) $this->option('week_start')) : '';
        $weekEndOption = is_string($this->option('week_end')) ? trim((string) $this->option('week_end')) : '';

        if ($weekStartOption === '' && $weekEndOption === '') {
            return app(WeeklyDigestService::class)->defaultWindow(now());
        }

        $start = null;
        $end = null;

        if ($weekStartOption !== '') {
            try {
                $start = Carbon::createFromFormat('Y-m-d', $weekStartOption)->startOfDay();
            } catch (\Throwable) {
                $this->error('Invalid --week_start format, expected Y-m-d.');

                return null;
            }
        }

        if ($weekEndOption !== '') {
            try {
                $end = Carbon::createFromFormat('Y-m-d', $weekEndOption)->endOfDay();
            } catch (\Throwable) {
                $this->error('Invalid --week_end format, expected Y-m-d.');

                return null;
            }
        }

        if (! $start instanceof Carbon && $end instanceof Carbon) {
            $start = $end->copy()->subDays(6)->startOfDay();
        }

        if ($start instanceof Carbon && ! $end instanceof Carbon) {
            $end = $start->copy()->addDays(6)->endOfDay();
        }

        if (! $start instanceof Carbon || ! $end instanceof Carbon) {
            $this->error('Unable to resolve digest window.');

            return null;
        }

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        if ($start->diffInDays($end) > 31) {
            $this->error('Digest window is too wide (max 31 days).');

            return null;
        }

        return [
            'start' => $start,
            'end' => $end,
        ];
    }

    private function resolveStoreReportOption(): bool
    {
        if ((bool) $this->option('no-store-report')) {
            return false;
        }

        if ((bool) $this->option('store-report')) {
            return true;
        }

        return (bool) config('wids.reports.weekly_digest.store_report', true);
    }

    private function resolveReportRetentionDays(): int
    {
        $option = $this->option('report-retention-days');

        if (is_numeric($option)) {
            return max(0, min(3650, (int) $option));
        }

        return max(0, min(3650, (int) config('wids.reports.weekly_digest.report_pack_retention_days', 180)));
    }
}
