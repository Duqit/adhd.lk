<?php
require_once __DIR__ . '/includes/boot.php';
adhd_session_start();
require_once 'includes/i18n.php';
require_once 'db.php';

$is_logged_in = isset($_SESSION['user_id']);
$user_id = $is_logged_in ? $_SESSION['user_id'] : null;

// Handle Bookmarking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_bookmark') {
    if (!$is_logged_in) {
        header("Location: login");
        exit;
    }

    $article_id = (int)$_POST['article_id'];

    $stmt = $pdo->prepare("SELECT id FROM user_bookmarks WHERE user_id = ? AND article_id = ?");
    $stmt->execute([$user_id, $article_id]);
    $bookmark = $stmt->fetch();

    if ($bookmark) {
        $stmt = $pdo->prepare("DELETE FROM user_bookmarks WHERE id = ?");
        $stmt->execute([$bookmark['id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO user_bookmarks (user_id, article_id) VALUES (?, ?)");
        $stmt->execute([$user_id, $article_id]);
    }

    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

// Fetch articles — only those in the currently selected site language
$lang = current_lang();
$search_tag = isset($_GET['tag']) ? trim($_GET['tag']) : '';
if ($search_tag) {
    $stmt = $pdo->prepare("SELECT * FROM articles WHERE lang = ? AND tags LIKE ? ORDER BY created_at DESC");
    $stmt->execute([$lang, "%$search_tag%"]);
} else {
    $stmt = $pdo->prepare("SELECT * FROM articles WHERE lang = ? ORDER BY created_at DESC");
    $stmt->execute([$lang]);
}
$articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch user's bookmarks if logged in
$bookmarked_ids = [];
if ($is_logged_in) {
    $stmt = $pdo->prepare("SELECT article_id FROM user_bookmarks WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $bookmarked_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Get unique tags for the filter — only from articles in this language,
// and skip the language-name tags (redundant now that the list is filtered).
$stmt = $pdo->prepare("SELECT tags FROM articles WHERE lang = ?");
$stmt->execute([$lang]);
$all_tags_raw = $stmt->fetchAll(PDO::FETCH_COLUMN);
$all_tags = [];
$lang_labels = ['සිංහල', 'தமிழ்', 'English'];
foreach ($all_tags_raw as $tags_str) {
    $parts = explode(',', $tags_str);
    foreach ($parts as $p) {
        $clean = trim($p);
        if ($clean && !in_array($clean, $all_tags) && !in_array($clean, $lang_labels)) {
            $all_tags[] = $clean;
        }
    }
}

$page_title = t('seo_articles_title');
$page_desc = t('seo_articles_desc');
$page_canonical = 'https://adhd.lk/articles';
require_once "includes/header.php";
?>

    <main class="main-content container">
        <div style="text-align: center; margin-bottom: 3rem;">
            <h1 style="font-size: 3rem; margin-bottom: 1rem;"><?php echo t('home_articles_t'); ?></h1>
            <p style="color: var(--text-secondary); max-width: 600px; margin: 0 auto;"><?php echo t('home_articles_d'); ?></p>
        </div>

        <div class="filter-container">
            <a href="articles" class="tag-pill <?php echo empty($search_tag) ? 'active' : ''; ?>"><?php echo t('ui_all'); ?></a>
            <?php foreach ($all_tags as $tag): ?>
                <a href="articles?tag=<?php echo urlencode($tag); ?>" class="tag-pill <?php echo $search_tag === $tag ? 'active' : ''; ?>" rel="nofollow"><?php echo htmlspecialchars($tag); ?></a>
            <?php endforeach; ?>
        </div>

        <div class="article-grid">
            <?php foreach ($articles as $article): ?>
                <?php
                    $is_bookmarked = in_array($article['id'], $bookmarked_ids);
                    $slug = !empty($article['slug']) ? $article['slug'] : $article['id'];
                    $url = '/article/' . $article['id'] . '-' . $slug;
                ?>
                <div class="glass-card article-card" style="padding: 0;">
                    <a href="<?php echo htmlspecialchars($url); ?>" style="color: inherit; display: block;">
                    <?php if ($article['image_url']): ?>
                        <img src="<?php echo htmlspecialchars($article['image_url']); ?>" class="article-img" alt="<?php echo htmlspecialchars($article['title']); ?>">
                    <?php else: ?>
                        <div class="article-img" style="display: flex; align-items: center; justify-content: center; font-size: 3rem; background: linear-gradient(135deg, var(--accent-primary), var(--accent-tertiary));">
                            🧠
                        </div>
                    <?php endif; ?>
                    </a>

                    <?php if ($is_logged_in): ?>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="toggle_bookmark">
                        <input type="hidden" name="article_id" value="<?php echo $article['id']; ?>">
                        <button type="submit" class="bookmark-btn" aria-label="<?php echo t('ui_save_later'); ?>" title="<?php echo t('ui_save_later'); ?>">
                            <?php echo $is_bookmarked ? '⭐' : '☆'; ?>
                        </button>
                    </form>
                    <?php endif; ?>

                    <div class="article-content">
                        <div class="article-tags"><?php echo htmlspecialchars($article['tags']); ?></div>
                        <h3 class="article-title"><a href="<?php echo htmlspecialchars($url); ?>" style="color: inherit;"><?php echo htmlspecialchars($article['title']); ?></a></h3>
                        <p class="article-excerpt"><?php echo htmlspecialchars($article['excerpt']); ?></p>
                        <a class="btn btn-secondary" href="<?php echo htmlspecialchars($url); ?>" style="width: 100%;"><?php echo t('ui_read_article'); ?> →</a>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($articles)): ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 4rem 0;">
                    <p style="color: var(--text-secondary); font-size: 1.2rem;"><?php echo t('ui_no_articles'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php require_once 'includes/footer.php'; ?>
