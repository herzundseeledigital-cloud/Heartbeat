<?php
declare(strict_types=1);

use Heartbeat\Database\Database;

return new class {
    public function up(Database $db): void
    {
        $isSqlite = $db->getDriver() === 'sqlite';

        // 1. Cookieless Analytics Daily Table
        if ($isSqlite) {
            $db->query("CREATE TABLE IF NOT EXISTS {prefix}analytics_daily (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                date TEXT NOT NULL,
                path VARCHAR(255) NOT NULL,
                visitor_hash VARCHAR(64) NOT NULL,
                views INTEGER NOT NULL DEFAULT 1,
                device VARCHAR(30) NOT NULL DEFAULT 'desktop',
                browser VARCHAR(50) NOT NULL DEFAULT 'other',
                referrer VARCHAR(255) DEFAULT '',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );");
            $db->query("CREATE INDEX IF NOT EXISTS idx_analytics_date ON {prefix}analytics_daily(date);");
            $db->query("CREATE INDEX IF NOT EXISTS idx_analytics_path ON {prefix}analytics_daily(path);");
            $db->query("CREATE INDEX IF NOT EXISTS idx_analytics_hash ON {prefix}analytics_daily(visitor_hash);");
        } else {
            $db->query("CREATE TABLE IF NOT EXISTS {prefix}analytics_daily (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                date DATE NOT NULL,
                path VARCHAR(255) NOT NULL,
                visitor_hash VARCHAR(64) NOT NULL,
                views INT UNSIGNED NOT NULL DEFAULT 1,
                device VARCHAR(30) NOT NULL DEFAULT 'desktop',
                browser VARCHAR(50) NOT NULL DEFAULT 'other',
                referrer VARCHAR(255) DEFAULT '',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_analytics_date (date),
                INDEX idx_analytics_path (path(191)),
                INDEX idx_analytics_hash (visitor_hash)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        }

        // 2. Orders & Member Transactions Table (Stripe Integration)
        if ($isSqlite) {
            $db->query("CREATE TABLE IF NOT EXISTS {prefix}orders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NULL,
                stripe_session_id VARCHAR(191) NOT NULL UNIQUE,
                stripe_customer_id VARCHAR(191) NULL,
                customer_email VARCHAR(191) NULL,
                amount_cents INTEGER NOT NULL DEFAULT 0,
                currency VARCHAR(10) NOT NULL DEFAULT 'eur',
                plan_or_item VARCHAR(100) NOT NULL DEFAULT 'member_access',
                status VARCHAR(30) NOT NULL DEFAULT 'completed',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );");
            $db->query("CREATE INDEX IF NOT EXISTS idx_orders_user ON {prefix}orders(user_id);");
            $db->query("CREATE INDEX IF NOT EXISTS idx_orders_session ON {prefix}orders(stripe_session_id);");
        } else {
            $db->query("CREATE TABLE IF NOT EXISTS {prefix}orders (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NULL,
                stripe_session_id VARCHAR(191) NOT NULL UNIQUE,
                stripe_customer_id VARCHAR(191) NULL,
                customer_email VARCHAR(191) NULL,
                amount_cents INT UNSIGNED NOT NULL DEFAULT 0,
                currency VARCHAR(10) NOT NULL DEFAULT 'eur',
                plan_or_item VARCHAR(100) NOT NULL DEFAULT 'member_access',
                status VARCHAR(30) NOT NULL DEFAULT 'completed',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_orders_user (user_id),
                INDEX idx_orders_session (stripe_session_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        }
    }

    public function down(Database $db): void
    {
        $db->query("DROP TABLE IF EXISTS {prefix}orders;");
        $db->query("DROP TABLE IF EXISTS {prefix}analytics_daily;");
    }
};

