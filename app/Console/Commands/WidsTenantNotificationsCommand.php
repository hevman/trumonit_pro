<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\TenantNotificationChannelService;
use Illuminate\Console\Command;

class WidsTenantNotificationsCommand extends Command
{
    protected $signature = 'wids:tenant-notifications
        {tenant_id : Tenant ID}
        {--show : Show current tenant notification channels}
        {--effective : Include effective resolved channels (after fallback/defaults)}
        {--clear : Clear all tenant notification channels}
        {--webhook_urls= : Comma-separated incident webhook URLs}
        {--slack_webhook_urls= : Comma-separated Slack webhook URLs}
        {--teams_webhook_urls= : Comma-separated Teams webhook URLs}
        {--discord_webhook_urls= : Comma-separated Discord webhook URLs}
        {--email_recipients= : Comma-separated email recipients}
        {--webhook_human_readable= : Include localized human-readable block in webhook payload (true/false)}
        {--webhook_min_severity= : Minimum severity for webhook channel (1-100)}
        {--slack_min_severity= : Minimum severity for Slack channel (1-100)}
        {--teams_min_severity= : Minimum severity for Teams channel (1-100)}
        {--discord_min_severity= : Minimum severity for Discord channel (1-100)}
        {--email_min_severity= : Minimum severity for email channel (1-100)}';

    protected $description = 'Show or update tenant-scoped notification channels';

    public function handle(): int
    {
        $tenantId = (int) $this->argument('tenant_id');

        if ($tenantId < 1) {
            $this->error('Invalid tenant_id.');

            return self::FAILURE;
        }

        $tenant = Tenant::query()->find($tenantId);

        if (! $tenant) {
            $this->error('Tenant not found.');

            return self::FAILURE;
        }

        $channels = is_array($tenant->notification_channels) ? $tenant->notification_channels : [];
        $routing = is_array($channels['routing'] ?? null) ? $channels['routing'] : [];
        $channels = [
            'webhook_urls' => is_array($channels['webhook_urls'] ?? null) ? array_values($channels['webhook_urls']) : [],
            'slack_webhook_urls' => is_array($channels['slack_webhook_urls'] ?? null) ? array_values($channels['slack_webhook_urls']) : [],
            'teams_webhook_urls' => is_array($channels['teams_webhook_urls'] ?? null) ? array_values($channels['teams_webhook_urls']) : [],
            'discord_webhook_urls' => is_array($channels['discord_webhook_urls'] ?? null) ? array_values($channels['discord_webhook_urls']) : [],
            'email_recipients' => is_array($channels['email_recipients'] ?? null) ? array_values($channels['email_recipients']) : [],
            'webhook_human_readable_enabled' => $this->parseBooleanOrDefault(
                $channels['webhook_human_readable_enabled'] ?? null,
                (bool) config('wids.notifications.webhook.human_readable_enabled', false),
            ),
            'routing' => [
                'webhook_min_severity' => $this->parseSeverityOrDefault($routing['webhook_min_severity'] ?? null),
                'slack_min_severity' => $this->parseSeverityOrDefault($routing['slack_min_severity'] ?? null),
                'teams_min_severity' => $this->parseSeverityOrDefault($routing['teams_min_severity'] ?? null),
                'discord_min_severity' => $this->parseSeverityOrDefault($routing['discord_min_severity'] ?? null),
                'email_min_severity' => $this->parseSeverityOrDefault($routing['email_min_severity'] ?? null),
            ],
        ];

        $clear = (bool) $this->option('clear');
        $show = (bool) $this->option('show');
        $showEffective = (bool) $this->option('effective');
        $webhookOption = $this->option('webhook_urls');
        $slackOption = $this->option('slack_webhook_urls');
        $teamsOption = $this->option('teams_webhook_urls');
        $discordOption = $this->option('discord_webhook_urls');
        $emailOption = $this->option('email_recipients');
        $webhookHumanReadableOption = $this->option('webhook_human_readable');
        $webhookSeverityOption = $this->option('webhook_min_severity');
        $slackSeverityOption = $this->option('slack_min_severity');
        $teamsSeverityOption = $this->option('teams_min_severity');
        $discordSeverityOption = $this->option('discord_min_severity');
        $emailSeverityOption = $this->option('email_min_severity');

        $hasUpdate = $clear
            || $webhookOption !== null
            || $slackOption !== null
            || $teamsOption !== null
            || $discordOption !== null
            || $emailOption !== null
            || $webhookHumanReadableOption !== null
            || $webhookSeverityOption !== null
            || $slackSeverityOption !== null
            || $teamsSeverityOption !== null
            || $discordSeverityOption !== null
            || $emailSeverityOption !== null;

        if ($clear) {
            $channels = [
                'webhook_urls' => [],
                'slack_webhook_urls' => [],
                'teams_webhook_urls' => [],
                'discord_webhook_urls' => [],
                'email_recipients' => [],
                'webhook_human_readable_enabled' => (bool) config('wids.notifications.webhook.human_readable_enabled', false),
                'routing' => [
                    'webhook_min_severity' => 1,
                    'slack_min_severity' => 1,
                    'teams_min_severity' => 1,
                    'discord_min_severity' => 1,
                    'email_min_severity' => 1,
                ],
            ];
        }

        try {
            if ($webhookOption !== null) {
                $channels['webhook_urls'] = $this->parseHttpUrlList((string) $webhookOption, 'webhook_urls');
            }

            if ($slackOption !== null) {
                $channels['slack_webhook_urls'] = $this->parseHttpUrlList((string) $slackOption, 'slack_webhook_urls');
            }

            if ($teamsOption !== null) {
                $channels['teams_webhook_urls'] = $this->parseHttpUrlList((string) $teamsOption, 'teams_webhook_urls');
            }

            if ($discordOption !== null) {
                $channels['discord_webhook_urls'] = $this->parseHttpUrlList((string) $discordOption, 'discord_webhook_urls');
            }

            if ($emailOption !== null) {
                $channels['email_recipients'] = $this->parseEmailList((string) $emailOption);
            }

            if ($webhookHumanReadableOption !== null) {
                $channels['webhook_human_readable_enabled'] = $this->parseBoolean((string) $webhookHumanReadableOption, 'webhook_human_readable');
            }

            if ($webhookSeverityOption !== null) {
                $channels['routing']['webhook_min_severity'] = $this->parseSeverity((string) $webhookSeverityOption, 'webhook_min_severity');
            }

            if ($slackSeverityOption !== null) {
                $channels['routing']['slack_min_severity'] = $this->parseSeverity((string) $slackSeverityOption, 'slack_min_severity');
            }

            if ($teamsSeverityOption !== null) {
                $channels['routing']['teams_min_severity'] = $this->parseSeverity((string) $teamsSeverityOption, 'teams_min_severity');
            }

            if ($discordSeverityOption !== null) {
                $channels['routing']['discord_min_severity'] = $this->parseSeverity((string) $discordSeverityOption, 'discord_min_severity');
            }

            if ($emailSeverityOption !== null) {
                $channels['routing']['email_min_severity'] = $this->parseSeverity((string) $emailSeverityOption, 'email_min_severity');
            }
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($hasUpdate) {
            $tenant->notification_channels = $channels;
            $tenant->save();

            app(TenantNotificationChannelService::class)->forgetTenant($tenantId);

            $this->info('Tenant notification channels updated.');
        }

        if ($show || ! $hasUpdate) {
            $payload = [
                'tenant_id' => $tenant->id,
                'tenant_slug' => $tenant->slug,
                'notification_channels' => $channels,
            ];

            if ($showEffective) {
                $payload['effective_notification_channels'] = app(TenantNotificationChannelService::class)->channelsForTenantId($tenantId);
            }

            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');
        }

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function parseHttpUrlList(string $input, string $field): array
    {
        $parts = array_values(array_filter(array_map('trim', explode(',', $input)), fn ($v) => $v !== ''));

        $urls = [];

        foreach ($parts as $url) {
            if (! preg_match('/\Ahttps?:\/\//i', $url)) {
                throw new \InvalidArgumentException('Invalid URL in '.$field.': '.$url);
            }

            $urls[$url] = true;
        }

        return array_values(array_keys($urls));
    }

    /**
     * @return list<string>
     */
    private function parseEmailList(string $input): array
    {
        $parts = array_values(array_filter(array_map('trim', explode(',', $input)), fn ($v) => $v !== ''));

        $emails = [];

        foreach ($parts as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new \InvalidArgumentException('Invalid email in email_recipients: '.$email);
            }

            $emails[strtolower($email)] = true;
        }

        return array_values(array_keys($emails));
    }

    private function parseSeverityOrDefault(mixed $value): int
    {
        if (! is_numeric($value)) {
            return 1;
        }

        return min(100, max(1, (int) $value));
    }

    private function parseBooleanOrDefault(mixed $value, bool $default): bool
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

    private function parseSeverity(string $input, string $field): int
    {
        if (! is_numeric($input)) {
            throw new \InvalidArgumentException('Invalid severity in '.$field.': '.$input);
        }

        $severity = (int) $input;

        if ($severity < 1 || $severity > 100) {
            throw new \InvalidArgumentException('Invalid severity in '.$field.': '.$input);
        }

        return $severity;
    }

    private function parseBoolean(string $input, string $field): bool
    {
        $normalized = filter_var($input, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($normalized === null) {
            throw new \InvalidArgumentException('Invalid boolean in '.$field.': '.$input);
        }

        return $normalized;
    }
}
