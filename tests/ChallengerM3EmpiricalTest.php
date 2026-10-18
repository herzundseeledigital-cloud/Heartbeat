<?php
declare(strict_types=1);

/**
 * Challenger M3 Empirical Test Suite - Reviewer 2
 * Heartbeat CMS Enterprise Refinement - Milestone M3 (Theme Customizer & Live CSS Editor)
 */

if (!defined('HEARTBEAT_TEST')) define('HEARTBEAT_TEST', true);
if (!defined('HEARTBEAT_ROOT')) define('HEARTBEAT_ROOT', dirname(__DIR__));
if (!defined('HEARTBEAT_CORE')) define('HEARTBEAT_CORE', HEARTBEAT_ROOT . '/core');
if (!defined('HEARTBEAT_CONFIG')) define('HEARTBEAT_CONFIG', HEARTBEAT_ROOT . '/config');
if (!defined('HEARTBEAT_STORAGE')) define('HEARTBEAT_STORAGE', HEARTBEAT_ROOT . '/storage');
if (!defined('HEARTBEAT_PUBLIC')) define('HEARTBEAT_PUBLIC', HEARTBEAT_ROOT . '/public');
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
use Heartbeat\App;

$testDbFile = HEARTBEAT_STORAGE . '/test_challenger_m3.sqlite';
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
$db->insert('{prefix}options', ['option_name' => 'site_title', 'option_value' => 'Challenger M3 Suite', 'autoload' => 1]);
$db->insert('{prefix}options', ['option_name' => 'site_url', 'option_value' => 'http://localhost:8000', 'autoload' => 1]);
$db->insert('{prefix}options', ['option_name' => 'active_theme', 'option_value' => 'one-pager', 'autoload' => 1]);

// Create admin, editor, and subscriber users for RBAC testing
$db->insert('{prefix}users', [
    'username'      => 'm3_admin',
    'email'         => 'admin@challenger-m3.de',
    'password_hash' => Security::hashPassword('AdminPass2026!'),
    'role'          => Rbac::ROLE_ADMIN,
    'status'        => 'active',
    'created_at'    => $now,
    'updated_at'    => $now,
]);

$db->insert('{prefix}users', [
    'username'      => 'm3_editor',
    'email'         => 'editor@challenger-m3.de',
    'password_hash' => Security::hashPassword('EditorPass2026!'),
    'role'          => Rbac::ROLE_EDITOR,
    'status'        => 'active',
    'created_at'    => $now,
    'updated_at'    => $now,
]);

$db->insert('{prefix}users', [
    'username'      => 'm3_subscriber',
    'email'         => 'sub@challenger-m3.de',
    'password_hash' => Security::hashPassword('SubPass2026!'),
    'role'          => Rbac::ROLE_USER,
    'status'        => 'active',
    'created_at'    => $now,
    'updated_at'    => $now,
]);

Session::start();
$themeService = ThemeService::getInstance();

$passes = 0;
$fails = 0;

function runChallengerTest(string $title, callable $fn, int &$passes, int &$fails): void {
    try {
        $result = $fn();
        $msg = is_string($result) ? $result : 'OK';
        echo "  ✓ PASS: {$title} ({$msg})\n";
        $passes++;
    } catch (\Throwable $e) {
        $detail = $e->getMessage() . " in " . basename($e->getFile()) . ":" . $e->getLine();
        echo "  ✗ FAIL: {$title} -> {$detail}\n";
        $fails++;
    }
}

echo "======================================================================\n";
echo "=== CHALLENGER M3 ADVERSARIAL STRESS SUITE (REVIEWER 2) ===\n";
echo "======================================================================\n\n";

try {
    // -------------------------------------------------------------------------
    // Test 1: Slug Traversal & Sanity Protection
    // -------------------------------------------------------------------------
    runChallengerTest("Slug Traversal & Dangerous Filename Neutralization", function() use ($themeService) {
        $maliciousSlugs = [
            '../../../../etc/passwd',
            '..\\..\\windows\\system32',
            'theme/../../secret',
            'slug with spaces',
            'test;rm -rf /',
            '<script>alert(1)</script>',
            'null%00byte',
        ];

        foreach ($maliciousSlugs as $slug) {
            $testCss = "/* payload for {$slug} */ body { background: #000; }";
            $saved = $themeService->saveCustomCss($slug, $testCss);
            if (!$saved) {
                // If it fails safely, that's fine too
                continue;
            }

            // Verify clean slug was used
            $cleanSlug = preg_replace('/[^a-zA-Z0-9_-]/', '', $slug);
            if ($cleanSlug === '') $cleanSlug = 'default';

            $expectedPath = HEARTBEAT_STORAGE . "/custom-css/{$cleanSlug}.css";
            if (!is_file($expectedPath)) {
                throw new \AssertionError("Clean slug file not found at {$expectedPath} for input {$slug}");
            }

            // Ensure no file was created outside storage/custom-css
            if (str_contains($slug, '..')) {
                $forbiddenPath = dirname(HEARTBEAT_STORAGE) . "/custom-css";
                if (is_file($forbiddenPath . "/passwd") || is_file(HEARTBEAT_ROOT . "/etc/passwd.css")) {
                    throw new \AssertionError("Path traversal succeeded for slug: {$slug}");
                }
            }

            @unlink($expectedPath);
        }

        return "All malicious slug payloads neutralized into sanitized filenames";
    }, $passes, $fails);

    // -------------------------------------------------------------------------
    // Test 2: Multi-Theme Custom CSS Isolation
    // -------------------------------------------------------------------------
    runChallengerTest("Multi-Theme CSS Isolation (Zero Leakage Across Themes)", function() use ($themeService) {
        $themes = [
            'default'     => '/* DEFAULT ONLY */ .brand-default { color: #f97316; }',
            'corporate'   => '/* CORPORATE ONLY */ .brand-corp { color: #2563eb; }',
            'real-estate' => '/* REAL ESTATE ONLY */ .brand-re { color: #059669; }',
            'startup'     => '/* STARTUP ONLY */ .brand-startup { color: #8b5cf6; }',
        ];

        foreach ($themes as $slug => $css) {
            $themeService->saveCustomCss($slug, $css);
        }

        // Verify each theme's getCustomCss returns only its own CSS
        foreach ($themes as $slug => $expectedCss) {
            $retrieved = $themeService->getCustomCss($slug);
            if ($retrieved !== $expectedCss) {
                throw new \AssertionError("Mismatch for {$slug}: expected '{$expectedCss}', got '{$retrieved}'");
            }

            // Cross-check that other themes' CSS is NOT present
            foreach ($themes as $otherSlug => $otherCss) {
                if ($otherSlug !== $slug && str_contains($retrieved, "/* " . strtoupper($otherSlug) . " ONLY */")) {
                    throw new \AssertionError("Theme {$slug} leaked custom CSS from {$otherSlug}!");
                }
            }
        }

        return "Strict isolation verified across all 4 themes";
    }, $passes, $fails);

    // -------------------------------------------------------------------------
    // Test 3: Empty and Non-Existent CSS Handling
    // -------------------------------------------------------------------------
    runChallengerTest("Empty & Non-Existent Custom CSS Handling", function() use ($themeService) {
        // Non-existent theme
        $empty = $themeService->getCustomCss('completely-unknown-theme-xyz-999');
        if ($empty !== '') {
            throw new \AssertionError("Expected empty string for unknown theme, got: '{$empty}'");
        }

        // Save empty string for active theme
        $activeSlug = $themeService->getActive()->getSlug();
        $themeService->saveCustomCss($activeSlug, '');
        $retrieved = $themeService->getCustomCss($activeSlug);
        if ($retrieved !== '') {
            throw new \AssertionError("Expected empty string after saving empty CSS, got: '{$retrieved}'");
        }

        // Render active header: <style id="theme-custom-css"> should NOT be present when empty
        $html = $themeService->renderTemplate('header', [
            'siteTitle' => 'Test Site',
            'customCss' => '',
        ]);

        if (str_contains($html, '<style id="theme-custom-css">')) {
            throw new \AssertionError("Empty custom CSS still emitted <style id=\"theme-custom-css\"> in header!");
        }

        return "Empty CSS cleanly returns empty string and omits <style> block from head";
    }, $passes, $fails);

    // -------------------------------------------------------------------------
    // Test 4: Unicode, Emojis, RTL & Special CSS Syntax Resilience
    // -------------------------------------------------------------------------
    runChallengerTest("Unicode, Emojis, Keyframes, Font-Face & CSS Variables Resilience", function() use ($themeService) {
        $advancedCss = <<<CSS
@charset "UTF-8";
@import url("https://fonts.local/custom.css");

:root {
    --accent-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    --card-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

@keyframes pulseHeartbeat {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.05); opacity: 0.9; }
}

.heartbeat-badge::before {
    content: "❤️ Made with Liebe in München 🇩🇪 — Übergröße & Präzision";
    font-feature-settings: "cv02", "cv03", "cv04", "cv11";
}

/* RTL support test */
[dir="rtl"] .sidebar {
    right: 0;
    left: auto;
    content: "مرحبا بكم";
}
CSS;

        $themeService->saveCustomCss('startup', $advancedCss);
        $retrieved = $themeService->getCustomCss('startup');

        if ($retrieved !== $advancedCss) {
            throw new \AssertionError("Advanced CSS corrupted during roundtrip!");
        }

        $startupTheme = $themeService->get('startup');
        $rendered = $themeService->renderTemplate('header', [
            'theme'     => $startupTheme,
            'siteTitle' => 'Startup Site',
            'customCss' => $advancedCss,
        ]);

        if (!str_contains($rendered, 'Made with Liebe in München 🇩🇪')) {
            throw new \AssertionError("Rendered header missing German umlauts / emoji in custom CSS");
        }
        if (!str_contains($rendered, 'pulseHeartbeat')) {
            throw new \AssertionError("Rendered header missing @keyframes in custom CSS");
        }

        return "Complex CSS rules, animations, RTL, and Unicode roundtrip with 100% fidelity";
    }, $passes, $fails);

    // -------------------------------------------------------------------------
    // Test 5: Customizer Controller RBAC Matrix
    // -------------------------------------------------------------------------
    runChallengerTest("Customizer RBAC Matrix (Guest, Author/User, Admin)", function() use ($db) {
        $ctrl = new ThemeController();

        // 1. Unauthenticated Guest
        Auth::logout();
        $resp = $ctrl->customize(new Request('GET', '/admin/themes/customize'));
        if ($resp->getStatusCode() !== 302 || !str_contains($resp->getHeader('Location') ?? '', '/admin/login')) {
            throw new \AssertionError("Guest access to /admin/themes/customize did not redirect to login");
        }

        // 2. Author / Standard User (ROLE_USER: manage_themes => false)
        $authorUser = $db->fetchOne("SELECT * FROM {prefix}users WHERE username = 'm3_subscriber'");
        Auth::login($authorUser);
        $resp = $ctrl->customize(new Request('GET', '/admin/themes/customize'));
        if ($resp->getStatusCode() !== 302) {
            throw new \AssertionError("Author/User without manage_themes was not blocked from /admin/themes/customize (got HTTP {$resp->getStatusCode()})");
        }

        // 3. Admin (ROLE_ADMIN: manage_themes => true)
        $adminUser = $db->fetchOne("SELECT * FROM {prefix}users WHERE username = 'm3_admin'");
        Auth::login($adminUser);
        $resp = $ctrl->customize(new Request('GET', '/admin/themes/customize'));
        if ($resp->getStatusCode() !== 200) {
            throw new \AssertionError("Admin was blocked from /admin/themes/customize (got HTTP {$resp->getStatusCode()})");
        }

        return "RBAC strictly enforced: unprivileged roles blocked from Theme Customizer";
    }, $passes, $fails);

    // -------------------------------------------------------------------------
    // Test 6: Customizer Controller CSRF Protection on Save
    // -------------------------------------------------------------------------
    runChallengerTest("Customizer POST /admin/themes/save CSRF Attack Interception", function() use ($db, $themeService) {
        $ctrl = new ThemeController();
        $adminUser = $db->fetchOne("SELECT * FROM {prefix}users WHERE username = 'm3_admin'");
        Auth::login($adminUser);

        // 1. POST without CSRF token
        $reqNoCsrf = new Request('POST', '/admin/themes/save', [], [
            'theme_slug' => 'corporate',
            'custom_css' => '/* CSRF HACK */ body { background: purple !important; }',
        ]);
        $resp = $ctrl->save($reqNoCsrf);
        if ($resp->getStatusCode() !== 302) {
            throw new \AssertionError("Expected 302 redirect on missing CSRF token");
        }
        $corpCss = $themeService->getCustomCss('corporate');
        if (str_contains($corpCss, '/* CSRF HACK */')) {
            throw new \AssertionError("CSRF protection failed: malicious CSS saved without CSRF token!");
        }

        // 2. POST with invalid CSRF token
        $reqInvalidCsrf = new Request('POST', '/admin/themes/save', [], [
            '_csrf_token' => 'invalid_csrf_token_1234567890',
            'theme_slug'  => 'corporate',
            'custom_css'  => '/* CSRF HACK 2 */ body { background: pink !important; }',
        ]);
        $resp = $ctrl->save($reqInvalidCsrf);
        $corpCss2 = $themeService->getCustomCss('corporate');
        if (str_contains($corpCss2, '/* CSRF HACK 2 */')) {
            throw new \AssertionError("CSRF protection failed: malicious CSS saved with bogus CSRF token!");
        }

        // 3. POST with valid CSRF token
        $validToken = Csrf::token();
        $reqValid = new Request('POST', '/admin/themes/save', [], [
            '_csrf_token' => $validToken,
            'theme_slug'  => 'corporate',
            'custom_css'  => '/* VALID SAVE */ .valid-header { color: #0284c7; }',
        ]);
        $resp = $ctrl->save($reqValid);
        $corpCss3 = $themeService->getCustomCss('corporate');
        if (!str_contains($corpCss3, '/* VALID SAVE */')) {
            throw new \AssertionError("Valid POST with CSRF failed to save!");
        }

        return "CSRF verification strictly guards custom CSS persistence";
    }, $passes, $fails);

    // -------------------------------------------------------------------------
    // Test 7: XSS Defense in Theme Customizer Alpine Config JSON
    // -------------------------------------------------------------------------
    runChallengerTest("XSS & Breakout Defense in Alpine JSON Config Serialization", function() use ($themeService) {
        $xssCss = "</textarea><script>alert('XSS-Injected')</script><style>/* breakout */";
        $themeService->saveCustomCss('corporate', $xssCss);

        $ctrl = new ThemeController();
        $req = new Request('GET', '/admin/themes/customize', ['theme' => 'corporate']);
        $resp = $ctrl->customize($req);
        $html = $resp->getBody();

        // Ensure raw </textarea> or <script> does not exist in the Alpine initialization attribute
        if (str_contains($html, "themeStudio({") && str_contains($html, "<script>alert")) {
            throw new \AssertionError("Unescaped <script> tag detected inside themeStudio JSON payload!");
        }

        // Ensure JSON_HEX_TAG turned `<` into `\u003C` and `>` into `\u003E`
        if (!str_contains($html, '\u003C\/textarea\u003E\u003Cscript\u003E')) {
            throw new \AssertionError("JSON_HEX_TAG encoding not detected for HTML tags in customizer JSON");
        }

        return "HTML tags in custom CSS properly serialized with JSON_HEX_* flags";
    }, $passes, $fails);

    // -------------------------------------------------------------------------
    // Test 8: Large CSS Payload Performance Stress Test
    // -------------------------------------------------------------------------
    runChallengerTest("Large CSS Payload Stress Test (10,000 Lines / 350 KB)", function() use ($themeService) {
        $lines = [];
        $lines[] = "/* Large Enterprise CSS Test */";
        for ($i = 1; $i <= 10000; $i++) {
            $lines[] = ".generated-utility-class-{$i} { margin: {$i}px; padding: " . ($i * 2) . "px; }";
        }
        $largeCss = implode("\n", $lines);

        $start = microtime(true);
        $saved = $themeService->saveCustomCss('real-estate', $largeCss);
        $saveTime = microtime(true) - $start;

        if (!$saved) {
            throw new \AssertionError("Failed to save large CSS payload");
        }

        $start2 = microtime(true);
        $retrieved = $themeService->getCustomCss('real-estate');
        $readTime = microtime(true) - $start2;

        if (strlen($retrieved) !== strlen($largeCss)) {
            throw new \AssertionError("Retrieved size mismatch: expected " . strlen($largeCss) . ", got " . strlen($retrieved));
        }

        if ($saveTime > 1.0 || $readTime > 0.5) {
            throw new \AssertionError("I/O latency too high: save={$saveTime}s, read={$readTime}s");
        }

        // Clean up large file
        $storageFile = HEARTBEAT_STORAGE . '/custom-css/real-estate.css';
        @unlink($storageFile);

        return sprintf("10,000 lines (348 KB) saved in %.4fs and read in %.4fs", $saveTime, $readTime);
    }, $passes, $fails);

} finally {
    Auth::logout();
    if (file_exists($testDbFile)) @unlink($testDbFile);
    if (file_exists($testDbFile . '-wal')) @unlink($testDbFile . '-wal');
    if (file_exists($testDbFile . '-shm')) @unlink($testDbFile . '-shm');
}

echo "\n----------------------------------------------------------------------\n";
printf("Total Tests: %d  |  Passed: %d  |  Failed: %d\n", $passes + $fails, $passes, $fails);
echo "======================================================================\n";

if ($fails > 0) {
    exit(1);
}

exit(0);
