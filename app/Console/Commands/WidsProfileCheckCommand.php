<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class WidsProfileCheckCommand extends Command
{
    protected $signature = 'wids:profile-check {--format=text : text|json}';

    protected $description = 'Validate SMB-first security and ops guardrails for TruMonit';

    public function handle(): int
    {
        $format = strtolower(trim((string) $this->option('format')));
        $format = in_array($format, ['text', 'json'], true) ? $format : 'text';

        $report = $this->buildReport();

        if ($format === 'json') {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->renderTextReport($report);
        }

        $errors = (int) ($report['summary']['errors'] ?? 0);

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array{
     *   profile: string,
     *   generated_at: string,
     *   summary: array{status: string, passed: int, failed: int, warnings: int, errors: int},
     *   checks: list<array{
     *      key: string,
     *      status: string,
     *      severity: string,
     *      message: string,
     *      current: mixed,
     *      expected: mixed,
     *      recommendation: string
     *   }>
     * }
     */
    private function buildReport(): array
    {
        $checks = [];

        $abilities = config('wids.api.tokens.abilities', []);
        $abilities = is_array($abilities) ? array_values(array_map('strval', $abilities)) : [];
        $hasWildcard = in_array('*', $abilities, true);
        $hasRead = in_array('wids.read', $abilities, true);
        $hasWrite = in_array('wids.write', $abilities, true);

        $checks[] = $this->check(
            key: 'api_token_wildcard_disabled',
            condition: ! $hasWildcard,
            severity: 'error',
            passMessage: 'API token abilities do not use wildcard.',
            failMessage: 'API token abilities include wildcard access.',
            current: $abilities,
            expected: ['wids.read', 'wids.write'],
            recommendation: 'Set WIDS_API_TOKEN_ABILITIES=wids.read,wids.write.'
        );

        $checks[] = $this->check(
            key: 'api_token_has_read_write',
            condition: $hasRead && $hasWrite,
            severity: 'error',
            passMessage: 'API token abilities include read and write scopes.',
            failMessage: 'API token abilities are missing wids.read or wids.write.',
            current: $abilities,
            expected: ['wids.read', 'wids.write'],
            recommendation: 'Ensure both scopes are present in WIDS_API_TOKEN_ABILITIES.'
        );

        $urlSafetyEnforced = (bool) config('wids.notifications.url_safety_enforced', true);
        $checks[] = $this->check(
            key: 'notification_url_safety_enforced',
            condition: $urlSafetyEnforced,
            severity: 'error',
            passMessage: 'Notification URL safety guard is enabled.',
            failMessage: 'Notification URL safety guard is disabled.',
            current: $urlSafetyEnforced,
            expected: true,
            recommendation: 'Set WIDS_NOTIFICATION_URL_SAFETY_ENFORCED=true.'
        );

        $dnsStrict = (bool) config('wids.notifications.url_safety_dns_strict', true);
        $checks[] = $this->check(
            key: 'notification_dns_strict_enabled',
            condition: $dnsStrict,
            severity: 'error',
            passMessage: 'Notification DNS strict validation is enabled.',
            failMessage: 'Notification DNS strict validation is disabled.',
            current: $dnsStrict,
            expected: true,
            recommendation: 'Set WIDS_NOTIFICATION_URL_SAFETY_DNS_STRICT=true.'
        );

        $metricsRequireToken = (bool) config('ops.metrics.require_token', true);
        $checks[] = $this->check(
            key: 'ops_metrics_require_token_enabled',
            condition: $metricsRequireToken,
            severity: 'error',
            passMessage: 'Ops metrics require bearer token.',
            failMessage: 'Ops metrics do not require bearer token.',
            current: $metricsRequireToken,
            expected: true,
            recommendation: 'Set OPS_METRICS_REQUIRE_TOKEN=true.'
        );

        $metricsEnabled = (bool) config('ops.metrics.enabled', false);
        $metricsToken = trim((string) config('ops.metrics.token', ''));
        $metricsTokenPresent = ! $metricsEnabled || ! $metricsRequireToken || $metricsToken !== '';

        $checks[] = $this->check(
            key: 'ops_metrics_token_present_when_enabled',
            condition: $metricsTokenPresent,
            severity: 'error',
            passMessage: 'Ops metrics token requirement is satisfied.',
            failMessage: 'Ops metrics are enabled with required token, but token is empty.',
            current: [
                'enabled' => $metricsEnabled,
                'require_token' => $metricsRequireToken,
                'token_present' => $metricsToken !== '',
            ],
            expected: [
                'enabled' => true,
                'require_token' => true,
                'token_present' => true,
            ],
            recommendation: 'Set OPS_METRICS_TOKEN to a strong random value.'
        );

        $globalFallback = (bool) config('wids.notifications.global_fallback_enabled', false);
        $checks[] = $this->check(
            key: 'notification_global_fallback_disabled',
            condition: ! $globalFallback,
            severity: 'warning',
            passMessage: 'Global notification fallback is disabled (tenant-scoped routing only).',
            failMessage: 'Global notification fallback is enabled.',
            current: $globalFallback,
            expected: false,
            recommendation: 'Set WIDS_NOTIFICATION_GLOBAL_FALLBACK_ENABLED=false.'
        );

        $oidcEnabled = (bool) config('wids.sso.oidc.enabled', false);
        $samlEnabled = (bool) config('wids.sso.saml.enabled', false);
        $scimEnabled = (bool) config('wids.scim.enabled', false);
        $checks[] = $this->check(
            key: 'enterprise_toggles_off_for_smb',
            condition: ! $oidcEnabled && ! $samlEnabled && ! $scimEnabled,
            severity: 'warning',
            passMessage: 'Enterprise toggles are disabled (SMB-first runtime profile).',
            failMessage: 'At least one enterprise toggle is enabled (OIDC/SAML/SCIM).',
            current: [
                'oidc' => $oidcEnabled,
                'saml' => $samlEnabled,
                'scim' => $scimEnabled,
            ],
            expected: [
                'oidc' => false,
                'saml' => false,
                'scim' => false,
            ],
            recommendation: 'Keep SSO/SCIM disabled by default for SMB-first environments.'
        );

        $provider = strtolower(trim((string) config('wids.billing.payment.provider', 'manual')));
        $providerSecret = trim((string) config('wids.billing.providers.'.$provider.'.webhook_secret', ''));
        $providerSecretOk = $provider === 'manual' || $providerSecret !== '';

        $checks[] = $this->check(
            key: 'billing_provider_secret_present',
            condition: $providerSecretOk,
            severity: 'error',
            passMessage: 'Billing provider webhook secret requirement is satisfied.',
            failMessage: 'Billing provider is active but webhook secret is empty.',
            current: [
                'provider' => $provider,
                'secret_present' => $providerSecret !== '',
            ],
            expected: [
                'provider' => 'manual|provider-with-secret',
                'secret_present' => true,
            ],
            recommendation: 'Set WIDS_BILLING_'.strtoupper($provider).'_WEBHOOK_SECRET or use manual provider.'
        );

        $summary = $this->buildSummary($checks);

        return [
            'profile' => 'smb',
            'generated_at' => now()->toISOString(),
            'summary' => $summary,
            'checks' => $checks,
        ];
    }

    /**
     * @param  list<array{status:string,severity:string}>  $checks
     * @return array{status: string, passed: int, failed: int, warnings: int, errors: int}
     */
    private function buildSummary(array $checks): array
    {
        $passed = 0;
        $failed = 0;
        $warnings = 0;
        $errors = 0;

        foreach ($checks as $check) {
            $status = (string) ($check['status'] ?? 'pass');
            $severity = (string) ($check['severity'] ?? 'warning');

            if ($status === 'pass') {
                $passed++;

                continue;
            }

            $failed++;

            if ($severity === 'error') {
                $errors++;
            } else {
                $warnings++;
            }
        }

        $status = $errors > 0 ? 'fail' : ($warnings > 0 ? 'warn' : 'pass');

        return [
            'status' => $status,
            'passed' => $passed,
            'failed' => $failed,
            'warnings' => $warnings,
            'errors' => $errors,
        ];
    }

    /**
     * @param  mixed  $current
     * @param  mixed  $expected
     * @return array{
     *   key: string,
     *   status: string,
     *   severity: string,
     *   message: string,
     *   current: mixed,
     *   expected: mixed,
     *   recommendation: string
     * }
     */
    private function check(
        string $key,
        bool $condition,
        string $severity,
        string $passMessage,
        string $failMessage,
        mixed $current,
        mixed $expected,
        string $recommendation
    ): array {
        return [
            'key' => $key,
            'status' => $condition ? 'pass' : 'fail',
            'severity' => $severity,
            'message' => $condition ? $passMessage : $failMessage,
            'current' => $current,
            'expected' => $expected,
            'recommendation' => $condition ? '' : $recommendation,
        ];
    }

    /**
     * @param  array{
     *   profile: string,
     *   generated_at: string,
     *   summary: array{status: string, passed: int, failed: int, warnings: int, errors: int},
     *   checks: list<array{
     *      key: string,
     *      status: string,
     *      severity: string,
     *      message: string,
     *      current: mixed,
     *      expected: mixed,
     *      recommendation: string
     *   }>
     * }  $report
     */
    private function renderTextReport(array $report): void
    {
        $summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
        $status = strtoupper((string) ($summary['status'] ?? 'pass'));
        $errors = (int) ($summary['errors'] ?? 0);
        $warnings = (int) ($summary['warnings'] ?? 0);

        $this->info('wIDS SMB profile check');
        $this->line('Generated at: '.(string) ($report['generated_at'] ?? now()->toISOString()));
        $this->line(sprintf('Overall: %s (errors=%d, warnings=%d)', $status, $errors, $warnings));
        $this->line('');

        $checks = is_array($report['checks'] ?? null) ? $report['checks'] : [];

        foreach ($checks as $check) {
            $checkStatus = strtoupper((string) ($check['status'] ?? 'pass'));
            $severity = strtoupper((string) ($check['severity'] ?? 'warning'));
            $key = (string) ($check['key'] ?? '');
            $message = (string) ($check['message'] ?? '');
            $recommendation = (string) ($check['recommendation'] ?? '');

            $this->line(sprintf('- %s: %s [%s] - %s', $key, $checkStatus, $severity, $message));

            if ($checkStatus === 'FAIL' && $recommendation !== '') {
                $this->line('  fix: '.$recommendation);
            }
        }
    }
}
