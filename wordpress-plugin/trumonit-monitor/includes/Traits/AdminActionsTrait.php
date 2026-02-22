<?php

trait TM_AdminActionsTrait
{
    public function handle_api_login(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Forbidden', 'trumonit-monitor'));
        }
        check_admin_referer('tm_api_login');

        $serviceUrl = isset($_POST['service_url']) ? (string) wp_unslash($_POST['service_url']) : '';
        if ($serviceUrl !== '') {
            $this->set_service_url($serviceUrl);
        }

        $email = isset($_POST['api_email']) ? sanitize_email((string) wp_unslash($_POST['api_email'])) : '';
        $password = isset($_POST['api_password']) ? (string) wp_unslash($_POST['api_password']) : '';
        $clientId = (string) home_url('/');

        $login = $this->api_auth_login($email, $password, $clientId);
        if (empty($login['ok'])) {
            $err = (string) ($login['error'] ?? __('Login failed.', 'trumonit-monitor'));
            $this->admin_redirect_with_message(sprintf(__('API login failed: %s', 'trumonit-monitor'), $err), true, 'overview');
        }

        $data = is_array($login['data']) ? $login['data'] : [];
        $token = isset($data['token']) ? $this->sanitize_api_token((string) $data['token']) : '';
        if ($token === '') {
            $this->admin_redirect_with_message(__('API login failed: token not returned.', 'trumonit-monitor'), true, 'overview');
        }

        $refresh = $this->refresh_dashboard_summary($token);
        if (empty($refresh['ok'])) {
            $this->admin_redirect_with_message((string) ($refresh['message'] ?? __('Login validation failed.', 'trumonit-monitor')), true, 'overview');
        }

        $this->set_api_token($token);
        $this->admin_redirect_with_message(__('Logged in via API and connected.', 'trumonit-monitor'), false, 'overview');
    }

    public function handle_connect(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Forbidden', 'trumonit-monitor'));
        }
        check_admin_referer('tm_connect');

        $serviceUrl = isset($_POST['service_url']) ? (string) wp_unslash($_POST['service_url']) : '';
        $serviceUrl = $this->sanitize_service_url($serviceUrl);
        $this->set_service_url($serviceUrl);

        $token = isset($_POST['api_token']) ? (string) wp_unslash($_POST['api_token']) : '';
        $token = $this->sanitize_api_token($token);

        if ($token === '') {
            $token = $this->get_api_token();
        }

        if ($token === '') {
            $this->admin_redirect_with_message(__('API token is required.', 'trumonit-monitor'), true, 'overview');
        }

        $refresh = $this->refresh_dashboard_summary($token);
        if (empty($refresh['ok'])) {
            $this->admin_redirect_with_message((string) ($refresh['message'] ?? __('Connection failed.', 'trumonit-monitor')), true, 'overview');
        }

        $this->set_api_token($token);

        $this->admin_redirect_with_message(__('Connected and synced.', 'trumonit-monitor'), false, 'overview');
    }

    public function handle_disconnect(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Forbidden', 'trumonit-monitor'));
        }
        check_admin_referer('tm_disconnect');

        $remoteLogoutWarning = '';
        if ($this->get_api_token() !== '') {
            $logout = $this->api_auth_logout();
            if (empty($logout['ok'])) {
                $status = isset($logout['status']) ? (int) $logout['status'] : 0;
                $knownRevokedStates = [401, 403, 404];
                if (!in_array($status, $knownRevokedStates, true)) {
                    $remoteError = (string) ($logout['error'] ?? '');
                    $remoteLogoutWarning = $remoteError !== ''
                        ? sprintf(__('Disconnected locally, but remote token revoke failed: %s', 'trumonit-monitor'), $remoteError)
                        : __('Disconnected locally, but remote token revoke failed.', 'trumonit-monitor');
                }
            }
        }

        $this->clear_connection_data();

        if ($remoteLogoutWarning !== '') {
            $this->admin_redirect_with_message($remoteLogoutWarning, true, 'overview');
        }

        $this->admin_redirect_with_message(__('Disconnected.', 'trumonit-monitor'), false, 'overview');
    }

    public function handle_refresh_summary(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Forbidden', 'trumonit-monitor'));
        }
        check_admin_referer('tm_refresh_summary');

        if (!$this->is_connected()) {
            $this->admin_redirect_with_message(__('Connect first.', 'trumonit-monitor'), true, 'overview');
        }

        $refresh = $this->refresh_dashboard_summary();
        if (empty($refresh['ok'])) {
            $this->admin_redirect_with_message((string) ($refresh['message'] ?? __('Refresh failed.', 'trumonit-monitor')), true, 'overview');
        }

        $this->admin_redirect_with_message(__('Summary refreshed.', 'trumonit-monitor'), false, 'overview');
    }

    public function handle_run_scan(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Forbidden', 'trumonit-monitor'));
        }
        check_admin_referer('tm_run_scan');

        $preferredTab = isset($_POST['tm_tab']) ? sanitize_key((string) wp_unslash($_POST['tm_tab'])) : 'monitors';
        if ($preferredTab === '') {
            $preferredTab = 'monitors';
        }

        if (!$this->is_connected()) {
            $this->admin_redirect_with_message(__('Connect first.', 'trumonit-monitor'), true, $preferredTab);
        }

        $monitorId = isset($_POST['monitor_id']) ? (int) wp_unslash($_POST['monitor_id']) : 0;
        if ($monitorId <= 0) {
            $this->admin_redirect_with_message(__('Invalid monitor selected.', 'trumonit-monitor'), true, $preferredTab);
        }

        $res = $this->api_run_scan($monitorId);
        if (empty($res['ok'])) {
            $errorCode = (string) ($res['error_code'] ?? '');
            if (in_array($errorCode, ['owner_required', 'insufficient_scope'], true)) {
                $err = __('Run scan requires owner API token.', 'trumonit-monitor');
            } else {
                $err = (string) ($res['error'] ?? __('Run scan failed.', 'trumonit-monitor'));
            }
            $this->admin_redirect_with_message(sprintf(__('Run scan failed: %s', 'trumonit-monitor'), $err), true, $preferredTab);
        }

        $this->refresh_dashboard_summary();
        $this->admin_redirect_with_message(__('Scan queued.', 'trumonit-monitor'), false, $preferredTab);
    }
}
