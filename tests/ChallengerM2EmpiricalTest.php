<?php
declare(strict_types=1);

/**
 * Milestone M2 Empirical Challenger Test Suite
 * Adversarial stress testing for Theme Color Scheme Decoupling (Requirement R3)
 *
 * Verifies:
 * 1. Rendered frontend HTML across all 4 themes (One-Pager, Corporate, Real Estate, Startup) for #fb326f leakage.
 * 2. BlockRenderer rendering diverse blocks (hero, cta, features, pricing, alert, quote, faq, button, card, etc.) under each active theme.
 * 3. Adversarial CSS rendering, invalid theme slugs, and fallback page rendering in core/App.php.
 * 4. End-to-end HTTP request dispatch through App kernel across all themes.
 * 5. Theme class fallback resilience.
 */

define('HEARTBEAT_TEST', true);
define('HEARTBEAT_ROOT', dirname(__DIR__));
define('HEARTBEAT_CORE', HEARTBEAT_ROOT . '/core');
define('HEARTBEAT_STORAGE', HEARTBEAT_ROOT . '/storage');
define('HEARTBEAT_THEMES', HEARTBEAT_ROOT . '/themes');

require_once HEARTBEAT_CORE . '/Autoloader.php';
\Heartbeat\Autoloader::register();

use Heartbeat\Database\Database;
use Heartbeat\Database\MigrationRunner;
use Heartbeat\Themes\ThemeService;
use Heartbeat\Themes\Theme;
use Heartbeat\Themes\CssCompiler;
use Heartbeat\Blocks\BlockRenderer;
use Heartbeat\Content\PostService;
use Heartbeat\Content\MetaService;
use Heartbeat\Content\PostTypeRegistry;
use Heartbeat\Security\Session;
use Heartbeat\Security\Csrf;
use Heartbeat\Http\Request;
use Heartbeat\Http\Response;
use Heartbeat\App;

// Test Runner Harness
class EmpiricalHarness
{
    private int $passed = 0;
    private int $failed = 0;
    /** @var array<string> */
    private array $errors = [];
    private float $startTime;

    public function __construct()
    {
        $this->startTime = microtime(true);
    }

    public function test(string $description, callable $fn): void
    {
        try {
            $msg = $fn();
            $this->passed++;
            echo "  ✓ PASS: {$description}" . ($msg ? " ({$msg})" : "") . "\n";
        } catch (\Throwable $e) {
            $this->failed++;
            $errorDetail = $e->getMessage() . " at " . basename($e->getFile()) . ":" . $e->getLine();
            $this->errors[] = "✗ {$description} -> {$errorDetail}";
            echo "  ✗ FAIL: {$description}\n";
            echo "         → {$errorDetail}\n";
        }
    }

    public function section(string $title): void
    {
        echo "\n=== [CHALLENGE SECTION] {$title} ===\n";
    }

    public function summary(): int
    {
        $duration = round(microtime(true) - $this->startTime, 3);
        echo "\n======================================================================\n";
        echo "               EMPIRICAL CHALLENGER TEST EXECUTION SUMMARY            \n";
        echo "======================================================================\n";
        echo "  Total Executed : " . ($this->passed + $this->failed) . "\n";
        echo "  Passed         : {$this->passed}\n";
        echo "  Failed         : {$this->failed}\n";
        echo "  Duration       : {$duration} seconds\n";
        echo "======================================================================\n";

        if ($this->failed > 0) {
            echo "❌ CRITICAL: EMPIRICAL CHALLENGE DETECTED {$this->failed} FAILURES:\n";
            foreach ($this->errors as $err) {
                echo "   {$err}\n";
            }
            return 1;
        }

        echo "🎉 ALL EMPIRICAL CHALLENGES PASSED! VERDICT: APPROVE\n";
        return 0;
    }
}

$harness = new EmpiricalHarness();

echo "======================================================================\n";
echo "=== HEARTBEAT CMS: M2 EMPIRICAL ADVERSARIAL CHALLENGER SUITE ===\n";
echo "======================================================================\n";

// -----------------------------------------------------------------------------
// SETUP: Isolated SQLite Database & Migrations
// -----------------------------------------------------------------------------
Session::start();

$testDbFile = HEARTBEAT_STORAGE . '/test_m2_challenger.sqlite';
if (file_exists($testDbFile)) {
    @unlink($testDbFile);
}

$db = new Database([
    'driver'   => 'sqlite',
    'database' => $testDbFile,
    'prefix'   => 'hb_',
]);
Database::setInstance($db);

$runner = new MigrationRunner($db);
$runner->runPending(HEARTBEAT_ROOT . '/core/Database/Schema');

$db->insert('{prefix}options', ['option_name' => 'site_title', 'option_value' => 'Empirical Challenge Site', 'autoload' => 1]);
$db->insert('{prefix}options', ['option_name' => 'site_url', 'option_value' => 'http://localhost:8000', 'autoload' => 1]);
$db->insert('{prefix}options', ['option_name' => 'admin_email', 'option_value' => 'challenger@heartbeat.local', 'autoload' => 1]);
$db->insert('{prefix}options', ['option_name' => 'active_theme', 'option_value' => 'one-pager', 'autoload' => 1]);

$meta = new MetaService($db);
$renderer = BlockRenderer::getInstance();
$posts = new PostService($db, $meta, $renderer);

// Seed home page and sample pages
$homeId = $posts->create([
    'title'          => 'Startseite',
    'slug'           => 'home',
    'post_type'      => 'page',
    'status'         => 'publish',
    'content_blocks' => [
        [
            'type'  => 'core/hero',
            'attrs' => [
                'headline'         => 'Empirical Test Hero',
                'subheadline'      => 'Testing theme palette isolation',
                'badge'            => 'Live Test',
                'primary_btn_text' => 'Action Button',
                'primary_btn_url'  => '#action',
            ],
        ],
    ],
]);

$aboutId = $posts->create([
    'title'          => 'Über Uns',
    'slug'           => 'about',
    'post_type'      => 'page',
    'status'         => 'publish',
    'content_blocks' => [
        [
            'type'  => 'core/heading',
            'attrs' => ['content' => 'Über Uns Seite', 'level' => 'h1', 'subtitle' => 'Unternehmensprofil'],
        ],
        [
            'type'  => 'core/paragraph',
            'attrs' => ['content' => 'Das ist eine Testseite.'],
        ],
    ],
]);

// Seed CPT items for Corporate & Real Estate
$posts->create([
    'title'          => 'Max Mustermann',
    'slug'           => 'max-mustermann',
    'post_type'      => 'team_member',
    'status'         => 'publish',
    'content_blocks' => [],
], [
    'position'   => 'Managing Director',
    'department' => 'Geschäftsführung',
    'email'      => 'max@agentur.de',
]);

$posts->create([
    'title'          => 'Global FinTech Redesign',
    'slug'           => 'global-fintech',
    'post_type'      => 'case_study',
    'status'         => 'publish',
    'content_blocks' => [],
], [
    'client_name'  => 'FinTech AG',
    'project_year' => '2026',
]);

$posts->create([
    'title'          => 'Penthouse HafenCity',
    'slug'           => 'penthouse-hafencity',
    'post_type'      => 'property',
    'status'         => 'publish',
    'content_blocks' => [],
], [
    'price'        => '1850000',
    'location'     => 'Hamburg HafenCity',
    'rooms'        => '4.5',
    'living_space' => '185',
]);

$themeService = ThemeService::getInstance();

$themesMap = [
    'one-pager'   => ['name' => 'One-Pager Starter Theme', 'primary' => '#f97316', 'hover' => '#ea580c'],
    'corporate'   => ['name' => 'Clean Corporate Theme',   'primary' => '#2563eb', 'hover' => '#1d4ed8'],
    'real-estate' => ['name' => 'Classic Real Estate Theme','primary' => '#059669', 'hover' => '#047857'],
    'startup'     => ['name' => 'Modern Startup Theme',    'primary' => '#8b5cf6', 'hover' => '#7c3aed'],
];

// Helper to audit rendered HTML for CMS CI leaks
function assertNoCiLeak(string $html, string $contextName): void
{
    if (stripos($html, '#fb326f') !== false) {
        throw new \AssertionError("Found hardcoded CMS brand '#fb326f' in {$contextName}");
    }
    if (stripos($html, '#e01e5a') !== false) {
        throw new \AssertionError("Found hardcoded CMS brand hover '#e01e5a' in {$contextName}");
    }
}

// =============================================================================
// SECTION 1: Exhaustive Template Rendering Probe Across All 4 Themes
// =============================================================================
$harness->section('1. Exhaustive Template Rendering Probe across All 4 Themes');

foreach ($themesMap as $slug => $info) {
    $harness->test("Theme '{$slug}' ({$info['name']}) renders all templates without #fb326f / #e01e5a", function () use ($themeService, $slug, $info, $db, $posts) {
        $theme = $themeService->get($slug);
        if (!$theme) {
            throw new \AssertionError("Theme '{$slug}' could not be loaded via ThemeService");
        }

        $themeService->setActive($slug);

        $templatesDir = $theme->getPath() . '/templates';
        $templateFiles = glob($templatesDir . '/*.php') ?: [];
        if (empty($templateFiles)) {
            throw new \AssertionError("No template files found in {$templatesDir}");
        }

        $context = [
            'theme'        => $theme,
            'themeService' => $themeService,
            'siteTitle'    => 'Empirical Test Site',
            'siteUrl'      => 'http://localhost:8000',
            'title'        => 'Test Page',
            'contentHtml'  => '<p>Sample Body</p>',
            'post'         => ['title' => 'Sample Post', 'content' => '<p>Post body</p>', 'slug' => 'sample'],
            'posts'        => [],
            'items'        => [],
            'navPages'     => [],
            'navTree'      => [],
            'csrfField'    => Csrf::field(),
            'csrfToken'    => Csrf::token(),
            'seoTags'      => '',
            'db'           => $db,
        ];

        $audited = 0;
        foreach ($templateFiles as $file) {
            $tplName = basename($file, '.php');
            $rendered = $themeService->renderTemplate($tplName, $context);
            if ($rendered === null) {
                continue;
            }

            assertNoCiLeak($rendered, "Theme '{$slug}' template '{$tplName}.php'");
            $audited++;
        }

        return "Audited {$audited} templates in {$slug}, zero CI leaks";
    });
}

// =============================================================================
// SECTION 2: BlockRenderer Diverse Block Rendering Under Each Active Theme
// =============================================================================
$harness->section('2. BlockRenderer Diverse Block Suite Under Each Theme');

$diverseBlocks = [
    [
        'type'  => 'core/hero',
        'attrs' => [
            'headline'           => 'Hero Headline',
            'subheadline'        => 'Hero Subheadline',
            'badge'              => 'Top Badge',
            'primary_btn_text'   => 'Primary Action',
            'primary_btn_url'    => '#primary',
            'secondary_btn_text' => 'Secondary Action',
            'secondary_btn_url'  => '#secondary',
        ],
    ],
    [
        'type'  => 'core/heading',
        'attrs' => [
            'level'    => 'h2',
            'content'  => 'Section Heading with Kicker',
            'subtitle' => 'Kicker Subtitle Text',
        ],
    ],
    [
        'type'  => 'core/paragraph',
        'attrs' => [
            'content' => 'This is a lead paragraph with rich text styling.',
            'lead'    => true,
        ],
    ],
    [
        'type'  => 'core/button',
        'attrs' => [
            'text'    => 'Default Primary Button',
            'url'     => '#default-btn',
            'variant' => 'default',
        ],
    ],
    [
        'type'  => 'core/button',
        'attrs' => [
            'text'    => 'Outline Button',
            'url'     => '#outline-btn',
            'variant' => 'outline',
        ],
    ],
    [
        'type'  => 'core/card',
        'attrs' => [
            'title'       => 'High-Performance Card',
            'description' => 'Card description text',
            'badge'       => 'Pro',
            'icon'        => '⚡',
            'button_text' => 'Card Button',
            'button_url'  => '#card',
        ],
    ],
    [
        'type'  => 'core/features',
        'attrs' => [
            'title'       => 'Features Section',
            'columns'     => 3,
            'items'       => [
                ['icon' => '🚀', 'title' => 'Speed', 'description' => 'Blazing fast load times.'],
                ['icon' => '🛡️', 'title' => 'Security', 'description' => 'Hardened against exploits.'],
                ['icon' => '🎨', 'title' => 'Palette', 'description' => 'Isolated theme color palettes.'],
            ],
        ],
    ],
    [
        'type'  => 'core/pricing',
        'attrs' => [
            'title'       => 'Enterprise Plan',
            'price'       => '€99',
            'period'      => '/Monat',
            'description' => 'Ideal für wachsende Unternehmen',
            'featured'    => true,
            'badge'       => 'Beliebt',
            'features'    => ['Unbegrenzte Domains', '24/7 Priority Support', 'Dedicated IP'],
            'cta_text'    => 'Plan buchen',
            'cta_url'     => '#pricing-book',
        ],
    ],
    [
        'type'  => 'core/cta',
        'attrs' => [
            'headline'    => 'Ready to scale?',
            'subheadline' => 'Join thousands of agency clients today.',
            'button_text' => 'Get Started Now',
            'button_url'  => '#start',
        ],
    ],
    [
        'type'  => 'core/quote',
        'attrs' => [
            'quote'   => 'Heartbeat CMS has revolutionized our digital client delivery.',
            'author'  => 'Sarah Connor',
            'role'    => 'CTO, Cyberdyne Systems',
            'avatar'  => '', // Fallback avatar triggers initials badge with theme primary
        ],
    ],
    [
        'type'  => 'core/faq',
        'attrs' => [
            'items' => [
                ['question' => 'How does theme color decoupling work?', 'answer' => 'Via CSS variables var(--hb-primary).'],
                ['question' => 'Does the CMS admin brand color leak into client themes?', 'answer' => 'Never. It is strictly reserved for CMS Admin.'],
            ],
        ],
    ],
    [
        'type'  => 'core/alert',
        'attrs' => [
            'type'    => 'default', // Default alert tests fallback alert styling
            'title'   => 'System Notice',
            'message' => 'This alert uses dynamic theme primary accent colors.',
        ],
    ],
    [
        'type'  => 'core/popup',
        'attrs' => [
            'trigger'       => 'role_gate',
            'required_role' => 'administrator',
            'title'         => 'Paywall Protected Section',
            'message'       => 'Login with your account to unlock this section.',
            'cta_text'      => 'Login to Unlock',
            'cta_url'       => '/admin/login',
        ],
    ],
    [
        'type'        => 'core/columns',
        'attrs'       => ['columns' => 2],
        'innerBlocks' => [
            [
                'type'  => 'core/button',
                'attrs' => ['text' => 'Nested Column Btn 1', 'variant' => 'default'],
            ],
            [
                'type'  => 'core/button',
                'attrs' => ['text' => 'Nested Column Btn 2', 'variant' => 'outline'],
            ],
        ],
    ],
];

foreach ($themesMap as $slug => $info) {
    $harness->test("BlockRenderer rendering all 14 block variants under Theme '{$slug}'", function () use ($slug, $info, $renderer, $diverseBlocks, $themeService) {
        $themeService->setActive($slug);
        $theme = $themeService->get($slug);
        $cssVars = $theme->getCssVariables();

        $renderedHtml = $renderer->render($diverseBlocks);

        // 1. Audit for CMS brand leaks
        assertNoCiLeak($renderedHtml, "BlockRenderer under {$slug}");

        // 2. Audit for CSS variable integration
        if (!str_contains($renderedHtml, 'var(--hb-primary)') && !str_contains($renderedHtml, 'bg-hb-primary')) {
            throw new \AssertionError("BlockRenderer under {$slug} does not utilize dynamic theme variables");
        }

        // 3. Verify CSS variables produce the theme's expected primary color
        if (!str_contains($cssVars, "--hb-primary: {$info['primary']}")) {
            throw new \AssertionError("Theme {$slug} CSS variables do not declare --hb-primary: {$info['primary']}");
        }

        if (!str_contains($cssVars, "--hb-primary-hover: {$info['hover']}")) {
            throw new \AssertionError("Theme {$slug} CSS variables do not declare --hb-primary-hover: {$info['hover']}");
        }

        return "14 block types rendered, zero #fb326f leaks, dynamic variable binding confirmed";
    });
}

// =============================================================================
// SECTION 3: Adversarial CSS Rendering & Compilation
// =============================================================================
$harness->section('3. Adversarial CSS Rendering & JIT Compilation');

$compiler = CssCompiler::getInstance();

foreach ($themesMap as $slug => $info) {
    $harness->test("CssCompiler compiles static CSS for Theme '{$slug}' without CMS brand leak", function () use ($compiler, $themeService, $slug, $info) {
        $theme = $themeService->get($slug);
        $compiledCss = $compiler->compileForTheme($theme);

        assertNoCiLeak($compiledCss, "Compiled CSS for theme '{$slug}'");

        if (!str_contains($compiledCss, "--hb-primary:{$info['primary']}") && !str_contains($compiledCss, "--hb-primary: {$info['primary']}")) {
            throw new \AssertionError("Compiled CSS for '{$slug}' does not contain expected primary token '{$info['primary']}'");
        }

        // Verify utility class generation for dynamic primary classes
        if (!str_contains($compiledCss, '.bg-hb-primary') || !str_contains($compiledCss, '.text-hb-primary')) {
            throw new \AssertionError("Compiled CSS for '{$slug}' missing .bg-hb-primary or .text-hb-primary utility classes");
        }

        return "Compiled bundle: " . strlen($compiledCss) . " bytes, zero CI leaks";
    });
}

// Stress test: Malformed custom CSS injection into compiler
$harness->test("CssCompiler safely handles adversarial custom CSS snippets without crashing", function () use ($compiler, $themeService) {
    $theme = $themeService->getActive();
    $adversarialSnippets = [
        "/* Unclosed comment \n body { color: red; }",
        "body { font-size: 14px; /* unclosed }",
        ".custom-class { background-color: var(--hb-primary); margin: -9999px; }",
        "<script>alert('xss in css')</script>",
    ];

    foreach ($adversarialSnippets as $idx => $snippet) {
        $out = $compiler->compileForTheme($theme, null, $snippet);
        assertNoCiLeak($out, "Adversarial snippet #{$idx}");
    }

    return "Successfully processed " . count($adversarialSnippets) . " adversarial CSS snippets";
});

// =============================================================================
// SECTION 4: Invalid Theme Slugs & Fallback Resilience
// =============================================================================
$harness->section('4. Invalid Theme Slugs & Fallback Resilience');

$harness->test("ThemeService::get() returns null on non-existent slugs", function () use ($themeService) {
    $res = $themeService->get('totally_non_existent_slug_123');
    if ($res !== null) {
        throw new \AssertionError("Expected null for invalid theme slug, got " . gettype($res));
    }
    return "Returns null as expected";
});

$harness->test("ThemeService::setActive() rejects invalid slugs and preserves current theme", function () use ($themeService) {
    $before = $themeService->getActive()->getSlug();
    $ok = $themeService->setActive('invalid_slug_xyz');
    if ($ok !== false) {
        throw new \AssertionError("Expected setActive('invalid_slug_xyz') to return false");
    }
    $after = $themeService->getActive()->getSlug();
    if ($after !== $before) {
        throw new \AssertionError("Active theme changed from {$before} to {$after} on invalid setActive call");
    }
    return "Refused invalid slug, preserved active theme '{$before}'";
});

$harness->test("ThemeService::getActive() falls back gracefully when DB has corrupted slug", function () use ($themeService, $db) {
    $corruptedSlugs = [
        'completely_unknown_theme_9999',
        '',
        '../../../../etc/passwd',
        '<script>alert("xss")</script>',
    ];

    foreach ($corruptedSlugs as $badSlug) {
        $db->update('{prefix}options', ['option_value' => $badSlug], 'option_name = :name', ['name' => 'active_theme']);

        // Force reload active theme
        $fallbackTheme = $themeService->getActive();
        if (!$fallbackTheme instanceof Theme) {
            throw new \AssertionError("getActive() failed to return a Theme instance on corrupted slug '{$badSlug}'");
        }

        // Verify fallback is NOT defaulting to CMS CI (#fb326f)
        $vars = $fallbackTheme->getCssVariables();
        assertNoCiLeak($vars, "Fallback theme for corrupted slug '{$badSlug}'");

        // The fallback must be One-Pager (Sunset Orange #f97316)
        if (!str_contains($vars, '--hb-primary: #f97316')) {
            throw new \AssertionError("Fallback theme primary color is not Sunset Orange #f97316 on corrupted slug '{$badSlug}'. Got: {$vars}");
        }
    }

    // Reset back to one-pager
    $db->update('{prefix}options', ['option_value' => 'one-pager'], 'option_name = :name', ['name' => 'active_theme']);
    return "All " . count($corruptedSlugs) . " corrupted slugs fell back to One-Pager (#f97316) with zero CI leak";
});

// =============================================================================
// SECTION 5: App Core Fallback Page Rendering
// =============================================================================
$harness->section('5. App Core Fallback Page Rendering');

// Use reflection to test private methods in App: renderFallbackHomeHtml, renderPageShell, render404
$appReflection = new \ReflectionClass(App::class);
$dummyApp = new App();

// 5.1 renderFallbackHomeHtml across all 4 themes
$harness->test("App::renderFallbackHomeHtml() renders with active theme colors across all 4 themes", function () use ($appReflection, $dummyApp, $themesMap, $themeService) {
    $method = $appReflection->getMethod('renderFallbackHomeHtml');
    $method->setAccessible(true);

    foreach ($themesMap as $slug => $info) {
        $theme = $themeService->get($slug);
        /** @var string $html */
        $html = $method->invoke($dummyApp, 'Empirical Fallback Site', $theme);

        assertNoCiLeak($html, "renderFallbackHomeHtml under {$slug}");

        if (!str_contains($html, "--hb-primary: {$info['primary']}")) {
            throw new \AssertionError("renderFallbackHomeHtml under {$slug} missing primary token {$info['primary']}");
        }

        if (!str_contains($html, 'bg-hb-primary')) {
            throw new \AssertionError("renderFallbackHomeHtml under {$slug} missing bg-hb-primary class");
        }
    }

    return "Verified across all 4 themes, zero CI brand leaks";
});

// 5.2 renderPageShell across all 4 themes
$harness->test("App::renderPageShell() renders with active theme colors across all 4 themes", function () use ($appReflection, $dummyApp, $themesMap, $themeService) {
    $method = $appReflection->getMethod('renderPageShell');
    $method->setAccessible(true);

    foreach ($themesMap as $slug => $info) {
        $theme = $themeService->get($slug);
        $post = ['title' => 'Fallback Page', 'slug' => 'fallback-page', 'content' => '<p>Fallback Content</p>'];
        /** @var string $html */
        $html = $method->invoke(
            $dummyApp,
            'Fallback Page',
            'Empirical Site',
            'http://localhost:8000',
            $theme,
            '<div class="prose"><p>Body</p></div>',
            [],
            $post,
            []
        );

        assertNoCiLeak($html, "renderPageShell under {$slug}");

        if (!str_contains($html, "--hb-primary: {$info['primary']}")) {
            throw new \AssertionError("renderPageShell under {$slug} missing primary token {$info['primary']}");
        }

        if (!str_contains($html, 'style="background-color: var(--hb-primary);"')) {
            throw new \AssertionError("renderPageShell under {$slug} missing dynamic HB badge background styling");
        }
    }

    return "Verified across all 4 themes, zero CI brand leaks";
});

// 5.3 render404 across all 4 themes
$harness->test("App::render404() renders 404 response with zero CI brand leaks", function () use ($appReflection, $dummyApp, $themesMap, $themeService) {
    $method = $appReflection->getMethod('render404');
    $method->setAccessible(true);

    foreach ($themesMap as $slug => $info) {
        $themeService->setActive($slug);
        /** @var Response $resp */
        $resp = $method->invoke($dummyApp);

        if ($resp->getStatusCode() !== 404) {
            throw new \AssertionError("render404 returned HTTP {$resp->getStatusCode()}, expected 404");
        }

        $html = $resp->getBody();
        assertNoCiLeak($html, "render404 under {$slug}");
    }

    return "Verified 404 rendering across all 4 themes, zero CI brand leaks";
});

// =============================================================================
// SECTION 6: Full Kernel HTTP Request Dispatch Across All 4 Themes
// =============================================================================
$harness->section('6. Full Kernel HTTP Request Dispatch Across All Themes');

foreach ($themesMap as $slug => $info) {
    $harness->test("Full HTTP request GET / with active_theme='{$slug}'", function () use ($themeService, $slug, $info, $db) {
        $themeService->setActive($slug);
        $db->update('{prefix}options', ['option_value' => $slug], 'option_name = :name', ['name' => 'active_theme']);

        $req = Request::create('GET', '/');
        $kernel = new App($req);

        // Access router dispatch directly
        $ref = new \ReflectionClass($kernel);
        $routerProp = $ref->getProperty('router');
        $routerProp->setAccessible(true);
        $router = $routerProp->getValue($kernel);

        $regMethod = $ref->getMethod('registerCoreRoutes');
        $regMethod->setAccessible(true);
        $regMethod->invoke($kernel);

        /** @var Response $resp */
        $resp = $router->dispatch($req);

        if ($resp->getStatusCode() !== 200) {
            throw new \AssertionError("GET / returned HTTP {$resp->getStatusCode()} under theme {$slug}");
        }

        $html = $resp->getBody();
        assertNoCiLeak($html, "GET / under {$slug}");

        return "HTTP 200 OK, " . strlen($html) . " bytes HTML, zero CI leaks";
    });

    $harness->test("Full HTTP request GET /about with active_theme='{$slug}'", function () use ($themeService, $slug, $db) {
        $themeService->setActive($slug);
        $db->update('{prefix}options', ['option_value' => $slug], 'option_name = :name', ['name' => 'active_theme']);

        $req = Request::create('GET', '/about');
        $kernel = new App($req);

        $ref = new \ReflectionClass($kernel);
        $routerProp = $ref->getProperty('router');
        $routerProp->setAccessible(true);
        $router = $routerProp->getValue($kernel);

        $regMethod = $ref->getMethod('registerCoreRoutes');
        $regMethod->setAccessible(true);
        $regMethod->invoke($kernel);

        /** @var Response $resp */
        $resp = $router->dispatch($req);

        if ($resp->getStatusCode() !== 200) {
            throw new \AssertionError("GET /about returned HTTP {$resp->getStatusCode()} under theme {$slug}");
        }

        $html = $resp->getBody();
        assertNoCiLeak($html, "GET /about under {$slug}");

        return "HTTP 200 OK, zero CI leaks";
    });
}

// Test theme preview parameter query ?theme_preview=...
foreach ($themesMap as $slug => $info) {
    $harness->test("HTTP request GET /?theme_preview={$slug} overrides active theme cleanly", function () use ($slug, $info, $themeService, $db) {
        // Set DB to a different theme
        $baseSlug = $slug === 'one-pager' ? 'corporate' : 'one-pager';
        $themeService->setActive($baseSlug);
        $db->update('{prefix}options', ['option_value' => $baseSlug], 'option_name = :name', ['name' => 'active_theme']);

        $req = Request::create('GET', '/?theme_preview=' . $slug);
        $kernel = new App($req);

        $ref = new \ReflectionClass($kernel);
        $routerProp = $ref->getProperty('router');
        $routerProp->setAccessible(true);
        $router = $routerProp->getValue($kernel);

        $regMethod = $ref->getMethod('registerCoreRoutes');
        $regMethod->setAccessible(true);
        $regMethod->invoke($kernel);

        /** @var Response $resp */
        $resp = $router->dispatch($req);

        if ($resp->getStatusCode() !== 200) {
            throw new \AssertionError("GET /?theme_preview={$slug} returned HTTP {$resp->getStatusCode()}");
        }

        $html = $resp->getBody();
        assertNoCiLeak($html, "GET /?theme_preview={$slug}");

        return "HTTP 200 OK, preview theme rendered cleanly without CI leaks";
    });
}

// Test specialized CPT routes
$specializedRoutes = [
    ['route' => '/team',                             'theme' => 'corporate',   'expectedSnippet' => 'Managing Director'],
    ['route' => '/referenzen',                       'theme' => 'corporate',   'expectedSnippet' => 'FinTech AG'],
    ['route' => '/immobilien/penthouse-hafencity',   'theme' => 'real-estate', 'expectedSnippet' => 'HafenCity'],
    ['route' => '/pricing',                          'theme' => 'startup',     'expectedSnippet' => 'Preis'],
];

foreach ($specializedRoutes as $sr) {
    $harness->test("HTTP request GET {$sr['route']} under theme '{$sr['theme']}'", function () use ($sr, $themeService, $db) {
        $themeService->setActive($sr['theme']);
        $db->update('{prefix}options', ['option_value' => $sr['theme']], 'option_name = :name', ['name' => 'active_theme']);

        $req = Request::create('GET', $sr['route']);
        $kernel = new App($req);

        $ref = new \ReflectionClass($kernel);
        $routerProp = $ref->getProperty('router');
        $routerProp->setAccessible(true);
        $router = $routerProp->getValue($kernel);

        $regMethod = $ref->getMethod('registerCoreRoutes');
        $regMethod->setAccessible(true);
        $regMethod->invoke($kernel);

        /** @var Response $resp */
        $resp = $router->dispatch($req);

        if ($resp->getStatusCode() !== 200) {
            throw new \AssertionError("GET {$sr['route']} returned HTTP {$resp->getStatusCode()}");
        }

        $html = $resp->getBody();
        assertNoCiLeak($html, "GET {$sr['route']}");

        if (!str_contains($html, $sr['expectedSnippet'])) {
            throw new \AssertionError("GET {$sr['route']} output missing expected content '{$sr['expectedSnippet']}'");
        }

        return "HTTP 200 OK, expected snippet '{$sr['expectedSnippet']}' found, zero CI leaks";
    });
}

// =============================================================================
// SECTION 7: Adversarial Edge Cases & Failure Mode Probes
// =============================================================================
$harness->section('7. Adversarial Edge Cases & Failure Mode Probes');

// Probe 7.1: Deep unmapped route router fallback 404
$harness->test("Deep unmapped route (e.g. /deep/nested/unknown/route) 404 response does not leak #fb326f", function () {
    $req = Request::create('GET', '/deep/nested/unknown/route');
    $kernel = new App($req);

    $ref = new \ReflectionClass($kernel);
    $routerProp = $ref->getProperty('router');
    $routerProp->setAccessible(true);
    $router = $routerProp->getValue($kernel);

    $regMethod = $ref->getMethod('registerCoreRoutes');
    $regMethod->setAccessible(true);
    $regMethod->invoke($kernel);

    /** @var Response $resp */
    $resp = $router->dispatch($req);

    if (stripos($resp->getBody(), '#fb326f') !== false) {
        throw new \AssertionError("LEAK DETECTED: core/Http/Router.php default unmatched 404 response contains hardcoded '#fb326f' in lines 82-83!");
    }

    return "Router 404 does not leak #fb326f";
});

// Probe 7.2: Virtual fallback theme without colors
$harness->test("Theme without colors definition does not default to CMS brand #fb326f", function () {
    $blankTheme = new Theme('blank', [], '/tmp');
    $css = $blankTheme->getCssVariables();

    if (stripos($css, '#fb326f') !== false) {
        throw new \AssertionError("LEAK DETECTED: core/Themes/Theme.php line 42 & 162 defaults unconfigured theme primary to CMS brand '#fb326f'!");
    }

    return "Virtual theme defaults to neutral/decoupled color";
});

// Probe 7.3: CssCompiler when scanning legacy database posts
$harness->test("CssCompiler does not extract legacy #fb326f classes from database posts into frontend bundles", function () use ($compiler, $themeService, $db) {
    // Insert a legacy post with pre-rendered #fb326f HTML
    $legacyId = (int)$db->insert('{prefix}posts', [
        'title'          => 'Legacy Test Post',
        'slug'           => 'legacy-test-post',
        'content_blocks' => '[]',
        'content_html'   => '<button class="bg-[#fb326f]">Legacy Button</button>',
        'post_type'      => 'page',
        'status'         => 'publish',
    ]);

    $theme = $themeService->get('corporate');
    $css = $compiler->compileForTheme($theme);

    // Clean up
    $db->delete('{prefix}posts', 'id = :id', ['id' => $legacyId]);

    if (stripos($css, '#fb326f') !== false) {
        throw new \AssertionError("LEAK DETECTED: CssCompiler extracted legacy 'bg-[#fb326f]' from database into compiled theme bundle!");
    }

    return "CssCompiler shields frontend theme bundle from legacy #fb326f";
});

// -----------------------------------------------------------------------------
// CLEANUP
// -----------------------------------------------------------------------------
if (file_exists($testDbFile)) {
    @unlink($testDbFile);
}

exit($harness->summary());

