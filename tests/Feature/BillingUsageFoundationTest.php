<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\MonitoredUrl;
use App\Models\Tenant;
use App\Models\TenantUsageMetric;
use App\Models\UrlSnapshot;
use App\Models\User;
use App\Services\BillingUsageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BillingUsageFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_requests_are_metered_and_monthly_cap_is_enforced(): void
    {
        config()->set('wids.billing.enforcement_enabled', true);
        config()->set('wids.billing.enforced_metric_key', BillingUsageService::METRIC_API_REQUESTS_TOTAL);
        config()->set('wids.billing.plan_tiers.lite.caps.'.BillingUsageService::METRIC_API_REQUESTS_TOTAL, 1);

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
            'plan_tier' => 'lite',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        Sanctum::actingAs($owner, ['wids.read', 'wids.write']);

        $this->getJson('/api/v1/me')->assertOk();
        $this->getJson('/api/v1/me')
            ->assertStatus(429)
            ->assertJsonPath('metric', BillingUsageService::METRIC_API_REQUESTS_TOTAL);

        $this->assertDatabaseHas('tenant_usage_metrics', [
            'tenant_id' => $tenant->id,
            'period_type' => BillingUsageService::PERIOD_MONTH,
            'period_start_date' => now()->startOfMonth()->toDateString(),
            'metric_key' => BillingUsageService::METRIC_API_REQUESTS_TOTAL,
            'value' => 1,
        ]);
    }

    public function test_snapshot_and_incident_creation_are_metered(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
            'plan_tier' => 'lite',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $monitor = MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $owner->id,
            'url' => 'https://example.com',
            'display_name' => 'Example',
            'is_active' => true,
            'frequency_minutes' => 5,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
            'scan_mode' => 'http',
        ]);

        $snapshotOk = UrlSnapshot::query()->create([
            'monitored_url_id' => $monitor->id,
            'fetched_at' => now(),
            'request_url' => $monitor->url,
            'final_url' => $monitor->url,
            'http_status' => 200,
            'headers' => [],
            'response_time_ms' => 80,
            'body_size' => 120,
            'body_bytes_stored' => 120,
            'screenshot_bytes_stored' => 80,
            'body_sha256' => hash('sha256', 'ok'),
        ]);

        $snapshotError = UrlSnapshot::query()->create([
            'monitored_url_id' => $monitor->id,
            'fetched_at' => now(),
            'request_url' => $monitor->url,
            'error_message' => 'timeout',
            'headers' => [],
            'body_sha256' => hash('sha256', 'err'),
        ]);

        Incident::query()->create([
            'tenant_id' => $tenant->id,
            'monitored_url_id' => $monitor->id,
            'previous_snapshot_id' => $snapshotOk->id,
            'current_snapshot_id' => $snapshotError->id,
            'kind' => 'fetch_error',
            'severity' => 90,
            'status' => 'open',
            'summary' => 'Fetch error',
            'details' => ['error' => 'timeout'],
            'detected_at' => now(),
            'last_seen_at' => now(),
            'occurrences' => 1,
            'fingerprint' => hash('sha256', 'billing-meter-incident'),
        ]);

        $periodType = BillingUsageService::PERIOD_MONTH;
        $periodStartDate = now()->startOfMonth()->toDateString();

        $this->assertDatabaseHas('tenant_usage_metrics', [
            'tenant_id' => $tenant->id,
            'period_type' => $periodType,
            'period_start_date' => $periodStartDate,
            'metric_key' => BillingUsageService::METRIC_SCANS_TOTAL,
            'value' => 2,
        ]);
        $this->assertDatabaseHas('tenant_usage_metrics', [
            'tenant_id' => $tenant->id,
            'period_type' => $periodType,
            'period_start_date' => $periodStartDate,
            'metric_key' => BillingUsageService::METRIC_SCANS_ERROR_TOTAL,
            'value' => 1,
        ]);
        $this->assertDatabaseHas('tenant_usage_metrics', [
            'tenant_id' => $tenant->id,
            'period_type' => $periodType,
            'period_start_date' => $periodStartDate,
            'metric_key' => BillingUsageService::METRIC_STORAGE_BYTES_ADDED,
            'value' => 200,
        ]);
        $this->assertDatabaseHas('tenant_usage_metrics', [
            'tenant_id' => $tenant->id,
            'period_type' => $periodType,
            'period_start_date' => $periodStartDate,
            'metric_key' => BillingUsageService::METRIC_INCIDENTS_TOTAL,
            'value' => 1,
        ]);
        $this->assertDatabaseHas('tenant_usage_metrics', [
            'tenant_id' => $tenant->id,
            'period_type' => $periodType,
            'period_start_date' => $periodStartDate,
            'metric_key' => BillingUsageService::METRIC_INCIDENTS_HIGH_TOTAL,
            'value' => 1,
        ]);
    }

    public function test_owner_can_open_billing_dashboard(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $this->actingAs($owner);

        $day = now()->toDateString();

        TenantUsageMetric::query()->create([
            'tenant_id' => $tenant->id,
            'period_type' => BillingUsageService::PERIOD_DAY,
            'period_start_date' => $day,
            'metric_key' => BillingUsageService::METRIC_SCANS_TOTAL,
            'value' => 12,
        ]);

        TenantUsageMetric::query()->create([
            'tenant_id' => $tenant->id,
            'period_type' => BillingUsageService::PERIOD_DAY,
            'period_start_date' => $day,
            'metric_key' => BillingUsageService::METRIC_INCIDENTS_TOTAL,
            'value' => 3,
        ]);

        $this->get(route('billing.index'))
            ->assertOk()
            ->assertSee('Billing / Usage')
            ->assertSee('Daily scans (current month)')
            ->assertSee('Projected cap risk')
            ->assertSee('Scan volume per day')
            ->assertSee('Incident volume per day')
            ->assertSee('last_scanned_from='.$day, false)
            ->assertSee('last_scanned_to='.$day, false)
            ->assertSee('detected_from='.$day, false)
            ->assertSee('detected_to='.$day, false);
    }

    public function test_member_cannot_open_billing_dashboard(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $member = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'member',
        ]);

        $this->actingAs($member);

        $this->get(route('billing.index'))->assertForbidden();
    }
}

