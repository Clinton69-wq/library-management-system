<?php
/**
 * Vercel Serverless Function Router for ApexLib
 */
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH);

// Normalize path
$file = ltrim($path, '/');
if (str_starts_with($file, 'api/')) {
    $file = substr($file, 4);
}

if (empty($file) || $file === 'index.php') {
    $file = 'index.php';
}

chdir(__DIR__ . '/..');

$target = __DIR__ . '/../' . $file;
if (is_file($target) && pathinfo($target, PATHINFO_EXTENSION) === 'php') {
    $_SERVER['SCRIPT_NAME'] = '/' . $file;
    $_SERVER['PHP_SELF'] = '/' . $file;
    $_SERVER['SCRIPT_FILENAME'] = $target;
    require $target;
    exit;
}

// Fallback to index.php
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/../index.php';
require __DIR__ . '/../index.php';
