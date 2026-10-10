<?php
// article.php — dedicated page per article (SEO: own URL, Article schema).
// Reached via .htaccess rule: /article/<id>-<slug> → article.php?id=<id>
require_once __DIR__ . '/includes/boot.php';
adhd_session_start();
require_once 'includes/i18n.php';
require_once 'db.php';

$is_logged_in = isset($_SESSION['user_id']);
$user_id = $is_logged_in ? $_SESSION['user_id'] : null;

$article_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Slug-only URLs (/article/my-slug) — look up and redirect to the canonical id-slug URL.
if (!$article_id && !empty($_GET['slug_only'])) {
    $stmt = $pdo->prepare("SELECT id, slug FROM articles WHERE slug = ? LIMIT 1");
    $stmt->execute([$_GET['slug_only']]);
    if ($hit = $stmt->fetch()) {
        header("Location: /article/" . $hit['id'] . '-' . $hit['slug'], true, 301);
        exit;
    }
}

$stmt = $pdo->prepare("SELECT * FROM articles WHERE id = ?");
$stmt->execute([$article_id]);
$article = $stmt->fetch();

if (!$article) {
    http_response_code(404);
    $page_title = t('ui_article_404') . ' | ADHD.lk';
    require_once 'includes/header.php';
    echo '<main class="main-content container" style="text-align:center;"><h1>404</h1><p style="color:var(--text-secondary);">'
        . htmlspecialchars(t('ui_article_404'))
        . '</p><a href="/articles" class="btn btn-primary" style="margin-top:1.5rem;">' . t('dash_browse') . '</a></main>';
    require_once 'includes/footer.php';
    exit;
}

// Wrong or missing slug (/article/5-old-title, /article/5) — 301 to the canonical URL
// so stale links consolidate instead of serving a duplicate with a canonical tag.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($article['slug'])) {
    $req_path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    $want = '/article/' . $article['id'] . '-' . $article['slug'];
    if ($req_path !== null && rawurldecode(rtrim($req_path, '/')) !== $want) {
        // A slug that starts with a number ("5-adhd-strategies-…" is article 10's slug)
        // lands here as id 5 — send it to the article that really owns that slug.
        $seg = substr(rawurldecode(rtrim($req_path, '/')), strlen('/article/'));
        $own = $pdo->prepare("SELECT id, slug FROM articles WHERE slug = ? LIMIT 1");
        $own->execute([$seg]);
        if ($hit = $own->fetch()) {
            $want = '/article/' . $hit['id'] . '-' . $hit['slug'];
        }
        $qs = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
        header('Location: ' . $want . ($qs ? '?' . $qs : ''), true, 301);
        exit;
    }
}

// Handle bookmark toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_bookmark') {
    if (!$is_logged_in) { header("Location: /login"); exit; }
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

$is_bookmarked = false;
if ($is_logged_in) {
    $stmt = $pdo->prepare("SELECT id FROM user_bookmarks WHERE user_id = ? AND article_id = ?");
    $stmt->execute([$user_id, $article_id]);
    $is_bookmarked = (bool)$stmt->fetch();
}

// Related articles: same language, ranked by shared tags, newest as tie-break.
// (Newest-only sent every page's links to the same 3 articles and none to older ones.)
$art_lang = !empty($article['lang']) ? $article['lang'] : 'en';
$tag_set = function ($s) {
    $skip = ['සිංහල', 'தமிழ்', 'english', 'sinhala', 'tamil'];
    $out = [];
    foreach (explode(',', (string)$s) as $t) {
        $t = mb_strtolower(trim($t));
        if ($t !== '' && !in_array($t, $skip, true)) { $out[$t] = true; }
    }
    return $out;
};
$my_tags = $tag_set($article['tags']);
$stmt = $pdo->prepare("SELECT id, title, slug, excerpt, tags FROM articles WHERE id != ? AND lang = ? ORDER BY created_at DESC");
$stmt->execute([$article_id, $art_lang]);
$candidates = $stmt->fetchAll();
foreach ($candidates as $i => &$c) {
    $c['_score'] = count(array_intersect_key($my_tags, $tag_set($c['tags'])));
    $c['_rank'] = $i;
}
unset($c);
usort($candidates, function ($a, $b) {
    return ($b['_score'] <=> $a['_score']) ?: ($a['_rank'] <=> $b['_rank']);
});
$related = array_slice($candidates, 0, 4);

// Translation siblings (same translation_group, other languages) for hreflang
$siblings = [];
if (!empty($article['translation_group'])) {
    try {
        $stmt = $pdo->prepare("SELECT id, slug, lang FROM articles WHERE translation_group = ? AND id != ?");
        $stmt->execute([$article['translation_group'], $article_id]);
        $siblings = $stmt->fetchAll();
    } catch (PDOException $e) { /* column may not exist yet */ }
}

$slug = !empty($article['slug']) ? $article['slug'] : $article_id;
$art_url = 'https://adhd.lk/article/' . $article_id . '-' . $slug;
$published = date('c', strtotime($article['created_at']));
$updated_ts = !empty($article['updated_at']) ? strtotime($article['updated_at']) : strtotime($article['created_at']);
$reading_mins = max(1, (int)round(str_word_count(strip_tags($article['content'])) / 200));

// SEO variables consumed by header.php
// meta_title (admin "SEO title") wins: it keeps English search terms on si/ta pages
// and is never sent through the Gemini rewrite, so a polish can't drop them.
if (!empty($article['meta_title'])) {
    $page_title = $article['meta_title'];
} else {
    $page_title = mb_strlen($article['title']) <= 58 ? $article['title'] . ' | ADHD.lk' : $article['title'];
}
$author_name = (empty($article['author']) || $article['author'] === 'ADHD.lk Team') ? 'Nishnath Thaweesha' : $article['author'];
$page_desc = mb_substr(trim(strip_tags($article['excerpt'] ?: $article['content'])), 0, 158);
$page_canonical = $art_url;
$page_og_type = 'article';
$page_lang = $art_lang;
$skip_hreflang = true;
$page_jsonld = '<script type="application/ld+json">' . json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'Article',
    'headline' => $article['title'],
    'description' => $page_desc,
    'inLanguage' => $art_lang,
    'datePublished' => $published,
    'dateModified' => date('c', $updated_ts),
    'mainEntityOfPage' => $art_url,
    'image' => $article['image_url'] ?: 'https://adhd.lk/assets/og-image.png',
    'author' => ['@type' => 'Person', 'name' => $author_name, 'url' => 'https://adhd.lk/about'],
    'publisher' => ['@type' => 'Organization', 'name' => 'ADHD.lk', 'url' => 'https://adhd.lk/', 'logo' => ['@type' => 'ImageObject', 'url' => 'https://adhd.lk/assets/icon-512.svg']]
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';

// Breadcrumbs
$page_jsonld .= '<script type="application/ld+json">' . json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => [
        ['@type' => 'ListItem', 'position' => 1, 'name' => 'ADHD.lk', 'item' => 'https://adhd.lk/'],
        ['@type' => 'ListItem', 'position' => 2, 'name' => t('nav_articles'), 'item' => 'https://adhd.lk/articles'],
        ['@type' => 'ListItem', 'position' => 3, 'name' => $article['title'], 'item' => $art_url],
    ],
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';

// hreflang alternates linking this article to its translations
if ($siblings) {
    $alts = '<link rel="alternate" hreflang="' . htmlspecialchars($art_lang) . '" href="' . htmlspecialchars($art_url) . "\">
";
    $default_url = ($art_lang === 'en') ? $art_url : null;
    foreach ($siblings as $sib) {
        $sib_url = 'https://adhd.lk/article/' . $sib['id'] . '-' . ($sib['slug'] ?: $sib['id']);
        $alts .= '<link rel="alternate" hreflang="' . htmlspecialchars($sib['lang']) . '" href="' . htmlspecialchars($sib_url) . "\">
";
        if ($sib['lang'] === 'en') { $default_url = $sib_url; }
    }
    if ($default_url) {
        $alts .= '<link rel="alternate" hreflang="x-default" href="' . htmlspecialchars($default_url) . "\">
";
    }
    $page_jsonld .= $alts; // emitted in <head> by header.php alongside the JSON-LD
}

require_once 'includes/header.php';
?>

    <main class="main-content container" style="max-width: 820px;">
        <article style="width: 100%;">
            <header style="margin-bottom: 2rem;">
                <p style="margin-bottom: 1rem;"><a href="/articles" style="color: var(--accent-secondary); font-size: 0.9rem;">&larr; <?php echo t('nav_articles'); ?></a></p>
                <div class="article-tags" style="margin-bottom: 0.75rem;"><?php echo htmlspecialchars($article['tags']); ?></div>
                <h1 style="font-size: 2.2rem; line-height: 1.25;"><?php echo htmlspecialchars($article['title']); ?></h1>
                <div style="display: flex; align-items: center; gap: 1rem; color: var(--text-secondary); font-size: 0.9rem; flex-wrap: wrap;">
                    <span>
                        <?php echo t('byline_by'); ?> <a href="/about" rel="author" style="color: inherit; text-decoration: underline;"><?php echo htmlspecialchars($author_name); ?></a>
                        · <?php echo date('M j, Y', strtotime($article['created_at'])); ?>
                        <?php if ($updated_ts > strtotime($article['created_at']) + 86400): ?>
                            · <?php echo t('updated_label'); ?> <?php echo date('M j, Y', $updated_ts); ?>
                        <?php endif; ?>
                        · <?php echo $reading_mins; ?> <?php echo t('read_mins'); ?>
                        · <a href="/editorial-policy" style="color: inherit; text-decoration: underline;"><?php echo t('byline_policy'); ?></a>
                    </span>
                    <?php if ($is_logged_in): ?>
                        <form method="POST" action="" style="margin: 0;">
                            <input type="hidden" name="action" value="toggle_bookmark">
                            <button type="submit" class="btn btn-secondary" style="font-size: 0.82rem; padding: 0.35rem 0.9rem;">
                                <?php echo $is_bookmarked ? '⭐ ' . t('ui_saved') : '☆ ' . t('ui_save_later'); ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </header>

            <?php if ($article['image_url']): ?>
                <img src="<?php echo htmlspecialchars($article['image_url']); ?>" alt="<?php echo htmlspecialchars($article['title']); ?>" style="width: 100%; border-radius: var(--radius-lg); margin-bottom: 2rem;">
            <?php endif; ?>

            <div class="article-modal-body" style="font-size: 1.08rem;" lang="<?php echo htmlspecialchars($art_lang); ?>">
                <?php echo $article['content']; // trusted admin/seeded HTML ?>
            </div>
        </article>

        <!-- Share -->
        <div class="share-bar">
            <span style="color: var(--text-secondary); font-size: 0.9rem; font-weight: 600;"><?php echo t('share_label'); ?>:</span>
            <a class="share-btn" target="_blank" rel="noopener" href="https://wa.me/?text=<?php echo urlencode($article['title'] . ' — ' . $art_url); ?>">WhatsApp</a>
            <a class="share-btn" target="_blank" rel="noopener" href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode($art_url); ?>">Facebook</a>
            <a class="share-btn" target="_blank" rel="noopener" href="https://twitter.com/intent/tweet?url=<?php echo urlencode($art_url); ?>&text=<?php echo urlencode($article['title']); ?>">X</a>
            <button class="share-btn" type="button" onclick="navigator.clipboard.writeText('<?php echo htmlspecialchars($art_url, ENT_QUOTES); ?>').then(()=>{ if(window.Store) Store.toast(<?php echo htmlspecialchars(json_encode(t('share_copied')), ENT_QUOTES); ?>); });">🔗</button>
        </div>

        <!-- Tools CTA -->
        <div class="glass-card" style="width: 100%; margin-top: 3rem; text-align: center; border-color: rgba(99,102,241,0.35);">
            <h3 style="font-size: 1.15rem;"><?php echo t('home_chip'); ?></h3>
            <p style="color: var(--text-secondary); font-size: 0.95rem; max-width: 520px; margin: 0 auto 1.25rem auto;"><?php echo t('meta_default'); ?></p>
            <div style="display: flex; gap: 0.75rem; justify-content: center; flex-wrap: wrap;">
                <a href="/timer" class="btn btn-primary"><?php echo t('footer_timer'); ?></a>
                <a href="/assessment" class="btn btn-secondary"><?php echo t('footer_selftest'); ?></a>
                <a href="/habits" class="btn btn-secondary"><?php echo t('footer_habits'); ?></a>
            </div>
        </div>

        <?php if ($related): ?>
        <section style="width: 100%; margin-top: 3rem;">
            <h3 style="margin-bottom: 1.25rem;"><?php echo t('ui_keep_reading'); ?></h3>
            <div class="article-grid" style="margin-bottom: 0;">
                <?php foreach ($related as $rel): ?>
                    <a class="glass-card article-card" style="padding: 1.4rem; text-decoration: none; color: inherit;" href="/article/<?php echo $rel['id'] . '-' . urlencode($rel['slug'] ?: $rel['id']); ?>">
                        <h4 class="article-title" style="font-size: 1.05rem;"><?php echo htmlspecialchars($rel['title']); ?></h4>
                        <p class="article-excerpt" style="margin-bottom: 0;"><?php echo htmlspecialchars(mb_substr($rel['excerpt'], 0, 120)); ?>…</p>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>
    </main>

    <?php require_once 'includes/footer.php'; ?>
