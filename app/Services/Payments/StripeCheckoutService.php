<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class StripeCheckoutService
{
    private const CHECKOUT_SESSIONS_ENDPOINT = 'https://api.stripe.com/v1/checkout/sessions';

    public function __construct(
        private readonly string $secretKey = '',
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{id: string, url: string}
     */
    public function createSession(array $payload): array
    {
        $secret = trim($this->resolveSecretKey());
        if ($secret === '') {
            throw new RuntimeException('Stripe secret key is missing.');
        }

        $normalizedPayload = $payload;
        $idempotencyKey = trim((string) ($normalizedPayload['idempotency_key'] ?? ''));
        unset($normalizedPayload['idempotency_key']);

        $request = Http::asForm()->withBasicAuth($secret, '');
        if ($idempotencyKey !== '') {
            $request = $request->withHeaders([
                'Idempotency-Key' => $idempotencyKey,
            ]);
        }

        $response = $request->post(self::CHECKOUT_SESSIONS_ENDPOINT, $this->flattenPayload($normalizedPayload));

        if (! $response->successful()) {
            throw new RuntimeException(sprintf(
                'Stripe API request failed (HTTP %s): %s',
                $response->status(),
                $response->body()
            ));
        }

        /** @var array<string, mixed> $data */
        $data = $response->json();
        $id = trim((string) ($data['id'] ?? ''));
        $url = trim((string) ($data['url'] ?? ''));

        if ($id === '' || $url === '') {
            throw new RuntimeException('Stripe API response does not contain session id or url.');
        }

        return [
            'id' => $id,
            'url' => $url,
        ];
    }

    private function resolveSecretKey(): string
    {
        if (trim($this->secretKey) !== '') {
            return $this->secretKey;
        }

        return (string) config('wids.billing.providers.stripe.secret_key', '');
    }

    /**
     * @param array<string|int, mixed> $payload
     * @return array<string, string>
     */
    private function flattenPayload(array $payload, string $prefix = ''): array
    {
        $result = [];

        foreach ($payload as $key => $value) {
            $field = $prefix === '' ? (string) $key : $prefix.'['.$key.']';

            if (is_array($value)) {
                $result = array_merge($result, $this->flattenPayload($value, $field));
                continue;
            }

            if (is_bool($value)) {
                $result[$field] = $value ? 'true' : 'false';
                continue;
            }

            if ($value === null) {
                continue;
            }

            $result[$field] = (string) $value;
        }

        return $result;
    }
}

