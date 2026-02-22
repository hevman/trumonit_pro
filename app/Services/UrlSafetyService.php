<?php

namespace App\Services;

use App\Exceptions\UnsafeUrlException;

class UrlSafetyService
{
    /**
     * @throws UnsafeUrlException
     */
    public static function assertSafeForScanning(string $url, ?array $allowedPorts = null): void
    {
        self::resolveSafeTarget($url, $allowedPorts);
    }

    /**
     * @throws UnsafeUrlException
     */
    public static function assertSafeForNotificationEndpoint(string $url): void
    {
        $strictDnsValidation = (bool) config('wids.notifications.url_safety_dns_strict', false);

        if ($strictDnsValidation) {
            self::resolveSafeTarget($url);

            return;
        }

        $parsed = parse_url($url);

        if (! is_array($parsed)) {
            throw new UnsafeUrlException('Invalid URL.');
        }

        $scheme = strtolower((string) ($parsed['scheme'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new UnsafeUrlException('Only http/https URLs are allowed.');
        }

        if (isset($parsed['user']) || isset($parsed['pass'])) {
            throw new UnsafeUrlException('URLs with credentials are not allowed.');
        }

        $host = strtolower((string) ($parsed['host'] ?? ''));

        if ($host === '') {
            throw new UnsafeUrlException('URL host is required.');
        }

        if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
            throw new UnsafeUrlException('localhost is not allowed.');
        }

        if (str_ends_with($host, '.local')) {
            throw new UnsafeUrlException('.local domains are not allowed.');
        }

        if (in_array($host, ['metadata.google.internal', 'metadata'], true)) {
            throw new UnsafeUrlException('Metadata hostnames are not allowed.');
        }

        $port = isset($parsed['port']) ? (int) $parsed['port'] : ($scheme === 'https' ? 443 : 80);

        if ($port < 1 || $port > 65535) {
            throw new UnsafeUrlException('Invalid port.');
        }

        $allowedPorts = config('wids.http.allowed_ports', [80, 443]);

        if (is_array($allowedPorts) && $allowedPorts !== [] && ! in_array($port, $allowedPorts, true)) {
            throw new UnsafeUrlException('Port not allowed.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (! self::isPublicIp($host)) {
                throw new UnsafeUrlException('IP address is not public.');
            }

            if ($host === '169.254.169.254') {
                throw new UnsafeUrlException('Metadata IP is not allowed.');
            }
        }
    }

    /**
     * @return array{
     *   scheme: string,
     *   host: string,
     *   port: int,
     *   is_ip_literal: bool,
     *   connect_ip: string,
     *   resolved_ips: list<string>
     * }
     *
     * @throws UnsafeUrlException
     */
    public static function resolveSafeTarget(string $url, ?array $allowedPorts = null): array
    {
        $parsed = parse_url($url);

        if (! is_array($parsed)) {
            throw new UnsafeUrlException('Invalid URL.');
        }

        $scheme = strtolower((string) ($parsed['scheme'] ?? ''));

        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new UnsafeUrlException('Only http/https URLs are allowed.');
        }

        if (isset($parsed['user']) || isset($parsed['pass'])) {
            throw new UnsafeUrlException('URLs with credentials are not allowed.');
        }

        $host = strtolower((string) ($parsed['host'] ?? ''));

        if ($host === '') {
            throw new UnsafeUrlException('URL host is required.');
        }

        if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
            throw new UnsafeUrlException('localhost is not allowed.');
        }

        if (str_ends_with($host, '.local')) {
            throw new UnsafeUrlException('.local domains are not allowed.');
        }

        if (in_array($host, ['metadata.google.internal', 'metadata'], true)) {
            throw new UnsafeUrlException('Metadata hostnames are not allowed.');
        }

        $port = isset($parsed['port']) ? (int) $parsed['port'] : ($scheme === 'https' ? 443 : 80);

        if ($port < 1 || $port > 65535) {
            throw new UnsafeUrlException('Invalid port.');
        }

        $allowedPorts = is_array($allowedPorts) ? $allowedPorts : config('wids.http.allowed_ports', [80, 443]);

        if (is_array($allowedPorts) && $allowedPorts !== [] && ! in_array($port, $allowedPorts, true)) {
            throw new UnsafeUrlException('Port not allowed.');
        }

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (! self::isPublicIp($host)) {
                throw new UnsafeUrlException('IP address is not public.');
            }

            if ($host === '169.254.169.254') {
                throw new UnsafeUrlException('Metadata IP is not allowed.');
            }

            return [
                'scheme' => $scheme,
                'host' => $host,
                'port' => $port,
                'is_ip_literal' => true,
                'connect_ip' => $host,
                'resolved_ips' => [$host],
            ];
        }

        $ips = self::resolveHostIps($host);

        if ($ips === []) {
            throw new UnsafeUrlException('Host could not be resolved.');
        }

        foreach ($ips as $ip) {
            if (! self::isPublicIp($ip)) {
                throw new UnsafeUrlException('Host resolves to a non-public IP.');
            }
        }

        sort($ips);
        $connectIp = self::selectConnectIp($ips);

        return [
            'scheme' => $scheme,
            'host' => $host,
            'port' => $port,
            'is_ip_literal' => false,
            'connect_ip' => $connectIp,
            'resolved_ips' => $ips,
        ];
    }

    /**
     * @param  list<string>  $knownIps
     *
     * @throws UnsafeUrlException
     */
    public static function assertNoDnsDrift(string $host, array $knownIps): void
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            if (! self::isPublicIp($host)) {
                throw new UnsafeUrlException('Host resolves to a non-public IP.');
            }

            return;
        }

        $knownIps = array_values(array_unique(array_filter($knownIps, fn ($ip) => is_string($ip) && $ip !== '')));

        if ($knownIps === []) {
            return;
        }

        $currentIps = self::resolveHostIps($host);

        if ($currentIps === []) {
            throw new UnsafeUrlException('Host could not be resolved.');
        }

        foreach ($currentIps as $ip) {
            if (! self::isPublicIp($ip)) {
                throw new UnsafeUrlException('Host resolves to a non-public IP.');
            }
        }

        $intersection = array_values(array_intersect($knownIps, $currentIps));

        if ($intersection === []) {
            throw new UnsafeUrlException('DNS drift detected for host.');
        }
    }

    /**
     * @return list<string>
     */
    private static function resolveHostIps(string $host): array
    {
        $ips = [];

        try {
            $a = dns_get_record($host, DNS_A);
            $aaaa = dns_get_record($host, DNS_AAAA);
        } catch (\Throwable) {
            return [];
        }

        if (is_array($a)) {
            foreach ($a as $record) {
                if (is_array($record) && isset($record['ip']) && is_string($record['ip'])) {
                    $ips[] = $record['ip'];
                }
            }
        }

        if (is_array($aaaa)) {
            foreach ($aaaa as $record) {
                if (is_array($record) && isset($record['ipv6']) && is_string($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        return array_values(array_unique($ips));
    }

    private static function isPublicIp(string $ip): bool
    {
        $validated = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);

        return $validated !== false;
    }

    /**
     * @param  list<string>  $ips
     */
    private static function selectConnectIp(array $ips): string
    {
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                return $ip;
            }
        }

        return $ips[0];
    }
}
