#!/usr/bin/env node
/**
 * Heartbeat CMS - Local Development Runner
 * Starts PHP built-in server with router.php
 */

const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');

// Determine PHP binary
function findPhpBinary() {
    const candidates = [
        path.join(__dirname, '..', 'bin', 'php'),
        'php',
        '/Applications/XAMPP/xamppfiles/bin/php',
        '/opt/homebrew/bin/php',
        '/usr/local/bin/php',
        '/usr/bin/php'
    ];

    for (const bin of candidates) {
        try {
            if (bin.startsWith('/') || bin.startsWith('.')) {
                if (fs.existsSync(bin)) return bin;
            } else {
                return bin;
            }
        } catch (e) {}
    }
    return 'php';
}

const phpBin = findPhpBinary();
const rootDir = path.resolve(__dirname, '..');
const port = process.env.PORT || 8000;
const host = process.env.HOST || 'localhost';

console.log('\x1b[38;2;249;115;22m%s\x1b[0m', '⚡ HEARTBEAT CMS - Local Core Development Server');
console.log('\x1b[90m%s\x1b[0m', `   PHP Binary : ${phpBin}`);
console.log('\x1b[90m%s\x1b[0m', `   DocumentRoot: ${path.join(rootDir, 'public')}`);
console.log('\x1b[90m%s\x1b[0m', `   Router Script: router.php`);
console.log('');
console.log('\x1b[32m%s\x1b[0m', `   ➜ Local URL : http://${host}:${port}`);
console.log('\x1b[36m%s\x1b[0m', `   ➜ Installer : http://${host}:${port}/install`);
console.log('');
console.log('\x1b[90m%s\x1b[0m', '   Press Ctrl+C to stop the server.');
console.log('------------------------------------------------------------');

const server = spawn(phpBin, ['-S', `${host}:${port}`, '-t', 'public', 'router.php'], {
    cwd: rootDir,
    stdio: 'inherit',
    env: {
        ...process.env,
        PATH: `${path.join(rootDir, 'bin')}:${process.env.PATH || ''}`
    }
});

server.on('error', (err) => {
    console.error('\x1b[31m%s\x1b[0m', `Failed to start PHP server: ${err.message}`);
    process.exit(1);
});

process.on('SIGINT', () => {
    console.log('\nStopping Heartbeat server...');
    server.kill();
    process.exit(0);
});

process.on('SIGTERM', () => {
    server.kill();
    process.exit(0);
});

