<?php
// includes/ratelimit.php — sliding-window throttling for auth endpoints.
// Backed by the `login_attempts` table; degrades gracefully (never blocks)
// if the table is missing so a failed migration can't lock everyone out.

if (!function_exists('client_ip')) {

    // Published Cloudflare edge ranges (https://www.cloudflare.com/ips/).
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

    /**
     * The visitor's IP. CF-Connecting-IP is only believed when the request
     * really came through Cloudflare (or a local proxy in front of PHP);
     * anyone hitting the origin IP directly could otherwise send a fresh fake
     * address on every request and never be rate limited.
     */
    function client_ip() {
        $remote = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!filter_var($remote, FILTER_VALIDATE_IP)) return '0.0.0.0';

        $via_proxy = !filter_var($remote, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        if (!$via_proxy) {
            foreach (adhd_cloudflare_ranges() as $cidr) {
                if (adhd_ip_in_cidr($remote, $cidr)) { $via_proxy = true; break; }
            }
        }
        if ($via_proxy && !empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
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
