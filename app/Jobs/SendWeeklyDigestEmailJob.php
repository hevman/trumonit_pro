<?php

namespace App\Jobs;

use App\Mail\WeeklyDigestMail;
use App\Models\Tenant;
use App\Services\WeeklyDigestService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendWeeklyDigestEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60, 300, 900];

    public function __construct(
        public readonly int $tenantId,
        public readonly string $recipient,
        public readonly string $weekStartDate,
        public readonly string $weekEndDate,
    ) {
        $this->onQueue('wids');
    }

    public function handle(): void
    {
        if (! (bool) config('wids.reports.weekly_digest.enabled', true)) {
            return;
        }

        $recipient = trim($this->recipient);

        if ($recipient === '' || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $tenant = Tenant::query()->find($this->tenantId);

        if (! $tenant) {
            return;
        }

        try {
            $weekStart = Carbon::createFromFormat('Y-m-d', $this->weekStartDate)->startOfDay();
            $weekEnd = Carbon::createFromFormat('Y-m-d', $this->weekEndDate)->endOfDay();
        } catch (\Throwable $e) {
            Log::warning('Invalid weekly digest window dates.', [
                'tenant_id' => $this->tenantId,
                'recipient' => $recipient,
                'week_start' => $this->weekStartDate,
                'week_end' => $this->weekEndDate,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $digest = app(WeeklyDigestService::class)->buildTenantDigest($tenant, $weekStart, $weekEnd);

        Mail::to($recipient)->send(new WeeklyDigestMail($digest));
    }

    public function failed(\Throwable $e): void
    {
        Log::warning('Weekly digest email delivery failed.', [
            'tenant_id' => $this->tenantId,
            'recipient' => $this->recipient,
            'week_start' => $this->weekStartDate,
            'week_end' => $this->weekEndDate,
            'error' => $e->getMessage(),
        ]);
    }
}

