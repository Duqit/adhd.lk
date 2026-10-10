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
