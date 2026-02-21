<?php
/**
 * Plugin Name: TruMonit Monitor
 * Plugin URI: https://trumonit.pro
 * Description: Connects your WordPress site with TruMonit and shows monitor and incident health in wp-admin.
 * Version: 0.1.0
 * Requires at least: 6.0
 * Tested up to: 6.9
 * Requires PHP: 7.4
 * Author: TruMonit
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: trumonit-monitor
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/includes/Traits/CoreConfigTrait.php';
require_once __DIR__ . '/includes/Traits/ApiClientTrait.php';
require_once __DIR__ . '/includes/Traits/AdminActionsTrait.php';
require_once __DIR__ . '/includes/Traits/AdminPageTrait.php';

final class TruMonit_Monitor_Plugin
{
    private const PLUGIN_FILE = __FILE__;
    private const DEFAULT_SERVICE_URL = 'https://trumonit.pro';

    private const OPT_SERVICE_URL = 'tm_service_url';
    private const OPT_API_TOKEN = 'tm_api_token';
    private const OPT_LAST_SUMMARY = 'tm_last_summary';

    private const MENU_SLUG = 'trumonit-monitor';

    use TM_CoreConfigTrait;
    use TM_ApiClientTrait;
    use TM_AdminActionsTrait;
    use TM_AdminPageTrait;

    public static function init(): void
    {
        $self = new self();

        add_action('admin_menu', [$self, 'admin_menu']);
        add_action('admin_enqueue_scripts', [$self, 'admin_enqueue_scripts']);

        add_action('admin_post_tm_connect', [$self, 'handle_connect']);
        add_action('admin_post_tm_api_login', [$self, 'handle_api_login']);
        add_action('admin_post_tm_disconnect', [$self, 'handle_disconnect']);
        add_action('admin_post_tm_refresh_summary', [$self, 'handle_refresh_summary']);
        add_action('admin_post_tm_run_scan', [$self, 'handle_run_scan']);
    }

    public static function activate(): void
    {
        $self = new self();
        $existing = (string) get_option(self::OPT_SERVICE_URL, '');

        if ($existing === '') {
            update_option(self::OPT_SERVICE_URL, self::DEFAULT_SERVICE_URL);
            return;
        }

        $normalized = $self->sanitize_service_url($existing);
        if ($normalized !== $existing) {
            update_option(self::OPT_SERVICE_URL, $normalized);
        }
    }

    public function admin_menu(): void
    {
        add_menu_page(
            esc_html__('TruMonit', 'trumonit-monitor'),
            esc_html__('TruMonit', 'trumonit-monitor'),
            'manage_options',
            self::MENU_SLUG,
            [$this, 'render_settings_page'],
            'dashicons-chart-line',
            58
        );
    }
}

register_activation_hook(__FILE__, [TruMonit_Monitor_Plugin::class, 'activate']);
TruMonit_Monitor_Plugin::init();
