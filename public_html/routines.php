<?php
require_once __DIR__ . '/includes/boot.php';
adhd_session_start();

$is_logged_in = isset($_SESSION['user_id']);
$routine_data = [];

if ($is_logged_in) {
    require_once 'db.php';
    $user_id = $_SESSION['user_id'];

    // Handle POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        if ($_POST['action'] === 'create_routine') {
            $name = trim($_POST['routine_name']);
            if (!empty($name)) {
                $stmt = $pdo->prepare("INSERT INTO routines (user_id, name) VALUES (?, ?)");
                $stmt->execute([$user_id, $name]);
                $routine_id = $pdo->lastInsertId();

                if (isset($_POST['step_title']) && is_array($_POST['step_title'])) {
                    $titles = $_POST['step_title'];
                    $durations = $_POST['step_duration'];
                    for ($i = 0; $i < count($titles); $i++) {
                        $title = trim($titles[$i]);
                        $duration = (int)$durations[$i];
                        if (!empty($title) && $duration > 0) {
                            $stmt = $pdo->prepare("INSERT INTO routine_steps (routine_id, title, duration_minutes, step_order) VALUES (?, ?, ?, ?)");
                            $stmt->execute([$routine_id, $title, $duration, $i + 1]);
                        }
                    }
                }
            }
            header("Location: routines");
            exit;
        }

        if ($_POST['action'] === 'delete_routine') {
            $routine_id = (int)$_POST['routine_id'];
            $stmt = $pdo->prepare("DELETE FROM routines WHERE id = ? AND user_id = ?");
            $stmt->execute([$routine_id, $user_id]);
            header("Location: routines");
            exit;
        }
    }

    // Fetch user's routines
    $stmt = $pdo->prepare("SELECT * FROM routines WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $routines = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($routines as $routine) {
        $stmt = $pdo->prepare("SELECT * FROM routine_steps WHERE routine_id = ? ORDER BY step_order ASC");
        $stmt->execute([$routine['id']]);
        $routine['steps'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $routine_data[] = $routine;
    }
}
?>
<?php
require_once "includes/i18n.php";
$page_title = t('seo_rout_title');
$page_desc = t('seo_rout_desc');
require_once "includes/header.php";
?>

    <main class="main-content container">
        <?php if (!$is_logged_in): ?>
            <div class="guest-banner">
                <span><?php echo t('banner_routines'); ?></span>
                <a href="/register"><?php echo t('banner_routines_cta'); ?></a>
            </div>
        <?php endif; ?>

        <div class="dashboard-header">
            <div>
                <h1><?php echo t('rout_title'); ?></h1>
                <p><?php echo t('rout_sub'); ?></p>
            </div>
            <button class="btn btn-primary" id="btn-create-routine"><?php echo t('rout_create'); ?></button>
        </div>

        <!-- Create Routine Form -->
        <div class="glass-card hidden" id="create-routine-form" style="margin-bottom: 3rem; border: 1px solid var(--accent-primary); width: 100%;">
            <h3><?php echo t('rout_create_title'); ?></h3>
            <form method="POST" action="" id="routine-form">
                <input type="hidden" name="action" value="create_routine">
                <div class="form-group" style="margin-top: 1rem;">
                    <label><?php echo t('rout_name_label'); ?></label>
                    <input type="text" name="routine_name" id="routine-name-input" class="form-control" required>
                </div>

                <div id="steps-container">
                    <label style="display:block; margin-bottom: 0.5rem; color: var(--text-secondary);"><?php echo t('rout_steps'); ?></label>
                    <div class="step-input-group">
                        <input type="text" name="step_title[]" placeholder="<?php echo t('rout_step1_ph'); ?>" required>
                        <input type="number" name="step_duration[]" placeholder="<?php echo t('rout_min_ph'); ?>" min="1" required>
                    </div>
                    <div class="step-input-group">
                        <input type="text" name="step_title[]" placeholder="<?php echo t('rout_step2_ph'); ?>" required>
                        <input type="number" name="step_duration[]" placeholder="<?php echo t('rout_min_ph'); ?>" min="1" required>
                    </div>
                </div>

                <button type="button" class="btn btn-secondary" id="btn-add-step" style="margin-top: 0.5rem; font-size: 0.85rem;"><?php echo t('rout_add_step'); ?></button>

                <div style="margin-top: 2rem; display: flex; gap: 1rem;">
                    <button type="submit" class="btn btn-primary"><?php echo t('rout_save'); ?></button>
                    <button type="button" class="btn btn-secondary" id="btn-cancel-routine"><?php echo t('rout_cancel'); ?></button>
                </div>
            </form>
        </div>

        <!-- Starter templates for guests with no routines yet -->
        <?php if (!$is_logged_in): ?>
        <div id="routine-templates" class="hidden" style="width: 100%; margin-bottom: 2.5rem;">
            <h3 style="margin-bottom: 1rem;"><?php echo t('rout_templates'); ?></h3>
            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                <button class="btn btn-secondary tpl-btn" data-tpl="morning"><?php echo t('rout_tpl_morning'); ?></button>
                <button class="btn btn-secondary tpl-btn" data-tpl="shutdown"><?php echo t('rout_tpl_shutdown'); ?></button>
                <button class="btn btn-secondary tpl-btn" data-tpl="desk"><?php echo t('rout_tpl_desk'); ?></button>
            </div>
        </div>
        <?php endif; ?>

        <div class="routine-grid" id="routine-grid">
            <?php foreach ($routine_data as $routine): ?>
                <div class="glass-card routine-card">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <h3><?php echo htmlspecialchars($routine['name']); ?></h3>
                        <form method="POST" action="" onsubmit="return confirm(<?php echo htmlspecialchars(json_encode(t('js_confirm_del_routine')), ENT_QUOTES); ?>);">
                            <input type="hidden" name="action" value="delete_routine">
                            <input type="hidden" name="routine_id" value="<?php echo $routine['id']; ?>">
                            <button type="submit" class="icon-btn" style="font-size: 1.5rem;">&times;</button>
                        </form>
                    </div>

                    <?php
                        $total_mins = 0;
                        foreach ($routine['steps'] as $step) { $total_mins += $step['duration_minutes']; }
                    ?>
                    <p style="color: var(--text-secondary); font-size: 0.9rem;"><?php echo t('rout_total'); ?>: <?php echo $total_mins; ?> <?php echo t('rout_mins'); ?></p>

                    <ul class="step-list">
                        <?php foreach ($routine['steps'] as $step): ?>
                            <li class="step-item">
                                <span><?php echo htmlspecialchars($step['title']); ?></span>
                                <span style="color: var(--text-secondary); font-weight: 600;"><?php echo $step['duration_minutes']; ?>m</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <button class="btn btn-primary" style="width: 100%; margin-top: 1rem;" onclick='startRoutine(<?php echo json_encode($routine, JSON_HEX_APOS | JSON_HEX_QUOT); ?>)'><?php echo t('rout_start'); ?></button>
                </div>
            <?php endforeach; ?>

            <?php if ($is_logged_in && empty($routine_data)): ?>
                <p style="color: var(--text-secondary); grid-column: 1 / -1; text-align: center; padding: 3rem 0;"><?php echo t('rout_empty_user'); ?></p>
            <?php endif; ?>
        </div>
        <p id="g-routines-empty" class="hidden" style="color: var(--text-secondary); text-align: center; padding: 2rem 0; width: 100%;"><?php echo t('rout_empty'); ?></p>
    </main>

    <!-- Fullscreen Routine Runner -->
    <div class="routine-runner" id="routine-runner">
        <div class="runner-progress-container">
            <div class="runner-progress-bar" id="runner-progress"></div>
        </div>

        <p style="color: var(--text-secondary); margin-bottom: 0.5rem; text-transform: uppercase; letter-spacing: 2px;" id="runner-routine-name"></p>
        <h2 class="runner-title" id="runner-step-title"></h2>

        <div class="runner-timer" id="runner-timer">00:00</div>

        <div style="display: flex; gap: 1rem; margin-bottom: 2rem;">
            <button class="btn btn-primary btn-large" id="runner-btn-play"><?php echo t('rout_pause'); ?></button>
            <button class="btn btn-secondary btn-large" id="runner-btn-skip"><?php echo t('rout_next_step'); ?></button>
        </div>

        <p class="runner-next" id="runner-next-step"></p>

        <button class="btn btn-secondary" id="runner-btn-quit" style="margin-top: 2rem; background: transparent; border: 1px solid var(--bg-glass-border); color: var(--text-secondary);"><?php echo t('rout_quit'); ?></button>
    </div>

    <script src="/js/routines.js?v=20260828a"></script>
    <?php if (!$is_logged_in): ?><script src="/js/routines-guest.js?v=20260828a"></script><?php endif; ?>
    <?php require_once 'includes/footer.php'; ?>
