<?php

namespace Tests\Feature;

use App\Jobs\SendIncidentDiscordJob;
use App\Jobs\SendIncidentSlackJob;
use App\Jobs\SendIncidentTeamsJob;
use App\Mail\IncidentDetectedMail;
use App\Mail\WeeklyDigestMail;
use App\Models\Incident;
use App\Models\MonitoredUrl;
use App\Models\Tenant;
use App\Models\UrlSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NotificationLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_incident_channel_payloads_use_configured_notification_locale(): void
    {
        config()->set('wids.notifications.url_safety_enforced', false);
        config()->set('wids.notifications.locale', 'pl');
        config()->set('wids.notifications.slack.enabled', false);
        config()->set('wids.notifications.teams.enabled', false);
        config()->set('wids.notifications.discord.enabled', false);

        $incident = $this->createIncident();

        config()->set('wids.notifications.slack.enabled', true);
        config()->set('wids.notifications.teams.enabled', true);
        config()->set('wids.notifications.discord.enabled', true);

        Http::fake([
            'https://hooks.example.com/*' => Http::response([], 200),
        ]);

        (new SendIncidentSlackJob($incident->id, 'https://hooks.example.com/slack'))->handle();
        (new SendIncidentTeamsJob($incident->id, 'https://hooks.example.com/teams'))->handle();
        (new SendIncidentDiscordJob($incident->id, 'https://hooks.example.com/discord'))->handle();

        Http::assertSentCount(3);

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== 'https://hooks.example.com/slack') {
                return false;
            }

            $payload = $request->data();
            $text = (string) ($payload['text'] ?? '');

            return str_contains($text, '[TruMonit] Incydent #')
                && str_contains($text, 'Typ: unexpected_status')
                && str_contains($text, 'Waga: 95')
                && str_contains($text, 'Podsumowanie: Unexpected HTTP status');
        });

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== 'https://hooks.example.com/teams') {
                return false;
            }

            $payload = $request->data();
            $text = (string) ($payload['text'] ?? '');
            $title = (string) ($payload['title'] ?? '');

            return str_contains($title, '[TruMonit] Incydent #')
                && str_contains($text, 'Typ: unexpected_status')
                && str_contains($text, 'Waga: 95')
                && str_contains($text, 'Podsumowanie: Unexpected HTTP status');
        });

        Http::assertSent(function (Request $request): bool {
            if ($request->url() !== 'https://hooks.example.com/discord') {
                return false;
            }

            $payload = $request->data();
            $content = (string) ($payload['content'] ?? '');

            return str_contains($content, '[TruMonit] Incydent #')
                && str_contains($content, 'Typ: unexpected_status')
                && str_contains($content, 'Waga: 95')
                && str_contains($content, 'Podsumowanie: Unexpected HTTP status');
        });
    }

    public function test_notification_mail_subjects_use_configured_locale(): void
    {
        config()->set('wids.notifications.locale', 'pl');
        app()->setLocale('en');

        $incident = $this->createIncident();

        $incidentMail = new IncidentDetectedMail($incident);
        $incidentMail->build();

        $this->assertSame('pl', $incidentMail->locale);
        $this->assertSame('[TruMonit] Incydent #'.$incident->id.' unexpected_status waga 95', (string) $incidentMail->subject);

        $digest = [
            'tenant' => ['name' => 'Acme'],
            'window' => ['start' => '2026-02-03', 'end' => '2026-02-09'],
        ];

        $weeklyDigestMail = new WeeklyDigestMail($digest);
        $weeklyDigestMail->build();

        $this->assertSame('pl', $weeklyDigestMail->locale);
        $this->assertSame('[TruMonit] Tygodniowy digest Acme (2026-02-03 - 2026-02-09)', (string) $weeklyDigestMail->subject);
    }

    private function createIncident(): Incident
    {
        $tenant = Tenant::query()->create([
            'name' => 'Acme',
            'slug' => 'acme',
        ]);

        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
        ]);

        $monitoredUrl = MonitoredUrl::query()->create([
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $owner->id,
            'url' => 'https://example.com',
            'display_name' => 'Example',
            'is_active' => true,
            'frequency_minutes' => 5,
            'timeout_seconds' => 30,
            'follow_redirects' => true,
            'expected_status_min' => 200,
            'expected_status_max' => 399,
        ]);

        $snapshot = UrlSnapshot::query()->create([
            'monitored_url_id' => $monitoredUrl->id,
            'fetched_at' => now(),
            'request_url' => $monitoredUrl->url,
            'final_url' => $monitoredUrl->url,
            'http_status' => 500,
            'headers' => [],
            'response_time_ms' => 123,
            'body_size' => 10,
            'body_sha256' => hash('sha256', 'payload'),
        ]);

        return Incident::query()->create([
            'tenant_id' => $tenant->id,
            'monitored_url_id' => $monitoredUrl->id,
            'previous_snapshot_id' => null,
            'current_snapshot_id' => $snapshot->id,
            'kind' => 'unexpected_status',
            'severity' => 95,
            'status' => 'open',
            'summary' => 'Unexpected HTTP status',
            'details' => ['http_status' => 500],
            'detected_at' => now(),
            'last_seen_at' => now(),
            'occurrences' => 1,
            'fingerprint' => hash('sha256', 'incident-notification-locale'),
        ]);
    }
}
