<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class OidcDiscoveryService
{
    /**
     * @return array{
     *   issuer: string,
     *   authorization_endpoint: string,
     *   token_endpoint: string,
     *   userinfo_endpoint: string,
     *   jwks_uri: string
     * }
     */
    public function discover(string $issuerUrl): array
    {
        $issuerUrl = $this->normalizeIssuerUrl($issuerUrl);
        $cacheSeconds = max(0, min(86_400, (int) config('wids.sso.oidc.discovery_cache_seconds', 600)));

        if ($cacheSeconds === 0) {
            return $this->fetchDiscovery($issuerUrl);
        }

        $cacheKey = 'sso:oidc:discovery:'.sha1($issuerUrl);

        return Cache::remember($cacheKey, now()->addSeconds($cacheSeconds), function () use ($issuerUrl): array {
            return $this->fetchDiscovery($issuerUrl);
        });
    }

    public function normalizeIssuerUrl(string $issuerUrl): string
    {
        $issuerUrl = trim($issuerUrl);

        if ($issuerUrl === '') {
            throw new \RuntimeException('OIDC issuer URL is required.');
        }

        $this->assertSafeHttpsUrl($issuerUrl, null);

        return rtrim($issuerUrl, '/');
    }

    public function normalizeEndpointUrl(string $endpointUrl, ?string $issuerUrl = null): string
    {
        $endpointUrl = trim($endpointUrl);

        if ($endpointUrl === '') {
            throw new \RuntimeException('OIDC endpoint URL is required.');
        }

        $issuerHost = null;

        if (is_string($issuerUrl) && trim($issuerUrl) !== '') {
            $issuerHost = $this->extractHost($this->normalizeIssuerUrl($issuerUrl));
        }

        $this->assertSafeHttpsUrl($endpointUrl, $issuerHost);

        return $endpointUrl;
    }

    /**
     * @return array{
     *   issuer: string,
     *   authorization_endpoint: string,
     *   token_endpoint: string,
     *   userinfo_endpoint: string,
     *   jwks_uri: string
     * }
     */
    private function fetchDiscovery(string $issuerUrl): array
    {
        $issuerHost = $this->extractHost($issuerUrl);
        $discoveryUrl = $issuerUrl.'/.well-known/openid-configuration';
        $this->assertSafeHttpsUrl($discoveryUrl, $issuerHost);

        $timeoutSeconds = max(2, min(60, (int) config('wids.sso.oidc.http_timeout_seconds', 8)));
        $response = Http::timeout($timeoutSeconds)
            ->acceptJson()
            ->get($discoveryUrl);

        if (! $response->successful()) {
            throw new \RuntimeException('OIDC discovery request failed (HTTP '.$response->status().').');
        }

        $data = $response->json();

        if (! is_array($data)) {
            throw new \RuntimeException('OIDC discovery response is invalid.');
        }

        $issuer = $this->requireString($data, 'issuer');
        $authorizationEndpoint = $this->requireString($data, 'authorization_endpoint');
        $tokenEndpoint = $this->requireString($data, 'token_endpoint');
        $userinfoEndpoint = $this->requireString($data, 'userinfo_endpoint');
        $jwksUri = $this->requireString($data, 'jwks_uri');

        $issuer = $this->normalizeIssuerUrl($issuer);
        $this->assertSafeHttpsUrl($authorizationEndpoint, $issuerHost);
        $this->assertSafeHttpsUrl($tokenEndpoint, $issuerHost);
        $this->assertSafeHttpsUrl($userinfoEndpoint, $issuerHost);
        $this->assertSafeHttpsUrl($jwksUri, $issuerHost);

        return [
            'issuer' => $issuer,
            'authorization_endpoint' => $authorizationEndpoint,
            'token_endpoint' => $tokenEndpoint,
            'userinfo_endpoint' => $userinfoEndpoint,
            'jwks_uri' => $jwksUri,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function requireString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        if (! is_string($value) || trim($value) === '') {
            throw new \RuntimeException('OIDC discovery is missing field: '.$key);
        }

        return trim($value);
    }

    private function extractHost(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) ? strtolower(trim($host)) : '';
    }

    private function assertSafeHttpsUrl(string $url, ?string $expectedHost): void
    {
        $parsed = parse_url($url);

        if (! is_array($parsed)) {
            throw new \RuntimeException('OIDC URL is invalid.');
        }

        $scheme = strtolower((string) ($parsed['scheme'] ?? ''));
        $host = strtolower((string) ($parsed['host'] ?? ''));

        if ($scheme !== 'https') {
            throw new \RuntimeException('OIDC URLs must use HTTPS.');
        }

        if ($host === '') {
            throw new \RuntimeException('OIDC URL host is required.');
        }

        if (isset($parsed['user']) || isset($parsed['pass'])) {
            throw new \RuntimeException('OIDC URLs with credentials are not allowed.');
        }

        if (
            (bool) config('wids.sso.oidc.strict_issuer_host_match', true)
            && is_string($expectedHost)
            && $expectedHost !== ''
            && $host !== strtolower(trim($expectedHost))
        ) {
            throw new \RuntimeException('OIDC endpoint host must match issuer host.');
        }

        try {
            UrlSafetyService::resolveSafeTarget($url);
        } catch (\Throwable $e) {
            throw new \RuntimeException('OIDC URL rejected by safety policy: '.$e->getMessage());
        }
    }
}
