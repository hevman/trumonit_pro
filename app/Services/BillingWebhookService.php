<?php

namespace App\Services;

use App\Models\BillingWebhookEvent;

class BillingWebhookService
{
    public function verifySignature(string $provider, string $rawPayload, ?string $signature): bool
    {
        $provider = $this->normalizeProvider($provider);
        $secret = trim((string) config('wids.billing.providers.'.$provider.'.webhook_secret', ''));

        if ($secret === '') {
            return $provider === 'manual';
        }

        $signature = trim((string) $signature);

        if ($signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $rawPayload, $secret);

        return hash_equals($expected, $signature);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{event: BillingWebhookEvent, created: bool}
     */
    public function ingestEvent(string $provider, array $payload, string $rawPayload): array
    {
        $provider = $this->normalizeProvider($provider);
        $eventId = $this->resolveEventId($payload, $provider, $rawPayload);
        $eventType = $this->resolveEventType($payload);

        $existing = BillingWebhookEvent::query()
            ->where('provider', $provider)
            ->where('event_id', $eventId)
            ->first();

        if ($existing) {
            return [
                'event' => $existing,
                'created' => false,
            ];
        }

        $event = BillingWebhookEvent::query()->create([
            'provider' => $provider,
            'event_id' => $eventId,
            'event_type' => $eventType,
            'status' => 'received',
            'payload' => $payload,
            'received_at' => now(),
        ]);

        return [
            'event' => $event,
            'created' => true,
        ];
    }

    private function normalizeProvider(string $provider): string
    {
        $provider = strtolower(trim($provider));

        return $provider !== '' ? $provider : 'unknown';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveEventId(array $payload, string $provider, string $rawPayload): string
    {
        $candidate = $payload['id'] ?? null;

        if (is_scalar($candidate)) {
            $candidate = trim((string) $candidate);

            if ($candidate !== '') {
                return mb_substr($candidate, 0, 191);
            }
        }

        return substr(hash('sha256', $provider.'|'.$rawPayload), 0, 64);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveEventType(array $payload): string
    {
        $type = $payload['type'] ?? null;

        if (is_scalar($type)) {
            $type = trim((string) $type);

            if ($type !== '') {
                return mb_substr($type, 0, 120);
            }
        }

        return 'unknown';
    }
}
