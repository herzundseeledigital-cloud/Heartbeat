<?php
declare(strict_types=1);

namespace Heartbeat\Database;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * PDO Database wrapper supporting MySQL/MariaDB and SQLite
 */
final class Database
{
    private static ?self $instance = null;
    private PDO $pdo;
    private string $driver;
    private string $prefix;

    public function __construct(array $config)
    {
        $this->driver = strtolower($config['driver'] ?? 'mysql');
        $this->prefix = $config['prefix'] ?? 'hb_';

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            if ($this->driver === 'sqlite') {
                $path = $config['database'] ?? (HEARTBEAT_STORAGE . '/database.sqlite');
                $dir = dirname($path);
                if (!is_dir($dir)) {
                    @mkdir($dir, 0775, true);
                }
                $dsn = 'sqlite:' . $path;
                $this->pdo = new PDO($dsn, null, null, $options);
                // Enable foreign keys and WAL mode in SQLite for high performance
                $this->pdo->exec('PRAGMA foreign_keys = ON;');
                $this->pdo->exec('PRAGMA journal_mode = WAL;');
            } else {
                $host = $config['host'] ?? '127.0.0.1';
                $port = $config['port'] ?? 3306;
                $db = $config['database'] ?? '';
                $user = $config['username'] ?? 'root';
                $pass = $config['password'] ?? '';
                $charset = $config['charset'] ?? 'utf8mb4';

                $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
                $this->pdo = new PDO($dsn, $user, $pass, $options);
            }
        } catch (PDOException $e) {
            throw new RuntimeException("Database connection failed: " . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    public static function getInstance(?array $config = null): self
    {
        if (self::$instance === null) {
            if ($config === null) {
                $config = \Heartbeat\Bootstrap::config('database', []);
            }
            if (empty($config)) {
                throw new RuntimeException("No database configuration available.");
            }
            self::$instance = new self($config);
        }

        return self::$instance;
    }

    public static function setInstance(self $instance): void
    {
        self::$instance = $instance;
    }

    /**
     * Test connection without binding to singleton instance
     * @return array{success: bool, message: string}
     */
    public static function testConnection(array $config): array
    {
        try {
            $driver = strtolower($config['driver'] ?? 'mysql');
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 3,
            ];

            if ($driver === 'sqlite') {
                $path = $config['database'] ?? (HEARTBEAT_STORAGE . '/database.sqlite');
                $dir = dirname($path);
                if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
                    return ['success' => false, 'message' => "SQLite Verzeichnis ist nicht beschreibbar: {$dir}"];
                }
                new PDO('sqlite:' . $path, null, null, $options);
                return ['success' => true, 'message' => "SQLite Verbindung erfolgreich hergestellt!"];
            }

            $host = $config['host'] ?? '127.0.0.1';
            $port = $config['port'] ?? 3306;
            $db = $config['database'] ?? '';
            $user = $config['username'] ?? 'root';
            $pass = $config['password'] ?? '';
            $charset = $config['charset'] ?? 'utf8mb4';

            $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
            new PDO($dsn, $user, $pass, $options);

            return ['success' => true, 'message' => "MySQL/MariaDB Verbindung erfolgreich hergestellt!"];
        } catch (PDOException $e) {
            return ['success' => false, 'message' => "Verbindung fehlgeschlagen: " . $e->getMessage()];
        }
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    public function getDriver(): string
    {
        return $this->driver;
    }

    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * Replaces {prefix} with configured table prefix
     */
    public function prefix(string $sql): string
    {
        return str_replace('{prefix}', $this->prefix, $sql);
    }

    /**
     * Prepare and execute a SQL statement
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $preparedSql = $this->prefix($sql);
        $stmt = $this->pdo->prepare($preparedSql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Fetch a single row
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = $this->query($sql, $params);
        $row = $stmt->fetch();
        return $row !== false ? $row : null;
    }

    /**
     * Fetch all matching rows
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Fetch a single column value
     */
    public function fetchColumn(string $sql, array $params = [], int $columnNumber = 0): mixed
    {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchColumn($columnNumber);
    }

    /**
     * Insert a record and return last inserted ID
     */
    public function insert(string $table, array $data): int|string
    {
        if (!isset($data['uuid'])) {
            $pureTable = str_replace(['{prefix}', $this->prefix], '', $table);
            if (in_array($pureTable, ['users', 'posts', 'terms', 'options', 'meta_data'], true)) {
                $data['uuid'] = class_exists(\Heartbeat\Security\Security::class)
                    ? \Heartbeat\Security\Security::uuid()
                    : sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
            }
        }

        $tableName = $this->prefix($table);
        $columns = array_keys($data);
        $placeholders = array_map(fn($col) => ':' . $col, $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $tableName,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);

        return $this->pdo->lastInsertId();
    }

    /**
     * Update records matching where clause
     */
    public function update(string $table, array $data, string $where, array $whereParams = []): int
    {
        $tableName = $this->prefix($table);
        $setClauses = [];
        $params = [];

        foreach ($data as $column => $value) {
            $paramName = 'set_' . $column;
            $setClauses[] = "{$column} = :{$paramName}";
            $params[$paramName] = $value;
        }

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s',
            $tableName,
            implode(', ', $setClauses),
            $where
        );

        $allParams = array_merge($params, $whereParams);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($allParams);

        return $stmt->rowCount();
    }

    /**
     * Delete records
     */
    public function delete(string $table, string $where, array $whereParams = []): int
    {
        $tableName = $this->prefix($table);
        $sql = sprintf('DELETE FROM %s WHERE %s', $tableName, $where);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($whereParams);
        return $stmt->rowCount();
    }

    /**
     * Transaction helpers
     */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }

    public function lastInsertId(?string $name = null): string|false
    {
        return $this->pdo->lastInsertId($name);
    }
}

