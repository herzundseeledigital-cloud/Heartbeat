<?php
declare(strict_types=1);

/**
 * Heartbeat CMS - Phase 6 Automated Verification Test Suite
 * Validates the 4 Default Themes (One-Pager, Corporate, Real Estate, Startup),
 * Contact & Inquiry Engine with Honeypot anti-spam, Live Tailwind JIT template extraction,
 * and the Production Release Builder.
 */

define('HEARTBEAT_ROOT', dirname(__DIR__));
define('HEARTBEAT_CONFIG', HEARTBEAT_ROOT . '/config');
define('HEARTBEAT_STORAGE', HEARTBEAT_ROOT . '/storage');

require_once HEARTBEAT_ROOT . '/core/Autoloader.php';
\Heartbeat\Autoloader::register();

use Heartbeat\Database\Database;
use Heartbeat\Database\MigrationRunner;
use Heartbeat\Themes\ThemeService;
use Heartbeat\Themes\CssCompiler;
use Heartbeat\Content\PostService;
use Heartbeat\Content\MetaService;
use Heartbeat\Content\PostTypeRegistry;
use Heartbeat\Security\Csrf;
use Heartbeat\Security\Session;
use Heartbeat\Http\Request;
use Heartbeat\Http\Response;
use Heartbeat\Http\ContactController;
use Heartbeat\Mail\Mailer;

echo "======================================================================\n";
echo "=== PHASE 6: THE 4 DEFAULT THEMES & PRODUCTION RELEASE BUILDER ===\n";
echo "======================================================================\n\n";

// Ensure clean test session
Session::start();

// Setup temporary SQLite test database
$testDbFile = HEARTBEAT_STORAGE . '/test_phase6.sqlite';
if (file_exists($testDbFile)) {
    @unlink($testDbFile);
}

$db = new Database([
    'driver'   => 'sqlite',
    'database' => $testDbFile,
    'prefix'   => 'hb_',
]);
Database::setInstance($db);

// Run all migrations
$migrationRunner = new MigrationRunner($db);
$migrationRunner->runPending(HEARTBEAT_ROOT . '/core/Database/Schema');

// Seed site settings
$db->insert('{prefix}options', ['option_name' => 'site_title', 'option_value' => 'Heartbeat Agency Test', 'autoload' => 1]);
$db->insert('{prefix}options', ['option_name' => 'site_url', 'option_value' => 'http://localhost:8000', 'autoload' => 1]);
$db->insert('{prefix}options', ['option_name' => 'admin_email', 'option_value' => 'admin@heartbeat-agency.de', 'autoload' => 1]);
$db->insert('{prefix}options', ['option_name' => 'active_theme', 'option_value' => 'one-pager', 'autoload' => 1]);
$db->insert('{prefix}options', ['option_name' => 'mail_driver', 'option_value' => 'log', 'autoload' => 1]);

// -----------------------------------------------------------------------------
// 1. Theme Discovery & Manifest Auditing
// -----------------------------------------------------------------------------
echo "1. Testing Theme Discovery & Manifest Auditing for all 4 Themes...\n";

$themeService = new ThemeService(HEARTBEAT_ROOT . '/themes');
$allThemes = $themeService->getAll();

$expectedThemeSlugs = ['one-pager', 'corporate', 'real-estate', 'startup'];
foreach ($expectedThemeSlugs as $slug) {
    $theme = $themeService->get($slug);
    assert($theme !== null, "Theme '{$slug}' must be discovered by ThemeService");
    assert($theme->getName() !== '', "Theme '{$slug}' must have a valid name");
    assert($theme->getVersion() !== '', "Theme '{$slug}' must have a valid version");
    assert(!empty($theme->getColors()['primary']), "Theme '{$slug}' must have a primary color");
    assert(!empty($theme->getColors()['background']), "Theme '{$slug}' must have a background color");
    assert(!empty($theme->getTypography()['font_family']), "Theme '{$slug}' must have font_family");
    assert(is_dir($theme->getPath()), "Theme '{$slug}' path must exist");
}

echo "   ✓ All 4 themes (One-Pager, Corporate, Real Estate, Startup) successfully discovered and audited!\n";

// -----------------------------------------------------------------------------
// 2. Template Existence & Integrity Checks
// -----------------------------------------------------------------------------
echo "2. Testing Template File Integrity Across All Themes...\n";

$themeTemplates = [
    'default' => ['header.php', 'footer.php', 'home.php', 'page.php', 'single.php', '404.php'],
    'corporate' => ['header.php', 'footer.php', 'home.php', 'page.php', 'single.php', 'team.php', 'case-studies.php', 'archive.php', '404.php'],
    'real-estate' => ['header.php', 'footer.php', 'home.php', 'page.php', 'single.php', 'property.php', '404.php'],
    'startup' => ['header.php', 'footer.php', 'home.php', 'page.php', 'single.php', 'pricing.php', '404.php'],
];

foreach ($themeTemplates as $dir => $tplFiles) {
    $baseDir = HEARTBEAT_ROOT . '/themes/' . $dir . '/templates';
    assert(is_dir($baseDir), "Template directory {$baseDir} must exist");
    foreach ($tplFiles as $tf) {
        $fullPath = $baseDir . '/' . $tf;
        assert(file_exists($fullPath), "Template {$fullPath} must exist");
        assert(filesize($fullPath) > 50, "Template {$fullPath} must not be empty");
    }
}

echo "   ✓ All 28 required template files across the 4 themes verified on disk!\n";

// -----------------------------------------------------------------------------
// 3. One-Pager Theme Template Rendering & Feature Verification
// -----------------------------------------------------------------------------
echo "3. Testing One-Pager Starter Theme Features (Smooth Scroll, Hero, Honeypot Form)...\n";

$themeService->setActive('one-pager');
$activeTheme = $themeService->getActive();
assert($activeTheme->getSlug() === 'one-pager' || $activeTheme->getSlug() === 'default');

$context = [
    'siteTitle'    => 'One-Pager Agency',
    'title'        => 'Startseite',
    'theme'        => $activeTheme,
    'themeService' => $themeService,
    'contentHtml'  => '<div class="test-block">Custom Block Content</div>',
    'navPages'     => [],
    'seoTags'      => '<meta name="description" content="One-Pager Test">',
    'csrfField'    => Csrf::field(),
    'csrfToken'    => Csrf::token(),
];

$homeHtml = $themeService->renderTemplate('home', $context);
assert($homeHtml !== null && is_string($homeHtml), "One-Pager home template must render HTML");
assert(str_contains($homeHtml, 'scroll-smooth'), "One-Pager must have scroll-smooth class");
assert(str_contains($homeHtml, 'id="hero"'), "One-Pager must have #hero section");
assert(str_contains($homeHtml, 'id="features"'), "One-Pager must have #features section");
assert(str_contains($homeHtml, 'id="services"'), "One-Pager must have #services section");
assert(str_contains($homeHtml, 'id="testimonials"'), "One-Pager must have #testimonials section");
assert(str_contains($homeHtml, 'id="faq"'), "One-Pager must have #faq accordion section");
assert(str_contains($homeHtml, 'id="contact"'), "One-Pager must have #contact section");
assert(str_contains($homeHtml, '_hb_hp'), "One-Pager contact form must contain anti-spam honeypot field");
assert(str_contains($homeHtml, 'csrf_token'), "One-Pager contact form must contain CSRF token");

$pageHtml = $themeService->renderTemplate('page', $context);
assert($pageHtml !== null && str_contains($pageHtml, 'Custom Block Content'));

$notFoundHtml = $themeService->renderTemplate('404', $context);
assert($notFoundHtml !== null && str_contains($notFoundHtml, '404'));

echo "   ✓ One-Pager Theme rendered all sections, smooth scrolling and honeypot form verified!\n";

// -----------------------------------------------------------------------------
// 4. Clean Corporate Theme Features (Multi-Level Nav, Team & Case Studies CPT)
// -----------------------------------------------------------------------------
echo "4. Testing Clean Corporate Theme Features (Multi-Level Navigation, CPT Integration)...\n";

$themeService->setActive('corporate');
$corpTheme = $themeService->getActive();
assert($corpTheme->getSlug() === 'corporate');

// Insert a sample team member and case study in the test DB
$now = date('Y-m-d H:i:s');
$tmId = $db->insert('{prefix}posts', [
    'title'          => 'Dr. Miriam Vogt',
    'slug'           => 'miriam-vogt',
    'content_blocks' => '[]',
    'content_html'   => '<p>Chief AI Officer</p>',
    'post_type'      => 'team_member',
    'status'         => 'publish',
    'author_id'      => 1,
    'created_at'     => $now,
    'updated_at'     => $now,
    'published_at'   => $now,
]);
$db->insert('{prefix}meta_data', ['object_type' => 'post', 'object_id' => $tmId, 'meta_key' => 'position', 'meta_value' => 'Chief Technology Officer']);
$db->insert('{prefix}meta_data', ['object_type' => 'post', 'object_id' => $tmId, 'meta_key' => 'department', 'meta_value' => 'Software Engineering']);
$db->insert('{prefix}meta_data', ['object_type' => 'post', 'object_id' => $tmId, 'meta_key' => 'email', 'meta_value' => 'vogt@enterprise.de']);

$corpContext = [
    'siteTitle'    => 'Enterprise Solutions AG',
    'title'        => 'Startseite',
    'theme'        => $corpTheme,
    'themeService' => $themeService,
    'contentHtml'  => '',
    'navPages'     => [],
    'seoTags'      => '',
    'csrfField'    => Csrf::field(),
    'csrfToken'    => Csrf::token(),
];

$corpHomeHtml = $themeService->renderTemplate('home', $corpContext);
assert($corpHomeHtml !== null, "Corporate home template must render");
assert(str_contains($corpHomeHtml, 'aria-haspopup="true"'), "Corporate nav must have accessible ARIA dropdown attribute");
assert(str_contains($corpHomeHtml, 'aria-expanded'), "Corporate nav must have aria-expanded attribute");
assert(str_contains($corpHomeHtml, 'role="menu"'), "Corporate nav must have role='menu'");
assert(str_contains($corpHomeHtml, 'Miriam Vogt'), "Corporate home must display CPT-powered Team member from database");
assert(str_contains($corpHomeHtml, 'Software Engineering'), "Corporate home must display team department tag");
assert(str_contains($corpHomeHtml, 'Fallstudien') || str_contains($corpHomeHtml, 'Referenzen'), "Corporate home must feature case studies");

// Test dedicated /team template
$teamHtml = $themeService->renderTemplate('team', $corpContext);
assert($teamHtml !== null && str_contains($teamHtml, 'Miriam Vogt'), "Corporate team template must render team members");

// Test dedicated /case-studies template
$csHtml = $themeService->renderTemplate('case-studies', $corpContext);
assert($csHtml !== null && str_contains($csHtml, 'Fallstudien'), "Corporate case studies template must render portfolio");

echo "   ✓ Clean Corporate Theme multi-level navigation and CPT-powered Team/Case studies verified!\n";

// -----------------------------------------------------------------------------
// 5. Classic Real Estate Theme Features (CPT, Alpine Filterable Grid, Lightbox, Inquiry)
// -----------------------------------------------------------------------------
echo "5. Testing Classic Real Estate Theme (Alpine.js Filterable Grid, Lightbox, Inquiry Form)...\n";

$themeService->setActive('real-estate');
$reTheme = $themeService->getActive();
assert($reTheme->getSlug() === 'real-estate');

// Insert a sample property in the test DB
$propId = (int)$db->insert('{prefix}posts', [
    'title'          => 'Seevilla mit eigenem Bootssteg',
    'slug'           => 'seevilla-bootssteg',
    'content_blocks' => '[]',
    'content_html'   => '<p>Luxuriöse Seevilla in traumhafter Lage direkt am Ufer.</p>',
    'post_type'      => 'property',
    'status'         => 'publish',
    'author_id'      => 1,
    'created_at'     => $now,
    'updated_at'     => $now,
    'published_at'   => $now,
]);
$db->insert('{prefix}meta_data', ['object_type' => 'post', 'object_id' => $propId, 'meta_key' => 'price', 'meta_value' => '3200000']);
$db->insert('{prefix}meta_data', ['object_type' => 'post', 'object_id' => $propId, 'meta_key' => 'rooms', 'meta_value' => '7.0']);
$db->insert('{prefix}meta_data', ['object_type' => 'post', 'object_id' => $propId, 'meta_key' => 'living_space', 'meta_value' => '420']);
$db->insert('{prefix}meta_data', ['object_type' => 'post', 'object_id' => $propId, 'meta_key' => 'location', 'meta_value' => 'Starnberger See']);
$db->insert('{prefix}meta_data', ['object_type' => 'post', 'object_id' => $propId, 'meta_key' => 'property_type', 'meta_value' => 'Villa']);
$db->insert('{prefix}meta_data', ['object_type' => 'post', 'object_id' => $propId, 'meta_key' => 'status', 'meta_value' => 'Verfügbar']);
$db->insert('{prefix}meta_data', ['object_type' => 'post', 'object_id' => $propId, 'meta_key' => 'energy_class', 'meta_value' => 'A+']);

$reContext = [
    'siteTitle'    => 'Residenzen & Villen',
    'title'        => 'Startseite',
    'theme'        => $reTheme,
    'themeService' => $themeService,
    'contentHtml'  => '',
    'navPages'     => [],
    'seoTags'      => '',
    'csrfField'    => Csrf::field(),
    'csrfToken'    => Csrf::token(),
];

$reHomeHtml = $themeService->renderTemplate('home', $reContext);
assert($reHomeHtml !== null, "Real Estate home template must render");
assert(str_contains($reHomeHtml, 'filteredProperties'), "Real Estate home must contain Alpine.js filteredProperties computed expression");
assert(str_contains($reHomeHtml, 'filterLocation'), "Real Estate home must contain location filter control");
assert(str_contains($reHomeHtml, 'filterType'), "Real Estate home must contain property type filter control");
assert(str_contains($reHomeHtml, 'filterMaxPrice'), "Real Estate home must contain max price filter control");
assert(str_contains($reHomeHtml, 'lightboxOpen'), "Real Estate home must contain Gallery Lightbox modal state");
assert(str_contains($reHomeHtml, 'inquiryOpen'), "Real Estate home must contain Property Inquiry modal state");
assert(str_contains($reHomeHtml, 'Seevilla mit eigenem Bootssteg'), "Real Estate home must display DB property");
assert(str_contains($reHomeHtml, 'Starnberger See'), "Real Estate home must display DB location");

// Test single property template
$propPost = PostService::getInstance()->find((int)$propId);
$propContext = array_merge($reContext, [
    'post'  => $propPost,
    'title' => $propPost['title'],
]);
$propViewHtml = $themeService->renderTemplate('property', $propContext);
assert($propViewHtml !== null, "Property detail template must render");
assert(str_contains($propViewHtml, '3.200.000 €'), "Property detail template must format price in Euros");
assert(str_contains($propViewHtml, '420 m²'), "Property detail template must show living space");
assert(str_contains($propViewHtml, 'Klasse A+'), "Property detail template must show energy efficiency class");
assert(str_contains($propViewHtml, 'Exposé anfordern'), "Property detail template must contain inquiry form");

echo "   ✓ Classic Real Estate Theme Alpine filterable grid, lightbox modal and inquiry form verified!\n";

// -----------------------------------------------------------------------------
// 6. Modern Startup Theme Features (Dark/Light Mode, Interactive Pricing Toggle)
// -----------------------------------------------------------------------------
echo "6. Testing Modern Startup Theme (Dark/Light Toggle, Interactive Pricing Toggle)...\n";

$themeService->setActive('startup');
$startupTheme = $themeService->getActive();
assert($startupTheme->getSlug() === 'startup');

$startupContext = [
    'siteTitle'    => 'NovaCloud Engine',
    'title'        => 'Startseite',
    'theme'        => $startupTheme,
    'themeService' => $themeService,
    'contentHtml'  => '',
    'navPages'     => [],
    'seoTags'      => '',
    'csrfField'    => Csrf::field(),
    'csrfToken'    => Csrf::token(),
];

$startupHomeHtml = $themeService->renderTemplate('home', $startupContext);
assert($startupHomeHtml !== null, "Startup home template must render");
assert(str_contains($startupHomeHtml, 'toggleDarkMode'), "Startup header must contain Dark/Light mode toggle");
assert(str_contains($startupHomeHtml, 'isDark'), "Startup header must reactively track isDark state");
assert(str_contains($startupHomeHtml, 'yearly ?'), "Startup pricing table must have yearly/monthly toggle expression");
assert(str_contains($startupHomeHtml, 'Spare 20%'), "Startup pricing table must show discount badge");
assert(str_contains($startupHomeHtml, 'Beliebteste Wahl'), "Startup pricing must highlight the Pro plan");
assert(str_contains($startupHomeHtml, '/api/stripe/checkout'), "Startup Pro plan must link to Stripe checkout");

// Test standalone pricing page template
$pricingHtml = $themeService->renderTemplate('pricing', $startupContext);
assert($pricingHtml !== null, "Startup pricing template must render");
assert(str_contains($pricingHtml, 'Professional'), "Pricing template must display Professional tier");
assert(str_contains($pricingHtml, 'Starter'), "Pricing template must display Starter tier");
assert(str_contains($pricingHtml, 'Enterprise'), "Pricing template must display Enterprise tier");

echo "   ✓ Modern Startup Theme Dark/Light toggle and interactive pricing tables verified!\n";

// -----------------------------------------------------------------------------
// 7. Universal Contact & Inquiry Controller (Honeypot + CSRF Protection)
// -----------------------------------------------------------------------------
echo "7. Testing Universal Contact Controller (Honeypot Spam Defense & CSRF Validation)...\n";

$contactController = new ContactController();

// A. Test invalid CSRF
$badCsrfRequest = new Request(
    server: ['REQUEST_METHOD' => 'POST', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
    post: ['name' => 'Spammer', 'email' => 'spam@bot.com', 'message' => 'Spam content', 'csrf_token' => 'invalid-token-12345']
);
$resCsrf = $contactController->submit($badCsrfRequest);
assert($resCsrf->getStatusCode() === 403, "Invalid CSRF token must return 403 Forbidden");

// B. Test Honeypot Anti-Spam: Bot fills out hidden honeypot field
$validToken = Csrf::token();
$honeypotSpamRequest = new Request(
    server: ['REQUEST_METHOD' => 'POST', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
    post: [
        'name'       => 'Automated Bot',
        'email'      => 'bot@spamnetwork.com',
        'message'    => 'Buy cheap pills online',
        '_hb_hp'     => 'http://spamsite.com', // Filled by bot!
        'csrf_token' => $validToken,
    ]
);

$resHp = $contactController->submit($honeypotSpamRequest);
assert($resHp->getStatusCode() === 200, "Honeypot spam must return 200 silently to deceive bot");
$hpData = json_decode($resHp->getContent(), true);
assert($hpData['success'] === true);

// Verify that mail was NOT sent for the honeypot spam bot
$mailLogFile = HEARTBEAT_STORAGE . '/logs/mail.log';
$logBefore = file_exists($mailLogFile) ? (string)file_get_contents($mailLogFile) : '';
assert(!str_contains($logBefore, 'bot@spamnetwork.com'), "Honeypot spam message must NOT be dispatched via Mailer");

// C. Test Legitimate Contact Submission
$legitRequest = new Request(
    server: ['REQUEST_METHOD' => 'POST', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
    post: [
        'name'       => 'Dr. Johann Schmidt',
        'email'      => 'johann.schmidt@testkunde.de',
        'subject'    => 'Neues Großprojekt',
        'message'    => 'Wir möchten gerne ein Beratungsgespräch für unseren Relaunch vereinbaren.',
        '_hb_hp'     => '', // Empty honeypot = real human user!
        'csrf_token' => $validToken,
    ]
);

$resLegit = $contactController->submit($legitRequest);
assert($resLegit->getStatusCode() === 200, "Legitimate contact submission must succeed");
$legitData = json_decode($resLegit->getContent(), true);
assert($legitData['success'] === true, "Contact response must indicate success");

// D. Test Property-Specific Inquiry Submission
$propInquiryRequest = new Request(
    server: ['REQUEST_METHOD' => 'POST', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'],
    post: [
        'name'           => 'Familie Sommer',
        'email'          => 'sommer@web.de',
        'message'        => 'Bitte senden Sie uns das Exposé zu.',
        'property_id'    => '101',
        'property_title' => 'Seevilla mit Bootssteg',
        '_hb_hp'         => '',
        'csrf_token'     => $validToken,
    ]
);

$resPropInq = $contactController->submit($propInquiryRequest);
assert($resPropInq->getStatusCode() === 200);

echo "   ✓ Contact Controller CSRF validation, Honeypot bot interception and property inquiries verified!\n";

// -----------------------------------------------------------------------------
// 8. Live Tailwind JIT CSS Extraction with Theme Template Scanning
// -----------------------------------------------------------------------------
echo "8. Testing Live Tailwind JIT CSS Extraction from Theme Templates...\n";

$compiler = CssCompiler::getInstance();

// Test that compiler extracts classes from theme templates
$corpThemeObj = $themeService->get('corporate');
assert($corpThemeObj !== null);

$extractedThemeClasses = $compiler->extractThemeClasses($corpThemeObj);
assert(!empty($extractedThemeClasses), "extractThemeClasses must find utility classes in Corporate templates");
assert(in_array('flex', $extractedThemeClasses, true) || in_array('text-white', $extractedThemeClasses, true));

// Test compilation creates static bundle containing theme design tokens and classes
$compiledCss = $compiler->compileForTheme($corpThemeObj);
assert(str_contains($compiledCss, '--hb-primary: #2563eb'), "Compiled CSS must contain corporate primary color token");
assert(str_contains($compiledCss, '--hb-bg: #0a0f1d'), "Compiled CSS must contain corporate background color token");

$bundlePath = $compiler->getCssFilePath('corporate');
assert(file_exists($bundlePath), "Compiled theme CSS bundle must exist on disk");

echo "   ✓ Live Tailwind JIT CSS compilation including theme template classes verified!\n";

// -----------------------------------------------------------------------------
// 9. Production Release Builder & ZIP Verification
// -----------------------------------------------------------------------------
echo "9. Testing Production Release Builder (scripts/build-release.js & scripts/build-release.php)...\n";

// Run pure PHP release builder
require_once HEARTBEAT_ROOT . '/scripts/build-release.php';

$zipPath = HEARTBEAT_ROOT . '/dist/heartbeat-cms-install.zip';
assert(file_exists($zipPath), "Production release ZIP must exist at dist/heartbeat-cms-install.zip");
assert(filesize($zipPath) > 100000, "Release ZIP must be at least 100 KB");

// Open and audit ZIP contents using ZipArchive
$zip = new ZipArchive();
$openStatus = $zip->open($zipPath);
if ($openStatus !== true) {
    throw new RuntimeException("Failed to open production release ZIP at {$zipPath}, status: " . var_export($openStatus, true));
}

// Audit required files in ZIP
$requiredZipEntries = [
    '.htaccess',
    'router.php',
    'composer.json',
    'core/App.php',
    'core/Autoloader.php',
    'core/Bootstrap.php',
    'core/Installer/InstallerService.php',
    'core/Http/ContactController.php',
    'themes/default/theme.json',
    'themes/default/templates/home.php',
    'themes/corporate/theme.json',
    'themes/corporate/templates/home.php',
    'themes/real-estate/theme.json',
    'themes/real-estate/templates/home.php',
    'themes/startup/theme.json',
    'themes/startup/templates/home.php',
    'public/.htaccess',
    'public/index.php',
    'public/assets/css/installer.css',
    'public/assets/js/alpine.min.js',
    'uploads/.htaccess',
];

foreach ($requiredZipEntries as $entry) {
    if ($zip->locateName($entry) === false) {
        throw new RuntimeException("Release ZIP is missing required entry: '{$entry}'");
    }
}

// Ensure development secrets and state files are strictly EXCLUDED from release ZIP
$forbiddenEntries = [
    'config/installed.lock',
    'config/env.php',
    'storage/database.sqlite',
    'storage/test_phase6.sqlite',
];

foreach ($forbiddenEntries as $forbidden) {
    if ($zip->locateName($forbidden) !== false) {
        throw new RuntimeException("Release ZIP must NEVER contain '{$forbidden}'");
    }
}

$numFiles = $zip->numFiles;
$zip->close();

echo "   ✓ Production release ZIP verified: {$numFiles} files, clean install state, all 4 themes bundled!\n";

// -----------------------------------------------------------------------------
// 10. Clean up Test Artifacts
// -----------------------------------------------------------------------------
if (file_exists($testDbFile)) {
    @unlink($testDbFile);
}

echo "\n======================================================================\n";
echo "🎉 ALL PHASE 6 VERIFICATION TESTS PASSED SUCCESSFULLY! (100% OK)\n";
echo "======================================================================\n";
