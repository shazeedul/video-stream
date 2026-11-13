<?php

declare(strict_types=1);

$publicPath = __DIR__.'/public';

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?? ''
);

$filePath = $publicPath.$uri;

// If requested file exists in public/, serve it directly with correct headers
if ($uri !== '/' && is_file($filePath)) {
    $mimeTypes = [
        'css' => 'text/css; charset=utf-8',
        'js' => 'application/javascript; charset=utf-8',
        'mjs' => 'application/javascript; charset=utf-8',
        'json' => 'application/json',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'mp3' => 'audio/mpeg',
        'mp4' => 'video/mp4',
        'webm' => 'video/webm',
    ];

    $ext = mb_strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $contentType = $mimeTypes[$ext] ?? (mime_content_type($filePath) ?: 'application/octet-stream');

    header("Content-Type: {$contentType}");
    header('Content-Length: '.(string) filesize($filePath));
    header('Cache-Control: public, max-age=31536000');
    readfile($filePath);
    exit;
}

require_once $publicPath.'/index.php';
