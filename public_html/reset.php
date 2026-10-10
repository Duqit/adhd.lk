<?php
require_once __DIR__ . '/includes/boot.php';
session_start();
require_once 'db.php';
require_once 'includes/i18n.php';
require_once 'includes/ratelimit.php';

$error = '';
$done = false;

// Token can arrive by query string (from the email) or in the POST body.
$token = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['token'] ?? '')
    : ($_GET['token'] ?? '');

$valid = false;
$reset_row = null;

if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    try {
        $stmt = $pdo->prepare(
            "SELECT id, user_id FROM password_resets
             WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()"
        );
        $stmt->execute([hash('sha256', $token)]);
        $reset_row = $stmt->fetch();
        $valid = (bool)$reset_row;
    } catch (PDOException $e) {
        error_log('reset lookup: ' . $e->getMessage());
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (too_many_attempts($pdo, 'resetuse', 15, 30)) {
        $error = t('auth_err_throttled');
    } elseif (!$valid) {
        $error = t('reset_invalid');
    } else {
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (strlen($new) < 8) {
            $error = t('set_pw_short');
        } elseif ($new !== $confirm) {
            $error = t('set_pw_mismatch');
        } else {
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
                ->execute([password_hash($new, PASSWORD_DEFAULT), $reset_row['user_id']]);
            $pdo->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?")
                ->execute([$reset_row['id']]);
            clear_attempts($pdo, 'resetuse');
            $done = true;
        }
    }
}
?>
<?php
$page_title = t('seo_reset_title');
$page_noindex = true; // account page: keep out of search results
$page_desc = t('seo_reset_desc');
require_once "includes/header.php";
?>

    <main class="main-content container">
        <div class="auth-container glass-card">
            <h2 style="text-align: center;"><?php echo t('reset_title'); ?></h2>

            <?php if ($done): ?>
                <div class="message" style="margin-top: 1.5rem;"><?php echo t('reset_done'); ?></div>
                <a href="/login" class="btn btn-primary btn-full" style="text-align: center;"><?php echo t('auth_login'); ?></a>

            <?php elseif (!$valid): ?>
                <div class="error" style="margin-top: 1.5rem;"><?php echo t('reset_invalid'); ?></div>
                <a href="/forgot" class="btn btn-secondary btn-full" style="text-align: center;"><?php echo t('forgot_btn'); ?></a>

            <?php else: ?>
                <p style="color: var(--text-secondary); font-size: 0.95rem; margin-bottom: 1.5rem; text-align: center;"><?php echo t('reset_intro'); ?></p>

                <?php if ($error): ?>
                    <div class="error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    <div class="form-group">
                        <label><?php echo t('set_pw_new'); ?></label>
                        <input type="password" name="new_password" class="form-control" required minlength="8" autocomplete="new-password">
                    </div>
                    <div class="form-group">
                        <label><?php echo t('set_pw_confirm'); ?></label>
                        <input type="password" name="confirm_password" class="form-control" required minlength="8" autocomplete="new-password">
                    </div>
                    <button type="submit" class="btn btn-primary btn-full"><?php echo t('reset_btn'); ?></button>
                </form>
            <?php endif; ?>
        </div>
    </main>

<?php require_once 'includes/footer.php'; ?>
