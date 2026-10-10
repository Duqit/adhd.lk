<?php
require_once __DIR__ . '/includes/boot.php';
adhd_session_start();

$is_logged_in = isset($_SESSION['user_id']);

// ---------------------------------------------------------
// LOGGED-IN MODE: server-side data (database)
// ---------------------------------------------------------
if ($is_logged_in) {
    require_once 'db.php';
    $user_id = $_SESSION['user_id'];
    $today = date('Y-m-d');

    function addXP($pdo, $user_id, $amount) {
        $stmt = $pdo->prepare("SELECT xp, level FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        if ($user) {
            $new_xp = $user['xp'] + $amount;
            $level = $user['level'];
            $xp_needed = $level * 100;
            if ($new_xp >= $xp_needed) {
                $level++;
                $new_xp -= $xp_needed;
            }
            $stmt = $pdo->prepare("UPDATE users SET xp = ?, level = ? WHERE id = ?");
            $stmt->execute([$new_xp, $level, $user_id]);
        }
    }

    // Add new Top 3 Task
    if (isset($_POST['action']) && $_POST['action'] === 'add_task') {
        $title = trim($_POST['title']);
        $est_mins = isset($_POST['est_mins']) && is_numeric($_POST['est_mins']) ? (int)$_POST['est_mins'] : null;
        if (!empty($title)) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ? AND created_at = ? AND is_completed = FALSE");
            $stmt->execute([$user_id, $today]);
            if ($stmt->fetchColumn() < 3) {
                $stmt = $pdo->prepare("INSERT INTO tasks (user_id, title, estimated_minutes, created_at) VALUES (?, ?, ?, ?)");
                $stmt->execute([$user_id, $title, $est_mins, $today]);
            }
        }
        header("Location: dashboard");
        exit;
    }

    // Toggle Task Completion
    if (isset($_POST['action']) && $_POST['action'] === 'toggle_task') {
        $task_id = (int)$_POST['task_id'];
        $stmt = $pdo->prepare("SELECT is_completed FROM tasks WHERE id = ? AND user_id = ?");
        $stmt->execute([$task_id, $user_id]);
        $task = $stmt->fetch();
        if ($task) {
            $new_status = $task['is_completed'] ? 0 : 1;
            $stmt = $pdo->prepare("UPDATE tasks SET is_completed = ? WHERE id = ?");
            $stmt->execute([$new_status, $task_id]);
            if ($new_status == 1) {
                addXP($pdo, $user_id, 10);
            }
        }
        header("Location: dashboard");
        exit;
    }

    // Delete Task
    if (isset($_POST['action']) && $_POST['action'] === 'delete_task') {
        $task_id = (int)$_POST['task_id'];
        $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ? AND user_id = ?");
        $stmt->execute([$task_id, $user_id]);
        header("Location: dashboard");
        exit;
    }

    // Log Mood
    if (isset($_POST['action']) && $_POST['action'] === 'log_mood') {
        $mood_score = max(1, min(5, (int)($_POST['mood'] ?? 3)));
        $note = trim($_POST['note']);
        $stmt = $pdo->prepare("INSERT INTO moods (user_id, mood_score, note) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $mood_score, $note]);
        addXP($pdo, $user_id, 5);
        header("Location: dashboard");
        exit;
    }

    // Fetch data
    $stmt = $pdo->prepare("SELECT id, title, is_completed, estimated_minutes FROM tasks WHERE user_id = ? AND created_at = ? ORDER BY id ASC");
    $stmt->execute([$user_id, $today]);
    $tasks = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT mood_score, note FROM moods WHERE user_id = ? AND DATE(created_at) = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$user_id, $today]);
    $today_mood = $stmt->fetch();

    $stmt = $pdo->prepare("SELECT xp, level FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_stats = $stmt->fetch();
    if (!$user_stats) $user_stats = ['xp' => 0, 'level' => 1];
    $xp_needed = $user_stats['level'] * 100;
    $xp_percent = min(100, ($user_stats['xp'] / max(1, $xp_needed)) * 100);

    $stmt = $pdo->prepare("
        SELECT a.id, a.title FROM articles a
        JOIN user_bookmarks b ON a.id = b.article_id
        WHERE b.user_id = ? ORDER BY b.created_at DESC LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $bookmarked_articles = $stmt->fetchAll();
}
?>
<?php
require_once "includes/i18n.php";
$page_title = t('seo_dash_title');
$page_desc = t('seo_dash_desc');
require_once "includes/header.php";
?>

    <main class="main-content container">
        <?php if (!$is_logged_in): ?>
            <div class="guest-banner">
                <span><?php echo t('banner_planner'); ?></span>
                <a href="/register"><?php echo t('banner_signup_cta'); ?></a>
            </div>
        <?php endif; ?>

        <div class="dashboard-header">
            <div>
                <?php if ($is_logged_in):
                    $display_name = !empty($_SESSION['last_name']) ? $_SESSION['last_name'] : $_SESSION['username']; ?>
                    <h1><?php echo t('dash_welcome'); ?>, <?php echo htmlspecialchars($display_name); ?>!</h1>
                <?php else: ?>
                    <h1><?php echo t('dash_title'); ?></h1>
                <?php endif; ?>
                <p><?php echo t('dash_sub'); ?></p>
            </div>
            <div style="display: flex; gap: 1rem;">
                <a href="/timer" class="btn btn-primary"><?php echo t('dash_start_focus'); ?></a>
                <?php if ($is_logged_in): ?>
                <a href="/settings" class="btn btn-secondary" title="Settings" style="display:flex;align-items:center;justify-content:center;width:42px;height:42px;padding:0;border-radius:var(--radius-full);">⚙</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="dashboard-grid">

            <!-- Top 3 Priority Board -->
            <div class="glass-card">
                <h3><?php echo t('dash_top3'); ?></h3>
                <p style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 1rem;"><?php echo t('dash_top3_sub'); ?></p>

                <?php if ($is_logged_in): ?>
                    <ul class="task-list">
                        <?php foreach ($tasks as $task): ?>
                            <li class="task-item <?php echo $task['is_completed'] ? 'completed' : ''; ?>">
                                <form method="POST" action="" style="display:flex; align-items: center; margin: 0;">
                                    <input type="hidden" name="action" value="toggle_task">
                                    <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                    <input type="checkbox" class="task-checkbox" onchange="this.form.submit()" <?php echo $task['is_completed'] ? 'checked' : ''; ?>>
                                </form>
                                <span class="task-title">
                                    <?php echo htmlspecialchars($task['title']); ?>
                                    <?php if ($task['estimated_minutes']): ?>
                                        <span class="task-badge">Est: <?php echo $task['estimated_minutes']; ?>m</span>
                                    <?php endif; ?>
                                </span>
                                <form method="POST" action="" style="margin:0;">
                                    <input type="hidden" name="action" value="delete_task">
                                    <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                    <button type="submit" class="task-del" title="Remove">&times;</button>
                                </form>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <?php
                    $active_tasks = count(array_filter($tasks, function($t) { return !$t['is_completed']; }));
                    if ($active_tasks < 3):
                    ?>
                        <form method="POST" action="" class="add-task-form">
                            <input type="hidden" name="action" value="add_task">
                            <input type="text" name="title" class="add-task-input" placeholder="<?php echo t('dash_add_ph'); ?>" required>
                            <input type="number" name="est_mins" class="add-task-input input-narrow" placeholder="<?php echo t('dash_est_ph'); ?>" min="1">
                            <button type="submit" class="btn btn-primary"><?php echo t('dash_add'); ?></button>
                        </form>
                    <?php else: ?>
                        <p style="color: var(--color-break); font-size: 0.9rem; margin-top: 1rem;"><?php echo t('dash_limit'); ?></p>
                    <?php endif; ?>
                <?php else: ?>
                    <ul class="task-list" id="g-task-list"></ul>
                    <form class="add-task-form" id="g-task-form">
                        <input type="text" id="g-task-title" class="add-task-input" placeholder="<?php echo t('dash_add_ph'); ?>" required>
                        <input type="number" id="g-task-est" class="add-task-input input-narrow" placeholder="<?php echo t('dash_est_ph'); ?>" min="1">
                        <button type="submit" class="btn btn-primary"><?php echo t('dash_add'); ?></button>
                    </form>
                    <p id="g-task-limit" class="hidden" style="color: var(--color-break); font-size: 0.9rem; margin-top: 1rem;"><?php echo t('dash_limit'); ?></p>
                <?php endif; ?>
            </div>

            <!-- Mood Check-In -->
            <div class="glass-card">
                <h3><?php echo t('dash_checkin'); ?></h3>
                <?php if ($is_logged_in): ?>
                    <?php if ($today_mood): ?>
                        <div style="text-align: center; margin-top: 2rem;">
                            <p style="color: var(--text-secondary);"><?php echo t('dash_checkin_done'); ?></p>
                            <div style="font-size: 4rem; margin: 1rem 0;">
                                <?php
                                    $emojis = [1 => '😫', 2 => '😕', 3 => '😐', 4 => '🙂', 5 => '😄'];
                                    echo $emojis[$today_mood['mood_score']] ?? '🙂';
                                ?>
                            </div>
                            <?php if ($today_mood['note']): ?>
                                <p style="background: var(--bg-input); padding: 1rem; border-radius: var(--radius-sm); font-style: italic;">
                                    "<?php echo htmlspecialchars($today_mood['note']); ?>"
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <p style="color: var(--text-secondary); font-size: 0.9rem;"><?php echo t('dash_checkin_q'); ?></p>
                        <form method="POST" action="" id="mood-form">
                            <input type="hidden" name="action" value="log_mood">
                            <input type="hidden" name="mood" id="mood-input" value="" required>
                            <div class="mood-emojis">
                                <button type="button" class="mood-btn" data-value="1">😫</button>
                                <button type="button" class="mood-btn" data-value="2">😕</button>
                                <button type="button" class="mood-btn" data-value="3">😐</button>
                                <button type="button" class="mood-btn" data-value="4">🙂</button>
                                <button type="button" class="mood-btn" data-value="5">😄</button>
                            </div>
                            <input type="text" name="note" class="mood-note" placeholder="<?php echo t('dash_note_ph'); ?>">
                            <button type="submit" class="btn btn-primary" style="width: 100%;"><?php echo t('dash_save_log'); ?></button>
                        </form>
                        <script>
                            document.addEventListener('DOMContentLoaded', () => {
                                document.querySelectorAll('.mood-btn').forEach(btn => {
                                    btn.addEventListener('click', function() {
                                        document.querySelectorAll('.mood-btn').forEach(b => b.classList.remove('active'));
                                        this.classList.add('active');
                                        document.getElementById('mood-input').value = this.dataset.value;
                                    });
                                });
                            });
                        </script>
                    <?php endif; ?>
                <?php else: ?>
                    <div id="g-mood-done" class="hidden" style="text-align: center; margin-top: 2rem;">
                        <p style="color: var(--text-secondary);"><?php echo t('dash_checkin_done'); ?></p>
                        <div style="font-size: 4rem; margin: 1rem 0;" id="g-mood-emoji"></div>
                        <p id="g-mood-note" class="hidden" style="background: var(--bg-input); padding: 1rem; border-radius: var(--radius-sm); font-style: italic;"></p>
                    </div>
                    <div id="g-mood-form-wrap">
                        <p style="color: var(--text-secondary); font-size: 0.9rem;"><?php echo t('dash_checkin_q'); ?></p>
                        <div class="mood-emojis">
                            <button type="button" class="mood-btn" data-value="1">😫</button>
                            <button type="button" class="mood-btn" data-value="2">😕</button>
                            <button type="button" class="mood-btn" data-value="3">😐</button>
                            <button type="button" class="mood-btn" data-value="4">🙂</button>
                            <button type="button" class="mood-btn" data-value="5">😄</button>
                        </div>
                        <input type="text" id="g-mood-note-input" class="mood-note" placeholder="<?php echo t('dash_note_ph'); ?>">
                        <button type="button" id="g-mood-save" class="btn btn-primary" style="width: 100%;"><?php echo t('dash_save_log'); ?></button>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Gamification Stats -->
            <div class="glass-card">
                <h3><?php echo t('dash_progress'); ?></h3>
                <div style="text-align: center; margin-top: 1.5rem;">
                    <div class="level-badge" id="g-level"><?php echo $is_logged_in ? $user_stats['level'] : '1'; ?></div>
                    <div style="font-size: 0.9rem; color: var(--text-secondary); margin-top: 0.5rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 600;"><?php echo t('dash_level'); ?></div>
                </div>
                <div style="margin-top: 2.5rem;">
                    <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 0.5rem; font-weight: 500;">
                        <span><?php echo t('dash_xp'); ?></span>
                        <span id="g-xp-label"><?php echo $is_logged_in ? ($user_stats['xp'] . ' / ' . $xp_needed . ' XP') : '0 / 100 XP'; ?></span>
                    </div>
                    <div class="progress-bar-bg">
                        <div class="progress-bar-fill" id="g-xp-bar" style="width: <?php echo $is_logged_in ? $xp_percent : 0; ?>%;"></div>
                    </div>
                    <p style="text-align: center; font-size: 0.8rem; color: var(--text-secondary); margin-top: 1.5rem; line-height: 1.4;"><?php echo t('dash_xp_hint'); ?></p>
                </div>
            </div>

            <!-- Brain Dump Inbox (local for everyone) -->
            <div class="glass-card">
                <h3><?php echo t('dash_dump'); ?></h3>
                <p style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 1rem;"><?php echo t('dash_dump_sub'); ?></p>
                <ul class="task-list" id="dump-list"></ul>
                <p id="dump-empty" style="color: var(--text-secondary); font-size: 0.9rem; text-align: center; margin-top: 1.5rem;"><?php echo t('dash_dump_empty'); ?></p>
            </div>

            <?php if ($is_logged_in): ?>
            <!-- Saved Articles -->
            <div class="glass-card">
                <h3><?php echo t('dash_saved'); ?></h3>
                <p style="color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 1rem;"><?php echo t('dash_saved_sub'); ?></p>
                <ul class="task-list">
                    <?php foreach ($bookmarked_articles as $article): ?>
                        <li class="task-item" style="padding: 0.75rem;">
                            <a href="articles?id=<?php echo $article['id']; ?>" style="color: var(--text-primary); flex: 1; font-weight: 500; font-size: 0.95rem; display: flex; align-items: center; gap: 0.5rem;">
                                <span style="color: var(--accent-secondary);">📄</span> <?php echo htmlspecialchars($article['title']); ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if (empty($bookmarked_articles)): ?>
                    <p style="color: var(--text-secondary); font-size: 0.9rem; text-align: center; margin-top: 2rem;"><?php echo t('dash_saved_empty'); ?></p>
                <?php endif; ?>
                <div style="text-align: center; margin-top: 1.5rem;">
                    <a href="/articles" class="btn btn-secondary" style="width: 100%; font-size: 0.85rem;"><?php echo t('dash_browse'); ?></a>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </main>

    <script src="/js/dashboard-guest.js?v=20260828a"></script>
    <?php require_once 'includes/footer.php'; ?>
