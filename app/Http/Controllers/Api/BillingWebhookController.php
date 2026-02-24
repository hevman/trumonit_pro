<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BillingCheckoutSession;
use App\Models\BillingWebhookEvent;
use App\Services\BillingPlanService;
use App\Services\BillingWebhookService;
use Illuminate\Http\Request;

class BillingWebhookController extends Controller
{
    public function __invoke(string $provider, Request $request)
    {
        if (! (bool) config('wids.billing.payment.webhook_route_enabled', true)) {
            abort(404);
        }

        $provider = strtolower(trim($provider));
        if ($provider === '' || $provider === 'manual') {
            abort(404);
        }

        $rawPayload = (string) $request->getContent();
        $payload = json_decode($rawPayload, true);

        if (! is_array($payload)) {
            return response()->json([
                'message' => 'Invalid webhook payload.',
            ], 422);
        }

        $signature = null;
        if ($provider === 'stripe') {
            // Primary Stripe header + compatibility fallback used by older internal clients/tests.
            $signature = $request->header('Stripe-Signature');
            if (! is_string($signature) || trim($signature) === '') {
                $signature = $request->header('X-Billing-Signature');
            }
        } else {
            $signature = $request->header('X-Billing-Signature');
        }

        if (! app(BillingWebhookService::class)->verifySignature($provider, $rawPayload, is_string($signature) ? $signature : null)) {
            return response()->json([
                'message' => 'Invalid webhook signature.',
            ], 401);
        }

        $ingested = app(BillingWebhookService::class)->ingestEvent($provider, $payload, $rawPayload);
        $event = $ingested['event'];
        $created = (bool) ($ingested['created'] ?? false);

        if ($created) {
            $this->processEvent($provider, $payload, $event);
        }

        return response()->json([
            'message' => $created ? 'Webhook accepted.' : 'Duplicate webhook ignored.',
            'event_id' => $event->event_id,
            'created' => $created,
        ], $created ? 202 : 200);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function processEvent(string $provider, array $payload, BillingWebhookEvent $event): void
    {
        $provider = strtolower(trim($provider));
        $eventType = strtolower(trim((string) ($event->event_type ?? '')));

        try {
            if (! in_array($eventType, [
                'checkout.session.completed',
                'billing.plan_change.completed',
                'plan_change.completed',
            ], true)) {
                $event->forceFill([
                    'status' => 'ignored',
                    'processed_at' => now(),
                ])->save();

                return;
            }

            $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

            $stripeObject = is_array($data['object'] ?? null) ? $data['object'] : [];
            $stripeCheckoutSessionId = $this->extractString($stripeObject['id'] ?? null, 191);

            $externalSessionId = $this->extractString(
                $data['external_session_id']
                    ?? $data['checkout_session_id']
                    ?? $payload['external_session_id']
                    ?? $stripeCheckoutSessionId
                    ?? null,
                191,
            );

            $localSessionId = $this->extractInt(
                $data['local_session_id'] ?? $data['session_id'] ?? $payload['local_session_id'] ?? null
            );

            $query = BillingCheckoutSession::query()->where('provider', $provider);

            if (is_string($externalSessionId) && $externalSessionId !== '') {
                $query->where('external_session_id', $externalSessionId);
            } elseif ($localSessionId > 0) {
                $query->whereKey($localSessionId);
            } else {
                $event->forceFill([
                    'status' => 'ignored',
                    'processed_at' => now(),
                    'error_message' => 'No session reference in webhook payload.',
                ])->save();

                return;
            }

            $checkoutSession = $query->first();

            if (! $checkoutSession) {
                $event->forceFill([
                    'status' => 'ignored',
                    'processed_at' => now(),
                    'error_message' => 'Referenced checkout session not found.',
                ])->save();

                return;
            }

            if ($checkoutSession->status === 'completed') {
                $event->forceFill([
                    'status' => 'ignored',
                    'processed_at' => now(),
                    'error_message' => 'Checkout session is already completed.',
                ])->save();

                return;
            }

            if ($checkoutSession->status !== 'pending') {
                $event->forceFill([
                    'status' => 'ignored',
                    'processed_at' => now(),
                    'error_message' => 'Checkout session is not in pending state.',
                ])->save();

                return;
            }

            $metadata = is_array($checkoutSession->metadata) ? $checkoutSession->metadata : [];
            $metadata['completed_via_webhook'] = true;
            $metadata['billing_webhook_event_id'] = $event->id;

            if ($provider === 'stripe') {
                $savedExternalSessionId = trim((string) ($checkoutSession->external_session_id ?? ''));
                $isLocalOrTesting = app()->environment(['local', 'testing']);

                // For live traffic require real Stripe checkout session id in payload.
                if (! $isLocalOrTesting && $stripeCheckoutSessionId === null) {
                    $event->forceFill([
                        'status' => 'ignored',
                        'processed_at' => now(),
                        'error_message' => 'Stripe checkout session id is missing in webhook payload.',
                    ])->save();

                    return;
                }

                if (! $isLocalOrTesting && $stripeObject === []) {
                    $event->forceFill([
                        'status' => 'ignored',
                        'processed_at' => now(),
                        'error_message' => 'Stripe checkout session payload is incomplete.',
                    ])->save();

                    return;
                }

                if ($savedExternalSessionId !== '' && $stripeCheckoutSessionId !== null && ! hash_equals($savedExternalSessionId, $stripeCheckoutSessionId)) {
                    $event->forceFill([
                        'status' => 'ignored',
                        'processed_at' => now(),
                        'error_message' => 'Stripe checkout session id mismatch.',
                    ])->save();

                    return;
                }

                if ($stripeObject !== []) {
                    $paymentStatus = $this->extractString($stripeObject['payment_status'] ?? null, 32);
                    $sessionStatus = $this->extractString($stripeObject['status'] ?? null, 32);

                    $metadata['stripe_checkout_session_id'] = $stripeCheckoutSessionId;
                    $metadata['stripe_payment_status'] = $paymentStatus;
                    $metadata['stripe_session_status'] = $sessionStatus;
                    $metadata['stripe_customer_ref'] = $this->extractString($stripeObject['customer'] ?? null, 191);
                    $metadata['stripe_subscription_ref'] = $this->extractString($stripeObject['subscription'] ?? null, 191);

                    // Do not apply plan changes for sessions that did not complete payment.
                    if ($paymentStatus === null || strtolower($paymentStatus) !== 'paid') {
                        $event->forceFill([
                            'status' => 'ignored',
                            'processed_at' => now(),
                            'error_message' => 'Stripe checkout session payment_status is not paid.',
                        ])->save();

                        return;
                    }
                }
            }

            $checkoutSession->forceFill([
                'status' => 'completed',
                'completed_at' => now(),
                'metadata' => $metadata,
            ])->save();

            app(BillingPlanService::class)->applyPlanTierFromSession($checkoutSession);

            $event->forceFill([
                'status' => 'processed',
                'processed_at' => now(),
                'error_message' => null,
            ])->save();
        } catch (\Throwable $e) {
            $event->forceFill([
                'status' => 'failed',
                'processed_at' => now(),
                'error_message' => mb_substr($e->getMessage(), 0, 5000),
            ])->save();
        }
    }

    private function extractInt(mixed $value): int
    {
        if (! is_numeric($value)) {
            return 0;
        }

        return max(0, (int) $value);
    }

    private function extractString(mixed $value, int $maxLen): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if ($maxLen > 0 && mb_strlen($value) > $maxLen) {
            return mb_substr($value, 0, $maxLen);
        }

        return $value;
    }
}
