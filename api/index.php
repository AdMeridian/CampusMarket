<?php
// ============================================================
// Vercel Front Controller
// Routes all PHP requests to the correct file.
// Static assets (CSS, JS, images) are served directly by Vercel CDN
// via the explicit routes defined in vercel.json.
// ============================================================

$projectRoot = realpath(__DIR__ . '/..');

// Parse the incoming request URI
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($requestUri, PHP_URL_PATH);
$path = rtrim($path, '/');

// Default to index.php for root
if ($path === '' || $path === '/') {
    $targetFile = $projectRoot . '/index.php';
} else {
    // Try exact path first (e.g., /pages/browse.php)
    $targetFile = $projectRoot . $path;
}

// Resolve and validate the target file
$realFile = realpath($targetFile);

// If exact path didn't work, try appending .php
if (!$realFile || !is_file($realFile)) {
    $realFile = realpath($targetFile . '.php');
}

// Security: ensure file is within project root and is a PHP file
if (
    $realFile
    && str_starts_with($realFile, $projectRoot)
    && is_file($realFile)
    && strtolower(pathinfo($realFile, PATHINFO_EXTENSION)) === 'php'
) {
    // Set working directory to the target file's directory
    // This ensures relative require/include paths work correctly
    chdir(dirname($realFile));
    require $realFile;
} else {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><title>404</title></head><body><h1>404 — Page Not Found</h1></body></html>';
}
