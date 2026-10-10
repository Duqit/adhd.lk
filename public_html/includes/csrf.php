<?php
// includes/csrf.php — minimal CSRF protection for state-changing forms.
if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/boot.php';
session_start();
}

if (!function_exists('csrf_token')) {
    function csrf_token() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(20));
        }
        return $_SESSION['csrf_token'];
    }

    function csrf_field() {
        return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
    }

    function csrf_check() {
        if (!isset($_POST['csrf']) || !is_string($_POST['csrf']) ||
            !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf'])) {
            http_response_code(403);
            $msg = function_exists('t') ? t('ui_csrf_error') : 'Your session expired. Please go back, refresh, and try again.';
            die(htmlspecialchars($msg));
        }
    }
}
