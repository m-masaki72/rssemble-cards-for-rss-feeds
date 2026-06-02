<?php
/**
 * php -S localhost:8080 router.php で起動するルーター。
 * /plugin/assets/* → プラグインの assets/ ディレクトリへマップ。
 * それ以外 → index.php へ。
 */

$uri = $_SERVER['REQUEST_URI'];

// /plugin/assets/... を実ファイルへマップ
if (preg_match('#^/plugin/assets/(.+)$#', $uri, $m)) {
    $file = dirname(__DIR__) . '/rss-grid-card/assets/' . $m[1];
    $file = realpath($file);

    // パストラバーサル防止
    $allowed_base = realpath(dirname(__DIR__) . '/rss-grid-card/assets');
    if (!$file || strpos($file, $allowed_base) !== 0) {
        http_response_code(403);
        exit('Forbidden');
    }

    if (!is_file($file)) {
        http_response_code(404);
        exit('Not Found');
    }

    $ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    $mime_map = [
        'css'  => 'text/css',
        'js'   => 'application/javascript',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'webp' => 'image/webp',
    ];
    $mime = isset($mime_map[$ext]) ? $mime_map[$ext] : 'application/octet-stream';

    header("Content-Type: $mime");
    readfile($file);
    return true;
}

// それ以外はメインのindex.phpへ
require __DIR__ . '/index.php';
