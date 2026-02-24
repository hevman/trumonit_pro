<?php

return [
    'allowed_ips' => array_values(array_filter(array_map(
        static fn (string $ip): string => trim($ip),
        explode(',', (string) env('OPS_ALLOWED_IPS', ''))
    ))),

    'metrics' => [
        'enabled' => env('OPS_METRICS_ENABLED', false),
        'require_token' => env('OPS_METRICS_REQUIRE_TOKEN', true),
        'token' => env('OPS_METRICS_TOKEN'),
        'cache_ttl_seconds' => (int) env('OPS_METRICS_CACHE_TTL_SECONDS', 5),
    ],

    'business' => [
        'enabled' => env('OPS_BUSINESS_SUMMARY_ENABLED', false),
        'require_token' => env('OPS_BUSINESS_SUMMARY_REQUIRE_TOKEN', true),
        'token' => env('OPS_BUSINESS_SUMMARY_TOKEN', env('OPS_METRICS_TOKEN')),
        'cache_ttl_seconds' => (int) env('OPS_BUSINESS_SUMMARY_CACHE_TTL_SECONDS', 30),
        'paid_subscription_statuses' => array_values(array_filter(array_map(
            static fn (string $status): string => strtolower(trim($status)),
            explode(',', (string) env('OPS_BUSINESS_PAID_SUBSCRIPTION_STATUSES', 'active,trialing,manual_active,past_due'))
        ))),
    ],

    'rate_limits' => [
        'endpoints_per_minute' => (int) env('OPS_ENDPOINTS_RPM', 30),
    ],

    'drill' => [
        'default_window_hours' => (int) env('OPS_DRILL_DEFAULT_WINDOW_HOURS', 24),
        'queue_lag_warn_seconds' => (int) env('OPS_DRILL_QUEUE_LAG_WARN_SECONDS', 300),
        'queue_lag_fail_seconds' => (int) env('OPS_DRILL_QUEUE_LAG_FAIL_SECONDS', 900),
        'queue_wait_warn_ms' => (int) env('OPS_DRILL_QUEUE_WAIT_WARN_MS', 2000),
        'queue_wait_fail_ms' => (int) env('OPS_DRILL_QUEUE_WAIT_FAIL_MS', 10000),
        'storage_usage_warn_percent' => (float) env('OPS_DRILL_STORAGE_USAGE_WARN_PERCENT', 85.0),
        'storage_usage_fail_percent' => (float) env('OPS_DRILL_STORAGE_USAGE_FAIL_PERCENT', 100.0),
        'private_probe_stale_warn_ratio' => (float) env('OPS_DRILL_PRIVATE_PROBE_STALE_WARN_RATIO', 0.4),
        'private_probe_stale_fail_ratio' => (float) env('OPS_DRILL_PRIVATE_PROBE_STALE_FAIL_RATIO', 0.7),
    ],

    'tuning' => [
        'env_file' => env('OPS_TUNE_ENV_FILE', '.env'),
        'backup_dir' => env('OPS_TUNE_BACKUP_DIR', 'ops-drills/env-backups'),
    ],

    'ownership' => [
        'service_owner' => env('OPS_SERVICE_OWNER', ''),
        'primary_on_call' => env('OPS_PRIMARY_ON_CALL', ''),
        'secondary_on_call' => env('OPS_SECONDARY_ON_CALL', ''),
        'incident_commander' => env('OPS_INCIDENT_COMMANDER', ''),
    ],

    'escalation' => [
        'channels' => [
            'sev1' => env('OPS_ESCALATION_CHANNEL_SEV1', ''),
            'sev2' => env('OPS_ESCALATION_CHANNEL_SEV2', ''),
            'status_updates' => env('OPS_ESCALATION_CHANNEL_STATUS', ''),
        ],
        'targets' => [
            'customer_updates' => env('OPS_ESCALATION_TARGET_CUSTOMER_UPDATES', ''),
            'internal_updates' => env('OPS_ESCALATION_TARGET_INTERNAL_UPDATES', ''),
        ],
    ],
];
