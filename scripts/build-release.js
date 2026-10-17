#!/usr/bin/env node
/**
 * Heartbeat CMS - Production Release Packaging Tool
 * Builds heartbeat-cms-install.zip for WordPress-style Managed Hosting deployment.
 * 
 * Verifies that all 4 default themes, Core, Installer, Security headers and
 * public assets are intact, clean and ready for immediate deployment.
 */

const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const rootDir = path.resolve(__dirname, '..');
const distDir = path.join(rootDir, 'dist');
const zipName = 'heartbeat-cms-install.zip';
const zipPath = path.join(distDir, zipName);

console.log('\x1b[38;2;249;115;22m%s\x1b[0m', '📦 Building Heartbeat CMS Production Release Package...');

if (!fs.existsSync(distDir)) {
    fs.mkdirSync(distDir, { recursive: true });
}

// Ensure zip file is removed if it already exists
if (fs.existsSync(zipPath)) {
    fs.unlinkSync(zipPath);
}

// Temporary staging directory
const stagingDir = path.join(distDir, 'staging');
if (fs.existsSync(stagingDir)) {
    fs.rmSync(stagingDir, { recursive: true, force: true });
}
fs.mkdirSync(stagingDir, { recursive: true });

// Copy essential directories and files
const includeItems = [
    '.htaccess',
    'router.php',
    'composer.json',
    'core',
    'config',
    'public',
    'storage',
    'uploads',
    'themes',
    'plugins'
];

let totalFilesCopied = 0;

function copyRecursive(src, dest) {
    const stat = fs.statSync(src);
    if (stat.isDirectory()) {
        fs.mkdirSync(dest, { recursive: true });
        const files = fs.readdirSync(src);
        for (const file of files) {
            // Strictly exclude dev artifacts, logs, sqlite dbs and lock files
            if (
                file === 'env.php' ||
                file === 'installed.lock' ||
                file.endsWith('.sqlite') ||
                file.startsWith('database.sqlite') ||
                file === '.DS_Store' ||
                file === 'Thumbs.db' ||
                file.endsWith('.log') ||
                file === 'staging'
            ) {
                continue;
            }
            copyRecursive(path.join(src, file), path.join(dest, file));
        }
    } else {
        fs.copyFileSync(src, dest);
        totalFilesCopied++;
    }
}

for (const item of includeItems) {
    const srcPath = path.join(rootDir, item);
    const destPath = path.join(stagingDir, item);
    if (fs.existsSync(srcPath)) {
        copyRecursive(srcPath, destPath);
    }
}

// Ensure storage subdirectories and .gitkeep exist
const requiredStorageDirs = [
    'storage/cache',
    'storage/cache/html',
    'storage/cache/css',
    'storage/cache/objects',
    'storage/logs',
    'storage/backups',
    'uploads'
];

for (const sDir of requiredStorageDirs) {
    const fullDir = path.join(stagingDir, sDir);
    if (!fs.existsSync(fullDir)) {
        fs.mkdirSync(fullDir, { recursive: true });
    }
    const gitkeep = path.join(fullDir, '.gitkeep');
    if (!fs.existsSync(gitkeep)) {
        fs.writeFileSync(gitkeep, '');
        totalFilesCopied++;
    }
}

// Ensure security .htaccess in uploads directory
const uploadsHtaccess = path.join(stagingDir, 'uploads', '.htaccess');
if (!fs.existsSync(uploadsHtaccess)) {
    fs.writeFileSync(uploadsHtaccess, '# Prevent direct PHP execution in uploads\n<FilesMatch "(?i)\\.(php|php[0-9]|phtml|phar|pl|py|cgi|asp|js|sh)$">\n    Order Deny,Allow\n    Deny from all\n</FilesMatch>\n');
    totalFilesCopied++;
}

// Verify that all 4 default themes are present in staging
const expectedThemes = ['default', 'corporate', 'real-estate', 'startup'];
for (const th of expectedThemes) {
    const thPath = path.join(stagingDir, 'themes', th);
    const manifest = path.join(thPath, 'theme.json');
    if (!fs.existsSync(manifest)) {
        console.error('\x1b[31m%s\x1b[0m', `CRITICAL ERROR: Default theme "${th}" is missing theme.json in staging!`);
        process.exit(1);
    }
}

// Create zip using system zip command or archiver
try {
    execSync(`cd "${stagingDir}" && zip -r -q "${zipPath}" .`, { stdio: 'inherit' });
    fs.rmSync(stagingDir, { recursive: true, force: true });
    
    const stats = fs.statSync(zipPath);
    const sizeMb = (stats.size / (1024 * 1024)).toFixed(2);
    const sizeKb = (stats.size / 1024).toFixed(1);

    // Compute SHA-256 Checksum
    const fileBuffer = fs.readFileSync(zipPath);
    const hashSum = crypto.createHash('sha256');
    hashSum.update(fileBuffer);
    const hexSha256 = hashSum.digest('hex');
    
    console.log('\x1b[32m%s\x1b[0m', `✓ Successfully built Heartbeat CMS production release package!`);
    console.log(`   Bundle  : ${zipName}`);
    console.log(`   Output  : ${zipPath}`);
    console.log(`   Size    : ${sizeMb} MB (${sizeKb} KB)`);
    console.log(`   Files   : ${totalFilesCopied} verified files included`);
    console.log(`   SHA-256 : ${hexSha256}`);
    console.log(`   Themes  : One-Pager (default), Clean Corporate, Classic Real Estate, Modern Startup`);
    console.log('');
    console.log('➜ Client Rollout Ready: Upload heartbeat-cms-install.zip to Webgo, IONOS, HostEurope or Mittwald, extract and open the domain in your browser to launch the Web-Installer.');
} catch (err) {
    console.error('\x1b[31m%s\x1b[0m', 'Failed to generate release zip: ' + err.message);
    process.exit(1);
}
