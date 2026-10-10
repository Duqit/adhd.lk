<?php
require_once __DIR__ . '/includes/boot.php';
session_start();
require_once 'db.php';
require_once 'includes/i18n.php';
require_once 'includes/ratelimit.php';
require_once 'includes/mailer.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard");
    exit;
}

$sent = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');

    if (too_many_attempts($pdo, 'reset:' . $email, 5, 30)) {
        $error = t('auth_err_throttled');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = t('reg_err_email');
    } else {
        record_attempt($pdo, 'reset:' . $email);

        $stmt = $pdo->prepare("SELECT id, username, password_hash, google_id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        // Only send for accounts that actually have a password to reset.
        // Google-only accounts sign in without one.
        if ($user && empty($user['google_id'])) {
            try {
                $token = bin2hex(random_bytes(32));
                $hash = hash('sha256', $token);

                // Invalidate any earlier unused tokens for this user.
                $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL")
                    ->execute([$user['id']]);

                $pdo->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))")
                    ->execute([$user['id'], $hash]);

                $link = 'https://adhd.lk/reset?token=' . $token;
                $subject = t('reset_mail_subject');
                $body = str_replace(
                    ['{username}', '{link}'],
                    [$user['username'], $link],
                    t('reset_mail_body')
                );

                $html = adhd_mail_template(
                    $subject,
                    '<p>' . nl2br(htmlspecialchars($body)) . '</p>'
                    . '<p><a href="' . htmlspecialchars($link) . '" style="background:#6366f1;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none;display:inline-block;">Reset your password</a></p>',
                    'This link expires in one hour. If you did not request it, you can ignore this email.'
                );
                adhd_send_mail($email, $subject, $html);
            } catch (Exception $e) {
                error_log('password reset: ' . $e->getMessage());
            }
        }

        // Always the same response — never reveal whether an account exists.
        $sent = true;
    }
}
?>
<?php
$page_title = t('seo_forgot_title');
$page_noindex = true; // account page: keep out of search results
$page_desc = t('seo_forgot_desc');
require_once "includes/header.php";
?>

    <main class="main-content container">
        <div class="auth-container glass-card">
            <h2 style="text-align: center;"><?php echo t('forgot_title'); ?></h2>

            <?php if ($sent): ?>
                <div class="message" style="margin-top: 1.5rem;"><?php echo t('forgot_sent'); ?></div>
                <p style="color: var(--text-secondary); font-size: 0.9rem;"><?php echo t('forgot_sent_hint'); ?></p>
                <a href="/login" class="auth-link" style="text-align: center;"><?php echo t('forgot_back_login'); ?></a>
            <?php else: ?>
                <p style="color: var(--text-secondary); font-size: 0.95rem; margin-bottom: 1.5rem; text-align: center;"><?php echo t('forgot_intro'); ?></p>

                <?php if ($error): ?>
                    <div class="error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label><?php echo t('auth_email'); ?></label>
                        <input type="email" name="email" class="form-control" required autocomplete="email">
                    </div>
                    <button type="submit" class="btn btn-primary btn-full"><?php echo t('forgot_btn'); ?></button>
                </form>

                <a href="/login" class="auth-link" style="text-align: center;"><?php echo t('forgot_back_login'); ?></a>
            <?php endif; ?>
        </div>
    </main>

<?php require_once 'includes/footer.php'; ?>
