<?php
declare(strict_types=1);

/**
 * Heartbeat CMS - Local Development Server Router
 * 
 * Usage: php -S localhost:8000 -t public router.php
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');
$publicDir = __DIR__ . '/public';
$filePath = realpath($publicDir . $uri);

// Block access to sensitive paths or hidden files
if (preg_match('#/\.(?!well-known)#', $uri) ||
    preg_match('#^/(core|config|storage|plugins|themes/.*\.json|vendor|bin)/#', $uri)) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "403 Forbidden: Direct access to this directory is prohibited.";
    exit;
}

// Serve existing static file in /public directly
if ($filePath !== false && is_file($filePath) && str_starts_with($filePath, $publicDir)) {
    // Map mime types for common assets
    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $mimes = [
        'css'   => 'text/css; charset=utf-8',
        'js'    => 'application/javascript; charset=utf-8',
        'json'  => 'application/json; charset=utf-8',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'svg'   => 'image/svg+xml',
        'webp'  => 'image/webp',
        'avif'  => 'image/avif',
        'woff'  => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf'   => 'font/ttf',
        'ico'   => 'image/x-icon',
    ];

    if (isset($mimes[$ext])) {
        header('Content-Type: ' . $mimes[$ext]);
    }

    return false; // Let PHP built-in server stream the file
}

// Forward all other requests to the public front controller
require $publicDir . '/index.php';

