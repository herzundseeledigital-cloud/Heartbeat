<?php
declare(strict_types=1);

namespace Heartbeat\Gdpr;

use Heartbeat\Database\Database;
use Heartbeat\Security\Security;

/**
 * GDPR Data Privacy & User Rights Service
 * Handles Art. 15 (Right of Access/Export) and Art. 17 (Right to Erasure/Anonymization).
 */
final class GdprService
{
    private static ?GdprService $instance = null;
    private Database $db;

    private function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Export all personal data relating to a user in JSON format (GDPR Art. 15/20)
     * @return array<string, mixed>
     */
    public function exportUserData(int $userId): array
    {
        $user = $this->db->fetchOne(
            "SELECT id, username, email, role, status, created_at, updated_at FROM {prefix}users WHERE id = :id LIMIT 1",
            ['id' => $userId]
        );

        if (!$user) {
            return [];
        }

        // Fetch meta data
        $metaRows = $this->db->fetchAll(
            "SELECT meta_key, meta_value FROM {prefix}meta_data WHERE object_type = 'user' AND object_id = :id",
            ['id' => $userId]
        );

        $meta = [];
        foreach ($metaRows as $r) {
            $meta[$r['meta_key']] = $r['meta_value'];
        }

        // Fetch user posts
        $posts = $this->db->fetchAll(
            "SELECT id, title, slug, post_type, status, created_at, published_at FROM {prefix}posts WHERE author_id = :id",
            ['id' => $userId]
        );

        return [
            'export_type'        => 'GDPR Art. 15 Data Subject Access Request',
            'generated_at'       => date('Y-m-d H:i:s'),
            'user_profile'       => $user,
            'user_metadata'      => $meta,
            'authored_content'   => $posts,
        ];
    }

    /**
     * Anonymize user personal data (GDPR Art. 17 Right to Erasure)
     * Keeps database integrity while removing all PII.
     */
    public function anonymizeUser(int $userId): bool
    {
        $user = $this->db->fetchOne("SELECT id, role FROM {prefix}users WHERE id = :id LIMIT 1", ['id' => $userId]);
        if (!$user) {
            return false;
        }

        // Protection: do not anonymize the last super_admin
        if ($user['role'] === 'super_admin') {
            $superCount = (int)$this->db->fetchColumn("SELECT COUNT(*) FROM {prefix}users WHERE role = 'super_admin'");
            if ($superCount <= 1) {
                return false;
            }
        }

        $anonUsername = 'anonymized_' . $userId;
        $anonEmail = 'deleted_' . $userId . '@anonymized.local';
        $randomPass = Security::hashPassword(bin2hex(random_bytes(32)));

        // 1. Update user row
        $this->db->update(
            '{prefix}users',
            [
                'username'      => $anonUsername,
                'email'         => $anonEmail,
                'password_hash' => $randomPass,
                'status'        => 'inactive',
                'updated_at'    => date('Y-m-d H:i:s'),
            ],
            'id = :id',
            ['id' => $userId]
        );

        // 2. Remove all personal meta entries
        $this->db->delete('{prefix}meta_data', "object_type = 'user' AND object_id = :id", ['id' => $userId]);

        return true;
    }
}

