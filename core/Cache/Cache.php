<?php
declare(strict_types=1);

namespace Heartbeat\Cache;

/**
 * Unified Cache Facade
 * Provides static access to PageCache and ObjectCache tiers.
 */
final class Cache
{
    public static function page(): PageCache
    {
        return PageCache::getInstance();
    }

    public static function object(): ObjectCache
    {
        return ObjectCache::getInstance();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::object()->get($key, $default);
    }

    public static function set(string $key, mixed $value, int $ttl = 3600): bool
    {
        return self::object()->set($key, $value, $ttl);
    }

    public static function has(string $key): bool
    {
        return self::object()->has($key);
    }

    public static function remember(string $key, int $ttl, callable $callback): mixed
    {
        return self::object()->remember($key, $ttl, $callback);
    }

    public static function forget(string $key): bool
    {
        return self::object()->delete($key);
    }

    /**
     * Flush all caches (both static full-page and object cache)
     */
    public static function flush(): bool
    {
        $pageOk = self::page()->flush();
        $objectOk = self::object()->flush();
        return $pageOk && $objectOk;
    }
}

