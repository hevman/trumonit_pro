<?php
/**
 * Lightweight WP smoke checks for plugin flow wiring.
 *
 * Run inside WordPress context, for example:
 * wp eval-file wp-content/plugins/trumonit-monitor/tests/wp-flow-smoke.php
 */

if (!defined('ABSPATH')) {
    fwrite(STDERR, "Run this file inside WordPress (WP-CLI eval-file).\n");
    exit(1);
}

$checks = [
    'admin_post_tm_connect' => 'handle_connect',
    'admin_post_tm_api_login' => 'handle_api_login',
    'admin_post_tm_disconnect' => 'handle_disconnect',
    'admin_post_tm_refresh_summary' => 'handle_refresh_summary',
    'admin_post_tm_run_scan' => 'handle_run_scan',
];

$errors = [];

if (!class_exists('TruMonit_Monitor_Plugin')) {
    $errors[] = 'TruMonit_Monitor_Plugin class is not loaded.';
}

foreach ($checks as $hook => $method) {
    if (has_action($hook) === false) {
        $errors[] = sprintf('Missing action hook: %s', $hook);
    }
}

$serviceUrl = get_option('tm_service_url', '');
if ($serviceUrl !== '' && !is_string($serviceUrl)) {
    $errors[] = 'tm_service_url option has invalid type.';
}

$summary = get_option('tm_last_summary', []);
if (!is_array($summary)) {
    $errors[] = 'tm_last_summary option must be an array.';
}

if ($errors !== []) {
    foreach ($errors as $error) {
        fwrite(STDERR, '[FAIL] ' . $error . "\n");
    }
    exit(1);
}

fwrite(STDOUT, "TruMonit plugin smoke checks: OK\n");
exit(0);
