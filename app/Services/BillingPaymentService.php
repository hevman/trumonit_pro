<?php

namespace App\Services;

use App\Models\BillingCheckoutSession;
use App\Models\Tenant;
use Illuminate\Support\Str;

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
}
