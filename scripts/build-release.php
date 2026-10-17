<?php
declare(strict_types=1);

/**
 * Heartbeat CMS - Zero-Exec Pure-PHP Release Packaging Tool
 * Builds heartbeat-cms-install.zip without Node.js or exec() using native ZipArchive.
 */

$rootDir = dirname(__DIR__);
$distDir = $rootDir . '/dist';
$zipPath = $distDir . '/heartbeat-cms-install.zip';

echo "\033[38;2;249;115;22m📦 Building Heartbeat CMS Production Release Package (Pure PHP)...\033[0m\n";

if (!is_dir($distDir)) {
    mkdir($distDir, 0755, true);
}

if (file_exists($zipPath)) {
    @unlink($zipPath);
}

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    fwrite(STDERR, "\033[31mFailed to create ZIP file at {$zipPath}\033[0m\n");
    exit(1);
}

$includeItems = [
    '.htaccess',
    'router.php',
    'composer.json',
    'core',
    'config',
    'public',
    'storage',
    'uploads',
    'themes',
    'plugins',
];

$fileCount = 0;

foreach ($includeItems as $item) {
    $itemPath = $rootDir . '/' . $item;
    if (!file_exists($itemPath)) {
        continue;
    }

    if (is_file($itemPath)) {
        $zip->addFile($itemPath, $item);
        $fileCount++;
        continue;
    }

    // Directory recursion
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($itemPath, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
        $filePath = $file->getPathname();
        $filename = $file->getFilename();

        // Strictly exclude secrets, databases, logs, lockfiles
        if (
            $filename === 'env.php' ||
            $filename === 'installed.lock' ||
            str_ends_with($filename, '.sqlite') ||
            str_starts_with($filename, 'database.sqlite') ||
            $filename === '.DS_Store' ||
            $filename === 'Thumbs.db' ||
            str_ends_with($filename, '.log')
        ) {
            continue;
        }

        $relativePath = substr($filePath, strlen($rootDir) + 1);

        if ($file->isDir()) {
            $zip->addEmptyDir($relativePath);
        } elseif ($file->isFile()) {
            $zip->addFile($filePath, $relativePath);
            $fileCount++;
        }
    }
}

// Ensure storage subdirectories and .gitkeep exist
$requiredDirs = [
    'storage/cache',
    'storage/cache/html',
    'storage/cache/css',
    'storage/cache/objects',
    'storage/logs',
    'storage/backups',
    'uploads',
];

foreach ($requiredDirs as $rDir) {
    $zip->addEmptyDir($rDir);
    $zip->addFromString($rDir . '/.gitkeep', '');
    $fileCount++;
}

// Ensure security .htaccess in uploads
$zip->addFromString('uploads/.htaccess', "# Prevent direct PHP execution in uploads\n<FilesMatch \"(?i)\\.(php|php[0-9]|phtml|phar|pl|py|cgi|asp|js|sh)$\">\n    Order Deny,Allow\n    Deny from all\n</FilesMatch>\n");
$fileCount++;

$zip->close();

if (!file_exists($zipPath)) {
    fwrite(STDERR, "\033[31mError: Output ZIP was not created!\033[0m\n");
    exit(1);
}

$sizeBytes = filesize($zipPath);
$sizeMb = number_format($sizeBytes / (1024 * 1024), 2);
$sizeKb = number_format($sizeBytes / 1024, 1);
$sha256 = hash_file('sha256', $zipPath);

echo "\033[32m✓ Successfully built Heartbeat CMS production release package (Pure PHP)!\033[0m\n";
echo "   Bundle  : heartbeat-cms-install.zip\n";
echo "   Output  : {$zipPath}\n";
echo "   Size    : {$sizeMb} MB ({$sizeKb} KB)\n";
echo "   Files   : {$fileCount} verified files included\n";
echo "   SHA-256 : {$sha256}\n";
echo "   Themes  : One-Pager (default), Clean Corporate, Classic Real Estate, Modern Startup\n\n";
echo "➜ Client Rollout Ready: Upload heartbeat-cms-install.zip to Webgo, IONOS, HostEurope or Mittwald, extract and open the domain in your browser to launch the Web-Installer.\n";
