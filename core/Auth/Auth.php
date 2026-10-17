<?php
declare(strict_types=1);

namespace Heartbeat\Auth;

use Heartbeat\Database\Database;
use Heartbeat\Security\Security;
use Heartbeat\Security\Session;
use Heartbeat\Security\Rbac;
use Heartbeat\Agency\AgencyService;

/**
 * Core Authentication Manager
 */
final class Auth
{
    private const SESSION_USER_ID = '_hb_auth_user_id';
    private const REMEMBER_COOKIE = 'hb_remember_token';
    private static ?array $cachedUser = null;

    /**
     * Attempt login with username/email and password
     */
    public static function attempt(string $usernameOrEmail, string $password, bool $remember = false): bool
    {
        Session::start();

        $identifier = trim($usernameOrEmail);
        if ($identifier === '' || $password === '') {
            return false;
        }

        try {
            $db = Database::getInstance();
            $user = $db->fetchOne(
                "SELECT * FROM {prefix}users WHERE (username = :u OR email = :e) AND status = 'active' LIMIT 1",
                ['u' => $identifier, 'e' => $identifier]
            );

            if (!$user) {
                return false;
            }

            if (!Security::verifyPassword($password, (string)$user['password_hash'])) {
                return false;
            }

            // Check if password hash needs upgrade
            if (Security::needsRehash((string)$user['password_hash'])) {
                $newHash = Security::hashPassword($password);
                $db->update('{prefix}users', ['password_hash' => $newHash], 'id = :id', ['id' => $user['id']]);
            }

            // Successful login: regenerate session ID to prevent fixation
            Session::regenerate(true);
            self::setUserSession($user);

            if ($remember) {
                self::setRememberCookie((int)$user['id']);
            }

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Manually log in a user instance
     */
    public static function login(array $user, bool $remember = false): void
    {
        Session::start();
        Session::regenerate(true);
        self::setUserSession($user);

        if ($remember && isset($user['id'])) {
            self::setRememberCookie((int)$user['id']);
        }
    }

    /**
     * Retrieve the currently authenticated user record
     */
    public static function user(): ?array
    {
        Session::start();

        if (self::$cachedUser !== null) {
            return self::$cachedUser;
        }

        $userId = Session::get(self::SESSION_USER_ID);

        // Try remember me cookie fallback if session expired
        if ($userId === null) {
            $userId = self::validateRememberCookie();
            if ($userId !== null) {
                Session::set(self::SESSION_USER_ID, $userId);
            }
        }

        if ($userId === null) {
            return null;
        }

        try {
            $db = Database::getInstance();
            $user = $db->fetchOne("SELECT * FROM {prefix}users WHERE id = :id AND status = 'active' LIMIT 1", [
                'id' => (int)$userId
            ]);

            if ($user) {
                unset($user['password_hash']); // Prevent leaking hash in runtime state
                self::$cachedUser = $user;
                return self::$cachedUser;
            }
        } catch (\Throwable) {
        }

        // Invalid user state
        self::logout();
        return null;
    }

    /**
     * Current authenticated user ID
     */
    public static function id(): ?int
    {
        $u = self::user();
        return $u !== null ? (int)$u['id'] : null;
    }

    /**
     * Check if user is authenticated
     */
    public static function check(): bool
    {
        return self::user() !== null;
    }

    /**
     * Check if current visitor is a guest
     */
    public static function guest(): bool
    {
        return !self::check();
    }

    /**
     * Current user role
     */
    public static function role(): ?string
    {
        $u = self::user();
        return $u !== null ? (string)$u['role'] : null;
    }

    /**
     * Check if user has Super-Admin role
     */
    public static function isSuperAdmin(): bool
    {
        return self::role() === Rbac::ROLE_SUPER_ADMIN;
    }

    /**
     * Check if user possesses specified role(s)
     */
    public static function hasRole(string|array $roles): bool
    {
        $currentRole = self::role();
        if ($currentRole === null) {
            return false;
        }

        $roleList = is_array($roles) ? $roles : [$roles];
        return in_array($currentRole, $roleList, true);
    }

    /**
     * Check if current user is authorized for permission
     */
    public static function can(string $permission): bool
    {
        $role = self::role();
        if ($role === null) {
            return false;
        }

        $isClientGuardActive = AgencyService::getInstance()->isClientGuardActive();
        return Rbac::can($role, $permission, $isClientGuardActive);
    }

    /**
     * Log out current user and clear all auth cookies and session
     */
    public static function logout(): void
    {
        self::$cachedUser = null;
        Session::remove(self::SESSION_USER_ID);

        // Clear remember cookie
        if (isset($_COOKIE[self::REMEMBER_COOKIE])) {
            setcookie(self::REMEMBER_COOKIE, '', time() - 3600, '/', '', false, true);
            unset($_COOKIE[self::REMEMBER_COOKIE]);
        }

        Session::destroy();
    }

    /**
     * Set user ID in session
     */
    private static function setUserSession(array $user): void
    {
        $id = (int)$user['id'];
        Session::set(self::SESSION_USER_ID, $id);
        self::$cachedUser = $user;
        unset(self::$cachedUser['password_hash']);
    }

    /**
     * Set cryptographic Remember Me cookie
     */
    private static function setRememberCookie(int $userId): void
    {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $cookieVal = $userId . '|' . $token;

        // Store hash in meta_data
        try {
            $db = Database::getInstance();
            $db->query("DELETE FROM {prefix}meta_data WHERE object_type = 'user' AND object_id = :id AND meta_key = 'remember_token'", [
                'id' => $userId
            ]);
            $db->insert('{prefix}meta_data', [
                'object_type' => 'user',
                'object_id'   => $userId,
                'meta_key'    => 'remember_token',
                'meta_value'  => $hash,
            ]);

            setcookie(
                self::REMEMBER_COOKIE,
                $cookieVal,
                time() + (86400 * 30), // 30 days
                '/',
                '',
                (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
                true // HTTPOnly
            );
        } catch (\Throwable) {
        }
    }

    /**
     * Validate remember me cookie and return user ID if valid
     */
    private static function validateRememberCookie(): ?int
    {
        if (empty($_COOKIE[self::REMEMBER_COOKIE])) {
            return null;
        }

        $parts = explode('|', (string)$_COOKIE[self::REMEMBER_COOKIE], 2);
        if (count($parts) !== 2) {
            return null;
        }

        $userId = (int)$parts[0];
        $token = $parts[1];

        try {
            $db = Database::getInstance();
            $meta = $db->fetchOne(
                "SELECT meta_value FROM {prefix}meta_data WHERE object_type = 'user' AND object_id = :id AND meta_key = 'remember_token' LIMIT 1",
                ['id' => $userId]
            );

            if ($meta && hash_equals($meta['meta_value'], hash('sha256', $token))) {
                return $userId;
            }
        } catch (\Throwable) {
        }

        return null;
    }
}

