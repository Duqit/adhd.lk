<?php
require_once __DIR__ . '/includes/boot.php';
adhd_session_start();

$is_logged_in = isset($_SESSION['user_id']);
// Anti-forgery tokens for the logged-in forms. Never loaded for guests: it starts a
// session, which would make the page uncacheable.
if ($is_logged_in) require_once __DIR__ . '/includes/csrf.php';
$habit_data = [];

if ($is_logged_in) {
    require_once 'db.php';
    require_once __DIR__ . '/includes/xp.php';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') csrf_check(); // every logged-in form carries a token
    $user_id = $_SESSION['user_id'];
    $today = date('Y-m-d');

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if ($_POST['action'] === 'add_habit') {
            $name = trim((string)($_POST['habit_name'] ?? ''));
            $emoji = trim((string)($_POST['habit_emoji'] ?? '')) ?: '✅';
            $emoji = mb_substr($emoji, 0, 8);
            if (!empty($name)) {
                $stmt = $pdo->prepare("INSERT INTO habits (user_id, name, emoji) VALUES (?, ?, ?)");
                $stmt->execute([$user_id, $name, $emoji]);
            }
            header("Location: habits");
            exit;
        }
        if ($_POST['action'] === 'delete_habit') {
            $habit_id = (int)($_POST['habit_id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM habits WHERE id = ? AND user_id = ?");
            $stmt->execute([$habit_id, $user_id]);
            header("Location: habits");
            exit;
        }
        if ($_POST['action'] === 'check_habit') {
            $habit_id = (int)($_POST['habit_id'] ?? 0);
            // Verify ownership
            $stmt = $pdo->prepare("SELECT id FROM habits WHERE id = ? AND user_id = ?");
            $stmt->execute([$habit_id, $user_id]);
            if ($stmt->fetch()) {
                $stmt = $pdo->prepare("SELECT id FROM habit_logs WHERE habit_id = ? AND log_date = ?");
                $stmt->execute([$habit_id, $today]);
                $existing = $stmt->fetch();
                if ($existing) {
                    $stmt = $pdo->prepare("DELETE FROM habit_logs WHERE id = ?");
                    $stmt->execute([$existing['id']]);
                    // Un-checking takes the 5 XP back, so the day pays at most once.
                    adhd_award_xp($pdo, $user_id, -5);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO habit_logs (habit_id, user_id, log_date) VALUES (?, ?, ?)");
                    $stmt->execute([$habit_id, $user_id, $today]);
                    adhd_award_xp($pdo, $user_id, 5);
                }
            }
            header("Location: habits");
            exit;
        }
    }

    // Fetch habits + logs
    $stmt = $pdo->prepare("SELECT * FROM habits WHERE user_id = ? ORDER BY created_at ASC");
    $stmt->execute([$user_id]);
    $habits = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($habits as $habit) {
        $stmt = $pdo->prepare("SELECT log_date FROM habit_logs WHERE habit_id = ? ORDER BY log_date DESC LIMIT 90");
        $stmt->execute([$habit['id']]);
        $logs = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $habit['logs'] = $logs;

        // Streak: consecutive days ending today (or yesterday if today unchecked)
        $log_set = array_flip($logs);
        $streak = 0;
        $cursor = isset($log_set[$today]) ? $today : date('Y-m-d', strtotime('-1 day'));
        while (isset($log_set[$cursor])) {
            $streak++;
            $cursor = date('Y-m-d', strtotime($cursor . ' -1 day'));
        }
        $habit['streak'] = $streak;
        $habit['checked_today'] = isset($log_set[$today]);
        $habit_data[] = $habit;
    }

    // Last 7 days for the grid
    $week = [];
    for ($i = 6; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i day"));
        $week[] = ['date' => $d, 'label' => date('D', strtotime($d))[0], 'num' => date('j', strtotime($d))];
    }
}
?>
<?php
require_once "includes/i18n.php";
$page_title = t('seo_hab_title');
$page_desc = t('seo_hab_desc');
require_once "includes/header.php";
?>

    <main id="main" class="main-content container" style="max-width: 860px;">
        <?php if (!$is_logged_in): ?>
            <div class="guest-banner">
                <span><?php echo t('banner_habits'); ?></span>
                <a href="/register"><?php echo t('banner_habits_cta'); ?></a>
            </div>
        <?php endif; ?>

        <div class="dashboard-header">
            <div>
                <h1><?php echo t('hab_title'); ?></h1>
                <p><?php echo t('hab_sub'); ?></p>
            </div>
        </div>

        <div class="glass-card" style="width: 100%; margin-bottom: 2rem;">
            <h2 class="h3" style="font-size: 1.05rem;"><?php echo t('hab_add_t'); ?></h2>
            <p style="color: var(--text-secondary); font-size: 0.88rem; margin-bottom: 1rem;"><?php echo t('hab_add_sub'); ?></p>
            <?php if ($is_logged_in): ?>
                <form method="POST" action="" class="add-task-form" style="margin-top: 0;">
                    <?php if ($is_logged_in) echo csrf_field(); ?>
                    <input type="hidden" name="action" value="add_habit">
                    <input type="text" name="habit_emoji" class="add-task-input input-narrow" style="text-align:center;" aria-label="<?php echo t('a11y_habit_emoji'); ?>" placeholder="😀" maxlength="8">
                    <input type="text" name="habit_name" class="add-task-input" aria-label="<?php echo t('hab_name_ph'); ?>" placeholder="<?php echo t('hab_name_ph'); ?>" required maxlength="120">
                    <button type="submit" class="btn btn-primary"><?php echo t('hab_add_btn'); ?></button>
                </form>
            <?php else: ?>
                <form class="add-task-form" id="g-habit-form" style="margin-top: 0;">
                    <input type="text" id="g-habit-emoji" class="add-task-input input-narrow" style="text-align:center;" aria-label="<?php echo t('a11y_habit_emoji'); ?>" placeholder="😀" maxlength="8">
                    <input type="text" id="g-habit-name" class="add-task-input" aria-label="<?php echo t('hab_name_ph'); ?>" placeholder="<?php echo t('hab_name_ph'); ?>" required maxlength="120">
                    <button type="submit" class="btn btn-primary"><?php echo t('hab_add_btn'); ?></button>
                </form>
            <?php endif; ?>
        </div>

        <div style="width: 100%;" id="habit-list">
            <?php if ($is_logged_in): ?>
                <?php foreach ($habit_data as $habit): ?>
                    <div class="habit-row">
                        <div class="habit-head">
                            <div class="habit-emoji"><?php echo htmlspecialchars($habit['emoji']); ?></div>
                            <div class="habit-info">
                                <div class="habit-name"><?php echo htmlspecialchars($habit['name']); ?></div>
                                <div class="habit-streak">🔥 <strong><?php echo $habit['streak']; ?> <?php echo $habit['streak'] == 1 ? t('hab_day') : t('hab_days'); ?></strong> <?php echo t('hab_streak'); ?></div>
                            </div>
                        </div>
                        <div class="habit-week">
                            <?php foreach ($week as $day): ?>
                                <div class="habit-day <?php echo in_array($day['date'], $habit['logs']) ? 'done' : ''; ?> <?php echo $day['date'] === $today ? 'today' : ''; ?>">
                                    <span><?php echo $day['label']; ?></span>
                                    <span class="d-num"><?php echo $day['num']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="habit-actions">
                            <form method="POST" action="" style="margin: 0; flex: 1; display: flex;">
                                <?php if ($is_logged_in) echo csrf_field(); ?>
                                <input type="hidden" name="action" value="check_habit">
                                <input type="hidden" name="habit_id" value="<?php echo $habit['id']; ?>">
                                <button type="submit" class="habit-check-btn <?php echo $habit['checked_today'] ? 'checked' : ''; ?>" style="flex: 1;">
                                    <?php echo $habit['checked_today'] ? t('hab_done') : t('hab_checkin'); ?>
                                </button>
                            </form>
                            <form method="POST" action="" onsubmit="return confirm(<?php echo htmlspecialchars(json_encode(t('js_confirm_del_habit')), ENT_QUOTES); ?>);" style="margin: 0;">
                                <?php if ($is_logged_in) echo csrf_field(); ?>
                                <input type="hidden" name="action" value="delete_habit">
                                <input type="hidden" name="habit_id" value="<?php echo $habit['id']; ?>">
                                <button type="submit" class="task-del" title="Delete">&times;</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($habit_data)): ?>
                    <p style="color: var(--text-secondary); text-align: center; padding: 2rem 0;"><?php echo t('hab_empty'); ?></p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        <p id="g-habits-empty" class="hidden" style="color: var(--text-secondary); text-align: center; padding: 2rem 0;"><?php echo t('hab_empty'); ?></p>

        <div class="disclaimer" style="width: 100%;">
            <?php echo t('hab_tip'); ?>
        </div>
    </main>

    <?php if (!$is_logged_in): ?><script src="/js/habits-guest.js?v=20261108b"></script><?php endif; ?>
    <?php require_once 'includes/footer.php'; ?>
