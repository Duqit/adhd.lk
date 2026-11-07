<?php
// includes/ratelimit.php — sliding-window throttling for auth endpoints.
// Backed by the `login_attempts` table; degrades gracefully (never blocks)
// if the table is missing so a failed migration can't lock everyone out.

require_once __DIR__ . '/boot.php'; // adhd_request_via_proxy() and the Cloudflare ranges

if (!function_exists('client_ip')) {

    /**
     * The visitor's IP. CF-Connecting-IP is only believed when the request
     * really came through Cloudflare (or a local proxy in front of PHP);
     * anyone hitting the origin IP directly could otherwise send a fresh fake
     * address on every request and never be rate limited.
     */
    function client_ip() {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!filter_var($remote, FILTER_VALIDATE_IP)) return '0.0.0.0';

        if (adhd_request_via_proxy() && !empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ip = trim($_SERVER['HTTP_CF_CONNECTING_IP']);
            if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
        }
        return $remote;
    }

    /** Record one failed/throttled attempt. */
    function record_attempt($pdo, $username = null) {
        try {
            $stmt = $pdo->prepare("INSERT INTO login_attempts (ip, username, attempted_at) VALUES (?, ?, NOW())");
            $stmt->execute([client_ip(), $username !== null ? mb_substr($username, 0, 255) : null]);
        } catch (PDOException $e) { /* table missing — ignore */ }
    }

    /**
     * True when this IP (or this username) has exceeded $max attempts
     * within the last $minutes.
     */
    function too_many_attempts($pdo, $username = null, $max = 6, $minutes = 15) {
        try {
            $stmt = $pdo->prepare(
                "SELECT COUNT(*) FROM login_attempts
                 WHERE attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
                   AND (ip = ? OR (username IS NOT NULL AND username = ?))"
            );
            $stmt->execute([$minutes, client_ip(), $username]);
            return ((int)$stmt->fetchColumn()) >= $max;
        } catch (PDOException $e) {
            return false; // fail open — never lock the site out on a DB issue
        }
    }

    /** Clear the counter after a successful login. */
    function clear_attempts($pdo, $username = null) {
        try {
            $stmt = $pdo->prepare("DELETE FROM login_attempts WHERE ip = ? OR (username IS NOT NULL AND username = ?)");
            $stmt->execute([client_ip(), $username]);
            // Opportunistic cleanup of old rows (keeps the table small).
            $pdo->exec("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");
        } catch (PDOException $e) { /* ignore */ }
    }
}
