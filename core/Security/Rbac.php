<?php
declare(strict_types=1);

namespace Heartbeat\Security;

/**
 * Role-Based Access Control (RBAC) Engine & Client-Guard Permission Resolver.
 */
final class Rbac
{
    public const ROLE_SUPER_ADMIN = 'super_admin';
    public const ROLE_ADMIN = 'admin';
    public const ROLE_EDITOR = 'editor';
    public const ROLE_USER = 'user';
    public const ROLE_MEMBER = 'member';

    /**
     * All recognized system roles ordered by privilege level
     */
    public const ALL_ROLES = [
        self::ROLE_SUPER_ADMIN,
        self::ROLE_ADMIN,
        self::ROLE_EDITOR,
        self::ROLE_USER,
        self::ROLE_MEMBER,
    ];

    /**
     * Role labels in German for UI display
     */
    public const ROLE_LABELS = [
        self::ROLE_SUPER_ADMIN => 'Super-Administrator (Agentur)',
        self::ROLE_ADMIN       => 'Administrator (Kunde)',
        self::ROLE_EDITOR      => 'Redakteur',
        self::ROLE_USER        => 'Autor / Standard-Benutzer',
        self::ROLE_MEMBER      => 'Mitglied (Frontend)',
    ];

    /**
     * Role descriptions explaining access level
     */
    public const ROLE_DESCRIPTIONS = [
        self::ROLE_SUPER_ADMIN => 'Vollzugriff auf alle System-, Code-, Update- und White-Label-Einstellungen.',
        self::ROLE_ADMIN       => 'Verwaltung von Inhalten, Benutzern, Themes und Einstellungen (vorbehaltlich Client-Guard).',
        self::ROLE_EDITOR      => 'Erstellung und Pflege aller Seiten, Beiträge, Custom Post Types und Medien.',
        self::ROLE_USER        => 'Verfassen und Bearbeiten eigener Artikel und Hochladen von Medien.',
        self::ROLE_MEMBER      => 'Ausschließlicher Frontend-Zugriff auf geschützte Mitgliederbereiche.',
    ];

    /**
     * Role permissions mapping
     * @var array<string, array<string, bool>>
     */
    private const ROLE_PERMISSIONS = [
        self::ROLE_SUPER_ADMIN => [
            '*'                     => true,
            'access_admin'          => true,
            'manage_agency'         => true,
            'manage_code'           => true,
            'update_core'           => true,
            'manage_backups'        => true,
            'manage_users'          => true,
            'manage_options'        => true,
            'manage_themes'         => true,
            'manage_content'        => true,
            'manage_media'          => true,
            'edit_posts'            => true,
            'access_member_content' => true,
        ],
        self::ROLE_ADMIN => [
            'access_admin'          => true,
            'manage_agency'         => false, // Always locked for non-super admins
            'manage_code'           => true,  // Overridden if client_guard is enabled
            'update_core'           => true,  // Overridden if client_guard is enabled
            'manage_backups'        => true,  // Overridden if client_guard is enabled
            'manage_users'          => true,
            'manage_options'        => true,
            'manage_themes'         => true,
            'manage_content'        => true,
            'manage_media'          => true,
            'edit_posts'            => true,
            'access_member_content' => true,
        ],
        self::ROLE_EDITOR => [
            'access_admin'          => true,
            'manage_agency'         => false,
            'manage_code'           => false,
            'update_core'           => false,
            'manage_backups'        => false,
            'manage_users'          => false,
            'manage_options'        => false,
            'manage_themes'         => true,
            'manage_content'        => true,
            'manage_media'          => true,
            'edit_posts'            => true,
            'access_member_content' => true,
        ],
        self::ROLE_USER => [
            'access_admin'          => true,
            'manage_agency'         => false,
            'manage_code'           => false,
            'update_core'           => false,
            'manage_backups'        => false,
            'manage_users'          => false,
            'manage_options'        => false,
            'manage_themes'         => false,
            'manage_content'        => false,
            'manage_media'          => true,
            'edit_posts'            => true,
            'access_member_content' => true,
        ],
        self::ROLE_MEMBER => [
            'access_admin'          => false,
            'manage_agency'         => false,
            'manage_code'           => false,
            'update_core'           => false,
            'manage_backups'        => false,
            'manage_users'          => false,
            'manage_options'        => false,
            'manage_themes'         => false,
            'manage_content'        => false,
            'manage_media'          => false,
            'edit_posts'            => false,
            'access_member_content' => true,
        ],
    ];

    /**
     * Destructive permissions locked by Client-Guard for non-super admins
     */
    private const CLIENT_GUARD_LOCKED_PERMISSIONS = [
        'manage_agency',
        'manage_code',
        'update_core',
        'manage_backups',
    ];

    /**
     * Check if a role possesses a specific permission
     */
    public static function can(string $role, string $permission, bool $isClientGuardActive = true): bool
    {
        if ($role === self::ROLE_SUPER_ADMIN) {
            return true;
        }

        // Check Client-Guard restrictions for non-super admins
        if ($isClientGuardActive && in_array($permission, self::CLIENT_GUARD_LOCKED_PERMISSIONS, true)) {
            return false;
        }

        $perms = self::ROLE_PERMISSIONS[$role] ?? [];
        if (!empty($perms['*'])) {
            return true;
        }

        return !empty($perms[$permission]);
    }

    /**
     * Check if an active user with role $currentRole is permitted to edit/delete a user with role $targetRole
     */
    public static function canManageRole(string $currentRole, string $targetRole): bool
    {
        if ($currentRole === self::ROLE_SUPER_ADMIN) {
            return true;
        }

        if ($currentRole === self::ROLE_ADMIN) {
            // Admin can only manage roles below Super Admin
            return in_array($targetRole, [self::ROLE_ADMIN, self::ROLE_EDITOR, self::ROLE_USER, self::ROLE_MEMBER], true);
        }

        return false;
    }

    /**
     * Check if an active user with role $currentRole can assign role $newRole to another user
     */
    public static function canAssignRole(string $currentRole, string $newRole): bool
    {
        if ($currentRole === self::ROLE_SUPER_ADMIN) {
            return true;
        }

        if ($currentRole === self::ROLE_ADMIN) {
            // Admin cannot create or elevate to Super Admin
            return $newRole !== self::ROLE_SUPER_ADMIN && in_array($newRole, self::ALL_ROLES, true);
        }

        return false;
    }

    /**
     * Get human-readable role label
     */
    public static function getLabel(string $role): string
    {
        return self::ROLE_LABELS[$role] ?? ucfirst($role);
    }

    /**
     * Get role description
     */
    public static function getDescription(string $role): string
    {
        return self::ROLE_DESCRIPTIONS[$role] ?? '';
    }

    /**
     * Return list of assignable roles for the given current user role
     */
    public static function getAssignableRoles(string $currentRole): array
    {
        if ($currentRole === self::ROLE_SUPER_ADMIN) {
            return self::ALL_ROLES;
        }

        if ($currentRole === self::ROLE_ADMIN) {
            return [
                self::ROLE_ADMIN,
                self::ROLE_EDITOR,
                self::ROLE_USER,
                self::ROLE_MEMBER,
            ];
        }

        return [];
    }
}

