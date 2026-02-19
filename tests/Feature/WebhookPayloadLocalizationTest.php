<?php

namespace Tests\Feature;

use App\Jobs\SendIncidentWebhookJob;
use App\Models\Incident;
use App\Models\MonitoredUrl;
use App\Models\Tenant;
use App\Models\UrlSnapshot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WebhookPayloadLocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_payload_keeps_machine_contract_by_default(): void
    {
        config()->set('wids.notifications.url_safety_enforced', false);
        config()->set('wids.notifications.webhook.enabled', true);
        config()->set('wids.notifications.webhook.human_readable_enabled', false);
        config()->set('wids.notifications.slack.enabled', false);
        config()->set('wids.notifications.teams.enabled', false);
        config()->set('wids.notifications.discord.enabled', false);
        config()->set('wids.notifications.email.enabled', false);

        $incident = $this->createIncident();

        Http::fake([
            'https://hooks.example.com/*' => Http::response([], 200),
        ]);

        (new SendIncidentWebhookJob($incident->id, 'https://hooks.example.com/webhook'))->handle();

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            $payload = json_decode((string) $request->body(), true);

            if (! is_array($payload)) {
                return false;
            }

            return ($payload['event'] ?? null) === 'incident.created'
                && isset($payload['incident'])
                && ! array_key_exists('human_readable', $payload);
        });
    }

    public function test_webhook_payload_can_include_human_readable_localized_block(): void
    {
        config()->set('wids.notifications.url_safety_enforced', false);
        config()->set('wids.notifications.webhook.enabled', true);
        config()->set('wids.notifications.webhook.human_readable_enabled', true);
        config()->set('wids.notifications.locale', 'pl');
        config()->set('wids.notifications.slack.enabled', false);
        config()->set('wids.notifications.teams.enabled', false);
        config()->set('wids.notifications.discord.enabled', false);
        config()->set('wids.notifications.email.enabled', false);

        $incident = $this->createIncident();

        Http::fake([
            'https://hooks.example.com/*' => Http::response([], 200),
        ]);

        (new SendIncidentWebhookJob($incident->id, 'https://hooks.example.com/webhook'))->handle();

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            $payload = json_decode((string) $request->body(), true);

            if (! is_array($payload) || ! is_array($payload['human_readable'] ?? null)) {
                return false;
            }

            $human = $payload['human_readable'];
            $labels = is_array($human['labels'] ?? null) ? $human['labels'] : [];
            $values = is_array($human['values'] ?? null) ? $human['values'] : [];
            $text = (string) ($human['text'] ?? '');

            return ($human['locale'] ?? null) === 'pl'
                && ($labels['kind'] ?? null) === 'Typ'
                && ($labels['severity'] ?? null) === 'Waga'
                && ($labels['summary'] ?? null) === 'Podsumowanie'
                && ($values['kind'] ?? null) === 'unexpected_status'
                && ($values['severity'] ?? null) === 95
                && str_contains($text, '[TruMonit] Incydent #')
                && str_contains($text, 'Typ: unexpected_status')
                && str_contains($text, 'Waga: 95');
        });
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
            'body_sha256' => hash('sha256', 'webhook-payload'),
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
            'fingerprint' => hash('sha256', 'webhook-localized-payload'),
        ]);
    }
}
