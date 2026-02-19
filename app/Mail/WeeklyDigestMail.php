<?php

namespace App\Mail;

use App\Support\NotificationLocale;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WeeklyDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $digest
     */
    public function __construct(public readonly array $digest)
    {
    }

    public function build(): self
    {
        $locale = NotificationLocale::resolve();
        $tenantName = (string) ($this->digest['tenant']['name'] ?? 'tenant');
        $start = (string) ($this->digest['window']['start'] ?? '');
        $end = (string) ($this->digest['window']['end'] ?? '');
        $subjectWindow = trim($start.' - '.$end, ' -');
        $subjectKey = $subjectWindow !== ''
            ? 'messages.notifications.weekly_digest_subject_window'
            : 'messages.notifications.weekly_digest_subject';
        $subject = trans($subjectKey, [
            'tenant' => $tenantName,
            'window' => $subjectWindow,
        ], $locale);
        $dashboardUrl = rtrim((string) config('app.url'), '/').'/dashboard';

        return $this->locale($locale)
            ->subject($subject)
            ->view('emails.weekly-digest', [
                'digest' => $this->digest,
                'dashboardUrl' => $dashboardUrl,
            ]);
    }
}
