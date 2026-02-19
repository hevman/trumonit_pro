<?php

return [
    'user_agent' => env('WIDS_USER_AGENT', 'TruMonit-wids/0.1'),

    'http' => [
        'max_redirects' => (int) env('WIDS_HTTP_MAX_REDIRECTS', 5),
        'allowed_ports' => array_values(array_filter(array_map('intval', array_map('trim', explode(',', (string) env('WIDS_ALLOWED_PORTS', '80,443')))))),
        'max_body_bytes' => (int) env('WIDS_HTTP_MAX_BODY_BYTES', 2_000_000),
    ],

    'rate_limits' => [
        'tenant_requests_per_minute' => (int) env('WIDS_TENANT_RPM', 120),
        'host_requests_per_minute' => (int) env('WIDS_HOST_RPM', 120),
    ],

    'api' => [
        'current_version' => env('WIDS_API_CURRENT_VERSION', 'v1'),
        'legacy_enabled' => env('WIDS_API_LEGACY_ENABLED', true),
        'rate_limits' => [
            'public_requests_per_minute' => (int) env('WIDS_API_PUBLIC_RPM', 60),
            'min_authenticated_requests_per_minute' => (int) env('WIDS_API_MIN_AUTH_RPM', 60),
            'tenant_rpm_share_percent' => (int) env('WIDS_API_TENANT_RPM_SHARE_PERCENT', 50),
            'per_user_share_percent' => (int) env('WIDS_API_PER_USER_SHARE_PERCENT', 50),
            'unlimited_tenant_requests_per_minute' => (int) env('WIDS_API_UNLIMITED_TENANT_RPM', 1200),
        ],
        'tokens' => [
            'ttl_minutes' => (int) env('WIDS_API_TOKEN_TTL_MINUTES', 43_200),
            'abilities' => array_values(array_filter(array_map('trim', explode(',', (string) env('WIDS_API_TOKEN_ABILITIES', 'wids.read,wids.write'))))),
        ],
    ],

    'limits' => [
        'max_monitored_urls_per_tenant' => (int) env('WIDS_LIMIT_MAX_URLS_PER_TENANT', 0),
        'storage_quota_mb' => (int) env('WIDS_LIMIT_STORAGE_QUOTA_MB', 0),
    ],

    'alerts' => [
        'default_after_failures' => (int) env('WIDS_ALERT_DEFAULT_AFTER_FAILURES', 2),
        'default_cooldown_minutes' => (int) env('WIDS_ALERT_DEFAULT_COOLDOWN_MINUTES', 10),
    ],

    'default_plan_tier' => env('WIDS_DEFAULT_PLAN_TIER', 'lite'),

    'plan_tiers' => [
        'lite' => [
            'max_monitored_urls' => (int) env('WIDS_PLAN_LITE_MAX_URLS', 50),
            'tenant_rpm' => (int) env('WIDS_PLAN_LITE_TENANT_RPM', 120),
            'storage_quota_mb' => (int) env('WIDS_PLAN_LITE_STORAGE_QUOTA_MB', 2048),
            'retention_days' => (int) env('WIDS_PLAN_LITE_RETENTION_DAYS', 30),
        ],
        'pro' => [
            'max_monitored_urls' => (int) env('WIDS_PLAN_PRO_MAX_URLS', 300),
            'tenant_rpm' => (int) env('WIDS_PLAN_PRO_TENANT_RPM', 600),
            'storage_quota_mb' => (int) env('WIDS_PLAN_PRO_STORAGE_QUOTA_MB', 10240),
            'retention_days' => (int) env('WIDS_PLAN_PRO_RETENTION_DAYS', 90),
        ],
        'enterprise' => [
            'max_monitored_urls' => (int) env('WIDS_PLAN_ENTERPRISE_MAX_URLS', 0),
            'tenant_rpm' => (int) env('WIDS_PLAN_ENTERPRISE_TENANT_RPM', 0),
            'storage_quota_mb' => (int) env('WIDS_PLAN_ENTERPRISE_STORAGE_QUOTA_MB', 0),
            'retention_days' => (int) env('WIDS_PLAN_ENTERPRISE_RETENTION_DAYS', 365),
        ],
    ],

    'queue' => [
        'scan' => [
            'high_queue' => env('WIDS_SCAN_QUEUE_HIGH', 'wids-high'),
            'default_queue' => env('WIDS_SCAN_QUEUE_DEFAULT', 'wids'),
            'low_queue' => env('WIDS_SCAN_QUEUE_LOW', 'wids-low'),
            'manual_queue' => env('WIDS_SCAN_MANUAL_QUEUE', 'wids-high'),
            'onboarding_queue' => env('WIDS_SCAN_ONBOARDING_QUEUE', 'wids-high'),
            'api_queue' => env('WIDS_SCAN_API_QUEUE', 'wids-high'),
            'high_priority_lag_seconds' => (int) env('WIDS_SCAN_PRIORITY_HIGH_LAG_SECONDS', 300),
            'enterprise_high_priority_lag_seconds' => (int) env('WIDS_SCAN_PRIORITY_ENTERPRISE_HIGH_LAG_SECONDS', 180),
            'high_priority_failures' => (int) env('WIDS_SCAN_PRIORITY_HIGH_FAILURES', 1),
            'low_priority_min_frequency_minutes' => (int) env('WIDS_SCAN_PRIORITY_LOW_FREQUENCY_MINUTES', 60),
            'low_priority_max_lag_seconds' => (int) env('WIDS_SCAN_PRIORITY_LOW_MAX_LAG_SECONDS', 120),
            'tenant_dispatch_cap_per_tick' => (int) env('WIDS_SCAN_TENANT_DISPATCH_CAP_PER_TICK', 250),
        ],

        'worker' => [
            'queue_order' => array_values(array_filter(array_map('trim', explode(',', (string) env('WIDS_WORKER_QUEUES', 'wids-high,wids,wids-low,default'))))),
        ],

        'autoscaling' => [
            'enabled' => env('WIDS_QUEUE_AUTOSCALING_ENABLED', true),
            'target_ready_jobs_per_worker' => (int) env('WIDS_QUEUE_AUTOSCALING_TARGET_READY_JOBS_PER_WORKER', 120),
            'min_workers' => (int) env('WIDS_QUEUE_AUTOSCALING_MIN_WORKERS', 1),
            'max_workers' => (int) env('WIDS_QUEUE_AUTOSCALING_MAX_WORKERS', 12),
            'current_workers' => (int) env('WIDS_QUEUE_AUTOSCALING_CURRENT_WORKERS', 1),
            'high_queue_weight' => (float) env('WIDS_QUEUE_AUTOSCALING_HIGH_WEIGHT', 1.8),
            'default_queue_weight' => (float) env('WIDS_QUEUE_AUTOSCALING_DEFAULT_WEIGHT', 1.0),
            'low_queue_weight' => (float) env('WIDS_QUEUE_AUTOSCALING_LOW_WEIGHT', 0.5),
            'auto_apply_enabled' => env('WIDS_QUEUE_AUTOSCALING_AUTO_APPLY_ENABLED', false),
            'auto_apply_cooldown_minutes' => (int) env('WIDS_QUEUE_AUTOSCALING_AUTO_APPLY_COOLDOWN_MINUTES', 15),
            'auto_apply_max_step' => (int) env('WIDS_QUEUE_AUTOSCALING_AUTO_APPLY_MAX_STEP', 2),
            'auto_apply_command_template' => env('WIDS_QUEUE_AUTOSCALING_AUTO_APPLY_COMMAND_TEMPLATE', 'docker compose up -d --scale queue={workers}'),
            'auto_apply_save_artifact' => env('WIDS_QUEUE_AUTOSCALING_AUTO_APPLY_SAVE_ARTIFACT', true),
        ],
    ],

    'multi_region' => [
        'enabled' => env('WIDS_MULTI_REGION_ENABLED', false),
        'regions' => array_values(array_filter(array_map('trim', explode(',', (string) env('WIDS_MULTI_REGION_REGIONS', 'us-east,eu-west,ap-southeast'))))),
        'max_regions_by_tier' => [
            'lite' => (int) env('WIDS_MULTI_REGION_LITE_MAX_REGIONS', 2),
            'pro' => (int) env('WIDS_MULTI_REGION_PRO_MAX_REGIONS', 2),
            'enterprise' => (int) env('WIDS_MULTI_REGION_ENTERPRISE_MAX_REGIONS', 3),
            'default' => (int) env('WIDS_MULTI_REGION_DEFAULT_MAX_REGIONS', 2),
        ],
        'quorum_failures' => (int) env('WIDS_MULTI_REGION_QUORUM_FAILURES', 2),
        'probe_timeout_seconds' => (int) env('WIDS_MULTI_REGION_PROBE_TIMEOUT_SECONDS', 8),
        'health_controls' => [
            'enabled' => env('WIDS_MULTI_REGION_HEALTH_ENABLED', true),
            'window_hours' => (int) env('WIDS_MULTI_REGION_HEALTH_WINDOW_HOURS', 24),
            'min_samples' => (int) env('WIDS_MULTI_REGION_HEALTH_MIN_SAMPLES', 12),
            'max_failure_ratio' => (float) env('WIDS_MULTI_REGION_HEALTH_MAX_FAILURE_RATIO', 0.75),
            'exclude_unhealthy_regions' => env('WIDS_MULTI_REGION_HEALTH_EXCLUDE_UNHEALTHY', true),
            'min_regions_after_filter' => (int) env('WIDS_MULTI_REGION_HEALTH_MIN_REGIONS', 2),
            'strict_min_regions' => env('WIDS_MULTI_REGION_HEALTH_STRICT_MIN_REGIONS', false),
            'cache_key_prefix' => env('WIDS_MULTI_REGION_HEALTH_CACHE_PREFIX', 'wids:multi-region:health'),
        ],
    ],

    'sso' => [
        'oidc' => [
            'enabled' => env('WIDS_SSO_OIDC_ENABLED', false),
            'http_timeout_seconds' => (int) env('WIDS_SSO_OIDC_HTTP_TIMEOUT_SECONDS', 8),
            'discovery_cache_seconds' => (int) env('WIDS_SSO_OIDC_DISCOVERY_CACHE_SECONDS', 600),
            'default_scopes' => env('WIDS_SSO_OIDC_DEFAULT_SCOPES', 'openid profile email'),
            'strict_issuer_host_match' => env('WIDS_SSO_OIDC_STRICT_ISSUER_HOST_MATCH', true),
        ],
        'saml' => [
            'enabled' => env('WIDS_SSO_SAML_ENABLED', false),
            'acs_binding' => env('WIDS_SSO_SAML_ACS_BINDING', 'urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST'),
            'nameid_format_default' => env('WIDS_SSO_SAML_NAMEID_FORMAT_DEFAULT', 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress'),
            'require_signed_assertion_default' => env('WIDS_SSO_SAML_REQUIRE_SIGNED_ASSERTION_DEFAULT', true),
            'require_signed_logout_request_default' => env('WIDS_SSO_SAML_REQUIRE_SIGNED_LOGOUT_REQUEST_DEFAULT', true),
            'require_signed_logout_response_default' => env('WIDS_SSO_SAML_REQUIRE_SIGNED_LOGOUT_RESPONSE_DEFAULT', false),
            'require_destination' => env('WIDS_SSO_SAML_REQUIRE_DESTINATION', true),
            'require_audience' => env('WIDS_SSO_SAML_REQUIRE_AUDIENCE', true),
            'clock_skew_seconds' => (int) env('WIDS_SSO_SAML_CLOCK_SKEW_SECONDS', 120),
        ],
    ],

    'scim' => [
        'enabled' => env('WIDS_SCIM_ENABLED', false),
        'token_prefix' => env('WIDS_SCIM_TOKEN_PREFIX', 'wids_scim'),
        'max_list_count' => (int) env('WIDS_SCIM_MAX_LIST_COUNT', 200),
        'bulk_enabled' => env('WIDS_SCIM_BULK_ENABLED', true),
        'bulk_max_operations' => (int) env('WIDS_SCIM_BULK_MAX_OPERATIONS', 100),
        'bulk_max_payload_bytes' => (int) env('WIDS_SCIM_BULK_MAX_PAYLOAD_BYTES', 1_048_576),
    ],

    'storage_lifecycle' => [
        'enabled' => env('WIDS_STORAGE_LIFECYCLE_ENABLED', false),
        'archive_prefix' => env('WIDS_STORAGE_ARCHIVE_PREFIX', 'wids/archive'),
        'chunk_size' => (int) env('WIDS_STORAGE_LIFECYCLE_CHUNK_SIZE', 300),
        'orphan_cleanup_enabled' => env('WIDS_STORAGE_ORPHAN_CLEANUP_ENABLED', false),
        'orphan_cleanup_grace_days' => (int) env('WIDS_STORAGE_ORPHAN_CLEANUP_GRACE_DAYS', 3),
        'orphan_cleanup_schedule_time' => env('WIDS_STORAGE_ORPHAN_CLEANUP_SCHEDULE_TIME', '03:10'),
        'archive_after_days_by_tier' => [
            'lite' => (int) env('WIDS_STORAGE_ARCHIVE_AFTER_DAYS_LITE', 7),
            'pro' => (int) env('WIDS_STORAGE_ARCHIVE_AFTER_DAYS_PRO', 14),
            'enterprise' => (int) env('WIDS_STORAGE_ARCHIVE_AFTER_DAYS_ENTERPRISE', 30),
            'default' => (int) env('WIDS_STORAGE_ARCHIVE_AFTER_DAYS_DEFAULT', 7),
        ],
    ],

    'snapshot_partition' => [
        'enabled' => env('WIDS_SNAPSHOT_PARTITION_ENABLED', false),
        'after_days' => (int) env('WIDS_SNAPSHOT_PARTITION_AFTER_DAYS', 30),
        'chunk_size' => (int) env('WIDS_SNAPSHOT_PARTITION_CHUNK_SIZE', 400),
        'purge_source' => env('WIDS_SNAPSHOT_PARTITION_PURGE_SOURCE', false),
        'schedule_time' => env('WIDS_SNAPSHOT_PARTITION_SCHEDULE_TIME', '03:40'),
    ],

    'private_probes' => [
        'enabled' => env('WIDS_PRIVATE_PROBES_ENABLED', false),
        'allowed_tiers' => array_values(array_filter(array_map('trim', explode(',', (string) env('WIDS_PRIVATE_PROBES_ALLOWED_TIERS', 'pro,enterprise'))))),
        'allowed_regions_by_tier' => [
            'lite' => array_values(array_filter(array_map('trim', explode(',', (string) env('WIDS_PRIVATE_PROBES_ALLOWED_REGIONS_LITE', '*'))))),
            'pro' => array_values(array_filter(array_map('trim', explode(',', (string) env('WIDS_PRIVATE_PROBES_ALLOWED_REGIONS_PRO', '*'))))),
            'enterprise' => array_values(array_filter(array_map('trim', explode(',', (string) env('WIDS_PRIVATE_PROBES_ALLOWED_REGIONS_ENTERPRISE', '*'))))),
            'default' => array_values(array_filter(array_map('trim', explode(',', (string) env('WIDS_PRIVATE_PROBES_ALLOWED_REGIONS_DEFAULT', '*'))))),
        ],
        'max_active_by_tier' => [
            'lite' => (int) env('WIDS_PRIVATE_PROBES_MAX_ACTIVE_LITE', 5),
            'pro' => (int) env('WIDS_PRIVATE_PROBES_MAX_ACTIVE_PRO', 30),
            'enterprise' => (int) env('WIDS_PRIVATE_PROBES_MAX_ACTIVE_ENTERPRISE', 0),
            'default' => (int) env('WIDS_PRIVATE_PROBES_MAX_ACTIVE_DEFAULT', 10),
        ],
        'fleet_policy' => [
            'auto_enforce_enabled' => env('WIDS_PRIVATE_PROBES_FLEET_AUTO_ENFORCE_ENABLED', false),
            'auto_enforce_schedule_time' => env('WIDS_PRIVATE_PROBES_FLEET_AUTO_ENFORCE_SCHEDULE_TIME', '02:55'),
            'stale_disable_hours' => (int) env('WIDS_PRIVATE_PROBES_FLEET_STALE_DISABLE_HOURS', 72),
            'event_history_limit' => (int) env('WIDS_PRIVATE_PROBES_FLEET_EVENT_HISTORY_LIMIT', 20),
        ],
        'stale_after_minutes' => (int) env('WIDS_PRIVATE_PROBES_STALE_AFTER_MINUTES', 10),
        'token_prefix' => env('WIDS_PRIVATE_PROBES_TOKEN_PREFIX', 'wids_probe'),
        'lease_seconds' => (int) env('WIDS_PRIVATE_PROBES_LEASE_SECONDS', 180),
        'result_signature_required' => env('WIDS_PRIVATE_PROBES_RESULT_SIGNATURE_REQUIRED', true),
        'monitor_tag' => env('WIDS_PRIVATE_PROBES_MONITOR_TAG', 'private_probe'),
        'pin_tag_prefix' => env('WIDS_PRIVATE_PROBES_PIN_TAG_PREFIX', 'probe-id:'),
        'region_tag_prefix' => env('WIDS_PRIVATE_PROBES_REGION_TAG_PREFIX', 'probe-region:'),
        'region_fallback' => env('WIDS_PRIVATE_PROBES_REGION_FALLBACK', true),
        'dispatch_max_per_tick' => (int) env('WIDS_PRIVATE_PROBES_DISPATCH_MAX_PER_TICK', 500),
        'dispatch_cap_per_tenant' => (int) env('WIDS_PRIVATE_PROBES_DISPATCH_CAP_PER_TENANT', 250),
        'dispatch_require_online' => env('WIDS_PRIVATE_PROBES_DISPATCH_REQUIRE_ONLINE', true),
    ],

    'billing' => [
        'enforcement_enabled' => env('WIDS_BILLING_ENFORCEMENT_ENABLED', false),
        'enforced_metric_key' => env('WIDS_BILLING_ENFORCED_METRIC_KEY', 'api_requests_total'),
        'payment' => [
            'provider' => env('WIDS_BILLING_PAYMENT_PROVIDER', 'manual'),
            'webhook_route_enabled' => env('WIDS_BILLING_WEBHOOK_ROUTE_ENABLED', true),
        ],
        'providers' => [
            'stripe' => [
                'checkout_base_url' => env('WIDS_BILLING_STRIPE_CHECKOUT_BASE_URL'),
                'webhook_secret' => env('WIDS_BILLING_STRIPE_WEBHOOK_SECRET'),
            ],
            'paddle' => [
                'checkout_base_url' => env('WIDS_BILLING_PADDLE_CHECKOUT_BASE_URL'),
                'webhook_secret' => env('WIDS_BILLING_PADDLE_WEBHOOK_SECRET'),
            ],
        ],

        'plan_tiers' => [
            'lite' => [
                'included' => [
                    'api_requests_total' => (int) env('WIDS_BILLING_LITE_INCLUDED_API_REQUESTS', 50_000),
                    'api_requests_write_total' => (int) env('WIDS_BILLING_LITE_INCLUDED_API_WRITES', 10_000),
                    'api_requests_4xx_total' => (int) env('WIDS_BILLING_LITE_INCLUDED_API_4XX', 5_000),
                    'api_requests_5xx_total' => (int) env('WIDS_BILLING_LITE_INCLUDED_API_5XX', 1_000),
                    'scans_total' => (int) env('WIDS_BILLING_LITE_INCLUDED_SCANS', 200_000),
                    'scans_error_total' => (int) env('WIDS_BILLING_LITE_INCLUDED_SCAN_ERRORS', 10_000),
                    'storage_bytes_added' => (int) env('WIDS_BILLING_LITE_INCLUDED_STORAGE_BYTES', 2_147_483_648),
                    'incidents_total' => (int) env('WIDS_BILLING_LITE_INCLUDED_INCIDENTS', 50_000),
                    'incidents_high_total' => (int) env('WIDS_BILLING_LITE_INCLUDED_HIGH_INCIDENTS', 10_000),
                ],
                'caps' => [
                    'api_requests_total' => (int) env('WIDS_BILLING_LITE_CAP_API_REQUESTS', 0),
                    'api_requests_write_total' => (int) env('WIDS_BILLING_LITE_CAP_API_WRITES', 0),
                    'api_requests_4xx_total' => (int) env('WIDS_BILLING_LITE_CAP_API_4XX', 0),
                    'api_requests_5xx_total' => (int) env('WIDS_BILLING_LITE_CAP_API_5XX', 0),
                    'scans_total' => (int) env('WIDS_BILLING_LITE_CAP_SCANS', 0),
                    'scans_error_total' => (int) env('WIDS_BILLING_LITE_CAP_SCAN_ERRORS', 0),
                    'storage_bytes_added' => (int) env('WIDS_BILLING_LITE_CAP_STORAGE_BYTES', 0),
                    'incidents_total' => (int) env('WIDS_BILLING_LITE_CAP_INCIDENTS', 0),
                    'incidents_high_total' => (int) env('WIDS_BILLING_LITE_CAP_HIGH_INCIDENTS', 0),
                ],
            ],
            'pro' => [
                'included' => [
                    'api_requests_total' => (int) env('WIDS_BILLING_PRO_INCLUDED_API_REQUESTS', 500_000),
                    'api_requests_write_total' => (int) env('WIDS_BILLING_PRO_INCLUDED_API_WRITES', 50_000),
                    'api_requests_4xx_total' => (int) env('WIDS_BILLING_PRO_INCLUDED_API_4XX', 20_000),
                    'api_requests_5xx_total' => (int) env('WIDS_BILLING_PRO_INCLUDED_API_5XX', 2_500),
                    'scans_total' => (int) env('WIDS_BILLING_PRO_INCLUDED_SCANS', 2_000_000),
                    'scans_error_total' => (int) env('WIDS_BILLING_PRO_INCLUDED_SCAN_ERRORS', 30_000),
                    'storage_bytes_added' => (int) env('WIDS_BILLING_PRO_INCLUDED_STORAGE_BYTES', 10_737_418_240),
                    'incidents_total' => (int) env('WIDS_BILLING_PRO_INCLUDED_INCIDENTS', 200_000),
                    'incidents_high_total' => (int) env('WIDS_BILLING_PRO_INCLUDED_HIGH_INCIDENTS', 20_000),
                ],
                'caps' => [
                    'api_requests_total' => (int) env('WIDS_BILLING_PRO_CAP_API_REQUESTS', 0),
                    'api_requests_write_total' => (int) env('WIDS_BILLING_PRO_CAP_API_WRITES', 0),
                    'api_requests_4xx_total' => (int) env('WIDS_BILLING_PRO_CAP_API_4XX', 0),
                    'api_requests_5xx_total' => (int) env('WIDS_BILLING_PRO_CAP_API_5XX', 0),
                    'scans_total' => (int) env('WIDS_BILLING_PRO_CAP_SCANS', 0),
                    'scans_error_total' => (int) env('WIDS_BILLING_PRO_CAP_SCAN_ERRORS', 0),
                    'storage_bytes_added' => (int) env('WIDS_BILLING_PRO_CAP_STORAGE_BYTES', 0),
                    'incidents_total' => (int) env('WIDS_BILLING_PRO_CAP_INCIDENTS', 0),
                    'incidents_high_total' => (int) env('WIDS_BILLING_PRO_CAP_HIGH_INCIDENTS', 0),
                ],
            ],
            'enterprise' => [
                'included' => [
                    'api_requests_total' => (int) env('WIDS_BILLING_ENTERPRISE_INCLUDED_API_REQUESTS', 0),
                    'api_requests_write_total' => (int) env('WIDS_BILLING_ENTERPRISE_INCLUDED_API_WRITES', 0),
                    'api_requests_4xx_total' => (int) env('WIDS_BILLING_ENTERPRISE_INCLUDED_API_4XX', 0),
                    'api_requests_5xx_total' => (int) env('WIDS_BILLING_ENTERPRISE_INCLUDED_API_5XX', 0),
                    'scans_total' => (int) env('WIDS_BILLING_ENTERPRISE_INCLUDED_SCANS', 0),
                    'scans_error_total' => (int) env('WIDS_BILLING_ENTERPRISE_INCLUDED_SCAN_ERRORS', 0),
                    'storage_bytes_added' => (int) env('WIDS_BILLING_ENTERPRISE_INCLUDED_STORAGE_BYTES', 0),
                    'incidents_total' => (int) env('WIDS_BILLING_ENTERPRISE_INCLUDED_INCIDENTS', 0),
                    'incidents_high_total' => (int) env('WIDS_BILLING_ENTERPRISE_INCLUDED_HIGH_INCIDENTS', 0),
                ],
                'caps' => [
                    'api_requests_total' => (int) env('WIDS_BILLING_ENTERPRISE_CAP_API_REQUESTS', 0),
                    'api_requests_write_total' => (int) env('WIDS_BILLING_ENTERPRISE_CAP_API_WRITES', 0),
                    'api_requests_4xx_total' => (int) env('WIDS_BILLING_ENTERPRISE_CAP_API_4XX', 0),
                    'api_requests_5xx_total' => (int) env('WIDS_BILLING_ENTERPRISE_CAP_API_5XX', 0),
                    'scans_total' => (int) env('WIDS_BILLING_ENTERPRISE_CAP_SCANS', 0),
                    'scans_error_total' => (int) env('WIDS_BILLING_ENTERPRISE_CAP_SCAN_ERRORS', 0),
                    'storage_bytes_added' => (int) env('WIDS_BILLING_ENTERPRISE_CAP_STORAGE_BYTES', 0),
                    'incidents_total' => (int) env('WIDS_BILLING_ENTERPRISE_CAP_INCIDENTS', 0),
                    'incidents_high_total' => (int) env('WIDS_BILLING_ENTERPRISE_CAP_HIGH_INCIDENTS', 0),
                ],
            ],
        ],
    ],

    'reports' => [
        'weekly_digest' => [
            'enabled' => env('WIDS_WEEKLY_DIGEST_ENABLED', true),
            'schedule_day_of_week' => (int) env('WIDS_WEEKLY_DIGEST_SCHEDULE_DOW', 1),
            'schedule_time' => env('WIDS_WEEKLY_DIGEST_SCHEDULE_TIME', '07:15'),
            'store_report' => env('WIDS_WEEKLY_DIGEST_STORE_REPORT', true),
            'report_pack_retention_days' => (int) env('WIDS_WEEKLY_DIGEST_REPORT_PACK_RETENTION_DAYS', 180),
        ],
    ],

    'monitor_templates' => [
        'status_page' => [
            'name' => 'Status page / marketing site',
            'description' => 'Homepage check with screenshot and status/outage keyword rules.',
            'defaults' => [
                'scan_mode' => 'http',
                'frequency_minutes' => 5,
                'timeout_seconds' => 20,
                'follow_redirects' => true,
                'expected_status_min' => 200,
                'expected_status_max' => 399,
                'appearance_min_distance' => 8,
                'alert_after_failures' => 2,
                'alert_cooldown_minutes' => 30,
                'content_rules' => [
                    'enabled' => true,
                    'mode' => 'status_page',
                ],
            ],
        ],
        'login_journey' => [
            'name' => 'Login page journey',
            'description' => 'Browser flow that checks if login form is visible.',
            'defaults' => [
                'scan_mode' => 'journey',
                'frequency_minutes' => 10,
                'timeout_seconds' => 45,
                'follow_redirects' => true,
                'expected_status_min' => 200,
                'expected_status_max' => 399,
                'appearance_min_distance' => 10,
                'alert_after_failures' => 2,
                'alert_cooldown_minutes' => 20,
                'journey_steps' => [
                    ['action' => 'assert_selector', 'selector' => 'input[type="password"]'],
                ],
            ],
        ],
        'api_health' => [
            'name' => 'API health endpoint',
            'description' => 'Fast health check with strict 2xx status expectations.',
            'defaults' => [
                'scan_mode' => 'http',
                'frequency_minutes' => 2,
                'timeout_seconds' => 15,
                'follow_redirects' => true,
                'expected_status_min' => 200,
                'expected_status_max' => 299,
                'alert_after_failures' => 2,
                'alert_cooldown_minutes' => 10,
                'content_rules' => [
                    'enabled' => true,
                    'allow_keywords' => ['ok'],
                ],
            ],
        ],
    ],

    'retention' => [
        'enabled' => env('WIDS_RETENTION_ENABLED', true),
        'days' => (int) env('WIDS_RETENTION_DAYS', 30),
    ],

    'screenshot' => [
        'enabled' => env('WIDS_SCREENSHOT_ENABLED', true),
        'service_url' => env('WIDS_SCREENSHOT_SERVICE_URL', 'http://playwright:3000'),
        'timeout_seconds' => (int) env('WIDS_SCREENSHOT_TIMEOUT_SECONDS', 30),
        'viewport_width' => (int) env('WIDS_SCREENSHOT_VIEWPORT_WIDTH', 1280),
        'viewport_height' => (int) env('WIDS_SCREENSHOT_VIEWPORT_HEIGHT', 720),
        'full_page' => env('WIDS_SCREENSHOT_FULL_PAGE', false),
        'wait_until' => env('WIDS_SCREENSHOT_WAIT_UNTIL', 'domcontentloaded'),
        'max_bytes' => (int) env('WIDS_SCREENSHOT_MAX_BYTES', 2_000_000),
    ],

    'appearance' => [
        'min_hamming_distance' => (int) env('WIDS_APPEARANCE_MIN_DISTANCE', 8),
    ],

    'tls' => [
        'expiring_days' => (int) env('WIDS_TLS_EXPIRING_DAYS', 14),
    ],

    'notifications' => [
        'locale' => env('WIDS_NOTIFICATIONS_LOCALE', ''),
        'respect_mute' => env('WIDS_NOTIFICATIONS_RESPECT_MUTE', true),
        'respect_maintenance' => env('WIDS_NOTIFICATIONS_RESPECT_MAINTENANCE', true),
        'respect_cooldown' => env('WIDS_NOTIFICATIONS_RESPECT_COOLDOWN', true),
        'global_fallback_enabled' => env('WIDS_NOTIFICATION_GLOBAL_FALLBACK_ENABLED', false),
        'tenant_cache_ttl_seconds' => (int) env('WIDS_NOTIFICATION_TENANT_CACHE_TTL_SECONDS', 60),
        'url_safety_enforced' => env('WIDS_NOTIFICATION_URL_SAFETY_ENFORCED', true),
        'url_safety_dns_strict' => env('WIDS_NOTIFICATION_URL_SAFETY_DNS_STRICT', true),

        'webhook' => [
            'enabled' => env('WIDS_WEBHOOK_ENABLED', false),
            'urls' => array_values(array_filter(array_map('trim', explode(',', (string) env('WIDS_WEBHOOK_URLS', ''))))),
            'secret' => env('WIDS_WEBHOOK_SECRET'),
            'human_readable_enabled' => env('WIDS_WEBHOOK_HUMAN_READABLE_ENABLED', false),
            'timeout_seconds' => (int) env('WIDS_WEBHOOK_TIMEOUT_SECONDS', 10),
        ],

        'slack' => [
            'enabled' => env('WIDS_SLACK_ENABLED', false),
            'webhook_urls' => array_values(array_filter(array_map('trim', explode(',', (string) env('WIDS_SLACK_WEBHOOK_URLS', ''))))),
            'timeout_seconds' => (int) env('WIDS_SLACK_TIMEOUT_SECONDS', 10),
        ],

        'teams' => [
            'enabled' => env('WIDS_TEAMS_ENABLED', false),
            'webhook_urls' => array_values(array_filter(array_map('trim', explode(',', (string) env('WIDS_TEAMS_WEBHOOK_URLS', ''))))),
            'timeout_seconds' => (int) env('WIDS_TEAMS_TIMEOUT_SECONDS', 10),
        ],

        'discord' => [
            'enabled' => env('WIDS_DISCORD_ENABLED', false),
            'webhook_urls' => array_values(array_filter(array_map('trim', explode(',', (string) env('WIDS_DISCORD_WEBHOOK_URLS', ''))))),
            'timeout_seconds' => (int) env('WIDS_DISCORD_TIMEOUT_SECONDS', 10),
        ],

        'email' => [
            'enabled' => env('WIDS_EMAIL_ENABLED', false),
            'to' => array_values(array_filter(array_map('trim', explode(',', (string) env('WIDS_EMAIL_TO', ''))))),
        ],
    ],

    'incidents' => [
        'auto_resolve' => [
            'enabled' => env('WIDS_AUTO_RESOLVE_ENABLED', true),
            'kinds' => array_values(array_filter(array_map('trim', explode(',', (string) env('WIDS_AUTO_RESOLVE_KINDS', 'fetch_error,unexpected_status,journey_failed'))))),
        ],
        'noise_controls' => [
            'browser_console_error_min_count' => (int) env('WIDS_INCIDENT_BROWSER_CONSOLE_MIN_COUNT', 2),
            'browser_request_failed_min_count' => (int) env('WIDS_INCIDENT_BROWSER_REQUEST_FAILED_MIN_COUNT', 2),
            'browser_request_failed_ignore_patterns' => array_values(array_filter(array_map('trim', explode(',', (string) env('WIDS_INCIDENT_BROWSER_REQUEST_FAILED_IGNORE_PATTERNS', 'net::ERR_ABORTED,source map,favicon.ico'))))),
        ],
    ],

    /**
     * Suspicious body patterns (regex) to detect common compromises / leaks / errors.
     *
     * Keep this conservative to avoid false positives. You can tune this per project via config/env.
     */
    'suspicious_patterns' => [
        [
            'id' => 'defacement_hacked_by',
            'regex' => '/hacked\\s+by/i',
            'severity' => 90,
            'summary' => 'Possible defacement detected',
        ],
        [
            'id' => 'defacement_defaced',
            'regex' => '/\\bdefaced\\b/i',
            'severity' => 80,
            'summary' => 'Possible defacement detected',
        ],
        [
            'id' => 'sql_error_output',
            'regex' => '/(SQLSTATE\\[[^\\]]+\\]|You have an error in your SQL syntax|ORA-\\d{5}|Unclosed quotation mark after the character string)/i',
            'severity' => 70,
            'summary' => 'Database error output detected',
        ],
        [
            'id' => 'php_error_output',
            'regex' => '/(Fatal error:|Parse error:|Warning:|Notice:|Uncaught (Exception|Error):|Stack trace:)/i',
            'severity' => 60,
            'summary' => 'Application error output detected',
        ],
    ],

    'suspicious_scan_max_body_bytes' => (int) env('WIDS_SUSPICIOUS_SCAN_MAX_BODY_BYTES', 200_000),
    'suspicious_match_context_bytes' => (int) env('WIDS_SUSPICIOUS_MATCH_CONTEXT_BYTES', 240),
];
