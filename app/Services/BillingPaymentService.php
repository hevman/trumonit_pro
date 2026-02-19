<?php

namespace App\Services;

use App\Models\BillingCheckoutSession;
use App\Models\Tenant;
use App\Services\Payments\StripeCheckoutService;
use Illuminate\Support\Str;
use RuntimeException;

class BillingPaymentService
{
    /**
     * @return array{
     *   provider: string,
     *   status: string,
     *   external_session_id: ?string,
     *   checkout_url: ?string,
     *   expires_at: ?\DateTimeInterface,
     *   completed_at: ?\DateTimeInterface,
     *   metadata: array<string, mixed>
     * }
     */
    public function startPlanChangeCheckout(BillingCheckoutSession $session, Tenant $tenant): array
    {
        $provider = $this->providerKey();

        if ($provider === 'manual') {
            return [
                'provider' => $provider,
                'status' => 'completed',
                'external_session_id' => null,
                'checkout_url' => null,
                'expires_at' => null,
                'completed_at' => now(),
                'metadata' => [
                    'mode' => 'manual',
                    'note' => 'Plan change completed without external checkout.',
                ],
            ];
        }

        if ($provider === 'stripe') {
            return $this->startStripeCheckout($session, $tenant);
        }

        $checkoutBaseUrl = trim((string) config('wids.billing.providers.'.$provider.'.checkout_base_url', ''));
        $externalSessionId = 'local_'.$provider.'_'.Str::lower(Str::random(24));

        if ($checkoutBaseUrl !== '') {
            $checkoutUrl = rtrim($checkoutBaseUrl, '/').'/'.$externalSessionId
                .'?tenant='.$tenant->id
                .'&target_plan_tier='.$session->target_plan_tier
                .'&session_id='.$session->id;

            return [
                'provider' => $provider,
                'status' => 'pending',
                'external_session_id' => $externalSessionId,
                'checkout_url' => $checkoutUrl,
                'expires_at' => now()->addMinutes(30),
                'completed_at' => null,
                'metadata' => [
                    'mode' => 'external',
                    'requires_webhook_completion' => true,
                ],
            ];
        }

        return [
            'provider' => $provider,
            'status' => 'pending',
            'external_session_id' => null,
            'checkout_url' => null,
            'expires_at' => null,
            'completed_at' => null,
            'metadata' => [
                'mode' => 'external',
                'requires_provider_setup' => true,
                'provider' => $provider,
            ],
        ];
    }

    public function providerKey(): string
    {
        $provider = strtolower(trim((string) config('wids.billing.payment.provider', 'manual')));

        return $provider !== '' ? $provider : 'manual';
    }

    /**
     * @return array{
     *   provider: string,
     *   status: string,
     *   external_session_id: ?string,
     *   checkout_url: ?string,
     *   expires_at: ?\DateTimeInterface,
     *   completed_at: ?\DateTimeInterface,
     *   metadata: array<string, mixed>
     * }
     */
    private function startStripeCheckout(BillingCheckoutSession $session, Tenant $tenant): array
    {
        $secretKey = trim((string) config('wids.billing.providers.stripe.secret_key', ''));
        if ($secretKey === '') {
            throw new RuntimeException('Stripe billing secret key is missing (WIDS_BILLING_STRIPE_SECRET_KEY).');
        }

        $webhookSecret = trim((string) config('wids.billing.providers.stripe.webhook_secret', ''));
        if ($webhookSecret === '') {
            throw new RuntimeException('Stripe billing webhook secret is missing (WIDS_BILLING_STRIPE_WEBHOOK_SECRET).');
        }

        $targetPlanTier = strtolower(trim((string) $session->target_plan_tier));
        $priceId = trim((string) config('wids.billing.providers.stripe.price_ids.'.$targetPlanTier, ''));
        if ($priceId === '') {
            throw new RuntimeException('Stripe price id is missing for plan tier: '.$targetPlanTier.'.');
        }

        $successUrl = rtrim((string) config('app.url', ''), '/').'/billing?checkout=stripe_success&session_id={CHECKOUT_SESSION_ID}';
        $cancelUrl = rtrim((string) config('app.url', ''), '/').'/billing?checkout=stripe_cancel&session_id={CHECKOUT_SESSION_ID}';

        $createdBy = $session->createdByUser()->first();
        $customerRef = trim((string) ($tenant->billing_customer_ref ?? ''));
        $customerEmail = $createdBy ? trim((string) ($createdBy->email ?? '')) : '';

        $metadata = [
            'mode' => 'stripe_checkout',
            'requires_webhook_completion' => true,
            'local_session_id' => (string) $session->id,
            'tenant_id' => (string) $tenant->id,
            'target_plan_tier' => (string) $session->target_plan_tier,
        ];

        $payload = [
            'idempotency_key' => 'wids_plan_change_'.$session->id,
            'mode' => 'subscription',
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'line_items' => [
                [
                    'price' => $priceId,
                    'quantity' => 1,
                ],
            ],
            'metadata' => $metadata,
            'subscription_data' => [
                'metadata' => $metadata,
            ],
        ];

        if ($customerRef !== '') {
            $payload['customer'] = $customerRef;
        } elseif ($customerEmail !== '') {
            $payload['customer_email'] = $customerEmail;
        }

        $stripe = new StripeCheckoutService(secretKey: $secretKey);
        $stripeSession = $stripe->createSession($payload);

        return [
            'provider' => 'stripe',
            'status' => 'pending',
            'external_session_id' => $stripeSession['id'],
            'checkout_url' => $stripeSession['url'],
            'expires_at' => now()->addMinutes(30),
            'completed_at' => null,
            'metadata' => [
                'mode' => 'stripe',
                'requires_webhook_completion' => true,
                'stripe_checkout_session_id' => $stripeSession['id'],
                'stripe_price_id' => $priceId,
            ],
        ];
    }
}
