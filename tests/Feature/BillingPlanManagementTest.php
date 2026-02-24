<?php

namespace Tests\Feature;

use App\Models\BillingCheckoutSession;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BillingPlanManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_change_plan_with_manual_provider(): void
    {
        config()->set('wids.billing.payment.provider', 'manual');

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
            'plan_tier' => 'lite',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $this->actingAs($owner);

        $response = $this->post(route('billing.plan.change'), [
            'target_plan_tier' => 'pro',
        ]);

        $response->assertRedirect(route('billing.index'));
        $response->assertSessionHas('status', 'Plan changed successfully.');

        $tenant->refresh();
        $this->assertSame('pro', $tenant->plan_tier);
        $this->assertSame('manual', $tenant->billing_provider);
        $this->assertSame('manual_active', $tenant->billing_subscription_status);

        $this->assertDatabaseHas('billing_checkout_sessions', [
            'tenant_id' => $tenant->id,
            'provider' => 'manual',
            'target_plan_tier' => 'pro',
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'action' => 'tenant.plan_tier_changed',
        ]);
    }

    public function test_owner_cannot_change_to_same_plan_tier(): void
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

        $this->actingAs($owner);

        $response = $this->post(route('billing.plan.change'), [
            'target_plan_tier' => 'lite',
        ]);

        $response->assertSessionHasErrors('target_plan_tier');
        $this->assertDatabaseCount('billing_checkout_sessions', 0);
    }

    public function test_member_cannot_change_plan_tier(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
            'plan_tier' => 'lite',
        ]);

        $member = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'member',
        ]);

        $this->actingAs($member);

        $this->post(route('billing.plan.change'), [
            'target_plan_tier' => 'pro',
        ])->assertForbidden();
    }

    public function test_billing_webhook_is_ingested_and_duplicate_is_ignored(): void
    {
        config()->set('wids.billing.providers.stripe.webhook_secret', 'test-secret');

        $payload = [
            'id' => 'evt_test_123',
            'type' => 'invoice.paid',
            'data' => ['foo' => 'bar'],
        ];

        $rawPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $signature = $this->stripeSignatureHeader((string) $rawPayload, 'test-secret');

        $first = $this->withHeaders([
            'Stripe-Signature' => $signature,
        ])->postJson('/api/v1/billing/webhooks/stripe', $payload);

        $first->assertStatus(202);
        $first->assertJsonPath('created', true);

        $second = $this->withHeaders([
            'Stripe-Signature' => $signature,
        ])->postJson('/api/v1/billing/webhooks/stripe', $payload);

        $second->assertStatus(200);
        $second->assertJsonPath('created', false);

        $this->assertDatabaseHas('billing_webhook_events', [
            'provider' => 'stripe',
            'event_id' => 'evt_test_123',
            'event_type' => 'invoice.paid',
            'status' => 'ignored',
        ]);
    }

    public function test_billing_webhook_rejects_non_manual_provider_without_secret(): void
    {
        config()->set('wids.billing.providers.stripe.webhook_secret', null);

        $payload = [
            'id' => 'evt_missing_secret',
            'type' => 'invoice.paid',
            'data' => ['foo' => 'bar'],
        ];

        $response = $this->postJson('/api/v1/billing/webhooks/stripe', $payload);

        $response->assertStatus(401);
        $response->assertJsonPath('message', 'Invalid webhook signature.');

        $this->assertDatabaseMissing('billing_webhook_events', [
            'provider' => 'stripe',
            'event_id' => 'evt_missing_secret',
        ]);
    }

    public function test_billing_webhook_route_rejects_manual_provider(): void
    {
        config()->set('wids.billing.payment.webhook_route_enabled', true);

        $payload = [
            'id' => 'evt_manual_provider',
            'type' => 'billing.plan_change.completed',
            'data' => [
                'local_session_id' => 123,
            ],
        ];

        $this->postJson('/api/v1/billing/webhooks/manual', $payload)->assertNotFound();
    }

    public function test_completed_checkout_webhook_applies_plan_change(): void
    {
        config()->set('wids.billing.payment.provider', 'stripe');
        config()->set('wids.billing.providers.stripe.secret_key', 'sk_test_123');
        config()->set('wids.billing.providers.stripe.webhook_secret', 'test-secret');
        config()->set('wids.billing.providers.stripe.price_ids.pro', 'price_test_pro');

        Http::fake([
            'https://api.stripe.com/v1/checkout/sessions' => Http::response([
                'id' => 'cs_test_123',
                'url' => 'https://checkout.stripe.test/session/cs_test_123',
            ], 200),
        ]);

        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
            'plan_tier' => 'lite',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $this->actingAs($owner);

        $this->post(route('billing.plan.change'), [
            'target_plan_tier' => 'pro',
        ])->assertRedirect(route('billing.index'));

        $session = BillingCheckoutSession::query()
            ->where('tenant_id', $tenant->id)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('pending', $session->status);

        $payload = [
            'id' => 'evt_checkout_completed',
            'type' => 'checkout.session.completed',
            'data' => [
                'local_session_id' => $session->id,
            ],
        ];

        $rawPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $signature = $this->stripeSignatureHeader((string) $rawPayload, 'test-secret');

        $response = $this->withHeaders([
            'Stripe-Signature' => $signature,
        ])->postJson('/api/v1/billing/webhooks/stripe', $payload);

        $response->assertStatus(202);

        $session->refresh();
        $tenant->refresh();

        $this->assertSame('completed', $session->status);
        $this->assertNotNull($session->completed_at);
        $this->assertSame('pro', $tenant->plan_tier);
        $this->assertSame('stripe', $tenant->billing_provider);

        $this->assertDatabaseHas('billing_webhook_events', [
            'provider' => 'stripe',
            'event_id' => 'evt_checkout_completed',
            'status' => 'processed',
        ]);
    }

    private function stripeSignatureHeader(string $rawPayload, string $secret): string
    {
        $timestamp = time();
        $signedPayload = $timestamp.'.'.$rawPayload;
        $signature = hash_hmac('sha256', $signedPayload, $secret);

        return 't='.$timestamp.',v1='.$signature;
    }
}
