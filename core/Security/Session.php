<?php
declare(strict_types=1);

namespace Heartbeat\Security;

/**
 * Secure Session Manager with fixation protection, fingerprinting, and flash messaging.
 */
final class Session
{
    private const FINGERPRINT_KEY = '_hb_fingerprint';
    private const LAST_ACTIVITY_KEY = '_hb_last_activity';
    private const FLASH_KEY = '_hb_flash';
    private const DEFAULT_IDLE_TIMEOUT = 7200; // 2 hours

    /**
     * Start secure session if not already started
     */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE || php_sapi_name() === 'cli') {
            return;
        }

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        $sessionParams = [
            'lifetime' => 86400 * 7, // 7 days max lifetime
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ];

        session_set_cookie_params($sessionParams);
        session_name('hb_session');
        @session_start();

        // Validate session fingerprint and activity timeout
        self::validateSession();
    }

    /**
     * Regenerate session ID to prevent session fixation attacks
     */
    public static function regenerate(bool $deleteOldSession = true): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return false;
        }

        $result = session_regenerate_id($deleteOldSession);
        self::set(self::FINGERPRINT_KEY, self::generateFingerprint());
        self::set(self::LAST_ACTIVITY_KEY, time());

        return $result;
    }

    /**
     * Store key-value in session
     */
    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Retrieve key from session
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Check if key exists in session
     */
    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    /**
     * Remove key from session
     */
    public static function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Set a flash message that expires after the next request
     */
    public static function flash(string $type, string $message): void
    {
        $_SESSION[self::FLASH_KEY][] = [
            'type'    => $type, // 'success', 'error', 'info', 'warning'
            'message' => $message,
            'time'    => time(),
        ];
    }

    /**
     * Retrieve and clear all flash messages
     *
     * @return array<int, array{type: string, message: string, time: int}>
     */
    public static function getFlashes(): array
    {
        $flashes = $_SESSION[self::FLASH_KEY] ?? [];
        unset($_SESSION[self::FLASH_KEY]);
        return is_array($flashes) ? $flashes : [];
    }

    /**
     * Check if any flash messages are pending
     */
    public static function hasFlash(): bool
    {
        return !empty($_SESSION[self::FLASH_KEY]);
    }

    /**
     * Completely destroy current session
     */
    public static function destroy(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];

            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000,
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }

            @session_destroy();
        }
    }

    /**
     * Validate session fingerprint and activity timeout
     */
    private static function validateSession(): void
    {
        $now = time();
        $lastActivity = (int)self::get(self::LAST_ACTIVITY_KEY, 0);

        // Check idle timeout
        if ($lastActivity > 0 && ($now - $lastActivity) > self::DEFAULT_IDLE_TIMEOUT) {
            self::destroy();
            return;
        }

        self::set(self::LAST_ACTIVITY_KEY, $now);

        // Check fingerprint
        $currentFingerprint = self::generateFingerprint();
        $storedFingerprint = self::get(self::FINGERPRINT_KEY);

        if ($storedFingerprint === null) {
            self::set(self::FINGERPRINT_KEY, $currentFingerprint);
        } elseif (!hash_equals($storedFingerprint, $currentFingerprint)) {
            // Fingerprint mismatch (potential session hijacking): destroy session
            self::destroy();
        }
    }

    /**
     * Generate browser & IP subnet fingerprint
     */
    private static function generateFingerprint(): string
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

        // Mask last octet for IPv4 or last segments for IPv6 to accommodate minor dynamic IP changes
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ipParts = explode('.', $ip);
            $subnet = $ipParts[0] . '.' . ($ipParts[1] ?? '0') . '.' . ($ipParts[2] ?? '0');
        } else {
            $subnet = substr($ip, 0, 16);
        }

        return hash('sha256', $userAgent . '|' . $subnet);
    }
}

