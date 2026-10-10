<?php
require_once __DIR__ . '/boot.php';
adhd_session_start();
require_once __DIR__ . '/i18n.php';
adhd_public_cache_headers();
$is_logged_in = isset($_SESSION['user_id']);
$is_admin = $is_logged_in && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
$page_title = isset($page_title) ? $page_title : 'Free ADHD Tools — Focus Timer, Self-Test & Habit Tracker | ADHD.lk';
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// --- SEO ---
$page_desc = isset($page_desc) ? $page_desc : t('meta_default');
$request_path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($request_path === '/index') { $request_path = '/'; }
$page_canonical = isset($page_canonical) ? $page_canonical : ('https://adhd.lk' . $request_path);
$page_og_image = isset($page_og_image) ? $page_og_image : 'https://adhd.lk/assets/og-image.png';
$skip_hreflang = isset($skip_hreflang) ? $skip_hreflang : false;
$page_lang = isset($page_lang) ? $page_lang : current_lang();

// Language variants of the param-based pages need their OWN canonical. Pointing
// /?lang=si at the English URL told Google the Sinhala and Tamil versions were
// duplicates, so it dropped 46 of them as "Alternate page with proper canonical tag"
// — directly contradicting the hreflang tags below. Article pages have their own
// per-language URLs and set $skip_hreflang, so they are unaffected.
$canonical_base = $page_canonical;
if (!$skip_hreflang && $page_lang !== 'en') {
    $page_canonical = $canonical_base . '?lang=' . $page_lang;
}

function nav_active($page, $current) {
    return $page === $current ? 'class="active"' : '';
}
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($page_lang); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script>
      // Set theme before first paint (no flash). Saved choice wins; else OS preference.
      (function () {
          var t = 'dark';
          try {
              t = localStorage.getItem('adhd_theme') ||
                  (window.matchMedia && matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');
          } catch (e) {}
          document.documentElement.setAttribute('data-theme', t);
      })();
    </script>
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($page_desc); ?>">
    <link rel="canonical" href="<?php echo htmlspecialchars($page_canonical); ?>">
    <?php if (!empty($page_noindex)): ?>
    <meta name="robots" content="noindex, follow">
    <?php endif; ?>
    <?php if (!$skip_hreflang): ?>
    <link rel="alternate" hreflang="en" href="<?php echo htmlspecialchars($canonical_base); ?>">
    <link rel="alternate" hreflang="si" href="<?php echo htmlspecialchars($canonical_base); ?>?lang=si">
    <link rel="alternate" hreflang="ta" href="<?php echo htmlspecialchars($canonical_base); ?>?lang=ta">
    <link rel="alternate" hreflang="x-default" href="<?php echo htmlspecialchars($canonical_base); ?>">
    <?php endif; ?>

    <!-- Open Graph / Twitter -->
    <meta property="og:site_name" content="ADHD.lk">
    <meta property="og:locale" content="<?php echo ['si' => 'si_LK', 'ta' => 'ta_LK'][$page_lang] ?? 'en_US'; ?>">
    <meta property="og:type" content="<?php echo isset($page_og_type) ? $page_og_type : 'website'; ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars($page_title); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($page_desc); ?>">
    <meta property="og:url" content="<?php echo htmlspecialchars($page_canonical); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($page_og_image); ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?php echo htmlspecialchars($page_title); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($page_desc); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($page_og_image); ?>">

    <?php if ($current_page === 'index'): ?>
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "WebSite",
      "name": "ADHD.lk",
      "url": "https://adhd.lk/",
      "description": <?php echo json_encode(html_entity_decode(t('seo_home_desc'), ENT_QUOTES, 'UTF-8'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>,
      "inLanguage": ["en", "si", "ta"],
      "publisher": {
        "@type": "Organization",
        "name": "ADHD.lk",
        "url": "https://adhd.lk/",
        "logo": "https://adhd.lk/assets/icon-512.svg"
      }
    }
    </script>
    <?php endif; ?>
    <?php if (isset($page_jsonld)) { echo $page_jsonld; } ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <!-- Google Tag Manager + Google tag (gtag.js) — skipped when the browser sends
         Do Not Track or Global Privacy Control, as the privacy policy promises. -->
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      if (!(navigator.doNotTrack === '1' || window.doNotTrack === '1' || navigator.globalPrivacyControl === true)) {
        (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
        new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
        j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
        'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer','GTM-M8GBPL3W');

        var g = document.createElement('script');
        g.async = true;
        g.src = 'https://www.googletagmanager.com/gtag/js?id=G-5NBM71X3E6';
        document.head.appendChild(g);
        gtag('js', new Date());
        gtag('config', 'G-5NBM71X3E6');
      }
    </script>
    <!-- End Google Tag Manager -->

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/css/style.css?v=20261011a">

    <!-- PWA Meta Tags -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#0b1120">
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/apple-touch-icon-180.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/assets/icon-192.png">
    <link rel="icon" type="image/svg+xml" href="/assets/icon-512.svg">
    <meta name="apple-mobile-web-app-status-bar" content="#0b1120">
    <meta name="apple-mobile-web-app-capable" content="yes">

    <script>
      // Global flags used by store.js (guest/local mode vs. logged-in sync)
      window.ADHD_USER = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
      // Translated strings for the JS layer
      window.ADHD_I18N = <?php echo i18n_js_payload(); ?>;

      if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
          navigator.serviceWorker.register('/sw.js').catch(() => {});
        });
      }
    </script>
</head>
<body>
    <!-- Google Tag Manager (noscript) -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=GTM-M8GBPL3W"
    height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    <!-- End Google Tag Manager (noscript) -->
    <header class="navbar" id="navbar">
        <a href="/" class="logo">ADHD.lk<?php echo $is_admin ? ' <span style="font-size: 0.9rem; color: var(--accent-secondary);">Admin</span>' : ''; ?></a>
        <nav class="nav-links" id="nav-links">
            <a href="/" <?php echo nav_active('index', $current_page); ?>><?php echo t('nav_home'); ?></a>
            <a href="/dashboard" <?php echo nav_active('dashboard', $current_page); ?>><?php echo t('nav_today'); ?></a>
            <a href="/timer" <?php echo nav_active('timer', $current_page); ?>><?php echo t('nav_timer'); ?></a>
            <a href="/routines" <?php echo nav_active('routines', $current_page); ?>><?php echo t('nav_routines'); ?></a>
            <a href="/habits" <?php echo nav_active('habits', $current_page); ?>><?php echo t('nav_habits'); ?></a>
            <a href="/journal" <?php echo nav_active('journal', $current_page); ?>><?php echo t('nav_journal'); ?></a>
            <a href="/breathe" <?php echo nav_active('breathe', $current_page); ?>><?php echo t('nav_calm'); ?></a>
            <a href="/assessment" <?php echo nav_active('assessment', $current_page); ?>><?php echo t('nav_selftest'); ?></a>
            <a href="/articles" <?php echo nav_active('articles', $current_page); ?>><?php echo t('nav_articles'); ?></a>
            <?php if ($is_logged_in): ?>
                <?php if ($is_admin): ?>
                    <a href="/admin">Users</a>
                    <a href="/admin_articles">CMS</a>
                <?php endif; ?>
                <a href="/settings" <?php echo nav_active('settings', $current_page); ?>><?php echo t('nav_settings'); ?></a>
                <a href="/logout" class="btn btn-secondary"><?php echo t('nav_logout'); ?></a>
            <?php else: ?>
                <a href="/login" class="btn btn-secondary"><?php echo t('nav_login'); ?></a>
                <a href="/register" class="btn btn-primary"><?php echo t('nav_signup'); ?></a>
            <?php endif; ?>
        </nav>
        <select class="lang-select" id="lang-select" aria-label="Language">
            <?php foreach ($GLOBALS['ADHD_LANGS'] as $code => $label): ?>
                <option value="<?php echo $code; ?>" <?php echo current_lang() === $code ? 'selected' : ''; ?>><?php echo $label; ?></option>
            <?php endforeach; ?>
        </select>
        <button class="theme-toggle" id="theme-toggle" aria-label="Switch to light theme" title="Switch theme">
            <svg class="icon-sun" xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>
            <svg class="icon-moon" xmlns="http://www.w3.org/2000/svg" width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>
        </button>
        <button class="nav-toggle" id="nav-toggle" aria-label="Open menu" aria-expanded="false" aria-controls="nav-links">
            <svg class="icon-bars" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            <svg class="icon-close" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </header>
    <script>
    (function () {
        var navbar = document.getElementById('navbar');
        var toggle = document.getElementById('nav-toggle');

        // --- Language switcher ---
        var langSel = document.getElementById('lang-select');
        if (langSel) {
            langSel.addEventListener('change', function () {
                var url = new URL(window.location.href);
                url.searchParams.set('lang', this.value);
                window.location.href = url.toString();
            });
        }

        // --- Theme toggle ---
        var themeBtn = document.getElementById('theme-toggle');
        var themeMeta = document.querySelector('meta[name="theme-color"]');
        function applyThemeMeta() {
            var t = document.documentElement.getAttribute('data-theme');
            if (themeMeta) themeMeta.setAttribute('content', t === 'light' ? '#f4f6fb' : '#0b1120');
            if (themeBtn) themeBtn.setAttribute('aria-label', t === 'light' ? 'Switch to dark theme' : 'Switch to light theme');
        }
        applyThemeMeta();
        if (themeBtn) {
            themeBtn.addEventListener('click', function () {
                var before = getComputedStyle(document.body).backgroundColor;
                var next = document.documentElement.getAttribute('data-theme') === 'light' ? 'dark' : 'light';
                document.documentElement.setAttribute('data-theme', next);
                try { localStorage.setItem('adhd_theme', next); } catch (e) {}
                applyThemeMeta();
                // Some embedded webviews fail to repaint on attribute-selector
                // changes; if nothing visibly changed, reload once to apply.
                setTimeout(function () {
                    if (getComputedStyle(document.body).backgroundColor === before) {
                        window.location.reload();
                    }
                }, 150);
            });
        }

        if (!navbar || !toggle) return;
        function setOpen(open) {
            navbar.classList.toggle('open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            toggle.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
        }
        toggle.addEventListener('click', function (e) {
            e.stopPropagation();
            setOpen(!navbar.classList.contains('open'));
        });
        // Close when a link is tapped or when tapping outside the menu
        document.getElementById('nav-links').addEventListener('click', function (e) {
            if (e.target.closest('a')) setOpen(false);
        });
        document.addEventListener('click', function (e) {
            if (navbar.classList.contains('open') && !navbar.contains(e.target)) setOpen(false);
        });
    })();
    </script>
