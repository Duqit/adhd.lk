<?php
// includes/config.sample.php — copy to includes/config.php and fill in.
// config.php is in .gitignore; never commit real values.
// (Mail settings live separately in includes/mail_config.php — see mail_config.sample.php.)

// MySQL / MariaDB connection
define('DB_HOST', 'localhost');
define('DB_NAME', 'adhd');
define('DB_USER', 'adhd');
define('DB_PASS', 'change-me');

// Google reCAPTCHA v3 secret key (https://www.google.com/recaptcha/admin).
// Leave empty for local development: login/register then skip the check.
define('RECAPTCHA_SECRET', '');

// Refuse requests that reach this server without going through Cloudflare (403).
// Leave it off until app-logs/php_errors.log shows no "origin-direct" lines for
// normal visitors — see includes/boot.php.
// define('ADHD_CF_ONLY', true);
