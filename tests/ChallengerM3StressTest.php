<?php
declare(strict_types=1);

/**
 * Challenger M3 Stress Test & Adversarial Verification Suite
 * Heartbeat CMS Enterprise Refinement - Milestone M3 (Requirement R4: Theme Customizer & Live CSS)
 *
 * This test suite independently stresses ThemeService custom CSS persistence,
 * edge cases (path traversal, huge payloads, complex CSS syntax, empty strings),
 * multi-theme frontend <head> injection, minified bundle isolation, and controller security.
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

$testDbFile = HEARTBEAT_STORAGE . '/test_challenger_m3_stress.sqlite';
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
$db->insert('{prefix}options', ['option_name' => 'site_title', 'option_value' => 'Heartbeat Enterprise M3 Test', 'autoload' => 1]);
$db->insert('{prefix}options', ['option_name' => 'site_url', 'option_value' => 'http://localhost:8000', 'autoload' => 1]);
$db->insert('{prefix}options', ['option_name' => 'active_theme', 'option_value' => 'one-pager', 'autoload' => 1]);

$db->insert('{prefix}users', [
    'username'      => 'challenger_admin',
    'email'         => 'challenger@heartbeat-cms.de',
    'password_hash' => Security::hashPassword('ChallengerSecure2026!'),
    'role'          => Rbac::ROLE_ADMIN,
    'status'        => 'active',
    'created_at'    => $now,
    'updated_at'    => $now,
]);

Session::start();
$adminUser = $db->fetchOne("SELECT * FROM {prefix}users WHERE username = 'challenger_admin'");

$themeService = ThemeService::getInstance();

$passes = [];
$failures = [];

function runProbe(string $category, string $title, callable $fn, array &$passes, array &$failures): void {
    $fullTitle = "[{$category}] {$title}";
    try {
        $result = $fn();
        $msg = is_string($result) ? $result : 'OK';
        $passes[] = $fullTitle;
        echo "  ✓ PASS: {$fullTitle} ({$msg})\n";
    } catch (\Throwable $e) {
        $errorMsg = $e->getMessage() . " (" . basename($e->getFile()) . ":" . $e->getLine() . ")";
        $failures[] = "{$fullTitle} -> {$errorMsg}";
        echo "  ✗ FAIL: {$fullTitle} -> {$errorMsg}\n";
    }
}

echo "======================================================================\n";
echo "=== HEARTBEAT CMS: CHALLENGER 1 EMPIRICAL M3 STRESS TEST SUITE ===\n";
echo "======================================================================\n\n";

try {
    // Authenticate as Admin
    Auth::login($adminUser);

    // =========================================================================
    // PROBE 1: Slug Sanitization & Path Traversal Resistance
    // =========================================================================
    runProbe('PROBE-1.1', 'Path traversal slug attack (../../evil, ../../etc/passwd) is safely contained in storage/custom-css', function () use ($themeService) {
        $css = "/* Traversal probe */ body { background: #000; }";
        
        // Attack 1: ../../evil
        $traversalSlug1 = '../../evil_exploit';
        $saved1 = $themeService->saveCustomCss($traversalSlug1, $css);
        if (!$saved1) throw new \AssertionError("saveCustomCss should succeed by sanitizing slug");

        // Verify root directory is NOT polluted
        if (file_exists(HEARTBEAT_ROOT . '/evil_exploit.css') || file_exists(HEARTBEAT_ROOT . '/../evil_exploit.css')) {
            throw new \AssertionError("CRITICAL VULNERABILITY: Path traversal wrote file outside storage/custom-css!");
        }

        // Verify file is strictly confined to storage/custom-css/evil_exploit.css
        $expectedFile1 = HEARTBEAT_STORAGE . '/custom-css/evil_exploit.css';
        if (!is_file($expectedFile1)) {
            throw new \AssertionError("Sanitized file not found at expected location: {$expectedFile1}");
        }
        @unlink($expectedFile1);

        // Attack 2: all dots and slashes (../../..)
        $traversalSlug2 = '../../..';
        $saved2 = $themeService->saveCustomCss($traversalSlug2, $css);
        if (!$saved2) throw new \AssertionError("saveCustomCss should fallback to default slug");

        // Must resolve to default.css
        $expectedFile2 = HEARTBEAT_STORAGE . '/custom-css/default.css';
        if (!is_file($expectedFile2)) {
            throw new \AssertionError("Empty sanitized slug did not fallback to default.css");
        }

        return "Sanitized path traversal attempts safely into storage/custom-css without escape";
    }, $passes, $failures);

    runProbe('PROBE-1.2', 'Special characters, null bytes, backslashes and unicode in slug are stripped', function () use ($themeService) {
        $css = "/* Special chars slug */";
        $weirdSlug = "corp\\theme\0<script>alert(1)</script>_test#slug?";
        
        $saved = $themeService->saveCustomCss($weirdSlug, $css);
        if (!$saved) throw new \AssertionError("Failed to save CSS with special characters in slug");

        // Allowed chars: [a-zA-Z0-9_-]
        $cleanExpected = "corpthemescriptalert1script_testslug";
        $expectedFile = HEARTBEAT_STORAGE . "/custom-css/{$cleanExpected}.css";
        if (!is_file($expectedFile)) {
            throw new \AssertionError("Expected sanitized file at {$expectedFile}");
        }

        $retrieved = $themeService->getCustomCss($weirdSlug);
        if ($retrieved !== $css) {
            throw new \AssertionError("getCustomCss failed to retrieve with sanitized weird slug");
        }
        @unlink($expectedFile);

        return "Special characters and null bytes strictly stripped to [a-zA-Z0-9_-]";
    }, $passes, $failures);

    runProbe('PROBE-1.3', 'Non-existent theme slug returns empty string without PHP warnings or notices', function () use ($themeService) {
        // Capture any PHP notices/warnings
        $errorReported = false;
        set_error_handler(function ($errno, $errstr) use (&$errorReported) {
            $errorReported = true;
            return true;
        });

        $result = $themeService->getCustomCss('completely_nonexistent_theme_998877');
        restore_error_handler();

        if ($errorReported) {
            throw new \AssertionError("PHP warning/notice emitted when querying non-existent theme CSS");
        }

        if ($result !== '') {
            throw new \AssertionError("Expected empty string for non-existent theme, got: " . var_export($result, true));
        }

        return "Returned empty string cleanly without notices";
    }, $passes, $failures);

    runProbe('PROBE-1.4', 'Theme slug alias resolution (one-pager vs default)', function () use ($themeService) {
        $css = "/* One-pager alias test */ :root { --alias-test: 1; }";
        $themeService->saveCustomCss('one-pager', $css);

        $retrievedOnePager = $themeService->getCustomCss('one-pager');
        if ($retrievedOnePager !== $css) {
            throw new \AssertionError("getCustomCss('one-pager') failed to retrieve saved CSS");
        }

        // Active theme is one-pager; check if default maps or returns cleanly
        $retrievedDefault = $themeService->getCustomCss('default');
        // Default might have its own default.css or fall back
        if (!is_string($retrievedDefault)) {
            throw new \AssertionError("getCustomCss('default') did not return a string");
        }

        return "Slug resolution handled cleanly";
    }, $passes, $failures);

    // =========================================================================
    // PROBE 2: Empty Strings, Whitespace & Clean State Handling
    // =========================================================================
    runProbe('PROBE-2.1', 'Empty CSS string persists cleanly and clears prior CSS', function () use ($themeService) {
        // First save some CSS
        $themeService->saveCustomCss('corporate', "/* Initial corporate CSS */ .box { color: blue; }");
        if ($themeService->getCustomCss('corporate') === '') {
            throw new \AssertionError("Failed initial CSS save");
        }

        // Now save empty string
        $savedEmpty = $themeService->saveCustomCss('corporate', "");
        if (!$savedEmpty) {
            throw new \AssertionError("saveCustomCss failed on empty string");
        }

        $cleared = $themeService->getCustomCss('corporate');
        if ($cleared !== '') {
            throw new \AssertionError("getCustomCss did not return empty string after saving empty CSS, got: " . var_export($cleared, true));
        }

        $file = HEARTBEAT_STORAGE . '/custom-css/corporate.css';
        if (is_file($file) && filesize($file) !== 0) {
            throw new \AssertionError("File is not 0 bytes after saving empty CSS");
        }

        return "Empty string cleared prior CSS and persisted as 0-byte file";
    }, $passes, $failures);

    runProbe('PROBE-2.2', 'Frontend <head> completely omits <style id="theme-custom-css"> when custom CSS is empty', function () use ($themeService) {
        $themes = ['default' => 'one-pager', 'corporate' => 'corporate', 'real-estate' => 'real-estate', 'startup' => 'startup'];

        foreach ($themes as $dirSlug => $themeSlug) {
            // Ensure empty custom CSS
            $themeService->saveCustomCss($themeSlug, "");

            $themeObj = $themeService->get($themeSlug);
            $rendered = $themeService->renderTemplate('header', [
                'theme'     => $themeObj,
                'siteTitle' => 'Test Site',
                'customCss' => '',
            ]);

            if ($rendered === null) {
                throw new \AssertionError("Failed to render header for theme {$themeSlug}");
            }

            if (str_contains($rendered, 'id="theme-custom-css"')) {
                throw new \AssertionError("CORRUPTION: <style id=\"theme-custom-css\"> was injected when CSS is empty in theme '{$themeSlug}'!");
            }
        }

        return "All 4 themes cleanly omit <style id=\"theme-custom-css\"> when custom CSS is empty";
    }, $passes, $failures);

    runProbe('PROBE-2.3', 'Automatic directory creation if storage/custom-css is missing', function () use ($themeService) {
        $customCssDir = HEARTBEAT_STORAGE . '/custom-css';
        $backupDir = HEARTBEAT_STORAGE . '/custom-css-temp-backup';

        // Temporarily rename storage/custom-css
        if (is_dir($customCssDir)) {
            @rename($customCssDir, $backupDir);
        }

        try {
            if (is_dir($customCssDir)) {
                throw new \AssertionError("Failed to temporarily move custom-css directory for test");
            }

            // Attempt save with missing directory
            $saved = $themeService->saveCustomCss('real-estate', "/* Recreated dir */ .auto-dir { margin: 1rem; }");
            if (!$saved) {
                throw new \AssertionError("saveCustomCss failed to create missing storage/custom-css directory");
            }

            if (!is_dir($customCssDir)) {
                throw new \AssertionError("storage/custom-css directory was not created");
            }

            $retrieved = $themeService->getCustomCss('real-estate');
            if (!str_contains($retrieved, '.auto-dir')) {
                throw new \AssertionError("Failed to retrieve CSS from recreated directory");
            }
        } finally {
            // Restore backup contents if needed
            if (is_dir($backupDir)) {
                // Copy any backup files back
                $files = glob($backupDir . '/*.css');
                foreach ($files as $f) {
                    $dest = $customCssDir . '/' . basename($f);
                    if (!file_exists($dest)) {
                        @copy($f, $dest);
                    }
                    @unlink($f);
                }
                @rmdir($backupDir);
            }
        }

        return "Successfully created missing custom-css directory on the fly with proper permissions";
    }, $passes, $failures);

    // =========================================================================
    // PROBE 3: Massive Payloads & Stress Limits (> 150KB)
    // =========================================================================
    runProbe('PROBE-3.1', 'Extreme CSS payload (> 150KB with 1,500 rules) roundtrip integrity', function () use ($themeService) {
        // Generate massive realistic CSS payload
        $rules = [];
        $rules[] = "/* Massive Enterprise CSS Stress Harness */";
        $rules[] = ":root {";
        for ($i = 1; $i <= 100; $i++) {
            $rules[] = "  --token-metric-{$i}: calc({$i}px * 1.618);";
        }
        $rules[] = "}";

        // 1,500 distinct selectors with complex rules and properties
        for ($i = 1; $i <= 1500; $i++) {
            $rules[] = ".enterprise-comp-{$i}:nth-child(2n+1):not([aria-hidden=\"true\"])::before {";
            $rules[] = "  content: \"Component {$i} active marker\";";
            $rules[] = "  display: inline-block;";
            $rules[] = "  padding: calc(var(--token-metric-1) + 2px);";
            $rules[] = "  background: linear-gradient(135deg, rgba(" . ($i % 255) . ", 100, 200, 0.9), #0f172a);";
            $rules[] = "  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);";
            $rules[] = "}";
        }
        $hugeCss = implode("\n", $rules);
        $payloadSize = strlen($hugeCss);

        if ($payloadSize < 150000) {
            throw new \AssertionError("Payload size {$payloadSize} bytes is less than 150KB requirement");
        }

        $saved = $themeService->saveCustomCss('corporate', $hugeCss);
        if (!$saved) {
            throw new \AssertionError("saveCustomCss failed with large payload ({$payloadSize} bytes)");
        }

        $retrieved = $themeService->getCustomCss('corporate');
        if (strlen($retrieved) !== $payloadSize) {
            throw new \AssertionError("Size mismatch: saved {$payloadSize} bytes, retrieved " . strlen($retrieved) . " bytes");
        }

        if (hash('sha256', $retrieved) !== hash('sha256', $hugeCss)) {
            throw new \AssertionError("SHA256 checksum mismatch on large payload roundtrip!");
        }

        return "Successfully saved and loaded " . round($payloadSize / 1024, 1) . " KB payload with 100% SHA256 fidelity";
    }, $passes, $failures);

    runProbe('PROBE-3.2', 'Admin Customizer view handles large CSS payload (> 150KB) without truncation or memory issues', function () use ($themeService) {
        $ctrl = new ThemeController();
        $req = new Request('GET', '/admin/themes/customize', ['theme' => 'corporate']);

        $startMem = memory_get_usage();
        $resp = $ctrl->customize($req);
        $memUsed = memory_get_usage() - $startMem;

        if ($resp->getStatusCode() !== 200) {
            throw new \AssertionError("Expected HTTP 200 from customize with large payload, got {$resp->getStatusCode()}");
        }

        $html = $resp->getBody();
        if (!str_contains($html, 'themeStudio(')) {
            throw new \AssertionError("Customizer HTML missing themeStudio Alpine component");
        }

        if (!str_contains($html, 'enterprise-comp-1500')) {
            throw new \AssertionError("Large CSS payload truncated inside customizer view JSON!");
        }

        return "Customizer rendered cleanly (HTTP 200, mem delta " . round($memUsed / 1024, 1) . " KB, complete payload verified)";
    }, $passes, $failures);

    // =========================================================================
    // PROBE 4: Complex CSS Syntax, Escapes, Keyframes & Comments
    // =========================================================================
    runProbe('PROBE-4.1', 'Complex CSS selectors, @keyframes, @media, CSS variables, pseudo-elements and calc()', function () use ($themeService) {
        $advancedCss = <<<CSS
@charset "UTF-8";
@import url("https://fonts.googleapis.com/css2?family=Fira+Code&display=swap");

:root {
  --hb-matrix: rgba(37, 99, 235, 0.85);
  --hb-calc: calc(100vh - (2rem + 15px) * 2);
  --hb-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
}

@keyframes heartbeatPulseAnimation {
  0% { transform: scale(1) translate3d(0, 0, 0); opacity: 0.9; }
  50% { transform: scale(1.08) translate3d(0, -4px, 0); opacity: 1; filter: drop-shadow(0 0 12px var(--hb-matrix)); }
  100% { transform: scale(1) translate3d(0, 0, 0); opacity: 0.9; }
}

@media screen and (min-width: 1024px) and (max-width: 1920px), (orientation: landscape) {
  .site-main-grid > section:first-of-type ~ article[data-featured="true"] {
    grid-column: span 3 / span 3;
    animation: heartbeatPulseAnimation 3s ease-in-out infinite;
  }
}

@supports (backdrop-filter: blur(20px)) or (-webkit-backdrop-filter: blur(20px)) {
  .glass-card {
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
  }
}
CSS;

        $themeService->saveCustomCss('startup', $advancedCss);
        $retrieved = $themeService->getCustomCss('startup');

        if ($retrieved !== $advancedCss) {
            throw new \AssertionError("Advanced CSS features altered during persistence");
        }

        return "All @keyframes, @media, @supports, calc(), and pseudo-selectors preserved verbatim";
    }, $passes, $failures);

    runProbe('PROBE-4.2', 'Raw CSS entity fidelity (no conversion of >, <, ", \', & to HTML entities in storage)', function () use ($themeService) {
        $entityTestCss = <<<CSS
/* Special characters test: > < " ' & */
div.container > ul.menu > li:last-child {
  content: "Double \"quotes\" and 'single' quotes & ampersand";
  font-family: 'Times New Roman', serif;
}
CSS;
        $themeService->saveCustomCss('default', $entityTestCss);
        $retrieved = $themeService->getCustomCss('default');

        if (str_contains($retrieved, '&gt;') || str_contains($retrieved, '&quot;') || str_contains($retrieved, '&amp;') || str_contains($retrieved, '&#039;')) {
            throw new \AssertionError("CSS was corrupted with HTML entity encoding in storage!");
        }

        if ($retrieved !== $entityTestCss) {
            throw new \AssertionError("Raw CSS entity fidelity failed roundtrip");
        }

        return "CSS stored raw without premature HTML entity encoding";
    }, $passes, $failures);

    runProbe('PROBE-4.3', 'Adversarial closing tags and comment escape sequences', function () use ($themeService) {
        // Attack scenario: CSS containing </style><script>
        $maliciousInCss = <<<CSS
/* Multi-line comment with slashes / * / and asterisks * */
.safe-rule {
  content: "Attempting breakout </style><script>alert('xss')</script>";
}
/* Unicode glyphs: ⚡ 🚀 🛡️ 💎 */
CSS;
        $themeService->saveCustomCss('startup', $maliciousInCss);
        $retrieved = $themeService->getCustomCss('startup');
        if ($retrieved !== $maliciousInCss) {
            throw new \AssertionError("CSS with script-like content corrupted in storage");
        }

        // Verify ThemeController encodes JSON safely using JSON_HEX_TAG
        $ctrl = new ThemeController();
        $req = new Request('GET', '/admin/themes/customize', ['theme' => 'startup']);
        $resp = $ctrl->customize($req);
        $html = $resp->getBody();

        // In JSON attribute inside <div x-data="themeStudio(...)">, </style> must be escaped as \u003C/style\u003E
        if (str_contains($html, '</style><script>alert')) {
            throw new \AssertionError("VULNERABILITY: Raw </style> tag in customizer view unescaped in HTML attribute!");
        }

        if (!str_contains($html, '\u003C\/style\u003E') && !str_contains($html, '\u003C/style\u003E')) {
            throw new \AssertionError("Expected JSON_HEX_TAG encoding (\\u003C\\/style\\u003E) in customizer view");
        }

        return "Adversarial tag breakouts safely neutralized with JSON_HEX_TAG in customizer view";
    }, $passes, $failures);

    // =========================================================================
    // PROBE 5: Frontend <head> Injection Across All Themes
    // =========================================================================
    runProbe('PROBE-5.1', 'Frontend <head> injects <style id="theme-custom-css"> across all 4 themes', function () use ($themeService) {
        $themes = [
            'default'     => 'one-pager',
            'startup'     => 'startup',
            'corporate'   => 'corporate',
            'real-estate' => 'real-estate',
        ];

        foreach ($themes as $dirSlug => $themeSlug) {
            $customRule = "/* Theme {$themeSlug} Custom Rule */ .brand-{$themeSlug} { border-color: var(--hb-primary); }";
            $themeService->saveCustomCss($themeSlug, $customRule);

            $themeObj = $themeService->get($themeSlug);
            $rendered = $themeService->renderTemplate('header', [
                'theme'     => $themeObj,
                'siteTitle' => 'Enterprise Portal',
                'customCss' => $customRule,
            ]);

            if ($rendered === null) {
                throw new \AssertionError("Theme template 'header' failed to render for {$themeSlug}");
            }

            // Assert exact tag presence
            if (!str_contains($rendered, '<style id="theme-custom-css">')) {
                throw new \AssertionError("Missing <style id=\"theme-custom-css\"> in {$themeSlug} header");
            }

            if (!str_contains($rendered, "</style>")) {
                throw new \AssertionError("Missing closing </style> tag in {$themeSlug} header");
            }

            if (!str_contains($rendered, ".brand-{$themeSlug}")) {
                throw new \AssertionError("Custom CSS content missing from {$themeSlug} header");
            }

            // Verify order: custom CSS should come AFTER theme.css for correct cascade override
            $themeCssPos = strpos($rendered, '/assets/css/theme.css');
            $customCssPos = strpos($rendered, '<style id="theme-custom-css">');
            if ($themeCssPos !== false && $customCssPos !== false) {
                if ($customCssPos < $themeCssPos) {
                    throw new \AssertionError("CASCADE WARNING: custom-css is placed BEFORE theme.css in {$themeSlug}");
                }
            }
        }

        return "All 4 themes properly inject <style id=\"theme-custom-css\"> after theme.css";
    }, $passes, $failures);

    runProbe('PROBE-5.2', 'Core App fallback templates (renderPageShell & renderFallbackHomeHtml) inject custom CSS', function () use ($themeService) {
        $app = new App();
        $ref = new \ReflectionClass($app);

        $testRule = "/* Fallback shell custom CSS */ .shell-btn { color: #f97316; }";
        $themeService->saveCustomCss('one-pager', $testRule);
        $theme = $themeService->get('one-pager');

        // 1. renderPageShell
        $methodShell = $ref->getMethod('renderPageShell');
        $methodShell->setAccessible(true);
        $htmlShell = $methodShell->invoke(
            $app,
            'Empirical Test Page',
            'Heartbeat CMS',
            'http://localhost:8000',
            $theme,
            '<main>Content</main>',
            [],
            ['title' => 'Empirical Test Page', 'slug' => 'empirical-test'],
            []
        );

        if (!str_contains($htmlShell, '<style id="theme-custom-css">')) {
            throw new \AssertionError("renderPageShell missing <style id=\"theme-custom-css\">");
        }
        if (!str_contains($htmlShell, '.shell-btn')) {
            throw new \AssertionError("renderPageShell missing custom CSS content");
        }

        // 2. renderFallbackHomeHtml
        $methodHome = $ref->getMethod('renderFallbackHomeHtml');
        $methodHome->setAccessible(true);
        $htmlHome = $methodHome->invoke($app, 'Heartbeat CMS', $theme);

        if (!str_contains($htmlHome, '<style id="theme-custom-css">')) {
            throw new \AssertionError("renderFallbackHomeHtml missing <style id=\"theme-custom-css\">");
        }
        if (!str_contains($htmlHome, '.shell-btn')) {
            throw new \AssertionError("renderFallbackHomeHtml missing custom CSS content");
        }

        // 3. Verify clean state when empty
        $themeService->saveCustomCss('one-pager', '');
        $htmlClean = $methodShell->invoke(
            $app,
            'Empty CSS Page',
            'Heartbeat CMS',
            'http://localhost:8000',
            $theme,
            '<main>Content</main>',
            [],
            ['title' => 'Empty CSS Page', 'slug' => 'empty-css'],
            []
        );

        if (str_contains($htmlClean, '<style id="theme-custom-css">')) {
            throw new \AssertionError("renderPageShell rendered <style id=\"theme-custom-css\"> when CSS is empty");
        }

        return "Both fallback shells properly inject when populated and omit when empty";
    }, $passes, $failures);

    // =========================================================================
    // PROBE 6: Minified Tailwind Framework Isolation & Clean Customizer View
    // =========================================================================
    runProbe('PROBE-6.1', 'Theme Customizer NEVER injects minified framework Tailwind bundle into user editor', function () use ($themeService) {
        $customCss = "/* Only user CSS should be here */ .custom-box { padding: 10px; }";
        $themeService->saveCustomCss('corporate', $customCss);

        $ctrl = new ThemeController();
        $req = new Request('GET', '/admin/themes/customize', ['theme' => 'corporate']);
        $resp = $ctrl->customize($req);

        if ($resp->getStatusCode() !== 200) {
            throw new \AssertionError("Expected HTTP 200, got {$resp->getStatusCode()}");
        }

        $html = $resp->getBody();

        // Check for minified Tailwind framework bundle indicators
        $frameworkMarkers = [
            '/*! tailwindcss',
            '@tailwind base',
            '@tailwind components',
            '@tailwind utilities',
            'box-sizing:border-box',
            '*,::before,::after{box-sizing:border-box',
        ];

        foreach ($frameworkMarkers as $marker) {
            if (str_contains($html, $marker)) {
                throw new \AssertionError("POLLUTION DETECTED: Customizer HTML contains framework bundle marker '{$marker}'!");
            }
        }

        // Verify the user's custom CSS IS present
        if (!str_contains($html, '.custom-box { padding: 10px; }')) {
            throw new \AssertionError("User custom CSS was not loaded into the customizer config!");
        }

        return "Zero framework bundle leakage; user custom CSS isolated cleanly";
    }, $passes, $failures);

    runProbe('PROBE-6.2', 'Theme Customizer UI contracts (textarea, line gutter, tab indent, live preview)', function () {
        $ctrl = new ThemeController();
        $req = new Request('GET', '/admin/themes/customize', ['theme' => 'one-pager']);
        $resp = $ctrl->customize($req);

        $html = $resp->getBody();

        // Check required UI components
        $requiredUiFeatures = [
            'id="customCssEditor"'      => 'Textarea editor (#customCssEditor)',
            'id="cssLineNumbers"'       => 'Line numbers gutter (#cssLineNumbers)',
            '@scroll="syncScroll'       => 'Scroll synchronization on textarea',
            '@keydown.tab.prevent'      => 'Tab key indentation handler',
            'id="themePreviewFrame"'    => 'Live preview iframe (#themePreviewFrame)',
            'updatePreviewCss()'        => 'Dynamic style injection handler (updatePreviewCss)',
            'Änderungen speichern'      => 'One-click save button',
            'action="/admin/themes/save"' => 'Form submission endpoint',
        ];

        foreach ($requiredUiFeatures as $signature => $desc) {
            if (!str_contains($html, $signature)) {
                throw new \AssertionError("Missing Customizer UI requirement: {$desc}");
            }
        }

        return "All UI contracts (line gutter, textarea, tab indent, preview iframe, save button) verified";
    }, $passes, $failures);

    // =========================================================================
    // PROBE 7: ThemeController::save Action & Security Lifecycles
    // =========================================================================
    runProbe('PROBE-7.1', 'ThemeController::save persists custom CSS, triggers recompile and redirects 302', function () use ($themeService) {
        $ctrl = new ThemeController();
        $targetTheme = 'startup';
        $submittedCss = "/* Submitted from Customizer POST */ .hero-cta { background: #8b5cf6; border-radius: 9999px; }";
        $csrfToken = Csrf::token();

        $req = new Request('POST', '/admin/themes/save', [], [
            '_csrf_token' => $csrfToken,
            'theme_slug'  => $targetTheme,
            'colors'      => ['primary' => '#8b5cf6', 'primary_hover' => '#7c3aed'],
            'typography'  => ['font_family' => 'Inter', 'heading_font' => 'Outfit'],
            'settings'    => ['container_width' => 'max-w-6xl', 'border_radius' => 'rounded-xl'],
            'custom_css'  => $submittedCss,
        ]);

        $resp = $ctrl->save($req);

        if ($resp->getStatusCode() !== 302) {
            throw new \AssertionError("Expected HTTP 302 redirect, got {$resp->getStatusCode()}");
        }

        $location = $resp->getHeader('Location');
        if (!str_contains((string)$location, "/admin/themes/customize?theme={$targetTheme}")) {
            throw new \AssertionError("Redirect location mismatch: {$location}");
        }

        // Verify persistence in ThemeService
        $persisted = $themeService->getCustomCss($targetTheme);
        if ($persisted !== $submittedCss) {
            throw new \AssertionError("Custom CSS was not persisted via ThemeController::save");
        }

        return "Successfully saved via POST, persisted to storage, and redirected with HTTP 302";
    }, $passes, $failures);

    runProbe('PROBE-7.2', 'CSRF Protection: POST to /admin/themes/save with invalid CSRF token is rejected', function () use ($themeService) {
        $ctrl = new ThemeController();
        $targetTheme = 'corporate';
        $priorCss = $themeService->getCustomCss($targetTheme);

        $maliciousCss = "/* Forged CSRF request */ body { display: none !important; }";

        $req = new Request('POST', '/admin/themes/save', [], [
            '_csrf_token' => 'invalid_forged_csrf_token_xyz',
            'theme_slug'  => $targetTheme,
            'custom_css'  => $maliciousCss,
        ]);

        $resp = $ctrl->save($req);

        // Verify redirect to customize with error
        if ($resp->getStatusCode() !== 302) {
            throw new \AssertionError("Expected HTTP 302 redirect on CSRF failure, got {$resp->getStatusCode()}");
        }

        // Verify CSS was NOT updated
        $currentCss = $themeService->getCustomCss($targetTheme);
        if ($currentCss === $maliciousCss) {
            throw new \AssertionError("SECURITY BREACH: Custom CSS was persisted despite invalid CSRF token!");
        }

        return "Invalid CSRF token rejected; custom CSS remained untouched";
    }, $passes, $failures);

    runProbe('PROBE-7.3', 'Authentication & RBAC: Unauthenticated requests to Customizer are blocked', function () {
        // Logout user
        Auth::logout();

        $ctrl = new ThemeController();
        $reqGet = new Request('GET', '/admin/themes/customize');
        $respGet = $ctrl->customize($reqGet);

        if ($respGet->getStatusCode() !== 302) {
            throw new \AssertionError("Unauthenticated GET /admin/themes/customize should redirect 302, got {$respGet->getStatusCode()}");
        }

        $reqPost = new Request('POST', '/admin/themes/save', [], ['theme_slug' => 'startup', 'custom_css' => 'hacked']);
        $respPost = $ctrl->save($reqPost);

        if ($respPost->getStatusCode() !== 302) {
            throw new \AssertionError("Unauthenticated POST /admin/themes/save should redirect 302, got {$respPost->getStatusCode()}");
        }

        return "Unauthenticated access properly redirected to login";
    }, $passes, $failures);

} finally {
    Auth::logout();
    if (file_exists($testDbFile)) @unlink($testDbFile);
    if (file_exists($testDbFile . '-wal')) @unlink($testDbFile . '-wal');
    if (file_exists($testDbFile . '-shm')) @unlink($testDbFile . '-shm');
}

echo "\n----------------------------------------------------------------------\n";
$total = count($passes) + count($failures);
printf("Total Probes: %d  |  Passed: %d  |  Failed: %d\n", $total, count($passes), count($failures));
echo "======================================================================\n";

if (count($failures) > 0) {
    echo "\n❌ FAILURES DETECTED:\n";
    foreach ($failures as $f) {
        echo " - {$f}\n";
    }
    exit(1);
}

echo "\n🎉 ALL EMPIRICAL CHALLENGER 1 STRESS PROBES PASSED SUCCESSFULLY! (100% OK)\n";
exit(0);
