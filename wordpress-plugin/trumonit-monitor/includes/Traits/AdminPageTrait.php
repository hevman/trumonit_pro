<?php

trait TM_AdminPageTrait
{
    public function admin_enqueue_scripts(string $hookSuffix): void
    {
        if (!in_array($hookSuffix, ['toplevel_page_' . self::MENU_SLUG], true)) {
            return;
        }

        wp_register_style('tm-monitor-admin', false, [], '0.1.0');
        wp_enqueue_style('tm-monitor-admin');
        wp_add_inline_style('tm-monitor-admin', '
.tm-wrap{max-width:1200px}
.tm-header{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:14px 0}
.tm-tabs{margin-top:8px}
.tm-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
@media (max-width:1100px){.tm-grid{grid-template-columns:1fr}}
.tm-card{background:#fff;border:1px solid #dcdcde;border-radius:12px;padding:14px}
.tm-card h2{margin:0 0 10px}
.tm-kpis{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
@media (max-width:1100px){.tm-kpis{grid-template-columns:1fr}}
.tm-kpi{border:1px solid #e5e7eb;border-radius:10px;padding:10px;background:#fff}
.tm-kpi .val{font-size:20px;font-weight:600;line-height:1.1}
.tm-muted{color:#646970}
.tm-actions{display:flex;gap:8px;flex-wrap:wrap}
');
    }

    public function render_settings_page(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Forbidden', 'trumonit-monitor'));
        }

        $serviceUrl = $this->get_service_url();
        $summary = $this->get_last_summary();
        $hasToken = $this->get_api_token() !== '';
        $refreshWindowSeconds = 300;

        $tab = isset($_GET['tm_tab']) ? sanitize_key((string) wp_unslash($_GET['tm_tab'])) : 'overview';
        $tabs = [
            'overview' => __('Overview', 'trumonit-monitor'),
            'monitors' => __('Monitors', 'trumonit-monitor'),
            'incidents' => __('Incidents', 'trumonit-monitor'),
            'plans' => __('Plans', 'trumonit-monitor'),
        ];
        if (!isset($tabs[$tab])) {
            $tab = 'overview';
        }

        $initialFetchedAt = isset($summary['fetched_at']) ? (int) $summary['fetched_at'] : 0;
        // Refresh summary not only on Overview: we want connection health to be accurate on every tab
        // (avoid "false green" connected state when token expired or was revoked).
        $shouldAutoRefresh = $hasToken
            && ($initialFetchedAt <= 0 || (time() - $initialFetchedAt) > $refreshWindowSeconds);
        if ($shouldAutoRefresh) {
            $this->refresh_dashboard_summary();
            $summary = $this->get_last_summary();
        }

        $authOk = ($summary['auth_ok'] ?? null) === true;
        $authStatus = isset($summary['auth_status']) ? (int) $summary['auth_status'] : 0;
        $authError = (string) ($summary['auth_error'] ?? '');
        // Connected = verified auth OK. Token presence alone is not enough (prevents "false green").
        $connected = $hasToken && $authOk;

        $msg = isset($_GET['tm_msg']) ? sanitize_text_field((string) wp_unslash($_GET['tm_msg'])) : '';
        $isError = isset($_GET['tm_err']) && (string) wp_unslash($_GET['tm_err']) === '1';

        $tenantName = (string) ($summary['tenant_name'] ?? '');
        $tenantSlug = (string) ($summary['tenant_slug'] ?? '');
        $userEmail = (string) ($summary['user_email'] ?? '');
        $userRole = (string) ($summary['user_role'] ?? '');
        $canRunScan = array_key_exists('can_run_scan', $summary) ? !empty($summary['can_run_scan']) : true;
        $scanRequiresOwner = array_key_exists('scan_requires_owner', $summary) ? !empty($summary['scan_requires_owner']) : true;
        $monitorsTotal = $summary['monitors_total'] ?? null;
        $incidentsOpen = $summary['incidents_open'] ?? null;
        $incidentsTotal = $summary['incidents_total'] ?? null;
        $fetchedAt = isset($summary['fetched_at']) ? (int) $summary['fetched_at'] : 0;
        $dataAgeSeconds = $fetchedAt > 0 ? max(0, time() - $fetchedAt) : 0;
        $isSummaryStale = $fetchedAt > 0 && $dataAgeSeconds > $refreshWindowSeconds;

        $summaryErrors = is_array($summary['errors'] ?? null) ? $summary['errors'] : [];
        $recentMonitors = is_array($summary['recent_monitors'] ?? null) ? ($summary['recent_monitors'] ?? []) : [];
        $recentIncidents = is_array($summary['recent_incidents'] ?? null) ? ($summary['recent_incidents'] ?? []) : [];

        $perPage = isset($_GET['tm_per_page']) ? (int) wp_unslash($_GET['tm_per_page']) : 20;
        $perPage = min(50, max(5, $perPage));
        $monitorsPage = isset($_GET['tm_m_page']) ? (int) wp_unslash($_GET['tm_m_page']) : 1;
        $monitorsPage = max(1, $monitorsPage);
        $incidentsPage = isset($_GET['tm_i_page']) ? (int) wp_unslash($_GET['tm_i_page']) : 1;
        $incidentsPage = max(1, $incidentsPage);
        $incidentStatus = isset($_GET['tm_incident_status']) ? sanitize_key((string) wp_unslash($_GET['tm_incident_status'])) : '';
        if (!in_array($incidentStatus, ['', 'open', 'ack', 'resolved'], true)) {
            $incidentStatus = '';
        }

        $monitorsTotalRows = is_array($recentMonitors) ? count($recentMonitors) : 0;
        $incidentsTotalRows = is_array($recentIncidents) ? count($recentIncidents) : 0;
        $monitorsError = (string) ($summaryErrors['recent_monitors'] ?? '');
        $incidentsError = (string) ($summaryErrors['recent_incidents'] ?? '');

        if ($tab === 'monitors' && $hasToken) {
            $monitorsRes = $this->api_monitors_list($monitorsPage, $perPage);
            if (!empty($monitorsRes['ok'])) {
                $recentMonitors = is_array($monitorsRes['rows'] ?? null) ? ($monitorsRes['rows'] ?? []) : [];
                $monitorsTotalRows = (int) ($monitorsRes['total'] ?? count($recentMonitors));
                $monitorsPage = max(1, (int) ($monitorsRes['page'] ?? $monitorsPage));
                $perPage = min(50, max(5, (int) ($monitorsRes['per_page'] ?? $perPage)));
                $monitorsError = '';
            } else {
                $monitorsError = (string) ($monitorsRes['error'] ?? __('Unable to load monitors.', 'trumonit-monitor'));
            }
        }

        if ($tab === 'incidents' && $hasToken) {
            $incidentsRes = $this->api_incidents_list($incidentsPage, $perPage, $incidentStatus !== '' ? $incidentStatus : null);
            if (!empty($incidentsRes['ok'])) {
                $recentIncidents = is_array($incidentsRes['rows'] ?? null) ? ($incidentsRes['rows'] ?? []) : [];
                $incidentsTotalRows = (int) ($incidentsRes['total'] ?? count($recentIncidents));
                $incidentsPage = max(1, (int) ($incidentsRes['page'] ?? $incidentsPage));
                $perPage = min(50, max(5, (int) ($incidentsRes['per_page'] ?? $perPage)));
                $incidentsError = '';
            } else {
                $incidentsError = (string) ($incidentsRes['error'] ?? __('Unable to load incidents.', 'trumonit-monitor'));
            }
        }

        $dashboardUrl = trailingslashit($serviceUrl) . 'dashboard';
        $incidentsUrl = trailingslashit($serviceUrl) . 'incidents';
        $pricingUrl = trailingslashit($serviceUrl) . 'pricing';
        $billingUrl = trailingslashit($serviceUrl) . 'billing';
        $registerUrl = trailingslashit($serviceUrl) . 'register';
        $loginUrl = trailingslashit($serviceUrl) . 'login';
        $websiteUrl = home_url('/');
        $planMatrix = [
            [
                'tier' => __('Free', 'trumonit-monitor'),
                'price' => __('$0 / month', 'trumonit-monitor'),
                'monitors' => __('Up to 3 monitors', 'trumonit-monitor'),
                'retention' => __('7 days retention', 'trumonit-monitor'),
                'best_for' => __('Single site and basic uptime checks', 'trumonit-monitor'),
            ],
            [
                'tier' => __('Lite', 'trumonit-monitor'),
                'price' => __('$39 / month', 'trumonit-monitor'),
                'monitors' => __('Up to 50 monitors', 'trumonit-monitor'),
                'retention' => __('30 days retention', 'trumonit-monitor'),
                'best_for' => __('Small production projects', 'trumonit-monitor'),
            ],
            [
                'tier' => __('Pro', 'trumonit-monitor'),
                'price' => __('$149 / month', 'trumonit-monitor'),
                'monitors' => __('Up to 300 monitors', 'trumonit-monitor'),
                'retention' => __('90 days retention', 'trumonit-monitor'),
                'best_for' => __('Teams that need higher quota and faster operations', 'trumonit-monitor'),
            ],
            [
                'tier' => __('Enterprise', 'trumonit-monitor'),
                'price' => __('Custom pricing', 'trumonit-monitor'),
                'monitors' => __('Unlimited by agreement', 'trumonit-monitor'),
                'retention' => __('Up to 365 days retention', 'trumonit-monitor'),
                'best_for' => __('Large organizations with compliance and SSO needs', 'trumonit-monitor'),
            ],
        ];
        $planMatrixDisclaimer = __('Plan values are based on current public defaults and can change. Use Compare plans for live checkout pricing.', 'trumonit-monitor');

        ?>
        <div class="wrap tm-wrap">
            <div class="tm-header">
                <h1>
                    <?php echo esc_html__('TruMonit Monitor', 'trumonit-monitor'); ?>
                    <?php if ($connected): ?>
                        <span class="tm-muted" style="font-size:12px;vertical-align:middle;">
                            (<?php echo esc_html__('Connected', 'trumonit-monitor'); ?>)
                        </span>
                    <?php elseif ($hasToken): ?>
                        <?php
                        $isUnauthorized = in_array($authStatus, [401, 403], true);
                        $badgeText = $isUnauthorized
                            ? __('Token invalid - reconnect', 'trumonit-monitor')
                            : __('Connection error', 'trumonit-monitor');
                        ?>
                        <span style="font-size:12px;vertical-align:middle;color:<?php echo esc_attr($isUnauthorized ? '#d63638' : '#b45309'); ?>;">
                            (<?php echo esc_html($badgeText); ?>)
                        </span>
                    <?php endif; ?>
                </h1>
                <div class="tm-actions">
                    <?php if ($connected): ?>
                        <a class="button button-primary" href="<?php echo esc_url($dashboardUrl); ?>" target="_blank" rel="noreferrer"><?php echo esc_html__('Open dashboard', 'trumonit-monitor'); ?></a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($msg !== ''): ?>
                <div class="notice <?php echo esc_attr($isError ? 'notice-error' : 'notice-success'); ?> is-dismissible">
                    <p><?php echo esc_html($msg); ?></p>
                </div>
            <?php endif; ?>

            <nav class="nav-tab-wrapper tm-tabs" aria-label="<?php echo esc_attr__('TruMonit tabs', 'trumonit-monitor'); ?>">
                <?php foreach ($tabs as $key => $label): ?>
                    <?php
                    $u = add_query_arg(['page' => self::MENU_SLUG, 'tm_tab' => $key], admin_url('admin.php'));
                    $cls = 'nav-tab' . ($tab === $key ? ' nav-tab-active' : '');
                    ?>
                    <a href="<?php echo esc_url($u); ?>" class="<?php echo esc_attr($cls); ?>"><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </nav>

            <?php if ($tab === 'overview'): ?>
                <div class="tm-grid" style="margin-top:14px;">
                    <div class="tm-card">
                        <h2><?php echo esc_html__('Connection', 'trumonit-monitor'); ?></h2>
                        <p class="tm-muted"><?php echo esc_html__('Connect using API token or sign in with API credentials.', 'trumonit-monitor'); ?></p>

                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('tm_connect'); ?>
                            <input type="hidden" name="action" value="tm_connect" />
                            <input type="hidden" name="tm_tab" value="overview" />
                            <table class="form-table" role="presentation" style="margin-top:0;">
                                <tr>
                                    <th scope="row"><label for="tm_service_url"><?php echo esc_html__('Service URL', 'trumonit-monitor'); ?></label></th>
                                    <td>
                                        <input id="tm_service_url" name="service_url" type="url" class="regular-text" value="<?php echo esc_attr($serviceUrl); ?>" required />
                                        <p class="description"><?php echo esc_html__('Default: https://trumonit.pro', 'trumonit-monitor'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="tm_api_token"><?php echo esc_html__('API token', 'trumonit-monitor'); ?></label></th>
                                    <td>
                                        <input id="tm_api_token" name="api_token" type="password" class="regular-text" autocomplete="off" placeholder="token..." />
                                        <p class="description"><?php echo esc_html__('Stored encrypted in WordPress options. Leave empty to keep existing token.', 'trumonit-monitor'); ?></p>
                                        <p class="description"><?php echo esc_html__('No token yet? Open dashboard and create API token in user settings.', 'trumonit-monitor'); ?></p>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php echo esc_html__('WordPress site URL', 'trumonit-monitor'); ?></th>
                                    <td><code><?php echo esc_html($websiteUrl); ?></code></td>
                                </tr>
                            </table>
                            <?php submit_button($connected ? __('Reconnect / Save', 'trumonit-monitor') : __('Connect', 'trumonit-monitor'), 'primary', 'submit', false); ?>
                            <a class="button button-secondary" href="<?php echo esc_url($dashboardUrl); ?>" target="_blank" rel="noreferrer"><?php echo esc_html__('Open dashboard to get token', 'trumonit-monitor'); ?></a>
                        </form>

                        <hr />

                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('tm_api_login'); ?>
                            <input type="hidden" name="action" value="tm_api_login" />
                            <input type="hidden" name="tm_tab" value="overview" />
                            <input type="hidden" name="service_url" value="<?php echo esc_attr($serviceUrl); ?>" />
                            <table class="form-table" role="presentation" style="margin-top:0;">
                                <tr>
                                    <th scope="row"><label for="tm_api_email"><?php echo esc_html__('API email', 'trumonit-monitor'); ?></label></th>
                                    <td><input id="tm_api_email" name="api_email" type="email" class="regular-text" autocomplete="username" /></td>
                                </tr>
                                <tr>
                                    <th scope="row"><label for="tm_api_password"><?php echo esc_html__('API password', 'trumonit-monitor'); ?></label></th>
                                    <td><input id="tm_api_password" name="api_password" type="password" class="regular-text" autocomplete="current-password" /></td>
                                </tr>
                            </table>
                            <?php submit_button(__('Login via API', 'trumonit-monitor'), 'secondary', 'submit', false); ?>
                        </form>

                        <?php if ($hasToken): ?>
                            <hr />
                            <div class="tm-actions">
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                    <?php wp_nonce_field('tm_refresh_summary'); ?>
                                    <input type="hidden" name="action" value="tm_refresh_summary" />
                                    <input type="hidden" name="tm_tab" value="overview" />
                                    <button type="submit" class="button button-secondary"><?php echo esc_html__('Refresh summary', 'trumonit-monitor'); ?></button>
                                </form>

                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Disconnect and remove token?', 'trumonit-monitor')); ?>');">
                                    <?php wp_nonce_field('tm_disconnect'); ?>
                                    <input type="hidden" name="action" value="tm_disconnect" />
                                    <input type="hidden" name="tm_tab" value="overview" />
                                    <button type="submit" class="button"><?php echo esc_html__('Disconnect', 'trumonit-monitor'); ?></button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="tm-card">
                        <h2><?php echo esc_html__('Health Snapshot', 'trumonit-monitor'); ?></h2>
                        <?php if (!$hasToken): ?>
                            <p class="tm-muted"><?php echo esc_html__('Connect API token to view health metrics.', 'trumonit-monitor'); ?></p>
                        <?php else: ?>
                            <?php if (!$connected): ?>
                                <div class="notice notice-error inline">
                                    <p>
                                        <?php
                                        $statusText = $authStatus > 0 ? ' (HTTP ' . $authStatus . ')' : '';
                                        $msgText = $authError !== '' ? $authError : __('Token validation failed.', 'trumonit-monitor');
                                        echo esc_html(sprintf(__('Connection is not healthy: %s%s', 'trumonit-monitor'), $msgText, $statusText));
                                        ?>
                                    </p>
                                </div>
                            <?php elseif (!$authOk && $authError !== ''): ?>
                                <div class="notice notice-warning inline"><p><?php echo esc_html($authError); ?></p></div>
                            <?php endif; ?>

                            <?php if ($isSummaryStale): ?>
                                <div class="notice notice-warning inline">
                                    <p>
                                        <?php
                                        $ageMinutes = (int) ceil($dataAgeSeconds / 60);
                                        echo esc_html(sprintf(__('Snapshot may be stale (%d min old). Click Refresh summary.', 'trumonit-monitor'), max(1, $ageMinutes)));
                                        ?>
                                    </p>
                                </div>
                            <?php endif; ?>

                            <div class="tm-kpis">
                                <div class="tm-kpi">
                                    <div class="tm-muted"><?php echo esc_html__('Monitors', 'trumonit-monitor'); ?></div>
                                    <div class="val"><?php echo esc_html(is_int($monitorsTotal) ? (string) $monitorsTotal : 'n/a'); ?></div>
                                </div>
                                <div class="tm-kpi">
                                    <div class="tm-muted"><?php echo esc_html__('Open incidents', 'trumonit-monitor'); ?></div>
                                    <div class="val"><?php echo esc_html(is_int($incidentsOpen) ? (string) $incidentsOpen : 'n/a'); ?></div>
                                </div>
                                <div class="tm-kpi">
                                    <div class="tm-muted"><?php echo esc_html__('All incidents', 'trumonit-monitor'); ?></div>
                                    <div class="val"><?php echo esc_html(is_int($incidentsTotal) ? (string) $incidentsTotal : 'n/a'); ?></div>
                                </div>
                            </div>

                            <?php
                            $errorLabels = [
                                'auth' => __('Authentication', 'trumonit-monitor'),
                                'monitors' => __('Monitors total', 'trumonit-monitor'),
                                'incidents_open' => __('Open incidents total', 'trumonit-monitor'),
                                'incidents_total' => __('All incidents total', 'trumonit-monitor'),
                                'recent_monitors' => __('Monitors list', 'trumonit-monitor'),
                                'recent_incidents' => __('Incidents list', 'trumonit-monitor'),
                            ];
                            $visibleErrors = [];
                            foreach ($errorLabels as $errorKey => $errorLabel) {
                                $errorValue = (string) ($summaryErrors[$errorKey] ?? '');
                                if ($errorValue !== '') {
                                    $visibleErrors[] = $errorLabel . ': ' . $errorValue;
                                }
                            }
                            ?>
                            <?php if ($visibleErrors !== []): ?>
                                <div class="notice notice-warning inline">
                                    <p><strong><?php echo esc_html__('API diagnostics', 'trumonit-monitor'); ?></strong></p>
                                    <ul style="margin:0 0 0 18px;">
                                        <?php foreach ($visibleErrors as $visibleError): ?>
                                            <li><?php echo esc_html($visibleError); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php endif; ?>

                            <table class="form-table" role="presentation">
                                <tr>
                                    <th scope="row"><?php echo esc_html__('Workspace', 'trumonit-monitor'); ?></th>
                                    <td>
                                        <?php
                                        $workspaceText = $tenantName !== '' ? $tenantName : '-';
                                        if ($tenantSlug !== '') {
                                            $workspaceText .= ' (' . $tenantSlug . ')';
                                        }
                                        echo esc_html($workspaceText);
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php echo esc_html__('User', 'trumonit-monitor'); ?></th>
                                    <td>
                                        <?php
                                        $userText = $userEmail !== '' ? $userEmail : '-';
                                        if ($userRole !== '') {
                                            $userText .= ' (' . $userRole . ')';
                                        }
                                        echo esc_html($userText);
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php echo esc_html__('Updated at', 'trumonit-monitor'); ?></th>
                                    <td><?php echo esc_html($fetchedAt > 0 ? wp_date('Y-m-d H:i:s', $fetchedAt) : '-'); ?></td>
                                </tr>
                            </table>

                            <div class="tm-actions">
                                <a class="button" href="<?php echo esc_url($dashboardUrl); ?>" target="_blank" rel="noreferrer"><?php echo esc_html__('Dashboard', 'trumonit-monitor'); ?></a>
                                <a class="button" href="<?php echo esc_url($incidentsUrl); ?>" target="_blank" rel="noreferrer"><?php echo esc_html__('Incidents', 'trumonit-monitor'); ?></a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'monitors'): ?>
                <div class="tm-card" style="margin-top:14px;">
                    <h2><?php echo esc_html__('Recent Monitors', 'trumonit-monitor'); ?></h2>
                    <?php if (!$canRunScan && $scanRequiresOwner): ?>
                        <div class="notice notice-warning inline">
                            <p><?php echo esc_html__('Run scan requires owner API token.', 'trumonit-monitor'); ?></p>
                        </div>
                    <?php endif; ?>
                    <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="margin:8px 0 12px 0;">
                        <input type="hidden" name="page" value="<?php echo esc_attr(self::MENU_SLUG); ?>" />
                        <input type="hidden" name="tm_tab" value="monitors" />
                        <label for="tm_per_page_monitors"><?php echo esc_html__('Items per page', 'trumonit-monitor'); ?></label>
                        <select id="tm_per_page_monitors" name="tm_per_page">
                            <?php foreach ([10, 20, 50] as $size): ?>
                                <option value="<?php echo esc_attr((string) $size); ?>" <?php selected($perPage, $size); ?>><?php echo esc_html((string) $size); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="button button-secondary"><?php echo esc_html__('Apply', 'trumonit-monitor'); ?></button>
                    </form>

                    <?php
                    $monitorsTotalPages = max(1, (int) ceil($monitorsTotalRows / max(1, $perPage)));
                    $monitorsPrevUrl = add_query_arg(
                        ['page' => self::MENU_SLUG, 'tm_tab' => 'monitors', 'tm_per_page' => $perPage, 'tm_m_page' => max(1, $monitorsPage - 1)],
                        admin_url('admin.php')
                    );
                    $monitorsNextUrl = add_query_arg(
                        ['page' => self::MENU_SLUG, 'tm_tab' => 'monitors', 'tm_per_page' => $perPage, 'tm_m_page' => min($monitorsTotalPages, $monitorsPage + 1)],
                        admin_url('admin.php')
                    );
                    ?>

                    <?php if (!$hasToken): ?>
                        <p class="tm-muted"><?php echo esc_html__('Connect first to load monitor list.', 'trumonit-monitor'); ?></p>
                    <?php elseif ($monitorsError !== ''): ?>
                        <div class="notice notice-error inline"><p><?php echo esc_html($monitorsError); ?></p></div>
                    <?php elseif ($recentMonitors === []): ?>
                        <p class="tm-muted"><?php echo esc_html__('No monitors found or API response is empty.', 'trumonit-monitor'); ?></p>
                    <?php else: ?>
                        <table class="widefat striped" style="margin-top:10px;">
                            <thead>
                                <tr>
                                    <th><?php echo esc_html__('ID', 'trumonit-monitor'); ?></th>
                                    <th><?php echo esc_html__('Name', 'trumonit-monitor'); ?></th>
                                    <th><?php echo esc_html__('URL', 'trumonit-monitor'); ?></th>
                                    <th><?php echo esc_html__('Active', 'trumonit-monitor'); ?></th>
                                    <th><?php echo esc_html__('Last scan', 'trumonit-monitor'); ?></th>
                                    <th><?php echo esc_html__('Action', 'trumonit-monitor'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentMonitors as $m): ?>
                                    <?php
                                    $id = isset($m['id']) ? (int) $m['id'] : 0;
                                    $name = (string) ($m['display_name'] ?? '');
                                    $url = (string) ($m['url'] ?? '');
                                    $isActive = !empty($m['is_active']);
                                    $lastScan = (string) ($m['last_scanned_at'] ?? '');
                                    ?>
                                    <tr>
                                        <td><code><?php echo esc_html($id > 0 ? (string) $id : '-'); ?></code></td>
                                        <td><?php echo esc_html($name !== '' ? $name : '-'); ?></td>
                                        <td><code><?php echo esc_html($url !== '' ? $url : '-'); ?></code></td>
                                        <td><?php echo esc_html($isActive ? __('yes', 'trumonit-monitor') : __('no', 'trumonit-monitor')); ?></td>
                                        <td><?php echo esc_html($lastScan !== '' ? $lastScan : '-'); ?></td>
                                        <td>
                                            <?php if ($id > 0 && $canRunScan): ?>
                                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:inline;">
                                                    <?php wp_nonce_field('tm_run_scan'); ?>
                                                    <input type="hidden" name="action" value="tm_run_scan" />
                                                    <input type="hidden" name="tm_tab" value="monitors" />
                                                    <input type="hidden" name="tm_per_page" value="<?php echo esc_attr((string) $perPage); ?>" />
                                                    <input type="hidden" name="tm_m_page" value="<?php echo esc_attr((string) $monitorsPage); ?>" />
                                                    <input type="hidden" name="monitor_id" value="<?php echo esc_attr((string) $id); ?>" />
                                                    <button type="submit" class="button button-small"><?php echo esc_html__('Run scan', 'trumonit-monitor'); ?></button>
                                                </form>
                                            <?php elseif ($id > 0): ?>
                                                <span class="tm-muted"><?php echo esc_html__('Owner token required', 'trumonit-monitor'); ?></span>
                                            <?php else: ?>
                                                <span class="tm-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <?php if ($hasToken && $monitorsTotalPages > 1): ?>
                        <div class="tm-actions" style="margin-top:10px;">
                            <?php if ($monitorsPage > 1): ?>
                                <a class="button" href="<?php echo esc_url($monitorsPrevUrl); ?>">&larr; <?php echo esc_html__('Previous', 'trumonit-monitor'); ?></a>
                            <?php endif; ?>
                            <span class="tm-muted">
                                <?php echo esc_html(sprintf(__('Page %1$d of %2$d', 'trumonit-monitor'), $monitorsPage, $monitorsTotalPages)); ?>
                            </span>
                            <?php if ($monitorsPage < $monitorsTotalPages): ?>
                                <a class="button" href="<?php echo esc_url($monitorsNextUrl); ?>"><?php echo esc_html__('Next', 'trumonit-monitor'); ?> &rarr;</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'incidents'): ?>
                <div class="tm-card" style="margin-top:14px;">
                    <h2><?php echo esc_html__('Recent Incidents', 'trumonit-monitor'); ?></h2>
                    <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="margin:8px 0 12px 0;display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end;">
                        <input type="hidden" name="page" value="<?php echo esc_attr(self::MENU_SLUG); ?>" />
                        <input type="hidden" name="tm_tab" value="incidents" />
                        <div>
                            <label for="tm_per_page_incidents"><?php echo esc_html__('Items per page', 'trumonit-monitor'); ?></label>
                            <select id="tm_per_page_incidents" name="tm_per_page">
                                <?php foreach ([10, 20, 50] as $size): ?>
                                    <option value="<?php echo esc_attr((string) $size); ?>" <?php selected($perPage, $size); ?>><?php echo esc_html((string) $size); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="tm_incident_status"><?php echo esc_html__('Status', 'trumonit-monitor'); ?></label>
                            <select id="tm_incident_status" name="tm_incident_status">
                                <option value="" <?php selected($incidentStatus, ''); ?>><?php echo esc_html__('All', 'trumonit-monitor'); ?></option>
                                <option value="open" <?php selected($incidentStatus, 'open'); ?>><?php echo esc_html__('open', 'trumonit-monitor'); ?></option>
                                <option value="ack" <?php selected($incidentStatus, 'ack'); ?>><?php echo esc_html__('ack', 'trumonit-monitor'); ?></option>
                                <option value="resolved" <?php selected($incidentStatus, 'resolved'); ?>><?php echo esc_html__('resolved', 'trumonit-monitor'); ?></option>
                            </select>
                        </div>
                        <button type="submit" class="button button-secondary"><?php echo esc_html__('Apply', 'trumonit-monitor'); ?></button>
                    </form>

                    <?php
                    $incidentsTotalPages = max(1, (int) ceil($incidentsTotalRows / max(1, $perPage)));
                    $incidentsPrevUrl = add_query_arg(
                        ['page' => self::MENU_SLUG, 'tm_tab' => 'incidents', 'tm_per_page' => $perPage, 'tm_incident_status' => $incidentStatus, 'tm_i_page' => max(1, $incidentsPage - 1)],
                        admin_url('admin.php')
                    );
                    $incidentsNextUrl = add_query_arg(
                        ['page' => self::MENU_SLUG, 'tm_tab' => 'incidents', 'tm_per_page' => $perPage, 'tm_incident_status' => $incidentStatus, 'tm_i_page' => min($incidentsTotalPages, $incidentsPage + 1)],
                        admin_url('admin.php')
                    );
                    ?>

                    <?php if (!$hasToken): ?>
                        <p class="tm-muted"><?php echo esc_html__('Connect first to load incidents.', 'trumonit-monitor'); ?></p>
                    <?php elseif ($incidentsError !== ''): ?>
                        <div class="notice notice-error inline"><p><?php echo esc_html($incidentsError); ?></p></div>
                    <?php elseif ($recentIncidents === []): ?>
                        <p class="tm-muted"><?php echo esc_html__('No incidents found or API response is empty.', 'trumonit-monitor'); ?></p>
                    <?php else: ?>
                        <table class="widefat striped" style="margin-top:10px;">
                            <thead>
                                <tr>
                                    <th><?php echo esc_html__('ID', 'trumonit-monitor'); ?></th>
                                    <th><?php echo esc_html__('Status', 'trumonit-monitor'); ?></th>
                                    <th><?php echo esc_html__('Severity', 'trumonit-monitor'); ?></th>
                                    <th><?php echo esc_html__('Kind', 'trumonit-monitor'); ?></th>
                                    <th><?php echo esc_html__('Summary', 'trumonit-monitor'); ?></th>
                                    <th><?php echo esc_html__('Detected at', 'trumonit-monitor'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentIncidents as $incident): ?>
                                    <tr>
                                        <td><code><?php echo esc_html((string) ((int) ($incident['id'] ?? 0))); ?></code></td>
                                        <td><?php echo esc_html((string) ($incident['status'] ?? '')); ?></td>
                                        <td><?php echo esc_html((string) ((int) ($incident['severity'] ?? 0))); ?></td>
                                        <td><?php echo esc_html((string) ($incident['kind'] ?? '')); ?></td>
                                        <td><?php echo esc_html((string) ($incident['summary'] ?? '')); ?></td>
                                        <td><?php echo esc_html((string) ($incident['detected_at'] ?? '')); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                    <?php if ($hasToken && $incidentsTotalPages > 1): ?>
                        <div class="tm-actions" style="margin-top:10px;">
                            <?php if ($incidentsPage > 1): ?>
                                <a class="button" href="<?php echo esc_url($incidentsPrevUrl); ?>">&larr; <?php echo esc_html__('Previous', 'trumonit-monitor'); ?></a>
                            <?php endif; ?>
                            <span class="tm-muted">
                                <?php echo esc_html(sprintf(__('Page %1$d of %2$d', 'trumonit-monitor'), $incidentsPage, $incidentsTotalPages)); ?>
                            </span>
                            <?php if ($incidentsPage < $incidentsTotalPages): ?>
                                <a class="button" href="<?php echo esc_url($incidentsNextUrl); ?>"><?php echo esc_html__('Next', 'trumonit-monitor'); ?> &rarr;</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'plans'): ?>
                <div class="tm-card" style="margin-top:14px;">
                    <h2><?php echo esc_html__('Plans & Billing', 'trumonit-monitor'); ?></h2>
                    <p class="tm-muted"><?php echo esc_html__('Choose a plan based on monitor volume and retention needs.', 'trumonit-monitor'); ?></p>
                    <table class="widefat striped" style="margin-top:10px;">
                        <thead>
                            <tr>
                                <th><?php echo esc_html__('Plan', 'trumonit-monitor'); ?></th>
                                <th><?php echo esc_html__('Price', 'trumonit-monitor'); ?></th>
                                <th><?php echo esc_html__('Monitor limit', 'trumonit-monitor'); ?></th>
                                <th><?php echo esc_html__('Retention', 'trumonit-monitor'); ?></th>
                                <th><?php echo esc_html__('Best for', 'trumonit-monitor'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($planMatrix as $planRow): ?>
                                <tr>
                                    <td><strong><?php echo esc_html((string) $planRow['tier']); ?></strong></td>
                                    <td><?php echo esc_html((string) $planRow['price']); ?></td>
                                    <td><?php echo esc_html((string) $planRow['monitors']); ?></td>
                                    <td><?php echo esc_html((string) $planRow['retention']); ?></td>
                                    <td><?php echo esc_html((string) $planRow['best_for']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <p class="description"><?php echo esc_html($planMatrixDisclaimer); ?></p>
                    <div class="tm-actions">
                        <a class="button button-primary" href="<?php echo esc_url($pricingUrl); ?>" target="_blank" rel="noreferrer"><?php echo esc_html__('Compare plans', 'trumonit-monitor'); ?></a>
                        <a class="button" href="<?php echo esc_url($billingUrl); ?>" target="_blank" rel="noreferrer"><?php echo esc_html__('Open billing', 'trumonit-monitor'); ?></a>
                        <a class="button" href="<?php echo esc_url($registerUrl); ?>" target="_blank" rel="noreferrer"><?php echo esc_html__('Create account', 'trumonit-monitor'); ?></a>
                        <a class="button" href="<?php echo esc_url($loginUrl); ?>" target="_blank" rel="noreferrer"><?php echo esc_html__('Login', 'trumonit-monitor'); ?></a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
