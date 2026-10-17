<?php
declare(strict_types=1);

namespace Heartbeat\Installer;

/**
 * System Requirement & Permission Checker for Installer
 */
final class SystemChecker
{
    /**
     * Run all system checks and return detailed report
     * @return array{
     *   allPassed: bool,
     *   phpVersion: array{required: string, current: string, pass: bool, preferred: bool},
     *   extensions: array<string, array{name: string, pass: bool, required: bool, description: string}>,
     *   directories: array<string, array{path: string, pass: bool, required: bool, description: string}>
     * }
     */
    public static function check(): array
    {
        $allPassed = true;

        // 1. PHP Version
        $currentPhp = PHP_VERSION;
        $phpPass = version_compare($currentPhp, '8.2.0', '>=');
        $phpPreferred = version_compare($currentPhp, '8.3.0', '>=');
        if (!$phpPass) {
            $allPassed = false;
        }

        // 2. Extensions
        $requiredExtensions = [
            'pdo' => [
                'name' => 'PDO & Database Treiber',
                'pass' => extension_loaded('pdo') && (extension_loaded('pdo_mysql') || extension_loaded('pdo_sqlite')),
                'required' => true,
                'description' => 'Für Datenbankabfragen (MySQL/MariaDB oder SQLite)'
            ],
            'curl' => [
                'name' => 'cURL',
                'pass' => extension_loaded('curl'),
                'required' => true,
                'description' => 'Für Resend API, GitHub 1-Klick Updates & Webhooks'
            ],
            'image' => [
                'name' => 'GD oder Imagick',
                'pass' => extension_loaded('gd') || extension_loaded('imagick'),
                'required' => true,
                'description' => 'Für automatische WebP/AVIF Bildkonvertierung'
            ],
            'zip' => [
                'name' => 'ZipArchive',
                'pass' => extension_loaded('zip'),
                'required' => true,
                'description' => 'Für 1-Klick Core Updates & Zero-Exec Backups'
            ],
            'mbstring' => [
                'name' => 'MBString',
                'pass' => extension_loaded('mbstring'),
                'required' => true,
                'description' => 'Für UTF-8 Textverarbeitung und Slugs'
            ],
            'json' => [
                'name' => 'JSON',
                'pass' => extension_loaded('json'),
                'required' => true,
                'description' => 'Für JSON-Block-Engine und CPT Metadaten'
            ],
        ];

        foreach ($requiredExtensions as $ext) {
            if ($ext['required'] && !$ext['pass']) {
                $allPassed = false;
            }
        }

        // 3. Writable Directories
        $directories = [
            'config' => [
                'path' => HEARTBEAT_CONFIG,
                'pass' => is_writable(HEARTBEAT_CONFIG) || (!file_exists(HEARTBEAT_CONFIG) && is_writable(HEARTBEAT_ROOT)),
                'required' => true,
                'description' => 'Für env.php Konfiguration und installed.lock'
            ],
            'storage_cache' => [
                'path' => HEARTBEAT_STORAGE . '/cache',
                'pass' => self::isWritableOrCreatable(HEARTBEAT_STORAGE . '/cache'),
                'required' => true,
                'description' => 'Für HTML- & CSS-Cache'
            ],
            'storage_logs' => [
                'path' => HEARTBEAT_STORAGE . '/logs',
                'pass' => self::isWritableOrCreatable(HEARTBEAT_STORAGE . '/logs'),
                'required' => true,
                'description' => 'Für System- & Fehlerprotokolle'
            ],
            'storage_backups' => [
                'path' => HEARTBEAT_STORAGE . '/backups',
                'pass' => self::isWritableOrCreatable(HEARTBEAT_STORAGE . '/backups'),
                'required' => true,
                'description' => 'Für automatische Datenbank- & Core-Backups'
            ],
            'uploads' => [
                'path' => HEARTBEAT_UPLOADS,
                'pass' => self::isWritableOrCreatable(HEARTBEAT_UPLOADS),
                'required' => true,
                'description' => 'Für Medien-Uploads (Bilder, Dokumente)'
            ],
        ];

        foreach ($directories as $dir) {
            if ($dir['required'] && !$dir['pass']) {
                $allPassed = false;
            }
        }

        return [
            'allPassed'   => $allPassed,
            'phpVersion'  => [
                'required'  => '8.2+ (empfohlen 8.3+)',
                'current'   => $currentPhp,
                'pass'      => $phpPass,
                'preferred' => $phpPreferred,
            ],
            'extensions'  => $requiredExtensions,
            'directories' => $directories,
        ];
    }

    private static function isWritableOrCreatable(string $dir): bool
    {
        if (is_dir($dir)) {
            return is_writable($dir);
        }
        
        $parent = dirname($dir);
        return is_writable($parent);
    }
}

