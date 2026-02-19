<?php

namespace App\Jobs;

use App\Exceptions\UnsafeUrlException;
use App\Models\Incident;
use App\Services\UrlSafetyService;
use App\Support\NotificationLocale;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendIncidentWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60, 300, 900];

    public function __construct(
        public readonly int $incidentId,
        public readonly string $webhookUrl,
        public readonly ?bool $humanReadableEnabled = null,
    ) {
        $this->onQueue('wids');
    }

    public function handle(): void
    {
        $enabled = (bool) config('wids.notifications.webhook.enabled', false);

        if (! $enabled) {
            return;
        }

        $webhookUrl = trim($this->webhookUrl);

        if ($webhookUrl === '' || ! preg_match('/\Ahttps?:\/\//i', $webhookUrl)) {
            return;
        }

        if ((bool) config('wids.notifications.url_safety_enforced', true)) {
            try {
                UrlSafetyService::assertSafeForNotificationEndpoint($webhookUrl);
            } catch (UnsafeUrlException $e) {
                Log::warning('Incident webhook delivery blocked by URL safety policy.', [
                    'incident_id' => $this->incidentId,
                    'webhook_url' => $this->webhookUrl,
                    'error' => $e->getMessage(),
                ]);

                return;
            }
        }

        $timeoutSeconds = (int) config('wids.notifications.webhook.timeout_seconds', 10);
        $timeoutSeconds = min(60, max(2, $timeoutSeconds));

        $incident = Incident::query()
            ->with(['tenant', 'monitoredUrl', 'previousSnapshot', 'currentSnapshot'])
            ->find($this->incidentId);

        if (! $incident) {
            return;
        }

        $locale = NotificationLocale::resolve();
        $payload = [
            'event' => 'incident.created',
            'sent_at' => now()->toISOString(),
            'incident' => [
                'id' => $incident->id,
                'tenant_id' => $incident->tenant_id,
                'tenant_slug' => $incident->tenant?->slug,
                'monitored_url_id' => $incident->monitored_url_id,
                'url' => $incident->monitoredUrl?->url,
                'display_name' => $incident->monitoredUrl?->display_name,
                'kind' => $incident->kind,
                'severity' => $incident->severity,
                'status' => $incident->status,
                'summary' => $incident->summary,
                'details' => $incident->details,
                'detected_at' => $incident->detected_at?->toISOString(),
                'last_seen_at' => $incident->last_seen_at?->toISOString(),
                'occurrences' => $incident->occurrences,
                'fingerprint' => $incident->fingerprint,
                'previous_snapshot_id' => $incident->previous_snapshot_id,
                'current_snapshot_id' => $incident->current_snapshot_id,
                'previous_snapshot' => $incident->previousSnapshot ? [
                    'fetched_at' => $incident->previousSnapshot->fetched_at?->toISOString(),
                    'http_status' => $incident->previousSnapshot->http_status,
                    'final_url' => $incident->previousSnapshot->final_url,
                    'body_sha256' => $incident->previousSnapshot->body_sha256,
                    'screenshot_phash' => $incident->previousSnapshot->screenshot_phash,
                ] : null,
                'current_snapshot' => $incident->currentSnapshot ? [
                    'fetched_at' => $incident->currentSnapshot->fetched_at?->toISOString(),
                    'http_status' => $incident->currentSnapshot->http_status,
                    'final_url' => $incident->currentSnapshot->final_url,
                    'body_sha256' => $incident->currentSnapshot->body_sha256,
                    'screenshot_phash' => $incident->currentSnapshot->screenshot_phash,
                ] : null,
            ],
        ];

        $humanReadableEnabled = $this->humanReadableEnabled;

        if ($humanReadableEnabled === null) {
            $humanReadableEnabled = (bool) config('wids.notifications.webhook.human_readable_enabled', false);
        }

        if ($humanReadableEnabled) {
            $title = trans('messages.notifications.incident_title', ['id' => $incident->id], $locale);
            $kindLabel = trans('messages.notifications.label_kind', [], $locale);
            $severityLabel = trans('messages.notifications.label_severity', [], $locale);
            $statusLabel = trans('messages.notifications.label_status', [], $locale);
            $urlLabel = trans('messages.notifications.label_url', [], $locale);
            $summaryLabel = trans('messages.notifications.label_summary', [], $locale);
            $panelLabel = trans('messages.notifications.label_panel', [], $locale);
            $panelUrl = rtrim((string) config('app.url'), '/').'/incidents/'.$incident->id;
            $url = $incident->monitoredUrl?->url ?? '-';
            $summary = $incident->summary ?? '';

            $payload['human_readable'] = [
                'locale' => $locale,
                'title' => $title,
                'text' => implode("\n", [
                    $title,
                    $kindLabel.': '.$incident->kind,
                    $severityLabel.': '.$incident->severity,
                    $statusLabel.': '.$incident->status,
                    $urlLabel.': '.$url,
                    $summaryLabel.': '.$summary,
                    $panelLabel.': '.$panelUrl,
                ]),
                'labels' => [
                    'kind' => $kindLabel,
                    'severity' => $severityLabel,
                    'status' => $statusLabel,
                    'url' => $urlLabel,
                    'summary' => $summaryLabel,
                    'panel' => $panelLabel,
                ],
                'values' => [
                    'kind' => $incident->kind,
                    'severity' => $incident->severity,
                    'status' => $incident->status,
                    'url' => $url,
                    'summary' => $summary,
                    'panel' => $panelUrl,
                ],
            ];
        }

        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (! is_string($json)) {
            return;
        }

        $headers = [
            'Content-Type' => 'application/json',
            'X-Saas-Monit-Event' => 'incident.created',
            'X-Saas-Monit-Delivery' => (string) $incident->id,
        ];

        $secret = config('wids.notifications.webhook.secret');

        if (is_string($secret) && $secret !== '') {
            $headers['X-Saas-Monit-Signature'] = hash_hmac('sha256', $json, $secret);
        }

        $response = Http::timeout($timeoutSeconds)
            ->withHeaders($headers)
            ->withBody($json, 'application/json')
            ->post($webhookUrl);

        if (! $response->successful()) {
            throw new \RuntimeException('Webhook request failed (status='.$response->status().').');
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::warning('Incident webhook delivery failed.', [
            'incident_id' => $this->incidentId,
            'webhook_url' => $this->webhookUrl,
            'error' => $e->getMessage(),
        ]);
    }
}
