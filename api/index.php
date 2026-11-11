<?php
/**
 * Vercel Serverless Function Router for ApexLib
 */
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH);

// Normalize path
$file = ltrim($path, '/');
if (empty($file) || $file === 'index.php') {
    require __DIR__ . '/../index.php';
    exit;
}

$target = __DIR__ . '/../' . $file;
if (is_file($target) && pathinfo($target, PATHINFO_EXTENSION) === 'php') {
    require $target;
    exit;
}

// Fallback to index.php
require __DIR__ . '/../index.php';
