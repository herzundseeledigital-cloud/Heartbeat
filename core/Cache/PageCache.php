<?php
declare(strict_types=1);

namespace Heartbeat\Cache;

use Heartbeat\Http\Request;
use Heartbeat\Auth\Auth;
use Heartbeat\Security\Session;

/**
 * Full-Page Static HTML Cache Engine
 * Serves cached HTML pages to anonymous visitors for < 20ms TTFB.
 */
final class PageCache
{
    private static ?self $instance = null;
    private string $cacheDir;
    private int $defaultTtl = 86400; // 24 hours

    public function __construct(?string $cacheDir = null)
    {
        $base = $cacheDir ?? (defined('HEARTBEAT_STORAGE') ? HEARTBEAT_STORAGE : dirname(__DIR__, 2) . '/storage');
        $this->cacheDir = rtrim($base, '/') . '/cache/html';

        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Determine if the incoming request can be served from or stored in cache
     */
    public function isCacheable(Request $request): bool
    {
        // 1. Only GET and HEAD requests can be cached
        $method = $request->getMethod();
        if ($method !== 'GET' && $method !== 'HEAD') {
            return false;
        }

        // 2. Never cache admin or install routes
        $path = $request->getPath();
        if (str_starts_with($path, '/admin') || str_starts_with($path, '/install')) {
            return false;
        }

        // 3. Never cache for authenticated users (admins, editors, members)
        if (class_exists(Auth::class) && Auth::check()) {
            return false;
        }

        // 4. Never cache if query contains bypass or debug parameters
        if ($request->get('nocache') !== null || 
            $request->get('preview') !== null || 
            $request->get('hb_safe_mode') !== null || 
            $request->get('safe_mode') !== null) {
            return false;
        }

        // 5. Never cache if flash messages are queued
        if (class_exists(Session::class) && Session::hasFlash()) {
            return false;
        }

        return true;
    }

    /**
     * Retrieve cached HTML if available and not expired
     */
    public function get(Request $request): ?string
    {
        if (!$this->isCacheable($request)) {
            return null;
        }

        $key = $this->generateKey($request);
        $file = $this->cacheDir . '/' . $key . '.html';

        if (!is_file($file)) {
            return null;
        }

        $content = @file_get_contents($file);
        if ($content === false) {
            return null;
        }

        // Read expiration timestamp from header comment: <!-- HB_CACHE_EXPIRES:1729188000 -->
        if (preg_match('/<!-- HB_CACHE_EXPIRES:(\d+) -->/', $content, $m)) {
            $expiresAt = (int)$m[1];
            if ($expiresAt < time()) {
                @unlink($file);
                return null;
            }
        }

        return $content;
    }

    /**
     * Store rendered HTML in static cache
     */
    public function put(Request $request, string $html, ?int $ttl = null): bool
    {
        if (!$this->isCacheable($request)) {
            return false;
        }

        // Do not cache empty or error responses
        if (trim($html) === '') {
            return false;
        }

        $key = $this->generateKey($request);
        $file = $this->cacheDir . '/' . $key . '.html';

        $duration = $ttl ?? $this->defaultTtl;
        $expiresAt = time() + $duration;
        $generatedAt = date('Y-m-d H:i:s');

        // Prepend metadata comment
        $cachePayload = "<!-- HB_CACHE_EXPIRES:{$expiresAt} -->\n" .
                        "<!-- Heartbeat PageCache | Generated: {$generatedAt} | Key: {$key} -->\n" .
                        $html;

        return (bool)@file_put_contents($file, $cachePayload, LOCK_EX);
    }

    /**
     * Invalidate cache for a specific path or all pages
     */
    public function invalidate(string $path = ''): bool
    {
        if ($path === '') {
            return $this->flush();
        }

        $key = md5(rtrim($path, '/'));
        $file = $this->cacheDir . '/' . $key . '.html';

        if (is_file($file)) {
            return @unlink($file);
        }

        return true;
    }

    /**
     * Flush all cached HTML pages
     */
    public function flush(): bool
    {
        if (!is_dir($this->cacheDir)) {
            return true;
        }

        $files = glob($this->cacheDir . '/*.html') ?: [];
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        return true;
    }

    /**
     * Count cached pages
     */
    public function count(): int
    {
        if (!is_dir($this->cacheDir)) {
            return 0;
        }
        $files = glob($this->cacheDir . '/*.html') ?: [];
        return count($files);
    }

    /**
     * Generate unique MD5 hash for request path and normalized query
     */
    private function generateKey(Request $request): string
    {
        $path = rtrim($request->getPath(), '/');
        if ($path === '') {
            $path = '/';
        }
        return md5($path);
    }
}

