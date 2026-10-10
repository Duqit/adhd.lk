<?php
require_once "includes/i18n.php";
$page_title = t('priv_title') . ' | ADHD.lk';
$page_desc = t('priv_desc');
require_once "includes/header.php";
?>

<main class="main-content container" style="max-width: 780px;">
    <div style="width: 100%;">
        <h1><?php echo t('priv_title'); ?></h1>
        <p style="color: var(--text-faint); font-size: 0.9rem; margin-bottom: 2rem;">
            <?php echo t('legal_updated'); ?>: 16 July 2026
        </p>

        <?php foreach (t_arr('priv_sections') as $s): ?>
            <section class="glass-card" style="margin-bottom: 1.25rem;">
                <h2 style="font-size: 1.15rem; margin-bottom: 0.75rem;"><?php echo $s['h']; ?></h2>
                <div style="color: var(--text-secondary); font-size: 0.97rem;"><?php echo $s['b']; ?></div>
            </section>
        <?php endforeach; ?>

        <div style="margin-top: 2rem; display: flex; gap: 1rem; flex-wrap: wrap;">
            <a href="/" class="btn btn-secondary"><?php echo t('legal_home'); ?></a>
            <a href="/terms" class="btn btn-secondary"><?php echo t('footer_terms'); ?></a>
            <a href="/data_deletion_instructions" class="btn btn-secondary"><?php echo t('footer_deletion'); ?></a>
        </div>
    </div>
</main>

<?php require_once 'includes/footer.php'; ?>
