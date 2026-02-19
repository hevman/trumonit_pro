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

class SendIncidentDiscordJob implements ShouldQueue
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
    ) {
        $this->onQueue('wids');
    }

    public function handle(): void
    {
        $enabled = (bool) config('wids.notifications.discord.enabled', false);

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
                Log::warning('Incident Discord delivery blocked by URL safety policy.', [
                    'incident_id' => $this->incidentId,
                    'webhook_url' => $this->webhookUrl,
                    'error' => $e->getMessage(),
                ]);

                return;
            }
        }

        $timeoutSeconds = (int) config('wids.notifications.discord.timeout_seconds', 10);
        $timeoutSeconds = min(60, max(2, $timeoutSeconds));

        $incident = Incident::query()
            ->with(['tenant', 'monitoredUrl'])
            ->find($this->incidentId);

        if (! $incident) {
            return;
        }

        $locale = NotificationLocale::resolve();
        $url = $incident->monitoredUrl?->url ?? '-';
        $panelUrl = rtrim((string) config('app.url'), '/').'/incidents/'.$incident->id;
        $title = trans('messages.notifications.incident_title', ['id' => $incident->id], $locale);
        $kindLabel = trans('messages.notifications.label_kind', [], $locale);
        $severityLabel = trans('messages.notifications.label_severity', [], $locale);
        $statusLabel = trans('messages.notifications.label_status', [], $locale);
        $urlLabel = trans('messages.notifications.label_url', [], $locale);
        $summaryLabel = trans('messages.notifications.label_summary', [], $locale);
        $panelLabel = trans('messages.notifications.label_panel', [], $locale);

        $content = $title."\n"
            .$kindLabel.': '.$incident->kind."\n"
            .$severityLabel.': '.$incident->severity."\n"
            .$statusLabel.': '.$incident->status."\n"
            .$urlLabel.': '.$url."\n"
            .$summaryLabel.': '.$incident->summary."\n"
            .$panelLabel.': '.$panelUrl;

        $response = Http::timeout($timeoutSeconds)
            ->acceptJson()
            ->post($webhookUrl, [
                'content' => $content,
            ]);

        if (! $response->successful()) {
            throw new \RuntimeException('Discord webhook failed (status='.$response->status().').');
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::warning('Incident Discord delivery failed.', [
            'incident_id' => $this->incidentId,
            'webhook_url' => $this->webhookUrl,
            'error' => $e->getMessage(),
        ]);
    }
}
