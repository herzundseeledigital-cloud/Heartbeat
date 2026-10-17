<?php
declare(strict_types=1);

namespace Heartbeat\Database;

use RuntimeException;
use Throwable;

/**
 * Auto-Migration Runner for Heartbeat CMS
 */
final class MigrationRunner
{
    private Database $db;

    public function __construct(Database $db)
    {
        $this->db = $db;
        $this->ensureMigrationsTable();
    }

    /**
     * Create migrations tracking table if not present
     */
    private function ensureMigrationsTable(): void
    {
        $isSqlite = $this->db->getDriver() === 'sqlite';

        if ($isSqlite) {
            $this->db->query("CREATE TABLE IF NOT EXISTS {prefix}migrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration VARCHAR(191) NOT NULL UNIQUE,
                batch INTEGER NOT NULL,
                applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );");
        } else {
            $this->db->query("CREATE TABLE IF NOT EXISTS {prefix}migrations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(191) NOT NULL UNIQUE,
                batch INT UNSIGNED NOT NULL,
                applied_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
        }
    }

    /**
     * Run all pending migrations in schema directory
     * @return array<string> Names of executed migrations
     */
    public function runPending(string $schemaDir = HEARTBEAT_CORE . '/Database/Schema'): array
    {
        if (!is_dir($schemaDir)) {
            return [];
        }

        $files = glob($schemaDir . '/*.php');
        if ($files === false || empty($files)) {
            return [];
        }

        sort($files);

        // Fetch already executed migrations
        $executedRows = $this->db->fetchAll("SELECT migration FROM {prefix}migrations");
        $executed = array_column($executedRows, 'migration');

        // Determine next batch number
        $currentBatch = (int)$this->db->fetchColumn("SELECT MAX(batch) FROM {prefix}migrations");
        $nextBatch = $currentBatch + 1;

        $applied = [];

        foreach ($files as $file) {
            $migrationName = basename($file, '.php');

            if (in_array($migrationName, $executed, true)) {
                continue;
            }

            $migration = require $file;
            if (!is_object($migration) || !method_exists($migration, 'up')) {
                throw new RuntimeException("Migration file {$file} must return an object with an up() method.");
            }

            try {
                $this->db->beginTransaction();
                $migration->up($this->db);

                $this->db->insert('{prefix}migrations', [
                    'migration'  => $migrationName,
                    'batch'      => $nextBatch,
                    'applied_at' => date('Y-m-d H:i:s'),
                ]);

                $this->db->commit();
                $applied[] = $migrationName;
            } catch (Throwable $e) {
                if ($this->db->getPdo()->inTransaction()) {
                    $this->db->rollBack();
                }
                throw new RuntimeException("Error running migration [{$migrationName}]: " . $e->getMessage(), 0, $e);
            }
        }

        return $applied;
    }
}

