<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\MonitoredUrl;
use App\Models\Tenant;
use App\Models\UrlSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpsBusinessSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_summary_endpoint_is_disabled_by_default(): void
    {
        $this->get('/ops/business-summary')->assertNotFound();
    }

    public function test_business_summary_requires_token_and_returns_plan_distribution(): void
    {
        config()->set('ops.business.enabled', true);
        config()->set('ops.business.require_token', true);
        config()->set('ops.business.token', 'ops-business-token');

        $freeTenant = Tenant::query()->create([
            'name' => 'Free',
            'slug' => 'free',
            'plan_tier' => 'lite',
        ]);
        $proTenant = Tenant::query()->create([
            'name' => 'Pro',
            'slug' => 'pro',
            'plan_tier' => 'pro',
            'billing_subscription_status' => 'active',
        ]);
        $manualTenant = Tenant::query()->create([
            'name' => 'Manual',
            'slug' => 'manual',
            'plan_tier' => 'lite',
            'billing_subscription_status' => 'manual_active',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $proTenant->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $monitor = MonitoredUrl::query()->create([
            'tenant_id' => $proTenant->id,
            'created_by_user_id' => $owner->id,
            'url' => 'https://example.com',
            'display_name' => 'Example',
            'is_active' => true,
            'frequency_minutes' => 5,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        $snapshot = UrlSnapshot::query()->create([
            'monitored_url_id' => $monitor->id,
            'fetched_at' => now(),
            'request_url' => $monitor->url,
            'final_url' => $monitor->url,
            'http_status' => 503,
            'headers' => [],
            'response_time_ms' => 250,
            'body_size' => 0,
            'body_sha256' => hash('sha256', 'ops-business'),
            'error_message' => 'service unavailable',
        ]);

        Incident::query()->create([
            'tenant_id' => $proTenant->id,
            'monitored_url_id' => $monitor->id,
            'previous_snapshot_id' => null,
            'current_snapshot_id' => $snapshot->id,
            'kind' => 'unexpected_status',
            'severity' => 80,
            'status' => 'open',
            'summary' => 'Down',
            'details' => [],
            'detected_at' => now(),
            'last_seen_at' => now(),
            'occurrences' => 1,
            'fingerprint' => hash('sha256', 'ops-business-open-incident'),
        ]);

        $this->get('/ops/business-summary')->assertForbidden();

        $response = $this
            ->withHeader('Authorization', 'Bearer ops-business-token')
            ->get('/ops/business-summary');

        $response->assertOk();
        $response->assertJsonPath('service', 'TruMonit');
        $response->assertJsonPath('accounts_total', 3);
        $response->assertJsonPath('subscribed_accounts_total', 2);
        $response->assertJsonPath('free_accounts_total', 1);
        $response->assertJsonPath('users_total', 1);
        $response->assertJsonPath('counters.tenants_total', 3);
        $response->assertJsonPath('counters.monitored_urls_total', 1);
        $response->assertJsonPath('counters.open_incidents_total', 1);

        $plans = collect((array) $response->json('plans'))
            ->mapWithKeys(fn (array $row): array => [(string) ($row['plan'] ?? '') => (int) ($row['accounts'] ?? 0)]);

        $this->assertSame(2, (int) ($plans['lite'] ?? 0));
        $this->assertSame(1, (int) ($plans['pro'] ?? 0));
    }

    public function test_business_summary_fails_closed_when_token_is_required_but_missing(): void
    {
        config()->set('ops.business.enabled', true);
        config()->set('ops.business.require_token', true);
        config()->set('ops.business.token', null);

        $this->get('/ops/business-summary')->assertStatus(503);
    }
}
