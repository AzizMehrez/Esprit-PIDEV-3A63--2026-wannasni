<?php
/**
 * Router script for PHP's built-in web server.
 * Serves static files directly and routes everything else through index.php.
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$filePath = __DIR__ . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $uri), DIRECTORY_SEPARATOR);

// If the requested file exists and is not a directory
if ($uri !== '/' && is_file($filePath)) {
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

    // PHP files should be executed normally
    if ($ext === 'php') {
        return false;
    }

    // Static file MIME types
    $mimeTypes = [
        'js'   => 'application/javascript',
        'mjs'  => 'application/javascript',
        'css'  => 'text/css',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'ico'  => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2'=> 'font/woff2',
        'ttf'  => 'font/ttf',
        'eot'  => 'application/vnd.ms-fontobject',
        'json' => 'application/json',
        'xml'  => 'application/xml',
        'mp3'  => 'audio/mpeg',
        'wav'  => 'audio/wav',
        'ogg'  => 'audio/ogg',
        'mp4'  => 'video/mp4',
        'webm' => 'video/webm',
        'webp' => 'image/webp',
        'map'  => 'application/json',
        'txt'  => 'text/plain',
        'html' => 'text/html',
        'htm'  => 'text/html',
    ];

    $contentType = $mimeTypes[$ext] ?? 'application/octet-stream';
    header('Content-Type: ' . $contentType);
    readfile($filePath);
    exit; // Hard exit — do NOT fall through to index.php
}

// Route everything else through the Symfony front controller
require __DIR__ . '/index.php';
