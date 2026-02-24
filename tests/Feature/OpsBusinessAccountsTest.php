<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpsBusinessAccountsTest extends TestCase
{
    use RefreshDatabase;

    public function test_business_accounts_uses_effective_free_plan_for_unpaid_tenants(): void
    {
        config()->set('ops.business.enabled', true);
        config()->set('ops.business.require_token', true);
        config()->set('ops.business.token', 'ops-business-token');

        $freeTenant = Tenant::query()->create([
            'name' => 'Free',
            'slug' => 'free',
            'plan_tier' => 'lite',
            'billing_subscription_status' => null,
        ]);
        $paidTenant = Tenant::query()->create([
            'name' => 'Paid',
            'slug' => 'paid',
            'plan_tier' => 'pro',
            'billing_subscription_status' => 'active',
        ]);
        $unpaidTenant = Tenant::query()->create([
            'name' => 'Unpaid',
            'slug' => 'unpaid',
            'plan_tier' => 'lite',
            'billing_subscription_status' => 'canceled',
        ]);

        User::factory()->create([
            'tenant_id' => $freeTenant->id,
            'role' => 'owner',
            'name' => 'Free Owner',
            'email' => 'free@example.com',
            'is_active' => true,
        ]);
        User::factory()->create([
            'tenant_id' => $paidTenant->id,
            'role' => 'owner',
            'name' => 'Paid Owner',
            'email' => 'paid@example.com',
            'is_active' => true,
        ]);
        User::factory()->create([
            'tenant_id' => $unpaidTenant->id,
            'role' => 'owner',
            'name' => 'Unpaid Owner',
            'email' => 'unpaid@example.com',
            'is_active' => true,
        ]);

        $response = $this
            ->withHeader('Authorization', 'Bearer ops-business-token')
            ->get('/ops/business-accounts');

        $response->assertOk();
        $response->assertJsonPath('service', 'TruMonit');
        $response->assertJsonPath('accounts_total', 3);
        $response->assertJsonPath('accounts.0.plan_tier', 'free');
        $response->assertJsonPath('accounts.0.payment_status', 'free');
        $response->assertJsonPath('accounts.0.subscription_status', null);
        $response->assertJsonPath('accounts.1.plan_tier', 'pro');
        $response->assertJsonPath('accounts.1.payment_status', 'paid');
        $response->assertJsonPath('accounts.1.subscription_status', 'active');
        $response->assertJsonPath('accounts.2.plan_tier', 'free');
        $response->assertJsonPath('accounts.2.payment_status', 'unpaid');
        $response->assertJsonPath('accounts.2.subscription_status', 'canceled');
    }
}

