<?php
declare(strict_types=1);

namespace Heartbeat\Security;

/**
 * General Security, Escaping, Password Hashing and Hardened Headers
 */
final class Security
{
    /**
     * Context-aware HTML escaping
     */
    public static function e(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Secure password hashing with BCrypt cost 12
     */
    public static function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Constant-time password verification
     */
    public static function verifyPassword(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Check if password hash needs upgrade
     */
    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => 12]);
    }

    /**
     * Sanitize string into clean URL slug
     */
    public static function sanitizeSlug(string $title): string
    {
        $slug = mb_strtolower(trim($title), 'UTF-8');
        
        // German umlaut conversions
        $slug = str_replace(
            ['ä', 'ö', 'ü', 'ß'],
            ['ae', 'oe', 'ue', 'ss'],
            $slug
        );

        // Replace non-alphanumeric characters with hyphens
        $slug = preg_replace('/[^a-z0-9]+/i', '-', $slug);
        return trim($slug, '-');
    }

    /**
     * Alias for sanitizeSlug
     */
    public static function slugify(string $title): string
    {
        return self::sanitizeSlug($title);
    }

    /**
     * Sanitize filename preventing directory traversal
     */
    public static function sanitizeFilename(string $filename): string
    {
        $basename = basename($filename);
        return preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $basename);
    }

    /**
     * Send standard hardened HTTP security headers
     */
    public static function sendSecurityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: SAMEORIGIN');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

        // Content Security Policy permitting self, inline styles & Alpine.js reactivity
        $csp = "default-src 'self'; " .
               "script-src 'self' 'unsafe-inline' 'unsafe-eval'; " .
               "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
               "font-src 'self' data: https://fonts.gstatic.com; " .
               "img-src 'self' data: https:; " .
               "connect-src 'self'; " .
               "frame-ancestors 'self'; " .
               "base-uri 'self'; " .
               "form-action 'self';";
        header("Content-Security-Policy: {$csp}");
    }
}
