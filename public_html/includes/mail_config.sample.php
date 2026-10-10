<?php
// Copy this file to includes/mail_config.php and fill in your values.
// includes/ is blocked from the web by includes/.htaccess, so this file is not
// publicly readable — but never commit it or copy it outside the server.

// ---------------------------------------------------------------------------
// Option A (simplest, in use): this server calls the Cloudflare Email Sending API
// ---------------------------------------------------------------------------
define('CF_ACCOUNT_ID', 'your-cloudflare-account-id');
define('CF_EMAIL_TOKEN', 'your-cloudflare-api-token-with-email-sending-permission');

// ---------------------------------------------------------------------------
// Option B (optional, more secure): send through the adhd-mailer Worker instead,
// so the Cloudflare API token lives in Cloudflare's secret store rather than here.
// If both are set, the Worker is used.
// ---------------------------------------------------------------------------
// define('ADHD_MAIL_ENDPOINT', 'https://adhd-mailer.YOUR-SUBDOMAIN.workers.dev/send');
// define('ADHD_MAIL_TOKEN', 'the-same-long-random-string-set-on-the-worker');

// Sender shown to recipients. Must be an address on a domain in your Cloudflare account.
define('ADHD_MAIL_FROM', 'ADHD.lk <noreply@adhd.lk>');

// Where contact form messages are delivered.
define('ADHD_CONTACT_TO', 'support@adhd.lk');
