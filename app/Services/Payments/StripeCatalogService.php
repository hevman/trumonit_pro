<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class StripeCatalogService
{
    private const PRICES_ENDPOINT = 'https://api.stripe.com/v1/prices';

    public function ensureMonthlyPrice(string $lookupKey, string $productName, int $unitAmountCents, string $currency = 'usd'): string
    {
        $lookupKey = trim($lookupKey);
        $productName = trim($productName);
        $currency = strtolower(trim($currency));
        $unitAmountCents = max(0, $unitAmountCents);

        if ($lookupKey === '' || $productName === '' || $currency === '' || $unitAmountCents <= 0) {
            throw new RuntimeException('Stripe price is not configured.');
        }

        $secret = trim((string) config('wids.billing.providers.stripe.secret_key', ''));
        if ($secret === '') {
            throw new RuntimeException('Stripe secret key is missing.');
        }

        $existing = $this->findActivePriceByLookupKey($secret, $lookupKey);
        if ($existing !== null) {
            return $existing;
        }

        $payload = [
            'currency' => $currency,
            'unit_amount' => $unitAmountCents,
            'lookup_key' => $lookupKey,
            'recurring' => [
                'interval' => 'month',
                'interval_count' => 1,
            ],
            'product_data' => [
                'name' => $productName,
            ],
        ];

        $response = Http::asForm()
            ->withBasicAuth($secret, '')
            ->post(self::PRICES_ENDPOINT, $this->flattenPayload($payload));

        if (! $response->successful()) {
            throw new RuntimeException(sprintf(
                'Stripe API request failed (HTTP %s): %s',
                $response->status(),
                $response->body(),
            ));
        }

        /** @var array<string, mixed> $data */
        $data = $response->json();
        $id = trim((string) ($data['id'] ?? ''));

        if ($id === '') {
            throw new RuntimeException('Stripe API response does not contain price id.');
        }

        return $id;
    }

    private function findActivePriceByLookupKey(string $secret, string $lookupKey): ?string
    {
        $url = self::PRICES_ENDPOINT
            .'?active=true'
            .'&limit=1'
            .'&lookup_keys[]='.rawurlencode($lookupKey);

        $response = Http::withBasicAuth($secret, '')->get($url);

        if (! $response->successful()) {
            return null;
        }

        /** @var array<string, mixed> $data */
        $data = $response->json();
        $items = $data['data'] ?? null;

        if (! is_array($items) || count($items) === 0) {
            return null;
        }

        $first = is_array($items[0] ?? null) ? $items[0] : null;
        if (! is_array($first)) {
            return null;
        }

        $id = trim((string) ($first['id'] ?? ''));

        return $id !== '' ? $id : null;
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

