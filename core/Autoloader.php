<?php
declare(strict_types=1);

namespace Heartbeat;

/**
 * PSR-4 Compliant Autoloader for Heartbeat CMS
 * Allows zero-dependency execution without requiring Composer installed on the host.
 */
final class Autoloader
{
    /** @var array<string, string> */
    private static array $prefixes = [];

    private static bool $registered = false;

    /**
     * Register autoloader with SPL
     */
    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        // Register default Heartbeat\ namespace to core directory
        self::addNamespace('Heartbeat\\', dirname(__DIR__) . '/core');

        spl_autoload_register([self::class, 'loadClass']);

        // Check if Composer vendor autoloader exists and load it as well
        $vendorAutoload = dirname(__DIR__) . '/vendor/autoload.php';
        if (is_file($vendorAutoload)) {
            require_once $vendorAutoload;
        }

        self::$registered = true;
    }

    /**
     * Map a namespace prefix to a base directory
     */
    public static function addNamespace(string $prefix, string $baseDir): void
    {
        $prefix = trim($prefix, '\\') . '\\';
        $baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        self::$prefixes[$prefix] = $baseDir;
    }

    /**
     * Load the requested class file
     */
    public static function loadClass(string $class): bool
    {
        foreach (self::$prefixes as $prefix => $baseDir) {
            $len = strlen($prefix);
            if (strncmp($prefix, $class, $len) !== 0) {
                continue;
            }

            $relativeClass = substr($class, $len);
            $file = $baseDir . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

            if (is_file($file)) {
                require $file;
                return true;
            }
        }

        return false;
    }
}

