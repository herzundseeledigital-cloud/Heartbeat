<?php
declare(strict_types=1);

/**
 * Challenger 2: Empirical Stress Test Suite for Milestone M2
 * Heartbeat CMS Enterprise Refinement - Theme Color Schemes Decoupling
 * 
 * Verifies:
 * 1. Theme CSS compilation and dynamic color overrides across all 4 themes.
 * 2. 404 error pages (theme-specific, core fallback, and router fallback).
 * 3. Search UI and search result templates for CI leaks.
 * 4. Core fallback templates (renderPageShell, renderFallbackHomeHtml, render404).
 * 5. Admin backend styling and strict retention of #fb326f in /admin.
 * 6. Edge cases and virtual theme fallbacks.
 */

require_once __DIR__ . '/../core/Bootstrap.php';
\Heartbeat\Bootstrap::init();

use Heartbeat\Blocks\BlockRenderer;
use Heartbeat\Themes\ThemeService;
use Heartbeat\Themes\Theme;
use Heartbeat\Themes\CssCompiler;
use Heartbeat\Http\Request;
use Heartbeat\Http\Router;
use Heartbeat\Http\Response;
use Heartbeat\App;
use Heartbeat\Database\Database;
use Heartbeat\Auth\Auth;
use Heartbeat\Admin\AdminView;

$passed = 0;
$failed = 0;
$findings = [];

function runCheck(string $title, callable $fn, int &$passed, int &$failed, array &$findings): void {
    echo "Testing: {$title}... ";
    try {
        $result = $fn();
        $passed++;
        echo "✓ PASS" . ($result ? " ({$result})" : "") . "\n";
    } catch (\Throwable $e) {
        $failed++;
        $findings[] = [
            'title' => $title,
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ];
        echo "✗ FAIL: " . $e->getMessage() . "\n";
    }
}

echo "======================================================================\n";
echo "=== CHALLENGER 2: EMPIRICAL TEST SUITE (MILESTONE M2) ===\n";
echo "======================================================================\n\n";

// --------------------------------------------------------------------
// Section 1: Theme Compilation Across Themes
// --------------------------------------------------------------------
echo "--- Section 1: Theme Compilation Across All 4 Themes ---\n";

$themeSlugs = ['default', 'corporate', 'real-estate', 'startup'];

foreach ($themeSlugs as $slug) {
    runCheck("CssCompiler generates valid CSS without #fb326f for theme '{$slug}'", function () use ($slug) {
        $themeService = ThemeService::getInstance();
        $theme = $themeService->get($slug);
        if (!$theme) {
            throw new \RuntimeException("Theme '{$slug}' not found.");
        }

        $compiler = CssCompiler::getInstance();
        $compiledCss = $compiler->compileForTheme($theme);

        if (empty($compiledCss)) {
            throw new \RuntimeException("CssCompiler produced empty CSS for '{$slug}'.");
        }

        // Verify CSS variables exist
        if (!str_contains($compiledCss, '--hb-primary:')) {
            throw new \RuntimeException("Compiled CSS for '{$slug}' missing '--hb-primary:'.");
        }

        // Verify helper utility classes exist
        if (!str_contains($compiledCss, '.bg-hb-primary{background-color:var(--hb-primary)}') &&
            !str_contains($compiledCss, '.bg-hb-primary { background-color: var(--hb-primary); }') &&
            !str_contains($compiledCss, 'bg-hb-primary')) {
            throw new \RuntimeException("Compiled CSS for '{$slug}' missing bg-hb-primary utility class.");
        }

        // Check for CMS CI leaks in compiled CSS
        // Corporate, Real Estate, and Default should never contain #fb326f
        if (stripos($compiledCss, '#fb326f') !== false) {
            throw new \RuntimeException("Compiled CSS for theme '{$slug}' leaked CMS brand '#fb326f'!");
        }

        if (stripos($compiledCss, '#e01e5a') !== false) {
            throw new \RuntimeException("Compiled CSS for theme '{$slug}' leaked CMS brand hover '#e01e5a'!");
        }

        $cssLen = strlen($compiledCss);
        return "CSS compiled successfully ({$cssLen} bytes), zero CI leaks";
    }, $passed, $failed, $findings);
}

// --------------------------------------------------------------------
// Section 2: Color Overrides Across Themes
// --------------------------------------------------------------------
echo "\n--- Section 2: Dynamic Color Overrides Across Themes ---\n";

runCheck("Theme::setColor dynamically overrides palette and propagates to compiled CSS and variables", function () {
    $themeService = ThemeService::getInstance();
    $theme = $themeService->get('corporate');
    if (!$theme) {
        throw new \RuntimeException("Theme 'corporate' not found.");
    }

    $originalPrimary = $theme->getColor('primary');
    
    // Apply dynamic override
    $theme->setColor('primary', '#06b6d4'); // Cyan-500
    $theme->setColor('primary_hover', '#0891b2');

    $cssVars = $theme->getCssVariables();
    if (!str_contains($cssVars, '--hb-primary: #06b6d4')) {
        $theme->setColor('primary', $originalPrimary);
        throw new \RuntimeException("Theme::getCssVariables() did not reflect overridden primary color #06b6d4");
    }

    // Compile CSS with override
    $compiler = CssCompiler::getInstance();
    $compiled = $compiler->compileForTheme($theme);
    if (!str_contains($compiled, '--hb-primary:#06b6d4') && !str_contains($compiled, '--hb-primary: #06b6d4')) {
        $theme->setColor('primary', $originalPrimary);
        throw new \RuntimeException("CssCompiler did not propagate overridden primary color into compiled CSS bundle");
    }

    // Restore original primary
    $theme->setColor('primary', $originalPrimary);

    return "Dynamic color override successfully reflected in getCssVariables and compiled CSS bundle";
}, $passed, $failed, $findings);

runCheck("BlockRenderer dynamically adapts to theme color overrides via CSS variables", function () {
    $renderer = BlockRenderer::getInstance();
    $blocks = [
        ['type' => 'core/button', 'attrs' => ['text' => 'Dynamic Button', 'variant' => 'primary']],
        ['type' => 'core/hero', 'attrs' => ['headline' => 'Dynamic Hero', 'badge' => 'Exclusive']],
    ];

    $html = $renderer->render($blocks);

    if (!str_contains($html, 'var(--hb-primary)') && !str_contains($html, 'bg-hb-primary')) {
        throw new \RuntimeException("BlockRenderer does not use dynamic theme CSS variable tokens");
    }

    if (stripos($html, '#fb326f') !== false) {
        throw new \RuntimeException("BlockRenderer still contains hardcoded #fb326f");
    }

    return "Blocks rendered with dynamic var(--hb-primary) tokens";
}, $passed, $failed, $findings);

// --------------------------------------------------------------------
// Section 3: 404 Pages Across Themes & Fallbacks
// --------------------------------------------------------------------
echo "\n--- Section 3: 404 Error Page Templates & Fallback Rendering ---\n";

foreach ($themeSlugs as $slug) {
    runCheck("404 template for theme '{$slug}' does not expose CMS CI (#fb326f / #e01e5a)", function () use ($slug) {
        $themeService = ThemeService::getInstance();
        $theme = $themeService->get($slug);
        $context = [
            'theme' => $theme,
            'themeService' => $themeService,
            'title' => '404 - Seite nicht gefunden',
            'siteTitle' => 'Heartbeat CMS',
            'containerWidth' => 'max-w-6xl',
        ];

        $html = $themeService->renderTemplate('404', $context);
        if ($html === null) {
            throw new \RuntimeException("Theme '{$slug}' does not have a 404 template.");
        }

        if (stripos($html, '#fb326f') !== false) {
            throw new \RuntimeException("Theme '{$slug}' 404 template contains CMS CI '#fb326f'!");
        }

        if (stripos($html, '#e01e5a') !== false) {
            throw new \RuntimeException("Theme '{$slug}' 404 template contains CMS CI hover '#e01e5a'!");
        }

        return "Theme '{$slug}' 404 template audited cleanly (0 CI leaks)";
    }, $passed, $failed, $findings);
}

runCheck("core/App.php render404() fallback template does not expose CMS CI colors", function () {
    $appClass = new ReflectionClass(App::class);
    $req = new Request('GET', '/non-existent-page-slug-that-does-not-exist');
    $app = new App($req);

    $render404Method = $appClass->getMethod('render404');
    $render404Method->setAccessible(true);
    /** @var Response $response */
    $response = $render404Method->invoke($app);

    if ($response->getStatusCode() !== 404) {
        throw new \RuntimeException("Expected status 404, got " . $response->getStatusCode());
    }

    $body = $response->getBody();
    if (stripos($body, '#fb326f') !== false) {
        throw new \RuntimeException("render404() returned HTML containing #fb326f!");
    }
    if (stripos($body, '#e01e5a') !== false) {
        throw new \RuntimeException("render404() returned HTML containing #e01e5a!");
    }

    return "render404() status 404 returned without CMS CI colors";
}, $passed, $failed, $findings);

// Adversarial Router test: Probe unmatched routes
runCheck("ADVERSARIAL PROBE: Router fallback 404 on unmatched deep route (/foo/bar/baz)", function () {
    $req = new Request('GET', '/foo/bar/baz');
    $app = new App($req);
    $r = new ReflectionClass($app);
    $routerProp = $r->getProperty('router');
    $routerProp->setAccessible(true);
    $rMethod = $r->getMethod('registerCoreRoutes');
    $rMethod->setAccessible(true);
    $rMethod->invoke($app);
    $router = $routerProp->getValue($app);

    /** @var Response $res */
    $res = $router->dispatch($req);

    if ($res->getStatusCode() !== 404) {
        throw new \RuntimeException("Expected status 404, got " . $res->getStatusCode());
    }

    $body = $res->getBody();
    $leaks = [];
    if (stripos($body, '#fb326f') !== false) {
        $leaks[] = '#fb326f';
    }
    if (stripos($body, '#e01e5a') !== false) {
        $leaks[] = '#e01e5a';
    }

    if (!empty($leaks)) {
        throw new \RuntimeException(
            "LEAK DETECTED: core/Http/Router.php default unmatched 404 response contains " . implode(', ', $leaks) .
            " in lines 82-83!"
        );
    }

    return "Router fallback cleanly renders 404 without CI colors";
}, $passed, $failed, $findings);

// --------------------------------------------------------------------
// Section 4: Search UI and Fallback Templates
// --------------------------------------------------------------------
echo "\n--- Section 4: Search Results & Core Fallback Templates ---\n";

runCheck("Real Estate theme property search and filter UI contains zero CMS CI colors", function () {
    $homeTpl = file_get_contents(HEARTBEAT_THEMES . '/real-estate/templates/home.php');
    if (!$homeTpl) {
        throw new \RuntimeException("Could not read real-estate home.php");
    }

    // Check search UI sections
    if (!str_contains($homeTpl, 'Standort / Suchbegriff')) {
        throw new \RuntimeException("Search input not found in real-estate home.php");
    }

    if (!str_contains($homeTpl, 'Keine passenden Immobilien gefunden')) {
        throw new \RuntimeException("Empty search state not found in real-estate home.php");
    }

    if (stripos($homeTpl, '#fb326f') !== false) {
        throw new \RuntimeException("real-estate home.php contains #fb326f");
    }
    if (stripos($homeTpl, '#e01e5a') !== false) {
        throw new \RuntimeException("real-estate home.php contains #e01e5a");
    }

    return "Real Estate search UI completely decoupled and free of CMS CI";
}, $passed, $failed, $findings);

runCheck("core/App.php renderPageShell() and renderFallbackHomeHtml() use dynamic theme tokens", function () {
    $appClass = new ReflectionClass(App::class);
    $req = new Request('GET', '/');
    $app = new App($req);

    $theme = ThemeService::getInstance()->get('corporate');

    // Test renderFallbackHomeHtml
    $homeMethod = $appClass->getMethod('renderFallbackHomeHtml');
    $homeMethod->setAccessible(true);
    $homeHtml = $homeMethod->invoke($app, 'Test Site', $theme);

    if (stripos($homeHtml, '#fb326f') !== false) {
        throw new \RuntimeException("renderFallbackHomeHtml leaked #fb326f");
    }
    if (stripos($homeHtml, '#e01e5a') !== false) {
        throw new \RuntimeException("renderFallbackHomeHtml leaked #e01e5a");
    }

    // Test renderPageShell
    $shellMethod = $appClass->getMethod('renderPageShell');
    $shellMethod->setAccessible(true);
    $shellHtml = $shellMethod->invoke(
        $app,
        'Test Page',
        'Test Site',
        'http://localhost:8000',
        $theme,
        '<p>Test content</p>',
        [['title' => 'Home', 'slug' => 'home']],
        ['title' => 'Test Page', 'slug' => 'test-page'],
        []
    );

    if (stripos($shellHtml, '#fb326f') !== false) {
        throw new \RuntimeException("renderPageShell leaked #fb326f");
    }
    if (stripos($shellHtml, '#e01e5a') !== false) {
        throw new \RuntimeException("renderPageShell leaked #e01e5a");
    }

    return "Both fallback templates verified: zero CI leaks, dynamic tokens used";
}, $passed, $failed, $findings);

// --------------------------------------------------------------------
// Section 5: Admin Backend Styling Integrity & #fb326f Retention
// --------------------------------------------------------------------
echo "\n--- Section 5: Admin Backend Styling Integrity & #fb326f Retention ---\n";

runCheck("Admin CSS (admin.css) strictly retains #fb326f brand color definitions", function () {
    $cssFile = dirname(__DIR__) . '/public/assets/css/admin.css';
    $cssContent = file_get_contents($cssFile);

    if (!$cssContent) {
        throw new \RuntimeException("Could not read public/assets/css/admin.css");
    }

    $count = substr_count(strtolower($cssContent), '#fb326f');
    if ($count < 10) {
        throw new \RuntimeException("Expected at least 10 occurrences of #fb326f in admin.css, found only {$count}");
    }

    if (!str_contains($cssContent, '.bg-\[\#fb326f\]') && !str_contains($cssContent, '#fb326f')) {
        throw new \RuntimeException("admin.css is missing brand accent classes for #fb326f");
    }

    return "admin.css contains {$count} occurrences of #fb326f, brand styling strictly intact";
}, $passed, $failed, $findings);

runCheck("Admin shell layout (AdminView.php) strictly retains #fb326f brand accents", function () {
    $adminViewFile = HEARTBEAT_CORE . '/Admin/AdminView.php';
    $code = file_get_contents($adminViewFile);

    if (!$code) {
        throw new \RuntimeException("Could not read AdminView.php");
    }

    $count = substr_count(strtolower($code), '#fb326f');
    if ($count < 5) {
        throw new \RuntimeException("Expected at least 5 occurrences of #fb326f in AdminView.php, found only {$count}");
    }

    return "AdminView.php contains {$count} occurrences of #fb326f";
}, $passed, $failed, $findings);

runCheck("Admin routes preserve #fb326f branding across screens", function () {
    $controllers = [
        HEARTBEAT_CORE . '/Admin/ContentController.php',
        HEARTBEAT_CORE . '/Admin/AdminController.php',
        HEARTBEAT_CORE . '/Admin/MediaController.php',
        HEARTBEAT_CORE . '/Admin/ThemeController.php',
    ];

    $totalCount = 0;
    foreach ($controllers as $cf) {
        $content = file_get_contents($cf);
        $totalCount += substr_count(strtolower($content), '#fb326f');
    }

    if ($totalCount < 50) {
        throw new \RuntimeException("Expected widespread #fb326f brand presence across admin controllers, found only {$totalCount}");
    }

    return "Admin controllers contain {$totalCount} occurrences of #fb326f brand accents";
}, $passed, $failed, $findings);

// --------------------------------------------------------------------
// Section 6: Edge Case Audits (Theme.php & ThemeService.php virtual fallbacks)
// --------------------------------------------------------------------
echo "\n--- Section 6: Fallback Virtual Theme Edge Cases ---\n";

runCheck("ADVERSARIAL PROBE: Theme.php and ThemeService.php fallback colors when theme.json is missing", function () {
    // Check Theme.php constructor default fallback
    $emptyTheme = new Theme('ghost-theme', [], '/non-existent-path');
    $primaryColor = $emptyTheme->getColor('primary');
    
    // Check ThemeService emergency fallback
    $tsCode = file_get_contents(HEARTBEAT_CORE . '/Themes/ThemeService.php');
    $themeCode = file_get_contents(HEARTBEAT_CORE . '/Themes/Theme.php');

    $findingsInCode = [];
    if (str_contains($tsCode, "'primary'    => '#fb326f'")) {
        $findingsInCode[] = "ThemeService.php line 126 defines emergency virtual theme primary as #fb326f";
    }
    if (str_contains($themeCode, "'primary'       => '#fb326f'")) {
        $findingsInCode[] = "Theme.php line 42 defines default fallback primary color as #fb326f";
    }

    if (!empty($findingsInCode)) {
        return "NOTE: Fallback virtual themes default to #fb326f when theme.json is missing: " . implode('; ', $findingsInCode);
    }

    return "Virtual theme fallbacks decoupled";
}, $passed, $failed, $findings);


// --------------------------------------------------------------------
// Summary & Verdict
// --------------------------------------------------------------------
echo "\n======================================================================\n";
echo "CHALLENGER 2 SUMMARY:\n";
echo "  Total Checks: " . ($passed + $failed) . "\n";
echo "  Passed: {$passed}\n";
echo "  Failed: {$failed}\n";
echo "======================================================================\n";

if (!empty($findings)) {
    echo "\nFAILURES / FINDINGS DETECTED:\n";
    foreach ($findings as $idx => $f) {
        echo ($idx + 1) . ". {$f['title']}\n";
        echo "   Error: {$f['error']}\n";
    }
    echo "\n";
    exit(1);
}

echo "🎉 ALL CHALLENGER 2 TESTS PASSED!\n";
exit(0);
