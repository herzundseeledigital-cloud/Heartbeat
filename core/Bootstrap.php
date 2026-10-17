<?php
declare(strict_types=1);

namespace Heartbeat;

use Throwable;
use ErrorException;

/**
 * Core Bootstrap class for Heartbeat CMS
 */
final class Bootstrap
{
    private static bool $bootstrapped = false;
    private static array $config = [];

    public static function init(): void
    {
        if (self::$bootstrapped) {
            return;
        }

        // Define system path constants
        if (!defined('HEARTBEAT_ROOT')) {
            define('HEARTBEAT_ROOT', dirname(__DIR__));
            define('HEARTBEAT_CORE', HEARTBEAT_ROOT . '/core');
            define('HEARTBEAT_CONFIG', HEARTBEAT_ROOT . '/config');
            define('HEARTBEAT_STORAGE', HEARTBEAT_ROOT . '/storage');
            define('HEARTBEAT_UPLOADS', HEARTBEAT_ROOT . '/uploads');
            define('HEARTBEAT_THEMES', HEARTBEAT_ROOT . '/themes');
            define('HEARTBEAT_PLUGINS', HEARTBEAT_ROOT . '/plugins');
            define('HEARTBEAT_PUBLIC', HEARTBEAT_ROOT . '/public');
            define('HEARTBEAT_VERSION', '1.0.0');
        }

        // Register autoloader
        require_once HEARTBEAT_CORE . '/Autoloader.php';
        Autoloader::register();

        // Load environment config if available
        $envFile = HEARTBEAT_CONFIG . '/env.php';
        if (is_file($envFile)) {
            $loadedConfig = require $envFile;
            if (is_array($loadedConfig)) {
                self::$config = $loadedConfig;
            }
        }

        // Set timezone
        $timezone = self::$config['app']['timezone'] ?? 'Europe/Berlin';
        date_default_timezone_set($timezone);

        // Setup error and exception handlers
        self::setupErrorHandling();

        // Start secure session if not yet started and not running in CLI
        self::initSession();

        self::$bootstrapped = true;
    }

    /**
     * Retrieve configuration value by dot notation
     */
    public static function config(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $data = self::$config;

        foreach ($segments as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                return $default;
            }
            $data = $data[$segment];
        }

        return $data;
    }

    /**
     * Check if the CMS has been installed
     */
    public static function isInstalled(): bool
    {
        return is_file(HEARTBEAT_CONFIG . '/installed.lock');
    }

    private static function setupErrorHandling(): void
    {
        $debug = (bool)(self::$config['app']['debug'] ?? true);

        if ($debug) {
            ini_set('display_errors', '1');
            ini_set('display_startup_errors', '1');
            error_reporting(E_ALL);
        } else {
            ini_set('display_errors', '0');
            ini_set('display_startup_errors', '0');
            error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
        }

        // Convert PHP notices/warnings into ErrorExceptions
        set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        // Uncaught exception handler
        set_exception_handler(function (Throwable $e) use ($debug): void {
            self::logException($e);

            if (php_sapi_name() === 'cli') {
                fwrite(STDERR, "Uncaught Exception: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
                exit(1);
            }

            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: text/html; charset=utf-8');
            }

            if ($debug) {
                echo '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><title>Heartbeat Error</title>';
                echo '<style>body{background:#090d16;color:#f8fafc;font-family:sans-serif;padding:2rem;}';
                echo '.box{background:#0f172a;border:1px solid #1e293b;border-radius:12px;padding:2rem;max-width:900px;margin:0 auto;}';
                echo 'h1{color:#f97316;margin-top:0;} pre{background:#090d16;padding:1rem;border-radius:8px;overflow-x:auto;color:#cbd5e1;}';
                echo '</style></head><body><div class="box">';
                echo '<h1>Heartbeat CMS - System Exception</h1>';
                echo '<p><strong>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</strong></p>';
                echo '<p>File: ' . htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8') . ' (Line ' . $e->getLine() . ')</p>';
                echo '<pre>' . htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8') . '</pre>';
                echo '</div></body></html>';
            } else {
                echo '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8"><title>500 - Server Error</title>';
                echo '<style>body{background:#090d16;color:#94a3b8;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}';
                echo '.box{text-align:center;padding:2rem;} h1{color:#f97316;margin:0 0 1rem;}';
                echo '</style></head><body><div class="box">';
                echo '<h1>500 - Ein interner Fehler ist aufgetreten</h1>';
                echo '<p>Der Vorfall wurde im Systemprotokoll erfasst. Bitte wenden Sie sich an Ihren Administrator.</p>';
                echo '</div></body></html>';
            }
            exit(1);
        });
    }

    private static function initSession(): void
    {
        \Heartbeat\Security\Session::start();
    }

    private static function logException(Throwable $e): void
    {
        $logDir = HEARTBEAT_STORAGE . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0775, true);
        }

        $logFile = $logDir . '/error-' . date('Y-m-d') . '.log';
        $entry = sprintf(
            "[%s] %s: %s in %s:%d\nStack trace:\n%s\n\n",
            date('Y-m-d H:i:s'),
            get_class($e),
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        );

        @file_put_contents($logFile, $entry, FILE_APPEND);
    }
}

