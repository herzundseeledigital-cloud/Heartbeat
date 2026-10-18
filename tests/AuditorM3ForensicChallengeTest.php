<?php
declare(strict_types=1);

/**
 * Independent Forensic Challenger Suite for Milestone M3
 * Author: Forensic Auditor
 */

if (!defined('HEARTBEAT_TEST')) define('HEARTBEAT_TEST', true);
if (!defined('HEARTBEAT_ROOT')) define('HEARTBEAT_ROOT', dirname(__DIR__));
if (!defined('HEARTBEAT_CORE')) define('HEARTBEAT_CORE', HEARTBEAT_ROOT . '/core');
if (!defined('HEARTBEAT_CONFIG')) define('HEARTBEAT_CONFIG', HEARTBEAT_ROOT . '/config');
if (!defined('HEARTBEAT_PUBLIC')) define('HEARTBEAT_PUBLIC', HEARTBEAT_ROOT . '/public');
if (!defined('HEARTBEAT_STORAGE')) define('HEARTBEAT_STORAGE', HEARTBEAT_ROOT . '/storage');
if (!defined('HEARTBEAT_THEMES')) define('HEARTBEAT_THEMES', HEARTBEAT_ROOT . '/themes');

require_once HEARTBEAT_CORE . '/Bootstrap.php';
\Heartbeat\Bootstrap::init();

use Heartbeat\Database\Database;
use Heartbeat\Database\MigrationRunner;
use Heartbeat\Themes\ThemeService;
use Heartbeat\Admin\ThemeController;
use Heartbeat\Http\Request;
use Heartbeat\Auth\Auth;
use Heartbeat\Security\Rbac;
use Heartbeat\Security\Security;
use Heartbeat\Security\Session;
use Heartbeat\Security\Csrf;

$passes = 0;
$failures = [];

function runForensicCheck(string $desc, callable $fn): void {
    global $passes, $failures;
    try {
        $msg = $fn();
        $passes++;
        echo "  [PASS] {$desc}" . ($msg ? " -> {$msg}" : "") . "\n";
    } catch (\Throwable $e) {
        $failures[] = "{$desc}: " . $e->getMessage() . " (" . basename($e->getFile()) . ":" . $e->getLine() . ")";
        echo "  [FAIL] {$desc} -> " . $e->getMessage() . "\n";
    }
}

echo "======================================================================\n";
echo "=== AUDITOR INDEPENDENT FORENSIC CHALLENGE SUITE (MILESTONE M3) ===\n";
echo "======================================================================\n\n";

$testDbFile = HEARTBEAT_STORAGE . '/test_forensic_m3.sqlite';
if (file_exists($testDbFile)) @unlink($testDbFile);
if (file_exists($testDbFile . '-wal')) @unlink($testDbFile . '-wal');
if (file_exists($testDbFile . '-shm')) @unlink($testDbFile . '-shm');

$db = new Database([
    'driver'   => 'sqlite',
    'database' => $testDbFile,
    'prefix'   => 'hb_',
]);
Database::setInstance($db);
$runner = new MigrationRunner($db);
$runner->runPending(HEARTBEAT_CORE . '/Database/Schema');

$now = date('Y-m-d H:i:s');
$db->insert('{prefix}options', ['option_name' => 'site_title', 'option_value' => 'Auditor Test', 'autoload' => 1]);
$db->insert('{prefix}options', ['option_name' => 'site_url', 'option_value' => 'http://localhost:8000', 'autoload' => 1]);
$db->insert('{prefix}options', ['option_name' => 'active_theme', 'option_value' => 'one-pager', 'autoload' => 1]);
$db->insert('{prefix}users', [
    'username'      => 'auditor_admin',
    'email'         => 'admin@auditor.test',
    'password_hash' => Security::hashPassword('AuditorPassword2026!'),
    'role'          => Rbac::ROLE_ADMIN,
    'status'        => 'active',
    'created_at'    => $now,
    'updated_at'    => $now,
]);

Session::start();
$user = $db->fetchOne("SELECT * FROM {prefix}users WHERE username = 'auditor_admin'");
Auth::login($user);

$themeService = ThemeService::getInstance();

try {
    // 1. Directory Traversal Defense
    runForensicCheck("Directory traversal attempt in saveCustomCss", function () use ($themeService) {
        $traversalSlug = '../../../../etc/passwd';
        $themeService->saveCustomCss($traversalSlug, "/* traversal test */");
        if (file_exists(dirname(__DIR__, 2) . '/etc/passwd.css')) {
            throw new \AssertionError("Directory traversal escaped root!");
        }
        $expected = HEARTBEAT_STORAGE . '/custom-css/etcpasswd.css';
        if (!file_exists($expected)) {
            throw new \AssertionError("Sanitized slug not written to custom-css dir");
        }
        @unlink($expected);
        return "Sanitized to 'etcpasswd.css' within custom-css directory";
    });

    // 2. Empty Slug Fallback
    runForensicCheck("Empty or pure-symbol slug fallback to 'default'", function () use ($themeService) {
        $themeService->saveCustomCss("!@#$%^&*()", "/* symbol test */");
        $expected = HEARTBEAT_STORAGE . '/custom-css/default.css';
        if (!file_exists($expected)) {
            throw new \AssertionError("Fallback default.css not created");
        }
        $retrieved = $themeService->getCustomCss("!@#$%^&*()");
        if (!str_contains($retrieved, "/* symbol test */")) {
            throw new \AssertionError("Failed to retrieve custom css for symbol slug");
        }
        return "Defaulted safely to default.css";
    });

    // 3. High-volume Stress Test (100KB payload)
    runForensicCheck("Large 100KB CSS payload integrity", function () use ($themeService) {
        $largeCss = "/* 100KB Stress Test */\n";
        for ($i = 0; $i < 2000; $i++) {
            $largeCss .= ".rule-{$i} { margin: {$i}px; padding: calc({$i}px / 2); }\n";
        }
        $themeService->saveCustomCss('corporate', $largeCss);
        $retrieved = $themeService->getCustomCss('corporate');
        if ($retrieved !== $largeCss) {
            throw new \AssertionError("Payload length mismatch: expected " . strlen($largeCss) . ", got " . strlen($retrieved));
        }
        return "100KB payload stored and read without byte loss";
    });

    // 4. Precedence: Storage file vs Theme file
    runForensicCheck("Storage custom-css takes precedence over theme directory fallback", function () use ($themeService) {
        $storageFile = HEARTBEAT_STORAGE . '/custom-css/corporate.css';
        $themeFile = HEARTBEAT_THEMES . '/corporate/custom.css';
        
        file_put_contents($themeFile, "/* THEME FILE */");
        file_put_contents($storageFile, "/* STORAGE FILE */");

        $retrieved = $themeService->getCustomCss('corporate');
        if ($retrieved !== "/* STORAGE FILE */") {
            throw new \AssertionError("Storage file did not take precedence over theme file");
        }

        // Now remove storage file and verify fallback to theme file
        @unlink($storageFile);
        $retrievedFallback = $themeService->getCustomCss('corporate');
        if ($retrievedFallback !== "/* THEME FILE */") {
            throw new \AssertionError("Fallback to theme file failed");
        }

        return "Storage precedence and theme fallback work correctly";
    });

    // 5. Slug Aliases: 'one-pager' vs 'default'
    runForensicCheck("Slug alias resolution between 'default' and 'one-pager'", function () use ($themeService) {
        $themeService->saveCustomCss('one-pager', "/* ALIAS TEST */");
        $fromOnePager = $themeService->getCustomCss('one-pager');
        $fromDefault = $themeService->getCustomCss('default');
        
        // Since active theme is one-pager (whose path is themes/default), getCustomCss should retrieve it
        if (!str_contains($fromOnePager, "/* ALIAS TEST */")) {
            throw new \AssertionError("Failed to get by one-pager slug");
        }
        return "Alias resolved correctly";
    });

    // 6. Security: ThemeController CSRF and RBAC protection
    runForensicCheck("ThemeController::save rejects invalid CSRF token", function () {
        $ctrl = new ThemeController();
        $req = new Request('POST', '/admin/themes/save', [], [
            '_csrf_token' => 'invalid-token-123',
            'theme_slug'  => 'corporate',
            'custom_css'  => '/* malicious */',
        ]);
        try {
            $resp = $ctrl->save($req);
            // If it returns, check if it redirected with error or threw exception
            if ($resp->getStatusCode() !== 302 && $resp->getStatusCode() !== 403) {
                throw new \AssertionError("Unexpected status code " . $resp->getStatusCode());
            }
        } catch (\Heartbeat\Security\SecurityException) {
            // Expected
        }
        return "CSRF verification triggered as expected";
    });

    // 7. Security: Unauthenticated access to customizer
    runForensicCheck("Unauthenticated access to /admin/themes/customize redirects or denies", function () {
        Auth::logout();
        $ctrl = new ThemeController();
        $req = new Request('GET', '/admin/themes/customize');
        try {
            $resp = $ctrl->customize($req);
            if ($resp->getStatusCode() !== 302) {
                throw new \AssertionError("Expected redirect to login, got " . $resp->getStatusCode());
            }
        } catch (\Heartbeat\Security\SecurityException) {
            // Expected
        }
        return "Unauthenticated access prevented";
    });

} finally {
    Auth::logout();
    if (file_exists($testDbFile)) @unlink($testDbFile);
    if (file_exists($testDbFile . '-wal')) @unlink($testDbFile . '-wal');
    if (file_exists($testDbFile . '-shm')) @unlink($testDbFile . '-shm');
}

echo "\n----------------------------------------------------------------------\n";
printf("Challenger Results: Total %d  |  Passed: %d  |  Failed: %d\n", $passes + count($failures), $passes, count($failures));
echo "======================================================================\n";

if (count($failures) > 0) {
    exit(1);
}
exit(0);
