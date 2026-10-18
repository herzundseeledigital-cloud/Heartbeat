<?php
declare(strict_types=1);

/**
 * Adversarial Integrity & Stress Test for Milestone M2
 * Heartbeat CMS Enterprise Refinement - Theme Color Schemes Decoupling
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

use Heartbeat\Blocks\BlockRenderer;
use Heartbeat\Themes\ThemeService;
use Heartbeat\Themes\Theme;

$failures = [];
$passes = [];

function assertTest(string $name, callable $fn, array &$passes, array &$failures): void {
    try {
        $result = $fn();
        $passes[] = "✓ PASS: {$name} (" . ($result ?? 'OK') . ")";
        echo "✓ PASS: {$name}\n";
    } catch (\Throwable $e) {
        $failures[] = "✗ FAIL: {$name} -> " . $e->getMessage();
        echo "✗ FAIL: {$name} -> " . $e->getMessage() . "\n";
    }
}

echo "======================================================================\n";
echo "=== AUDITOR M2 ADVERSARIAL INTEGRITY & STRESS TEST ===\n";
echo "======================================================================\n\n";

// Test 1: Exhaustive BlockRenderer block types verification
assertTest("BlockRenderer outputs zero CMS CI (#fb326f / #e01e5a) across all block types", function () {
    $renderer = BlockRenderer::getInstance();
    $blocks = [
        ['type' => 'core/hero', 'attrs' => ['headline' => 'Test Hero', 'subheadline' => 'Sub', 'badge' => 'Badge', 'primary_btn_text' => 'Go', 'secondary_btn_text' => 'Learn']],
        ['type' => 'core/heading', 'attrs' => ['content' => 'Section Heading', 'subtitle' => 'Kicker subtitle', 'level' => 'h2']],
        ['type' => 'core/paragraph', 'attrs' => ['content' => 'Paragraph test body.', 'lead' => true]],
        ['type' => 'core/image', 'attrs' => ['url' => '/test.jpg', 'caption' => 'Caption']],
        ['type' => 'core/button', 'attrs' => ['text' => 'Click Me', 'variant' => 'primary']],
        ['type' => 'core/button', 'attrs' => ['text' => 'Outline Btn', 'variant' => 'outline']],
        ['type' => 'core/card', 'attrs' => ['title' => 'Feature Card', 'description' => 'Card desc', 'badge' => 'Hot']],
        ['type' => 'core/features', 'attrs' => ['title' => 'Features Grid', 'items' => [['icon' => '🚀', 'title' => 'F1', 'description' => 'D1']]]],
        ['type' => 'core/cta-banner', 'attrs' => ['headline' => 'CTA Headline', 'subheadline' => 'Sub', 'button' => 'Action']],
        ['type' => 'core/faq', 'attrs' => ['items' => [['question' => 'Q1?', 'answer' => 'A1.']]]],
        ['type' => 'core/quote', 'attrs' => ['quote' => 'Great product', 'author' => 'Jane Doe']],
        ['type' => 'core/alert', 'attrs' => ['title' => 'Alert Title', 'message' => 'Info message', 'type' => 'info']],
        ['type' => 'core/alert', 'attrs' => ['title' => 'Default Alert', 'message' => 'Default message', 'type' => 'custom']],
        ['type' => 'core/popup', 'attrs' => ['title' => 'Popup Title', 'message' => 'Popup message', 'cta_text' => 'Subscribe', 'cta_url' => '#pop', 'trigger' => 'exit', 'delay' => 5]],
    ];

    $html = $renderer->render($blocks);

    if (stripos($html, '#fb326f') !== false) {
        throw new \AssertionError("Found #fb326f in rendered HTML");
    }
    if (stripos($html, '#e01e5a') !== false) {
        throw new \AssertionError("Found #e01e5a in rendered HTML");
    }

    if (!str_contains($html, 'var(--hb-primary)') && !str_contains($html, 'bg-hb-primary')) {
        throw new \AssertionError("Rendered HTML does not utilize dynamic theme CSS variables");
    }

    return "Rendered " . count($blocks) . " blocks, 0 CMS CI leaks detected";
}, $passes, $failures);

// Test 2: Dynamic CSS variables for all 4 themes
assertTest("Theme::getCssVariables generates independent palettes for all 4 themes", function () {
    $themeService = ThemeService::getInstance();
    $themes = $themeService->getAll();

    $expected = [
        'one-pager'   => ['#f97316', '#ea580c'],
        'corporate'   => ['#2563eb', '#1d4ed8'],
        'real-estate' => ['#059669', '#047857'],
        'startup'     => ['#8b5cf6', '#7c3aed'],
    ];

    $verified = [];
    foreach ($expected as $slug => [$expectedPrimary, $expectedHover]) {
        $theme = $themeService->get($slug);
        if ($theme === null) {
            throw new \AssertionError("Theme '{$slug}' not found");
        }
        $css = $theme->getCssVariables();
        if (!str_contains($css, "--hb-primary: {$expectedPrimary}")) {
            throw new \AssertionError("Theme '{$slug}' CSS vars do not contain '--hb-primary: {$expectedPrimary}'. Got: {$css}");
        }
        if (!str_contains($css, "--hb-primary-hover: {$expectedHover}")) {
            throw new \AssertionError("Theme '{$slug}' CSS vars do not contain '--hb-primary-hover: {$expectedHover}'. Got: {$css}");
        }
        if (stripos($css, '#fb326f') !== false) {
            throw new \AssertionError("Theme '{$slug}' leaked CMS brand '#fb326f' in getCssVariables()");
        }
        $verified[] = $slug;
    }

    return "Verified distinct palettes for " . implode(', ', $verified);
}, $passes, $failures);

// Test 3: Exhaustive template directory scan
assertTest("Complete scan of themes/ directory for CI color leaks", function () {
    $themesDir = HEARTBEAT_THEMES;
    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($themesDir));
    $scannedFiles = 0;
    $leaks = [];

    foreach ($iterator as $file) {
        if ($file->isDir()) continue;
        $ext = pathinfo($file->getPathname(), PATHINFO_EXTENSION);
        if (!in_array($ext, ['php', 'json', 'css', 'html', 'js'], true)) continue;

        $scannedFiles++;
        $content = file_get_contents($file->getPathname());
        $rel = str_replace($themesDir . '/', '', $file->getPathname());

        if (stripos($content, '#fb326f') !== false) {
            $leaks[] = "{$rel} contains #fb326f";
        }
        if (stripos($content, '#e01e5a') !== false) {
            $leaks[] = "{$rel} contains #e01e5a";
        }
    }

    if (!empty($leaks)) {
        throw new \AssertionError("Found leaks in themes: " . implode('; ', $leaks));
    }

    return "Scanned {$scannedFiles} theme files across all themes, 0 leaks";
}, $passes, $failures);

// Test 4: App Core Fallback rendering checks
assertTest("core/App.php fallback templates have zero CI leaks", function () {
    $appFile = HEARTBEAT_CORE . '/App.php';
    $appCode = file_get_contents($appFile);

    if (stripos($appCode, '#fb326f') !== false) {
        throw new \AssertionError("core/App.php contains #fb326f");
    }
    if (stripos($appCode, '#e01e5a') !== false) {
        throw new \AssertionError("core/App.php contains #e01e5a");
    }

    // Verify dynamic css variable usage in App fallback
    if (!str_contains($appCode, 'var(--hb-primary)')) {
        throw new \AssertionError("core/App.php does not utilize var(--hb-primary) in fallback templates");
    }

    return "core/App.php completely free of #fb326f and #e01e5a; dynamic fallbacks verified";
}, $passes, $failures);

// Test 5: Adversarial edge cases on BlockRenderer
assertTest("BlockRenderer survives malformed blocks and injection payloads without errors", function () {
    $renderer = BlockRenderer::getInstance();

    $malformedBlocks = [
        [], // empty
        ['type' => 'unknown/block', 'attrs' => []],
        ['type' => 'core/hero', 'attrs' => ['headline' => '<script>alert(1)</script>', 'alignment' => 'invalid']],
        ['type' => 'core/card', 'attrs' => null, 'styles' => null],
        ['type' => 'core/columns', 'attrs' => ['columns' => -5, 'gap' => 'huge'], 'innerBlocks' => [
            ['type' => 'core/heading', 'attrs' => ['level' => 'h99', 'content' => '"quoted & escaped"']]
        ]],
        ['type' => 'core/alert', 'attrs' => ['type' => 'invalid-type', 'message' => null]],
    ];

    $html = $renderer->render($malformedBlocks);

    // Verify XSS is escaped
    if (str_contains($html, '<script>alert(1)</script>')) {
        throw new \AssertionError("XSS script injection was not properly escaped in BlockRenderer");
    }

    if (stripos($html, '#fb326f') !== false) {
        throw new \AssertionError("Malformed block triggered fallback with hardcoded #fb326f");
    }

    return "Handled " . count($malformedBlocks) . " edge-case/malformed blocks safely with proper escaping";
}, $passes, $failures);

echo "\n======================================================================\n";
echo "SUMMARY: " . count($passes) . " passed, " . count($failures) . " failed\n";
echo "======================================================================\n";

if (!empty($failures)) {
    exit(1);
}
echo "🎉 ALL ADVERSARIAL STRESS TESTS PASSED!\n";
