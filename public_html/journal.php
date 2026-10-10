<?php
require_once __DIR__ . '/includes/boot.php';
adhd_session_start();

$is_logged_in = isset($_SESSION['user_id']);

// Prompts come from the language files (lang/*.php)
require_once 'includes/i18n.php';
$prompts = t_arr('journal_prompts');
$daily_prompt = $prompts[date('z') % count($prompts)];

$entries = [];
$journaled_today = false;

if ($is_logged_in) {
    require_once 'db.php';
    $user_id = $_SESSION['user_id'];
    $today = date('Y-m-d');

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_journal') {
        $entry = trim($_POST['entry_text']);
        if (!empty($entry)) {
            $stmt = $pdo->prepare("INSERT INTO journal_entries (user_id, prompt, entry_text) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $daily_prompt, $entry]);

            // Award XP
            $stmt = $pdo->prepare("SELECT xp, level FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            if ($user) {
                $new_xp = $user['xp'] + 15;
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
        header("Location: journal");
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM journal_entries WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmt = $pdo->prepare("SELECT id FROM journal_entries WHERE user_id = ? AND DATE(created_at) = ?");
    $stmt->execute([$user_id, $today]);
    $journaled_today = (bool)$stmt->fetch();
}
?>
<?php
$page_title = t('seo_jour_title');
$page_desc = t('seo_jour_desc');
require_once "includes/header.php";
?>

    <main class="main-content container journal-container">
        <?php if (!$is_logged_in): ?>
            <div class="guest-banner">
                <span><?php echo t('banner_journal'); ?></span>
                <a href="/register"><?php echo t('banner_journal_cta'); ?></a>
            </div>
        <?php endif; ?>

        <div style="margin-bottom: 2rem;">
            <h1><?php echo t('jour_title'); ?></h1>
            <p style="color: var(--text-secondary);"><?php echo t('jour_sub'); ?></p>
        </div>

        <?php if ($is_logged_in): ?>
            <?php if (!$journaled_today): ?>
                <div class="prompt-box">
                    <h3 style="color: var(--text-secondary); font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 1rem;"><?php echo t('jour_prompt_label'); ?></h3>
                    <h2 style="margin-bottom: 2rem; font-weight: 500;">"<?php echo htmlspecialchars($daily_prompt); ?>"</h2>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="save_journal">
                        <textarea name="entry_text" rows="5" placeholder="<?php echo t('jour_ph'); ?>" required style="margin-bottom: 1rem; resize: vertical;"></textarea>
                        <button type="submit" class="btn btn-primary" style="width: 100%;"><?php echo t('jour_save'); ?></button>
                    </form>
                </div>
            <?php else: ?>
                <div class="prompt-box" style="border-color: var(--color-break);">
                    <h2 style="margin-bottom: 1rem;"><?php echo t('jour_done'); ?></h2>
                    <p style="color: var(--text-secondary);"><?php echo t('jour_come_back'); ?></p>
                </div>
            <?php endif; ?>

            <h3 style="margin-bottom: 1.5rem;"><?php echo t('jour_past'); ?></h3>
            <?php foreach ($entries as $entry): ?>
                <div class="entry-box">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; color: var(--text-secondary); font-size: 0.85rem;">
                        <span><?php echo htmlspecialchars($entry['prompt']); ?></span>
                        <span><?php echo date('M j, Y', strtotime($entry['created_at'])); ?></span>
                    </div>
                    <p style="white-space: pre-wrap; line-height: 1.6;"><?php echo htmlspecialchars($entry['entry_text']); ?></p>
                </div>
            <?php endforeach; ?>
            <?php if (empty($entries)): ?>
                <p style="color: var(--text-secondary); text-align: center;"><?php echo t('jour_empty'); ?></p>
            <?php endif; ?>

        <?php else: ?>
            <!-- GUEST MODE -->
            <div class="prompt-box" id="g-journal-box">
                <h3 style="color: var(--text-secondary); font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 1rem;"><?php echo t('jour_prompt_label'); ?></h3>
                <h2 style="margin-bottom: 2rem; font-weight: 500;">"<?php echo htmlspecialchars($daily_prompt); ?>"</h2>
                <textarea id="g-journal-text" rows="5" placeholder="<?php echo t('jour_ph'); ?>" style="margin-bottom: 1rem; resize: vertical;"></textarea>
                <button type="button" id="g-journal-save" class="btn btn-primary" style="width: 100%;"><?php echo t('jour_save'); ?></button>
            </div>
            <div class="prompt-box hidden" id="g-journal-done" style="border-color: var(--color-break);">
                <h2 style="margin-bottom: 1rem;"><?php echo t('jour_done'); ?></h2>
                <p style="color: var(--text-secondary);"><?php echo t('jour_come_back'); ?></p>
            </div>

            <h3 style="margin-bottom: 1.5rem;"><?php echo t('jour_past'); ?></h3>
            <div id="g-entries"></div>
            <p id="g-entries-empty" style="color: var(--text-secondary); text-align: center;"><?php echo t('jour_empty'); ?></p>

            <script>
            document.addEventListener('DOMContentLoaded', () => {
                const K = Store.KEYS;
                const today = Store.today();
                const dailyPrompt = <?php echo json_encode($daily_prompt); ?>;
                const box = document.getElementById('g-journal-box');
                const done = document.getElementById('g-journal-done');
                const list = document.getElementById('g-entries');
                const empty = document.getElementById('g-entries-empty');
                const esc = s => { const d = document.createElement('div'); d.textContent = s; return d.innerHTML; };

                function fmtDate(iso) {
                    const d = new Date(iso + 'T00:00:00');
                    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                }

                function render() {
                    const entries = Store.get(K.journal, []).slice().reverse();
                    const journaledToday = entries.some(e => e.date === today);
                    box.classList.toggle('hidden', journaledToday);
                    done.classList.toggle('hidden', !journaledToday);
                    empty.classList.toggle('hidden', entries.length > 0);
                    list.innerHTML = entries.map(e =>
                        '<div class="entry-box">' +
                            '<div style="display:flex; justify-content:space-between; margin-bottom:0.5rem; color:var(--text-secondary); font-size:0.85rem;">' +
                                '<span>' + esc(e.prompt) + '</span><span>' + fmtDate(e.date) + '</span>' +
                            '</div>' +
                            '<p style="white-space:pre-wrap; line-height:1.6;">' + esc(e.text) + '</p>' +
                        '</div>'
                    ).join('');
                }

                document.getElementById('g-journal-save').addEventListener('click', () => {
                    const text = document.getElementById('g-journal-text').value.trim();
                    if (!text) return;
                    const entries = Store.get(K.journal, []);
                    entries.push({ prompt: dailyPrompt, text: text, date: today });
                    Store.set(K.journal, entries);
                    Store.addXP(15);
                    render();
                });

                render();
            });
            </script>
        <?php endif; ?>
    </main>

    <?php require_once 'includes/footer.php'; ?>
