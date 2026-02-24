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

    /**
     * @return list<array{property_id: string, display_name: string, account_name: string}>
     */
    public function listProperties(SeoIntegration $integration): array
    {
        $accessToken = $this->tokenService->accessTokenForIntegration($integration);
        $endpoint = 'https://analyticsadmin.googleapis.com/v1beta/accountSummaries';

        $propertiesById = [];
        $pageToken = null;
        $pages = 0;

        do {
            $query = ['pageSize' => 200];
            if (is_string($pageToken) && $pageToken !== '') {
                $query['pageToken'] = $pageToken;
            }

            $response = Http::timeout(25)
                ->withToken($accessToken)
                ->get($endpoint, $query);

            if (! $response->successful()) {
                throw new RuntimeException('GA4 properties request failed (status='.$response->status().').');
            }

            $payload = $response->json();
            if (! is_array($payload)) {
                throw new RuntimeException('GA4 properties response payload is invalid.');
            }

            $accountSummaries = is_array($payload['accountSummaries'] ?? null) ? $payload['accountSummaries'] : [];
            foreach ($accountSummaries as $summary) {
                if (! is_array($summary)) {
                    continue;
                }

                $accountName = trim((string) ($summary['displayName'] ?? ''));
                $propertySummaries = is_array($summary['propertySummaries'] ?? null) ? $summary['propertySummaries'] : [];
                foreach ($propertySummaries as $propertySummary) {
                    if (! is_array($propertySummary)) {
                        continue;
                    }

                    $propertyResource = trim((string) ($propertySummary['property'] ?? ''));
                    if (! preg_match('#^properties/(\d+)$#', $propertyResource, $matches)) {
                        continue;
                    }

                    $propertyId = trim((string) ($matches[1] ?? ''));
                    if ($propertyId === '') {
                        continue;
                    }

                    $displayName = trim((string) ($propertySummary['displayName'] ?? ''));
                    $propertiesById[$propertyId] = [
                        'property_id' => $propertyId,
                        'display_name' => $displayName,
                        'account_name' => $accountName,
                    ];
                }
            }

            $pageToken = trim((string) ($payload['nextPageToken'] ?? ''));
            $pages++;
        } while ($pageToken !== '' && $pages < 10);

        $properties = array_values($propertiesById);
        usort($properties, static function (array $a, array $b): int {
            $accountCompare = strcmp($a['account_name'], $b['account_name']);
            if ($accountCompare !== 0) {
                return $accountCompare;
            }

            $nameCompare = strcmp($a['display_name'], $b['display_name']);
            if ($nameCompare !== 0) {
                return $nameCompare;
            }

            return strcmp($a['property_id'], $b['property_id']);
        });

        return $properties;
    }
}
