<?php

namespace App\Services;

use App\Exceptions\UnsafeUrlException;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;

class TenantNotificationChannelService
{
    /**
     * @return array{
     *   webhook_urls: list<string>,
     *   slack_webhook_urls: list<string>,
     *   teams_webhook_urls: list<string>,
     *   discord_webhook_urls: list<string>,
     *   email_recipients: list<string>,
     *   webhook_human_readable_enabled: bool,
     *   webhook_min_severity: int,
     *   slack_min_severity: int,
     *   teams_min_severity: int,
     *   discord_min_severity: int,
     *   email_min_severity: int
     * }
     */
    public function channelsForTenantId(int $tenantId): array
    {
        $cacheTtlSeconds = min(300, max(0, (int) config('wids.notifications.tenant_cache_ttl_seconds', 60)));
        $cacheKey = 'wids:tenant:notification_channels:'.$tenantId.':v3';

        if ($cacheTtlSeconds <= 0) {
            return $this->buildChannels($tenantId);
        }

        return Cache::remember(
            $cacheKey,
            now()->addSeconds($cacheTtlSeconds),
            fn () => $this->buildChannels($tenantId)
        );
    }

    public function forgetTenant(int $tenantId): void
    {
        Cache::forget('wids:tenant:notification_channels:'.$tenantId.':v3');
    }

    /**
     * @return array{
     *   webhook_urls: list<string>,
     *   slack_webhook_urls: list<string>,
     *   teams_webhook_urls: list<string>,
     *   discord_webhook_urls: list<string>,
     *   email_recipients: list<string>,
     *   webhook_human_readable_enabled: bool,
     *   webhook_min_severity: int,
     *   slack_min_severity: int,
     *   teams_min_severity: int,
     *   discord_min_severity: int,
     *   email_min_severity: int
     * }
     */
    private function buildChannels(int $tenantId): array
    {
        $tenant = Tenant::query()
            ->select(['id', 'notification_channels'])
            ->find($tenantId);

        $channels = is_array($tenant?->notification_channels) ? $tenant->notification_channels : [];
        $routing = is_array($channels['routing'] ?? null) ? $channels['routing'] : [];

        $resolved = [
            'webhook_urls' => $this->sanitizeHttpUrlList($channels['webhook_urls'] ?? null),
            'slack_webhook_urls' => $this->sanitizeHttpUrlList($channels['slack_webhook_urls'] ?? null),
            'teams_webhook_urls' => $this->sanitizeHttpUrlList($channels['teams_webhook_urls'] ?? null),
            'discord_webhook_urls' => $this->sanitizeHttpUrlList($channels['discord_webhook_urls'] ?? null),
            'email_recipients' => $this->sanitizeEmailList($channels['email_recipients'] ?? null),
            'webhook_human_readable_enabled' => $this->sanitizeBoolean(
                $channels['webhook_human_readable_enabled'] ?? null,
                (bool) config('wids.notifications.webhook.human_readable_enabled', false),
            ),
            'webhook_min_severity' => $this->sanitizeSeverity($routing['webhook_min_severity'] ?? null),
            'slack_min_severity' => $this->sanitizeSeverity($routing['slack_min_severity'] ?? null),
            'teams_min_severity' => $this->sanitizeSeverity($routing['teams_min_severity'] ?? null),
            'discord_min_severity' => $this->sanitizeSeverity($routing['discord_min_severity'] ?? null),
            'email_min_severity' => $this->sanitizeSeverity($routing['email_min_severity'] ?? null),
        ];

        if ((bool) config('wids.notifications.global_fallback_enabled', false)) {
            if ($resolved['webhook_urls'] === []) {
                $resolved['webhook_urls'] = $this->sanitizeHttpUrlList(config('wids.notifications.webhook.urls', []));
            }

            if ($resolved['slack_webhook_urls'] === []) {
                $resolved['slack_webhook_urls'] = $this->sanitizeHttpUrlList(config('wids.notifications.slack.webhook_urls', []));
            }

            if ($resolved['teams_webhook_urls'] === []) {
                $resolved['teams_webhook_urls'] = $this->sanitizeHttpUrlList(config('wids.notifications.teams.webhook_urls', []));
            }

            if ($resolved['discord_webhook_urls'] === []) {
                $resolved['discord_webhook_urls'] = $this->sanitizeHttpUrlList(config('wids.notifications.discord.webhook_urls', []));
            }

            if ($resolved['email_recipients'] === []) {
                $resolved['email_recipients'] = $this->sanitizeEmailList(config('wids.notifications.email.to', []));
            }
        }

        return $resolved;
    }

    /**
     * @return list<string>
     */
    private function sanitizeHttpUrlList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $urls = [];

        foreach ($value as $item) {
            if (! is_string($item)) {
                continue;
            }

            $url = trim($item);

            if ($url === '' || ! preg_match('/\Ahttps?:\/\//i', $url)) {
                continue;
            }

            if ((bool) config('wids.notifications.url_safety_enforced', true)) {
                try {
                    UrlSafetyService::assertSafeForNotificationEndpoint($url);
                } catch (UnsafeUrlException) {
                    continue;
                }
            }

            $urls[$url] = true;
        }

        return array_values(array_keys($urls));
    }

    /**
     * @return list<string>
     */
    private function sanitizeEmailList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $emails = [];

        foreach ($value as $item) {
            if (! is_string($item)) {
                continue;
            }

            $email = trim($item);

            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $emails[strtolower($email)] = true;
        }

        return array_values(array_keys($emails));
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
