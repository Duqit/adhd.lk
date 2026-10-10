<?php
require_once __DIR__ . '/includes/boot.php';
session_start();
require_once 'db.php';
require_once 'includes/csrf.php';
require_once 'includes/article_links.php';

// Admins only
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: dashboard");
    exit;
}

$LANGS = ['en' => 'English', 'si' => 'සිංහල', 'ta' => 'தமிழ்'];

function make_slug($text, $fallback) {
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim(preg_replace('/-+/', '-', $slug), '-');
    $slug = substr($slug, 0, 120);
    return strlen($slug) >= 3 ? $slug : $fallback;
}

// The FAQPage schema is derived from the visible FAQ blocks, so it is rebuilt on every
// save instead of being edited by hand (or by Gemini).
function rebuild_faq_schema($html) {
    $html = preg_replace_callback('#\s*<script type="application/ld\+json">(.*?)</script>#si', function ($m) {
        return strpos($m[1], 'FAQPage') !== false ? '' : $m[0];
    }, $html);
    if (!preg_match_all('#<details[^>]*class="[^"]*faq-item[^"]*"[^>]*>\s*<summary[^>]*>(.*?)</summary>(.*?)</details>#si', $html, $found, PREG_SET_ORDER)) {
        return $html;
    }
    $clean = function ($s) {
        $s = html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return trim(preg_replace('/\s+/u', ' ', $s));
    };
    $items = [];
    foreach ($found as $f) {
        $q = $clean($f[1]);
        $a = $clean($f[2]);
        if ($q !== '' && $a !== '') {
            $items[] = ['@type' => 'Question', 'name' => $q, 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $a]];
        }
    }
    if (!$items) return $html;
    $json = json_encode(['@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $items],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);
    return rtrim($html) . '<script type="application/ld+json">' . $json . '</script>';
}

// What Gemini sees: no schema script, no HTML comments.
function strip_for_prompt($html) {
    $html = preg_replace('#\s*<script\b.*?</script>#si', '', $html);
    $html = preg_replace('#<!--.*?-->#s', '', $html);
    return trim($html);
}

// When rewriting from the English original, point article links at the same-language
// siblings so the new version keeps linking within its own language.
function localize_links($html, $lang, $pdo) {
    $by_id = $pdo->prepare("SELECT id, translation_group FROM articles WHERE id = ?");
    $by_slug = $pdo->prepare("SELECT id, translation_group FROM articles WHERE slug = ? LIMIT 1");
    $sibling = $pdo->prepare("SELECT id, slug FROM articles WHERE translation_group = ? AND lang = ? ORDER BY id LIMIT 1");
    return preg_replace_callback('#href="/article/([^"\#?]+)"#', function ($m) use ($lang, $by_id, $by_slug, $sibling) {
        if (preg_match('/^(\d+)(?:-|$)/', $m[1], $idm)) {
            $by_id->execute([(int)$idm[1]]);
            $row = $by_id->fetch();
        } else {
            $by_slug->execute([$m[1]]);
            $row = $by_slug->fetch();
        }
        if (!$row) return $m[0];
        $sibling->execute([$row['translation_group'] ?: $row['id'], $lang]);
        $s = $sibling->fetch();
        return ($s && $s['slug']) ? 'href="/article/' . $s['id'] . '-' . $s['slug'] . '"' : $m[0];
    }, $html);
}

function build_prompt($lang, $mode, $title, $excerpt, $content) {
    $name = ['si' => 'Sinhala (සිංහල)', 'ta' => 'Tamil (தமிழ்)', 'en' => 'English'][$lang] ?? 'English';
    $readers = ['si' => 'Sri Lankan readers', 'ta' => 'Tamil readers in Sri Lanka and India', 'en' => 'an international audience (mainly the US, UK and Europe)'][$lang] ?? 'an international audience';
    if ($mode === 'from_en') {
        $task = "Below is an English article from ADHD.lk, a free ADHD support website. Write it as a completely natural $name article for $readers. Do NOT translate word for word: write it the way a skilled native $name writer would explain these ideas, keeping the same sections, facts and advice.";
    } else {
        $task = "Below is a $name article from ADHD.lk, a free ADHD support website. Its wording sounds translated and unnatural. Rewrite it so it reads as if it was originally written by a skilled native $name writer for $readers, keeping the same sections, facts and advice.";
    }
    $rules = [
        "Keep every fact, number, phone number (e.g. 1926, 1333) and piece of advice. Do not add new medical claims, statistics or sources.",
        "Tone: warm, clear, conversational and respectful. Short paragraphs. Use the everyday language people actually speak and read — not formal, literary or textbook language.",
    ];
    if ($lang !== 'en') {
        $rules[] = "Keep widely used English terms as they are where Sri Lankans naturally use them (e.g. ADHD, timer, planner, dopamine, WhatsApp, Pomodoro). Do not force awkward native coinages.";
    }
    $rules = array_merge($rules, [
        "HTML: keep exactly the same HTML structure and tags (<p>, <h3>, <ul>, <ol>, <li>, <strong>, <em>, <a>, <table>, <tr>, <td>, <details>, <summary>) and their attributes. Only change the visible text.",
        "Links: keep every <a href=\"...\"> URL exactly as it is. Do not change, remove or add any URL. Only the visible link text may change.",
        "FAQ: keep every <details class=\"faq-item\"><summary>question</summary><p>answer</p></details> block and rewrite the question and answer text inside it.",
        "Title: natural and clear, ideally under 70 characters, and keep the main search term (usually \"ADHD\"). If the title contains an English phrase such as \"ADHD Meaning in Sinhala\", keep that phrase in English exactly.",
        "Excerpt: 1–2 sentences, under 160 characters, plain text (no HTML).",
        "No markdown (**, #), no <script> or <style>, no HTML comments, and no notes or explanations of your own.",
    ]);
    $numbered = '';
    foreach ($rules as $i => $r) $numbered .= ($i + 1) . ". $r\n";

    return $task . "\n\nRULES\n" . $numbered
        . "\nREPLY FORMAT — put your whole reply inside ONE code block, exactly like this, with nothing before or after it:\n"
        . "```\n===TITLE===\n(title)\n===EXCERPT===\n(excerpt)\n===CONTENT===\n(HTML)\n===END===\n```\n"
        . "\nTHE ARTICLE\n===TITLE===\n$title\n===EXCERPT===\n$excerpt\n===CONTENT===\n$content\n===END===\n";
}

$message = null;
$error = null;
$next_link = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    csrf_check();

    if ($_POST['action'] === 'add_article' || $_POST['action'] === 'update_article') {
        $title = trim($_POST['title'] ?? '');
        $author = trim($_POST['author'] ?? '') ?: 'ADHD.lk Team';
        $tags = trim($_POST['tags'] ?? '');
        $excerpt = trim($_POST['excerpt'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $image_url = trim($_POST['image_url'] ?? '');
        $lang = isset($LANGS[$_POST['lang'] ?? '']) ? $_POST['lang'] : 'en';
        $slug_input = trim($_POST['slug'] ?? '');
        $tgroup = ($_POST['translation_group'] ?? '') !== '' ? (int)$_POST['translation_group'] : null;
        $meta_title = trim($_POST['meta_title'] ?? '');

        if (empty($title) || empty($content)) {
            $error = "Title and Content are required.";
        } else {
            $content = normalize_article_links(rebuild_faq_schema($content), $pdo);
            if ($_POST['action'] === 'add_article') {
                $slug = make_slug($slug_input !== '' ? $slug_input : $title, 'article');
                $stmt = $pdo->prepare("INSERT INTO articles (title, excerpt, content, author, tags, image_url, lang, slug, translation_group) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$title, $excerpt, $content, $author, $tags, $image_url, $lang, $slug, $tgroup]);
                $new_id = $pdo->lastInsertId();
                if ($tgroup === null) {
                    $pdo->prepare("UPDATE articles SET translation_group = ? WHERE id = ?")->execute([$new_id, $new_id]);
                }
                if ($slug === 'article') { // non-Latin title and no manual slug
                    $pdo->prepare("UPDATE articles SET slug = ? WHERE id = ?")->execute(['article-' . $new_id, $new_id]);
                }
                $saved_id = $new_id;
                $message = "Article published.";
            } else {
                $edit_id = (int)$_POST['article_id'];
                $slug = make_slug($slug_input !== '' ? $slug_input : $title, 'article-' . $edit_id);

                // Keep the previous version so a bad paste can be undone.
                try {
                    $old = $pdo->prepare("SELECT title, excerpt, content FROM articles WHERE id = ?");
                    $old->execute([$edit_id]);
                    $o = $old->fetch();
                    if ($o && ($o['title'] !== $title || $o['excerpt'] !== $excerpt || $o['content'] !== $content)) {
                        $pdo->prepare("INSERT INTO article_revisions (article_id, title, excerpt, content) VALUES (?, ?, ?, ?)")
                            ->execute([$edit_id, $o['title'], $o['excerpt'], $o['content']]);
                    }
                } catch (Throwable $e) { /* revisions table not migrated yet */ }

                $stmt = $pdo->prepare("UPDATE articles SET title = ?, excerpt = ?, content = ?, author = ?, tags = ?, image_url = ?, lang = ?, slug = ?, translation_group = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$title, $excerpt, $content, $author, $tags, $image_url, $lang, $slug, ($tgroup !== null ? $tgroup : $edit_id), $edit_id]);
                $saved_id = $edit_id;
                $message = "Article #$edit_id updated.";

                if (($_POST['via_gemini'] ?? '') === '1') {
                    try {
                        $pdo->prepare("UPDATE articles SET polished_at = NOW() WHERE id = ?")->execute([$edit_id]);
                        $message = "Article #$edit_id updated with the Gemini rewrite ✨";
                        $nx = $pdo->prepare("SELECT id, title FROM articles WHERE lang = ? AND polished_at IS NULL AND id <> ? ORDER BY id LIMIT 1");
                        $nx->execute([$lang, $edit_id]);
                        $next_link = $nx->fetch() ?: null;
                    } catch (Throwable $e) { /* polished_at not migrated yet */ }
                }
            }
            // SEO title lives outside the Gemini prompt, so a rewrite can never drop it.
            try {
                $pdo->prepare("UPDATE articles SET meta_title = ? WHERE id = ?")
                    ->execute([$meta_title !== '' ? $meta_title : null, $saved_id]);
            } catch (Throwable $e) { /* meta_title not migrated yet */ }
        }
    }

    if ($_POST['action'] === 'delete_article') {
        $article_id = (int)$_POST['article_id'];
        $stmt = $pdo->prepare("DELETE FROM articles WHERE id = ?");
        $stmt->execute([$article_id]);
        $message = "Article deleted.";
    }
}

// Editing? Load the article into the form, plus everything the Gemini panel needs.
$editing = null;
$prompts = [];
$en_source = null;
$revisions = [];
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM articles WHERE id = ?");
    $stmt->execute([(int)$_GET['edit']]);
    $editing = $stmt->fetch();
}
if ($editing) {
    $ed_lang = $editing['lang'] ?: 'en';
    $prompts['polish'] = build_prompt($ed_lang, 'polish', $editing['title'], $editing['excerpt'], strip_for_prompt($editing['content']));
    if ($ed_lang !== 'en') {
        $src = $pdo->prepare("SELECT id, title, excerpt, content FROM articles WHERE translation_group = ? AND lang = 'en' AND id <> ? ORDER BY id LIMIT 1");
        $src->execute([$editing['translation_group'] ?: $editing['id'], $editing['id']]);
        $en_source = $src->fetch() ?: null;
        if ($en_source) {
            $prompts['from_en'] = build_prompt($ed_lang, 'from_en', $en_source['title'], $en_source['excerpt'],
                localize_links(strip_for_prompt($en_source['content']), $ed_lang, $pdo));
        }
    }
    try {
        $rv = $pdo->prepare("SELECT id, title, excerpt, content, saved_at FROM article_revisions WHERE article_id = ? ORDER BY id DESC LIMIT 5");
        $rv->execute([$editing['id']]);
        $revisions = $rv->fetchAll();
    } catch (Throwable $e) { $revisions = []; }
}

// List filters. "fl" rather than "lang": ?lang= switches the site's UI language.
$f_lang = isset($LANGS[$_GET['fl'] ?? '']) ? $_GET['fl'] : '';
$f_needs = ($_GET['needs'] ?? '') === '1';
$has_polish = true;
try {
    $where = [];
    $params = [];
    if ($f_lang !== '') { $where[] = 'lang = ?'; $params[] = $f_lang; }
    if ($f_needs) { $where[] = 'polished_at IS NULL'; }
    $stmt = $pdo->prepare("SELECT id, title, author, tags, lang, slug, created_at, polished_at FROM articles"
        . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . " ORDER BY created_at DESC, id DESC");
    $stmt->execute($params);
    $articles = $stmt->fetchAll();
    $progress = $pdo->query("SELECT lang, COUNT(*) AS total, SUM(polished_at IS NOT NULL) AS done FROM articles GROUP BY lang")->fetchAll();
} catch (Throwable $e) {
    $has_polish = false;
    $stmt = $pdo->prepare("SELECT id, title, author, tags, lang, slug, created_at FROM articles"
        . ($f_lang !== '' ? ' WHERE lang = ?' : '') . " ORDER BY created_at DESC, id DESC");
    $stmt->execute($f_lang !== '' ? [$f_lang] : []);
    $articles = $stmt->fetchAll();
    $progress = [];
}
?>
<?php $page_title = "Article CMS | ADHD.lk Admin"; require_once "includes/header.php"; ?>

    <main class="main-content container" style="align-items: stretch;">
        <div class="admin-header">
            <div>
                <h2>Article Management</h2>
                <p style="color: var(--text-secondary);">Add, edit and manage educational content.</p>
            </div>
            <button class="btn btn-primary" onclick="document.getElementById('article-form-card').classList.toggle('hidden')">
                <?php echo $editing ? '✎ Editing #' . (int)$editing['id'] : '+ New Article'; ?>
            </button>
        </div>

        <?php if ($message): ?>
            <div class="message">
                <?php echo htmlspecialchars($message); ?>
                <?php if ($next_link): ?>
                    &nbsp;<a href="/admin_articles?edit=<?php echo (int)$next_link['id']; ?>#gemini" style="font-weight: 600;">Next to polish: #<?php echo (int)$next_link['id']; ?> <?php echo htmlspecialchars(mb_strimwidth($next_link['title'], 0, 50, '…')); ?> →</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if ($error): ?><div class="error"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>

        <?php if ($editing): ?>
        <!-- Rewrite with Gemini -->
        <div class="glass-card" id="gemini" style="margin-bottom: 2rem;">
            <h3>✨ Rewrite with Gemini</h3>
            <ol style="color: var(--text-secondary); font-size: 0.9rem; margin: 0.5rem 0 1rem 1.2rem; line-height: 1.7;">
                <li>Copy a prompt and paste it into <a href="https://gemini.google.com/app" target="_blank" rel="noopener">Gemini</a>.</li>
                <li>In Gemini's reply, click the <strong>copy icon on the code box</strong>.</li>
                <li>Paste it below and press <strong>Apply to form</strong>, check the safety report, then press <strong>Save Changes</strong>.</li>
            </ol>
            <div style="display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
                <button type="button" class="btn btn-primary" data-copy="polish">📋 Copy prompt — polish this text</button>
                <?php if (isset($prompts['from_en'])): ?>
                    <button type="button" class="btn btn-secondary" data-copy="from_en">📋 Copy prompt — rewrite from the English original (#<?php echo (int)$en_source['id']; ?>)</button>
                <?php endif; ?>
                <span id="gm-status" style="font-size: 0.9rem;"></span>
            </div>
            <?php foreach ($prompts as $mode => $p): ?>
                <textarea id="gm-prompt-<?php echo $mode; ?>" class="form-control" rows="8" readonly style="display: none; margin-top: 0.75rem; font-family: monospace; font-size: 0.8rem;"><?php echo htmlspecialchars($p); ?></textarea>
            <?php endforeach; ?>

            <div class="form-group" style="margin-top: 1.25rem;">
                <label>Paste Gemini's reply here</label>
                <textarea id="gm-reply" rows="7" class="form-control" style="font-family: monospace; font-size: 0.85rem;" placeholder="===TITLE===&#10;…&#10;===EXCERPT===&#10;…&#10;===CONTENT===&#10;…&#10;===END==="></textarea>
            </div>
            <button type="button" class="btn btn-primary" id="gm-apply">Apply to form ↓</button>
            <div id="gm-report" style="display: none; margin-top: 1rem; padding: 1rem; border-radius: 10px; border: 1px solid var(--bg-glass-border); font-size: 0.9rem; line-height: 1.7;"></div>

            <?php if ($revisions): ?>
                <details style="margin-top: 1.25rem;">
                    <summary style="cursor: pointer; color: var(--text-secondary); font-size: 0.9rem;">↩ Previous versions (<?php echo count($revisions); ?>)</summary>
                    <ul style="list-style: none; padding: 0; margin-top: 0.75rem;">
                        <?php foreach ($revisions as $i => $r): ?>
                            <li style="display: flex; gap: 0.75rem; align-items: center; margin-bottom: 0.5rem; flex-wrap: wrap;">
                                <span style="font-size: 0.85rem; color: var(--text-secondary); white-space: nowrap;"><?php echo date('M d, Y H:i', strtotime($r['saved_at'])); ?></span>
                                <span style="font-size: 0.85rem;"><?php echo htmlspecialchars(mb_strimwidth($r['title'], 0, 60, '…')); ?></span>
                                <button type="button" class="btn-small" data-rev="<?php echo $i; ?>">Load into form</button>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Compose / Edit form -->
        <div class="glass-card <?php echo $editing ? '' : 'hidden'; ?>" id="article-form-card" style="margin-bottom: 2rem;">
            <h3><?php echo $editing ? 'Edit Article #' . (int)$editing['id'] : 'Compose Article'; ?></h3>
            <?php if ($editing): ?>
                <p style="margin-bottom: 1rem;"><a href="/admin_articles" style="color: var(--accent-secondary); font-size: 0.9rem;">← Cancel editing / compose new instead</a></p>
            <?php endif; ?>
            <form method="POST" action="/admin_articles" id="article-form" style="margin-top: 1rem;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="<?php echo $editing ? 'update_article' : 'add_article'; ?>">
                <input type="hidden" name="via_gemini" value="0">
                <?php if ($editing): ?><input type="hidden" name="article_id" value="<?php echo (int)$editing['id']; ?>"><?php endif; ?>

                <div class="form-group">
                    <label>Title *</label>
                    <input type="text" name="title" class="form-control" required value="<?php echo htmlspecialchars($editing['title'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label>SEO title <span style="color: var(--text-secondary); font-weight: 400;">(optional — the browser tab / Google title; not sent to Gemini, so a rewrite never changes it. Keep English search words here, e.g. "What is ADHD in Sinhala?")</span></label>
                    <input type="text" name="meta_title" class="form-control" maxlength="255" value="<?php echo htmlspecialchars($editing['meta_title'] ?? ''); ?>" placeholder="Leave empty to use the Title">
                </div>

                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <div class="form-group" style="flex: 2; min-width: 180px;">
                        <label>Author</label>
                        <input type="text" name="author" class="form-control" value="<?php echo htmlspecialchars($editing['author'] ?? 'ADHD.lk Team'); ?>">
                    </div>
                    <div class="form-group" style="flex: 2; min-width: 180px;">
                        <label>Tags (comma separated)</label>
                        <input type="text" name="tags" class="form-control" placeholder="Focus, Routine, Science" value="<?php echo htmlspecialchars($editing['tags'] ?? ''); ?>">
                    </div>
                    <div class="form-group" style="flex: 1; min-width: 120px;">
                        <label>Language</label>
                        <select name="lang" class="form-control">
                            <?php foreach ($LANGS as $code => $label): ?>
                                <option value="<?php echo $code; ?>" <?php echo (($editing['lang'] ?? 'en') === $code) ? 'selected' : ''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                    <div class="form-group" style="flex: 1; min-width: 200px;">
                        <label>URL slug <span style="color: var(--text-faint);">(a-z, 0-9, hyphens — auto-generated from title if left blank; for Sinhala/Tamil titles type a romanized slug, e.g. "adhd-sleep-sinhala")</span></label>
                        <input type="text" name="slug" class="form-control" placeholder="my-article-title" value="<?php echo htmlspecialchars($editing['slug'] ?? ''); ?>">
                    </div>
                    <div class="form-group" style="flex: 0 0 140px;">
                        <label>Translation group <span style="color: var(--text-faint);">(same number links en/si/ta versions for SEO; blank = standalone)</span></label>
                        <input type="number" name="translation_group" class="form-control" value="<?php echo htmlspecialchars($editing['translation_group'] ?? ''); ?>">
                    </div>
                    <div class="form-group" style="flex: 1; min-width: 200px;">
                        <label>Image URL (optional)</label>
                        <input type="text" name="image_url" class="form-control" placeholder="https://example.com/image.jpg" value="<?php echo htmlspecialchars($editing['image_url'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Short excerpt (shown on cards &amp; as the SEO description)</label>
                    <textarea name="excerpt" rows="2" class="form-control"><?php echo htmlspecialchars($editing['excerpt'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Content (HTML allowed: &lt;p&gt;, &lt;h3&gt;, &lt;ul&gt;, &lt;li&gt;, &lt;strong&gt;, &lt;a&gt;) * <span style="color: var(--text-faint);">— the FAQ schema is generated automatically from the FAQ blocks when you save</span></label>
                    <textarea name="content" rows="14" class="form-control" required style="font-family: monospace; font-size: 0.85rem;"><?php echo htmlspecialchars($editing['content'] ?? ''); ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary"><?php echo $editing ? 'Save Changes' : 'Publish Article'; ?></button>
            </form>
        </div>

        <!-- List filters -->
        <form method="GET" action="/admin_articles" style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap; margin-bottom: 1rem;">
            <select name="fl" class="form-control" style="width: auto;" onchange="this.form.submit()">
                <option value="">All languages</option>
                <?php foreach ($LANGS as $code => $label): ?>
                    <option value="<?php echo $code; ?>" <?php echo $f_lang === $code ? 'selected' : ''; ?>><?php echo $label; ?></option>
                <?php endforeach; ?>
            </select>
            <?php if ($has_polish): ?>
                <label style="display: flex; gap: 0.4rem; align-items: center; font-size: 0.9rem; cursor: pointer;">
                    <input type="checkbox" name="needs" value="1" <?php echo $f_needs ? 'checked' : ''; ?> onchange="this.form.submit()"> Needs polish only
                </label>
                <span style="font-size: 0.85rem; color: var(--text-secondary);">
                    <?php foreach ($progress as $pg): if (($pg['lang'] ?: 'en') === 'en') continue; ?>
                        <?php echo htmlspecialchars($LANGS[$pg['lang']] ?? $pg['lang']); ?>: <?php echo (int)$pg['done']; ?>/<?php echo (int)$pg['total']; ?> polished &nbsp;
                    <?php endforeach; ?>
                </span>
            <?php endif; ?>
        </form>

        <div class="glass-card table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Title</th>
                        <th>Lang</th>
                        <th>Tags</th>
                        <th>Published</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($articles as $article): ?>
                    <tr>
                        <td><?php echo (int)$article['id']; ?></td>
                        <td>
                            <a href="/article/<?php echo (int)$article['id'] . '-' . urlencode($article['slug'] ?: $article['id']); ?>" style="color: var(--text-primary); font-weight: 600;" target="_blank">
                                <?php echo htmlspecialchars(mb_strimwidth($article['title'], 0, 60, '…')); ?>
                            </a>
                            <?php if (!empty($article['polished_at'])): ?>
                                <span class="badge lang" title="Rewritten with Gemini on <?php echo date('M d, Y', strtotime($article['polished_at'])); ?>" style="margin-left: 0.35rem; text-transform: none;">✨ polished</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge lang"><?php echo htmlspecialchars($article['lang'] ?: 'en'); ?></span></td>
                        <td style="color: var(--text-secondary); font-size: 0.85rem;"><?php echo htmlspecialchars(mb_strimwidth($article['tags'], 0, 30, '…')); ?></td>
                        <td style="white-space: nowrap;"><?php echo date('M d, Y', strtotime($article['created_at'])); ?></td>
                        <td style="white-space: nowrap;">
                            <a href="/admin_articles?edit=<?php echo (int)$article['id']; ?>" class="btn-small" style="text-decoration: none; display: inline-block;">Edit</a>
                            <a href="/admin_articles?edit=<?php echo (int)$article['id']; ?>#gemini" class="btn-small" style="text-decoration: none; display: inline-block;">✨ Rewrite</a>
                            <form method="POST" action="/admin_articles" onsubmit="return confirm('Delete this article forever?');" style="display:inline;">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="delete_article">
                                <input type="hidden" name="article_id" value="<?php echo (int)$article['id']; ?>">
                                <button type="submit" class="btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($articles)): ?>
                        <tr><td colspan="6" style="text-align: center;">No articles match.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

<?php if ($editing): ?>
<script>
(function () {
    const form = document.getElementById('article-form');
    const field = name => form.elements[name];
    const statusEl = document.getElementById('gm-status');
    const report = document.getElementById('gm-report');
    const REVS = <?php echo json_encode($revisions, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
    let mode = 'polish';

    const esc = s => String(s).replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
    const hrefs = html => [...html.matchAll(/href\s*=\s*"([^"]*)"/gi)].map(m => m[1]);
    const count = (html, re) => (html.match(re) || []).length;
    const words = html => html.replace(/<[^>]+>/g, ' ').split(/\s+/).filter(Boolean).length;

    function setStatus(text, ok) {
        statusEl.textContent = text;
        statusEl.style.color = ok ? 'var(--accent-secondary)' : 'var(--color-warn)';
    }

    // The article sits after the last ===CONTENT=== marker; the earlier one is the format example.
    function promptArticle(m) {
        const p = document.getElementById('gm-prompt-' + m).value;
        const start = p.lastIndexOf('===CONTENT===');
        const end = p.lastIndexOf('===END===');
        return start >= 0 && end > start ? p.slice(start + 13, end) : '';
    }

    function copyText(text) {
        if (navigator.clipboard && window.isSecureContext) return navigator.clipboard.writeText(text);
        return new Promise((resolve, reject) => {
            const ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            const ok = document.execCommand('copy');
            ta.remove();
            ok ? resolve() : reject();
        });
    }

    document.querySelectorAll('[data-copy]').forEach(btn => btn.addEventListener('click', () => {
        mode = btn.dataset.copy;
        const box = document.getElementById('gm-prompt-' + mode);
        copyText(box.value)
            .then(() => setStatus('✓ Prompt copied — paste it into Gemini.', true))
            .catch(() => {
                box.style.display = 'block';
                box.select();
                setStatus('Copy was blocked — the prompt is selected below, press Ctrl+C.', false);
            });
    }));

    function parseReply(raw) {
        let t = raw.replace(/\r\n?/g, '\n').replace(/^\s*```[\w-]*\s*$/gm, '');
        t = t.replace(/=+\s*(TITLE|EXCERPT|CONTENT|END)\s*=+/gi, (m, k) => '@@' + k.toUpperCase() + '@@');
        const get = key => {
            const m = t.match(new RegExp('@@' + key + '@@([\\s\\S]*?)(?=@@(?:TITLE|EXCERPT|CONTENT|END)@@|$)'));
            return m ? m[1].replace(/^[\s*]+|[\s*]+$/g, '') : null;
        };
        return { title: get('TITLE'), excerpt: get('EXCERPT'), content: get('CONTENT') };
    }

    document.getElementById('gm-apply').addEventListener('click', () => {
        const r = parseReply(document.getElementById('gm-reply').value);
        report.style.display = 'block';
        if (!r.content) {
            report.innerHTML = '<strong style="color: var(--color-warn);">Couldn’t find ===CONTENT=== in the pasted reply.</strong> Paste Gemini’s whole code box (it starts with ===TITLE===).';
            return;
        }
        const content = r.content.replace(/<script[\s\S]*?<\/script>/gi, '').trim();
        const before = promptArticle(mode);
        const bLinks = [...new Set(hrefs(before))];
        const aLinks = [...new Set(hrefs(content))];
        const missing = bLinks.filter(h => !aLinks.includes(h));
        const added = aLinks.filter(h => !bLinks.includes(h));
        const faqB = count(before, /class="faq-item"/g), faqA = count(content, /class="faq-item"/g);
        const h3B = count(before, /<h3\b/gi), h3A = count(content, /<h3\b/gi);
        const noHtml = !/<(p|h3|ul|ol|li)\b/i.test(content);

        if (r.title) field('title').value = r.title.replace(/<[^>]+>/g, '').trim();
        if (r.excerpt) field('excerpt').value = r.excerpt.replace(/<[^>]+>/g, '').trim();
        field('content').value = content;
        field('via_gemini').value = '1';

        const ok = t => '<span style="color: var(--accent-secondary);">✓ ' + t + '</span>';
        const bad = t => '<span style="color: var(--color-warn); font-weight: 600;">⚠ ' + t + '</span>';
        const lines = [];
        lines.push(noHtml ? bad('The reply has no HTML tags — formatting would be lost. Ask Gemini to keep the HTML.') : ok('HTML structure present'));
        lines.push(missing.length ? bad(missing.length + ' link(s) missing: ' + missing.map(esc).join(', ')) : ok('All ' + bLinks.length + ' links kept'));
        if (added.length) lines.push(bad(added.length + ' new link(s) added: ' + added.map(esc).join(', ')));
        lines.push(faqA === faqB ? ok('FAQ items: ' + faqA) : bad('FAQ items: ' + faqB + ' → ' + faqA));
        lines.push(h3A === h3B ? ok('Sections (H3): ' + h3A) : bad('Sections (H3): ' + h3B + ' → ' + h3A));
        lines.push('Words: ' + words(before) + ' → ' + words(content));
        lines.push('<strong>Form filled. Review it, then press “Save Changes”.</strong> Nothing is saved until you do.');
        report.innerHTML = lines.join('<br>');
    });

    document.querySelectorAll('[data-rev]').forEach(btn => btn.addEventListener('click', () => {
        const r = REVS[+btn.dataset.rev];
        field('title').value = r.title;
        field('excerpt').value = r.excerpt;
        field('content').value = r.content;
        field('via_gemini').value = '0';
        report.style.display = 'block';
        report.innerHTML = '<strong>Version from ' + esc(r.saved_at) + ' loaded into the form.</strong> Press “Save Changes” to restore it.';
    }));
})();
</script>
<?php endif; ?>
<?php require_once 'includes/footer.php'; ?>
