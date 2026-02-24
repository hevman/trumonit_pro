<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingEffectivePlanTierTest extends TestCase
{
    use RefreshDatabase;

    public function test_billing_page_shows_free_plan_for_unpaid_tenant(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
            'plan_tier' => 'lite',
            'billing_subscription_status' => null,
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'is_active' => true,
        ]);

        $this->actingAs($owner);

        $response = $this->get(route('billing.index'));

        $response->assertOk();
        $response->assertViewHas('currentPlanTier', 'free');
        $response->assertViewHas('planTier', 'FREE');
    }
}

