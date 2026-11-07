<?php
// db.php
// Database connection. Credentials live in includes/config.php (not in git);
// copy includes/config.sample.php to create it.
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/boot.php';

try {
    // Create PDO connection
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS);

    // Set PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Set default fetch mode to associative array
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    unset($dsn);

} catch(PDOException $e) {
    // Never expose the DSN / credentials / schema to visitors.
    error_log('DB connection failed: ' . $e->getMessage());
    http_response_code(503);
    die("The site is temporarily unavailable. Please try again in a moment.");
}

// A signed-in session is tied to the account's password hash at sign-in time
// ($_SESSION['pw_fp'], set by login/register/google_auth). Changing or resetting the
// password, or deleting the account, therefore signs out every other device.
if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['user_id'])) {
    $fp_stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
    $fp_stmt->execute([(int)$_SESSION['user_id']]);
    $fp_hash = $fp_stmt->fetchColumn();
    if ($fp_hash === false || empty($_SESSION['pw_fp'])
        || !hash_equals(adhd_pw_fingerprint($fp_hash), (string)$_SESSION['pw_fp'])) {
        $_SESSION = [];
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        session_destroy();
        if (strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') === 0) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Not logged in']);
        } else {
            header('Location: /login');
        }
        exit;
    }
    unset($fp_stmt, $fp_hash);
}

function verify_recaptcha($token) {
    $secret = defined('RECAPTCHA_SECRET') ? RECAPTCHA_SECRET : '';
    if ($secret === '') {
        // No key configured: allow it only on a local development machine.
        $local = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
        if (!$local) error_log('reCAPTCHA secret missing in includes/config.php — rejecting.');
        return $local;
    }
    $verify_url = "https://www.google.com/recaptcha/api/siteverify";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $verify_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'secret' => $secret,
        'response' => $token,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    $response = curl_exec($ch);
    $curl_failed = ($response === false);
    curl_close($ch);

    // Fail OPEN if Google is unreachable — otherwise an outage on their side
    // would block every login and registration site-wide. Rate limiting is the
    // real brute-force defence; reCAPTCHA is a secondary signal.
    if ($curl_failed) {
        error_log('reCAPTCHA verify unreachable — allowing request through.');
        return true;
    }

    $result = json_decode($response);
    return ($result && isset($result->success) && $result->success && isset($result->score) && $result->score >= 0.5);
}
?>
