<?php

namespace App\Services\SeoRisk;

use App\Models\SeoProperty;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class BacklinkSignalService
{
    /**
     * @return array{
     *   referring_domains_total: int,
     *   backlinks_total: int,
     *   lost_referring_domains: int,
     *   new_referring_domains: int,
     *   raw: array<string, mixed>
     * }
     */
    public function fetchDailyMetrics(SeoProperty $property, CarbonInterface $metricDate): array
    {
        $domain = trim((string) $property->domain);
        if ($domain === '') {
            throw new RuntimeException('SEO property domain is missing for backlink metrics.');
        }

        $apiUrl = trim((string) config('wids.seo_risk.backlinks.api_url', ''));
        if ($apiUrl === '') {
            throw new RuntimeException('Backlink API URL is missing.');
        }

        $timeout = max(3, (int) config('wids.seo_risk.backlinks.api_timeout_seconds', 15));
        $apiKey = trim((string) config('wids.seo_risk.backlinks.api_key', ''));
        $apiKeyHeader = trim((string) config('wids.seo_risk.backlinks.api_key_header', 'Authorization'));
        $apiKeyPrefix = (string) config('wids.seo_risk.backlinks.api_key_prefix', 'Bearer ');

        $endpoint = str_replace(
            ['{domain}', '{date}'],
            [rawurlencode($domain), rawurlencode($metricDate->toDateString())],
            $apiUrl
        );

        $request = Http::timeout($timeout)->acceptJson();

        if ($apiKey !== '' && $apiKeyHeader !== '') {
            $request = $request->withHeaders([
                $apiKeyHeader => $apiKeyPrefix.$apiKey,
            ]);
        }

        $response = $request->get($endpoint, [
            'domain' => $domain,
            'date' => $metricDate->toDateString(),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Backlink API request failed (status='.$response->status().').');
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw new RuntimeException('Backlink API returned invalid payload.');
        }

        return [
            'referring_domains_total' => max(0, $this->intValue($payload, [
                'referring_domains_total',
                'referringDomainsTotal',
                'data.referring_domains_total',
                'data.referringDomainsTotal',
                'stats.refdomains',
            ])),
            'backlinks_total' => max(0, $this->intValue($payload, [
                'backlinks_total',
                'backlinksTotal',
                'data.backlinks_total',
                'data.backlinksTotal',
                'stats.backlinks',
            ])),
            'lost_referring_domains' => max(0, $this->intValue($payload, [
                'lost_referring_domains',
                'lostReferringDomains',
                'data.lost_referring_domains',
                'data.lostReferringDomains',
                'changes.lost_refdomains',
            ])),
            'new_referring_domains' => max(0, $this->intValue($payload, [
                'new_referring_domains',
                'newReferringDomains',
                'data.new_referring_domains',
                'data.newReferringDomains',
                'changes.new_refdomains',
            ])),
            'raw' => $payload,
        ];
    }

    /**
     * @param  list<string>  $paths
     */
    private function intValue(array $payload, array $paths): int
    {
        foreach ($paths as $path) {
            $value = data_get($payload, $path);
            if (is_numeric($value)) {
                return (int) round((float) $value);
            }
        }

        return 0;
    }
}
