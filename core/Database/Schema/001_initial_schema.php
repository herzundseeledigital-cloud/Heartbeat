<?php
declare(strict_types=1);

use Heartbeat\Database\Database;

return new class {
    public function up(Database $db): void
    {
        $isSqlite = $db->getDriver() === 'sqlite';

        // 1. Options Table (key-value store for site settings, active theme, agency configs)
        if ($isSqlite) {
            $db->query("CREATE TABLE IF NOT EXISTS {prefix}options (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                option_name VARCHAR(191) NOT NULL UNIQUE,
                option_value TEXT,
                autoload INTEGER DEFAULT 1
            );");
            $db->query("CREATE INDEX IF NOT EXISTS idx_options_autoload ON {prefix}options(autoload);");
        } else {
            $db->query("CREATE TABLE IF NOT EXISTS {prefix}options (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                option_name VARCHAR(191) NOT NULL UNIQUE,
                option_value LONGTEXT,
                autoload TINYINT(1) DEFAULT 1,
                INDEX idx_options_autoload (autoload)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        }

        // 2. Users Table
        if ($isSqlite) {
            $db->query("CREATE TABLE IF NOT EXISTS {prefix}users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username VARCHAR(60) NOT NULL UNIQUE,
                email VARCHAR(191) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                role VARCHAR(30) NOT NULL DEFAULT 'editor',
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );");
            $db->query("CREATE INDEX IF NOT EXISTS idx_users_role ON {prefix}users(role);");
        } else {
            $db->query("CREATE TABLE IF NOT EXISTS {prefix}users (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(60) NOT NULL UNIQUE,
                email VARCHAR(191) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                role VARCHAR(30) NOT NULL DEFAULT 'editor',
                status VARCHAR(20) NOT NULL DEFAULT 'active',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_users_role (role)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        }

        // 3. Posts & Content Types Table (Pages, Posts, CPTs)
        if ($isSqlite) {
            $db->query("CREATE TABLE IF NOT EXISTS {prefix}posts (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(191) NOT NULL,
                content_blocks TEXT,
                content_html TEXT,
                post_type VARCHAR(50) NOT NULL DEFAULT 'page',
                status VARCHAR(20) NOT NULL DEFAULT 'publish',
                author_id INTEGER NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                published_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );");
            $db->query("CREATE INDEX IF NOT EXISTS idx_posts_slug ON {prefix}posts(slug);");
            $db->query("CREATE INDEX IF NOT EXISTS idx_posts_type_status ON {prefix}posts(post_type, status);");
        } else {
            $db->query("CREATE TABLE IF NOT EXISTS {prefix}posts (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                slug VARCHAR(191) NOT NULL,
                content_blocks LONGTEXT,
                content_html LONGTEXT,
                post_type VARCHAR(50) NOT NULL DEFAULT 'page',
                status VARCHAR(20) NOT NULL DEFAULT 'publish',
                author_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                published_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_posts_slug (slug),
                INDEX idx_posts_type_status (post_type, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        }

        // 4. Meta Data Table (Indexed JSON & Key-Value custom fields for posts/users/themes)
        if ($isSqlite) {
            $db->query("CREATE TABLE IF NOT EXISTS {prefix}meta_data (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                object_type VARCHAR(30) NOT NULL DEFAULT 'post',
                object_id INTEGER NOT NULL,
                meta_key VARCHAR(191) NOT NULL,
                meta_value TEXT
            );");
            $db->query("CREATE INDEX IF NOT EXISTS idx_meta_lookup ON {prefix}meta_data(object_type, object_id, meta_key);");
        } else {
            $db->query("CREATE TABLE IF NOT EXISTS {prefix}meta_data (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                object_type VARCHAR(30) NOT NULL DEFAULT 'post',
                object_id BIGINT UNSIGNED NOT NULL,
                meta_key VARCHAR(191) NOT NULL,
                meta_value LONGTEXT,
                INDEX idx_meta_lookup (object_type, object_id, meta_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        }

        // 5. Taxonomies / Terms & Relationships
        if ($isSqlite) {
            $db->query("CREATE TABLE IF NOT EXISTS {prefix}terms (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(191) NOT NULL,
                slug VARCHAR(191) NOT NULL,
                taxonomy VARCHAR(50) NOT NULL DEFAULT 'category',
                parent_id INTEGER DEFAULT 0
            );");
            $db->query("CREATE INDEX IF NOT EXISTS idx_terms_taxonomy ON {prefix}terms(taxonomy, slug);");

            $db->query("CREATE TABLE IF NOT EXISTS {prefix}term_relationships (
                object_id INTEGER NOT NULL,
                term_id INTEGER NOT NULL,
                PRIMARY KEY (object_id, term_id)
            );");
        } else {
            $db->query("CREATE TABLE IF NOT EXISTS {prefix}terms (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(191) NOT NULL,
                slug VARCHAR(191) NOT NULL,
                taxonomy VARCHAR(50) NOT NULL DEFAULT 'category',
                parent_id BIGINT UNSIGNED DEFAULT 0,
                INDEX idx_terms_taxonomy (taxonomy, slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

            $db->query("CREATE TABLE IF NOT EXISTS {prefix}term_relationships (
                object_id BIGINT UNSIGNED NOT NULL,
                term_id BIGINT UNSIGNED NOT NULL,
                PRIMARY KEY (object_id, term_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        }
    }

    public function down(Database $db): void
    {
        $db->query("DROP TABLE IF EXISTS {prefix}term_relationships;");
        $db->query("DROP TABLE IF EXISTS {prefix}terms;");
        $db->query("DROP TABLE IF EXISTS {prefix}meta_data;");
        $db->query("DROP TABLE IF EXISTS {prefix}posts;");
        $db->query("DROP TABLE IF EXISTS {prefix}users;");
        $db->query("DROP TABLE IF EXISTS {prefix}options;");
    }
};

