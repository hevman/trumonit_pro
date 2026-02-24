<?php

namespace App\Services\SeoRisk;

use App\Models\SeoIntegration;
use App\Models\SeoProperty;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleAnalyticsService
{
    public function __construct(
        private readonly GoogleTokenService $tokenService,
    ) {}

    /**
     * @return array{
     *   sessions: int,
     *   conversions: int,
     *   raw: array<string, mixed>
     * }
     */
    public function fetchOrganicDailyMetrics(
        SeoIntegration $integration,
        SeoProperty $property,
        CarbonInterface $metricDate,
    ): array {
        $propertyId = trim((string) $property->ga4_property_id);
        if ($propertyId === '') {
            throw new RuntimeException('GA4 property ID is missing.');
        }

        $accessToken = $this->tokenService->accessTokenForIntegration($integration);
        $endpoint = 'https://analyticsdata.googleapis.com/v1beta/properties/'.$propertyId.':runReport';

        $response = Http::timeout(25)
            ->withToken($accessToken)
            ->post($endpoint, [
                'dateRanges' => [
                    [
                        'startDate' => $metricDate->toDateString(),
                        'endDate' => $metricDate->toDateString(),
                    ],
                ],
                'dimensions' => [
                    ['name' => 'sessionDefaultChannelGroup'],
                ],
                'metrics' => [
                    ['name' => 'sessions'],
                    ['name' => 'conversions'],
                ],
                'dimensionFilter' => [
                    'filter' => [
                        'fieldName' => 'sessionDefaultChannelGroup',
                        'stringFilter' => [
                            'matchType' => 'EXACT',
                            'value' => 'Organic Search',
                        ],
                    ],
                ],
                'limit' => '1',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('GA4 request failed (status='.$response->status().').');
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('GA4 response payload is invalid.');
        }

        $metricValues = is_array($payload['rows'][0]['metricValues'] ?? null)
            ? $payload['rows'][0]['metricValues']
            : [];

        $sessions = is_array($metricValues[0] ?? null)
            ? (int) round((float) ($metricValues[0]['value'] ?? 0))
            : 0;
        $conversions = is_array($metricValues[1] ?? null)
            ? (int) round((float) ($metricValues[1]['value'] ?? 0))
            : 0;

        return [
            'sessions' => max(0, $sessions),
            'conversions' => max(0, $conversions),
            'raw' => $payload,
        ];
    }
}
