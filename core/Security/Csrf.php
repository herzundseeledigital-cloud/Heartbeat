<?php
declare(strict_types=1);

namespace Heartbeat\Security;

use Heartbeat\Http\Request;

/**
 * CSRF Protection Manager
 */
final class Csrf
{
    private const SESSION_KEY = '_hb_csrf_token';

    /**
     * Get or create active CSRF token
     */
    public static function token(): string
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Generate HTML hidden input for forms
     */
    public static function field(): string
    {
        $token = self::token();
        return '<input type="hidden" name="_csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Alias for field()
     */
    public static function formInput(): string
    {
        return self::field();
    }

    /**
     * Generate HTML meta tag for AJAX requests
     */
    public static function meta(): string
    {
        $token = self::token();
        return '<meta name="csrf-token" content="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }

    /**
     * Validate a token string against the session
     */
    public static function validate(?string $token): bool
    {
        if (empty($token) || empty($_SESSION[self::SESSION_KEY])) {
            return false;
        }

        return hash_equals($_SESSION[self::SESSION_KEY], $token);
    }

    /**
     * Verify CSRF token from Request (checking POST parameter, JSON body, or X-CSRF-TOKEN header)
     */
    public static function verifyRequest(Request $request): bool
    {
        $token = (string)(
            $request->post('_csrf_token') 
            ?? $request->input('_csrf_token') 
            ?? $request->header('X-CSRF-TOKEN')
            ?? $request->header('X-XSRF-TOKEN')
            ?? ''
        );

        return self::validate($token);
    }

    /**
     * Rotate CSRF token
     */
    public static function rotate(): string
    {
        $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        return $_SESSION[self::SESSION_KEY];
    }
}

