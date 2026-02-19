<?php

namespace App\Services;

use App\Models\MonitoredUrl;
use Illuminate\Support\Facades\Http;

class MultiRegionOutageConfirmationService
{
    public function isEnabled(): bool
    {
        return (bool) config('wids.multi_region.enabled', false);
    }

    /**
     * @return array{
     *   enabled: bool,
     *   performed: bool,
     *   confirmed: bool,
     *   reason: string,
     *   plan_tier: string,
      *   required_failures: int,
     *   health_controls_enabled: bool,
     *   policy_relaxed: bool,
     *   selected_regions: list<string>,
     *   excluded_regions: list<array{region: string, reason: string, attempts: int, failures: int, failure_ratio: float, unhealthy: bool}>,
     *   region_health: array<string, array{attempts: int, failures: int, failure_ratio: float, unhealthy: bool}>,
     *   failed_regions: list<array{region: string, kind: string, status: ?int, error: ?string}>,
     *   healthy_regions: list<array{region: string, status: ?int}>,
     *   probe_results: list<array{region: string, is_down: bool, kind: string, status: ?int, error: ?string}>
     * }
     */
    public function confirmDownState(MonitoredUrl $monitoredUrl, string $primaryDownKind): array
    {
        $planTier = $this->resolvePlanTier($monitoredUrl);
        $regions = $this->selectedRegionsForPlanTier($planTier);
        $healthService = app(MultiRegionHealthService::class);
        $policy = $healthService->applyRegionPolicy($regions);
        $selectedRegions = $policy['selected_regions'];
        $excludedRegions = $policy['excluded_regions'];
        $regionHealth = $policy['region_health'];
        $healthControlsEnabled = (bool) $policy['controls_enabled'];
        $policyRelaxed = (bool) $policy['policy_relaxed'];
        $strictBlocked = (bool) $policy['strict_blocked'];

        if (! $this->isEnabled()) {
            return $this->result(
                enabled: false,
                performed: false,
                confirmed: true,
                reason: 'disabled',
                planTier: $planTier,
                requiredFailures: 1,
                healthControlsEnabled: $healthControlsEnabled,
                policyRelaxed: $policyRelaxed,
                selectedRegions: $selectedRegions,
                excludedRegions: $excludedRegions,
                regionHealth: $regionHealth,
                failedRegions: [],
                healthyRegions: [],
                probeResults: []
            );
        }

        if ($strictBlocked && count($selectedRegions) < 2) {
            return $this->result(
                enabled: true,
                performed: false,
                confirmed: true,
                reason: 'not_enough_healthy_regions',
                planTier: $planTier,
                requiredFailures: 1,
                healthControlsEnabled: $healthControlsEnabled,
                policyRelaxed: $policyRelaxed,
                selectedRegions: $selectedRegions,
                excludedRegions: $excludedRegions,
                regionHealth: $regionHealth,
                failedRegions: [],
                healthyRegions: [],
                probeResults: []
            );
        }

        if (count($selectedRegions) < 2) {
            return $this->result(
                enabled: true,
                performed: false,
                confirmed: true,
                reason: 'not_enough_regions',
                planTier: $planTier,
                requiredFailures: 1,
                healthControlsEnabled: $healthControlsEnabled,
                policyRelaxed: $policyRelaxed,
                selectedRegions: $selectedRegions,
                excludedRegions: $excludedRegions,
                regionHealth: $regionHealth,
                failedRegions: [],
                healthyRegions: [],
                probeResults: []
            );
        }

        $requiredFailures = $this->requiredFailures(count($selectedRegions));
        $primaryRegion = $selectedRegions[0];
        $primaryKind = $this->normalizePrimaryDownKind($primaryDownKind);
        $healthService->recordProbeResult($primaryRegion, true);

        $failedRegions = [[
            'region' => $primaryRegion,
            'kind' => $primaryKind,
            'status' => null,
            'error' => null,
        ]];

        $healthyRegions = [];
        $probeResults = [];

        foreach (array_slice($selectedRegions, 1) as $region) {
            $probe = $this->probeRegion($monitoredUrl, $region);
            $probeResults[] = $probe;
            $healthService->recordProbeResult($region, (bool) $probe['is_down']);

            if ($probe['is_down']) {
                $failedRegions[] = [
                    'region' => $region,
                    'kind' => $probe['kind'],
                    'status' => $probe['status'],
                    'error' => $probe['error'],
                ];
            } else {
                $healthyRegions[] = [
                    'region' => $region,
                    'status' => $probe['status'],
                ];
            }

            $remaining = count($selectedRegions) - 1 - count($probeResults);

            if (count($failedRegions) >= $requiredFailures) {
                break;
            }

            if ((count($failedRegions) + $remaining) < $requiredFailures) {
                break;
            }
        }

        $confirmed = count($failedRegions) >= $requiredFailures;

        return $this->result(
            enabled: true,
            performed: true,
            confirmed: $confirmed,
            reason: $confirmed ? 'quorum_met' : 'quorum_not_met',
            planTier: $planTier,
            requiredFailures: $requiredFailures,
            healthControlsEnabled: $healthControlsEnabled,
            policyRelaxed: $policyRelaxed,
            selectedRegions: $selectedRegions,
            excludedRegions: $excludedRegions,
            regionHealth: $regionHealth,
            failedRegions: $failedRegions,
            healthyRegions: $healthyRegions,
            probeResults: $probeResults
        );
    }

    /**
     * @return list<string>
     */
    private function selectedRegionsForPlanTier(string $planTier): array
    {
        $regions = config('wids.multi_region.regions', []);
        $regions = is_array($regions) ? $regions : [];

        $normalizedRegions = [];

        foreach ($regions as $region) {
            if (! is_string($region)) {
                continue;
            }

            $region = strtolower(trim($region));

            if ($region === '' || in_array($region, $normalizedRegions, true)) {
                continue;
            }

            $normalizedRegions[] = $region;
        }

        $maxRegionsByTier = config('wids.multi_region.max_regions_by_tier', []);
        $maxRegionsByTier = is_array($maxRegionsByTier) ? $maxRegionsByTier : [];
        $maxForTier = (int) ($maxRegionsByTier[$planTier] ?? $maxRegionsByTier['default'] ?? count($normalizedRegions));
        $maxForTier = max(1, min(16, $maxForTier));

        return array_slice($normalizedRegions, 0, $maxForTier);
    }

    private function resolvePlanTier(MonitoredUrl $monitoredUrl): string
    {
        $tier = '';

        if ($monitoredUrl->relationLoaded('tenant') && $monitoredUrl->tenant !== null) {
            $tier = (string) ($monitoredUrl->tenant->plan_tier ?? '');
        }

        if ($tier === '' && $monitoredUrl->tenant_id !== null) {
            $limits = app(TenantQuotaService::class)->limitsForTenantId((int) $monitoredUrl->tenant_id);
            $tier = is_string($limits['tier'] ?? null) ? (string) $limits['tier'] : '';
        }

        if ($tier === '') {
            $tier = (string) config('wids.default_plan_tier', 'lite');
        }

        $tier = strtolower(trim($tier));

        return $tier !== '' ? $tier : 'lite';
    }

    private function requiredFailures(int $regionCount): int
    {
        $quorum = (int) config('wids.multi_region.quorum_failures', 2);
        $quorum = max(1, min(16, $quorum));

        return min($regionCount, $quorum);
    }

    /**
     * @return array{region: string, is_down: bool, kind: string, status: ?int, error: ?string}
     */
    private function probeRegion(MonitoredUrl $monitoredUrl, string $region): array
    {
        try {
            $resolvedTarget = UrlSafetyService::resolveSafeTarget((string) $monitoredUrl->url);

            $timeoutSeconds = max(2, min(60, (int) config('wids.multi_region.probe_timeout_seconds', 8)));
            $userAgent = trim((string) config('wids.user_agent', 'TruMonit-wids/0.1'));
            $userAgent = $userAgent !== '' ? $userAgent.' region-probe/'.$region : 'TruMonit-wids/0.1 region-probe/'.$region;

            $pendingRequest = Http::timeout($timeoutSeconds)
                ->withHeaders([
                    'User-Agent' => $userAgent,
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'X-Wids-Probe-Region' => $region,
                ])
                ->withoutRedirecting();

            $pendingRequest = $this->applyDnsPinning($pendingRequest, $resolvedTarget);

            $response = $pendingRequest->get((string) $monitoredUrl->url);
            UrlSafetyService::assertNoDnsDrift((string) $resolvedTarget['host'], (array) $resolvedTarget['resolved_ips']);

            $status = $response->status();
            $isDown = $status < (int) $monitoredUrl->expected_status_min || $status > (int) $monitoredUrl->expected_status_max;

            return [
                'region' => $region,
                'is_down' => $isDown,
                'kind' => $isDown ? 'unexpected_status' : 'ok',
                'status' => $status,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'region' => $region,
                'is_down' => true,
                'kind' => 'fetch_error',
                'status' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $resolvedTarget
     */
    private function applyDnsPinning($pendingRequest, array $resolvedTarget)
    {
        $isIpLiteral = (bool) ($resolvedTarget['is_ip_literal'] ?? false);

        if ($isIpLiteral || ! defined('CURLOPT_RESOLVE')) {
            return $pendingRequest;
        }

        $host = is_string($resolvedTarget['host'] ?? null) ? (string) $resolvedTarget['host'] : '';
        $port = (int) ($resolvedTarget['port'] ?? 0);
        $connectIp = is_string($resolvedTarget['connect_ip'] ?? null) ? (string) $resolvedTarget['connect_ip'] : '';

        if ($host === '' || $port < 1 || $port > 65535 || $connectIp === '') {
            return $pendingRequest;
        }

        $resolveAddress = $this->formatCurlResolveAddress($connectIp);

        if ($resolveAddress === null) {
            return $pendingRequest;
        }

        return $pendingRequest->withOptions([
            'curl' => [
                CURLOPT_RESOLVE => [$host.':'.$port.':'.$resolveAddress],
            ],
        ]);
    }

    private function formatCurlResolveAddress(string $ip): ?string
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return null;
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return '['.$ip.']';
        }

        return $ip;
    }

    private function normalizePrimaryDownKind(string $kind): string
    {
        $kind = strtolower(trim($kind));

        return in_array($kind, ['fetch_error', 'unexpected_status', 'journey_failed'], true) ? $kind : 'fetch_error';
    }

    /**
     * @param  list<string>  $selectedRegions
     * @param  list<array{region: string, reason: string, attempts: int, failures: int, failure_ratio: float, unhealthy: bool}>  $excludedRegions
     * @param  array<string, array{attempts: int, failures: int, failure_ratio: float, unhealthy: bool}>  $regionHealth
     * @param  list<array{region: string, kind: string, status: ?int, error: ?string}>  $failedRegions
     * @param  list<array{region: string, status: ?int}>  $healthyRegions
     * @param  list<array{region: string, is_down: bool, kind: string, status: ?int, error: ?string}>  $probeResults
     * @return array{
     *   enabled: bool,
     *   performed: bool,
     *   confirmed: bool,
     *   reason: string,
     *   plan_tier: string,
      *   required_failures: int,
     *   health_controls_enabled: bool,
     *   policy_relaxed: bool,
     *   selected_regions: list<string>,
     *   excluded_regions: list<array{region: string, reason: string, attempts: int, failures: int, failure_ratio: float, unhealthy: bool}>,
     *   region_health: array<string, array{attempts: int, failures: int, failure_ratio: float, unhealthy: bool}>,
     *   failed_regions: list<array{region: string, kind: string, status: ?int, error: ?string}>,
     *   healthy_regions: list<array{region: string, status: ?int}>,
     *   probe_results: list<array{region: string, is_down: bool, kind: string, status: ?int, error: ?string}>
     * }
     */
    private function result(
        bool $enabled,
        bool $performed,
        bool $confirmed,
        string $reason,
        string $planTier,
        int $requiredFailures,
        bool $healthControlsEnabled,
        bool $policyRelaxed,
        array $selectedRegions,
        array $excludedRegions,
        array $regionHealth,
        array $failedRegions,
        array $healthyRegions,
        array $probeResults
    ): array {
        return [
            'enabled' => $enabled,
            'performed' => $performed,
            'confirmed' => $confirmed,
            'reason' => $reason,
            'plan_tier' => $planTier,
            'required_failures' => max(1, $requiredFailures),
            'health_controls_enabled' => $healthControlsEnabled,
            'policy_relaxed' => $policyRelaxed,
            'selected_regions' => $selectedRegions,
            'excluded_regions' => $excludedRegions,
            'region_health' => $regionHealth,
            'failed_regions' => $failedRegions,
            'healthy_regions' => $healthyRegions,
            'probe_results' => $probeResults,
        ];
    }
}
