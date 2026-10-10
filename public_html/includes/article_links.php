<?php
// Internal article links must point at the canonical /article/<id>-<slug> URL.
// Slug-only (/article/my-slug) and stale-slug (/article/5-old-title) links still work
// via 301, but every redirect costs crawl budget and weakens the link, so they are
// rewritten on save. Unknown targets are left untouched.
function normalize_article_links($html, $pdo, &$unknown = null) {
    $unknown = [];
    $by_id = $pdo->prepare("SELECT id, slug FROM articles WHERE id = ?");
    $by_slug = $pdo->prepare("SELECT id, slug FROM articles WHERE slug = ? LIMIT 1");
    return preg_replace_callback('#href="(?:https?://(?:www\.)?adhd\.lk)?/article/([^"\#?]+)([^"]*)"#i',
        function ($m) use ($by_id, $by_slug, &$unknown) {
            $path = rtrim($m[1], '/');
            // Exact slug first: some slugs start with a number
            // ("5-adhd-strategies-that-actually-work" is article 10, not article 5).
            $by_slug->execute([$path]);
            $row = $by_slug->fetch();
            if (!$row && preg_match('/^(\d+)(?:-|$)/', $path, $idm)) {
                $by_id->execute([(int)$idm[1]]);
                $row = $by_id->fetch();
            }
            if (!$row || empty($row['slug'])) {
                $unknown[] = $m[1];
                return $m[0];
            }
            return 'href="/article/' . $row['id'] . '-' . $row['slug'] . $m[2] . '"';
        }, $html);
}
