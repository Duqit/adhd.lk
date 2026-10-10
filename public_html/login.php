<?php
require_once __DIR__ . '/includes/boot.php';
session_start();
require_once 'db.php';
require_once 'includes/i18n.php';
require_once 'includes/ratelimit.php';

// Redirect to dashboard if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard");
    exit;
}

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $recaptcha_response = $_POST['recaptcha_response'] ?? '';

    if (too_many_attempts($pdo, $username)) {
        $error = t('auth_err_throttled');
    } elseif (empty($username) || empty($password)) {
        $error = t('auth_err_empty');
    } elseif (empty($recaptcha_response) || !verify_recaptcha($recaptcha_response)) {
        // Count these too — a bot that never solves reCAPTCHA should still be
        // throttled rather than hammering the endpoint indefinitely.
        record_attempt($pdo, $username);
        $error = t('auth_err_recaptcha');
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password_hash, role FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // Password is correct, start a new session
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            clear_attempts($pdo, $username);

            header("Location: dashboard");
            exit;
        } else {
            // Identical message whether or not the account exists (no enumeration).
            record_attempt($pdo, $username);
            $error = t('auth_err_invalid');
        }
    }
}
?>
<?php
$page_title = t('seo_login_title');
$page_noindex = true; // account page: keep out of search results
$page_desc = t('seo_login_desc');
require_once "includes/header.php";
?>

    <main class="main-content container">
        <div class="auth-container glass-card">
            <h2 style="text-align: center;"><?php echo t('auth_welcome'); ?></h2>

            <script src="https://accounts.google.com/gsi/client" async defer></script>
            <script>
            function handleGoogleLogin(response) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = 'google_auth';

                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'credential';
                input.value = response.credential;

                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
            </script>
            <div id="g_id_onload"
                 data-client_id="776109473102-l9stq5md476nanlq6jf2jgho47aa96q0.apps.googleusercontent.com"
                 data-context="signin"
                 data-ux_mode="popup"
                 data-callback="handleGoogleLogin"
                 data-auto_prompt="false">
            </div>

            <div class="g_id_signin"
                 data-type="standard"
                 data-shape="rectangular"
                 data-theme="outline"
                 data-text="signin_with"
                 data-size="large"
                 data-logo_alignment="left"
                 style="display: flex; justify-content: center; margin-bottom: 1.5rem;">
            </div>

            <div style="text-align: center; margin-bottom: 1.5rem; color: var(--text-secondary); font-size: 0.9rem;">
                <span style="background: var(--bg-primary); padding: 0 10px;"><?php echo t('auth_or'); ?></span>
            </div>

            <?php if ($error): ?>
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm">
                <input type="hidden" name="recaptcha_response" id="recaptchaResponse">
                <div class="form-group">
                    <label><?php echo t('auth_username'); ?></label>
                    <input type="text" name="username" class="form-control" required autocomplete="username">
                </div>
                <div class="form-group">
                    <label><?php echo t('auth_password'); ?></label>
                    <input type="password" name="password" class="form-control" required autocomplete="current-password">
                </div>
                <button type="submit" class="btn btn-primary btn-full"><?php echo t('auth_login'); ?></button>
            </form>

            <a href="/forgot" class="auth-link" style="text-align: center; font-size: 0.9rem;"><?php echo t('auth_forgot'); ?></a>
            <a href="/register" class="auth-link" style="text-align: center;"><?php echo t('auth_no_account'); ?></a>
        </div>
    </main>

    <script src="https://www.google.com/recaptcha/api.js?render=6LdNfi8tAAAAAMbitRZfprzCA9An1G8V7dR7JJBW"></script>
    <script>
        // Mint the token at submit time — v3 tokens expire after ~2 minutes, so
        // generating it on page load fails anyone who fills the form slowly.
        (function () {
            var form = document.getElementById('loginForm');
            var submitted = false;
            form.addEventListener('submit', function (e) {
                if (submitted) return;
                e.preventDefault();
                grecaptcha.ready(function () {
                    grecaptcha.execute('6LdNfi8tAAAAAMbitRZfprzCA9An1G8V7dR7JJBW', { action: 'login' })
                        .then(function (token) {
                            document.getElementById('recaptchaResponse').value = token;
                            submitted = true;
                            form.submit();
                        })
                        .catch(function () { submitted = true; form.submit(); });
                });
            });
        })();
    </script>
<?php require_once 'includes/footer.php'; ?>
