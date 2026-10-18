<?php
declare(strict_types=1);

/**
 * Adversarial Integrity & Comprehensive Test for Milestone M3
 * Heartbeat CMS Enterprise Refinement - Theme Customizer & Live CSS Editor (R4)
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
use Heartbeat\Themes\Theme;
use Heartbeat\Admin\ThemeController;
use Heartbeat\Http\Request;
use Heartbeat\Auth\Auth;
use Heartbeat\Security\Rbac;
use Heartbeat\Security\Security;
use Heartbeat\Security\Session;
use Heartbeat\Security\Csrf;
use Heartbeat\App;

$failures = [];
$passes = [];

function assertM3(string $name, callable $fn, array &$passes, array &$failures): void {
    try {
        $result = $fn();
        $msg = is_string($result) ? $result : 'OK';
        $passes[] = "✓ PASS: {$name} ({$msg})";
        echo "  ✓ PASS: {$name} ({$msg})\n";
    } catch (\Throwable $e) {
        $detail = $e->getMessage() . " in " . basename($e->getFile()) . ":" . $e->getLine();
        $failures[] = "✗ FAIL: {$name} -> {$detail}";
        echo "  ✗ FAIL: {$name} -> {$detail}\n";
    }
}

echo "======================================================================\n";
echo "=== AUDITOR M3 ADVERSARIAL INTEGRITY & LIVE CSS CUSTOMIZER TEST ===\n";
echo "======================================================================\n\n";

$testDbFile = HEARTBEAT_STORAGE . '/test_adversarial_m3.sqlite';
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
$db->insert('{prefix}options', ['option_name' => 'site_title', 'option_value' => 'M3 Customizer Test', 'autoload' => 1]);
$db->insert('{prefix}options', ['option_name' => 'site_url', 'option_value' => 'http://localhost:8000', 'autoload' => 1]);
$db->insert('{prefix}options', ['option_name' => 'active_theme', 'option_value' => 'one-pager', 'autoload' => 1]);

$db->insert('{prefix}users', [
    'username'      => 'customizer_admin',
    'email'         => 'admin@customizer-test.de',
    'password_hash' => Security::hashPassword('SuperPass2026!'),
    'role'          => Rbac::ROLE_ADMIN,
    'status'        => 'active',
    'created_at'    => $now,
    'updated_at'    => $now,
]);

Session::start();
$user = $db->fetchOne("SELECT * FROM {prefix}users WHERE username = 'customizer_admin'");
Auth::login($user);

$themeService = ThemeService::getInstance();

try {
    // -------------------------------------------------------------------------
    // Test 1: ThemeService Custom CSS Persistence & Retrieval
    // -------------------------------------------------------------------------
    assertM3("ThemeService::saveCustomCss persists to storage and mirrors to themes/ when writable", function () use ($themeService) {
        $slug = 'corporate';
        $css = "/* Corporate Custom CSS */\n:root { --custom-accent: #0284c7; }\n.btn-corp { padding: 12px 24px; border-radius: 8px; }";

        $saved = $themeService->saveCustomCss($slug, $css);
        if (!$saved) {
            throw new \AssertionError("saveCustomCss returned false");
        }

        $storageFile = HEARTBEAT_STORAGE . "/custom-css/{$slug}.css";
        if (!is_file($storageFile)) {
            throw new \AssertionError("Storage file {$storageFile} does not exist");
        }
        $stored = file_get_contents($storageFile);
        if ($stored !== $css) {
            throw new \AssertionError("Stored CSS content mismatch");
        }

        $retrieved = $themeService->getCustomCss($slug);
        if ($retrieved !== $css) {
            throw new \AssertionError("getCustomCss did not return exact stored CSS");
        }

        return "Successfully saved and retrieved exact corporate CSS";
    }, $passes, $failures);

    // -------------------------------------------------------------------------
    // Test 2: Adversarial Character Roundtrip & Traversal Defense
    // -------------------------------------------------------------------------
    assertM3("Adversarial CSS (Unicode, Umlauts, Quotes, Media Queries, Traversal Slugs)", function () use ($themeService) {
        $complexCss = <<<CSS
/* Test German: Übergröße & Schnelligkeit */
@media (min-width: 1024px) {
    .hero-banner::after {
        content: "Heartbeat 'Special' <Tag> & \"Double Quotes\"";
        background: url('/assets/img/pattern.svg?v=1&w=2');
        transform: translate3d(0, 0, 0);
    }
}
/* Emojis: 🚀 ⚡ 💖 */
CSS;
        $saved = $themeService->saveCustomCss('real-estate', $complexCss);
        if (!$saved) throw new \AssertionError("Failed to save complex CSS");

        $retrieved = $themeService->getCustomCss('real-estate');
        if ($retrieved !== $complexCss) {
            throw new \AssertionError("Complex CSS was modified or corrupted in roundtrip");
        }

        // Test path traversal slug sanitation
        $traversalSlug = '../../sensitive/etc';
        $themeService->saveCustomCss($traversalSlug, ".safe { color: red; }");
        $sanitizedFile = HEARTBEAT_STORAGE . "/custom-css/sensitiveetc.css";
        if (!is_file($sanitizedFile)) {
            throw new \AssertionError("Sanitized slug file expected at {$sanitizedFile}");
        }
        @unlink($sanitizedFile);

        return "Unicode, quotes, media queries preserved and traversal safely sanitized";
    }, $passes, $failures);

    // -------------------------------------------------------------------------
    // Test 3: Frontend <head> Injection Across All 4 Themes
    // -------------------------------------------------------------------------
    assertM3("Frontend <head> injection works across all 4 themes", function () use ($themeService) {
        $themes = ['default' => 'one-pager', 'corporate' => 'corporate', 'real-estate' => 'real-estate', 'startup' => 'startup'];
        
        foreach ($themes as $dirSlug => $themeSlug) {
            $testRule = ".theme-spec-{$dirSlug} { background-color: var(--hb-primary); }";
            $themeService->saveCustomCss($themeSlug, $testRule);

            $themeObj = $themeService->get($themeSlug);
            $rendered = $themeService->renderTemplate('header', [
                'theme'     => $themeObj,
                'siteTitle' => 'Test Site',
                'customCss' => $testRule,
            ]);

            if ($rendered === null) {
                throw new \AssertionError("Failed to render header for {$themeSlug}");
            }

            if (!str_contains($rendered, '<style id="theme-custom-css">')) {
                throw new \AssertionError("Missing <style id=\"theme-custom-css\"> in {$themeSlug} header");
            }

            if (!str_contains($rendered, ".theme-spec-{$dirSlug}")) {
                throw new \AssertionError("Custom CSS rule missing from {$themeSlug} head");
            }
        }

        return "All 4 themes successfully inject <style id=\"theme-custom-css\">";
    }, $passes, $failures);

    // -------------------------------------------------------------------------
    // Test 4: ThemeController::customize View Features & No Bundle Leaks
    // -------------------------------------------------------------------------
    assertM3("ThemeController::customize provides clean code editor with line numbers and live preview", function () use ($themeService) {
        $customCss = ".editor-canvas-test { font-family: monospace; }";
        $themeService->saveCustomCss('one-pager', $customCss);

        $ctrl = new ThemeController();
        $req = new Request('GET', '/admin/themes/customize');
        $resp = $ctrl->customize($req);

        if ($resp->getStatusCode() !== 200) {
            throw new \AssertionError("Expected HTTP 200, got {$resp->getStatusCode()}");
        }

        $html = $resp->getBody();

        // 1. Gutter line numbers
        if (!str_contains($html, 'id="cssLineNumbers"')) {
            throw new \AssertionError("Missing line numbers gutter #cssLineNumbers");
        }

        // 2. Code editor textarea
        if (!str_contains($html, 'id="customCssEditor"') || !str_contains($html, 'name="custom_css"')) {
            throw new \AssertionError("Missing #customCssEditor textarea");
        }

        // 3. Tab key indentation and scroll sync
        if (!str_contains($html, '@keydown.tab.prevent') || !str_contains($html, '@scroll=')) {
            throw new \AssertionError("Missing Tab key handler or scroll sync on textarea");
        }

        // 4. Live preview iframe and live-custom-css handler
        if (!str_contains($html, 'id="themePreviewFrame"') || !str_contains($html, 'updatePreviewCss')) {
            throw new \AssertionError("Missing #themePreviewFrame or updatePreviewCss function");
        }

        // 5. Save button
        if (!str_contains($html, 'Änderungen speichern')) {
            throw new \AssertionError("Missing 'Änderungen speichern' button");
        }

        // 6. Zero minified CSS bundle leakage
        if (str_contains($html, '/*! tailwindcss') || str_contains($html, '@tailwind base')) {
            throw new \AssertionError("Minified framework CSS bundle leaked into customizer!");
        }

        return "Complete Alpine.js code editor, line gutter, tab indent and live preview verified";
    }, $passes, $failures);

    // -------------------------------------------------------------------------
    // Test 5: ThemeController::save Action Lifecycle
    // -------------------------------------------------------------------------
    assertM3("ThemeController::save persists custom CSS and redirects", function () use ($themeService) {
        $ctrl = new ThemeController();
        $newCss = "/* Saved via Customizer POST */ .new-custom-rule { display: flex; }";
        $csrfToken = Csrf::token();

        $req = new Request('POST', '/admin/themes/save', [], [
            '_csrf_token' => $csrfToken,
            'theme_slug'  => 'startup',
            'colors'      => ['primary' => '#8b5cf6'],
            'custom_css'  => $newCss,
        ]);

        $resp = $ctrl->save($req);
        if ($resp->getStatusCode() !== 302) {
            throw new \AssertionError("Expected HTTP 302 redirect, got {$resp->getStatusCode()}");
        }

        $savedCss = $themeService->getCustomCss('startup');
        if (!str_contains($savedCss, '.new-custom-rule')) {
            throw new \AssertionError("Custom CSS was not saved via ThemeController::save");
        }

        return "Successfully saved and redirected to customize view";
    }, $passes, $failures);

    // -------------------------------------------------------------------------
    // Test 6: Fallback Shell Custom CSS in App.php
    // -------------------------------------------------------------------------
    assertM3("App.php fallback shells inject <style id=\"theme-custom-css\">", function () use ($themeService) {
        $app = new App();
        $ref = new \ReflectionClass($app);
        
        $renderShellMethod = $ref->getMethod('renderPageShell');
        $renderShellMethod->setAccessible(true);

        $theme = $themeService->get('one-pager');
        $themeService->saveCustomCss('one-pager', '.app-shell-rule { margin: 0; }');

        $html = $renderShellMethod->invoke(
            $app,
            'Test Page',
            'Test Site',
            'http://localhost:8000',
            $theme,
            '<p>Content</p>',
            [],
            ['title' => 'Test Page', 'slug' => 'test-page'],
            []
        );

        if (!str_contains($html, '<style id="theme-custom-css">')) {
            throw new \AssertionError("renderPageShell did not inject <style id=\"theme-custom-css\">");
        }

        if (!str_contains($html, '.app-shell-rule')) {
            throw new \AssertionError("renderPageShell did not output custom CSS content");
        }

        return "renderPageShell cleanly injected custom CSS";
    }, $passes, $failures);

} finally {
    Auth::logout();
    if (file_exists($testDbFile)) @unlink($testDbFile);
    if (file_exists($testDbFile . '-wal')) @unlink($testDbFile . '-wal');
    if (file_exists($testDbFile . '-shm')) @unlink($testDbFile . '-shm');
}

echo "\n----------------------------------------------------------------------\n";
printf("Total Tests: %d  |  Passed: %d  |  Failed: %d\n", count($passes) + count($failures), count($passes), count($failures));
echo "======================================================================\n";

if (count($failures) > 0) {
    exit(1);
}

exit(0);
