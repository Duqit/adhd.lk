<?php
require_once __DIR__ . '/includes/boot.php';
session_start();
require_once 'db.php';
require_once 'includes/i18n.php';
require_once 'includes/csrf.php';

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login");
    exit;
}
$user_id = (int)$_SESSION['user_id'];

// Fetch the user
$stmt = $pdo->prepare("SELECT id, username, email, role, xp, level, google_id, password_hash, created_at FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();
if (!$user) {
    session_destroy();
    header("Location: login");
    exit;
}
$is_google = !empty($user['google_id']);

$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    csrf_check();

    /* ---- Change password ---- */
    if ($_POST['action'] === 'change_password' && !$is_google) {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!password_verify($current, $user['password_hash'])) {
            $error = t('set_pw_wrong');
        } elseif (strlen($new) < 8) {
            $error = t('set_pw_short');
        } elseif ($new !== $confirm) {
            $error = t('set_pw_mismatch');
        } else {
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([password_hash($new, PASSWORD_DEFAULT), $user_id]);
            $message = t('set_pw_changed');
        }
    }

    /* ---- Export all user data as JSON ---- */
    if ($_POST['action'] === 'export_data') {
        $export = [
            'exported_at' => date('c'),
            'account' => [
                'username' => $user['username'],
                'email' => $user['email'],
                'level' => (int)$user['level'],
                'xp' => (int)$user['xp'],
                'member_since' => $user['created_at'],
            ],
        ];

        $queries = [
            'tasks' => "SELECT title, estimated_minutes, is_completed, created_at FROM tasks WHERE user_id = ? ORDER BY created_at",
            'moods' => "SELECT mood_score, note, created_at FROM moods WHERE user_id = ? ORDER BY created_at",
            'journal_entries' => "SELECT prompt, entry_text, created_at FROM journal_entries WHERE user_id = ? ORDER BY created_at",
            'habits' => "SELECT id, name, emoji, created_at FROM habits WHERE user_id = ? ORDER BY created_at",
            'habit_logs' => "SELECT habit_id, log_date FROM habit_logs WHERE user_id = ? ORDER BY log_date",
            'bookmarked_articles' => "SELECT a.title, a.id FROM user_bookmarks b JOIN articles a ON a.id = b.article_id WHERE b.user_id = ?",
        ];
        foreach ($queries as $key => $sql) {
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id]);
            $export[$key] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        // Routines with their steps
        $stmt = $pdo->prepare("SELECT id, name, created_at FROM routines WHERE user_id = ? ORDER BY created_at");
        $stmt->execute([$user_id]);
        $routines = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stepStmt = $pdo->prepare("SELECT title, duration_minutes, step_order FROM routine_steps WHERE routine_id = ? ORDER BY step_order");
        foreach ($routines as &$r) {
            $stepStmt->execute([$r['id']]);
            $r['steps'] = $stepStmt->fetchAll(PDO::FETCH_ASSOC);
        }
        unset($r);
        $export['routines'] = $routines;

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="adhd-lk-data-' . date('Y-m-d') . '.json"');
        echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
?>
<?php
$page_title = t('nav_settings') . ' | ADHD.lk';
require_once "includes/header.php";
?>

<main class="main-content container" style="max-width: 720px;">
    <div style="width: 100%;">
        <h1 style="margin-bottom: 2rem;"><?php echo t('nav_settings'); ?></h1>

        <?php if ($message): ?><div class="message"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>
        <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <!-- Account info -->
        <section class="glass-card" style="margin-bottom: 1.5rem;">
            <h2 style="font-size: 1.15rem; margin-bottom: 1.25rem;"><?php echo t('set_account'); ?></h2>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <div>
                    <div style="font-size: 0.8rem; color: var(--text-faint); text-transform: uppercase; letter-spacing: 0.05em;"><?php echo t('set_username'); ?></div>
                    <div style="font-weight: 600;"><?php echo htmlspecialchars($user['username']); ?></div>
                </div>
                <div>
                    <div style="font-size: 0.8rem; color: var(--text-faint); text-transform: uppercase; letter-spacing: 0.05em;"><?php echo t('set_email'); ?></div>
                    <div style="font-weight: 600; overflow-wrap: anywhere;"><?php echo htmlspecialchars($user['email']); ?></div>
                </div>
                <div>
                    <div style="font-size: 0.8rem; color: var(--text-faint); text-transform: uppercase; letter-spacing: 0.05em;"><?php echo t('set_joined'); ?></div>
                    <div style="font-weight: 600;"><?php echo date('M j, Y', strtotime($user['created_at'])); ?></div>
                </div>
                <div>
                    <div style="font-size: 0.8rem; color: var(--text-faint); text-transform: uppercase; letter-spacing: 0.05em;"><?php echo t('set_signin'); ?></div>
                    <div style="font-weight: 600;"><?php echo $is_google ? 'Google' : t('auth_password'); ?></div>
                </div>
                <div>
                    <div style="font-size: 0.8rem; color: var(--text-faint); text-transform: uppercase; letter-spacing: 0.05em;"><?php echo t('set_level'); ?></div>
                    <div style="font-weight: 600;"><?php echo (int)$user['level']; ?> · <?php echo (int)$user['xp']; ?> XP</div>
                </div>
            </div>
        </section>

        <!-- Change password -->
        <section class="glass-card" style="margin-bottom: 1.5rem;">
            <h2 style="font-size: 1.15rem; margin-bottom: 1.25rem;"><?php echo t('set_pw_title'); ?></h2>
            <?php if ($is_google): ?>
                <p style="color: var(--text-secondary); font-size: 0.95rem;"><?php echo t('set_google_note'); ?></p>
            <?php else: ?>
                <form method="POST" action="">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="change_password">
                    <div class="form-group">
                        <label><?php echo t('set_pw_current'); ?></label>
                        <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
                    </div>
                    <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                        <div class="form-group" style="flex: 1; min-width: 200px;">
                            <label><?php echo t('set_pw_new'); ?></label>
                            <input type="password" name="new_password" class="form-control" required minlength="8" autocomplete="new-password">
                        </div>
                        <div class="form-group" style="flex: 1; min-width: 200px;">
                            <label><?php echo t('set_pw_confirm'); ?></label>
                            <input type="password" name="confirm_password" class="form-control" required minlength="8" autocomplete="new-password">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary"><?php echo t('set_pw_btn'); ?></button>
                </form>
            <?php endif; ?>
        </section>

        <!-- Export data -->
        <section class="glass-card" style="margin-bottom: 1.5rem;">
            <h2 style="font-size: 1.15rem; margin-bottom: 0.75rem;"><?php echo t('set_export_title'); ?></h2>
            <p style="color: var(--text-secondary); font-size: 0.95rem; margin-bottom: 1.25rem;"><?php echo t('set_export_desc'); ?></p>
            <form method="POST" action="">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="export_data">
                <button type="submit" class="btn btn-secondary"><?php echo t('set_export_btn'); ?></button>
            </form>
        </section>

        <!-- Danger zone -->
        <section class="glass-card" style="border-color: rgba(248, 113, 113, 0.35);">
            <h2 style="font-size: 1.15rem; margin-bottom: 0.75rem; color: var(--color-work);"><?php echo t('set_danger'); ?></h2>
            <p style="color: var(--text-secondary); font-size: 0.95rem; margin-bottom: 1.25rem;"><?php echo t('set_danger_desc'); ?></p>
            <form action="/delete_account" method="POST" onsubmit="return confirm(<?php echo htmlspecialchars(json_encode(t('set_delete_confirm')), ENT_QUOTES); ?>);">
                <?php echo csrf_field(); ?>
                <button type="submit" class="btn btn-danger"><?php echo t('set_delete_btn'); ?></button>
            </form>
        </section>

        <div style="margin-top: 2rem;">
            <a href="/dashboard" class="btn btn-secondary"><?php echo t('set_back'); ?></a>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>
