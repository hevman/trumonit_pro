<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\BillingCheckoutSession;
use App\Models\MonitoredUrl;
use App\Models\TenantUsageMetric;
use App\Models\WeeklyDigestReport;
use App\Services\BillingPaymentService;
use App\Services\BillingPlanService;
use App\Services\BillingUsageService;
use App\Services\TenantQuotaService;
use App\Services\WeeklyDigestReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class BillingController extends Controller
{
    public function index(Request $request)
    {
        $tenant = $request->user()?->tenant;
        abort_if(! $tenant, 400, 'User has no tenant.');

        $filters = $request->validate([
            'metric_key' => ['nullable', 'string', 'max:64'],
            'metric_day' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $billingUsage = app(BillingUsageService::class);
        $billingPlanService = app(BillingPlanService::class);
        $tenantQuotaService = app(TenantQuotaService::class);
        $metricDefinitions = $billingUsage->metricDefinitions();
        $metricKeys = array_values(array_keys($metricDefinitions));
        $periodStart = now()->startOfMonth();
        $monthStart = $periodStart->copy()->startOfMonth();
        $monthEnd = $periodStart->copy()->endOfMonth();

        $usage = $billingUsage->metricsForTenantPeriod(
            tenantId: (int) $tenant->id,
            periodType: BillingUsageService::PERIOD_MONTH,
            period: $periodStart,
        );

        $plan = $billingUsage->billingPlanForTenant($tenant);
        $rows = [];
        $now = now();
        $elapsedDays = max(1, (int) $now->day);
        $daysInMonth = max(1, (int) $now->daysInMonth);

        foreach ($metricDefinitions as $metricKey => $metricLabel) {
            $used = max(0, (int) ($usage[$metricKey] ?? 0));
            $included = max(0, (int) ($plan['included'][$metricKey] ?? 0));
            $cap = max(0, (int) ($plan['caps'][$metricKey] ?? 0));
            $dailyRunRate = round($used / $elapsedDays, 2);

            $utilizationPct = $cap > 0
                ? round(($used / $cap) * 100, 1)
                : null;
            $projectedEndOfMonth = (int) ceil(($used / $elapsedDays) * $daysInMonth);
            $projectedCapPct = $cap > 0
                ? round(($projectedEndOfMonth / $cap) * 100, 1)
                : null;
            $daysToCap = null;
            $forecastBreachDate = null;

            if ($cap > 0) {
                if ($used >= $cap) {
                    $daysToCap = 0;
                    $forecastBreachDate = $now->copy()->toDateString();
                } elseif ($dailyRunRate > 0) {
                    $daysToCap = (int) ceil(($cap - $used) / $dailyRunRate);
                    $forecastBreachDate = $now->copy()->addDays($daysToCap)->toDateString();
                }
            }

            $riskLevel = match (true) {
                $cap <= 0 => 'uncapped',
                $used >= $cap => 'over_cap',
                $projectedCapPct !== null && $projectedCapPct >= 100 => 'forecast_breach',
                $utilizationPct !== null && $utilizationPct >= 80 => 'near_cap',
                default => 'healthy',
            };

            $rows[] = [
                'key' => $metricKey,
                'label' => $metricLabel,
                'used' => $used,
                'included' => $included,
                'cap' => $cap,
                'utilization_pct' => $utilizationPct,
                'projected_end_of_month' => $projectedEndOfMonth,
                'projected_cap_pct' => $projectedCapPct,
                'daily_run_rate' => $dailyRunRate,
                'days_to_cap' => $daysToCap,
                'forecast_breach_date' => $forecastBreachDate,
                'risk_level' => $riskLevel,
            ];
        }

        $periodStartDate = $monthStart->toDateString();
        $periodEndDate = $monthEnd->toDateString();

        $executiveRows = collect($rows)
            ->filter(fn (array $row): bool => ((int) ($row['cap'] ?? 0)) > 0)
            ->sortByDesc(fn (array $row): float => (float) ($row['projected_cap_pct'] ?? 0.0))
            ->values();

        $executiveForecast = [
            'labels' => [],
            'utilization_pct' => [],
            'projected_cap_pct' => [],
            'drilldown_urls' => [],
            'max_utilization_pct' => 100.0,
            'max_projected_cap_pct' => 100.0,
            'risk_breakdown' => [
                'healthy' => 0,
                'near_cap' => 0,
                'forecast_breach' => 0,
                'over_cap' => 0,
            ],
            'has_data' => false,
            'soonest_breach_date' => null,
            'soonest_breach_metric' => null,
        ];

        foreach ($executiveRows as $executiveRow) {
            $metricKey = (string) ($executiveRow['key'] ?? '');
            $metricLabel = (string) ($executiveRow['label'] ?? $metricKey);
            $utilizationPct = round((float) ($executiveRow['utilization_pct'] ?? 0), 1);
            $projectedCapPct = round((float) ($executiveRow['projected_cap_pct'] ?? 0), 1);
            $riskLevel = (string) ($executiveRow['risk_level'] ?? 'healthy');
            $forecastBreachDate = is_string($executiveRow['forecast_breach_date'] ?? null)
                ? trim((string) $executiveRow['forecast_breach_date'])
                : null;

            $executiveForecast['labels'][] = $metricLabel;
            $executiveForecast['utilization_pct'][] = $utilizationPct;
            $executiveForecast['projected_cap_pct'][] = $projectedCapPct;
            $executiveForecast['drilldown_urls'][] = $this->metricPeriodDrilldownUrl($metricKey, $periodStartDate, $periodEndDate);
            $executiveForecast['has_data'] = $executiveForecast['has_data'] || $utilizationPct > 0 || $projectedCapPct > 0;
            $executiveForecast['max_utilization_pct'] = max((float) $executiveForecast['max_utilization_pct'], $utilizationPct, 100.0);
            $executiveForecast['max_projected_cap_pct'] = max((float) $executiveForecast['max_projected_cap_pct'], $projectedCapPct, 100.0);

            if (isset($executiveForecast['risk_breakdown'][$riskLevel])) {
                $executiveForecast['risk_breakdown'][$riskLevel]++;
            }

            if ($forecastBreachDate !== null && $forecastBreachDate !== '') {
                $currentSoonest = is_string($executiveForecast['soonest_breach_date'] ?? null)
                    ? (string) $executiveForecast['soonest_breach_date']
                    : null;

                if ($currentSoonest === null || $forecastBreachDate < $currentSoonest) {
                    $executiveForecast['soonest_breach_date'] = $forecastBreachDate;
                    $executiveForecast['soonest_breach_metric'] = $metricLabel;
                }
            }
        }

        $fallbackMetricKey = in_array(BillingUsageService::METRIC_SCANS_TOTAL, $metricKeys, true)
            ? BillingUsageService::METRIC_SCANS_TOTAL
            : ($metricKeys[0] ?? BillingUsageService::METRIC_API_REQUESTS_TOTAL);
        $requestedMetricKey = is_string($filters['metric_key'] ?? null) ? trim((string) $filters['metric_key']) : '';
        $configuredDefaultMetric = trim((string) config('wids.billing.enforced_metric_key', $fallbackMetricKey));
        $selectedMetricKey = in_array($requestedMetricKey, $metricKeys, true)
            ? $requestedMetricKey
            : (in_array($configuredDefaultMetric, $metricKeys, true) ? $configuredDefaultMetric : $fallbackMetricKey);
        $selectedMetricDay = is_string($filters['metric_day'] ?? null) ? trim((string) $filters['metric_day']) : null;

        if (is_string($selectedMetricDay) && $selectedMetricDay !== '') {
            try {
                $selectedMetricDayDate = Carbon::createFromFormat('Y-m-d', $selectedMetricDay)->startOfDay();
            } catch (\Throwable) {
                $selectedMetricDayDate = null;
            }

            if (! $selectedMetricDayDate || $selectedMetricDayDate->lessThan($monthStart) || $selectedMetricDayDate->greaterThan($monthEnd)) {
                $selectedMetricDay = null;
            } else {
                $selectedMetricDay = $selectedMetricDayDate->toDateString();
            }
        } else {
            $selectedMetricDay = null;
        }

        $metricsTrend = [
            'labels' => [],
            'dates' => [],
            'scans' => [],
            'incidents' => [],
            'api_requests' => [],
            'storage_mb' => [],
            'max_scans' => 1,
            'max_incidents' => 1,
            'max_api_requests' => 1,
            'max_storage_mb' => 1,
        ];

        $selectedMetric = [
            'key' => $selectedMetricKey,
            'label' => (string) ($metricDefinitions[$selectedMetricKey] ?? $selectedMetricKey),
            'unit' => $this->metricChartUnit($selectedMetricKey),
            'values' => [],
            'labels' => [],
            'dates' => [],
            'drilldown_urls' => [],
            'max_value' => 1,
            'has_data' => false,
            'latest_value' => null,
            'selected_day' => $selectedMetricDay,
            'selected_day_value' => null,
            'selected_day_cap_pct' => null,
            'selected_day_drilldown_url' => null,
            'cap' => max(0, (int) ($plan['caps'][$selectedMetricKey] ?? 0)),
            'included' => max(0, (int) ($plan['included'][$selectedMetricKey] ?? 0)),
        ];

        if (Schema::hasTable('tenant_usage_metrics')) {
            $monthStartString = $monthStart->toDateString();
            $monthEndString = $monthEnd->toDateString();
            $dayBuckets = [];

            for ($date = $monthStart->copy(); $date->lessThanOrEqualTo($monthEnd); $date->addDay()) {
                $key = $date->toDateString();
                $dayBuckets[$key] = [
                    'label' => $date->format('d'),
                ];

                foreach ($metricKeys as $metricKey) {
                    $dayBuckets[$key][$metricKey] = 0;
                }
            }

            $dailyRows = TenantUsageMetric::query()
                ->where('tenant_id', (int) $tenant->id)
                ->where('period_type', BillingUsageService::PERIOD_DAY)
                ->whereBetween('period_start_date', [$monthStartString, $monthEndString])
                ->whereIn('metric_key', $metricKeys)
                ->get(['period_start_date', 'metric_key', 'value']);

            foreach ($dailyRows as $dailyRow) {
                $periodKey = (string) $dailyRow->period_start_date;
                $metricKey = (string) $dailyRow->metric_key;

                if (! isset($dayBuckets[$periodKey])) {
                    continue;
                }

                $dayBuckets[$periodKey][$metricKey] = max(0, (int) $dailyRow->value);
            }

            foreach ($dayBuckets as $bucketDate => $bucket) {
                $metricsTrend['labels'][] = $bucket['label'];
                $metricsTrend['dates'][] = (string) $bucketDate;
                $metricsTrend['scans'][] = (int) $bucket[BillingUsageService::METRIC_SCANS_TOTAL];
                $metricsTrend['incidents'][] = (int) $bucket[BillingUsageService::METRIC_INCIDENTS_TOTAL];
                $metricsTrend['api_requests'][] = (int) $bucket[BillingUsageService::METRIC_API_REQUESTS_TOTAL];
                $metricsTrend['storage_mb'][] = round(((int) $bucket[BillingUsageService::METRIC_STORAGE_BYTES_ADDED]) / 1_048_576, 2);

                $metricRawValue = (int) ($bucket[$selectedMetricKey] ?? 0);
                $metricChartValue = $this->metricChartValue($selectedMetricKey, $metricRawValue);

                $selectedMetric['labels'][] = (string) $bucket['label'];
                $selectedMetric['dates'][] = (string) $bucketDate;
                $selectedMetric['values'][] = $metricChartValue;
                $selectedMetric['drilldown_urls'][] = $this->metricDrilldownUrl($selectedMetricKey, (string) $bucketDate);
                $selectedMetric['has_data'] = $selectedMetric['has_data'] || $metricRawValue > 0;

                if ($metricRawValue > 0) {
                    $selectedMetric['latest_value'] = $metricChartValue;
                }
            }

            $metricsTrend['max_scans'] = max(1, max($metricsTrend['scans'] ?: [0]));
            $metricsTrend['max_incidents'] = max(1, max($metricsTrend['incidents'] ?: [0]));
            $metricsTrend['max_api_requests'] = max(1, max($metricsTrend['api_requests'] ?: [0]));
            $metricsTrend['max_storage_mb'] = max(1.0, max($metricsTrend['storage_mb'] ?: [0.0]));
            $selectedMetric['max_value'] = max(1.0, max($selectedMetric['values'] ?: [0.0]));

            if (is_string($selectedMetricDay) && isset($dayBuckets[$selectedMetricDay])) {
                $selectedDayRawValue = (int) ($dayBuckets[$selectedMetricDay][$selectedMetricKey] ?? 0);
                $selectedMetric['selected_day_value'] = $this->metricChartValue($selectedMetricKey, $selectedDayRawValue);
                $selectedMetric['selected_day_drilldown_url'] = $this->metricDrilldownUrl($selectedMetricKey, $selectedMetricDay);

                if (($selectedMetric['cap'] ?? 0) > 0) {
                    $selectedMetric['selected_day_cap_pct'] = round(($selectedDayRawValue / max(1, (int) $selectedMetric['cap'])) * 100, 1);
                }
            }
        }

        $highRiskMetrics = collect($rows)
            ->filter(fn (array $row): bool => ($row['cap'] ?? 0) > 0 && (($row['projected_cap_pct'] ?? 0) >= 100))
            ->count();
        $forecastBreachCount = collect($rows)
            ->filter(fn (array $row): bool => in_array((string) ($row['risk_level'] ?? ''), ['over_cap', 'forecast_breach'], true))
            ->count();
        $nearCapMetrics = collect($rows)
            ->filter(fn (array $row): bool => (string) ($row['risk_level'] ?? '') === 'near_cap')
            ->count();
        $uncappedMetrics = collect($rows)
            ->filter(fn (array $row): bool => (string) ($row['risk_level'] ?? '') === 'uncapped')
            ->count();

        $availablePlanTiers = $billingPlanService->availablePlanTiers();
        $resolvedTierByPlanService = $billingPlanService->currentPlanTierForTenant($tenant);
        $resolvedTierByUsagePlan = strtolower(trim((string) ($plan['tier'] ?? '')));
        $currentPlanTier = $resolvedTierByUsagePlan !== '' ? $resolvedTierByUsagePlan : $resolvedTierByPlanService;
        $planTierDisplay = strtoupper($currentPlanTier);
        $planMismatchDetected = $resolvedTierByUsagePlan !== '' && $resolvedTierByPlanService !== $resolvedTierByUsagePlan;
        $billingSubscriptionStatus = strtolower(trim((string) ($tenant->billing_subscription_status ?? '')));
        $tenantLimits = $tenantQuotaService->limitsForTenant($tenant);
        $monitoredUrlCount = MonitoredUrl::query()
            ->where('tenant_id', (int) $tenant->id)
            ->count();
        $planHealthSummary = [
            'forecast_breach' => (int) $forecastBreachCount,
            'near_cap' => (int) $nearCapMetrics,
            'uncapped' => (int) $uncappedMetrics,
            'high_risk' => (int) $highRiskMetrics,
        ];

        $checkoutSessions = BillingCheckoutSession::query()
            ->where('tenant_id', $tenant->id)
            ->latest('id')
            ->limit(10)
            ->get();

        $weeklyDigestReports = collect();

        if (Schema::hasTable('weekly_digest_reports')) {
            $weeklyDigestReports = WeeklyDigestReport::query()
                ->where('tenant_id', (int) $tenant->id)
                ->orderByDesc('generated_at')
                ->orderByDesc('id')
                ->limit(12)
                ->get();
        }

        return view('app.billing.index', [
            'tenant' => $tenant,
            'periodStart' => $periodStart,
            'planTier' => $planTierDisplay,
            'currentPlanTier' => $currentPlanTier,
            'availablePlanTiers' => $availablePlanTiers,
            'rows' => $rows,
            'checkoutSessions' => $checkoutSessions,
            'weeklyDigestReports' => $weeklyDigestReports,
            'paymentProvider' => app(BillingPaymentService::class)->providerKey(),
            'enforcementEnabled' => (bool) config('wids.billing.enforcement_enabled', false),
            'enforcedMetricKey' => (string) config('wids.billing.enforced_metric_key', BillingUsageService::METRIC_API_REQUESTS_TOTAL),
            'metricsTrend' => $metricsTrend,
            'highRiskMetrics' => $highRiskMetrics,
            'forecastBreachCount' => $forecastBreachCount,
            'nearCapMetrics' => $nearCapMetrics,
            'uncappedMetrics' => $uncappedMetrics,
            'metricDefinitions' => $metricDefinitions,
            'selectedMetric' => $selectedMetric,
            'executiveForecast' => $executiveForecast,
            'planMismatchDetected' => $planMismatchDetected,
            'billingSubscriptionStatus' => $billingSubscriptionStatus,
            'tenantLimits' => $tenantLimits,
            'monitoredUrlCount' => (int) $monitoredUrlCount,
            'planHealthSummary' => $planHealthSummary,
        ]);
    }

    public function downloadWeeklyDigestReport(Request $request, WeeklyDigestReport $weeklyDigestReport, string $format)
    {
        $tenantId = (int) ($request->user()?->tenant_id ?? 0);

        abort_if($tenantId <= 0, 400, 'User has no tenant.');
        abort_if((int) $weeklyDigestReport->tenant_id !== $tenantId, 404);

        $payload = app(WeeklyDigestReportService::class)->resolveDownloadPayload($weeklyDigestReport, $format);

        abort_if(! is_array($payload), 404);

        $filename = (string) ($payload['filename'] ?? 'weekly-digest-report');
        $contentType = (string) ($payload['content_type'] ?? 'application/octet-stream');
        $content = (string) ($payload['content'] ?? '');

        return response($content, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function changePlan(Request $request)
    {
        $tenant = $request->user()?->tenant;
        abort_if(! $tenant, 400, 'User has no tenant.');

        $availablePlanTiers = app(BillingPlanService::class)->availablePlanTiers();

        $data = $request->validate([
            'target_plan_tier' => ['required', 'string', 'max:32', 'in:'.implode(',', $availablePlanTiers)],
        ]);

        try {
            $session = app(BillingPlanService::class)->requestPlanChange(
                tenant: $tenant,
                actorUserId: (int) ($request->user()?->id ?? 0),
                targetPlanTier: (string) $data['target_plan_tier'],
                request: $request,
            );
        } catch (\InvalidArgumentException $e) {
            throw ValidationException::withMessages([
                'target_plan_tier' => [$e->getMessage()],
            ]);
        }

        if ($session->status === 'completed') {
            return redirect()
                ->route('billing.index')
                ->with('status', 'Plan changed successfully.');
        }

        if (is_string($session->checkout_url) && trim($session->checkout_url) !== '') {
            return redirect()
                ->route('billing.index')
                ->with('status', 'Checkout session created. Complete payment to activate new plan.')
                ->with('checkout_url', $session->checkout_url);
        }

        return redirect()
            ->route('billing.index')
            ->with('status', 'Plan change session created. Configure provider checkout or complete manually.');
    }

    private function metricChartValue(string $metricKey, int $rawValue): float|int
    {
        return $metricKey === BillingUsageService::METRIC_STORAGE_BYTES_ADDED
            ? round($rawValue / 1_048_576, 2)
            : $rawValue;
    }

    private function metricChartUnit(string $metricKey): string
    {
        return $metricKey === BillingUsageService::METRIC_STORAGE_BYTES_ADDED
            ? 'MB'
            : 'events';
    }

    private function metricDrilldownUrl(string $metricKey, string $date): string
    {
        $date = trim($date);

        if ($date === '') {
            return route('billing.index');
        }

        return match ($metricKey) {
            BillingUsageService::METRIC_SCANS_TOTAL,
            BillingUsageService::METRIC_SCANS_ERROR_TOTAL,
            BillingUsageService::METRIC_STORAGE_BYTES_ADDED => route('monitored-urls.index', [
                'last_scanned_from' => $date,
                'last_scanned_to' => $date,
            ]),
            BillingUsageService::METRIC_INCIDENTS_TOTAL => route('incidents.index', [
                'detected_from' => $date,
                'detected_to' => $date,
            ]),
            BillingUsageService::METRIC_INCIDENTS_HIGH_TOTAL => route('incidents.index', [
                'severity_min' => 80,
                'detected_from' => $date,
                'detected_to' => $date,
            ]),
            default => route('billing.index', [
                'metric_key' => $metricKey,
                'metric_day' => $date,
            ]),
        };
    }

    private function metricPeriodDrilldownUrl(string $metricKey, string $fromDate, string $toDate): string
    {
        $fromDate = trim($fromDate);
        $toDate = trim($toDate);

        if ($fromDate === '' || $toDate === '') {
            return route('billing.index', ['metric_key' => $metricKey]);
        }

        return match ($metricKey) {
            BillingUsageService::METRIC_SCANS_TOTAL,
            BillingUsageService::METRIC_SCANS_ERROR_TOTAL,
            BillingUsageService::METRIC_STORAGE_BYTES_ADDED => route('monitored-urls.index', [
                'last_scanned_from' => $fromDate,
                'last_scanned_to' => $toDate,
            ]),
            BillingUsageService::METRIC_INCIDENTS_TOTAL => route('incidents.index', [
                'detected_from' => $fromDate,
                'detected_to' => $toDate,
            ]),
            BillingUsageService::METRIC_INCIDENTS_HIGH_TOTAL => route('incidents.index', [
                'severity_min' => 80,
                'detected_from' => $fromDate,
                'detected_to' => $toDate,
            ]),
            default => route('billing.index', ['metric_key' => $metricKey]),
        };
    }

}
