<?php
// api/index.php
// Vercel Front Controller Router

$requestUri = $_SERVER['REQUEST_URI'];
$parsedUrl = parse_url($requestUri);
$path = $parsedUrl['path'] ?? '/';

// Normalize path
$path = ltrim($path, '/');

// Default to index.php
if ($path === '' || $path === 'index.php') {
    require __DIR__ . '/../index.php';
    exit;
}

// Prevent directory traversal
$path = str_replace(['../', '..\\'], '', $path);

$targetFile = __DIR__ . '/../' . $path;

// If it's a directory, try index.php inside it
if (is_dir($targetFile)) {
    $targetFile = rtrim($targetFile, '/') . '/index.php';
}

// If the file exists and is a PHP file, require it
if (file_exists($targetFile) && pathinfo($targetFile, PATHINFO_EXTENSION) === 'php') {
    // Fix up $_SERVER variables so scripts think they were accessed directly
    $_SERVER['SCRIPT_NAME'] = '/' . $path;
    $_SERVER['SCRIPT_FILENAME'] = $targetFile;
    $_SERVER['PHP_SELF'] = '/' . $path;
    
    // Change working directory to the target file's directory
    chdir(dirname($targetFile));
    
    require $targetFile;
    exit;
}

// If it's a static file that wasn't caught by Vercel's routing
if (file_exists($targetFile) && is_file($targetFile)) {
    $ext = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
    $mimeTypes = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'webp' => 'image/webp',
        'ico' => 'image/x-icon',
        'webmanifest' => 'application/manifest+json'
    ];
    if (isset($mimeTypes[$ext])) {
        header('Content-Type: ' . $mimeTypes[$ext]);
        // Cache control for static assets
        header('Cache-Control: public, max-age=86400');
        readfile($targetFile);
        exit;
    }
}

// 404 Fallback
http_response_code(404);
echo "404 Not Found - CampusMarket";
exit;
