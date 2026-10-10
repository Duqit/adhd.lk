<?php
require_once __DIR__ . '/includes/boot.php';
session_start();
// Expire the session cookie in the browser as well as destroying it server-side.
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
// Unset all of the session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page
header("Location: login");
exit;
?>
