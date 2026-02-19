<?php

namespace App\Jobs;

use App\Mail\IncidentDetectedMail;
use App\Models\Incident;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendIncidentEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60, 300, 900];

    public function __construct(
        public readonly int $incidentId,
        public readonly string $recipient,
    ) {
        $this->onQueue('wids');
    }

    public function handle(): void
    {
        $enabled = (bool) config('wids.notifications.email.enabled', false);

        if (! $enabled) {
            return;
        }

        $recipient = trim($this->recipient);

        if ($recipient === '' || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $incident = Incident::query()
            ->with(['tenant', 'monitoredUrl', 'previousSnapshot', 'currentSnapshot'])
            ->find($this->incidentId);

        if (! $incident) {
            return;
        }

        Mail::to($recipient)->send(new IncidentDetectedMail($incident));
    }

    public function failed(\Throwable $e): void
    {
        Log::warning('Incident email delivery failed.', [
            'incident_id' => $this->incidentId,
            'recipient' => $this->recipient,
            'error' => $e->getMessage(),
        ]);
    }
}

