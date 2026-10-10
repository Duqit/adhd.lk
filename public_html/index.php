<?php
require_once "includes/i18n.php";
$page_title = t('seo_home_title');
$page_desc = t('seo_home_desc');

// Latest articles in the visitor's language — homepage link equity + freshness.
// Guarded so the homepage still renders if the database is unavailable.
$latest_articles = [];
try {
    require_once 'db.php';
    $stmt = $pdo->prepare("SELECT id, title, slug, excerpt, tags, created_at FROM articles WHERE lang = ? ORDER BY created_at DESC, id DESC LIMIT 6");
    $stmt->execute([current_lang()]);
    $latest_articles = $stmt->fetchAll();
} catch (Throwable $e) { /* homepage must never break on DB issues */ }

require_once "includes/header.php";
?>

    <main class="main-content container">

        <!-- Hero Section -->
        <section class="hero">
            <span class="chip"><?php echo t('home_chip'); ?></span>
            <h1><?php echo t('home_hero_title'); ?></h1>
            <p><?php echo t('home_hero_sub'); ?> <strong style="color: var(--text-primary);"><?php echo t('home_hero_sub2'); ?></strong> <?php echo t('home_hero_sub3'); ?></p>
            <div class="hero-cta">
                <a href="/timer" class="btn btn-primary btn-large"><?php echo t('home_cta_start'); ?></a>
                <a href="/assessment" class="btn btn-secondary btn-large"><?php echo t('home_cta_test'); ?></a>
            </div>
        </section>

        <!-- Tools Grid -->
        <section class="hub-grid">

            <a href="/timer" class="glass-card tool-card">
                <span class="tool-tag"><?php echo t('tag_nologin'); ?></span>
                <div class="tool-icon">⏳</div>
                <h2 class="tool-title"><?php echo t('home_timer_t'); ?></h2>
                <p class="tool-desc"><?php echo t('home_timer_d'); ?></p>
            </a>

            <a href="/dashboard" class="glass-card tool-card">
                <span class="tool-tag"><?php echo t('tag_nologin'); ?></span>
                <div class="tool-icon">📌</div>
                <h2 class="tool-title"><?php echo t('home_planner_t'); ?></h2>
                <p class="tool-desc"><?php echo t('home_planner_d'); ?></p>
            </a>

            <a href="/routines" class="glass-card tool-card">
                <span class="tool-tag"><?php echo t('tag_nologin'); ?></span>
                <div class="tool-icon">🔄</div>
                <h2 class="tool-title"><?php echo t('home_routines_t'); ?></h2>
                <p class="tool-desc"><?php echo t('home_routines_d'); ?></p>
            </a>

            <a href="/habits" class="glass-card tool-card">
                <span class="tool-tag"><?php echo t('tag_new'); ?></span>
                <div class="tool-icon">🔥</div>
                <h2 class="tool-title"><?php echo t('home_habits_t'); ?></h2>
                <p class="tool-desc"><?php echo t('home_habits_d'); ?></p>
            </a>

            <a href="/journal" class="glass-card tool-card">
                <span class="tool-tag"><?php echo t('tag_nologin'); ?></span>
                <div class="tool-icon">📔</div>
                <h2 class="tool-title"><?php echo t('home_journal_t'); ?></h2>
                <p class="tool-desc"><?php echo t('home_journal_d'); ?></p>
            </a>

            <a href="/breathe" class="glass-card tool-card">
                <span class="tool-tag"><?php echo t('tag_new'); ?></span>
                <div class="tool-icon">🌬️</div>
                <h2 class="tool-title"><?php echo t('home_calm_t'); ?></h2>
                <p class="tool-desc"><?php echo t('home_calm_d'); ?></p>
            </a>

            <a href="/assessment" class="glass-card tool-card">
                <span class="tool-tag"><?php echo t('tag_new'); ?></span>
                <div class="tool-icon">📋</div>
                <h2 class="tool-title"><?php echo t('home_test_t'); ?></h2>
                <p class="tool-desc"><?php echo t('home_test_d'); ?></p>
            </a>

            <a href="/articles" class="glass-card tool-card">
                <span class="tool-tag"><?php echo t('tag_nologin'); ?></span>
                <div class="tool-icon">📚</div>
                <h2 class="tool-title"><?php echo t('home_articles_t'); ?></h2>
                <p class="tool-desc"><?php echo t('home_articles_d'); ?></p>
            </a>

        </section>

        <!-- Start here: self-test, timer and two evergreen guides (same-language slugs from lang/*.php) -->
        <section class="glass-card" style="width: 100%; margin-top: 4.5rem; padding: 1.6rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
            <div>
                <h2 style="font-size: 1.15rem; margin-bottom: 0.25rem;"><?php echo t('home_start_t'); ?></h2>
                <p style="color: var(--text-secondary); font-size: 0.9rem; margin: 0;"><?php echo t('home_start_d'); ?></p>
            </div>
            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                <a class="btn btn-primary" href="/assessment"><?php echo t('home_start_test'); ?></a>
                <a class="btn btn-secondary" href="/timer"><?php echo t('home_start_timer'); ?></a>
                <a class="btn btn-secondary" href="/article/<?php echo t('whatis_slug'); ?>"><?php echo t('home_start_whatis'); ?></a>
                <a class="btn btn-secondary" href="/article/<?php echo t('paralysis_slug'); ?>"><?php echo t('home_start_paralysis'); ?></a>
            </div>
        </section>

        <!-- Latest articles -->
        <?php if ($latest_articles): ?>
        <section style="width: 100%; margin-top: 4.5rem;">
            <div class="section-head">
                <h2><?php echo t('home_latest_t'); ?></h2>
                <p><?php echo t('home_latest_d'); ?></p>
            </div>
            <div class="article-grid" style="margin-bottom: 0;">
                <?php foreach ($latest_articles as $la):
                    $la_url = '/article/' . $la['id'] . '-' . ($la['slug'] ?: $la['id']); ?>
                    <a class="glass-card article-card" style="padding: 1.4rem; text-decoration: none; color: inherit;" href="<?php echo htmlspecialchars($la_url); ?>">
                        <div class="article-tags"><?php echo htmlspecialchars($la['tags']); ?></div>
                        <h3 class="article-title" style="font-size: 1.05rem;"><?php echo htmlspecialchars($la['title']); ?></h3>
                        <p class="article-excerpt" style="margin-bottom: 0;"><?php echo htmlspecialchars(mb_substr($la['excerpt'], 0, 130)); ?>…</p>
                    </a>
                <?php endforeach; ?>
            </div>
            <div style="text-align: center; margin-top: 1.75rem;">
                <a href="/articles" class="btn btn-secondary"><?php echo t('home_latest_all'); ?></a>
            </div>
        </section>
        <?php endif; ?>

        <!-- Sri Lanka guides: kept as a section so the best-ranking local pages keep their homepage links -->
        <section class="glass-card" style="width: 100%; margin-top: 3rem; padding: 1.4rem 1.6rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
            <div>
                <h2 style="font-size: 1.05rem; margin-bottom: 0.25rem;">🇱🇰 <?php echo t('home_sl_h'); ?></h2>
                <p style="color: var(--text-secondary); font-size: 0.9rem; margin: 0;"><?php echo t('home_sl_d'); ?></p>
            </div>
            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
                <a class="btn btn-secondary" style="font-size: 0.85rem;" href="/article/7-how-to-get-an-adhd-diagnosis-in-sri-lanka-a-practical-guide"><?php echo t('home_sl_diag'); ?></a>
                <a class="btn btn-secondary" style="font-size: 0.85rem;" href="/article/<?php echo t('dir_slug'); ?>"><?php echo t('footer_help_sl'); ?></a>
                <a class="btn btn-secondary" style="font-size: 0.85rem;" href="/articles?tag=Sri+Lanka"><?php echo t('home_sl_all'); ?></a>
            </div>
        </section>

        <!-- Why sign up -->
        <section style="width: 100%; margin-top: 4.5rem;">
            <div class="section-head">
                <h2><?php echo t('home_why_title'); ?></h2>
                <p><?php echo t('home_why_sub'); ?></p>
            </div>
            <div class="hub-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));">
                <div class="glass-card" style="text-align: center; padding: 1.6rem;">
                    <div style="font-size: 1.8rem; margin-bottom: 0.6rem;">🔓</div>
                    <h3 style="font-size: 1.05rem;"><?php echo t('home_why1_t'); ?></h3>
                    <p style="color: var(--text-secondary); font-size: 0.9rem;"><?php echo t('home_why1_d'); ?></p>
                </div>
                <div class="glass-card" style="text-align: center; padding: 1.6rem;">
                    <div style="font-size: 1.8rem; margin-bottom: 0.6rem;">☁️</div>
                    <h3 style="font-size: 1.05rem;"><?php echo t('home_why2_t'); ?></h3>
                    <p style="color: var(--text-secondary); font-size: 0.9rem;"><?php echo t('home_why2_d'); ?></p>
                </div>
                <div class="glass-card" style="text-align: center; padding: 1.6rem;">
                    <div style="font-size: 1.8rem; margin-bottom: 0.6rem;">⭐</div>
                    <h3 style="font-size: 1.05rem;"><?php echo t('home_why3_t'); ?></h3>
                    <p style="color: var(--text-secondary); font-size: 0.9rem;"><?php echo t('home_why3_d'); ?></p>
                </div>
            </div>
            <div style="text-align: center; margin-top: 2.5rem;">
                <a href="/register" class="btn btn-primary btn-large"><?php echo t('home_create_account'); ?></a>
            </div>
        </section>

    </main>

<?php require_once 'includes/footer.php'; ?>
