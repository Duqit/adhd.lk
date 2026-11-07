<?php
/**
 * includes/boot.php — must be required BEFORE any session_start().
 *
 * The host ignores .user.ini and does not permit `Header` / `Require`
 * directives in .htaccess, so session cookie flags and security headers
 * are set here in PHP instead.
 */

if (!defined('ADHD_BOOT')) {
    define('ADHD_BOOT', true);

    $adhd_https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
        || (($_SERVER['SERVER_PORT'] ?? '') == 443);

    // --- Session cookie hardening (must precede session_start) ---
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        if (PHP_VERSION_ID >= 70300) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path'     => '/',
                'secure'   => $adhd_https,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        } else {
            session_set_cookie_params(0, '/; samesite=Lax', '', $adhd_https, true);
        }
    }

    // --- Security headers ---
    if (!headers_sent()) {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=()');
        if ($adhd_https) {
            header('Strict-Transport-Security: max-age=31536000');
        }
    }

    // --- Never leak PHP errors to visitors ---
    @ini_set('display_errors', '0');
    @ini_set('log_errors', '1');

    // --- Error log the site owner can read ---
    // The host's own PHP log is root-only. If <site home>/app-logs exists (outside
    // public_html, so never web-reachable), log there instead; rotate at 5 MB.
    // Without that directory (local development) PHP's default log is used.
    $adhd_log_dir = dirname(__DIR__, 2) . '/app-logs';
    if (is_dir($adhd_log_dir) && is_writable($adhd_log_dir)) {
        $adhd_log = $adhd_log_dir . '/php_errors.log';
        if (@filesize($adhd_log) > 5 * 1024 * 1024) {
            @rename($adhd_log, $adhd_log . '.1');
        }
        @ini_set('error_log', $adhd_log);
    }
    unset($adhd_log_dir, $adhd_log);

    // Published Cloudflare edge ranges (https://www.cloudflare.com/ips/).
    if (!function_exists('adhd_cloudflare_ranges')) {
        function adhd_cloudflare_ranges() {
            return [
                '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
                '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
                '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
                '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
                '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
                '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
            ];
        }
    }

    if (!function_exists('adhd_ip_in_cidr')) {
        function adhd_ip_in_cidr($ip, $cidr) {
            list($net, $bits) = explode('/', $cidr);
            $ip_bin = @inet_pton($ip);
            $net_bin = @inet_pton($net);
            if ($ip_bin === false || $net_bin === false || strlen($ip_bin) !== strlen($net_bin)) return false;
            $bits = (int)$bits;
            $bytes = intdiv($bits, 8);
            if (substr($ip_bin, 0, $bytes) !== substr($net_bin, 0, $bytes)) return false;
            $rem = $bits % 8;
            if ($rem === 0) return true;
            $mask = (0xFF << (8 - $rem)) & 0xFF;
            return (ord($ip_bin[$bytes]) & $mask) === (ord($net_bin[$bytes]) & $mask);
        }
    }

    /** True when the connection came from Cloudflare or from a proxy on this machine/LAN. */
    function adhd_request_via_proxy() {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!filter_var($remote, FILTER_VALIDATE_IP)) return false;
        if (!filter_var($remote, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) return true;
        foreach (adhd_cloudflare_ranges() as $cidr) {
            if (adhd_ip_in_cidr($remote, $cidr)) return true;
        }
        return false;
    }

    // --- Requests that bypass Cloudflare ---
    // Visitors always arrive through Cloudflare; a request straight to the server's IP
    // skips its protection. For now these are only logged. Once the log shows real
    // traffic is never flagged, set define('ADHD_CF_ONLY', true) in config.php to refuse them.
    if (PHP_SAPI !== 'cli' && !adhd_request_via_proxy()) {
        $cfg = __DIR__ . '/config.php';
        if (is_file($cfg)) require_once $cfg;
        if (defined('ADHD_CF_ONLY') && ADHD_CF_ONLY) {
            http_response_code(403);
            exit('Forbidden');
        }
        error_log('origin-direct: ' . ($_SERVER['REMOTE_ADDR'] ?? '?') . ' '
            . ($_SERVER['REQUEST_METHOD'] ?? '') . ' ' . substr($_SERVER['REQUEST_URI'] ?? '', 0, 200)
            . ' ua=' . substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 80));
        unset($cfg);
    }

    /** What a session stores to prove it was created with the account's current password. */
    function adhd_pw_fingerprint($password_hash) {
        return hash('sha256', (string)$password_hash);
    }

    /**
     * Start the session only for visitors who already have one (logged in, or
     * mid-way through a form). Anonymous visitors get no PHPSESSID cookie, so
     * their pages can be cached by Cloudflare. Pages that must have a session
     * (login, register, CSRF-protected forms) still call session_start() directly.
     */
    function adhd_session_start() {
        if (session_status() === PHP_SESSION_NONE && !empty($_COOKIE[session_name()])) {
            session_start();
        }
    }

    /**
     * Mark a page as shareable by the CDN when nothing on it is per-visitor:
     * no session, no cookie being set, a plain GET that succeeded. Browsers
     * still revalidate every time (max-age=0); only the edge keeps a copy.
     */
    function adhd_public_cache_headers() {
        if (headers_sent() || session_status() === PHP_SESSION_ACTIVE) return;
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' || http_response_code() !== 200) return;
        // Language picked from the adhd_lang cookie (not the URL) would put a
        // Sinhala/Tamil page into the cache under the English URL.
        if (!isset($_GET['lang']) && ($GLOBALS['ADHD_LANG'] ?? 'en') !== 'en') return;
        foreach (headers_list() as $h) {
            if (stripos($h, 'Set-Cookie:') === 0) return;
        }
        header('Cache-Control: public, max-age=0, s-maxage=600');
    }
}
