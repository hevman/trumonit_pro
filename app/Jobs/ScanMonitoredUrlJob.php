<?php

namespace App\Jobs;

use App\Services\UrlSafetyService;
use App\Services\IncidentService;
use App\Services\MultiRegionOutageConfirmationService;
use App\Services\TenantQuotaService;
use App\Models\Incident;
use App\Models\MonitoredUrl;
use App\Models\UrlSnapshot;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

class ScanMonitoredUrlJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public readonly int $monitoredUrlId;

    public readonly int $queuedAtMs;

    public readonly ?int $scanLagSeconds;

    public function __construct(int $monitoredUrlId, ?int $queuedAtMs = null, ?int $scanLagSeconds = null)
    {
        $this->monitoredUrlId = $monitoredUrlId;
        $this->queuedAtMs = $queuedAtMs ?? $this->currentTimeMs();
        $this->scanLagSeconds = is_int($scanLagSeconds) ? max(0, $scanLagSeconds) : null;

        $defaultQueue = (string) config('wids.queue.scan.default_queue', 'wids');
        $this->onQueue(trim($defaultQueue) !== '' ? $defaultQueue : 'wids');
    }

    public function handle(): void
    {
        $lock = Cache::lock('wids:scan:monitored_url:'.$this->monitoredUrlId, 600);

        if (! $lock->get()) {
            return;
        }

        try {
            $monitoredUrl = MonitoredUrl::query()->find($this->monitoredUrlId);

            if (! $monitoredUrl || ! $monitoredUrl->is_active) {
                return;
            }

            $fetchedAt = now();

            if (! $this->acquireRateLimit($monitoredUrl)) {
                $frequencyMinutes = (int) ($monitoredUrl->frequency_minutes ?? 1440);
                $frequencyMinutes = min(43200, max(1, $frequencyMinutes));

                $monitoredUrl->forceFill([
                    'last_scanned_at' => $fetchedAt,
                    'next_scan_at' => $fetchedAt->copy()->addMinutes($frequencyMinutes),
                ])->save();

                return;
            }

            $body = null;
            $queueWaitMs = max(0, $this->currentTimeMs() - $this->queuedAtMs);

            $snapshotData = [
                'monitored_url_id' => $monitoredUrl->id,
                'fetched_at' => $fetchedAt,
                'request_url' => $monitoredUrl->url,
                'meta' => $this->buildSnapshotMetaBase($queueWaitMs),
            ];

            [$snapshot, $body] = $this->performScan($monitoredUrl, $snapshotData);

            if (($monitoredUrl->scan_mode ?? 'http') !== 'journey') {
                $this->tryCaptureScreenshot($monitoredUrl, $snapshot);
            }
            $this->tryCaptureDns($monitoredUrl, $snapshot);
            $this->tryCaptureTls($monitoredUrl, $snapshot);

            $previousSnapshot = UrlSnapshot::query()
                ->where('monitored_url_id', $monitoredUrl->id)
                ->where('id', '!=', $snapshot->id)
                ->orderByDesc('fetched_at')
                ->first();

            $baselineSnapshot = null;

            if ($monitoredUrl->baseline_snapshot_id !== null) {
                $baselineSnapshot = UrlSnapshot::query()
                    ->where('monitored_url_id', $monitoredUrl->id)
                    ->find($monitoredUrl->baseline_snapshot_id);
            }

            $alertState = $this->applyAlertRules($monitoredUrl, $snapshot);
            $multiRegionConfirmation = $this->confirmDownStateAcrossRegions($monitoredUrl, $snapshot, $alertState);

            if (is_array($multiRegionConfirmation)) {
                $meta = is_array($snapshot->meta) ? $snapshot->meta : [];
                $meta['multi_region'] = $multiRegionConfirmation;

                $snapshot->forceFill([
                    'meta' => $meta,
                ])->save();

                if (($multiRegionConfirmation['performed'] ?? false) && ! ($multiRegionConfirmation['confirmed'] ?? true)) {
                    $alertState['should_emit'] = false;
                    $alertState['consecutive_failures'] = 0;
                    $monitoredUrl->consecutive_failures = 0;
                }
            }

            $incidents = [];

            if ($alertState['should_emit'] ?? true) {
                $incidents = $this->detectIncidents($monitoredUrl, $snapshot, $previousSnapshot, $baselineSnapshot, $body, (bool) ($alertState['suppress_content_signals'] ?? false));
            }

            if ($incidents !== []) {
                $incidentService = app(IncidentService::class);

                foreach ($incidents as $incidentPayload) {
                    $incidentService->upsert($incidentPayload);
                }
            }

            $this->autoResolveRecoveredIncidents($monitoredUrl, $snapshot, $incidents);

            $frequencyMinutes = (int) ($monitoredUrl->frequency_minutes ?? 1440);
            $frequencyMinutes = min(43200, max(1, $frequencyMinutes));

            $monitoredUrl->forceFill([
                'last_scanned_at' => $fetchedAt,
                'next_scan_at' => $fetchedAt->copy()->addMinutes($frequencyMinutes),
                'consecutive_failures' => (int) ($alertState['consecutive_failures'] ?? ($monitoredUrl->consecutive_failures ?? 0)),
            ])->save();
        } finally {
            $lock->release();
        }
    }

    private function currentTimeMs(): int
    {
        return (int) floor(microtime(true) * 1000);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSnapshotMetaBase(int $queueWaitMs): array
    {
        return [
            'queue' => is_string($this->queue) && trim($this->queue) !== '' ? (string) $this->queue : null,
            'queue_wait_ms' => max(0, $queueWaitMs),
            'dispatched_at_ms' => $this->queuedAtMs,
            'scan_lag_seconds' => $this->scanLagSeconds,
        ];
    }

    /**
     * @param  array{is_down: bool, suppress_content_signals: bool, consecutive_failures: int, should_emit: bool}  $alertState
     * @return array<string, mixed>|null
     */
    private function confirmDownStateAcrossRegions(MonitoredUrl $monitoredUrl, UrlSnapshot $snapshot, array $alertState): ?array
    {
        if (! ($alertState['is_down'] ?? false)) {
            return null;
        }

        $primaryDownKind = $this->primaryDownKind($monitoredUrl, $snapshot);

        return app(MultiRegionOutageConfirmationService::class)->confirmDownState(
            $monitoredUrl,
            $primaryDownKind
        );
    }

    private function primaryDownKind(MonitoredUrl $monitoredUrl, UrlSnapshot $snapshot): string
    {
        if (is_string($snapshot->error_message) && trim($snapshot->error_message) !== '') {
            return 'fetch_error';
        }

        if ($snapshot->http_status !== null) {
            $status = (int) $snapshot->http_status;

            if ($status < (int) $monitoredUrl->expected_status_min || $status > (int) $monitoredUrl->expected_status_max) {
                return 'unexpected_status';
            }
        }

        $journey = is_array($snapshot->meta['journey'] ?? null) ? $snapshot->meta['journey'] : null;

        if (is_array($journey) && ($journey['ok'] ?? true) === false) {
            return 'journey_failed';
        }

        return 'fetch_error';
    }

    private function canStoreSnapshotBytes(MonitoredUrl $monitoredUrl, int $bytes): bool
    {
        $bytes = max(0, $bytes);

        if ($bytes <= 0) {
            return true;
        }

        return ! app(TenantQuotaService::class)->exceedsStorageQuota($monitoredUrl->tenant_id, $bytes);
    }

    /**
     * Compute and apply per-monitor alert rules based on scan result.
     *
     * @return array{is_down: bool, suppress_content_signals: bool, consecutive_failures: int, should_emit: bool}
     */
    private function applyAlertRules(MonitoredUrl $monitoredUrl, UrlSnapshot $snapshot): array
    {
        $isFetchError = is_string($snapshot->error_message) && trim($snapshot->error_message) !== '';

        $isUnexpectedStatus = $snapshot->http_status !== null
            && ($snapshot->http_status < $monitoredUrl->expected_status_min || $snapshot->http_status > $monitoredUrl->expected_status_max);

        $meta = is_array($snapshot->meta) ? $snapshot->meta : [];
        $journey = is_array($meta['journey'] ?? null) ? $meta['journey'] : null;
        $isJourneyFailed = is_array($journey) && ($journey['ok'] ?? true) === false;

        $isDown = $isFetchError || $isUnexpectedStatus || $isJourneyFailed;
        $suppressContentSignals = $isFetchError || $isUnexpectedStatus;

        $currentFailures = (int) ($monitoredUrl->consecutive_failures ?? 0);
        $currentFailures = max(0, $currentFailures);

        $consecutiveFailures = $isDown ? min(1_000_000, $currentFailures + 1) : 0;

        $defaultAlertAfterFailures = min(10, max(1, (int) config('wids.alerts.default_after_failures', 2)));
        $alertAfterFailures = (int) ($monitoredUrl->alert_after_failures ?? $defaultAlertAfterFailures);
        $alertAfterFailures = min(10, max(1, $alertAfterFailures));

        $shouldEmit = ! $isDown || $consecutiveFailures >= $alertAfterFailures;

        $monitoredUrl->consecutive_failures = $consecutiveFailures;

        return [
            'is_down' => $isDown,
            'suppress_content_signals' => $suppressContentSignals,
            'consecutive_failures' => $consecutiveFailures,
            'should_emit' => $shouldEmit,
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshotData
     * @return array{0: UrlSnapshot, 1: ?string}
     */
    private function performScan(MonitoredUrl $monitoredUrl, array $snapshotData): array
    {
        $scanMode = is_string($monitoredUrl->scan_mode) ? strtolower(trim($monitoredUrl->scan_mode)) : 'http';

        if ($scanMode === 'journey') {
            return $this->scanViaJourney($monitoredUrl, $snapshotData);
        }

        return $this->scanViaHttp($monitoredUrl, $snapshotData);
    }

    /**
     * @param  array<string, mixed>  $snapshotData
     * @return array{0: UrlSnapshot, 1: ?string}
     */
    private function scanViaHttp(MonitoredUrl $monitoredUrl, array $snapshotData): array
    {
        $body = null;

        try {
            $maxRedirects = (int) config('wids.http.max_redirects', 5);
            $maxRedirects = min(20, max(0, $maxRedirects));

            $redirectChain = [];
            $resolvedTargets = [];
            $currentUrl = $monitoredUrl->url;

            for ($i = 0; $i <= $maxRedirects; $i++) {
                $resolvedTarget = UrlSafetyService::resolveSafeTarget($currentUrl);

                $redirectChain[] = $currentUrl;
                $resolvedTargets[] = [
                    'url' => $currentUrl,
                    'host' => $resolvedTarget['host'],
                    'port' => $resolvedTarget['port'],
                    'connect_ip' => $resolvedTarget['connect_ip'],
                    'resolved_ips' => $resolvedTarget['resolved_ips'],
                ];

                $pendingRequest = Http::timeout($monitoredUrl->timeout_seconds)
                    ->withHeaders([
                        'User-Agent' => config('wids.user_agent', 'TruMonit-wids/0.1'),
                        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    ])
                    ->withoutRedirecting();

                $pendingRequest = $this->applyDnsPinning($pendingRequest, $resolvedTarget);

                $response = $pendingRequest->get($currentUrl);
                UrlSafetyService::assertNoDnsDrift((string) $resolvedTarget['host'], (array) $resolvedTarget['resolved_ips']);

                $status = $response->status();
                $location = $response->header('Location');

                $isRedirect = in_array($status, [301, 302, 303, 307, 308], true);

                if ($monitoredUrl->follow_redirects && $isRedirect && is_string($location) && $location !== '') {
                    if ($i === $maxRedirects) {
                        throw new \RuntimeException('Too many redirects.');
                    }

                    $currentUrl = (string) UriResolver::resolve(new Uri($currentUrl), new Uri($location));
                    continue;
                }

                $stats = $response->handlerStats();
                $responseTimeMs = isset($stats['total_time']) ? (int) round(((float) $stats['total_time']) * 1000) : null;

                $body = $response->body();
                $bodyBytesRead = strlen($body);

                $maxBodyBytes = (int) config('wids.http.max_body_bytes', 2_000_000);
                $maxBodyBytes = min(20_000_000, max(0, $maxBodyBytes));

                $bodyTruncated = false;

                if ($maxBodyBytes > 0 && $bodyBytesRead > $maxBodyBytes) {
                    $body = substr($body, 0, $maxBodyBytes);
                    $bodyTruncated = true;
                }

                $bodySha256 = hash('sha256', $body);
                $bodyBytesStored = strlen($body);
                $storeBody = $this->canStoreSnapshotBytes($monitoredUrl, $bodyBytesStored);

                $meta = is_array($snapshotData['meta'] ?? null) ? $snapshotData['meta'] : [];
                $meta['redirect_chain'] = $redirectChain;
                $meta['resolved_targets'] = $resolvedTargets;
                $meta['body_truncated'] = $bodyTruncated;
                $meta['body_bytes_stored'] = $storeBody ? $bodyBytesStored : 0;
                $meta['storage_quota_exceeded'] = ! $storeBody;

                $snapshotData = array_merge($snapshotData, [
                    'final_url' => $currentUrl,
                    'http_status' => $status,
                    'headers' => $response->headers(),
                    'response_time_ms' => $responseTimeMs,
                    'body_size' => $bodyBytesRead,
                    'body_bytes_stored' => $storeBody ? $bodyBytesStored : 0,
                    'body_sha256' => $bodySha256,
                    'meta' => $meta,
                ]);

                break;
            }

            $snapshot = UrlSnapshot::query()->create($snapshotData);

            $shouldStoreBody = (int) ($snapshot->body_bytes_stored ?? 0) > 0;

            if ($shouldStoreBody) {
                $bodyPath = 'wids/bodies/'.$monitoredUrl->id.'/'.$snapshot->id.'.html';
                Storage::put($bodyPath, $body);

                $snapshot->forceFill([
                    'body_storage_path' => $bodyPath,
                ])->save();
            }

            return [$snapshot, $body];
        } catch (\Throwable $e) {
            $meta = is_array($snapshotData['meta'] ?? null) ? $snapshotData['meta'] : [];
            $meta['redirect_chain'] = $meta['redirect_chain'] ?? null;
            $meta['resolved_targets'] = $meta['resolved_targets'] ?? null;

            $snapshot = UrlSnapshot::query()->create(array_merge($snapshotData, [
                'error_message' => $e->getMessage(),
                'meta' => $meta,
            ]));

            return [$snapshot, null];
        }
    }

    /**
     * @param  array<string, mixed>  $snapshotData
     * @return array{0: UrlSnapshot, 1: ?string}
     */
    private function scanViaJourney(MonitoredUrl $monitoredUrl, array $snapshotData): array
    {
        $serviceUrl = (string) config('wids.screenshot.service_url', '');
        $serviceUrl = rtrim(trim($serviceUrl), '/');

        if (! config('wids.screenshot.enabled', true) || $serviceUrl === '') {
            $snapshot = UrlSnapshot::query()->create(array_merge($snapshotData, [
                'error_message' => 'Journey scan requires screenshot service.',
            ]));

            return [$snapshot, null];
        }

        $targetUrl = (string) $monitoredUrl->url;
        $resolvedTarget = null;

        try {
            $resolvedTarget = UrlSafetyService::resolveSafeTarget($targetUrl);
        } catch (\Throwable $e) {
            $snapshot = UrlSnapshot::query()->create(array_merge($snapshotData, [
                'error_message' => $e->getMessage(),
            ]));

            return [$snapshot, null];
        }

        $timeoutSeconds = (int) config('wids.screenshot.timeout_seconds', 30);
        $timeoutSeconds = min(180, max(5, $timeoutSeconds));

        $viewportWidth = (int) config('wids.screenshot.viewport_width', 1280);
        $viewportHeight = (int) config('wids.screenshot.viewport_height', 720);

        $fullPage = (bool) config('wids.screenshot.full_page', false);
        $waitUntil = (string) config('wids.screenshot.wait_until', 'domcontentloaded');
        $maxBytes = (int) config('wids.screenshot.max_bytes', 2_000_000);

        $steps = $monitoredUrl->journey_steps;
        $steps = is_array($steps) ? array_values($steps) : [];

        $maxHtmlBytes = (int) config('wids.http.max_body_bytes', 2_000_000);

        try {
            $response = Http::timeout($timeoutSeconds + 15)
                ->acceptJson()
                ->post($serviceUrl.'/journey', [
                    'url' => $targetUrl,
                    'steps' => $steps,
                    'viewport' => [
                        'width' => $viewportWidth,
                        'height' => $viewportHeight,
                    ],
                    'fullPage' => $fullPage,
                    'waitUntil' => $waitUntil,
                    'timeoutMs' => $timeoutSeconds * 1000,
                    'maxHtmlBytes' => $maxHtmlBytes,
                ]);

            if (is_array($resolvedTarget)) {
                UrlSafetyService::assertNoDnsDrift((string) $resolvedTarget['host'], (array) $resolvedTarget['resolved_ips']);
            }

            if (! $response->successful()) {
                $message = $response->json('message');

                $snapshot = UrlSnapshot::query()->create(array_merge($snapshotData, [
                    'error_message' => is_string($message) && $message !== '' ? $message : 'Journey request failed.',
                ]));

                return [$snapshot, null];
            }

            $data = $response->json();

            if (! is_array($data)) {
                $snapshot = UrlSnapshot::query()->create(array_merge($snapshotData, [
                    'error_message' => 'Invalid journey response.',
                ]));

                return [$snapshot, null];
            }

            $finalUrl = $data['final_url'] ?? null;
            $finalUrl = is_string($finalUrl) && $finalUrl !== '' ? $finalUrl : null;

            $httpStatus = $data['http_status'] ?? null;
            $httpStatus = is_int($httpStatus) ? $httpStatus : null;

            $headers = $data['headers'] ?? null;
            $headers = is_array($headers) ? $headers : null;

            $body = $data['html'] ?? null;
            $body = is_string($body) ? $body : '';

            $bodyBytesRead = strlen($body);
            $bodySha256 = hash('sha256', $body);
            $storeBody = $this->canStoreSnapshotBytes($monitoredUrl, $bodyBytesRead);

            $journey = $data['journey'] ?? null;
            $journey = is_array($journey) ? $journey : null;

            $durationMs = $journey && isset($journey['duration_ms']) && is_int($journey['duration_ms']) ? (int) $journey['duration_ms'] : null;

            $consoleErrors = $data['console_errors'] ?? null;
            $pageErrors = $data['page_errors'] ?? null;
            $requestFailed = $data['request_failed'] ?? null;

            $meta = is_array($snapshotData['meta'] ?? null) ? $snapshotData['meta'] : [];
            $meta['journey'] = $journey;
            if (is_array($resolvedTarget)) {
                $meta['resolved_target'] = [
                    'host' => $resolvedTarget['host'],
                    'port' => $resolvedTarget['port'],
                    'connect_ip' => $resolvedTarget['connect_ip'],
                    'resolved_ips' => $resolvedTarget['resolved_ips'],
                ];
            }
            $meta['body_truncated'] = (bool) ($data['html_truncated'] ?? false);
            $meta['body_bytes_stored'] = $storeBody ? $bodyBytesRead : 0;
            $meta['storage_quota_exceeded'] = ! $storeBody;

            $meta['browser'] = [
                'console_errors' => is_array($consoleErrors) ? array_slice($consoleErrors, 0, 20) : [],
                'page_errors' => is_array($pageErrors) ? array_slice($pageErrors, 0, 20) : [],
                'request_failed' => is_array($requestFailed) ? array_slice($requestFailed, 0, 20) : [],
            ];

            $snapshot = UrlSnapshot::query()->create(array_merge($snapshotData, [
                'final_url' => $finalUrl,
                'http_status' => $httpStatus,
                'headers' => $headers,
                'response_time_ms' => $durationMs,
                'body_size' => $bodyBytesRead,
                'body_bytes_stored' => $storeBody ? $bodyBytesRead : 0,
                'body_sha256' => $bodySha256,
                'meta' => $meta,
            ]));

            if ($storeBody && $body !== '') {
                $bodyPath = 'wids/bodies/'.$monitoredUrl->id.'/'.$snapshot->id.'.html';
                Storage::put($bodyPath, $body);

                $snapshot->forceFill([
                    'body_storage_path' => $bodyPath,
                ])->save();
            }

            $pngBase64 = $data['png_base64'] ?? null;
            $phash = $data['phash'] ?? null;

            if (is_string($pngBase64) && $pngBase64 !== '') {
                $pngBytes = base64_decode($pngBase64, true);

                if (is_string($pngBytes) && $pngBytes !== '') {
                    $pngSize = strlen($pngBytes);

                    if (($maxBytes <= 0 || $pngSize <= $maxBytes) && $this->canStoreSnapshotBytes($monitoredUrl, $pngSize)) {
                        $phashString = is_string($phash) && preg_match('/\A[0-9a-f]{16,64}\z/i', $phash) ? strtolower($phash) : null;

                        $screenshotPath = 'wids/screenshots/'.$monitoredUrl->id.'/'.$snapshot->id.'.png';
                        Storage::put($screenshotPath, $pngBytes);

                        $meta = is_array($snapshot->meta) ? $snapshot->meta : [];
                        $meta['screenshot'] = [
                            'bytes' => $pngSize,
                        ];

                        $snapshot->forceFill([
                            'screenshot_storage_path' => $screenshotPath,
                            'screenshot_bytes_stored' => $pngSize,
                            'screenshot_phash' => $phashString,
                            'meta' => $meta,
                        ])->save();
                    } elseif ($maxBytes <= 0 || $pngSize <= $maxBytes) {
                        $meta = is_array($snapshot->meta) ? $snapshot->meta : [];
                        $meta['storage_quota_exceeded'] = true;
                        $snapshot->forceFill([
                            'meta' => $meta,
                        ])->save();
                    }
                }
            }

            return [$snapshot, $body];
        } catch (\Throwable $e) {
            $snapshot = UrlSnapshot::query()->create(array_merge($snapshotData, [
                'error_message' => $e->getMessage(),
            ]));

            return [$snapshot, null];
        }
    }

    private function acquireRateLimit(MonitoredUrl $monitoredUrl): bool
    {
        $limits = app(TenantQuotaService::class)->limitsForTenantId((int) $monitoredUrl->tenant_id);

        $tenantMax = (int) ($limits['tenant_rpm'] ?? 0);
        $hostMax = (int) config('wids.rate_limits.host_requests_per_minute', 0);

        $tenantMax = max(0, $tenantMax);
        $hostMax = max(0, $hostMax);

        if ($tenantMax > 0) {
            $tenantKey = 'wids:rl:tenant:'.$monitoredUrl->tenant_id;
            $ok = RateLimiter::attempt($tenantKey, $tenantMax, fn () => true, 60);

            if (! $ok) {
                return false;
            }
        }

        if ($hostMax > 0) {
            $host = parse_url((string) $monitoredUrl->url, PHP_URL_HOST);
            $host = is_string($host) ? strtolower(trim($host)) : '';

            if ($host !== '') {
                $hostKey = 'wids:rl:host:'.$host;
                $ok = RateLimiter::attempt($hostKey, $hostMax, fn () => true, 60);

                if (! $ok) {
                    return false;
                }
            }
        }

        return true;
    }

    private function tryCaptureScreenshot(MonitoredUrl $monitoredUrl, UrlSnapshot $snapshot): void
    {
        if ($snapshot->error_message !== null) {
            return;
        }

        if (! config('wids.screenshot.enabled', true)) {
            return;
        }

        $serviceUrl = (string) config('wids.screenshot.service_url', '');
        $serviceUrl = rtrim(trim($serviceUrl), '/');

        if ($serviceUrl === '') {
            return;
        }

        $targetUrl = $snapshot->final_url ?? $snapshot->request_url;

        if (! is_string($targetUrl) || $targetUrl === '') {
            return;
        }

        $resolvedTarget = null;

        try {
            $resolvedTarget = UrlSafetyService::resolveSafeTarget($targetUrl);
        } catch (\Throwable) {
            return;
        }

        $timeoutSeconds = (int) config('wids.screenshot.timeout_seconds', 30);
        $timeoutSeconds = min(120, max(5, $timeoutSeconds));

        $viewportWidth = (int) config('wids.screenshot.viewport_width', 1280);
        $viewportHeight = (int) config('wids.screenshot.viewport_height', 720);

        $fullPage = (bool) config('wids.screenshot.full_page', false);
        $waitUntil = (string) config('wids.screenshot.wait_until', 'domcontentloaded');

        $maxBytes = (int) config('wids.screenshot.max_bytes', 2_000_000);

        try {
            $response = Http::timeout($timeoutSeconds + 5)
                ->acceptJson()
                ->post($serviceUrl.'/screenshot', [
                    'url' => $targetUrl,
                    'viewport' => [
                        'width' => $viewportWidth,
                        'height' => $viewportHeight,
                    ],
                    'fullPage' => $fullPage,
                    'waitUntil' => $waitUntil,
                    'timeoutMs' => $timeoutSeconds * 1000,
                ]);

            if (is_array($resolvedTarget)) {
                UrlSafetyService::assertNoDnsDrift((string) $resolvedTarget['host'], (array) $resolvedTarget['resolved_ips']);
            }

            if (! $response->successful()) {
                return;
            }

            $data = $response->json();

            if (! is_array($data)) {
                return;
            }

            $pngBase64 = $data['png_base64'] ?? null;
            $phash = $data['phash'] ?? null;
            $consoleErrors = $data['console_errors'] ?? null;
            $pageErrors = $data['page_errors'] ?? null;
            $requestFailed = $data['request_failed'] ?? null;

            if (! is_string($pngBase64) || $pngBase64 === '') {
                return;
            }

            $pngBytes = base64_decode($pngBase64, true);

            if (! is_string($pngBytes) || $pngBytes === '') {
                return;
            }

            if ($maxBytes > 0 && strlen($pngBytes) > $maxBytes) {
                return;
            }

            $pngSize = strlen($pngBytes);

            if (! $this->canStoreSnapshotBytes($monitoredUrl, $pngSize)) {
                $meta = is_array($snapshot->meta) ? $snapshot->meta : [];
                $meta['storage_quota_exceeded'] = true;

                $snapshot->forceFill([
                    'meta' => $meta,
                ])->save();

                return;
            }

            $phashString = is_string($phash) && preg_match('/\A[0-9a-f]{16,64}\z/i', $phash) ? strtolower($phash) : null;

            $screenshotPath = 'wids/screenshots/'.$monitoredUrl->id.'/'.$snapshot->id.'.png';
            Storage::put($screenshotPath, $pngBytes);

            $meta = is_array($snapshot->meta) ? $snapshot->meta : [];

            $meta['browser'] = [
                'console_errors' => is_array($consoleErrors) ? array_slice($consoleErrors, 0, 20) : [],
                'page_errors' => is_array($pageErrors) ? array_slice($pageErrors, 0, 20) : [],
                'request_failed' => is_array($requestFailed) ? array_slice($requestFailed, 0, 20) : [],
            ];

            $meta['screenshot'] = [
                'bytes' => $pngSize,
            ];

            if (is_array($resolvedTarget)) {
                $meta['resolved_target'] = [
                    'host' => $resolvedTarget['host'],
                    'port' => $resolvedTarget['port'],
                    'connect_ip' => $resolvedTarget['connect_ip'],
                    'resolved_ips' => $resolvedTarget['resolved_ips'],
                ];
            }

            $snapshot->forceFill([
                'screenshot_storage_path' => $screenshotPath,
                'screenshot_bytes_stored' => $pngSize,
                'screenshot_phash' => $phashString,
                'meta' => $meta,
            ])->save();
        } catch (\Throwable) {
            return;
        }
    }

    private function tryCaptureDns(MonitoredUrl $monitoredUrl, UrlSnapshot $snapshot): void
    {
        if ($snapshot->error_message !== null) {
            return;
        }

        $targetUrl = $snapshot->final_url ?? $snapshot->request_url;

        if (! is_string($targetUrl) || $targetUrl === '') {
            return;
        }

        $host = parse_url($targetUrl, PHP_URL_HOST);
        $host = is_string($host) ? strtolower(trim($host)) : '';

        if ($host === '') {
            return;
        }

        try {
            UrlSafetyService::assertSafeForScanning($targetUrl);
        } catch (\Throwable) {
            return;
        }

        $a = [];
        $aaaa = [];
        $cname = [];

        try {
            $records = @dns_get_record($host, DNS_A | DNS_AAAA | DNS_CNAME);

            if (is_array($records)) {
                foreach ($records as $record) {
                    if (! is_array($record)) {
                        continue;
                    }

                    $type = $record['type'] ?? null;

                    if ($type === 'A' && isset($record['ip']) && is_string($record['ip'])) {
                        $a[] = $record['ip'];
                    } elseif ($type === 'AAAA' && isset($record['ipv6']) && is_string($record['ipv6'])) {
                        $aaaa[] = $record['ipv6'];
                    } elseif ($type === 'CNAME' && isset($record['target']) && is_string($record['target'])) {
                        $cname[] = $record['target'];
                    }
                }
            }
        } catch (\Throwable) {
            return;
        }

        $a = array_values(array_unique(array_filter(array_map('trim', $a))));
        $aaaa = array_values(array_unique(array_filter(array_map('trim', $aaaa))));
        $cname = array_values(array_unique(array_filter(array_map('trim', $cname))));

        sort($a);
        sort($aaaa);
        sort($cname);

        $meta = is_array($snapshot->meta) ? $snapshot->meta : [];
        $meta['dns'] = [
            'host' => $host,
            'a' => $a,
            'aaaa' => $aaaa,
            'cname' => $cname,
            'fetched_at' => now()->toISOString(),
        ];

        $snapshot->forceFill([
            'meta' => $meta,
        ])->save();
    }

    private function tryCaptureTls(MonitoredUrl $monitoredUrl, UrlSnapshot $snapshot): void
    {
        if ($snapshot->error_message !== null) {
            return;
        }

        $targetUrl = $snapshot->final_url ?? $snapshot->request_url;

        if (! is_string($targetUrl) || $targetUrl === '') {
            return;
        }

        $scheme = parse_url($targetUrl, PHP_URL_SCHEME);
        $scheme = is_string($scheme) ? strtolower(trim($scheme)) : '';

        if ($scheme !== 'https') {
            return;
        }

        $host = parse_url($targetUrl, PHP_URL_HOST);
        $host = is_string($host) ? strtolower(trim($host)) : '';

        if ($host === '') {
            return;
        }

        $port = parse_url($targetUrl, PHP_URL_PORT);
        $port = is_int($port) ? $port : 443;

        if ($port <= 0 || $port > 65535) {
            return;
        }

        try {
            UrlSafetyService::assertSafeForScanning($targetUrl);
        } catch (\Throwable) {
            return;
        }

        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
                'peer_name' => $host,
                'SNI_enabled' => true,
            ],
        ]);

        $timeoutSeconds = min(10, max(2, (int) ($monitoredUrl->timeout_seconds ?? 5)));
        $errno = 0;
        $errstr = '';

        $socket = @stream_socket_client(
            'ssl://'.$host.':'.$port,
            $errno,
            $errstr,
            $timeoutSeconds,
            STREAM_CLIENT_CONNECT,
            $context,
        );

        if (! is_resource($socket)) {
            return;
        }

        try {
            $params = stream_context_get_params($socket);
            $cert = $params['options']['ssl']['peer_certificate'] ?? null;

            if ($cert === null) {
                return;
            }

            $parsed = @openssl_x509_parse($cert, false);

            if (! is_array($parsed)) {
                return;
            }

            $validFrom = isset($parsed['validFrom_time_t']) ? (int) $parsed['validFrom_time_t'] : null;
            $validTo = isset($parsed['validTo_time_t']) ? (int) $parsed['validTo_time_t'] : null;

            $fingerprintSha256 = null;

            if (function_exists('openssl_x509_fingerprint')) {
                $fp = @openssl_x509_fingerprint($cert, 'sha256');
                $fingerprintSha256 = is_string($fp) && $fp !== '' ? strtolower($fp) : null;
            }

            $meta = is_array($snapshot->meta) ? $snapshot->meta : [];
            $meta['tls'] = [
                'host' => $host,
                'port' => $port,
                'fingerprint_sha256' => $fingerprintSha256,
                'serial_number_hex' => isset($parsed['serialNumberHex']) ? (string) $parsed['serialNumberHex'] : null,
                'valid_from' => $validFrom ? now()->setTimestamp($validFrom)->toISOString() : null,
                'valid_to' => $validTo ? now()->setTimestamp($validTo)->toISOString() : null,
                'issuer' => $parsed['issuer'] ?? null,
                'subject' => $parsed['subject'] ?? null,
                'fetched_at' => now()->toISOString(),
            ];

            $snapshot->forceFill([
                'meta' => $meta,
            ])->save();
        } finally {
            try {
                fclose($socket);
            } catch (\Throwable) {
            }
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function detectIncidents(MonitoredUrl $monitoredUrl, UrlSnapshot $snapshot, ?UrlSnapshot $previousSnapshot, ?UrlSnapshot $baselineSnapshot, ?string $body, bool $suppressContentSignals): array
    {
        $incidents = [];
        $noiseControls = $this->resolveIncidentNoiseControls();
        $defaultAlertAfterFailures = min(10, max(1, (int) config('wids.alerts.default_after_failures', 2)));
        $currentAlertAfterFailures = min(10, max(1, (int) ($monitoredUrl->alert_after_failures ?? $defaultAlertAfterFailures)));

        if ($snapshot->error_message !== null) {
            $incidents[] = [
                'tenant_id' => $monitoredUrl->tenant_id,
                'monitored_url_id' => $monitoredUrl->id,
                'previous_snapshot_id' => $previousSnapshot?->id,
                'current_snapshot_id' => $snapshot->id,
                'kind' => 'fetch_error',
                'fingerprint' => $this->computeFingerprint('fetch_error', []),
                'severity' => 80,
                'status' => 'open',
                'summary' => 'Fetch error',
                'details' => [
                    'error_message' => $snapshot->error_message,
                    'consecutive_failures' => (int) ($monitoredUrl->consecutive_failures ?? 0),
                    'alert_after_failures' => $currentAlertAfterFailures,
                ],
                'detected_at' => $snapshot->fetched_at,
                'last_seen_at' => $snapshot->fetched_at,
            ];

            return $this->attachIncidentAlertContext(
                $incidents,
                $monitoredUrl,
                $snapshot,
                $suppressContentSignals,
                $currentAlertAfterFailures,
            );
        }

        if ($snapshot->http_status !== null &&
            ($snapshot->http_status < $monitoredUrl->expected_status_min || $snapshot->http_status > $monitoredUrl->expected_status_max)) {
            $severity = $snapshot->http_status >= 500 ? 95 : 85;

            $incidents[] = [
                'tenant_id' => $monitoredUrl->tenant_id,
                'monitored_url_id' => $monitoredUrl->id,
                'previous_snapshot_id' => $previousSnapshot?->id,
                'current_snapshot_id' => $snapshot->id,
                'kind' => 'unexpected_status',
                'fingerprint' => $this->computeFingerprint('unexpected_status', [$snapshot->http_status]),
                'severity' => $severity,
                'status' => 'open',
                'summary' => 'Unexpected HTTP status',
                'details' => [
                    'http_status' => $snapshot->http_status,
                    'expected_min' => $monitoredUrl->expected_status_min,
                    'expected_max' => $monitoredUrl->expected_status_max,
                    'consecutive_failures' => (int) ($monitoredUrl->consecutive_failures ?? 0),
                    'alert_after_failures' => $currentAlertAfterFailures,
                ],
                'detected_at' => $snapshot->fetched_at,
                'last_seen_at' => $snapshot->fetched_at,
            ];
        }

        if ($match = $this->detectSuspiciousPatternMatch($body)) {
            $severity = is_int($match['severity'] ?? null) ? (int) $match['severity'] : 70;
            $severity = min(100, max(1, $severity));

            $patternId = is_string($match['pattern_id'] ?? null) ? (string) $match['pattern_id'] : '';

            $incidents[] = [
                'tenant_id' => $monitoredUrl->tenant_id,
                'monitored_url_id' => $monitoredUrl->id,
                'previous_snapshot_id' => $previousSnapshot?->id,
                'current_snapshot_id' => $snapshot->id,
                'kind' => 'suspicious_pattern',
                'fingerprint' => $this->computeFingerprint('suspicious_pattern', [$patternId]),
                'severity' => $severity,
                'status' => 'open',
                'summary' => (string) ($match['summary'] ?? 'Suspicious pattern detected'),
                'details' => $match,
                'detected_at' => $snapshot->fetched_at,
                'last_seen_at' => $snapshot->fetched_at,
            ];
        }

        $journey = is_array($snapshot->meta['journey'] ?? null) ? $snapshot->meta['journey'] : null;

        if (is_array($journey) && ($journey['ok'] ?? true) === false) {
            $error = is_string($journey['error'] ?? null) ? (string) $journey['error'] : '';
            $errorKey = $error !== '' ? substr($error, 0, 120) : 'journey_failed';

            $incidents[] = [
                'tenant_id' => $monitoredUrl->tenant_id,
                'monitored_url_id' => $monitoredUrl->id,
                'previous_snapshot_id' => $previousSnapshot?->id,
                'current_snapshot_id' => $snapshot->id,
                'kind' => 'journey_failed',
                'fingerprint' => $this->computeFingerprint('journey_failed', [
                    $baselineSnapshot?->id ?? 0,
                    $errorKey,
                ]),
                'severity' => 75,
                'status' => 'open',
                'summary' => 'User journey failed',
                'details' => [
                    'error' => $error !== '' ? $error : null,
                    'journey' => $journey,
                    'baseline_snapshot_id' => $baselineSnapshot?->id,
                    'consecutive_failures' => (int) ($monitoredUrl->consecutive_failures ?? 0),
                    'alert_after_failures' => $currentAlertAfterFailures,
                ],
                'detected_at' => $snapshot->fetched_at,
                'last_seen_at' => $snapshot->fetched_at,
            ];
        }

        $browser = is_array($snapshot->meta['browser'] ?? null) ? $snapshot->meta['browser'] : null;

        if (is_array($browser)) {
            $consoleErrors = is_array($browser['console_errors'] ?? null) ? $browser['console_errors'] : [];
            $pageErrors = is_array($browser['page_errors'] ?? null) ? $browser['page_errors'] : [];
            $requestFailed = is_array($browser['request_failed'] ?? null) ? $browser['request_failed'] : [];
            $consoleSignalCount = count($consoleErrors) + count($pageErrors);
            $consoleSignalMin = min(20, max(1, (int) ($noiseControls['browser_console_error_min_count'] ?? 2)));

            if ($consoleSignalCount >= $consoleSignalMin) {
                $incidents[] = [
                    'tenant_id' => $monitoredUrl->tenant_id,
                    'monitored_url_id' => $monitoredUrl->id,
                    'previous_snapshot_id' => $previousSnapshot?->id,
                    'current_snapshot_id' => $snapshot->id,
                    'kind' => 'browser_console_error',
                    'fingerprint' => $this->computeFingerprint('browser_console_error', []),
                    'severity' => 45,
                    'status' => 'open',
                    'summary' => 'Browser console / page errors detected',
                    'details' => [
                        'console_errors_count' => count($consoleErrors),
                        'page_errors_count' => count($pageErrors),
                        'signal_count' => $consoleSignalCount,
                        'signal_min_count' => $consoleSignalMin,
                        'console_errors' => array_slice($consoleErrors, 0, 5),
                        'page_errors' => array_slice($pageErrors, 0, 5),
                    ],
                    'detected_at' => $snapshot->fetched_at,
                    'last_seen_at' => $snapshot->fetched_at,
                ];
            }

            $requestFailedIgnorePatterns = is_array($noiseControls['browser_request_failed_ignore_patterns'] ?? null)
                ? $noiseControls['browser_request_failed_ignore_patterns']
                : [];

            $requestFailedRelevant = [];
            $requestFailedIgnoredCount = 0;

            foreach (array_slice($requestFailed, 0, 200) as $entry) {
                if ($this->shouldIgnoreBrowserRequestFailure($entry, $requestFailedIgnorePatterns)) {
                    $requestFailedIgnoredCount++;
                    continue;
                }

                $requestFailedRelevant[] = $entry;
            }

            $requestFailedSignalCount = count($requestFailedRelevant);
            $requestFailedSignalMin = min(20, max(1, (int) ($noiseControls['browser_request_failed_min_count'] ?? 2)));

            if ($requestFailedSignalCount >= $requestFailedSignalMin) {
                $incidents[] = [
                    'tenant_id' => $monitoredUrl->tenant_id,
                    'monitored_url_id' => $monitoredUrl->id,
                    'previous_snapshot_id' => $previousSnapshot?->id,
                    'current_snapshot_id' => $snapshot->id,
                    'kind' => 'browser_request_failed',
                    'fingerprint' => $this->computeFingerprint('browser_request_failed', []),
                    'severity' => 50,
                    'status' => 'open',
                    'summary' => 'Browser request failures detected',
                    'details' => [
                        'request_failed_count' => count($requestFailed),
                        'request_failed_relevant_count' => $requestFailedSignalCount,
                        'request_failed_ignored_count' => $requestFailedIgnoredCount,
                        'signal_min_count' => $requestFailedSignalMin,
                        'ignore_patterns' => array_values($requestFailedIgnorePatterns),
                        'request_failed' => array_slice($requestFailedRelevant, 0, 10),
                    ],
                    'detected_at' => $snapshot->fetched_at,
                    'last_seen_at' => $snapshot->fetched_at,
                ];
            }
        }

        if ($previousSnapshot && $previousSnapshot->final_url !== null && $snapshot->final_url !== null && $previousSnapshot->final_url !== $snapshot->final_url) {
            $incidents[] = [
                'tenant_id' => $monitoredUrl->tenant_id,
                'monitored_url_id' => $monitoredUrl->id,
                'previous_snapshot_id' => $previousSnapshot->id,
                'current_snapshot_id' => $snapshot->id,
                'kind' => 'final_url_change',
                'fingerprint' => $this->computeFingerprint('final_url_change', [$snapshot->final_url]),
                'severity' => 60,
                'status' => 'open',
                'summary' => 'Final URL changed',
                'details' => [
                    'previous_final_url' => $previousSnapshot->final_url,
                    'current_final_url' => $snapshot->final_url,
                ],
                'detected_at' => $snapshot->fetched_at,
                'last_seen_at' => $snapshot->fetched_at,
            ];
        }

        if (! $suppressContentSignals) {
            $appearanceReference = $baselineSnapshot?->screenshot_phash !== null ? $baselineSnapshot : $previousSnapshot;

            if ($appearanceReference && $appearanceReference->screenshot_phash !== null && $snapshot->screenshot_phash !== null) {
                $distance = $this->hammingDistanceHex($appearanceReference->screenshot_phash, $snapshot->screenshot_phash);

                $minDistance = $monitoredUrl->appearance_min_distance ?? (int) config('wids.appearance.min_hamming_distance', 8);
                $minDistance = min(64, max(0, $minDistance));

                if ($distance !== null && $distance >= $minDistance) {
                    $severity = min(100, max(1, 35 + ($distance * 2)));

                    $incidents[] = [
                        'tenant_id' => $monitoredUrl->tenant_id,
                        'monitored_url_id' => $monitoredUrl->id,
                        'previous_snapshot_id' => $appearanceReference->id,
                        'current_snapshot_id' => $snapshot->id,
                        'kind' => 'appearance_change',
                        'fingerprint' => $this->computeFingerprint('appearance_change', [
                            $baselineSnapshot?->id ?? 0,
                            $minDistance,
                        ]),
                        'severity' => $severity,
                        'status' => 'open',
                        'summary' => 'Appearance changed',
                        'details' => [
                            'reference_snapshot_id' => $appearanceReference->id,
                            'reference_screenshot_phash' => $appearanceReference->screenshot_phash,
                            'current_screenshot_phash' => $snapshot->screenshot_phash,
                            'hamming_distance' => $distance,
                            'min_distance' => $minDistance,
                            'reference_screenshot_path' => $appearanceReference->screenshot_storage_path,
                            'current_screenshot_path' => $snapshot->screenshot_storage_path,
                            'baseline_snapshot_id' => $baselineSnapshot?->id,
                        ],
                        'detected_at' => $snapshot->fetched_at,
                        'last_seen_at' => $snapshot->fetched_at,
                    ];
                }
            }

            $contentReference = $baselineSnapshot?->body_sha256 !== null ? $baselineSnapshot : $previousSnapshot;

            if ($contentReference && $contentReference->body_sha256 !== null && $snapshot->body_sha256 !== null && $contentReference->body_sha256 !== $snapshot->body_sha256) {
                $severity = 50;

                $incidents[] = [
                    'tenant_id' => $monitoredUrl->tenant_id,
                    'monitored_url_id' => $monitoredUrl->id,
                    'previous_snapshot_id' => $contentReference->id,
                    'current_snapshot_id' => $snapshot->id,
                    'kind' => 'content_change',
                    'fingerprint' => $this->computeFingerprint('content_change', [
                        $baselineSnapshot?->id ?? 0,
                    ]),
                    'severity' => $severity,
                    'status' => 'open',
                    'summary' => 'Content changed',
                    'details' => [
                        'reference_snapshot_id' => $contentReference->id,
                        'reference_body_sha256' => $contentReference->body_sha256,
                        'current_body_sha256' => $snapshot->body_sha256,
                        'baseline_snapshot_id' => $baselineSnapshot?->id,
                    ],
                    'detected_at' => $snapshot->fetched_at,
                    'last_seen_at' => $snapshot->fetched_at,
                ];
            }
        }

        $incidents = array_merge(
            $incidents,
            $this->detectTlsAndDnsIncidents($monitoredUrl, $snapshot, $previousSnapshot, $baselineSnapshot),
        );

        if (! $suppressContentSignals) {
            $incidents = array_merge(
                $incidents,
                $this->detectContentRuleIncidents($monitoredUrl, $snapshot, $baselineSnapshot, $body),
            );
        }

        return $this->attachIncidentAlertContext(
            $incidents,
            $monitoredUrl,
            $snapshot,
            $suppressContentSignals,
            $currentAlertAfterFailures,
        );
    }

    /**
     * Auto-resolve certain incident kinds when the monitored URL recovers.
     *
     * @param  list<array<string, mixed>>  $incidents
     */
    private function autoResolveRecoveredIncidents(MonitoredUrl $monitoredUrl, UrlSnapshot $snapshot, array $incidents): void
    {
        if (! (bool) config('wids.incidents.auto_resolve.enabled', true)) {
            return;
        }

        if ($snapshot->error_message !== null) {
            return;
        }

        $kinds = config('wids.incidents.auto_resolve.kinds', ['fetch_error', 'unexpected_status', 'journey_failed']);
        $kinds = is_array($kinds) ? array_values($kinds) : [];
        $kinds = array_values(array_unique(array_filter($kinds, fn ($k) => is_string($k) && trim($k) !== '')));

        if ($kinds === []) {
            return;
        }

        $activeKinds = [];

        foreach ($incidents as $incident) {
            $kind = $incident['kind'] ?? null;

            if (is_string($kind) && $kind !== '') {
                $activeKinds[$kind] = true;
            }
        }

        $now = now();

        if (in_array('fetch_error', $kinds, true) && ! isset($activeKinds['fetch_error'])) {
            $this->resolveOpenIncidentsByKind($monitoredUrl, 'fetch_error', $now);
        }

        $httpStatus = $snapshot->http_status;
        $httpStatusOk = $httpStatus !== null
            && is_numeric($httpStatus)
            && (int) $httpStatus >= (int) $monitoredUrl->expected_status_min
            && (int) $httpStatus <= (int) $monitoredUrl->expected_status_max;

        if (in_array('unexpected_status', $kinds, true) && ! isset($activeKinds['unexpected_status']) && $httpStatusOk) {
            $this->resolveOpenIncidentsByKind($monitoredUrl, 'unexpected_status', $now);
        }

        $journeyOk = null;

        $journey = is_array($snapshot->meta['journey'] ?? null) ? $snapshot->meta['journey'] : null;
        if (is_array($journey) && array_key_exists('ok', $journey)) {
            $journeyOk = (bool) $journey['ok'];
        }

        if (in_array('journey_failed', $kinds, true) && ! isset($activeKinds['journey_failed']) && $journeyOk === true) {
            $this->resolveOpenIncidentsByKind($monitoredUrl, 'journey_failed', $now);
        }
    }

    private function resolveOpenIncidentsByKind(MonitoredUrl $monitoredUrl, string $kind, \DateTimeInterface $resolvedAt): void
    {
        Incident::query()
            ->where('tenant_id', $monitoredUrl->tenant_id)
            ->where('monitored_url_id', $monitoredUrl->id)
            ->where('kind', $kind)
            ->whereIn('status', ['open', 'ack'])
            ->update([
                'status' => 'resolved',
                'resolved_at' => $resolvedAt,
                'updated_at' => $resolvedAt,
            ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function detectTlsAndDnsIncidents(MonitoredUrl $monitoredUrl, UrlSnapshot $snapshot, ?UrlSnapshot $previousSnapshot, ?UrlSnapshot $baselineSnapshot): array
    {
        $incidents = [];

        $tls = is_array($snapshot->meta['tls'] ?? null) ? $snapshot->meta['tls'] : null;
        $dns = is_array($snapshot->meta['dns'] ?? null) ? $snapshot->meta['dns'] : null;

        $tlsReference = null;
        $dnsReference = null;

        if ($baselineSnapshot) {
            if (is_array($baselineSnapshot->meta['tls'] ?? null)) {
                $tlsReference = $baselineSnapshot->meta['tls'];
            }
            if (is_array($baselineSnapshot->meta['dns'] ?? null)) {
                $dnsReference = $baselineSnapshot->meta['dns'];
            }
        }

        if (! $tlsReference && $previousSnapshot && is_array($previousSnapshot->meta['tls'] ?? null)) {
            $tlsReference = $previousSnapshot->meta['tls'];
        }

        if (! $dnsReference && $previousSnapshot && is_array($previousSnapshot->meta['dns'] ?? null)) {
            $dnsReference = $previousSnapshot->meta['dns'];
        }

        if (is_array($tls)) {
            $fingerprint = is_string($tls['fingerprint_sha256'] ?? null) ? (string) $tls['fingerprint_sha256'] : null;
            $validToIso = is_string($tls['valid_to'] ?? null) ? (string) $tls['valid_to'] : null;

            if (is_array($tlsReference)) {
                $refFingerprint = is_string($tlsReference['fingerprint_sha256'] ?? null) ? (string) $tlsReference['fingerprint_sha256'] : null;

                if ($fingerprint !== null && $refFingerprint !== null && $fingerprint !== $refFingerprint) {
                    $incidents[] = [
                        'tenant_id' => $monitoredUrl->tenant_id,
                        'monitored_url_id' => $monitoredUrl->id,
                        'previous_snapshot_id' => $baselineSnapshot?->id ?? $previousSnapshot?->id,
                        'current_snapshot_id' => $snapshot->id,
                        'kind' => 'tls_changed',
                        'fingerprint' => $this->computeFingerprint('tls_changed', [
                            $baselineSnapshot?->id ?? 0,
                            $fingerprint,
                        ]),
                        'severity' => 65,
                        'status' => 'open',
                        'summary' => 'TLS certificate changed',
                        'details' => [
                            'reference_fingerprint_sha256' => $refFingerprint,
                            'current_fingerprint_sha256' => $fingerprint,
                            'reference_tls' => $tlsReference,
                            'current_tls' => $tls,
                            'baseline_snapshot_id' => $baselineSnapshot?->id,
                        ],
                        'detected_at' => $snapshot->fetched_at,
                        'last_seen_at' => $snapshot->fetched_at,
                    ];
                }
            }

            if ($validToIso) {
                try {
                    $validTo = \Carbon\CarbonImmutable::parse($validToIso);
                    $daysLeft = now()->diffInDays($validTo, false);

                    $thresholdDays = (int) config('wids.tls.expiring_days', 14);
                    $thresholdDays = min(365, max(1, $thresholdDays));

                    if ($daysLeft <= $thresholdDays) {
                        $severity = 70;

                        if ($daysLeft <= 0) {
                            $severity = 100;
                        } elseif ($daysLeft <= 3) {
                            $severity = 95;
                        } elseif ($daysLeft <= 7) {
                            $severity = 85;
                        }

                        $incidents[] = [
                            'tenant_id' => $monitoredUrl->tenant_id,
                            'monitored_url_id' => $monitoredUrl->id,
                            'previous_snapshot_id' => $previousSnapshot?->id,
                            'current_snapshot_id' => $snapshot->id,
                            'kind' => 'tls_expiring',
                            'fingerprint' => $this->computeFingerprint('tls_expiring', [
                                $fingerprint ?? '',
                                $thresholdDays,
                            ]),
                            'severity' => $severity,
                            'status' => 'open',
                            'summary' => 'TLS certificate expiring soon',
                            'details' => [
                                'days_left' => $daysLeft,
                                'valid_to' => $validToIso,
                                'threshold_days' => $thresholdDays,
                                'fingerprint_sha256' => $fingerprint,
                                'tls' => $tls,
                            ],
                            'detected_at' => $snapshot->fetched_at,
                            'last_seen_at' => $snapshot->fetched_at,
                        ];
                    }
                } catch (\Throwable) {
                    // ignore parse errors
                }
            }
        }

        if (is_array($dns) && is_array($dnsReference)) {
            $current = [
                'a' => $dns['a'] ?? [],
                'aaaa' => $dns['aaaa'] ?? [],
                'cname' => $dns['cname'] ?? [],
            ];

            $reference = [
                'a' => $dnsReference['a'] ?? [],
                'aaaa' => $dnsReference['aaaa'] ?? [],
                'cname' => $dnsReference['cname'] ?? [],
            ];

            $currentJson = json_encode($current, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $referenceJson = json_encode($reference, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if (is_string($currentJson) && is_string($referenceJson) && $currentJson !== $referenceJson) {
                $incidents[] = [
                    'tenant_id' => $monitoredUrl->tenant_id,
                    'monitored_url_id' => $monitoredUrl->id,
                    'previous_snapshot_id' => $baselineSnapshot?->id ?? $previousSnapshot?->id,
                    'current_snapshot_id' => $snapshot->id,
                    'kind' => 'dns_change',
                    'fingerprint' => $this->computeFingerprint('dns_change', [
                        $baselineSnapshot?->id ?? 0,
                        $current,
                    ]),
                    'severity' => 45,
                    'status' => 'open',
                    'summary' => 'DNS records changed',
                    'details' => [
                        'reference_dns' => $reference,
                        'current_dns' => $current,
                        'baseline_snapshot_id' => $baselineSnapshot?->id,
                    ],
                    'detected_at' => $snapshot->fetched_at,
                    'last_seen_at' => $snapshot->fetched_at,
                ];
            }
        }

        return $incidents;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function detectContentRuleIncidents(MonitoredUrl $monitoredUrl, UrlSnapshot $snapshot, ?UrlSnapshot $baselineSnapshot, ?string $body): array
    {
        $rules = $monitoredUrl->content_rules;

        if (! is_array($rules) || $rules === []) {
            return [];
        }

        $enabled = $rules['enabled'] ?? true;
        if ($enabled === false) {
            return [];
        }

        if (! is_string($body) || $body === '') {
            return [];
        }

        $denyKeywords = $rules['deny_keywords'] ?? $rules['denyKeywords'] ?? [];
        $allowKeywords = $rules['allow_keywords'] ?? $rules['allowKeywords'] ?? [];
        $denyRegexes = $rules['deny_regexes'] ?? $rules['deny_regex'] ?? [];
        $allowRegexes = $rules['allow_regexes'] ?? $rules['allow_regex'] ?? [];

        $mode = is_string($rules['mode'] ?? null) ? strtolower(trim((string) $rules['mode'])) : null;

        if ($mode === 'status_page' || $mode === 'status-page') {
            if ((! is_array($denyKeywords) || $denyKeywords === []) && (! is_array($denyRegexes) || $denyRegexes === [])) {
                $denyKeywords = [
                    'incident',
                    'outage',
                    'service disruption',
                    'degraded performance',
                    'partial outage',
                    'major outage',
                ];
            }
        }

        $violations = [];
        $severity = 0;

        if (is_array($denyKeywords)) {
            foreach (array_slice($denyKeywords, 0, 50) as $kw) {
                if (! is_string($kw) || trim($kw) === '') {
                    continue;
                }

                if (stripos($body, $kw) !== false) {
                    $violations[] = ['type' => 'deny_keyword', 'value' => $kw];
                    $severity = max($severity, 80);
                }
            }
        }

        if (is_array($denyRegexes)) {
            foreach (array_slice($denyRegexes, 0, 50) as $regex) {
                if (! is_string($regex) || trim($regex) === '') {
                    continue;
                }

                $result = @preg_match($regex, $body);
                if ($result === 1) {
                    $violations[] = ['type' => 'deny_regex', 'value' => $regex];
                    $severity = max($severity, 85);
                }
            }
        }

        $allowKeywordMatched = null;
        if (is_array($allowKeywords) && $allowKeywords !== []) {
            $allowKeywordMatched = false;

            foreach (array_slice($allowKeywords, 0, 50) as $kw) {
                if (! is_string($kw) || trim($kw) === '') {
                    continue;
                }

                if (stripos($body, $kw) !== false) {
                    $allowKeywordMatched = true;
                    break;
                }
            }

            if ($allowKeywordMatched === false) {
                $violations[] = ['type' => 'allow_keyword_missing', 'allow_keywords' => array_values($allowKeywords)];
                $severity = max($severity, 55);
            }
        }

        $allowRegexMatched = null;
        if (is_array($allowRegexes) && $allowRegexes !== []) {
            $allowRegexMatched = false;

            foreach (array_slice($allowRegexes, 0, 50) as $regex) {
                if (! is_string($regex) || trim($regex) === '') {
                    continue;
                }

                $result = @preg_match($regex, $body);
                if ($result === 1) {
                    $allowRegexMatched = true;
                    break;
                }
            }

            if ($allowRegexMatched === false) {
                $violations[] = ['type' => 'allow_regex_missing', 'allow_regexes' => array_values($allowRegexes)];
                $severity = max($severity, 55);
            }
        }

        if ($violations === []) {
            return [];
        }

        $severity = min(100, max(1, $severity > 0 ? $severity : 60));

        return [[
            'tenant_id' => $monitoredUrl->tenant_id,
            'monitored_url_id' => $monitoredUrl->id,
            'previous_snapshot_id' => $baselineSnapshot?->id,
            'current_snapshot_id' => $snapshot->id,
            'kind' => 'content_rule_violation',
            'fingerprint' => $this->computeFingerprint('content_rule_violation', [
                $baselineSnapshot?->id ?? 0,
                $violations,
            ]),
            'severity' => $severity,
            'status' => 'open',
            'summary' => 'Content rule violation',
            'details' => [
                'violations' => $violations,
                'baseline_snapshot_id' => $baselineSnapshot?->id,
            ],
            'detected_at' => $snapshot->fetched_at,
            'last_seen_at' => $snapshot->fetched_at,
        ]];
    }

    /**
     * @return array{
     *   browser_console_error_min_count: int,
     *   browser_request_failed_min_count: int,
     *   browser_request_failed_ignore_patterns: list<string>
     * }
     */
    private function resolveIncidentNoiseControls(): array
    {
        $noiseControls = config('wids.incidents.noise_controls', []);
        $noiseControls = is_array($noiseControls) ? $noiseControls : [];

        $consoleMin = min(20, max(1, (int) ($noiseControls['browser_console_error_min_count'] ?? 2)));
        $requestFailedMin = min(20, max(1, (int) ($noiseControls['browser_request_failed_min_count'] ?? 2)));

        $ignorePatternsRaw = $noiseControls['browser_request_failed_ignore_patterns'] ?? [];
        $ignorePatternsRaw = is_array($ignorePatternsRaw) ? $ignorePatternsRaw : [];
        $ignorePatterns = [];

        foreach ($ignorePatternsRaw as $pattern) {
            if (! is_string($pattern)) {
                continue;
            }

            $normalized = trim($pattern);
            if ($normalized === '') {
                continue;
            }

            $ignorePatterns[] = $normalized;
        }

        return [
            'browser_console_error_min_count' => $consoleMin,
            'browser_request_failed_min_count' => $requestFailedMin,
            'browser_request_failed_ignore_patterns' => array_values(array_unique($ignorePatterns)),
        ];
    }

    /**
     * @param  mixed  $entry
     * @param  list<string>  $ignorePatterns
     */
    private function shouldIgnoreBrowserRequestFailure(mixed $entry, array $ignorePatterns): bool
    {
        if ($ignorePatterns === []) {
            return false;
        }

        $haystackParts = [];

        if (is_string($entry)) {
            $haystackParts[] = $entry;
        } elseif (is_array($entry)) {
            foreach ([
                'url',
                'error',
                'errorText',
                'failure',
                'failureText',
                'method',
                'resourceType',
            ] as $key) {
                $value = $entry[$key] ?? null;

                if (is_string($value) && trim($value) !== '') {
                    $haystackParts[] = $value;
                }
            }
        }

        if ($haystackParts === []) {
            return false;
        }

        $haystack = strtolower(implode(' | ', $haystackParts));

        foreach ($ignorePatterns as $pattern) {
            $needle = strtolower(trim((string) $pattern));

            if ($needle === '') {
                continue;
            }

            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string, mixed>>  $incidents
     * @return list<array<string, mixed>>
     */
    private function attachIncidentAlertContext(
        array $incidents,
        MonitoredUrl $monitoredUrl,
        UrlSnapshot $snapshot,
        bool $suppressContentSignals,
        int $alertAfterFailures,
    ): array {
        $consecutiveFailures = max(0, (int) ($monitoredUrl->consecutive_failures ?? 0));

        $alertGate = [
            'consecutive_failures' => $consecutiveFailures,
            'alert_after_failures' => $alertAfterFailures,
            'emitted_after_threshold' => $consecutiveFailures >= $alertAfterFailures,
        ];

        $scanContext = [
            'scan_mode' => is_string($monitoredUrl->scan_mode) ? $monitoredUrl->scan_mode : 'http',
            'http_status' => $snapshot->http_status,
            'response_time_ms' => $snapshot->response_time_ms,
            'final_url' => $snapshot->final_url,
            'suppress_content_signals' => $suppressContentSignals,
        ];

        foreach ($incidents as $index => $incident) {
            $details = is_array($incident['details'] ?? null) ? $incident['details'] : [];

            if (! isset($details['alert_gate']) || ! is_array($details['alert_gate'])) {
                $details['alert_gate'] = $alertGate;
            }

            if (! isset($details['scan_context']) || ! is_array($details['scan_context'])) {
                $details['scan_context'] = $scanContext;
            }

            $incident['details'] = $details;
            $incidents[$index] = $incident;
        }

        return $incidents;
    }

    private function computeFingerprint(string $kind, array $parts): string
    {
        $payload = $kind.'|'.implode('|', array_map(fn ($p) => is_scalar($p) ? (string) $p : json_encode($p), $parts));

        return hash('sha256', $payload);
    }

    private function hammingDistanceHex(string $hexA, string $hexB): ?int
    {
        $hexA = strtolower(trim($hexA));
        $hexB = strtolower(trim($hexB));

        if ($hexA === '' || $hexB === '' || strlen($hexA) !== strlen($hexB)) {
            return null;
        }

        $a = @hex2bin($hexA);
        $b = @hex2bin($hexB);

        if (! is_string($a) || ! is_string($b) || strlen($a) !== strlen($b)) {
            return null;
        }

        $xor = $a ^ $b;
        $len = strlen($xor);

        static $bitCounts = null;

        if ($bitCounts === null) {
            $bitCounts = [];
            for ($i = 0; $i < 256; $i++) {
                $bitCounts[$i] = substr_count(decbin($i), '1');
            }
        }

        $distance = 0;

        for ($i = 0; $i < $len; $i++) {
            $distance += $bitCounts[ord($xor[$i])];
        }

        return $distance;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function detectSuspiciousPatternMatch(?string $body): ?array
    {
        if (! is_string($body) || $body === '') {
            return null;
        }

        $maxBytes = (int) config('wids.suspicious_scan_max_body_bytes', 200_000);
        $contextBytes = (int) config('wids.suspicious_match_context_bytes', 240);
        $contextBytes = max(40, min(2000, $contextBytes));

        $truncated = false;
        $bodyForScan = $body;

        if ($maxBytes > 0 && strlen($bodyForScan) > $maxBytes) {
            $bodyForScan = substr($bodyForScan, 0, $maxBytes);
            $truncated = true;
        }

        $patterns = config('wids.suspicious_patterns', []);

        if (! is_array($patterns) || $patterns === []) {
            return null;
        }

        foreach ($patterns as $pattern) {
            if (! is_array($pattern)) {
                continue;
            }

            $regex = $pattern['regex'] ?? null;

            if (! is_string($regex) || $regex === '') {
                continue;
            }

            $matches = [];
            $result = @preg_match($regex, $bodyForScan, $matches, PREG_OFFSET_CAPTURE);

            if ($result !== 1) {
                continue;
            }

            $matchText = $matches[0][0] ?? null;
            $matchOffset = $matches[0][1] ?? null;

            if (! is_string($matchText) || ! is_int($matchOffset)) {
                continue;
            }

            $start = max(0, $matchOffset - intdiv($contextBytes, 2));
            $context = substr($bodyForScan, $start, $contextBytes);

            return [
                'pattern_id' => $pattern['id'] ?? null,
                'severity' => $pattern['severity'] ?? null,
                'summary' => $pattern['summary'] ?? null,
                'match' => $matchText,
                'match_offset' => $matchOffset,
                'match_context' => $context,
                'scanned_bytes' => strlen($bodyForScan),
                'body_truncated' => $truncated,
            ];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $resolvedTarget
     */
    private function applyDnsPinning($pendingRequest, array $resolvedTarget)
    {
        $isIpLiteral = (bool) ($resolvedTarget['is_ip_literal'] ?? false);

        if ($isIpLiteral) {
            return $pendingRequest;
        }

        if (! defined('CURLOPT_RESOLVE')) {
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
}
