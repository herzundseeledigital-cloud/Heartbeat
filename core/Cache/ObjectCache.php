<?php
declare(strict_types=1);

namespace Heartbeat\Cache;

/**
 * Multi-Driver Object & Query Cache Engine
 * Supports APCu with automatic fallback to high-speed atomic file caching.
 */
final class ObjectCache
{
    private static ?self $instance = null;
    private string $cacheDir;
    private string $driver;

    public function __construct(?string $cacheDir = null)
    {
        $base = $cacheDir ?? (defined('HEARTBEAT_STORAGE') ? HEARTBEAT_STORAGE : dirname(__DIR__, 2) . '/storage');
        $this->cacheDir = rtrim($base, '/') . '/cache/objects';

        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0755, true);
        }

        // Determine driver
        if (function_exists('apcu_fetch') && (bool)ini_get('apc.enabled')) {
            $this->driver = 'apcu';
        } else {
            $this->driver = 'file';
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getDriver(): string
    {
        return $this->driver;
    }

    /**
     * Retrieve an item from the cache
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if ($this->driver === 'apcu') {
            $success = false;
            $val = apcu_fetch('hb_' . $key, $success);
            return $success ? $val : $default;
        }

        $file = $this->getFilePath($key);
        if (!is_file($file)) {
            return $default;
        }

        $raw = @file_get_contents($file);
        if ($raw === false) {
            return $default;
        }

        $data = @unserialize($raw);
        if (!is_array($data) || !isset($data['expires'], $data['value'])) {
            @unlink($file);
            return $default;
        }

        if ($data['expires'] !== 0 && $data['expires'] < time()) {
            @unlink($file);
            return $default;
        }

        return $data['value'];
    }

    /**
     * Store an item in the cache
     */
    public function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        if ($this->driver === 'apcu') {
            return apcu_store('hb_' . $key, $value, $ttl);
        }

        $file = $this->getFilePath($key);
        $payload = serialize([
            'expires' => $ttl > 0 ? time() + $ttl : 0,
            'value'   => $value,
        ]);

        return (bool)@file_put_contents($file, $payload, LOCK_EX);
    }

    /**
     * Check if an item exists in the cache and has not expired
     */
    public function has(string $key): bool
    {
        if ($this->driver === 'apcu') {
            return apcu_exists('hb_' . $key);
        }

        return $this->get($key, '__HB_NOT_FOUND__') !== '__HB_NOT_FOUND__';
    }

    /**
     * Delete an item from the cache
     */
    public function delete(string $key): bool
    {
        if ($this->driver === 'apcu') {
            return apcu_delete('hb_' . $key);
        }

        $file = $this->getFilePath($key);
        if (is_file($file)) {
            return @unlink($file);
        }

        return true;
    }

    /**
     * Flush all cached items
     */
    public function flush(): bool
    {
        if ($this->driver === 'apcu') {
            return apcu_clear_cache();
        }

        if (!is_dir($this->cacheDir)) {
            return true;
        }

        $files = glob($this->cacheDir . '/*.cache') ?: [];
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }

        return true;
    }

    /**
     * Get an item from the cache, or execute the given Closure and store the result
     */
    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $val = $this->get($key, '__HB_MISS__');
        if ($val !== '__HB_MISS__') {
            return $val;
        }

        $fresh = $callback();
        $this->set($key, $fresh, $ttl);
        return $fresh;
    }

    private function getFilePath(string $key): string
    {
        $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $key);
        return $this->cacheDir . '/' . md5($key) . '_' . substr($safeName, 0, 32) . '.cache';
    }
}

