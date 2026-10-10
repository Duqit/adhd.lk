<?php
// includes/i18n.php — lightweight translation layer.
// Language priority: ?lang= param (also saves a 1-year cookie) → cookie → English.
// t('key') returns the string in the active language, falling back to English.

// One host only: www.adhd.lk served a full duplicate of the site. Every public page
// loads this file, so the 301 lives here (the .htaccess host rule never matched).
if (!empty($_SERVER['HTTP_HOST']) && stripos($_SERVER['HTTP_HOST'], 'www.') === 0 && !headers_sent()) {
    header('Location: https://adhd.lk' . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
    exit;
}

if (!function_exists('t')) {

    $ADHD_LANGS = ['en' => 'English', 'si' => 'සිංහල', 'ta' => 'தமிழ்'];

    $lang = 'en';
    if (isset($_GET['lang']) && isset($ADHD_LANGS[$_GET['lang']])) {
        $lang = $_GET['lang'];
        // Persist for a year (path=/ so every page sees it). Only when headers not sent,
        // and only when it changes — a Set-Cookie header makes the page uncacheable.
        if (!headers_sent() && ($_COOKIE['adhd_lang'] ?? '') !== $lang) {
            setcookie('adhd_lang', $lang, time() + 31536000, '/');
        }
    } elseif (isset($_COOKIE['adhd_lang']) && isset($ADHD_LANGS[$_COOKIE['adhd_lang']])) {
        $lang = $_COOKIE['adhd_lang'];
    }

    $GLOBALS['ADHD_LANG'] = $lang;
    $GLOBALS['ADHD_LANGS'] = $ADHD_LANGS;

    $GLOBALS['ADHD_STR_EN'] = require __DIR__ . '/../lang/en.php';
    $GLOBALS['ADHD_STR'] = ($lang === 'en')
        ? $GLOBALS['ADHD_STR_EN']
        : array_merge($GLOBALS['ADHD_STR_EN'], require __DIR__ . '/../lang/' . $lang . '.php');

    function t($key) {
        return isset($GLOBALS['ADHD_STR'][$key]) ? $GLOBALS['ADHD_STR'][$key] : $key;
    }

    // Array-valued entries (journal prompts, ASRS questions)
    function t_arr($key) {
        $v = t($key);
        return is_array($v) ? $v : [];
    }

    function current_lang() { return $GLOBALS['ADHD_LANG']; }

    // Strings needed by the JS layer (guest scripts, runner, FAB, toasts).
    function i18n_js_payload() {
        $keys = [
            'js_start', 'js_pause', 'js_resume', 'js_logged', 'js_log_distraction',
            'js_next_step', 'js_next_finish', 'js_finish_routine', 'js_routine_complete',
            'js_great_job', 'js_return', 'js_next', 'js_step_ph', 'js_min_ph',
            'js_check_in', 'js_done', 'js_streak', 'js_day', 'js_days', 'js_est',
            'js_routine_saved', 'js_saved', 'js_pick_mood', 'js_synced',
            'js_confirm_del_routine', 'js_confirm_del_habit', 'js_confirm_quit',
            'js_no_steps', 'js_dump_title', 'js_dump_sub', 'js_dump_ph', 'js_dump_save',
            'js_dump_saved', 'js_breath_title', 'js_breath_sub', 'js_breath_ready',
            'js_breath_start', 'js_breath_stop', 'js_inhale', 'js_hold', 'js_exhale',
            'js_tpl_morning', 'js_tpl_shutdown', 'js_tpl_desk',
            'rout_total', 'rout_mins', 'rout_start',
            'js_session_done', 'js_break_done', 'js_stats', 'js_exit_fs', 'js_focus_label'
        ];
        $out = [];
        foreach ($keys as $k) { $out[$k] = t($k); }
        return json_encode($out, JSON_UNESCAPED_UNICODE);
    }
}
