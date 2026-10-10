<?php
// sitemap.php — served as /sitemap.xml via .htaccess rewrite.
require_once __DIR__ . '/db.php';
header('Content-Type: application/xml; charset=utf-8');

$base = 'https://adhd.lk';
// Static lastmod = when the page's file last changed (homepage and /articles: newest
// article). A lastmod that is always "today" teaches Google to ignore it.
$newest_article = null;
try {
    $newest_article = $pdo->query("SELECT MAX(COALESCE(updated_at, created_at)) FROM articles")->fetchColumn();
} catch (Exception $e) {}
$static_lastmod = function ($path) use ($newest_article) {
    $file = __DIR__ . '/' . ($path === '' ? 'index' : ltrim($path, '/')) . '.php';
    $ts = is_file($file) ? filemtime($file) : time();
    if (($path === '' || $path === '/articles') && $newest_article) {
        $ts = max($ts, strtotime($newest_article));
    }
    return date('Y-m-d', $ts);
};

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

// Static pages (with hreflang alternates for the three UI languages)
$static = [
    ''            => '1.0',
    '/timer'      => '0.9',
    '/assessment' => '0.9',
    '/dashboard'  => '0.8',
    '/habits'     => '0.8',
    '/routines'   => '0.8',
    '/journal'    => '0.7',
    '/breathe'    => '0.7',
    '/articles'   => '0.8',
];

// English-only pages (not translated yet): one URL each, no alternates.
$english_only = [
    '/about'      => '0.6',
    '/editorial-policy' => '0.4',
    '/contact'    => '0.4',
];
foreach ($english_only as $path => $priority) {
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($base . $path) . "</loc>\n";
    echo "    <lastmod>" . $static_lastmod($path) . "</lastmod>\n";
    echo "    <priority>$priority</priority>\n";
    echo "  </url>\n";
}

// Each language version gets its own <loc>, all three carrying the same alternates.
// Listing only the English URL left the si/ta versions relying on alternates alone.
foreach ($static as $path => $priority) {
    $url = $base . ($path === '' ? '/' : $path);
    foreach (['en', 'si', 'ta'] as $lng) {
        $loc = $url . ($lng === 'en' ? '' : '?lang=' . $lng);
        $prio = ($lng === 'en') ? $priority : number_format(max(0.1, (float)$priority - 0.1), 1);
        echo "  <url>\n";
        echo "    <loc>" . htmlspecialchars($loc) . "</loc>\n";
        echo "    <lastmod>" . $static_lastmod($path) . "</lastmod>\n";
        echo "    <priority>$prio</priority>\n";
        echo '    <xhtml:link rel="alternate" hreflang="en" href="' . htmlspecialchars($url) . "\"/>\n";
        echo '    <xhtml:link rel="alternate" hreflang="si" href="' . htmlspecialchars($url) . "?lang=si\"/>\n";
        echo '    <xhtml:link rel="alternate" hreflang="ta" href="' . htmlspecialchars($url) . "?lang=ta\"/>\n";
        echo '    <xhtml:link rel="alternate" hreflang="x-default" href="' . htmlspecialchars($url) . "\"/>\n";
        echo "  </url>\n";
    }
}

// Articles (with hreflang alternates for translation groups)
try {
    $rows = $pdo->query("SELECT id, slug, lang, created_at, updated_at, translation_group FROM articles ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

    $by_group = [];
    foreach ($rows as $r) {
        if (!empty($r['translation_group'])) {
            $by_group[$r['translation_group']][] = $r;
        }
    }
    $art_url = function ($r) use ($base) {
        return $base . '/article/' . $r['id'] . '-' . (!empty($r['slug']) ? $r['slug'] : $r['id']);
    };

    foreach ($rows as $r) {
        $lastmod = !empty($r['updated_at']) ? $r['updated_at'] : $r['created_at'];
        echo "  <url>
";
        echo "    <loc>" . htmlspecialchars($art_url($r)) . "</loc>
";
        echo "    <lastmod>" . date('Y-m-d', strtotime($lastmod)) . "</lastmod>
";
        echo "    <priority>0.7</priority>
";
        $group = $r['translation_group'] ? ($by_group[$r['translation_group']] ?? []) : [];
        if (count($group) > 1) {
            $default = null;
            foreach ($group as $g) {
                echo '    <xhtml:link rel="alternate" hreflang="' . htmlspecialchars($g['lang'] ?: 'en') . '" href="' . htmlspecialchars($art_url($g)) . "\"/>
";
                if (($g['lang'] ?: 'en') === 'en') { $default = $g; }
            }
            if ($default) {
                echo '    <xhtml:link rel="alternate" hreflang="x-default" href="' . htmlspecialchars($art_url($default)) . "\"/>
";
            }
        }
        echo "  </url>
";
    }
} catch (Exception $e) {
    // articles table unavailable
}

echo '</urlset>';
