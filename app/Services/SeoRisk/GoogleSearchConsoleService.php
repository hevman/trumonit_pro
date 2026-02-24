<?php

namespace App\Services\SeoRisk;

use App\Models\SeoIntegration;
use App\Models\SeoProperty;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleSearchConsoleService
{
    public function __construct(
        private readonly GoogleTokenService $tokenService,
    ) {}

    /**
     * @return array{
     *   clicks: int,
     *   impressions: int,
     *   ctr: float,
     *   position: float,
     *   raw: array<string, mixed>
     * }
     */
    public function fetchDailyMetrics(
        SeoIntegration $integration,
        SeoProperty $property,
        CarbonInterface $metricDate,
    ): array {
        $siteUrl = trim((string) $property->gsc_site_url);
        if ($siteUrl === '') {
            throw new RuntimeException('GSC site URL is missing.');
        }

        $accessToken = $this->tokenService->accessTokenForIntegration($integration);
        $encodedSite = rawurlencode($siteUrl);
        $endpoint = 'https://searchconsole.googleapis.com/webmasters/v3/sites/'.$encodedSite.'/searchAnalytics/query';

        $response = Http::timeout(25)
            ->withToken($accessToken)
            ->post($endpoint, [
                'startDate' => $metricDate->toDateString(),
                'endDate' => $metricDate->toDateString(),
                'rowLimit' => 1,
                'startRow' => 0,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('GSC request failed (status='.$response->status().').');
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('GSC response payload is invalid.');
        }

        $row = is_array($payload['rows'][0] ?? null) ? $payload['rows'][0] : [];

        return [
            'clicks' => max(0, (int) round((float) ($row['clicks'] ?? 0))),
            'impressions' => max(0, (int) round((float) ($row['impressions'] ?? 0))),
            'ctr' => max(0.0, (float) ($row['ctr'] ?? 0.0)),
            'position' => max(0.0, (float) ($row['position'] ?? 0.0)),
            'raw' => $payload,
        ];
    }

    /**
     * @return array{
     *   indexed_urls: int,
     *   submitted_urls: int,
     *   coverage_ratio: float,
     *   sitemaps_count: int,
     *   raw: array<string, mixed>
     * }
     */
    public function fetchIndexCoverageMetrics(
        SeoIntegration $integration,
        SeoProperty $property,
    ): array {
        $siteUrl = trim((string) $property->gsc_site_url);
        if ($siteUrl === '') {
            throw new RuntimeException('GSC site URL is missing for index metrics.');
        }

        $accessToken = $this->tokenService->accessTokenForIntegration($integration);
        $encodedSite = rawurlencode($siteUrl);
        $endpoint = 'https://searchconsole.googleapis.com/webmasters/v3/sites/'.$encodedSite.'/sitemaps';

        $response = Http::timeout(25)
            ->withToken($accessToken)
            ->get($endpoint);

        if (! $response->successful()) {
            throw new RuntimeException('GSC sitemap request failed (status='.$response->status().').');
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('GSC sitemap response payload is invalid.');
        }

        $sitemapsRaw = $payload['sitemap'] ?? $payload['sitemaps'] ?? [];
        $sitemaps = is_array($sitemapsRaw) ? $sitemapsRaw : [];

        $indexed = 0;
        $submitted = 0;

        foreach ($sitemaps as $sitemap) {
            if (! is_array($sitemap)) {
                continue;
            }

            $contents = is_array($sitemap['contents'] ?? null) ? $sitemap['contents'] : [];
            if ($contents === []) {
                // Fallback for providers returning submitted/indexed directly on root level.
                $submitted += max(0, (int) round((float) ($sitemap['submitted'] ?? 0)));
                $indexed += max(0, (int) round((float) ($sitemap['indexed'] ?? 0)));
                continue;
            }

            foreach ($contents as $content) {
                if (! is_array($content)) {
                    continue;
                }

                $contentType = strtolower(trim((string) ($content['type'] ?? 'web')));
                if ($contentType !== '' && $contentType !== 'web') {
                    continue;
                }

                $submitted += max(0, (int) round((float) ($content['submitted'] ?? 0)));
                $indexed += max(0, (int) round((float) ($content['indexed'] ?? 0)));
            }
        }

        $coverageRatio = $submitted > 0 ? round($indexed / $submitted, 4) : 0.0;

        return [
            'indexed_urls' => max(0, $indexed),
            'submitted_urls' => max(0, $submitted),
            'coverage_ratio' => max(0.0, $coverageRatio),
            'sitemaps_count' => count($sitemaps),
            'raw' => $payload,
        ];
    }
}
