<?php
// includes/mailer.php — outbound email for the site (contact form, password resets).
//
// Why not PHP mail(): adhd.lk publishes "v=spf1 include:_spf.mx.cloudflare.net ~all"
// with DMARC p=reject, so mail sent from the LiteSpeed host fails SPF and DKIM
// alignment and is REJECTED by Gmail/Outlook. Password resets were dying this way.
//
// Why SMTP rather than Cloudflare's REST API: the REST API does not carry a charset,
// so Sinhala/Tamil subjects and bodies arrive as "?????". Over SMTP we build the MIME
// message ourselves — base64 body parts with charset=UTF-8 and an RFC 2047 subject —
// so non-Latin text survives intact. Verified from this host: connect ~90ms, AUTH OK.
//
// Transports, in order of preference:
//   1. SMTP   — smtp.mx.cloudflare.net:465 (user "api_token", password = API token).
//   2. Worker — the adhd-mailer Worker, if ADHD_MAIL_ENDPOINT + ADHD_MAIL_TOKEN are set.
//   3. REST   — Cloudflare's API as a last resort; non-ASCII is converted to HTML
//               entities first so the body still reads correctly.
//
// Configure by copying includes/mail_config.sample.php to includes/mail_config.php.

if (!function_exists('adhd_send_mail')) {

    function adhd_mail_load_config() {
        static $loaded = false;
        if (!$loaded) {
            if (is_readable(__DIR__ . '/mail_config.php')) {
                require_once __DIR__ . '/mail_config.php';
            }
            $loaded = true;
        }
    }

    function adhd_mail_configured() {
        adhd_mail_load_config();
        $relay = defined('ADHD_MAIL_ENDPOINT') && ADHD_MAIL_ENDPOINT !== ''
              && defined('ADHD_MAIL_TOKEN') && ADHD_MAIL_TOKEN !== '';
        $direct = defined('CF_ACCOUNT_ID') && CF_ACCOUNT_ID !== ''
               && defined('CF_EMAIL_TOKEN') && CF_EMAIL_TOKEN !== '';
        return $relay || $direct;
    }

    /** Plain-text alternative, so messages are not HTML-only (helps deliverability). */
    function adhd_html_to_text($html) {
        $t = preg_replace('#<(script|style)[\s\S]*?</\1>#i', ' ', $html);
        $t = preg_replace('#</(p|div|h[1-6]|li|tr)>#i', "\n", $t);
        $t = preg_replace('#<br\s*/?>#i', "\n", $t);
        $t = strip_tags($t);
        $t = html_entity_decode($t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = preg_replace("/\n{3,}/", "\n\n", $t);
        return trim(preg_replace('/[ \t]{2,}/', ' ', $t));
    }

    /** Split "Name <addr>" into [name, address]. */
    function adhd_mail_split_from($from) {
        if (preg_match('/^\s*(.*?)\s*<([^>]+)>\s*$/', $from, $m)) {
            return [trim($m[1], " \"'"), trim($m[2])];
        }
        return ['', trim($from)];
    }

    /** RFC 2047 encoded-word, so non-Latin subjects and display names survive. */
    function adhd_mail_encode_header($text) {
        // No CR/LF: a contact-form name like "x\r\nBcc: ..." must not add headers.
        $text = str_replace(["\r", "\n"], ' ', (string)$text);
        return preg_match('/[\x80-\xFF]/', $text)
            ? '=?UTF-8?B?' . base64_encode($text) . '?='
            : $text;
    }

    /** Build a multipart/alternative MIME message with UTF-8 base64 parts. */
    function adhd_mail_build_mime($from, $to, $subject, $html, $reply_to = null) {
        list($from_name, $from_addr) = adhd_mail_split_from($from);
        $boundary = 'b_' . bin2hex(random_bytes(12));
        $text = adhd_html_to_text($html);

        $head = [];
        $head[] = 'From: ' . ($from_name !== ''
            ? adhd_mail_encode_header($from_name) . ' <' . $from_addr . '>'
            : $from_addr);
        $head[] = 'To: ' . $to;
        if ($reply_to) $head[] = 'Reply-To: ' . $reply_to;
        $head[] = 'Subject: ' . adhd_mail_encode_header($subject);
        $head[] = 'Message-ID: <' . bin2hex(random_bytes(12)) . '@adhd.lk>';
        $head[] = 'Date: ' . date('r');
        $head[] = 'MIME-Version: 1.0';
        $head[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

        $body = "--$boundary\r\n"
              . "Content-Type: text/plain; charset=UTF-8\r\n"
              . "Content-Transfer-Encoding: base64\r\n\r\n"
              . chunk_split(base64_encode($text), 76, "\r\n")
              . "--$boundary\r\n"
              . "Content-Type: text/html; charset=UTF-8\r\n"
              . "Content-Transfer-Encoding: base64\r\n\r\n"
              . chunk_split(base64_encode($html), 76, "\r\n")
              . "--$boundary--\r\n";

        return implode("\r\n", $head) . "\r\n\r\n" . $body;
    }

    function adhd_smtp_read($fp) {
        $out = '';
        while (($line = fgets($fp, 2048)) !== false) {
            $out .= $line;
            if (preg_match('/^\d{3} /', $line)) break;
            $info = stream_get_meta_data($fp);
            if (!empty($info['timed_out'])) break;
        }
        return $out;
    }

    function adhd_smtp_cmd($fp, $cmd, $expect) {
        if ($cmd !== null) fwrite($fp, $cmd . "\r\n");
        $resp = adhd_smtp_read($fp);
        $code = (int)substr(ltrim($resp), 0, 3);
        if (!in_array($code, (array)$expect, true)) {
            throw new RuntimeException('SMTP ' . ($cmd === null ? 'greeting' : explode(' ', $cmd)[0])
                . ' got ' . $code . ': ' . trim(substr($resp, 0, 200)));
        }
        return $resp;
    }

    /** Transport 1: SMTP with a MIME message we control end to end. */
    function adhd_send_via_smtp($to, $subject, $html, $reply_to, $from) {
        $host = defined('ADHD_SMTP_HOST') ? ADHD_SMTP_HOST : 'ssl://smtp.mx.cloudflare.net';
        $port = defined('ADHD_SMTP_PORT') ? ADHD_SMTP_PORT : 465;
        $user = defined('ADHD_SMTP_USER') ? ADHD_SMTP_USER : 'api_token';
        $pass = defined('ADHD_SMTP_PASS') ? ADHD_SMTP_PASS : (defined('CF_EMAIL_TOKEN') ? CF_EMAIL_TOKEN : '');
        if ($pass === '') return false;

        list(, $from_addr) = adhd_mail_split_from($from);
        $fp = @stream_socket_client($host . ':' . $port, $errno, $errstr, 12);
        if (!$fp) {
            error_log('adhd_send_mail(smtp): connect failed ' . $errno . ' ' . $errstr);
            return false;
        }
        stream_set_timeout($fp, 20);

        try {
            adhd_smtp_cmd($fp, null, 220);
            adhd_smtp_cmd($fp, 'EHLO adhd.lk', 250);
            adhd_smtp_cmd($fp, 'AUTH PLAIN ' . base64_encode("\0" . $user . "\0" . $pass), 235);
            adhd_smtp_cmd($fp, 'MAIL FROM:<' . $from_addr . '>', 250);
            adhd_smtp_cmd($fp, 'RCPT TO:<' . $to . '>', [250, 251]);
            adhd_smtp_cmd($fp, 'DATA', 354);

            $mime = adhd_mail_build_mime($from, $to, $subject, $html, $reply_to);
            // Dot-stuffing, per RFC 5321.
            $mime = preg_replace('/^\./m', '..', $mime);
            fwrite($fp, $mime . "\r\n.\r\n");
            adhd_smtp_cmd($fp, null, 250);
            @adhd_smtp_cmd($fp, 'QUIT', [221, 250]);
            fclose($fp);
            return true;
        } catch (Throwable $e) {
            error_log('adhd_send_mail(smtp): ' . $e->getMessage());
            @fclose($fp);
            return false;
        }
    }

    function adhd_mail_post($url, $payload, $headers, $timeout = 15) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $headers),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 6,
        ]);
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        return [$code, $body, $err];
    }

    /** Non-ASCII → numeric HTML entities, so it survives the REST API's ASCII pipeline. */
    function adhd_mail_entities($html) {
        return preg_replace_callback('/[\x{0080}-\x{10FFFF}]/u', function ($m) {
            $cp = mb_ord($m[0], 'UTF-8');
            return $cp ? '&#' . $cp . ';' : '';
        }, $html);
    }

    /**
     * Send one email. Returns true only when a transport accepted it.
     *
     * @param string      $to       Recipient address.
     * @param string      $subject  Plain-text subject (any script; encoded automatically).
     * @param string      $html     HTML body.
     * @param string|null $reply_to Optional Reply-To address.
     */
    function adhd_send_mail($to, $subject, $html, $reply_to = null) {
        adhd_mail_load_config();

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            error_log('adhd_send_mail: invalid recipient ' . $to);
            return false;
        }
        if (!adhd_mail_configured()) {
            error_log('adhd_send_mail: mail_config.php missing or incomplete — nothing sent to ' . $to);
            return false;
        }

        $from = defined('ADHD_MAIL_FROM') && ADHD_MAIL_FROM !== '' ? ADHD_MAIL_FROM : 'ADHD.lk <noreply@adhd.lk>';
        $valid_reply = $reply_to && filter_var($reply_to, FILTER_VALIDATE_EMAIL) ? $reply_to : null;

        // 1. SMTP — keeps UTF-8 intact.
        if (defined('CF_EMAIL_TOKEN') && CF_EMAIL_TOKEN !== '') {
            if (adhd_send_via_smtp($to, $subject, $html, $valid_reply, $from)) return true;
        }

        // 2. Worker relay, when configured.
        if (defined('ADHD_MAIL_ENDPOINT') && ADHD_MAIL_ENDPOINT !== '' && defined('ADHD_MAIL_TOKEN') && ADHD_MAIL_TOKEN !== '') {
            $payload = ['to' => $to, 'subject' => $subject, 'html' => $html];
            if ($valid_reply) $payload['reply_to'] = $valid_reply;
            list($code, $body, $err) = adhd_mail_post(ADHD_MAIL_ENDPOINT, $payload, ['X-ADHD-Token: ' . ADHD_MAIL_TOKEN], 12);
            if ($code >= 200 && $code < 300) return true;
            error_log('adhd_send_mail(relay): HTTP ' . $code . ' to=' . $to . ' err=' . $err . ' body=' . substr((string)$body, 0, 300));
        }

        // 3. REST API — last resort. Entity-encode so non-Latin text still renders.
        if (defined('CF_ACCOUNT_ID') && CF_ACCOUNT_ID !== '' && defined('CF_EMAIL_TOKEN') && CF_EMAIL_TOKEN !== '') {
            $safe_html = adhd_mail_entities($html);
            $payload = [
                'to' => $to,
                'from' => $from,
                'subject' => mb_substr($subject, 0, 200),
                'html' => $safe_html,
                'text' => adhd_html_to_text($html),
            ];
            if ($valid_reply) $payload['reply_to'] = $valid_reply;
            $url = 'https://api.cloudflare.com/client/v4/accounts/' . CF_ACCOUNT_ID . '/email/sending/send';
            list($code, $body, $err) = adhd_mail_post($url, $payload, ['Authorization: Bearer ' . CF_EMAIL_TOKEN]);
            $data = json_decode((string)$body, true);
            if ($code >= 200 && $code < 300 && is_array($data) && !empty($data['success'])) {
                $result = isset($data['result']) && is_array($data['result']) ? $data['result'] : [];
                if (empty($result['permanent_bounces']) && empty($result['suppressed_recipients'])) {
                    error_log('adhd_send_mail: sent via REST fallback (subject may lose non-Latin characters) to=' . $to);
                    return true;
                }
                error_log('adhd_send_mail: recipient rejected ' . $to);
                return false;
            }
            $detail = is_array($data) && isset($data['errors']) ? json_encode($data['errors']) : substr((string)$body, 0, 300);
            error_log('adhd_send_mail(rest): failed (HTTP ' . $code . ') to=' . $to . ' err=' . $err . ' detail=' . $detail);
        }

        return false;
    }

    /** Minimal HTML wrapper so transactional mail looks like it came from the site. */
    function adhd_mail_template($heading, $body_html, $footer_note = '') {
        $h = htmlspecialchars($heading, ENT_QUOTES, 'UTF-8');
        $f = $footer_note !== '' ? '<p style="color:#888;font-size:12px;margin-top:24px;">' . $footer_note . '</p>' : '';
        return '<div style="font-family:Arial,Helvetica,sans-serif;font-size:15px;line-height:1.6;color:#222;max-width:560px;">'
             . '<p style="font-size:18px;font-weight:bold;color:#111;margin:0 0 16px;">' . $h . '</p>'
             . $body_html
             . $f
             . '<p style="color:#888;font-size:12px;margin-top:24px;border-top:1px solid #eee;padding-top:12px;">'
             . 'ADHD.lk — free ADHD tools and guides · <a href="https://adhd.lk" style="color:#6366f1;">adhd.lk</a></p>'
             . '</div>';
    }
}
