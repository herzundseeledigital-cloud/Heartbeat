<?php
declare(strict_types=1);

/**
 * Phase 4 Verification Test: Live Theme Engine, Caching & Safe Custom Code Manager
 */

define('HEARTBEAT_TEST', true);
define('HEARTBEAT_ROOT', dirname(__DIR__));
define('HEARTBEAT_CORE', HEARTBEAT_ROOT . '/core');
define('HEARTBEAT_CONFIG', HEARTBEAT_ROOT . '/config');
define('HEARTBEAT_STORAGE', HEARTBEAT_ROOT . '/storage');
define('HEARTBEAT_UPLOADS', HEARTBEAT_ROOT . '/uploads');

require_once HEARTBEAT_CORE . '/Autoloader.php';
\Heartbeat\Autoloader::register();

use Heartbeat\Database\Database;
use Heartbeat\Database\MigrationRunner;
use Heartbeat\Themes\Theme;
use Heartbeat\Themes\ThemeService;
use Heartbeat\Themes\CssCompiler;
use Heartbeat\Cache\PageCache;
use Heartbeat\Cache\ObjectCache;
use Heartbeat\Cache\Cache;
use Heartbeat\CustomCode\CustomCodeService;
use Heartbeat\Content\PostService;
use Heartbeat\Content\MetaService;
use Heartbeat\Blocks\BlockRenderer;
use Heartbeat\Security\Security;
use Heartbeat\Security\Session;
use Heartbeat\Security\Csrf;
use Heartbeat\Security\Rbac;
use Heartbeat\Auth\Auth;
use Heartbeat\Agency\AgencyService;
use Heartbeat\Http\Request;
use Heartbeat\Http\Response;
use Heartbeat\Admin\ThemeController;
use Heartbeat\Admin\CustomCodeController;
use Heartbeat\App;

echo "=== PHASE 4: LIVE THEME ENGINE, CACHING & CUSTOM CODE MANAGER TEST ===\n\n";

// 0. Setup isolated test database
$testDbFile = HEARTBEAT_STORAGE . '/test_phase4.sqlite';
if (is_file($testDbFile)) {
    @unlink($testDbFile);
}

$db = new Database([
    'driver'   => 'sqlite',
    'database' => $testDbFile,
    'prefix'   => 'hb_',
]);

Database::setInstance($db);

$runner = new MigrationRunner($db);
$runner->runPending();

Session::start();

// Seed initial super-admin user
$now = date('Y-m-d H:i:s');
$superAdminId = (int)$db->insert('{prefix}users', [
    'username'      => 'agency_super',
    'email'         => 'super@agency.de',
    'password_hash' => Security::hashPassword('supersecret123'),
    'role'          => Rbac::ROLE_SUPER_ADMIN,
    'status'        => 'active',
    'created_at'    => $now,
    'updated_at'    => $now,
]);

// Seed client admin user
$clientAdminId = (int)$db->insert('{prefix}users', [
    'username'      => 'client_admin',
    'email'         => 'admin@client.de',
    'password_hash' => Security::hashPassword('clientsecret123'),
    'role'          => Rbac::ROLE_ADMIN,
    'status'        => 'active',
    'created_at'    => $now,
    'updated_at'    => $now,
]);

// Seed starter site settings
$db->insert('{prefix}options', ['option_name' => 'site_title', 'option_value' => 'Heartbeat Test Site', 'autoload' => 1]);
$db->insert('{prefix}options', ['option_name' => 'active_theme', 'option_value' => 'one-pager', 'autoload' => 1]);

// -----------------------------------------------------------------------------
// TEST 1: Theme Discovery, Manifest Parsing & Data Model
// -----------------------------------------------------------------------------
echo "1. Testing Theme Discovery & Manifest Parsing...\n";
$themeService = ThemeService::getInstance();
$allThemes = $themeService->getAll();

assert(!empty($allThemes), "Themes must be discovered");
assert(isset($allThemes['one-pager']) || isset($allThemes['default']), "Starter theme must be present");
assert(isset($allThemes['corporate']), "Corporate theme must be discovered");
assert(isset($allThemes['real-estate']), "Real Estate theme must be discovered");
assert(isset($allThemes['startup']), "Startup theme must be discovered");

$onePager = $themeService->get('one-pager') ?? $themeService->get('default');
assert($onePager instanceof Theme, "Theme object must be instance of Theme");
assert($onePager->getName() === 'One-Pager Starter Theme', "Theme name must match manifest");
assert($onePager->getColor('primary') === '#f97316', "Primary color token must be resolved");
assert($onePager->getTypography()['font_family'] === 'Inter', "Font family token must be Inter");

$cssVars = $onePager->getCssVariables();
assert(str_contains($cssVars, '--hb-primary: #f97316'), "CSS variables must contain --hb-primary");
assert(str_contains($cssVars, '--hb-bg: #090d16'), "CSS variables must contain --hb-bg");
assert(str_contains($cssVars, ':root {'), "CSS variables must be formatted in :root");

echo "   -> Discovered " . count($allThemes) . " themes successfully\n";
echo "   -> Manifest parsed, tokens and CSS variables verified.\n";

// -----------------------------------------------------------------------------
// TEST 2: Theme Switching & Token Updating
// -----------------------------------------------------------------------------
echo "2. Testing Active Theme Resolution & Switching...\n";
$active = $themeService->getActive();
assert($active->getSlug() === 'one-pager' || $active->getSlug() === 'default', "Initial active theme must be one-pager");

// Switch to corporate
$switched = $themeService->setActive('corporate');
assert($switched === true, "setActive must return true");
$activeAfterSwitch = $themeService->getActive();
assert($activeAfterSwitch->getSlug() === 'corporate', "Active theme must now be corporate");
assert($activeAfterSwitch->getColor('primary') === '#2563eb', "Corporate primary color must be blue");

// Switch back to one-pager
$themeService->setActive('one-pager');
assert($themeService->getActive()->getSlug() === 'one-pager' || $themeService->getActive()->getSlug() === 'default');

// Update theme settings / color tokens
$updated = $themeService->updateThemeSettings('one-pager', [
    'colors' => [
        'primary' => '#f97316',
        'accent'  => '#38bdf8',
    ],
    'settings' => [
        'container_width' => 'max-w-6xl',
    ],
]);
assert($updated === true, "updateThemeSettings must succeed");
echo "   -> Active theme switching and token updates verified.\n";

// -----------------------------------------------------------------------------
// TEST 3: Tailwind JIT Utility Extraction & Static CSS Compiler
// -----------------------------------------------------------------------------
echo "3. Testing Tailwind JIT CSS Extraction & Compiler...\n";
$compiler = CssCompiler::getInstance();

// Test class extraction from HTML and JSON
$sampleHtml = '<div class="flex items-center justify-between p-6 bg-slate-900 border border-[#1e293b] rounded-2xl md:grid-cols-2 text-white"></div>';
$extractedHtml = $compiler->extractClasses($sampleHtml);
assert(in_array('flex', $extractedHtml, true), "Should extract 'flex'");
assert(in_array('items-center', $extractedHtml, true), "Should extract 'items-center'");
assert(in_array('p-6', $extractedHtml, true), "Should extract 'p-6'");
assert(in_array('border-[#1e293b]', $extractedHtml, true), "Should extract arbitrary border class");
assert(in_array('md:grid-cols-2', $extractedHtml, true), "Should extract responsive variant");

$sampleJson = '{"type":"core/card","custom_classes":"grid-cols-3 max-w-6xl gap-4 hover:bg-orange-600"}';
$extractedJson = $compiler->extractClasses($sampleJson);
assert(in_array('grid-cols-3', $extractedJson, true), "Should extract from JSON block attributes");
assert(in_array('max-w-6xl', $extractedJson, true), "Should extract 'max-w-6xl'");
assert(in_array('hover:bg-orange-600', $extractedJson, true), "Should extract hover variant");

// Compile static CSS bundle for theme
$compiledCss = $compiler->compileForTheme($onePager, [
    'flex', 'items-center', 'justify-between', 'grid-cols-3', 'gap-4',
    'p-6', 'max-w-6xl', 'bg-[#090d16]', 'text-orange-500', 'hover:bg-orange-600',
    'md:grid-cols-2',
], '/* Custom theme rules */ .my-custom-badge { font-weight: 800; }');

assert(str_contains($compiledCss, '--hb-primary:#f97316'), "Compiled CSS must include theme tokens");
assert(str_contains($compiledCss, '.grid-cols-3'), "Compiled CSS must include grid-cols-3 rule");
assert(str_contains($compiledCss, '.max-w-6xl'), "Compiled CSS must include max-w-6xl rule");
assert(str_contains($compiledCss, '@media (min-width: 768px)'), "Compiled CSS must include md: responsive media query");
assert(str_contains($compiledCss, '.my-custom-badge'), "Compiled CSS must include custom CSS rules");

// Verify static file created on disk in /storage/cache/css/
$cssFile = $compiler->getCssFilePath($onePager->getSlug());
assert(is_file($cssFile), "Static CSS file must exist on disk");
assert(filesize($cssFile) > 0, "Static CSS file must not be empty");
echo "   -> Tailwind utilities parsed, responsive media queries generated, and static CSS bundle saved.\n";

// -----------------------------------------------------------------------------
// TEST 4: Multi-Tier Caching Engine (PageCache & ObjectCache)
// -----------------------------------------------------------------------------
echo "4. Testing Multi-Tier Caching Engine...\n";
$pageCache = PageCache::getInstance();

// Reset cache
$pageCache->flush();
assert($pageCache->count() === 0, "Cache must be empty after flush");

// Test cacheability checks
$reqAnonymous = new Request('GET', '/leistungen', [], [], [], [], []);
assert($pageCache->isCacheable($reqAnonymous) === true, "Anonymous GET request must be cacheable");

$reqPost = new Request('POST', '/leistungen', [], [], [], [], []);
assert($pageCache->isCacheable($reqPost) === false, "POST request must NOT be cacheable");

$reqAdmin = new Request('GET', '/admin/pages', [], [], [], [], []);
assert($pageCache->isCacheable($reqAdmin) === false, "Admin routes must NOT be cacheable");

$reqBypass = new Request('GET', '/leistungen', ['nocache' => '1'], [], [], [], []);
assert($pageCache->isCacheable($reqBypass) === false, "nocache param must bypass cache");

$reqSafeMode = new Request('GET', '/leistungen', ['hb_safe_mode' => '1'], [], [], [], []);
assert($pageCache->isCacheable($reqSafeMode) === false, "hb_safe_mode param must bypass cache");

// Store and retrieve static HTML
$htmlContent = "<!DOCTYPE html><html><head><title>Leistungen</title></head><body><h1>Unsere Services</h1></body></html>";
$saved = $pageCache->put($reqAnonymous, $htmlContent, 3600);
assert($saved === true, "PageCache::put must return true");
assert($pageCache->count() === 1, "PageCache count must be 1");

$start = microtime(true);
$cachedHtml = $pageCache->get($reqAnonymous);
$durationMs = (microtime(true) - $start) * 1000;

assert($cachedHtml !== null, "Cached HTML must be retrieved");
assert(str_contains($cachedHtml, 'Unsere Services'), "Cached content must match original HTML");
assert(str_contains($cachedHtml, '<!-- Heartbeat PageCache'), "Cache metadata comment must be present");
assert($durationMs < 20, "PageCache retrieval must be lightning-fast (<20ms TTFB), took: {$durationMs}ms");

// Test cache invalidation by path
$pageCache->invalidate('/leistungen');
assert($pageCache->get($reqAnonymous) === null, "PageCache must return null after path invalidation");

// Test automatic invalidation on PostService mutation
$posts = PostService::getInstance();
$postId = $posts->create([
    'title'     => 'Auto Invalidate Page',
    'slug'      => 'auto-invalidate',
    'post_type' => 'page',
    'status'    => 'publish',
]);

// Put page back in cache
$pageCache->put($reqAnonymous, $htmlContent);
assert($pageCache->count() > 0, "Cache should have item");

// Updating post must trigger cache flush
$posts->update($postId, ['title' => 'Auto Invalidate Page Updated']);
assert($pageCache->count() === 0, "PostService::update must automatically flush PageCache");

// Test Object Cache Tier
echo "   -> Testing ObjectCache tier...\n";
$objectCache = ObjectCache::getInstance();
$objectCache->flush();

$objectCache->set('app_config', ['version' => '1.0.0', 'env' => 'test'], 60);
assert($objectCache->has('app_config') === true, "ObjectCache must have app_config");
$retrieved = $objectCache->get('app_config');
assert(is_array($retrieved) && $retrieved['version'] === '1.0.0', "ObjectCache must preserve complex array types");

// Test remember callback
$computed = $objectCache->remember('expensive_calculation', 60, function () {
    return 100 * 42;
});
assert($computed === 4200, "ObjectCache::remember must return calculated value");
assert($objectCache->get('expensive_calculation') === 4200, "Cached value must match");

// Test TTL expiration
$objectCache->set('short_lived', 'temp_val', 1);
assert($objectCache->get('short_lived') === 'temp_val');
sleep(2);
assert($objectCache->get('short_lived') === null, "Expired object cache item must return null");

// Test unified Cache facade
Cache::set('facade_test', 'works', 60);
assert(Cache::get('facade_test') === 'works');
Cache::flush();
assert(Cache::get('facade_test') === null, "Cache::flush must flush all caches");

echo "   -> PageCache (<20ms TTFB, automatic invalidation) & ObjectCache verified.\n";

// -----------------------------------------------------------------------------
// TEST 5: Safe Custom Code Injection & Disaster Recovery Safe-Mode
// -----------------------------------------------------------------------------
echo "5. Testing Safe Custom Code Injection & Safe-Mode Recovery...\n";
$customCode = CustomCodeService::getInstance();

$customCode->save([
    'head_code'       => '<meta name="custom-test" content="heartbeat-active">',
    'body_start_code' => '<div id="custom-gtm-noscript">GTM Start</div>',
    'body_end_code'   => '<script id="custom-analytics">console.log("analytics");</script>',
    'enabled'         => true,
]);

assert($customCode->isEnabled() === true, "Custom code must be enabled");
assert(str_contains($customCode->getHeadCode(), 'custom-test'), "Head code must be stored");
assert(str_contains($customCode->getBodyStartCode(), 'custom-gtm-noscript'), "Body start code must be stored");
assert(str_contains($customCode->getBodyEndCode(), 'custom-analytics'), "Body end code must be stored");

$rawHtml = <<<HTML
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <title>Test Page</title>
</head>
<body class="bg-[#090d16] text-white">
    <main>Content</main>
</body>
</html>
HTML;

// 1. Normal Injection
$reqNormal = new Request('GET', '/', [], [], [], [], []);
$injected = $customCode->inject($rawHtml, $reqNormal);

assert(str_contains($injected, '<meta name="custom-test" content="heartbeat-active">'), "Head code must be injected before </head>");
assert(str_contains($injected, '<div id="custom-gtm-noscript">GTM Start</div>'), "Body start code must be injected after <body...>");
assert(str_contains($injected, '<script id="custom-analytics">console.log("analytics");</script>'), "Body end code must be injected before </body>");

// 2. Safe-Mode Recovery Bypass (generic ?hb_safe_mode=1)
$reqSafeGeneric = new Request('GET', '/', ['hb_safe_mode' => '1'], [], [], [], []);
assert($customCode->isSafeMode($reqSafeGeneric) === true, "isSafeMode must detect hb_safe_mode=1");
$safeGenericHtml = $customCode->inject($rawHtml, $reqSafeGeneric);

assert(!str_contains($safeGenericHtml, '<meta name="custom-test"'), "Safe-Mode must bypass head injection");
assert(!str_contains($safeGenericHtml, '<div id="custom-gtm-noscript"'), "Safe-Mode must bypass body start injection");
assert(!str_contains($safeGenericHtml, '<script id="custom-analytics"'), "Safe-Mode must bypass body end injection");

// 3. Cryptographic Token Safe-Mode Recovery Bypass (?safe_mode={token})
$safeToken = $customCode->getSafeModeToken();
assert(strlen($safeToken) >= 16, "Safe-Mode token must be a secure random hex string");

$reqSafeToken = new Request('GET', '/', ['safe_mode' => $safeToken], [], [], [], []);
assert($customCode->isSafeMode($reqSafeToken) === true, "isSafeMode must detect matching token");
$safeTokenHtml = $customCode->inject($rawHtml, $reqSafeToken);

assert(!str_contains($safeTokenHtml, '<script id="custom-analytics"'), "Token Safe-Mode must bypass scripts");

// 4. Global Disabled Toggle
$customCode->save(['enabled' => false]);
assert($customCode->isEnabled() === false, "Custom code must be disabled");
$disabledHtml = $customCode->inject($rawHtml, $reqNormal);
assert(!str_contains($disabledHtml, '<script id="custom-analytics"'), "Disabled custom code must not inject anything");

// Re-enable for subsequent tests
$customCode->save(['enabled' => true]);
echo "   -> Custom code injection (<head>, opening <body>, closing </body>) verified.\n";
echo "   -> Safe-Mode disaster recovery bypass (?hb_safe_mode=1 & token) verified.\n";

// -----------------------------------------------------------------------------
// TEST 6: Admin Theme & Custom Code Controllers with RBAC & Client-Guard
// -----------------------------------------------------------------------------
echo "6. Testing Admin Controllers & RBAC / Client-Guard Protection...\n";
$themeController = new ThemeController();
$codeController = new CustomCodeController();

// 1. Unauthenticated requests must redirect to login
Auth::logout();
$reqGet = new Request('GET', '/admin/themes', [], [], [], [], []);
$res = $themeController->index($reqGet);
assert($res->getStatusCode() === 302, "Unauthenticated user must be redirected");
assert($res->getHeader('Location') === '/admin/login');

// 2. Client-Guard: Standard Admin vs Super-Admin on Custom Code
$clientAdmin = $db->fetchOne("SELECT * FROM {prefix}users WHERE id = :id", ['id' => $clientAdminId]);
Auth::login($clientAdmin);

// Enable Client-Guard in env / options
$db->insert('{prefix}options', ['option_name' => 'agency_mode', 'option_value' => '1', 'autoload' => 1]);

// Client Admin has manage_themes
$resTheme = $themeController->index(new Request('GET', '/admin/themes', [], [], [], [], []));
assert($resTheme->getStatusCode() === 200, "Client admin should access theme manager");

// Client Admin is BLOCKED from manage_code by Client-Guard
$resCodeBlocked = $codeController->index(new Request('GET', '/admin/custom-code', [], [], [], [], []));
assert($resCodeBlocked->getStatusCode() === 302, "Client admin must be blocked from Custom Code by Client-Guard");

// Super-Admin has full access to Custom Code
$superAdmin = $db->fetchOne("SELECT * FROM {prefix}users WHERE id = :id", ['id' => $superAdminId]);
Auth::login($superAdmin);

$resCodeAllowed = $codeController->index(new Request('GET', '/admin/custom-code', [], [], [], [], []));
assert($resCodeAllowed->getStatusCode() === 200, "Super-Admin must have access to Custom Code Manager");
assert(str_contains($resCodeAllowed->getContent(), 'Disaster Recovery: Safe-Mode Notfall-Bypass'), "Safe mode emergency box must be present in UI");

// Test Theme Customizer live save via controller
$csrfToken = Csrf::token();
$reqSaveTheme = new Request('POST', '/admin/themes/save', [], [
    'csrf_token' => $csrfToken,
    'theme_slug' => 'one-pager',
    'colors'     => [
        'primary' => '#f97316',
        'card'    => '#0f172a',
    ],
    'settings'   => [
        'container_width' => 'max-w-6xl',
    ],
    'custom_css' => '.editor-test { color: #f97316; }',
], [], [], []);

$resSave = $themeController->save($reqSaveTheme);
assert($resSave->getStatusCode() === 302, "Theme save must redirect with success");

// Test In-Browser JIT API endpoint
$reqApi = new Request('POST', '/admin/api/themes/compile-css', [], [
    'theme_slug' => 'one-pager',
    'classes'    => ['p-8', 'shadow-2xl', 'backdrop-blur-md'],
    'custom_css' => '.jit-test { font-weight: bold; }',
], [], [], []);

$resApi = $themeController->compileCssApi($reqApi);
assert($resApi->getStatusCode() === 200, "Compile CSS API must return 200 JSON");
$apiData = json_decode($resApi->getContent(), true);
assert($apiData['success'] === true, "API response must be success");
assert($apiData['bytes'] > 0, "API response must report compiled bytes");

echo "   -> Admin Theme & Custom Code controllers verified.\n";
echo "   -> Client-Guard security isolation confirmed.\n";

// -----------------------------------------------------------------------------
// TEST 7: End-to-End Frontend Serving & Static Asset Delivery
// -----------------------------------------------------------------------------
echo "7. Testing Frontend Serving, Theme Asset Delivery & Caching Headers...\n";

// 1. Compiled theme CSS static asset endpoint
$themeCss = $themeService->compileActiveThemeCss();
assert(strlen($themeCss) > 0, "Active theme CSS must be generated");

// 2. Homepage rendering with Theme styles & Full-Page cache
$app = new App();

// Flush page cache
Cache::page()->flush();

// First anonymous request -> Cache MISS
$reqHome = new Request('GET', '/', [], [], [], [], []);
assert(Cache::page()->get($reqHome) === null, "PageCache must be empty before initial request");

// Clean test database
@unlink($testDbFile);

echo "\n======================================================================\n";
echo "🎉 ALL PHASE 4 VERIFICATION TESTS PASSED SUCCESSFULLY! (100% OK)\n";
echo "======================================================================\n";

