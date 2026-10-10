<?php
require_once __DIR__ . '/includes/boot.php';
session_start();
require_once 'db.php';
require_once 'includes/i18n.php';
require_once 'includes/ratelimit.php';

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $recaptcha_response = $_POST['recaptcha_response'] ?? '';

    if (too_many_attempts($pdo, $username, 10, 15)) {
        $error = t('auth_err_throttled');
    } elseif (empty($username) || empty($email) || empty($password)) {
        $error = t('reg_err_required');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = t('reg_err_email');
    } elseif (strlen($password) < 8) {
        $error = t('reg_err_pw_short');
    } elseif (empty($recaptcha_response) || !verify_recaptcha($recaptcha_response)) {
        record_attempt($pdo, $username);
        $error = t('auth_err_recaptcha');
    } else {
        // Check if username or email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->rowCount() > 0) {
            record_attempt($pdo, $username);
            $error = t('reg_err_exists');
        } else {
            // Insert new user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)");
            if ($stmt->execute([$username, $email, $hashed_password])) {
                // Log the new user in right away so their guest data syncs.
                session_regenerate_id(true);
                $_SESSION['user_id'] = $pdo->lastInsertId();
                $_SESSION['username'] = $username;
                $_SESSION['role'] = 'user';
                header("Location: dashboard");
                exit;
            } else {
                $error = t('reg_err_generic');
            }
        }
    }
}
?>
<?php
$page_title = t('seo_register_title');
$page_noindex = true; // account page: keep out of search results
$page_desc = t('seo_register_desc');
require_once "includes/header.php";
?>

    <main class="main-content container">
        <div class="auth-container glass-card">
            <h2 style="text-align: center;"><?php echo t('auth_create'); ?></h2>
            
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
                 data-context="signup"
                 data-ux_mode="popup"
                 data-callback="handleGoogleLogin"
                 data-auto_prompt="false">
            </div>

            <div class="g_id_signin"
                 data-type="standard"
                 data-shape="rectangular"
                 data-theme="outline"
                 data-text="signup_with"
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
            <?php if ($success): ?>
                <div class="success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <form method="POST" action="" id="registerForm">
                <input type="hidden" name="recaptcha_response" id="recaptchaResponse">
                <div class="form-group">
                    <label><?php echo t('auth_username'); ?></label>
                    <input type="text" name="username" class="form-control" required>
                </div>
                <div class="form-group">
                    <label><?php echo t('auth_email'); ?></label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label><?php echo t('auth_password'); ?></label>
                    <input type="password" name="password" class="form-control" required minlength="8" autocomplete="new-password">
                </div>
                <button type="submit" class="btn btn-primary btn-full"><?php echo t('auth_signup'); ?></button>
            </form>
            
            <a href="login" class="auth-link" style="text-align: center;"><?php echo t('auth_have_account'); ?></a>
        </div>
    </main>
    
    <script src="https://www.google.com/recaptcha/api.js?render=6LdNfi8tAAAAAMbitRZfprzCA9An1G8V7dR7JJBW"></script>
    <script>
        // Mint the token at submit time — v3 tokens expire after ~2 minutes.
        (function () {
            var form = document.getElementById('registerForm');
            var submitted = false;
            form.addEventListener('submit', function (e) {
                if (submitted) return;
                e.preventDefault();
                grecaptcha.ready(function () {
                    grecaptcha.execute('6LdNfi8tAAAAAMbitRZfprzCA9An1G8V7dR7JJBW', { action: 'register' })
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
