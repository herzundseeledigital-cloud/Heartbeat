#!/usr/bin/env node
/**
 * Heartbeat CMS - Local Development Runner
 * Starts PHP built-in server with router.php & automatic port resolution
 */

const { spawn } = require('child_process');
const fs = require('fs');
const path = require('path');
const net = require('net');

// Determine PHP binary
function findPhpBinary() {
    const candidates = [
        '/Applications/XAMPP/xamppfiles/bin/php',
        '/opt/homebrew/bin/php',
        '/usr/local/bin/php',
        '/usr/bin/php',
        path.join(__dirname, '..', 'bin', 'php')
    ];

    for (const bin of candidates) {
        try {
            if (fs.existsSync(bin)) return bin;
        } catch (e) {}
    }
    return 'php';
}

function checkPort(port, host) {
    return new Promise((resolve) => {
        const s = net.createServer()
            .once('error', () => resolve(false))
            .once('listening', () => {
                s.once('close', () => resolve(true)).close();
            })
            .listen(port, host);
    });
}

async function findAvailablePort(startPort, host) {
    if (process.env.PORT) return parseInt(process.env.PORT, 10);
    const candidates = [startPort, 8080, 8001, 8002, 8888, 3000];
    for (const p of candidates) {
        if (await checkPort(p, host)) {
            return p;
        }
    }
    return startPort;
}

async function main() {
    const phpBin = findPhpBinary();
    const rootDir = path.resolve(__dirname, '..');
    const host = process.env.HOST || '127.0.0.1';
    const desiredPort = parseInt(process.env.PORT || '8000', 10);
    const port = await findAvailablePort(desiredPort, host);

    console.log('\x1b[38;2;251;50;111m%s\x1b[0m', '⚡ HEARTBEAT CMS - Local Core Development Server');
    console.log('\x1b[90m%s\x1b[0m', `   PHP Binary : ${phpBin}`);
    console.log('\x1b[90m%s\x1b[0m', `   DocumentRoot: ${path.join(rootDir, 'public')}`);
    console.log('\x1b[90m%s\x1b[0m', `   Router Script: router.php`);
    if (port !== desiredPort) {
        console.log('\x1b[33m%s\x1b[0m', `   ⚠️ Port ${desiredPort} war bereits durch einen anderen Prozess belegt.`);
        console.log('\x1b[33m%s\x1b[0m', `   👉 Server startet automatisch auf Port ${port}!`);
    }
    console.log('');
    console.log('\x1b[32m%s\x1b[0m', `   ➜ Local URL : http://localhost:${port} (or http://${host}:${port})`);
    console.log('\x1b[36m%s\x1b[0m', `   ➜ Installer : http://localhost:${port}/install`);
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

    server.on('exit', (code, signal) => {
        if (code !== 0 && code !== null) {
            console.error('\x1b[31m%s\x1b[0m', `PHP server exited with code ${code}`);
        }
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
}

main();
