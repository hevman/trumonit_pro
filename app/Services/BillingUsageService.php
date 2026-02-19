<?php

namespace App\Services;

use App\Models\Incident;
use App\Models\MonitoredUrl;
use App\Models\Tenant;
use App\Models\TenantUsageMetric;
use App\Models\UrlSnapshot;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class BillingUsageService
{
    public const PERIOD_DAY = 'day';

    public const PERIOD_MONTH = 'month';

    public const METRIC_API_REQUESTS_TOTAL = 'api_requests_total';

    public const METRIC_API_REQUESTS_WRITE_TOTAL = 'api_requests_write_total';

    public const METRIC_API_REQUESTS_4XX_TOTAL = 'api_requests_4xx_total';

    public const METRIC_API_REQUESTS_5XX_TOTAL = 'api_requests_5xx_total';

    public const METRIC_SCANS_TOTAL = 'scans_total';

    public const METRIC_SCANS_ERROR_TOTAL = 'scans_error_total';

    public const METRIC_STORAGE_BYTES_ADDED = 'storage_bytes_added';

    public const METRIC_INCIDENTS_TOTAL = 'incidents_total';

    public const METRIC_INCIDENTS_HIGH_TOTAL = 'incidents_high_total';

    private ?bool $usageTableAvailable = null;

    /**
     * @return array<string, string>
     */
    public function metricDefinitions(): array
    {
        return [
            self::METRIC_API_REQUESTS_TOTAL => 'API requests (all)',
            self::METRIC_API_REQUESTS_WRITE_TOTAL => 'API requests (write)',
            self::METRIC_API_REQUESTS_4XX_TOTAL => 'API requests (4xx)',
            self::METRIC_API_REQUESTS_5XX_TOTAL => 'API requests (5xx)',
            self::METRIC_SCANS_TOTAL => 'Scans executed',
            self::METRIC_SCANS_ERROR_TOTAL => 'Scans with error',
            self::METRIC_STORAGE_BYTES_ADDED => 'Stored bytes added',
            self::METRIC_INCIDENTS_TOTAL => 'Incidents created',
            self::METRIC_INCIDENTS_HIGH_TOTAL => 'High-severity incidents',
        ];
    }

    /**
     * @return array{tier: string, included: array<string, int>, caps: array<string, int>}
     */
    public function billingPlanForTenant(?Tenant $tenant): array
    {
        $defaultTier = $this->normalizeTier((string) config('wids.default_plan_tier', 'lite'));
        $configured = config('wids.billing.plan_tiers', []);
        $configured = is_array($configured) ? $configured : [];

        $tier = $this->normalizeTier((string) ($tenant?->plan_tier ?? $defaultTier));
        $plan = is_array($configured[$tier] ?? null) ? $configured[$tier] : null;

        if (! is_array($plan)) {
            $tier = $defaultTier;
            $plan = is_array($configured[$tier] ?? null) ? $configured[$tier] : [];
        }

        $included = $this->normalizeMetricMap(is_array($plan['included'] ?? null) ? $plan['included'] : []);
        $caps = $this->normalizeMetricMap(is_array($plan['caps'] ?? null) ? $plan['caps'] : []);

        return [
            'tier' => $tier,
            'included' => $included,
            'caps' => $caps,
        ];
    }

    public function metricCapForTenant(?Tenant $tenant, string $metricKey): int
    {
        $metricKey = trim($metricKey);

        if ($metricKey === '') {
            return 0;
        }

        $plan = $this->billingPlanForTenant($tenant);

        return max(0, (int) ($plan['caps'][$metricKey] ?? 0));
    }

    public function metricIncludedForTenant(?Tenant $tenant, string $metricKey): int
    {
        $metricKey = trim($metricKey);

        if ($metricKey === '') {
            return 0;
        }

        $plan = $this->billingPlanForTenant($tenant);

        return max(0, (int) ($plan['included'][$metricKey] ?? 0));
    }

    public function wouldExceedMonthlyCap(int $tenantId, string $metricKey, int $incrementBy = 1, \DateTimeInterface|string|null $at = null): bool
    {
        if ($tenantId <= 0) {
            return false;
        }

        $metricKey = trim($metricKey);

        if ($metricKey === '') {
            return false;
        }

        $incrementBy = max(0, $incrementBy);
        if ($incrementBy === 0) {
            return false;
        }

        $tenant = Tenant::query()
            ->select(['id', 'plan_tier'])
            ->find($tenantId);

        $cap = $this->metricCapForTenant($tenant, $metricKey);
        if ($cap <= 0) {
            return false;
        }

        $current = $this->metricValueForTenantPeriod($tenantId, self::PERIOD_MONTH, $metricKey, $at);

        return ($current + $incrementBy) > $cap;
    }

    public function recordApiRequest(int $tenantId, string $method, int $statusCode, \DateTimeInterface|string|null $at = null): void
    {
        if ($tenantId <= 0) {
            return;
        }

        $method = strtoupper(trim($method));
        $this->increment($tenantId, self::METRIC_API_REQUESTS_TOTAL, 1, $at);

        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $this->increment($tenantId, self::METRIC_API_REQUESTS_WRITE_TOTAL, 1, $at);
        }

        if ($statusCode >= 400 && $statusCode < 500) {
            $this->increment($tenantId, self::METRIC_API_REQUESTS_4XX_TOTAL, 1, $at);
        }

        if ($statusCode >= 500) {
            $this->increment($tenantId, self::METRIC_API_REQUESTS_5XX_TOTAL, 1, $at);
        }
    }

    public function recordSnapshotCreated(UrlSnapshot $snapshot): void
    {
        $tenantId = $this->tenantIdForSnapshot($snapshot);

        if ($tenantId <= 0) {
            return;
        }

        $this->increment($tenantId, self::METRIC_SCANS_TOTAL);

        if (is_string($snapshot->error_message) && trim($snapshot->error_message) !== '') {
            $this->increment($tenantId, self::METRIC_SCANS_ERROR_TOTAL);
        }

        $storedBodyBytes = max(0, (int) ($snapshot->body_bytes_stored ?? 0));
        $storedScreenshotBytes = max(0, (int) ($snapshot->screenshot_bytes_stored ?? 0));
        $storedBytes = $storedBodyBytes + $storedScreenshotBytes;

        if ($storedBytes > 0) {
            $this->increment($tenantId, self::METRIC_STORAGE_BYTES_ADDED, $storedBytes);
        }
    }

    public function recordIncidentCreated(Incident $incident): void
    {
        $tenantId = (int) ($incident->tenant_id ?? 0);

        if ($tenantId <= 0) {
            return;
        }

        $this->increment($tenantId, self::METRIC_INCIDENTS_TOTAL);

        $severity = max(1, min(100, (int) ($incident->severity ?? 1)));
        if ($severity >= 80) {
            $this->increment($tenantId, self::METRIC_INCIDENTS_HIGH_TOTAL);
        }
    }

    /**
     * @return array<string, int>
     */
    public function metricsForTenantPeriod(int $tenantId, string $periodType, \DateTimeInterface|string|null $period = null): array
    {
        if ($tenantId <= 0 || ! $this->canUseUsageTable()) {
            return [];
        }

        $periodType = $this->normalizePeriodType($periodType);
        $periodStartDate = $this->normalizePeriodStartDate($periodType, $period)->toDateString();

        $rows = TenantUsageMetric::query()
            ->where('tenant_id', $tenantId)
            ->where('period_type', $periodType)
            ->where('period_start_date', $periodStartDate)
            ->pluck('value', 'metric_key')
            ->all();

        $metrics = [];

        foreach ($rows as $metricKey => $value) {
            if (! is_string($metricKey)) {
                continue;
            }

            $metrics[$metricKey] = max(0, (int) $value);
        }

        return $metrics;
    }

    public function metricValueForTenantPeriod(int $tenantId, string $periodType, string $metricKey, \DateTimeInterface|string|null $period = null): int
    {
        if ($tenantId <= 0 || ! $this->canUseUsageTable()) {
            return 0;
        }

        $metricKey = trim($metricKey);

        if ($metricKey === '') {
            return 0;
        }

        $periodType = $this->normalizePeriodType($periodType);
        $periodStartDate = $this->normalizePeriodStartDate($periodType, $period)->toDateString();

        $value = TenantUsageMetric::query()
            ->where('tenant_id', $tenantId)
            ->where('period_type', $periodType)
            ->where('period_start_date', $periodStartDate)
            ->where('metric_key', $metricKey)
            ->value('value');

        return max(0, (int) ($value ?? 0));
    }

    public function increment(int $tenantId, string $metricKey, int $value = 1, \DateTimeInterface|string|null $at = null): void
    {
        if ($tenantId <= 0 || ! $this->canUseUsageTable()) {
            return;
        }

        $metricKey = trim($metricKey);
        $value = max(0, $value);

        if ($metricKey === '' || $value === 0) {
            return;
        }

        $at = $this->normalizeDateTime($at);

        $this->incrementPeriod($tenantId, self::PERIOD_DAY, $at->copy()->startOfDay()->toDateString(), $metricKey, $value);
        $this->incrementPeriod($tenantId, self::PERIOD_MONTH, $at->copy()->startOfMonth()->toDateString(), $metricKey, $value);
    }

    private function incrementPeriod(int $tenantId, string $periodType, string $periodStartDate, string $metricKey, int $value): void
    {
        $attrs = [
            'tenant_id' => $tenantId,
            'period_type' => $periodType,
            'period_start_date' => $periodStartDate,
            'metric_key' => $metricKey,
        ];

        $updated = TenantUsageMetric::query()
            ->where($attrs)
            ->increment('value', $value);

        if ($updated > 0) {
            return;
        }

        try {
            TenantUsageMetric::query()->create(array_merge($attrs, [
                'value' => $value,
            ]));
        } catch (QueryException $e) {
            if (! $this->isUniqueConstraintViolation($e)) {
                throw $e;
            }

            TenantUsageMetric::query()
                ->where($attrs)
                ->increment('value', $value);
        }
    }

    private function isUniqueConstraintViolation(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');

        return in_array($sqlState, ['23000', '23505'], true);
    }

    private function canUseUsageTable(): bool
    {
        if ($this->usageTableAvailable === true) {
            return true;
        }

        try {
            $this->usageTableAvailable = Schema::hasTable('tenant_usage_metrics');
        } catch (\Throwable) {
            $this->usageTableAvailable = false;
        }

        return $this->usageTableAvailable === true;
    }

    private function tenantIdForSnapshot(UrlSnapshot $snapshot): int
    {
        $tenantIdFromRelation = (int) ($snapshot->monitoredUrl?->tenant_id ?? 0);

        if ($tenantIdFromRelation > 0) {
            return $tenantIdFromRelation;
        }

        return (int) MonitoredUrl::query()
            ->whereKey($snapshot->monitored_url_id)
            ->value('tenant_id');
    }

    private function normalizeTier(string $tier): string
    {
        $tier = strtolower(trim($tier));

        return $tier !== '' ? $tier : 'lite';
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, int>
     */
    private function normalizeMetricMap(array $values): array
    {
        $normalized = [];

        foreach (array_keys($this->metricDefinitions()) as $metricKey) {
            $normalized[$metricKey] = max(0, (int) ($values[$metricKey] ?? 0));
        }

        return $normalized;
    }

    private function normalizePeriodType(string $periodType): string
    {
        $periodType = strtolower(trim($periodType));

        return in_array($periodType, [self::PERIOD_DAY, self::PERIOD_MONTH], true)
            ? $periodType
            : self::PERIOD_MONTH;
    }

    private function normalizePeriodStartDate(string $periodType, \DateTimeInterface|string|null $period = null): Carbon
    {
        $at = $this->normalizeDateTime($period);

        return $periodType === self::PERIOD_DAY
            ? $at->copy()->startOfDay()
            : $at->copy()->startOfMonth();
    }

    private function normalizeDateTime(\DateTimeInterface|string|null $at = null): Carbon
    {
        if ($at instanceof \DateTimeInterface) {
            return Carbon::instance(\DateTime::createFromInterface($at));
        }

        if (is_string($at) && trim($at) !== '') {
            return Carbon::parse($at);
        }

        return now();
    }
}
