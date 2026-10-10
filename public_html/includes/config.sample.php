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
