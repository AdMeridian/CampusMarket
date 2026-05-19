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

// Elegant URL cleaner: automatically redirect explicit .php requests to extensionless URIs for a premium feel
if ($_SERVER['REQUEST_METHOD'] === 'GET' && substr($path, -4) === '.php') {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $isJson = str_contains($accept, 'application/json');
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    $isApi = str_contains($path, 'api') || str_contains($path, '/api/');
    
    if (!$isJson && !$isAjax && !$isApi) {
        $cleanPath = substr($path, 0, -4);
        $queryString = $_SERVER['QUERY_STRING'] ?? '';
        $redirectUrl = $cleanPath . ($queryString !== '' ? '?' . $queryString : '');
        header('Location: ' . $redirectUrl, true, 301);
        exit;
    }
}

// Static fallback: if a static request reaches this front controller,
// serve the file directly instead of returning 404.
$normalizedPath = $path === '' ? '/' : $path;
$isAllowedStaticPath =
    str_starts_with($normalizedPath, '/public/')
    || in_array($normalizedPath, ['/manifest.webmanifest', '/sw.js', '/robots.txt', '/favicon.ico', '/favicon.png'], true);

if ($isAllowedStaticPath) {
    $staticTarget = $projectRoot . $normalizedPath;
    $realStatic = realpath($staticTarget);
    if ($realStatic && str_starts_with($realStatic, $projectRoot) && is_file($realStatic)) {
        $ext = strtolower(pathinfo($realStatic, PATHINFO_EXTENSION));
        $mimeMap = [
            'css' => 'text/css; charset=utf-8',
            'js' => 'application/javascript; charset=utf-8',
            'json' => 'application/json; charset=utf-8',
            'webmanifest' => 'application/manifest+json; charset=utf-8',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            'txt' => 'text/plain; charset=utf-8',
        ];
        if (isset($mimeMap[$ext])) {
            header('Content-Type: ' . $mimeMap[$ext]);
        }
        readfile($realStatic);
        exit;
    }
}

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
