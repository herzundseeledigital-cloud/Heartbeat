<?php
declare(strict_types=1);

use Heartbeat\Database\Database;
use Heartbeat\Security\Security;

/**
 * Migration 003: Add unique-id (UUID v4) and hierarchical parent_id to schema
 */
return new class {
    public function up(Database $db): void
    {
        $isSqlite = $db->getDriver() === 'sqlite';

        // 1. Users uuid
        try {
            $db->query("ALTER TABLE {prefix}users ADD COLUMN uuid VARCHAR(36);");
            $db->query("CREATE UNIQUE INDEX IF NOT EXISTS idx_users_uuid ON {prefix}users(uuid);");
        } catch (\Throwable) {}

        // 2. Posts uuid & parent_id
        try {
            $db->query("ALTER TABLE {prefix}posts ADD COLUMN uuid VARCHAR(36);");
            $db->query("CREATE UNIQUE INDEX IF NOT EXISTS idx_posts_uuid ON {prefix}posts(uuid);");
        } catch (\Throwable) {}

        try {
            $db->query("ALTER TABLE {prefix}posts ADD COLUMN parent_id " . ($isSqlite ? "INTEGER DEFAULT 0" : "BIGINT UNSIGNED DEFAULT 0") . ";");
            $db->query("CREATE INDEX IF NOT EXISTS idx_posts_parent ON {prefix}posts(parent_id);");
        } catch (\Throwable) {}

        // 3. Terms uuid
        try {
            $db->query("ALTER TABLE {prefix}terms ADD COLUMN uuid VARCHAR(36);");
            $db->query("CREATE INDEX IF NOT EXISTS idx_terms_uuid ON {prefix}terms(uuid);");
        } catch (\Throwable) {}

        // 4. Options uuid
        try {
            $db->query("ALTER TABLE {prefix}options ADD COLUMN uuid VARCHAR(36);");
            $db->query("CREATE INDEX IF NOT EXISTS idx_options_uuid ON {prefix}options(uuid);");
        } catch (\Throwable) {}

        // 5. Meta Data uuid
        try {
            $db->query("ALTER TABLE {prefix}meta_data ADD COLUMN uuid VARCHAR(36);");
            $db->query("CREATE INDEX IF NOT EXISTS idx_meta_uuid ON {prefix}meta_data(uuid);");
        } catch (\Throwable) {}

        // Backfill UUIDs for all existing records
        $tables = ['users', 'posts', 'terms', 'options', 'meta_data'];
        foreach ($tables as $t) {
            try {
                $rows = $db->fetchAll("SELECT id FROM {prefix}{$t} WHERE uuid IS NULL OR uuid = ''");
                foreach ($rows as $r) {
                    $db->update("{prefix}{$t}", ['uuid' => Security::uuid()], 'id = :id', ['id' => $r['id']]);
                }
            } catch (\Throwable) {}
        }
    }

    public function down(Database $db): void
    {
    }
};

