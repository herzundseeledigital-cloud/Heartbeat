<?php
declare(strict_types=1);

namespace Heartbeat\Backup;

use Heartbeat\Database\Database;
use ZipArchive;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;
use FilesystemIterator;

/**
 * Zero-Exec Backup Engine
 * Generates full database dumps (MySQL & SQLite) and file archives
 * purely using native PHP PDO streaming and ZipArchive (no exec/mysqldump required).
 */
final class BackupService
{
    private static ?BackupService $instance = null;
    private Database $db;
    private string $backupDir;

    private function __construct()
    {
        $this->db = Database::getInstance();
        $this->backupDir = HEARTBEAT_STORAGE . '/backups';

        if (!is_dir($this->backupDir)) {
            @mkdir($this->backupDir, 0755, true);
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Generate pure PHP SQL dump for active database
     */
    public function dumpDatabase(): string
    {
        $pdo = $this->db->getPdo();
        $driver = $this->db->getDriver();
        $timestamp = date('Y-m-d H:i:s');

        $out = "-- ========================================================\n";
        $out .= "-- Heartbeat CMS Native Database Dump (Zero-Exec)\n";
        $out .= "-- Generation Time: {$timestamp}\n";
        $out .= "-- Driver: " . strtoupper($driver) . "\n";
        $out .= "-- PHP Version: " . PHP_VERSION . "\n";
        $out .= "-- ========================================================\n\n";

        if ($driver === 'sqlite') {
            $out .= "PRAGMA foreign_keys = OFF;\n";
            $out .= "BEGIN TRANSACTION;\n\n";

            // Fetch table list
            $stmt = $pdo->query("SELECT name, sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
            $tables = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($tables as $t) {
                $tableName = $t['name'];
                $createSql = $t['sql'];

                $out .= "-- --------------------------------------------------------\n";
                $out .= "-- Table structure for {$tableName}\n";
                $out .= "-- --------------------------------------------------------\n";
                $out .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
                $out .= "{$createSql};\n\n";

                // Table data
                $dataStmt = $pdo->query("SELECT * FROM `{$tableName}`");
                $rows = $dataStmt->fetchAll(\PDO::FETCH_ASSOC);
                if (!empty($rows)) {
                    $out .= "-- Dumping data for {$tableName}\n";
                    foreach ($rows as $row) {
                        $cols = array_map(fn($c) => "`{$c}`", array_keys($row));
                        $vals = array_map(function ($val) use ($pdo) {
                            if ($val === null) return 'NULL';
                            return $pdo->quote((string)$val);
                        }, array_values($row));

                        $out .= "INSERT INTO `{$tableName}` (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ");\n";
                    }
                    $out .= "\n";
                }
            }

            $out .= "COMMIT;\n";
            $out .= "PRAGMA foreign_keys = ON;\n";
        } else {
            // MySQL / MariaDB
            $out .= "SET FOREIGN_KEY_CHECKS=0;\n";
            $out .= "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n";
            $out .= "SET NAMES utf8mb4;\n";
            $out .= "START TRANSACTION;\n\n";

            $stmt = $pdo->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
            $tables = $stmt->fetchAll(\PDO::FETCH_NUM);

            foreach ($tables as $tRow) {
                $tableName = $tRow[0];

                $out .= "-- --------------------------------------------------------\n";
                $out .= "-- Table structure for {$tableName}\n";
                $out .= "-- --------------------------------------------------------\n";
                $out .= "DROP TABLE IF EXISTS `{$tableName}`;\n";

                $createStmt = $pdo->query("SHOW CREATE TABLE `{$tableName}`");
                $createRow = $createStmt->fetch(\PDO::FETCH_NUM);
                $out .= $createRow[1] . ";\n\n";

                // Table data streaming
                $dataStmt = $pdo->query("SELECT * FROM `{$tableName}`");
                $dataStmt->setFetchMode(\PDO::FETCH_ASSOC);

                $firstRow = true;
                while ($row = $dataStmt->fetch()) {
                    if ($firstRow) {
                        $out .= "-- Dumping data for {$tableName}\n";
                        $firstRow = false;
                    }
                    $cols = array_map(fn($c) => "`{$c}`", array_keys($row));
                    $vals = array_map(function ($val) use ($pdo) {
                        if ($val === null) return 'NULL';
                        return $pdo->quote((string)$val);
                    }, array_values($row));

                    $out .= "INSERT INTO `{$tableName}` (" . implode(', ', $cols) . ") VALUES (" . implode(', ', $vals) . ");\n";
                }
                $out .= "\n";
            }

            $out .= "COMMIT;\n";
            $out .= "SET FOREIGN_KEY_CHECKS=1;\n";
        }

        return $out;
    }

    /**
     * Create a backup archive (Database + Files)
     *
     * @param bool $includeDb Include SQL database dump
     * @param bool $includeFiles Include uploads, themes, config
     * @return array{success: bool, filename: string, filepath: string, size: int, size_formatted: string, created_at: string, message: string}
     */
    public function createBackup(bool $includeDb = true, bool $includeFiles = true): array
    {
        if (!class_exists('ZipArchive')) {
            return [
                'success'        => false,
                'filename'       => '',
                'filepath'       => '',
                'size'           => 0,
                'size_formatted' => '0 B',
                'created_at'     => date('Y-m-d H:i:s'),
                'message'        => 'PHP ZipArchive Erweiterung ist auf diesem Server nicht installiert.',
            ];
        }

        $type = 'full';
        if ($includeDb && !$includeFiles) $type = 'db';
        if (!$includeDb && $includeFiles) $type = 'files';

        $timestamp = date('Y-m-d_His');
        $filename = "heartbeat_backup_{$timestamp}_{$type}.zip";
        $filepath = $this->backupDir . '/' . $filename;

        $zip = new ZipArchive();
        $res = $zip->open($filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($res !== true) {
            return [
                'success'        => false,
                'filename'       => '',
                'filepath'       => '',
                'size'           => 0,
                'size_formatted' => '0 B',
                'created_at'     => date('Y-m-d H:i:s'),
                'message'        => "Konnte ZIP-Datei unter {$filepath} nicht anlegen (Fehlercode: {$res}).",
            ];
        }

        // 1. Add SQL Dump
        if ($includeDb) {
            $sql = $this->dumpDatabase();
            $zip->addFromString('database.sql', $sql);
        }

        // 2. Add System Files
        if ($includeFiles) {
            $includeDirs = [
                'config'  => HEARTBEAT_CONFIG,
                'uploads' => HEARTBEAT_UPLOADS,
                'themes'  => HEARTBEAT_THEMES,
            ];

            foreach ($includeDirs as $prefix => $absDir) {
                if (!is_dir($absDir)) continue;
                $this->addDirectoryToZip($zip, $absDir, $prefix);
            }
        }

        $manifest = [
            'cms'             => 'Heartbeat CMS',
            'version'         => HEARTBEAT_VERSION,
            'backup_type'     => $type,
            'created_at'      => date('Y-m-d H:i:s'),
            'php_version'     => PHP_VERSION,
            'db_driver'       => $this->db->getDriver(),
            'includes_db'     => $includeDb,
            'includes_files'  => $includeFiles,
        ];
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));

        $zip->close();

        $size = (int)@filesize($filepath);

        // Retention policy: automatically prune oldest backups
        $this->cleanOldBackups(5);

        return [
            'success'        => true,
            'filename'       => $filename,
            'filepath'       => $filepath,
            'size'           => $size,
            'size_formatted' => $this->formatBytes($size),
            'created_at'     => date('Y-m-d H:i:s'),
            'message'        => "Datensicherung {$filename} wurde erfolgreich erstellt ({$this->formatBytes($size)}).",
        ];
    }

    /**
     * Recursively add a directory to ZipArchive
     */
    private function addDirectoryToZip(ZipArchive $zip, string $sourceDir, string $zipPrefix): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            $subPath = substr($file->getPathname(), strlen($sourceDir) + 1);
            $zipPath = $zipPrefix . '/' . str_replace('\\', '/', $subPath);

            if ($file->isDir()) {
                $zip->addEmptyDir($zipPath);
            } elseif ($file->isFile()) {
                $zip->addFile($file->getPathname(), $zipPath);
            }
        }
    }

    /**
     * List all existing backup archives
     * @return array<int, array{filename: string, filepath: string, size: int, size_formatted: string, created_at: string, download_url: string}>
     */
    public function listBackups(): array
    {
        $files = glob($this->backupDir . '/*.zip');
        if ($files === false || empty($files)) {
            return [];
        }

        $list = [];
        foreach ($files as $file) {
            $filename = basename($file);
            $size = (int)filesize($file);
            $mtime = filemtime($file);

            $list[] = [
                'filename'       => $filename,
                'filepath'       => $file,
                'size'           => $size,
                'size_formatted' => $this->formatBytes($size),
                'created_at'     => date('Y-m-d H:i:s', $mtime),
                'timestamp'      => $mtime,
                'download_url'   => "/admin/backups/download/{$filename}",
            ];
        }

        // Sort descending by timestamp
        usort($list, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);

        return $list;
    }

    /**
     * Delete a specific backup file
     */
    public function deleteBackup(string $filename): bool
    {
        $safeName = basename($filename);
        $file = $this->backupDir . '/' . $safeName;

        if (is_file($file) && str_ends_with($safeName, '.zip')) {
            return @unlink($file);
        }

        return false;
    }

    /**
     * Retention policy: Keep the newest $keep backups, remove older ones
     */
    public function cleanOldBackups(int $keep = 5): int
    {
        $backups = $this->listBackups();
        if (count($backups) <= $keep) {
            return 0;
        }

        $toRemove = array_slice($backups, $keep);
        $removed = 0;
        foreach ($toRemove as $b) {
            if ($this->deleteBackup($b['filename'])) {
                $removed++;
            }
        }
        return $removed;
    }

    /**
     * Retrieve or generate secret token for automated cron backups
     */
    public function getCronToken(): string
    {
        $token = (string)$this->db->fetchColumn("SELECT option_value FROM {prefix}options WHERE option_name = 'backup_cron_token'");
        if ($token === '') {
            $token = bin2hex(random_bytes(24));
            $this->db->insert('{prefix}options', [
                'option_name'  => 'backup_cron_token',
                'option_value' => $token,
                'autoload'     => 1,
            ]);
        }
        return $token;
    }

    /**
     * Verify cron token
     */
    public function verifyCronToken(string $token): bool
    {
        if ($token === '') return false;
        return hash_equals($this->getCronToken(), $token);
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' B';
    }
}

