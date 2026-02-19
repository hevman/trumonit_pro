<?php

namespace App\Http\Controllers\Web;

use App\Exceptions\UnsafeUrlException;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\AuditLogService;
use App\Services\TenantNotificationChannelService;
use App\Services\UrlSafetyService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class NotificationChannelController extends Controller
{
    public function edit(Request $request)
    {
        $tenant = $request->user()?->tenant;
        abort_if(! $tenant, 400, 'User has no tenant.');

        $channels = $this->normalizeChannels(
            is_array($tenant->notification_channels) ? $tenant->notification_channels : []
        );

        $channelSummary = [
            [
                'key' => 'webhook',
                'label' => 'Webhook',
                'count' => count($channels['webhook_urls'] ?? []),
                'min_severity' => (int) ($channels['routing']['webhook_min_severity'] ?? 1),
                'payload_mode_key' => (bool) ($channels['webhook_human_readable_enabled'] ?? false)
                    ? 'human_machine'
                    : 'machine_only',
            ],
            [
                'key' => 'slack',
                'label' => 'Slack',
                'count' => count($channels['slack_webhook_urls'] ?? []),
                'min_severity' => (int) ($channels['routing']['slack_min_severity'] ?? 1),
            ],
            [
                'key' => 'teams',
                'label' => 'Teams',
                'count' => count($channels['teams_webhook_urls'] ?? []),
                'min_severity' => (int) ($channels['routing']['teams_min_severity'] ?? 1),
            ],
            [
                'key' => 'discord',
                'label' => 'Discord',
                'count' => count($channels['discord_webhook_urls'] ?? []),
                'min_severity' => (int) ($channels['routing']['discord_min_severity'] ?? 1),
            ],
            [
                'key' => 'email',
                'label' => 'Email',
                'count' => count($channels['email_recipients'] ?? []),
                'min_severity' => (int) ($channels['routing']['email_min_severity'] ?? 1),
            ],
        ];

        $configuredChannels = collect($channelSummary)->filter(fn (array $channel): bool => ($channel['count'] ?? 0) > 0)->count();
        $maxChannelEndpoints = max(1, max(array_column($channelSummary, 'count') ?: [0]));

        return view('app.notification-channels.edit', [
            'channels' => $channels,
            'channelSummary' => $channelSummary,
            'configuredChannelsCount' => $configuredChannels,
            'maxChannelEndpoints' => $maxChannelEndpoints,
        ]);
    }

    public function update(Request $request)
    {
        $tenant = $request->user()?->tenant;
        abort_if(! $tenant, 400, 'User has no tenant.');

        $data = $request->validate([
            'webhook_urls' => ['nullable', 'string', 'max:10000'],
            'slack_webhook_urls' => ['nullable', 'string', 'max:10000'],
            'teams_webhook_urls' => ['nullable', 'string', 'max:10000'],
            'discord_webhook_urls' => ['nullable', 'string', 'max:10000'],
            'email_recipients' => ['nullable', 'string', 'max:10000'],
            'webhook_human_readable_enabled' => ['nullable', 'boolean'],
            'webhook_min_severity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'slack_min_severity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'teams_min_severity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'discord_min_severity' => ['nullable', 'integer', 'min:1', 'max:100'],
            'email_min_severity' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $before = $this->normalizeChannels(
            is_array($tenant->notification_channels) ? $tenant->notification_channels : []
        );

        $channels = [
            'webhook_urls' => $this->parseHttpUrlList((string) ($data['webhook_urls'] ?? ''), 'webhook_urls'),
            'slack_webhook_urls' => $this->parseHttpUrlList((string) ($data['slack_webhook_urls'] ?? ''), 'slack_webhook_urls'),
            'teams_webhook_urls' => $this->parseHttpUrlList((string) ($data['teams_webhook_urls'] ?? ''), 'teams_webhook_urls'),
            'discord_webhook_urls' => $this->parseHttpUrlList((string) ($data['discord_webhook_urls'] ?? ''), 'discord_webhook_urls'),
            'email_recipients' => $this->parseEmailList((string) ($data['email_recipients'] ?? '')),
            'webhook_human_readable_enabled' => $this->sanitizeBoolean(
                $data['webhook_human_readable_enabled'] ?? null,
                (bool) config('wids.notifications.webhook.human_readable_enabled', false),
            ),
            'routing' => [
                'webhook_min_severity' => (int) ($data['webhook_min_severity'] ?? 1),
                'slack_min_severity' => (int) ($data['slack_min_severity'] ?? 1),
                'teams_min_severity' => (int) ($data['teams_min_severity'] ?? 1),
                'discord_min_severity' => (int) ($data['discord_min_severity'] ?? 1),
                'email_min_severity' => (int) ($data['email_min_severity'] ?? 1),
            ],
        ];

        $tenant->notification_channels = $channels;
        $tenant->save();

        app(TenantNotificationChannelService::class)->forgetTenant((int) $tenant->id);

        app(AuditLogService::class)->log(
            tenantId: (int) $tenant->id,
            actorUserId: $request->user()?->id,
            action: 'tenant.notification_channels_updated',
            auditableType: Tenant::class,
            auditableId: (int) $tenant->id,
            data: [
                'before' => $before,
                'after' => $channels,
            ],
            request: $request,
        );

        return redirect()
            ->route('notification-channels.edit')
            ->with('status', 'Notification channels updated.');
    }

    /**
     * @return array{
     *   webhook_urls: list<string>,
     *   slack_webhook_urls: list<string>,
     *   teams_webhook_urls: list<string>,
     *   discord_webhook_urls: list<string>,
     *   email_recipients: list<string>,
     *   webhook_human_readable_enabled: bool,
     *   routing: array{
     *     webhook_min_severity: int,
     *     slack_min_severity: int,
     *     teams_min_severity: int,
     *     discord_min_severity: int,
     *     email_min_severity: int
     *   }
     * }
     */
    private function normalizeChannels(array $channels): array
    {
        $routing = is_array($channels['routing'] ?? null) ? $channels['routing'] : [];

        return [
            'webhook_urls' => is_array($channels['webhook_urls'] ?? null) ? array_values($channels['webhook_urls']) : [],
            'slack_webhook_urls' => is_array($channels['slack_webhook_urls'] ?? null) ? array_values($channels['slack_webhook_urls']) : [],
            'teams_webhook_urls' => is_array($channels['teams_webhook_urls'] ?? null) ? array_values($channels['teams_webhook_urls']) : [],
            'discord_webhook_urls' => is_array($channels['discord_webhook_urls'] ?? null) ? array_values($channels['discord_webhook_urls']) : [],
            'email_recipients' => is_array($channels['email_recipients'] ?? null) ? array_values($channels['email_recipients']) : [],
            'webhook_human_readable_enabled' => $this->sanitizeBoolean(
                $channels['webhook_human_readable_enabled'] ?? null,
                (bool) config('wids.notifications.webhook.human_readable_enabled', false),
            ),
            'routing' => [
                'webhook_min_severity' => $this->sanitizeSeverity($routing['webhook_min_severity'] ?? null),
                'slack_min_severity' => $this->sanitizeSeverity($routing['slack_min_severity'] ?? null),
                'teams_min_severity' => $this->sanitizeSeverity($routing['teams_min_severity'] ?? null),
                'discord_min_severity' => $this->sanitizeSeverity($routing['discord_min_severity'] ?? null),
                'email_min_severity' => $this->sanitizeSeverity($routing['email_min_severity'] ?? null),
            ],
        ];
    }

    /**
     * @return list<string>
     */
    private function parseHttpUrlList(string $input, string $field): array
    {
        $parts = preg_split('/[\r\n,]+/', $input) ?: [];
        $values = [];

        foreach ($parts as $part) {
            $url = trim((string) $part);

            if ($url === '') {
                continue;
            }

            if (! preg_match('/\Ahttps?:\/\//i', $url)) {
                throw ValidationException::withMessages([
                    $field => ['Invalid URL: '.$url],
                ]);
            }

            if ((bool) config('wids.notifications.url_safety_enforced', true)) {
                try {
                    UrlSafetyService::assertSafeForNotificationEndpoint($url);
                } catch (UnsafeUrlException $e) {
                    throw ValidationException::withMessages([
                        $field => [$e->getMessage().' URL: '.$url],
                    ]);
                }
            }

            $values[$url] = true;
        }

        return array_values(array_keys($values));
    }

    /**
     * @return list<string>
     */
    private function parseEmailList(string $input): array
    {
        $parts = preg_split('/[\r\n,]+/', $input) ?: [];
        $values = [];

        foreach ($parts as $part) {
            $email = trim((string) $part);

            if ($email === '') {
                continue;
            }

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw ValidationException::withMessages([
                    'email_recipients' => ['Invalid email: '.$email],
                ]);
            }

            $values[strtolower($email)] = true;
        }

        return array_values(array_keys($values));
    }

    private function sanitizeSeverity(mixed $value): int
    {
        if (! is_numeric($value)) {
            return 1;
        }

        return min(100, max(1, (int) $value));
    }

    private function sanitizeBoolean(mixed $value, bool $default): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value === 1;
        }

        if (is_string($value)) {
            $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($normalized !== null) {
                return $normalized;
            }
        }

        return $default;
    }
}
