<?php
declare(strict_types=1);

/**
 * Phase 3 Verification Test: Flexible Content Engine (CPT, JSON Block Builder & Media Manager)
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
use Heartbeat\Content\MetaService;
use Heartbeat\Content\PostType;
use Heartbeat\Content\PostTypeRegistry;
use Heartbeat\Content\PostService;
use Heartbeat\Blocks\BlockRegistry;
use Heartbeat\Blocks\BlockRenderer;
use Heartbeat\Media\MediaService;
use Heartbeat\Security\Security;
use Heartbeat\Security\Session;
use Heartbeat\Security\Csrf;
use Heartbeat\Security\Rbac;
use Heartbeat\Auth\Auth;
use Heartbeat\Http\Request;
use Heartbeat\Http\Response;
use Heartbeat\Admin\ContentController;
use Heartbeat\Admin\MediaController;
use Heartbeat\App;

echo "=== PHASE 3: FLEXIBLE CONTENT ENGINE, CPT & JSON BLOCK BUILDER TEST ===\n\n";

// 0. Setup in-memory / temporary test database
$testDbFile = HEARTBEAT_STORAGE . '/test_phase3.sqlite';
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

// Start session
Session::start();

// Seed initial super-admin user for auth tests
$now = date('Y-m-d H:i:s');
$adminId = (int)$db->insert('{prefix}users', [
    'username'      => 'agency_admin',
    'email'         => 'admin@agency.de',
    'password_hash' => Security::hashPassword('supersecret123'),
    'role'          => Rbac::ROLE_SUPER_ADMIN,
    'status'        => 'active',
    'created_at'    => $now,
    'updated_at'    => $now,
]);

// Seed editor user
$editorId = (int)$db->insert('{prefix}users', [
    'username'      => 'content_editor',
    'email'         => 'editor@agency.de',
    'password_hash' => Security::hashPassword('editor12345'),
    'role'          => Rbac::ROLE_EDITOR,
    'status'        => 'active',
    'created_at'    => $now,
    'updated_at'    => $now,
]);

// Seed frontend member user
$memberId = (int)$db->insert('{prefix}users', [
    'username'      => 'premium_member',
    'email'         => 'member@client.de',
    'password_hash' => Security::hashPassword('member12345'),
    'role'          => Rbac::ROLE_MEMBER,
    'status'        => 'active',
    'created_at'    => $now,
    'updated_at'    => $now,
]);

// -----------------------------------------------------------------------------
// TEST 1: Custom Meta Data Service
// -----------------------------------------------------------------------------
echo "1. Testing Custom Meta Data Service (MetaService)...\n";
$meta = new MetaService($db);

// Set and get scalar values
$meta->set('post', 10, 'custom_color', '#f97316');
$meta->set('post', 10, 'view_count', '142');
$meta->set('post', 10, 'is_featured', true);

assert($meta->get('post', 10, 'custom_color') === '#f97316', 'Meta string value mismatch');
assert((int)$meta->get('post', 10, 'view_count') === 142, 'Meta integer value mismatch');
assert($meta->get('post', 10, 'is_featured') === true, 'Meta boolean value mismatch');

// Set and get array / JSON values
$features = ['balcony' => true, 'parking' => 'underground', 'rooms' => 3.5];
$meta->set('post', 10, 'property_features', $features);

$retrievedFeatures = $meta->get('post', 10, 'property_features');
assert(is_array($retrievedFeatures) && $retrievedFeatures['rooms'] === 3.5, 'Meta JSON array decode failed');

// Test getAll
$allMeta = $meta->getAll('post', 10);
assert(count($allMeta) === 4, 'Meta getAll count mismatch');
assert(isset($allMeta['custom_color'], $allMeta['property_features']), 'Meta getAll missing expected keys');

// Test delete specific key
$meta->delete('post', 10, 'custom_color');
assert($meta->get('post', 10, 'custom_color') === null, 'Meta delete key failed');

// Test delete all
$meta->delete('post', 10);
assert(empty($meta->getAll('post', 10)), 'Meta delete all failed');

echo "   ✓ MetaService scalar, JSON, and bulk CRUD verified!\n";

// -----------------------------------------------------------------------------
// TEST 2: Custom Post Type Engine (PostType & PostTypeRegistry)
// -----------------------------------------------------------------------------
echo "2. Testing Custom Post Type Engine (PostType & PostTypeRegistry)...\n";
$registry = PostTypeRegistry::getInstance();

// Verify built-ins
assert($registry->has('page'), "Registry missing 'page' type");
assert($registry->has('post'), "Registry missing 'post' type");
assert($registry->has('property'), "Registry missing 'property' CPT");
assert($registry->has('team_member'), "Registry missing 'team_member' CPT");
assert($registry->has('case_study'), "Registry missing 'case_study' CPT");

$propertyCpt = $registry->get('property');
assert($propertyCpt !== null, 'Property CPT lookup failed');
assert($propertyCpt->getSingular() === 'Immobilie', 'Property singular label mismatch');
assert($propertyCpt->getPlural() === 'Immobilien', 'Property plural label mismatch');
assert($propertyCpt->getSlugPrefix() === 'immobilien', 'Property slug prefix mismatch');
assert($propertyCpt->supports('title'), 'Property supports title failed');
assert($propertyCpt->supports('meta'), 'Property supports meta failed');

// Verify meta fields blueprint
$metaFields = $propertyCpt->getMetaFields();
$fieldKeys = array_column($metaFields, 'key');
assert(in_array('price', $fieldKeys, true), 'Property missing price meta field');
assert(in_array('rooms', $fieldKeys, true), 'Property missing rooms meta field');
assert(in_array('living_space', $fieldKeys, true), 'Property missing living_space meta field');

// Test dynamic CPT registration from blueprint
$serviceCpt = $registry->registerFromBlueprint([
    'post_type'   => 'service',
    'singular'    => 'Dienstleistung',
    'plural'      => 'Leistungen',
    'icon'        => 'cog',
    'slug_prefix' => 'leistungen',
    'meta_fields' => [
        ['key' => 'hourly_rate', 'label' => 'Stundensatz (€)', 'type' => 'number'],
    ],
]);
assert($registry->has('service'), 'Dynamic blueprint registration failed');
assert($serviceCpt->getSingular() === 'Dienstleistung', 'Dynamic blueprint singular label mismatch');

// Test getCustomPostTypes (should exclude page and post)
$cpts = $registry->getCustomPostTypes();
assert(!isset($cpts['page']), 'getCustomPostTypes includes page');
assert(!isset($cpts['post']), 'getCustomPostTypes includes post');
assert(isset($cpts['property'], $cpts['team_member'], $cpts['case_study'], $cpts['service']), 'getCustomPostTypes missing registered CPTs');

echo "   ✓ Built-in CPTs, dynamic blueprints, and meta definitions verified!\n";

// -----------------------------------------------------------------------------
// TEST 3: Structured Block Registry & Block Renderer
// -----------------------------------------------------------------------------
echo "3. Testing Structured JSON Block Registry & Renderer...\n";
$blockReg = BlockRegistry::getInstance();
$renderer = BlockRenderer::getInstance();

// Verify core blocks registered
$allBlocks = $blockReg->getAll();
$expectedCore = [
    'core/hero', 'core/heading', 'core/paragraph', 'core/image', 'core/button',
    'core/columns', 'core/card', 'core/features', 'core/faq', 'core/quote',
    'core/alert', 'core/spacer', 'core/popup'
];

foreach ($expectedCore as $coreType) {
    assert(isset($allBlocks[$coreType]), "BlockRegistry missing core block: {$coreType}");
}

// Test Block Rendering: Hero
$heroHtml = $renderer->renderBlock([
    'type'  => 'core/hero',
    'attrs' => [
        'headline'         => 'Test Hero Titel',
        'subheadline'      => 'Test Untertitel',
        'badge'            => 'Top Angebot',
        'primary_btn_text' => 'Klick hier',
        'primary_btn_url'  => 'https://example.com',
    ],
]);
assert(str_contains($heroHtml, 'Test Hero Titel'), 'Hero rendered HTML missing headline');
assert(str_contains($heroHtml, 'Top Angebot'), 'Hero rendered HTML missing badge');
assert(str_contains($heroHtml, 'Klick hier'), 'Hero rendered HTML missing button text');

// Test Block Rendering: Heading and Paragraph
$headingHtml = $renderer->renderBlock([
    'type'  => 'core/heading',
    'attrs' => ['level' => 'h2', 'content' => 'Abschnitt Eins', 'subtitle' => 'Kicker'],
]);
assert(str_contains($headingHtml, '<h2') && str_contains($headingHtml, 'Abschnitt Eins') && str_contains($headingHtml, 'Kicker'), 'Heading block failed');

$paragraphHtml = $renderer->renderBlock([
    'type'  => 'core/paragraph',
    'attrs' => ['content' => 'Ein toller Textabsatz.', 'lead' => true],
]);
assert(str_contains($paragraphHtml, 'Ein toller Textabsatz.'), 'Paragraph block failed');

// Test Block Rendering: Columns with recursive innerBlocks
$columnsHtml = $renderer->renderBlock([
    'type'        => 'core/columns',
    'attrs'       => ['columns' => 2, 'gap' => 'lg'],
    'innerBlocks' => [
        [
            'type'  => 'core/card',
            'attrs' => ['icon' => '⚡', 'title' => 'Spalte 1', 'description' => 'Inhalt Spalte 1'],
        ],
        [
            'type'  => 'core/card',
            'attrs' => ['icon' => '🔒', 'title' => 'Spalte 2', 'description' => 'Inhalt Spalte 2'],
        ],
    ],
]);
assert(str_contains($columnsHtml, 'grid-cols-1 md:grid-cols-2'), 'Columns grid class missing');
assert(str_contains($columnsHtml, 'Spalte 1') && str_contains($columnsHtml, 'Spalte 2'), 'Columns innerBlocks rendering failed');

// Test Block Rendering: FAQ Accordion (Alpine.js)
$faqHtml = $renderer->renderBlock([
    'type'  => 'core/faq',
    'attrs' => [
        'items' => [
            ['question' => 'Frage 1?', 'answer' => 'Antwort 1.'],
        ],
    ],
]);
assert(str_contains($faqHtml, 'x-data') && str_contains($faqHtml, 'Frage 1?'), 'FAQ accordion block failed');

// Test Block Rendering: Popup & Paywall Condition Engine
// 1. Role Gate (Paywall) - Logged out
Auth::logout();
$paywallHtml = $renderer->renderBlock([
    'type'  => 'core/popup',
    'attrs' => [
        'trigger'       => 'role_gate',
        'required_role' => 'member',
        'title'         => 'Exklusiver Mitglieder-Inhalt',
        'message'       => 'Bitte registrieren Sie sich, um den Artikel zu lesen.',
        'cta_text'      => 'Mitglied werden',
        'cta_url'       => '/register',
    ],
]);
assert(str_contains($paywallHtml, 'Exklusiver Mitglieder-Inhalt'), 'Paywall barrier not rendered for guest');
assert(str_contains($paywallHtml, 'Mitglied werden'), 'Paywall CTA missing for guest');

// 2. Role Gate (Paywall) - Logged in as member
Auth::attempt('premium_member', 'member12345');
assert(Auth::check() && Auth::hasRole(Rbac::ROLE_MEMBER), 'Member auth failed');
$paywallUnlockedHtml = $renderer->renderBlock([
    'type'  => 'core/popup',
    'attrs' => [
        'trigger'       => 'role_gate',
        'required_role' => 'member',
        'title'         => 'Exklusiver Mitglieder-Inhalt',
    ],
]);
assert(str_contains($paywallUnlockedHtml, 'Paywall Unlocked'), 'Paywall should be unlocked for member');

// 3. Time delay / Scroll popup
$delayPopupHtml = $renderer->renderBlock([
    'type'  => 'core/popup',
    'attrs' => [
        'trigger'       => 'time_delay',
        'trigger_value' => 3,
        'title'         => 'Newsletter Angebot',
        'message'       => 'Melden Sie sich jetzt an.',
        'variant'       => 'modal',
    ],
]);
assert(str_contains($delayPopupHtml, 'setTimeout') && str_contains($delayPopupHtml, 'Newsletter Angebot'), 'Time delay popup failed');

echo "   ✓ All 13 block types, innerColumns, and Alpine.js condition engine verified!\n";

// -----------------------------------------------------------------------------
// TEST 4: Post Service (CRUD, Slug Collision, Blocks & Pre-rendering)
// -----------------------------------------------------------------------------
echo "4. Testing Post Service (PostService)...\n";
$posts = new PostService($db, $meta, $renderer);

// 1. Create a page
$pageId = $posts->create([
    'title'          => 'Über Uns',
    'post_type'      => 'page',
    'status'         => 'publish',
    'content_blocks' => [
        [
            'type'  => 'core/heading',
            'attrs' => ['level' => 'h1', 'content' => 'Das sind wir'],
        ],
    ],
], [
    '_excerpt' => 'Erfahren Sie mehr über unsere digitale Agentur.',
]);

assert($pageId > 0, 'Page creation failed');
$createdPage = $posts->find($pageId);
assert($createdPage !== null, 'Page find failed');
assert($createdPage['title'] === 'Über Uns', 'Page title mismatch');
assert($createdPage['slug'] === 'ueber-uns', "Page slugify mismatch: got {$createdPage['slug']}");
assert(!empty($createdPage['content_html']), 'Page content_html was not pre-rendered');
assert(str_contains($createdPage['content_html'], 'Das sind wir'), 'Page pre-rendered HTML missing content');
assert($createdPage['meta']['_excerpt'] === 'Erfahren Sie mehr über unsere digitale Agentur.', 'Page meta excerpt mismatch');

// 2. Slug Collision Handling: Create another page with same title
$pageId2 = $posts->create([
    'title'     => 'Über Uns',
    'post_type' => 'page',
    'status'    => 'publish',
]);
$page2 = $posts->find($pageId2);
assert($page2['slug'] === 'ueber-uns-2', "Slug collision did not append -2: got {$page2['slug']}");

// 3. Create a Custom Post Type item (Real Estate Property)
$propertyId = $posts->create([
    'title'     => 'Luxus Penthouse Berlin',
    'post_type' => 'property',
    'status'    => 'publish',
], [
    'price'         => 850000,
    'rooms'         => 4.5,
    'living_space'  => 160,
    'location'      => 'Berlin-Charlottenburg',
    'property_type' => 'Penthouse',
    'status'        => 'Verfügbar',
]);

$property = $posts->find($propertyId);
assert($property !== null, 'Property creation failed');
assert($property['post_type'] === 'property', 'Property post_type mismatch');
assert((int)$property['meta']['price'] === 850000, 'Property meta price mismatch');
assert((float)$property['meta']['rooms'] === 4.5, 'Property meta rooms mismatch');
assert($property['meta']['location'] === 'Berlin-Charlottenburg', 'Property meta location mismatch');

// 4. Update post
$posts->update($propertyId, [
    'title' => 'Exklusives Luxus Penthouse Berlin',
], [
    'price' => 890000,
]);
$updatedProp = $posts->find($propertyId);
assert($updatedProp['title'] === 'Exklusives Luxus Penthouse Berlin', 'Post update title failed');
assert((int)$updatedProp['meta']['price'] === 890000, 'Post update meta failed');

// 5. Query and search
$found = $posts->query(['post_type' => 'property', 'search' => 'Charlottenburg']);
assert(count($found) >= 1, 'Post query search failed');

// 6. Trash, Restore & Delete
$posts->trash($pageId2);
$trashedPage = $posts->find($pageId2);
assert($trashedPage['status'] === 'trash', 'Post trash status failed');

$posts->restore($pageId2);
$restoredPage = $posts->find($pageId2);
assert($restoredPage['status'] === 'draft', 'Post restore status failed');

$posts->delete($pageId2, true);
assert($posts->find($pageId2) === null, 'Post permanent delete failed');

echo "   ✓ PostService CRUD, collision slugs, CPT meta, pre-rendering, and trash lifecycle verified!\n";

// -----------------------------------------------------------------------------
// TEST 5: Media Manager & Asset Pipeline (MediaService)
// -----------------------------------------------------------------------------
echo "5. Testing Media Manager & Asset Pipeline (MediaService)...\n";
$mediaService = new MediaService($db, $meta);

// Verify uploads directory and .htaccess
$uploadsDir = $mediaService->getUploadsDir();
assert(is_dir($uploadsDir), 'Uploads directory does not exist');
assert(is_file($uploadsDir . '/.htaccess'), 'Security .htaccess in uploads missing');
$htaccessContent = file_get_contents($uploadsDir . '/.htaccess');
assert(str_contains($htaccessContent, 'Deny from all'), 'Security .htaccess does not deny script execution');

// Create a real temporary test image via GD
$tempImage = tempnam(sys_get_temp_dir(), 'hb_img_') . '.jpg';
$im = imagecreatetruecolor(800, 600);
$bg = imagecolorallocate($im, 249, 115, 22); // #f97316 coral
imagefill($im, 0, 0, $bg);
imagejpeg($im, $tempImage, 90);
imagedestroy($im);

// Test Upload
$uploadFileArray = [
    'name'     => 'Sample Property Photo.jpg',
    'type'     => 'image/jpeg',
    'tmp_name' => $tempImage,
    'error'    => UPLOAD_ERR_OK,
    'size'     => filesize($tempImage),
];

$attachment = $mediaService->upload($uploadFileArray, $adminId);
assert(!empty($attachment['id']), 'Attachment upload failed to return ID');
assert($attachment['title'] === 'Sample Property Photo', 'Attachment title mismatch');
assert($attachment['_mime_type'] === 'image/jpeg', 'Attachment MIME mismatch');
assert((int)$attachment['_width'] === 800, 'Attachment width mismatch');
assert((int)$attachment['_height'] === 600, 'Attachment height mismatch');

// Verify WebP conversion exists if GD imagewebp is supported
if (function_exists('imagewebp')) {
    assert(!empty($attachment['_webp_url']), 'WebP conversion URL missing');
    $webpDiskPath = HEARTBEAT_ROOT . '/' . ltrim($attachment['_webp_path'], '/');
    assert(is_file($webpDiskPath), "WebP file was not created on disk: {$webpDiskPath}");
}

// Verify responsive sizes
assert(isset($attachment['_sizes']['thumbnail']), 'Thumbnail size missing in attachment');
assert(isset($attachment['_sizes']['medium']), 'Medium size missing in attachment');

// Test srcset helper
$srcset = $mediaService->getSrcset($attachment);
assert(!empty($srcset), 'getSrcset returned empty string');
assert(str_contains($srcset, 'w'), 'getSrcset missing width descriptor');

// Test picture tag generator
$pictureTag = $mediaService->renderPictureTag($attachment, 'Wunderschöne Immobilie', 'rounded-xl');
assert(str_contains($pictureTag, '<picture'), 'renderPictureTag missing <picture>');
assert(str_contains($pictureTag, 'alt="Wunderschöne Immobilie"'), 'renderPictureTag alt attribute failed');

// Test Upload Security: Reject malicious double extension
$maliciousTemp = tempnam(sys_get_temp_dir(), 'hb_bad_') . '.jpg';
file_put_contents($maliciousTemp, '<?php echo "hacked";');
$maliciousUpload = [
    'name'     => 'exploit.php.jpg',
    'type'     => 'image/jpeg',
    'tmp_name' => $maliciousTemp,
    'error'    => UPLOAD_ERR_OK,
    'size'     => filesize($maliciousTemp),
];

$rejected = false;
try {
    $mediaService->upload($maliciousUpload, $adminId);
} catch (\InvalidArgumentException) {
    $rejected = true;
}
assert($rejected, 'MediaService failed to reject double extension .php.jpg');
@unlink($maliciousTemp);

// Test Media deletion (cleans DB and all disk files)
$attachmentId = (int)$attachment['id'];
$mediaService->delete($attachmentId);
assert($mediaService->get($attachmentId) === null, 'Media record not deleted from DB');
if (!empty($attachment['_file_path'])) {
    assert(!is_file(HEARTBEAT_ROOT . '/' . $attachment['_file_path']), 'Original media file was not removed from disk');
}

echo "   ✓ Native image conversions, WebP generation, sizes, srcset, and MIME security verified!\n";

// -----------------------------------------------------------------------------
// TEST 6: Admin Controllers (ContentController & MediaController)
// -----------------------------------------------------------------------------
echo "6. Testing ContentController, MediaController & RBAC Permissions...\n";

// Test unauthenticated access (Redirects to /admin/login)
Auth::logout();
$req = Request::create('GET', '/admin/pages');
$contentCtrl = new ContentController($posts, $registry, $blockReg, $mediaService);
$resp = $contentCtrl->index($req);
assert($resp->isRedirect(), 'Unauthenticated content access did not redirect');
assert(str_contains($resp->getHeader('Location') ?? '', '/admin/login'), 'Did not redirect to /admin/login');

// Log in as Editor
Auth::attempt('content_editor', 'editor12345');
assert(Auth::check() && Auth::can('manage_content'), 'Editor login failed');

// Editor can access Pages index
$pagesResp = $contentCtrl->index($req);
assert($pagesResp->getStatusCode() === 200, 'Editor was denied access to pages');
assert(str_contains($pagesResp->getBody(), 'Seiten &amp; Inhalte') || str_contains($pagesResp->getBody(), 'Seiten'), 'Pages view HTML missing expected title');

// Editor can access Create view
$createReq = Request::create('GET', '/admin/content/create?type=page');
$createResp = $contentCtrl->create($createReq);
assert($createResp->getStatusCode() === 200, 'Create page view failed');
assert(str_contains($createResp->getBody(), 'Block Builder'), 'Create page view missing Block Builder');

// Test Save POST with CSRF protection
$token = Csrf::token();
$saveReq = Request::create('POST', '/admin/content/save', [
    '_csrf_token'    => $token,
    'post_type'      => 'page',
    'title'          => 'Kontaktseite Neu',
    'slug'           => 'kontakt-neu',
    'status'         => 'publish',
    'content_blocks' => json_encode([
        [
            'type'  => 'core/heading',
            'attrs' => ['level' => 'h1', 'content' => 'Schreiben Sie uns'],
        ],
    ]),
]);

$saveResp = $contentCtrl->save($saveReq);
assert($saveResp->isRedirect(), 'Content save did not redirect on success');

$savedPage = $posts->findBySlug('kontakt-neu', 'page');
assert($savedPage !== null, 'Saved page was not found in database');
assert($savedPage['title'] === 'Kontaktseite Neu', 'Saved page title mismatch');

// Test MediaController API list for block editor picker
$mediaCtrl = new MediaController($mediaService);
$apiReq = Request::create('GET', '/admin/api/media');
$apiResp = $mediaCtrl->apiList($apiReq);
assert($apiResp->getStatusCode() === 200, 'Media API list returned non-200');
$apiJson = json_decode($apiResp->getBody(), true);
assert(isset($apiJson['status']) && $apiJson['status'] === 'ok', 'Media API response invalid');

// Member role cannot access admin content
Auth::logout();
Auth::attempt('premium_member', 'member12345');
$memberReq = Request::create('GET', '/admin/pages');
$memberResp = $contentCtrl->index($memberReq);
assert($memberResp->isRedirect(), 'Member role should be denied from admin content');

echo "   ✓ Admin controllers, CSRF checks, and RBAC matrix verified!\n";

// -----------------------------------------------------------------------------
// TEST 7: Frontend Routing & Dynamic Block Page Rendering
// -----------------------------------------------------------------------------
echo "7. Testing Frontend Routing & Dynamic Block Page Rendering...\n";

// Seed active_theme and site_title options
$db->insert('{prefix}options', ['option_name' => 'site_title', 'option_value' => 'Heartbeat Agency Pro', 'autoload' => 1]);
$db->insert('{prefix}options', ['option_name' => 'active_theme', 'option_value' => 'corporate', 'autoload' => 1]);

// Create homepage with rich blocks
$homeId = $posts->create([
    'title'          => 'Startseite',
    'slug'           => 'home',
    'post_type'      => 'page',
    'status'         => 'publish',
    'content_blocks' => [
        [
            'type'  => 'core/hero',
            'attrs' => [
                'headline'         => 'Digitale Exzellenz für Ihr Business',
                'subheadline'      => 'Heartbeat CMS treibt moderne Agenturwebsites an.',
                'badge'            => 'Phase 3 Live',
                'primary_btn_text' => 'Jetzt starten',
                'primary_btn_url'  => '#start',
            ],
        ],
        [
            'type'  => 'core/features',
            'attrs' => [
                'columns' => 3,
                'items'   => [
                    ['icon' => '⚡', 'title' => 'Speed', 'description' => '< 20ms TTFB'],
                    ['icon' => '🛡️', 'title' => 'Security', 'description' => 'OWASP Top 10'],
                    ['icon' => '🎨', 'title' => 'Blocks', 'description' => 'JSON Block Tree'],
                ],
            ],
        ],
    ],
]);

// Test routing to Homepage (/)
$homeReq = Request::create('GET', '/');
$kernel = new App();
// Execute router dispatch directly
$routerReflection = new \ReflectionClass($kernel);
$routerProp = $routerReflection->getProperty('router');
$routerProp->setAccessible(true);
$router = $routerProp->getValue($kernel);

// Register routes
$registerMethod = $routerReflection->getMethod('registerCoreRoutes');
$registerMethod->setAccessible(true);
$registerMethod->invoke($kernel);

$homeResponse = $router->dispatch($homeReq);
assert($homeResponse->getStatusCode() === 200, 'Homepage dispatch failed');
assert(str_contains($homeResponse->getBody(), 'Digitale Exzellenz für Ihr Business'), 'Homepage rendered HTML missing hero headline');
assert(str_contains($homeResponse->getBody(), 'Phase 3 Live'), 'Homepage rendered HTML missing badge');
assert(str_contains($homeResponse->getBody(), '< 20ms TTFB'), 'Homepage rendered HTML missing feature');

// Test routing to custom page: /kontakt-neu
$pageReq = Request::create('GET', '/kontakt-neu');
$pageResponse = $router->dispatch($pageReq);
assert($pageResponse->getStatusCode() === 200, 'Page dispatch failed for /kontakt-neu');
assert(str_contains($pageResponse->getBody(), 'Schreiben Sie uns'), 'Page rendered HTML missing heading content');

// Test routing to CPT single item: /immobilien/luxus-penthouse-berlin
$cptReq = Request::create('GET', '/immobilien/exklusives-luxus-penthouse-berlin');
$cptResponse = $router->dispatch($cptReq);
assert($cptResponse->getStatusCode() === 200, 'CPT single dispatch failed');
assert(str_contains($cptResponse->getBody(), 'Exklusives Luxus Penthouse Berlin'), 'CPT single view missing title');

// Test 404 for unknown slug
$unknownReq = Request::create('GET', '/this-page-does-not-exist');
$unknownResponse = $router->dispatch($unknownReq);
assert($unknownResponse->getStatusCode() === 404, 'Unknown page did not return 404');
assert(str_contains($unknownResponse->getBody(), '404'), '404 page missing 404 indicator');

echo "   ✓ Frontend dynamic routing, CPT single views, and 404 handling verified!\n";

// -----------------------------------------------------------------------------
// CLEANUP
// -----------------------------------------------------------------------------
echo "8. Cleaning up test artifacts...\n";
if (is_file($testDbFile)) {
    @unlink($testDbFile);
}
echo "   ✓ Test database cleaned up.\n";

echo "\n========================================================================\n";
echo "✓ ALL PHASE 3 CONTENT ENGINE, CPT, BLOCKS & MEDIA TESTS PASSED!\n";
echo "========================================================================\n";

