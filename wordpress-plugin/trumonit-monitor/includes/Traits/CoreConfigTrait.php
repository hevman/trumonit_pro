<?php

trait TM_CoreConfigTrait
{
    private function get_crypto_key(): string
    {
        $keyMaterial = (string) wp_salt('auth');
        if ($keyMaterial === '') {
            return '';
        }

        return hash('sha256', $keyMaterial, true);
    }

    private function random_bytes_safe(int $length): string
    {
        if ($length <= 0) {
            return '';
        }

        try {
            return random_bytes($length);
        } catch (Exception $e) {
            if (function_exists('openssl_random_pseudo_bytes')) {
                $bytes = openssl_random_pseudo_bytes($length);
                if (is_string($bytes) && strlen($bytes) === $length) {
                    return $bytes;
                }
            }
        }

        return '';
    }

    private function trim_notice_message(string $message, int $maxLength = 200): string
    {
        $message = wp_strip_all_tags($message);
        $message = preg_replace('/\s+/', ' ', $message);
        $message = is_string($message) ? trim($message) : '';

        if ($message === '') {
            return '';
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($message, 'UTF-8') > $maxLength) {
                $message = rtrim(mb_substr($message, 0, $maxLength - 3, 'UTF-8')) . '...';
            }
            return $message;
        }

        if (strlen($message) > $maxLength) {
            $message = rtrim(substr($message, 0, $maxLength - 3)) . '...';
        }

        return $message;
    }

    private function sanitize_tab_key(string $tab): string
    {
        $tab = sanitize_key($tab);
        return in_array($tab, ['overview', 'monitors', 'incidents', 'plans'], true) ? $tab : '';
    }

    private function redirect_context(?string $preferredTab = null): array
    {
        $ctx = [];

        $tab = '';
        if (is_string($preferredTab) && $preferredTab !== '') {
            $tab = $this->sanitize_tab_key($preferredTab);
        }
        if ($tab === '' && isset($_REQUEST['tm_tab'])) {
            $tab = $this->sanitize_tab_key((string) wp_unslash($_REQUEST['tm_tab']));
        }
        if ($tab !== '') {
            $ctx['tm_tab'] = $tab;
        }

        if (isset($_REQUEST['tm_per_page'])) {
            $perPage = (int) wp_unslash($_REQUEST['tm_per_page']);
            if ($perPage >= 5 && $perPage <= 50) {
                $ctx['tm_per_page'] = $perPage;
            }
        }

        if (isset($_REQUEST['tm_m_page'])) {
            $mPage = max(1, (int) wp_unslash($_REQUEST['tm_m_page']));
            $ctx['tm_m_page'] = $mPage;
        }

        if (isset($_REQUEST['tm_i_page'])) {
            $iPage = max(1, (int) wp_unslash($_REQUEST['tm_i_page']));
            $ctx['tm_i_page'] = $iPage;
        }

        if (isset($_REQUEST['tm_incident_status'])) {
            $status = sanitize_key((string) wp_unslash($_REQUEST['tm_incident_status']));
            if (in_array($status, ['', 'open', 'ack', 'resolved'], true)) {
                $ctx['tm_incident_status'] = $status;
            }
        }

        return $ctx;
    }

    private function sanitize_service_url(string $value): string
    {
        $url = trim($value);
        if ($url === '') {
            return self::DEFAULT_SERVICE_URL;
        }

        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }

        $url = esc_url_raw($url, ['http', 'https']);
        if (!is_string($url) || $url === '') {
            return self::DEFAULT_SERVICE_URL;
        }

        return rtrim($url, "/ \t\n\r\0\x0B");
    }

    private function sanitize_api_token(string $value): string
    {
        $v = trim($value);
        if ($v === '') {
            return '';
        }

        $v = preg_replace('/[^\x21-\x7E]/', '', $v);
        $v = is_string($v) ? $v : '';
        if ($v === '') {
            return '';
        }

        return substr($v, 0, 1024);
    }

    private function encrypt_token(string $plain): string
    {
        $plain = trim($plain);
        if ($plain === '') {
            return '';
        }

        $key = $this->get_crypto_key();
        if ($key === '') {
            return $plain;
        }

        if (function_exists('sodium_crypto_secretbox') && defined('SODIUM_CRYPTO_SECRETBOX_NONCEBYTES')) {
            $nonceLen = (int) SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
            $nonce = $this->random_bytes_safe($nonceLen);
            if ($nonce !== '') {
                $cipher = sodium_crypto_secretbox($plain, $nonce, $key);
                if (is_string($cipher) && $cipher !== '') {
                    return 'enc:sb1:' . base64_encode($nonce . $cipher);
                }
            }
        }

        if (!function_exists('openssl_encrypt')) {
            return $plain;
        }

        $iv = $this->random_bytes_safe(12);
        if ($iv !== '') {
            $tag = '';
            $enc = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
            if (is_string($enc) && $enc !== '' && is_string($tag) && strlen($tag) === 16) {
                return 'enc:v2:' . base64_encode($iv . $tag . $enc);
            }
        }

        $iv = $this->random_bytes_safe(16);
        if ($iv === '') {
            return $plain;
        }

        $enc = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        if (!is_string($enc) || $enc === '') {
            return $plain;
        }

        $mac = hash_hmac('sha256', $iv . $enc, $key, true);
        return 'enc:v1r:' . base64_encode($iv . $mac . $enc);
    }

    private function decrypt_token(string $stored): string
    {
        $stored = trim($stored);
        if ($stored === '') {
            return '';
        }

        if (strpos($stored, 'enc:sb1:') !== 0 && strpos($stored, 'enc:v2:') !== 0 && strpos($stored, 'enc:v1r:') !== 0 && strpos($stored, 'enc:v1:') !== 0) {
            return $stored;
        }

        $key = $this->get_crypto_key();
        if ($key === '') {
            return '';
        }

        if (strpos($stored, 'enc:sb1:') === 0 && function_exists('sodium_crypto_secretbox_open') && defined('SODIUM_CRYPTO_SECRETBOX_NONCEBYTES')) {
            $raw = base64_decode(substr($stored, 8), true);
            $nonceLen = (int) SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
            if (!is_string($raw) || strlen($raw) <= $nonceLen) {
                return '';
            }
            $nonce = substr($raw, 0, $nonceLen);
            $cipher = substr($raw, $nonceLen);
            if ($nonce === '' || $cipher === '') {
                return '';
            }
            $plain = sodium_crypto_secretbox_open($cipher, $nonce, $key);
            return is_string($plain) ? trim($plain) : '';
        }

        if (!function_exists('openssl_decrypt')) {
            return '';
        }

        if (strpos($stored, 'enc:v2:') === 0) {
            $raw = base64_decode(substr($stored, 7), true);
            if (!is_string($raw) || strlen($raw) < 29) {
                return '';
            }
            $iv = substr($raw, 0, 12);
            $tag = substr($raw, 12, 16);
            $cipher = substr($raw, 28);
            if ($iv === '' || $tag === '' || $cipher === '') {
                return '';
            }
            $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
            return is_string($plain) ? trim($plain) : '';
        }

        if (strpos($stored, 'enc:v1r:') === 0) {
            $raw = base64_decode(substr($stored, 8), true);
            if (!is_string($raw) || strlen($raw) < 49) {
                return '';
            }
            $iv = substr($raw, 0, 16);
            $mac = substr($raw, 16, 32);
            $cipher = substr($raw, 48);
            if ($iv === '' || $mac === '' || $cipher === '') {
                return '';
            }
            $calcMac = hash_hmac('sha256', $iv . $cipher, $key, true);
            if (!hash_equals($calcMac, $mac)) {
                return '';
            }
            $plain = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
            return is_string($plain) ? trim($plain) : '';
        }

        $raw = base64_decode(substr($stored, 7), true);
        if (!is_string($raw) || $raw === '') {
            return '';
        }

        // Legacy decrypt for early v1 format with deterministic IV.
        $iv = substr(hash('sha256', (string) wp_salt('nonce'), true), 0, 16);
        $plain = openssl_decrypt($raw, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);

        return is_string($plain) ? trim($plain) : '';
    }

    private function get_service_url(): string
    {
        $raw = (string) get_option(self::OPT_SERVICE_URL, self::DEFAULT_SERVICE_URL);
        return $this->sanitize_service_url($raw);
    }

    private function set_service_url(string $url): void
    {
        update_option(self::OPT_SERVICE_URL, $this->sanitize_service_url($url));
    }

    private function get_api_token(): string
    {
        $raw = (string) get_option(self::OPT_API_TOKEN, '');
        if ($raw === '') {
            return '';
        }

        $plain = $this->decrypt_token($raw);
        if ($plain === '') {
            return '';
        }

        if (strpos($raw, 'enc:v1:') !== 0) {
            $this->set_api_token($plain);
        }

        return $plain;
    }

    private function set_api_token(string $token): void
    {
        $token = $this->sanitize_api_token($token);
        if ($token === '') {
            delete_option(self::OPT_API_TOKEN);
            return;
        }

        update_option(self::OPT_API_TOKEN, $this->encrypt_token($token));
    }

    private function is_connected(): bool
    {
        $token = $this->get_api_token();
        if ($token === '') {
            return false;
        }

        $summary = $this->get_last_summary();
        $authOk = $summary['auth_ok'] ?? null;
        $authStatus = isset($summary['auth_status']) ? (int) $summary['auth_status'] : 0;
        $checkedAt = isset($summary['fetched_at']) ? (int) $summary['fetched_at'] : 0;

        if ($authOk === false && in_array($authStatus, [401, 403], true)) {
            if ($checkedAt <= 0 || (time() - $checkedAt) <= 86400) {
                return false;
            }
        }

        return true;
    }

    private function get_last_summary(): array
    {
        $raw = get_option(self::OPT_LAST_SUMMARY, []);
        return is_array($raw) ? $raw : [];
    }

    private function set_last_summary(array $summary): void
    {
        $summary['fetched_at'] = time();
        update_option(self::OPT_LAST_SUMMARY, $summary);
    }

    private function clear_connection_data(): void
    {
        delete_option(self::OPT_API_TOKEN);
        delete_option(self::OPT_LAST_SUMMARY);
    }

    private function admin_redirect_with_message(string $message, bool $isError = false, ?string $preferredTab = null): void
    {
        $message = $this->trim_notice_message($message);
        if ($message === '') {
            $message = $isError ? __('Request failed.', 'trumonit-monitor') : __('Summary refreshed.', 'trumonit-monitor');
        }

        $args = [
            'page' => self::MENU_SLUG,
            'tm_msg' => $message,
            'tm_err' => $isError ? '1' : '0',
        ];
        $args = array_merge($args, $this->redirect_context($preferredTab));

        $url = add_query_arg(
            $args,
            admin_url('admin.php')
        );

        wp_safe_redirect($url);
        exit;
    }
}
