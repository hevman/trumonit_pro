<?php

namespace App\Mail;

use App\Models\Incident;
use App\Support\NotificationLocale;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class IncidentDetectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Incident $incident)
    {
    }

    public function build(): self
    {
        $locale = NotificationLocale::resolve();
        $subject = trans('messages.notifications.incident_mail_subject', [
            'id' => $this->incident->id,
            'kind' => $this->incident->kind,
            'severity' => $this->incident->severity,
        ], $locale);

        return $this->locale($locale)
            ->subject($subject)
            ->view('emails.incident-detected', [
                'incident' => $this->incident,
                'panelUrl' => rtrim((string) config('app.url'), '/').'/incidents/'.$this->incident->id,
            ]);
    }
}
