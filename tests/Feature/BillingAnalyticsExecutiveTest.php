<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantUsageMetric;
use App\Models\User;
use App\Services\BillingUsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BillingAnalyticsExecutiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_billing_dashboard_shows_forecast_risk_levels_and_metric_explorer(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-10 12:00:00', 'UTC'));

        config()->set('wids.billing.plan_tiers.lite.caps.'.BillingUsageService::METRIC_API_REQUESTS_TOTAL, 100);
        config()->set('wids.billing.plan_tiers.lite.caps.'.BillingUsageService::METRIC_SCANS_TOTAL, 200);

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
            'plan_tier' => 'lite',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $monthStart = now()->startOfMonth()->toDateString();

        TenantUsageMetric::query()->create([
            'tenant_id' => $tenant->id,
            'period_type' => BillingUsageService::PERIOD_MONTH,
            'period_start_date' => $monthStart,
            'metric_key' => BillingUsageService::METRIC_API_REQUESTS_TOTAL,
            'value' => 120,
        ]);

        TenantUsageMetric::query()->create([
            'tenant_id' => $tenant->id,
            'period_type' => BillingUsageService::PERIOD_MONTH,
            'period_start_date' => $monthStart,
            'metric_key' => BillingUsageService::METRIC_SCANS_TOTAL,
            'value' => 80,
        ]);

        TenantUsageMetric::query()->create([
            'tenant_id' => $tenant->id,
            'period_type' => BillingUsageService::PERIOD_DAY,
            'period_start_date' => '2026-02-09',
            'metric_key' => BillingUsageService::METRIC_API_REQUESTS_TOTAL,
            'value' => 35,
        ]);

        TenantUsageMetric::query()->create([
            'tenant_id' => $tenant->id,
            'period_type' => BillingUsageService::PERIOD_DAY,
            'period_start_date' => '2026-02-10',
            'metric_key' => BillingUsageService::METRIC_API_REQUESTS_TOTAL,
            'value' => 50,
        ]);

        $this->actingAs($owner);

        $response = $this->get(route('billing.index', [
            'metric_key' => BillingUsageService::METRIC_API_REQUESTS_TOTAL,
        ]));

        $response->assertOk();
        $response->assertSee('Forecast breaches');
        $response->assertSee('Executive cap forecast (month)');
        $response->assertSee('Projected end of month');
        $response->assertSee('Soonest forecast breach:');
        $response->assertSee('2026-02-10');
        $response->assertSee('Metric trend explorer (daily)');
        $response->assertSee('Over cap');
        $response->assertSee('Forecast breach');
        $response->assertSee('last_scanned_from=2026-02-01', false);
        $response->assertSee('last_scanned_to=2026-02-28', false);
        $response->assertSee('metric_key='.BillingUsageService::METRIC_API_REQUESTS_TOTAL, false);
    }

    public function test_billing_metric_day_drilldown_can_target_scan_day_filters(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-10 12:00:00', 'UTC'));

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
            'plan_tier' => 'lite',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        TenantUsageMetric::query()->create([
            'tenant_id' => $tenant->id,
            'period_type' => BillingUsageService::PERIOD_DAY,
            'period_start_date' => '2026-02-10',
            'metric_key' => BillingUsageService::METRIC_SCANS_TOTAL,
            'value' => 44,
        ]);

        $this->actingAs($owner);

        $response = $this->get(route('billing.index', [
            'metric_key' => BillingUsageService::METRIC_SCANS_TOTAL,
            'metric_day' => '2026-02-10',
        ]));

        $response->assertOk();
        $response->assertSee('Selected day: 2026-02-10');
        $response->assertSee('Open selected day drilldown');
        $response->assertSee('last_scanned_from=2026-02-10', false);
        $response->assertSee('last_scanned_to=2026-02-10', false);
    }
}
