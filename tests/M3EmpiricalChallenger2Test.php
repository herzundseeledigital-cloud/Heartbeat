<?php
declare(strict_types=1);

/**
 * Empirical Adversarial Test Suite - Challenger 2 (Milestone M3)
 * Heartbeat CMS Enterprise Refinement - Theme Customizer & Live CSS Editor (R4)
 * 
 * Verifies:
 * 1. Isolation & Persistence across Theme Switching (One-Pager, Startup, Corporate, Real Estate)
 * 2. Storage Mirroring (storage/custom-css/{theme}.css <-> themes/{theme}/custom.css) & Healing
 * 3. Alpine.js UI mechanics & Event Handlers in ThemeController::customize
 * 4. End-to-end ThemeController::save() persistence and immediate frontend reflection
 * 5. Adversarial input hardening (XSS tag escape, unicode, empty CSS suppression, path traversal)
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
use Heartbeat\Http\Response;
use Heartbeat\Auth\Auth;
use Heartbeat\Security\Rbac;
use Heartbeat\Security\Security;
use Heartbeat\Security\Session;
use Heartbeat\Security\Csrf;
use Heartbeat\App;

$passes = [];
$failures = [];

function challengeAssert(string $title, callable $fn, array &$passes, array &$failures): void {
    try {
        $result = $fn();
        $msg = is_string($result) ? $result : 'OK';
        $passes[] = "✓ PASS: {$title} ({$msg})";
        echo "  ✓ PASS: {$title} ({$msg})\n";
    } catch (\Throwable $e) {
        $detail = $e->getMessage() . " [" . basename($e->getFile()) . ":" . $e->getLine() . "]";
        $failures[] = "✗ FAIL: {$title} -> {$detail}";
        echo "  ✗ FAIL: {$title} -> {$detail}\n";
    }
}

echo "======================================================================\n";
echo "=== CHALLENGER 2: EMPIRICAL ADVERSARIAL HARNESS FOR MILESTONE M3 ===\n";
echo "======================================================================\n\n";

// 1. Setup isolated test SQLite database
$testDbFile = HEARTBEAT_STORAGE . '/test_m3_challenger2.sqlite';
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
$db->insert('{prefix}options', ['option_name' => 'site_title', 'option_value' => 'Empirical Challenger M3', 'autoload' => 1]);
$db->insert('{prefix}options', ['option_name' => 'site_url', 'option_value' => 'http://localhost:8000', 'autoload' => 1]);
$db->insert('{prefix}options', ['option_name' => 'active_theme', 'option_value' => 'one-pager', 'autoload' => 1]);

$db->insert('{prefix}users', [
    'username'      => 'challenger2_admin',
    'email'         => 'challenger2@heartbeat-cms.de',
    'password_hash' => Security::hashPassword('ChallengerSecret2026!'),
    'role'          => Rbac::ROLE_ADMIN,
    'status'        => 'active',
    'created_at'    => $now,
    'updated_at'    => $now,
]);

Session::start();
$adminUser = $db->fetchOne("SELECT * FROM {prefix}users WHERE username = 'challenger2_admin'");
Auth::login($adminUser);

$themeService = ThemeService::getInstance();
$themeController = new ThemeController($themeService);

// Backup existing custom CSS files to restore later
$themesList = ['default', 'one-pager', 'startup', 'corporate', 'real-estate'];
$originalStorageCss = [];
$originalThemeCss = [];

foreach ($themesList as $tSlug) {
    $sFile = HEARTBEAT_STORAGE . "/custom-css/{$tSlug}.css";
    if (file_exists($sFile)) {
        $originalStorageCss[$tSlug] = file_get_contents($sFile);
    }
    $tObj = $themeService->get($tSlug);
    if ($tObj) {
        $tFile = $tObj->getPath() . '/custom.css';
        if (file_exists($tFile)) {
            $originalThemeCss[$tSlug] = file_get_contents($tFile);
        }
    }
}

try {
    // =========================================================================
    // TEST GROUP 1: THEME SWITCHING ISOLATION
    // =========================================================================
    echo "--- Group 1: Theme Switching Isolation Across All 4 Themes ---\n";

    challengeAssert("Isolation: Distinct custom CSS per theme remains completely separated", function () use ($themeService) {
        $payloads = [
            'one-pager'   => "/* [SIG_ONE_PAGER] */\n.theme-op { color: #f97316; }",
            'startup'     => "/* [SIG_STARTUP] */\n.theme-st { color: #8b5cf6; }",
            'corporate'   => "/* [SIG_CORPORATE] */\n.theme-cp { color: #2563eb; }",
            'real-estate' => "/* [SIG_REAL_ESTATE] */\n.theme-re { color: #059669; }",
        ];

        // 1. Save all 4 payloads
        foreach ($payloads as $slug => $css) {
            $res = $themeService->saveCustomCss($slug, $css);
            if (!$res) {
                throw new \AssertionError("Failed to saveCustomCss for {$slug}");
            }
        }

        // 2. Verify getCustomCss retrieves each distinct payload
        foreach ($payloads as $slug => $css) {
            $retrieved = $themeService->getCustomCss($slug);
            if ($retrieved !== $css) {
                throw new \AssertionError("getCustomCss('{$slug}') did not match saved payload");
            }
        }

        // 3. For each active theme, verify only its signature is rendered in header
        foreach ($payloads as $activeSlug => $expectedCss) {
            $themeService->setActive($activeSlug);
            $activeTheme = $themeService->getActive();
            if ($activeTheme->getSlug() !== $activeSlug) {
                throw new \AssertionError("Active theme did not switch to {$activeSlug}");
            }

            // Render frontend header
            $headerHtml = $themeService->renderTemplate('header', [
                'theme'     => $activeTheme,
                'siteTitle' => 'Isolation Test',
                'title'     => 'Home',
                'siteUrl'   => 'http://localhost:8000',
                'navPages'  => [],
            ]);

            if (empty($headerHtml)) {
                throw new \AssertionError("renderTemplate('header') returned empty for {$activeSlug}");
            }

            // Check that active theme's style tag exists
            if (!str_contains($headerHtml, '<style id="theme-custom-css">')) {
                throw new \AssertionError("Missing <style id=\"theme-custom-css\"> in {$activeSlug} header");
            }

            // Must contain current active theme's signature
            $expectedSig = "[SIG_" . strtoupper(str_replace('-', '_', $activeSlug)) . "]";
            if (!str_contains($headerHtml, $expectedSig)) {
                throw new \AssertionError("Header for {$activeSlug} missing expected signature {$expectedSig}");
            }

            // Must NOT contain signatures from any other theme!
            foreach ($payloads as $otherSlug => $otherCss) {
                if ($otherSlug === $activeSlug) continue;
                $otherSig = "[SIG_" . strtoupper(str_replace('-', '_', $otherSlug)) . "]";
                if (str_contains($headerHtml, $otherSig)) {
                    throw new \AssertionError("CROSS-THEME CONTAMINATION DETECTED: {$activeSlug} header contains {$otherSig} from {$otherSlug}!");
                }
            }
        }

        return "All 4 themes verified strictly isolated with zero cross-contamination";
    }, $passes, $failures);

    // =========================================================================
    // TEST GROUP 2: STORAGE MIRRORING & FALLBACK HEALING
    // =========================================================================
    echo "\n--- Group 2: Storage Mirroring & Fallback Healing ---\n";

    challengeAssert("Mirroring: storage/custom-css/{theme}.css and themes/{theme}/custom.css fidelity", function () use ($themeService) {
        $slug = 'corporate';
        $testCss = "/* Fidelity Mirror Test */\n#corp-nav { background: rgba(37, 99, 235, 0.95); }\n@media (min-width: 1024px) { .corp-grid { display: grid; } }";

        $themeService->saveCustomCss($slug, $testCss);

        $storageFile = HEARTBEAT_STORAGE . "/custom-css/{$slug}.css";
        $theme = $themeService->get($slug);
        $themeCustomFile = $theme->getPath() . '/custom.css';

        if (!file_exists($storageFile)) {
            throw new \AssertionError("Primary storage file {$storageFile} does not exist");
        }
        if (file_get_contents($storageFile) !== $testCss) {
            throw new \AssertionError("Storage file content does not match test CSS");
        }

        if (!file_exists($themeCustomFile)) {
            throw new \AssertionError("Mirrored file {$themeCustomFile} does not exist");
        }
        if (file_get_contents($themeCustomFile) !== $testCss) {
            throw new \AssertionError("Mirrored file content does not match test CSS");
        }

        return "Storage and mirrored files reflect byte-for-byte fidelity";
    }, $passes, $failures);

    challengeAssert("Fallback Healing: getCustomCss falls back to theme directory when storage file deleted", function () use ($themeService) {
        $slug = 'startup';
        $testCss = "/* Startup Fallback Test */\n.startup-hero { backdrop-filter: blur(10px); }";

        $themeService->saveCustomCss($slug, $testCss);

        $storageFile = HEARTBEAT_STORAGE . "/custom-css/{$slug}.css";
        $theme = $themeService->get($slug);
        $themeCustomFile = $theme->getPath() . '/custom.css';

        // Intentionally delete storage file
        if (file_exists($storageFile)) {
            unlink($storageFile);
        }

        // Must still read from themeCustomFile
        $retrieved = $themeService->getCustomCss($slug);
        if ($retrieved !== $testCss) {
            throw new \AssertionError("Failed to fall back to theme/custom.css when storage file missing");
        }

        // Re-saving should heal the storage file
        $healedCss = $testCss . "\n/* Healed */";
        $themeService->saveCustomCss($slug, $healedCss);

        if (!file_exists($storageFile) || file_get_contents($storageFile) !== $healedCss) {
            throw new \AssertionError("Re-saving did not regenerate storage/custom-css/{$slug}.css");
        }

        return "Transparent fallback and healing verified successfully";
    }, $passes, $failures);

    challengeAssert("Aliases: 'default' vs 'one-pager' slug resolution parity", function () use ($themeService) {
        $testCss = "/* Aliases Parity Test */\n.alias-box { box-shadow: 0 0 10px #f97316; }";

        // Remove any old default.css to test alias fallback to one-pager.css
        $defaultStorageFile = HEARTBEAT_STORAGE . "/custom-css/default.css";
        if (file_exists($defaultStorageFile)) {
            unlink($defaultStorageFile);
        }

        $themeService->saveCustomCss('one-pager', $testCss);

        $fromOnePager = $themeService->getCustomCss('one-pager');
        $fromDefault = $themeService->getCustomCss('default');

        if ($fromOnePager !== $testCss) {
            throw new \AssertionError("getCustomCss('one-pager') failed to return saved CSS");
        }
        if ($fromDefault !== $testCss) {
            throw new \AssertionError("getCustomCss('default') failed to resolve to one-pager custom CSS (got: '{$fromDefault}')");
        }

        return "Slug aliases 'default' and 'one-pager' resolve to identical custom CSS";
    }, $passes, $failures);

    // =========================================================================
    // TEST GROUP 3: ALPINE.JS UI MECHANICS IN ThemeController::customize
    // =========================================================================
    echo "\n--- Group 3: Alpine.js HTML Attributes & Event Handlers in ThemeController ---\n";

    challengeAssert("Alpine UI: Monospace class on editor and gutter", function () use ($themeController, $themeService) {
        $request = new Request('GET', '/admin/themes/customize?theme=one-pager', ['theme' => 'one-pager']);
        $response = $themeController->customize($request);
        $html = $response->getBody();

        if (!str_contains($html, 'font-mono')) {
            throw new \AssertionError("Missing 'font-mono' class in ThemeController customize HTML");
        }

        return "font-mono present in customizer markup";
    }, $passes, $failures);

    challengeAssert("Adversarial Defect Check: DOM attribute x-data HTML quote integrity", function () use ($themeController) {
        $request = new Request('GET', '/admin/themes/customize?theme=corporate', ['theme' => 'corporate']);
        $response = $themeController->customize($request);
        $html = $response->getBody();

        // Parse HTML using DOMDocument
        $doc = new \DOMDocument();
        @$doc->loadHTML($html);
        $xpath = new \DOMXPath($doc);
        $nodes = $xpath->query('//div[starts-with(@x-data, "themeStudio")]');

        if ($nodes->length === 0) {
            throw new \AssertionError("No element with x-data starting with themeStudio found");
        }

        $xdataAttr = $nodes->item(0)->getAttribute('x-data');

        // If the HTML attribute quotes collided, x-data attribute value is prematurely truncated to 'themeStudio({'
        if ($xdataAttr === 'themeStudio({' || !str_ends_with(trim($xdataAttr), ')') && !str_ends_with(trim($xdataAttr), '})')) {
            throw new \AssertionError("DOM attribute x-data prematurely truncated to '{$xdataAttr}' due to unescaped double quotes inside x-data=\"themeStudio({\$themeConfigJson})\"");
        }

        return "x-data attribute successfully parsed by DOM parser without premature closure";
    }, $passes, $failures);

    challengeAssert("Adversarial Defect Check: Alpine x-for line count expression JS syntax validity", function () use ($themeController) {
        $request = new Request('GET', '/admin/themes/customize?theme=one-pager', ['theme' => 'one-pager']);
        $response = $themeController->customize($request);
        $html = $response->getBody();

        // Extract the x-for attribute
        if (!preg_match('/x-for="([^"]+)"/', $html, $matches)) {
            throw new \AssertionError("Could not find x-for attribute in customizer HTML");
        }

        $xforExpr = $matches[1];

        // The expression is: line in (config.custom_css ? config.custom_css.split('\n').length : 1)
        // If split('\n') has an unescaped literal newline byte, JavaScript engines throw SyntaxError
        if (preg_match('/split\(\'\s*\n\s*\'\)/', $xforExpr) || str_contains($xforExpr, "split('\n')")) {
            throw new \AssertionError("x-for expression contains raw unescaped newline byte inside split('...') string literal: '{$xforExpr}'. This causes JS SyntaxError in browser");
        }

        return "x-for line count expression contains valid JS escape sequences";
    }, $passes, $failures);

    challengeAssert("Alpine UI: Scroll sync handler, tab indentation, and textarea attributes", function () use ($themeController) {
        $request = new Request('GET', '/admin/themes/customize?theme=one-pager', ['theme' => 'one-pager']);
        $response = $themeController->customize($request);
        $html = $response->getBody();

        // Textarea attributes
        if (!str_contains($html, 'id="customCssEditor"')) {
            throw new \AssertionError("Textarea missing id=\"customCssEditor\"");
        }
        if (!str_contains($html, 'name="custom_css"')) {
            throw new \AssertionError("Textarea missing name=\"custom_css\"");
        }
        if (!str_contains($html, 'x-model="config.custom_css"')) {
            throw new \AssertionError("Textarea missing x-model=\"config.custom_css\"");
        }
        if (!str_contains($html, '@input="updatePreviewCss()"')) {
            throw new \AssertionError("Textarea missing @input=\"updatePreviewCss()\"");
        }

        // Scroll sync: must be literally syncScroll($event), not empty or evaluated php var
        if (!str_contains($html, '@scroll="syncScroll($event)"')) {
            throw new \AssertionError("Textarea missing @scroll=\"syncScroll(\$event)\"");
        }

        // Tab indentation: must be literally handleTab($event)
        if (!str_contains($html, '@keydown.tab.prevent="handleTab($event)"')) {
            throw new \AssertionError("Textarea missing @keydown.tab.prevent=\"handleTab(\$event)\"");
        }

        // Verify JS function syncScroll(e) exists and updates gutter
        if (!str_contains($html, 'syncScroll(e)') || !str_contains($html, 'gutter.scrollTop = e.target.scrollTop')) {
            throw new \AssertionError("Missing syncScroll(e) implementation updating cssLineNumbers scrollTop");
        }

        // Verify JS function handleTab(e) exists, inserts 2 spaces, and maintains cursor position
        if (!str_contains($html, 'handleTab(e)')) {
            throw new \AssertionError("Missing handleTab(e) implementation");
        }
        if (!str_contains($html, '"  "') || !str_contains($html, 'start + 2')) {
            throw new \AssertionError("handleTab(e) does not implement 2-space indentation with start + 2 offset");
        }

        return "Scroll sync and 2-space tab indentation handlers verified in HTML and JS";
    }, $passes, $failures);

    challengeAssert("Alpine UI: Live Preview DOM injection logic (updatePreviewCss)", function () use ($themeController) {
        $request = new Request('GET', '/admin/themes/customize?theme=corporate', ['theme' => 'corporate']);
        $response = $themeController->customize($request);
        $html = $response->getBody();

        // Iframe must exist with @load hook
        if (!str_contains($html, 'id="themePreviewFrame"')) {
            throw new \AssertionError("Missing iframe id=\"themePreviewFrame\"");
        }
        if (!str_contains($html, '@load="updatePreview(); updatePreviewCss()"')) {
            throw new \AssertionError("Iframe missing @load handler invoking updatePreviewCss()");
        }

        // Check JS implementation of updatePreviewCss
        if (!str_contains($html, 'updatePreviewCss()')) {
            throw new \AssertionError("Missing updatePreviewCss() definition");
        }
        if (!str_contains($html, "doc.getElementById('live-custom-css')") && !str_contains($html, 'doc.getElementById("live-custom-css")')) {
            throw new \AssertionError("updatePreviewCss does not target 'live-custom-css'");
        }
        if (!str_contains($html, 'style.textContent = this.config.custom_css')) {
            throw new \AssertionError("updatePreviewCss does not bind style.textContent to this.config.custom_css");
        }

        // Check save button
        if (!str_contains($html, 'Änderungen speichern')) {
            throw new \AssertionError("Missing 'Änderungen speichern' submit button");
        }

        return "DOM injection mechanics targeting #live-custom-css and 'Änderungen speichern' button verified";
    }, $passes, $failures);

    // =========================================================================
    // TEST GROUP 4: END-TO-END ThemeController::save() AND FRONTEND REFLECTION
    // =========================================================================
    echo "\n--- Group 4: End-to-End ThemeController::save() & Frontend Reflection ---\n";

    challengeAssert("End-to-End: ThemeController::save() persists and updates active theme frontend immediately", function () use ($themeController, $themeService) {
        $slug = 'startup';
        $themeService->setActive($slug);

        $savedCss = "/* E2E Controller Save Test */\n.btn-e2e-hero { padding: 18px 36px; border-radius: 9999px; }";
        $csrfToken = Csrf::token();

        $postData = [
            '_csrf_token' => $csrfToken,
            'theme_slug'  => $slug,
            'custom_css'  => $savedCss,
            'colors'      => ['primary' => '#8b5cf6'],
            'typography'  => ['font_family' => 'Inter'],
            'settings'    => ['container_width' => 'max-w-6xl'],
        ];

        $request = new Request('POST', '/admin/themes/save', [], $postData);
        $response = $themeController->save($request);

        // 1. Must redirect back to customize view
        if ($response->getStatusCode() !== 302) {
            throw new \AssertionError("Expected 302 Redirect from save(), got HTTP {$response->getStatusCode()}");
        }
        $location = $response->getHeader('Location');
        if (!str_contains($location, "/admin/themes/customize?theme={$slug}")) {
            throw new \AssertionError("Expected redirect to customize view for {$slug}, got {$location}");
        }

        // 2. Storage file must contain the newly saved CSS
        $storageFile = HEARTBEAT_STORAGE . "/custom-css/{$slug}.css";
        if (!file_exists($storageFile) || file_get_contents($storageFile) !== $savedCss) {
            throw new \AssertionError("storage/custom-css/{$slug}.css does not contain saved CSS");
        }

        // 3. Mirrored file must contain the newly saved CSS
        $theme = $themeService->get($slug);
        $themeCustomFile = $theme->getPath() . '/custom.css';
        if (!file_exists($themeCustomFile) || file_get_contents($themeCustomFile) !== $savedCss) {
            throw new \AssertionError("theme custom.css does not contain saved CSS");
        }

        // 4. Frontend header must immediately reflect the newly saved CSS
        $headerHtml = $themeService->renderTemplate('header', [
            'theme'     => $theme,
            'siteTitle' => 'E2E Reflection',
            'title'     => 'Home',
            'siteUrl'   => 'http://localhost:8000',
            'navPages'  => [],
        ]);

        if (!str_contains($headerHtml, '<style id="theme-custom-css">' . $savedCss . '</style>')) {
            throw new \AssertionError("Rendered frontend header does not immediately contain <style id=\"theme-custom-css\"> with saved CSS");
        }

        return "ThemeController::save() persisted to disk and immediately rendered on frontend";
    }, $passes, $failures);

    challengeAssert("Suppression: Empty custom CSS does not emit empty <style id=\"theme-custom-css\">", function () use ($themeController, $themeService) {
        $slug = 'corporate';
        $themeService->setActive($slug);
        $csrfToken = Csrf::token();

        $postData = [
            '_csrf_token' => $csrfToken,
            'theme_slug'  => $slug,
            'custom_css'  => '', // empty CSS
        ];

        $request = new Request('POST', '/admin/themes/save', [], $postData);
        $response = $themeController->save($request);

        if ($response->getStatusCode() !== 302) {
            throw new \AssertionError("Expected 302 redirect from save(), got {$response->getStatusCode()}");
        }

        $theme = $themeService->get($slug);
        $headerHtml = $themeService->renderTemplate('header', [
            'theme'     => $theme,
            'siteTitle' => 'Empty Test',
            'title'     => 'Home',
            'siteUrl'   => 'http://localhost:8000',
            'navPages'  => [],
        ]);

        if (str_contains($headerHtml, 'id="theme-custom-css"')) {
            throw new \AssertionError("Empty custom CSS should not output <style id=\"theme-custom-css\">, but tag was found");
        }

        return "Empty custom CSS cleanly suppressed on frontend";
    }, $passes, $failures);

    // =========================================================================
    // TEST GROUP 5: ADVERSARIAL STRESS & SECURITY HARNESS
    // =========================================================================
    echo "\n--- Group 5: Adversarial Stress & Security Hardening ---\n";

    challengeAssert("Security: XSS style breakout attempts in custom CSS are safely encoded in JSON payload", function () use ($themeController, $themeService) {
        $xssCss = "</style><script>alert('pwned')</script>";
        $themeService->saveCustomCss('corporate', $xssCss);

        $request = new Request('GET', '/admin/themes/customize?theme=corporate', ['theme' => 'corporate']);
        $response = $themeController->customize($request);
        $html = $response->getBody();

        // Must NOT contain literal unescaped </style><script> inside HTML attributes
        if (str_contains($html, '</style><script>')) {
            throw new \AssertionError("Unescaped HTML tag breakout detected in Customizer HTML!");
        }

        // Verify JSON_HEX_TAG encoding (\u003C)
        if (!str_contains($html, '\u003C\/style\u003E\u003Cscript\u003E')) {
            throw new \AssertionError("Expected JSON_HEX_TAG encoding (\u003C) for style breakout attempt");
        }

        return "Style tag breakout attempt safely neutralized via JSON_HEX_TAG in payload";
    }, $passes, $failures);

    challengeAssert("Security: Path traversal attempts in theme slug are strictly sanitized", function () use ($themeService) {
        $maliciousSlug = '../../../../etc/passwd';
        $css = "/* Traversal attempt */";

        $themeService->saveCustomCss($maliciousSlug, $css);

        // Verify no file created outside storage/custom-css
        $traversedFile = HEARTBEAT_ROOT . '/etc/passwd.css';
        if (file_exists($traversedFile)) {
            unlink($traversedFile);
            throw new \AssertionError("Path traversal succeeded! File written outside storage");
        }

        // Verify it was sanitized to alphanumeric/dash/underscore
        $sanitizedSlug = preg_replace('/[^a-zA-Z0-9_-]/', '', $maliciousSlug);
        $expectedFile = HEARTBEAT_STORAGE . "/custom-css/{$sanitizedSlug}.css";
        if (!file_exists($expectedFile)) {
            throw new \AssertionError("Sanitized file {$expectedFile} was not found");
        }
        @unlink($expectedFile);

        return "Path traversal neutralized; saved to sanitized path only";
    }, $passes, $failures);

    challengeAssert("Stress: High line-count CSS (500 lines) preserves integrity across roundtrips", function () use ($themeService, $themeController) {
        $lines = [];
        for ($i = 1; $i <= 500; $i++) {
            $lines[] = ".class-line-{$i} { margin: {$i}px; content: 'Line {$i} /* test */'; }";
        }
        $largeCss = implode("\n", $lines);

        $themeService->saveCustomCss('real-estate', $largeCss);

        $retrieved = $themeService->getCustomCss('real-estate');
        if ($retrieved !== $largeCss) {
            throw new \AssertionError("500-line CSS roundtrip failed in getCustomCss");
        }

        // Verify line count in customize HTML
        $request = new Request('GET', '/admin/themes/customize?theme=real-estate', ['theme' => 'real-estate']);
        $response = $themeController->customize($request);
        $html = $response->getBody();

        if (!str_contains($html, '.class-line-500')) {
            throw new \AssertionError("500th line not found in customize view JSON payload");
        }

        return "500 lines of CSS roundtripped with 100% fidelity";
    }, $passes, $failures);

} finally {
    // Restore original custom CSS files
    foreach ($originalStorageCss as $tSlug => $content) {
        $sFile = HEARTBEAT_STORAGE . "/custom-css/{$tSlug}.css";
        @file_put_contents($sFile, $content);
    }
    foreach ($originalThemeCss as $tSlug => $content) {
        $tObj = $themeService->get($tSlug);
        if ($tObj) {
            @file_put_contents($tObj->getPath() . '/custom.css', $content);
        }
    }

    // Clean up test DB
    if (file_exists($testDbFile)) @unlink($testDbFile);
    if (file_exists($testDbFile . '-wal')) @unlink($testDbFile . '-wal');
    if (file_exists($testDbFile . '-shm')) @unlink($testDbFile . '-shm');
}

echo "\n======================================================================\n";
echo "SUMMARY: " . count($passes) . " PASSED, " . count($failures) . " FAILED\n";
echo "======================================================================\n";

if (!empty($failures)) {
    echo "FAILURES:\n";
    foreach ($failures as $f) {
        echo "  - {$f}\n";
    }
    exit(1);
} else {
    echo "🎉 ALL ADVERSARIAL CHALLENGES PASSED EMPIRICALLY!\n";
    exit(0);
}
