<?php

trait TM_ApiClientTrait
{
    private function normalize_api_error_text(string $value, int $maxLength = 180): string
    {
        $value = wp_strip_all_tags($value);
        $value = preg_replace('/\s+/', ' ', $value);
        $value = is_string($value) ? trim($value) : '';

        if ($value === '') {
            return '';
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($value, 'UTF-8') > $maxLength) {
                $value = rtrim(mb_substr($value, 0, $maxLength - 3, 'UTF-8')) . '...';
            }
            return $value;
        }

        if (strlen($value) > $maxLength) {
            $value = rtrim(substr($value, 0, $maxLength - 3)) . '...';
        }

        return $value;
    }

    private function api_request(
        string $method,
        string $path,
        ?array $body = null,
        ?array $query = null,
        ?string $tokenOverride = null,
        bool $useAuth = true
    ): array {
        $base = $this->get_service_url();
        $token = $tokenOverride !== null ? $this->sanitize_api_token($tokenOverride) : $this->get_api_token();

        if ($base === '') {
            return ['ok' => false, 'status' => 0, 'data' => [], 'error' => __('Service URL is empty.', 'trumonit-monitor')];
        }

        if ($useAuth && $token === '') {
            return ['ok' => false, 'status' => 0, 'data' => [], 'error' => __('API token is missing.', 'trumonit-monitor')];
        }

        $url = rtrim($base, '/') . $path;
        if (is_array($query) && $query !== []) {
            $url = add_query_arg($query, $url);
        }

        $args = [
            'method' => strtoupper($method),
            'timeout' => 20,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ];

        if ($useAuth) {
            $args['headers']['Authorization'] = 'Bearer ' . $token;
            $args['headers']['X-Api-Key'] = $token;
        }

        if (is_array($body)) {
            $args['headers']['Content-Type'] = 'application/json';
            $args['body'] = wp_json_encode($body);
        }

        $res = wp_remote_request($url, $args);
        if (is_wp_error($res)) {
            return [
                'ok' => false,
                'status' => 0,
                'data' => [],
                'error' => (string) $res->get_error_message(),
            ];
        }

        $status = (int) wp_remote_retrieve_response_code($res);
        $raw = (string) wp_remote_retrieve_body($res);
        $decoded = json_decode($raw, true);
        $data = is_array($decoded) ? $decoded : [];

        $ok = $status >= 200 && $status < 300;
        $error = '';
        $errorCode = '';

        if (!$ok) {
            $errorCode = $this->normalize_api_error_text((string) ($data['error'] ?? ''), 64);
            $error = $this->normalize_api_error_text((string) ($data['message'] ?? ''));
            if ($error === '' && $raw !== '') {
                $error = $this->normalize_api_error_text($raw);
            }
            if ($error === '') {
                $error = sprintf(__('HTTP error %d', 'trumonit-monitor'), $status);
            }
        }

        return [
            'ok' => $ok,
            'status' => $status,
            'data' => $data,
            'error' => $error,
            'error_code' => $errorCode,
        ];
    }

    private function api_request_candidates(
        string $method,
        array $paths,
        ?array $body = null,
        ?array $query = null,
        ?string $tokenOverride = null,
        bool $useAuth = true
    ): array {
        $last = ['ok' => false, 'status' => 0, 'data' => [], 'error' => __('Request failed.', 'trumonit-monitor')];

        foreach ($paths as $path) {
            $res = $this->api_request($method, (string) $path, $body, $query, $tokenOverride, $useAuth);
            $last = $res;

            if (!empty($res['ok'])) {
                return $res;
            }

            if ((int) ($res['status'] ?? 0) !== 404) {
                return $res;
            }
        }

        return $last;
    }

    private function extract_total_from_list_response(array $res): int
    {
        $data = is_array($res['data'] ?? null) ? $res['data'] : [];
        $total = isset($data['total']) ? (int) $data['total'] : 0;
        if ($total === 0 && isset($data['data']) && is_array($data['data'])) {
            $total = count($data['data']);
        }

        return max(0, $total);
    }

    private function normalize_monitors_rows(array $list): array
    {
        $rows = [];
        foreach ($list as $row) {
            if (!is_array($row)) {
                continue;
            }
            $rows[] = [
                'id' => isset($row['id']) ? (int) $row['id'] : 0,
                'url' => (string) ($row['url'] ?? ''),
                'display_name' => (string) ($row['display_name'] ?? ''),
                'is_active' => !empty($row['is_active']),
                'last_scanned_at' => (string) ($row['last_scanned_at'] ?? ''),
                'next_scan_at' => (string) ($row['next_scan_at'] ?? ''),
                'scan_mode' => (string) ($row['scan_mode'] ?? ''),
            ];
        }

        return $rows;
    }

    private function normalize_incidents_rows(array $list): array
    {
        $rows = [];
        foreach ($list as $row) {
            if (!is_array($row)) {
                continue;
            }

            $rows[] = [
                'id' => isset($row['id']) ? (int) $row['id'] : 0,
                'status' => (string) ($row['status'] ?? ''),
                'severity' => isset($row['severity']) ? (int) $row['severity'] : 0,
                'kind' => (string) ($row['kind'] ?? ''),
                'summary' => (string) ($row['summary'] ?? ''),
                'detected_at' => (string) ($row['detected_at'] ?? ''),
                'monitored_url_id' => isset($row['monitored_url_id']) ? (int) $row['monitored_url_id'] : 0,
            ];
        }

        return $rows;
    }

    private function api_me(?string $tokenOverride = null): array
    {
        return $this->api_request_candidates('GET', ['/api/plugin/me'], null, null, $tokenOverride);
    }

    private function api_monitors_total(?string $tokenOverride = null): array
    {
        $res = $this->api_monitors_list(1, 1, $tokenOverride);

        if (empty($res['ok'])) {
            return $res;
        }

        return $res;
    }

    private function api_incidents_total(?string $status = null, ?string $tokenOverride = null): array
    {
        $res = $this->api_incidents_list(1, 1, $status, $tokenOverride);

        if (empty($res['ok'])) {
            return $res;
        }

        return $res;
    }

    private function api_auth_login(string $email, string $password, string $clientId = ''): array
    {
        $email = sanitize_email($email);
        $password = (string) $password;
        $clientId = trim($clientId);

        if ($email === '' || $password === '') {
            return [
                'ok' => false,
                'status' => 0,
                'data' => [],
                'error' => __('Email and password are required.', 'trumonit-monitor'),
            ];
        }

        $payload = ['email' => $email, 'password' => $password];
        if ($clientId !== '') {
            $payload['client_id'] = substr($clientId, 0, 191);
        }

        return $this->api_request_candidates(
            'POST',
            ['/api/plugin/auth/login'],
            $payload,
            null,
            null,
            false
        );
    }

    private function api_auth_logout(): array
    {
        return $this->api_request_candidates(
            'POST',
            ['/api/plugin/auth/logout'],
            []
        );
    }

    private function api_monitors_list(int $page = 1, int $perPage = 20, ?string $tokenOverride = null): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $res = $this->api_request_candidates(
            'GET',
            ['/api/plugin/monitored-urls'],
            null,
            ['page' => $page, 'per_page' => $perPage],
            $tokenOverride
        );

        if (empty($res['ok'])) {
            return $res;
        }

        $list = is_array($res['data']['data'] ?? null) ? $res['data']['data'] : [];
        $serverPage = isset($res['data']['current_page']) ? (int) $res['data']['current_page'] : 0;
        $serverPerPage = isset($res['data']['per_page']) ? (int) $res['data']['per_page'] : 0;
        $effectivePage = $serverPage > 0 ? $serverPage : $page;
        $effectivePerPage = $serverPerPage > 0 ? $serverPerPage : $perPage;

        $res['rows'] = $this->normalize_monitors_rows($list);
        $res['total'] = $this->extract_total_from_list_response($res);
        $res['page'] = max(1, $effectivePage);
        $res['per_page'] = min(100, max(1, $effectivePerPage));
        return $res;
    }

    private function api_monitors_recent(int $limit = 8, ?string $tokenOverride = null): array
    {
        return $this->api_monitors_list(1, min(20, max(1, $limit)), $tokenOverride);
    }

    private function api_incidents_list(int $page = 1, int $perPage = 20, ?string $status = null, ?string $tokenOverride = null): array
    {
        $page = max(1, $page);
        $perPage = min(100, max(1, $perPage));
        $query = ['page' => $page, 'per_page' => $perPage];
        if (is_string($status) && $status !== '') {
            $query['status'] = $status;
        }

        $res = $this->api_request_candidates(
            'GET',
            ['/api/plugin/incidents'],
            null,
            $query,
            $tokenOverride
        );

        if (empty($res['ok'])) {
            return $res;
        }

        $list = is_array($res['data']['data'] ?? null) ? $res['data']['data'] : [];
        $serverPage = isset($res['data']['current_page']) ? (int) $res['data']['current_page'] : 0;
        $serverPerPage = isset($res['data']['per_page']) ? (int) $res['data']['per_page'] : 0;
        $effectivePage = $serverPage > 0 ? $serverPage : $page;
        $effectivePerPage = $serverPerPage > 0 ? $serverPerPage : $perPage;

        $res['rows'] = $this->normalize_incidents_rows($list);
        $res['total'] = $this->extract_total_from_list_response($res);
        $res['page'] = max(1, $effectivePage);
        $res['per_page'] = min(100, max(1, $effectivePerPage));
        return $res;
    }

    private function api_incidents_recent(int $limit = 8, ?string $tokenOverride = null): array
    {
        return $this->api_incidents_list(1, min(20, max(1, $limit)), null, $tokenOverride);
    }

    private function api_run_scan(int $monitorId): array
    {
        if ($monitorId <= 0) {
            return [
                'ok' => false,
                'status' => 0,
                'data' => [],
                'error' => __('Invalid monitor ID.', 'trumonit-monitor'),
            ];
        }

        return $this->api_request_candidates(
            'POST',
            [
                '/api/plugin/monitored-urls/' . $monitorId . '/scan',
            ],
            []
        );
    }

    private function refresh_dashboard_summary(?string $tokenOverride = null): array
    {
        $me = $this->api_me($tokenOverride);
        if (empty($me['ok'])) {
            $authStatus = (int) ($me['status'] ?? 0);
            $authError = (string) ($me['error'] ?? __('unknown', 'trumonit-monitor'));
            $prev = $this->get_last_summary();
            $prev['tenant_name'] = '';
            $prev['tenant_slug'] = '';
            $prev['user_email'] = '';
            $prev['monitors_total'] = null;
            $prev['incidents_open'] = null;
            $prev['incidents_total'] = null;
            $prev['recent_monitors'] = [];
            $prev['recent_incidents'] = [];
            $prev['auth_ok'] = false;
            $prev['auth_status'] = $authStatus;
            $prev['auth_error'] = $authError;
            $prev['can_run_scan'] = false;
            $prev['scan_requires_owner'] = true;
            $prev['user_role'] = '';
            $prev['errors'] = is_array($prev['errors'] ?? null) ? $prev['errors'] : [];
            $prev['errors']['auth'] = $authError;
            $this->set_last_summary($prev);

            return [
                'ok' => false,
                'message' => sprintf(__('Connection failed: %s', 'trumonit-monitor'), $authError),
            ];
        }

        $meData = is_array($me['data']) ? $me['data'] : [];
        $user = is_array($meData['user'] ?? null) ? $meData['user'] : [];
        $tenant = is_array($meData['tenant'] ?? null) ? $meData['tenant'] : [];
        $capabilities = is_array($meData['capabilities'] ?? null) ? $meData['capabilities'] : [];
        $scanRequiresOwner = array_key_exists('scan_requires_owner', $capabilities)
            ? !empty($capabilities['scan_requires_owner'])
            : true;
        $canRunScan = array_key_exists('can_run_scan', $capabilities)
            ? !empty($capabilities['can_run_scan'])
            : true;

        $monitors = $this->api_monitors_total($tokenOverride);
        $incidentsOpen = $this->api_incidents_total('open', $tokenOverride);
        $incidentsAll = $this->api_incidents_total(null, $tokenOverride);
        $prev = $this->get_last_summary();
        $cachedRecentMonitors = is_array($prev['recent_monitors'] ?? null) ? $prev['recent_monitors'] : [];
        $cachedRecentIncidents = is_array($prev['recent_incidents'] ?? null) ? $prev['recent_incidents'] : [];

        // Provide a small "value proof" snapshot on Overview without extra requests:
        // both api_*_total() calls use list endpoints with per_page=1, so we can reuse rows.
        $recentMonitors = $cachedRecentMonitors;
        $recentMonitorsError = '';
        if (!empty($monitors['ok'])) {
            $rows = is_array($monitors['rows'] ?? null) ? $monitors['rows'] : [];
            $recentMonitors = array_slice($rows, 0, 1);
        } else {
            $recentMonitorsError = (string) ($monitors['error'] ?? '');
        }

        $recentIncidents = $cachedRecentIncidents;
        $recentIncidentsError = '';
        if (!empty($incidentsAll['ok'])) {
            $rows = is_array($incidentsAll['rows'] ?? null) ? $incidentsAll['rows'] : [];
            $recentIncidents = array_slice($rows, 0, 1);
        } else {
            $recentIncidentsError = (string) ($incidentsAll['error'] ?? '');
        }

        $summary = [
            'tenant_name' => (string) ($tenant['name'] ?? ''),
            'tenant_slug' => (string) ($tenant['slug'] ?? ''),
            'user_email' => (string) ($user['email'] ?? ''),
            'auth_ok' => true,
            'auth_status' => (int) ($me['status'] ?? 200),
            'auth_error' => '',
            'user_role' => (string) ($user['role'] ?? ''),
            'can_run_scan' => $canRunScan,
            'scan_requires_owner' => $scanRequiresOwner,
            'monitors_total' => !empty($monitors['ok']) ? (int) ($monitors['total'] ?? 0) : null,
            'incidents_open' => !empty($incidentsOpen['ok']) ? (int) ($incidentsOpen['total'] ?? 0) : null,
            'incidents_total' => !empty($incidentsAll['ok']) ? (int) ($incidentsAll['total'] ?? 0) : null,
            'errors' => [
                'auth' => '',
                'monitors' => !empty($monitors['ok']) ? '' : (string) ($monitors['error'] ?? ''),
                'incidents_open' => !empty($incidentsOpen['ok']) ? '' : (string) ($incidentsOpen['error'] ?? ''),
                'incidents_total' => !empty($incidentsAll['ok']) ? '' : (string) ($incidentsAll['error'] ?? ''),
                'recent_monitors' => $recentMonitorsError,
                'recent_incidents' => $recentIncidentsError,
            ],
            'recent_monitors' => $recentMonitors,
            'recent_incidents' => $recentIncidents,
        ];

        $this->set_last_summary($summary);

        return [
            'ok' => true,
            'summary' => $summary,
        ];
    }
}
