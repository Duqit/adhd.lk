<?php
// dev/router.php — local development router for PHP's built-in server.
// Mirrors the clean URLs that public_html/.htaccess provides on Apache:
//
//   php -S localhost:8000 -t public_html dev/router.php
//
// Never deploy this file; production uses Apache + .htaccess.

$root = dirname(__DIR__) . '/public_html';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Static files (css, js, images, manifest, sw.js) are served as-is.
if ($path !== '/' && is_file($root . $path) && substr($path, -4) !== '.php') {
    return false;
}

if ($path === '/sitemap.xml') {
    chdir($root);
    require $root . '/sitemap.php';
    return true;
}

// /article/<id>-<slug> → article.php?id=<id>
if (preg_match('#^/article/(\d+)(?:-[^/]*)?/?$#', $path, $m)) {
    $_GET['id'] = $m[1];
    $_SERVER['PHP_SELF'] = '/article.php';
    chdir($root);
    require $root . '/article.php';
    return true;
}

// /timer → timer.php, /api/import → api/import.php, / → index.php
$name = $path === '/' ? 'index' : preg_replace('/\.php$/', '', trim($path, '/'));
$file = $root . '/' . $name . '.php';
if (strpos($name, '..') === false && is_file($file)) {
    $_SERVER['PHP_SELF'] = '/' . $name . '.php';
    chdir(dirname($file));
    require $file;
    return true;
}

http_response_code(404);
echo '404 Not Found';
