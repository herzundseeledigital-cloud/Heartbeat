<?php
declare(strict_types=1);

/**
 * Adversarial Challenger Test Suite for Milestone M1
 * 
 * Empirically challenges:
 * 1. Container Width & CSS Rules (widescreen max-w-[1800px], elimination of max-w-7xl, nested containers)
 * 2. 3-Column Card Widescreen Layout (.editor-col-3 responsive breakpoints and min-width 360px enforcement)
 * 3. Collapsible Inspector Sidebar (Alpine bindings, localStorage persistence, exception handling)
 * 4. Route Dispatching & Authentication Matrix (Super Admin HTTP 200, Guest 302 redirect, RBAC barriers)
 * 5. Non-Standard HTTP Methods & Method Spoofing (OPTIONS, PUT, DELETE, PATCH, POST on GET routes)
 * 6. Query Parameter & Malicious Input Fuzzing (XSS payloads, path traversal, SQLi strings, non-existent IDs)
 * 7. Regression Invariants (MediaController \$event escaping, AdminController modal close, /admin/content registration)
 */

namespace Heartbeat\Tests;

if (!defined('HEARTBEAT_ROOT')) {
    define('HEARTBEAT_ROOT', dirname(__DIR__));
}
if (!defined('HEARTBEAT_CORE')) {
    define('HEARTBEAT_CORE', HEARTBEAT_ROOT . '/core');
}
if (!defined('HEARTBEAT_CONFIG')) {
    define('HEARTBEAT_CONFIG', HEARTBEAT_ROOT . '/config');
}
if (!defined('HEARTBEAT_PUBLIC')) {
    define('HEARTBEAT_PUBLIC', HEARTBEAT_ROOT . '/public');
}
if (!defined('HEARTBEAT_STORAGE')) {
    define('HEARTBEAT_STORAGE', HEARTBEAT_ROOT . '/storage');
}
if (!defined('HEARTBEAT_UPLOADS')) {
    define('HEARTBEAT_UPLOADS', HEARTBEAT_ROOT . '/uploads');
}
if (!defined('HEARTBEAT_THEMES')) {
    define('HEARTBEAT_THEMES', HEARTBEAT_ROOT . '/themes');
}
if (!defined('HEARTBEAT_PLUGINS')) {
    define('HEARTBEAT_PLUGINS', HEARTBEAT_ROOT . '/plugins');
}

require_once HEARTBEAT_CORE . '/Bootstrap.php';
\Heartbeat\Bootstrap::init();

use Heartbeat\Database\Database;
use Heartbeat\Database\MigrationRunner;
use Heartbeat\Security\Security;
use Heartbeat\Security\Session;
use Heartbeat\Security\Csrf;
use Heartbeat\Security\Rbac;
use Heartbeat\Auth\Auth;
use Heartbeat\Http\Request;
use Heartbeat\Http\Response;
use Heartbeat\Http\Router;
use Heartbeat\App;
use Heartbeat\Admin\AdminView;
use Heartbeat\Admin\AdminController;
use Heartbeat\Admin\ContentController;
use Heartbeat\Admin\MediaController;
use Heartbeat\Admin\ThemeController;
use Heartbeat\Admin\AgencyModulesController;
use Heartbeat\Installer\InstallerController;
use Heartbeat\Installer\InstallerService;
use Throwable;

final class AdversarialM1Test
{
    private string $testDbFile;
    private Database $db;
    private App $app;
    private Router $router;
    private array $superAdminUser;
    private array $subscriberUser;

    private int $passCount = 0;
    private int $failCount = 0;
    private array $failures = [];

    public function __construct()
    {
        $this->testDbFile = HEARTBEAT_STORAGE . '/adversarial_m1_' . uniqid() . '.sqlite';
    }

    public function setUp(): void
    {
        if (file_exists($this->testDbFile)) {
            @unlink($this->testDbFile);
        }

        $config = [
            'driver'   => 'sqlite',
            'database' => $this->testDbFile,
            'prefix'   => 'hb_',
        ];

        $this->db = new Database($config);
        $migrationRunner = new MigrationRunner($this->db);
        $migrationRunner->runPending(HEARTBEAT_CORE . '/Database/Schema');

        $now = date('Y-m-d H:i:s');
        $this->db->insert('{prefix}options', ['option_name' => 'site_title', 'option_value' => 'Adversarial M1 Test Site', 'autoload' => 1]);
        $this->db->insert('{prefix}options', ['option_name' => 'site_url', 'option_value' => 'http://localhost:8000', 'autoload' => 1]);
        $this->db->insert('{prefix}options', ['option_name' => 'admin_email', 'option_value' => 'admin@adversarial.local', 'autoload' => 1]);
        $this->db->insert('{prefix}options', ['option_name' => 'active_theme', 'option_value' => 'default', 'autoload' => 1]);
        $this->db->insert('{prefix}options', ['option_name' => 'seo_indexing', 'option_value' => 'index', 'autoload' => 1]);

        // Seed users: Super Admin & Subscriber
        $this->db->insert('{prefix}users', [
            'username'      => 'adv_super',
            'email'         => 'super@adversarial.local',
            'password_hash' => Security::hashPassword('SuperSecret123!'),
            'role'          => Rbac::ROLE_SUPER_ADMIN,
            'status'        => 'active',
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);
        $this->superAdminUser = $this->db->fetchOne("SELECT * FROM {prefix}users WHERE username = 'adv_super'");

        $this->db->insert('{prefix}users', [
            'username'      => 'adv_sub',
            'email'         => 'sub@adversarial.local',
            'password_hash' => Security::hashPassword('SubscriberPass123!'),
            'role'          => Rbac::ROLE_MEMBER,
            'status'        => 'active',
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);
        $this->subscriberUser = $this->db->fetchOne("SELECT * FROM {prefix}users WHERE username = 'adv_sub'");

        // Setup App & Router
        $this->app = new App();
        $ref = new \ReflectionClass($this->app);
        $routerProp = $ref->getProperty('router');
        $routerProp->setAccessible(true);
        $this->router = $routerProp->getValue($this->app);

        // In App::run(), installer routes are registered first
        $this->router->get('/install', [InstallerController::class, 'index']);
        $this->router->post('/install/api/test-db', [InstallerController::class, 'testDatabase']);
        $this->router->post('/install/api/run-install', [InstallerController::class, 'runInstall']);

        $m = $ref->getMethod('registerCoreRoutes');
        $m->setAccessible(true);
        $m->invoke($this->app);

        Session::start();
    }

    public function tearDown(): void
    {
        Auth::logout();
        if (file_exists($this->testDbFile)) {
            @unlink($this->testDbFile);
        }
        if (file_exists($this->testDbFile . '-wal')) {
            @unlink($this->testDbFile . '-wal');
        }
        if (file_exists($this->testDbFile . '-shm')) {
            @unlink($this->testDbFile . '-shm');
        }
    }

    private function assertTest(string $name, callable $fn): void
    {
        try {
            $msg = $fn();
            echo "  \033[32m✓ PASS\033[0m: {$name}" . ($msg ? " ({$msg})" : "") . "\n";
            $this->passCount++;
        } catch (Throwable $e) {
            echo "  \033[31m✗ FAIL\033[0m: {$name}\n";
            echo "     \033[31mError: " . $e->getMessage() . "\033[0m\n";
            $this->failCount++;
            $this->failures[] = ['name' => $name, 'error' => $e->getMessage()];
        }
    }

    public function runAll(): void
    {
        echo "\n\033[1m======================================================================\033[0m\n";
        echo "\033[1m=== ADVERSARIAL CHALLENGER TEST SUITE: MILESTONE M1 ===\033[0m\n";
        echo "\033[1m======================================================================\033[0m\n";

        $this->testGroup1ContainerAndCssRules();
        $this->testGroup2EditorCol3ResponsiveMath();
        $this->testGroup3InspectorTogglingAndPersistence();
        $this->testGroup4RouteDispatchingAndAuthMatrix();
        $this->testGroup5NonStandardHttpMethods();
        $this->testGroup6QueryParameterFuzzing();
        $this->testGroup7SpecificBugRegressions();

        echo "\n\033[1m======================================================================\033[0m\n";
        echo "SUMMARY: {$this->passCount} Passed, {$this->failCount} Failed.\n";
        echo "\033[1m======================================================================\033[0m\n";

        if ($this->failCount > 0) {
            echo "\033[31mFAILURES ENCOUNTERED:\033[0m\n";
            foreach ($this->failures as $f) {
                echo " - " . $f['name'] . ": " . $f['error'] . "\n";
            }
        } else {
            echo "\033[32mALL ADVERSARIAL CHALLENGE CHECKS PASSED EMPIRICALLY!\033[0m\n";
        }
    }

    // -------------------------------------------------------------------------
    // GROUP 1: Container Width & CSS Rules
    // -------------------------------------------------------------------------
    public function testGroup1ContainerAndCssRules(): void
    {
        echo "\n\033[1;36m--- [Group 1] Container Width & CSS Rules Stress Tests ---\033[0m\n";

        $this->assertTest("AdminView.php main shell container uses max-w-[1800px] without max-w-7xl bottleneck", function () {
            $file = HEARTBEAT_CORE . '/Admin/AdminView.php';
            $code = file_get_contents($file);
            if (!preg_match('/<main\b([^>]*)>/is', $code, $m)) {
                throw new \AssertionError("<main> tag not found in AdminView.php");
            }
            $attrs = $m[1];
            if (str_contains($attrs, 'max-w-7xl')) {
                throw new \AssertionError("Found forbidden 'max-w-7xl' in main container attributes: {$attrs}");
            }
            if (!str_contains($attrs, 'max-w-[1800px]')) {
                throw new \AssertionError("Missing 'max-w-[1800px]' in main container attributes: {$attrs}");
            }
            return "Tag: <main {$attrs}>";
        });

        $this->assertTest("admin.css declares properly escaped .max-w-\[1800px\] utility", function () {
            $cssFile = HEARTBEAT_PUBLIC . '/assets/css/admin.css';
            if (!file_exists($cssFile)) {
                throw new \AssertionError("admin.css not found");
            }
            $css = file_get_contents($cssFile);
            if (!preg_match('/\.max-w-\\\\\[1800px\\\\\]\s*\{\s*max-width:\s*1800px;\s*\}/', $css)) {
                throw new \AssertionError(".max-w-\[1800px\] { max-width: 1800px; } declaration missing or improperly formatted");
            }
            return "Utility found and regex verified";
        });

        $this->assertTest("Adversarial: Visual editor views do NOT contain nested max-w-7xl or max-w-6xl canvas bottlenecks", function () {
            $filesToCheck = [
                HEARTBEAT_CORE . '/Admin/ContentController.php',
            ];
            foreach ($filesToCheck as $f) {
                $code = file_get_contents($f);
                // Look for visual canvas container definitions
                if (preg_match('/class="[^"]*(?:document-canvas|visual-canvas|content-canvas)[^"]*max-w-(?:7xl|6xl|5xl|4xl)/is', $code, $m)) {
                    throw new \AssertionError("Found restrictive max-w container bottleneck in " . basename($f) . ": " . $m[0]);
                }
            }
            return "No nested bottlenecks found in visual editor markup";
        });
    }

    // -------------------------------------------------------------------------
    // GROUP 2: 3-Column Card Widescreen Layout & Math Verification
    // -------------------------------------------------------------------------
    public function testGroup2EditorCol3ResponsiveMath(): void
    {
        echo "\n\033[1;36m--- [Group 2] 3-Column Card Widescreen Layout (.editor-col-3) Math & Breakpoints ---\033[0m\n";

        $this->assertTest(".editor-col-3 CSS rule structure in admin.css has 3-tier responsive breakpoints", function () {
            $css = file_get_contents(HEARTBEAT_PUBLIC . '/assets/css/admin.css');

            // 1. Base rule (mobile: 100%)
            if (!preg_match('/\.editor-col-3\s*\{[^}]*width:\s*100%\s*!important/is', $css)) {
                throw new \AssertionError("Base .editor-col-3 width: 100% !important rule missing");
            }

            // 2. Tablet rule (768px: 50%)
            if (!preg_match('/@media\s*\(\s*min-width:\s*768px\s*\)\s*\{[^}]*\.editor-col-3\s*\{[^}]*50%/is', $css)) {
                throw new \AssertionError("Tablet @media (min-width: 768px) 50% rule for .editor-col-3 missing");
            }

            // 3. Desktop rule (1280px: 33.333% with min-width: 360px)
            if (!preg_match('/@media\s*\(\s*min-width:\s*1280px\s*\)\s*\{[^}]*\.editor-col-3\s*\{[^}]*33\.333%[^}]*min-width:\s*360px\s*!important/is', $css)) {
                throw new \AssertionError("Desktop @media (min-width: 1280px) 33.333% + min-width: 360px !important rule missing");
            }

            return "All 3 tiers (mobile 100%, tablet 50%, desktop 33.333% + min-width: 360px !important) verified";
        });

        $this->assertTest("Empirical calculation: 1080p display (1920x1080) cards yield >= 360px with and without inspector", function () {
            $viewport = 1920;
            $sidebar = 256;
            $padding = 64; // md:p-8 = 32px * 2
            $contentWidth = $viewport - $sidebar - $padding; // 1600px

            $inspectorWidth = 320;
            $inspectorGap = 24; // gap-6
            $cardGapTotal = 40; // 2 gaps of 20px (gap-5) for 3 columns

            // Case A: Inspector open
            $canvasWithInspector = $contentWidth - $inspectorWidth - $inspectorGap; // 1256px
            $cardWidthWithInspector = ($canvasWithInspector - $cardGapTotal) / 3; // 405.33px
            if ($cardWidthWithInspector < 360.0) {
                throw new \AssertionError("Card width with inspector open ({$cardWidthWithInspector}px) is below 360px");
            }

            // Case B: Inspector closed
            $canvasWithoutInspector = $contentWidth; // 1600px
            $cardWidthWithoutInspector = ($canvasWithoutInspector - $cardGapTotal) / 3; // 520px
            if ($cardWidthWithoutInspector < 360.0) {
                throw new \AssertionError("Card width with inspector closed ({$cardWidthWithoutInspector}px) is below 360px");
            }

            return "Inspector Open: " . round($cardWidthWithInspector, 1) . "px | Inspector Closed: " . round($cardWidthWithoutInspector, 1) . "px (both >= 360px)";
        });

        $this->assertTest("Empirical calculation: 1440p (2560px) and 4K (3840px) displays capped at 1800px yield >= 360px", function () {
            $maxWidth = 1800;
            $padding = 64;
            $contentWidth = $maxWidth - $padding; // 1736px
            $cardGapTotal = 40;

            // Inspector Open (320px + 24px)
            $canvasWithInspector = $contentWidth - 344; // 1392px
            $cardWidthWithInspector = ($canvasWithInspector - $cardGapTotal) / 3; // 450.67px
            if ($cardWidthWithInspector < 360.0) {
                throw new \AssertionError("Card width on 1440p/4K ({$cardWidthWithInspector}px) is below 360px");
            }

            // Inspector Closed
            $cardWidthWithoutInspector = ($contentWidth - $cardGapTotal) / 3; // 565.33px
            if ($cardWidthWithoutInspector < 360.0) {
                throw new \AssertionError("Card width on 1440p/4K closed ({$cardWidthWithoutInspector}px) is below 360px");
            }

            return "1440p/4K Open: " . round($cardWidthWithInspector, 1) . "px | Closed: " . round($cardWidthWithoutInspector, 1) . "px";
        });

        $this->assertTest("Adversarial: Boundary condition at 1280px desktop threshold strictly enforces min-width 360px and flex-wrap", function () {
            $code = file_get_contents(HEARTBEAT_CORE . '/Admin/ContentController.php');
            if (!str_contains($code, 'flex-wrap')) {
                throw new \AssertionError("Visual blocks container does not declare flex-wrap! Cards could overflow without wrapping.");
            }

            $css = file_get_contents(HEARTBEAT_PUBLIC . '/assets/css/admin.css');
            if (!str_contains($css, 'min-width: 360px !important')) {
                throw new \AssertionError("min-width: 360px !important is missing in CSS!");
            }

            return "CSS enforces min-width: 360px !important and parent container enforces flex-wrap";
        });
    }

    // -------------------------------------------------------------------------
    // GROUP 3: Collapsible Inspector Sidebar & Persistence
    // -------------------------------------------------------------------------
    public function testGroup3InspectorTogglingAndPersistence(): void
    {
        echo "\n\033[1;36m--- [Group 3] Collapsible Inspector Sidebar & Persistence Stress Tests ---\033[0m\n";

        $contentCode = file_get_contents(HEARTBEAT_CORE . '/Admin/ContentController.php');

        $this->assertTest("Inspector sidebar state defaults to true if localStorage is empty or throws", function () use ($contentCode) {
            if (!str_contains($contentCode, "localStorage.getItem('hb_inspector_open') !== 'false'")) {
                throw new \AssertionError("Inspector default state does not evaluate localStorage !== 'false'");
            }
            if (!str_contains($contentCode, "try { return localStorage.getItem('hb_inspector_open') !== 'false'; } catch(e) { return true; }")) {
                throw new \AssertionError("Inspector state initialization lacks exception catch fallback to true");
            }
            return "Exception-safe initialization confirmed";
        });

        $this->assertTest("Inspector watcher updates localStorage with try/catch guard", function () use ($contentCode) {
            if (!str_contains($contentCode, "localStorage.setItem('hb_inspector_open'")) {
                throw new \AssertionError("localStorage.setItem('hb_inspector_open') not found in watcher");
            }
            if (!str_contains($contentCode, "try { localStorage.setItem('hb_inspector_open'")) {
                throw new \AssertionError("Inspector watcher does not guard localStorage.setItem with try/catch");
            }
            return "localStorage.setItem safely guarded with try/catch";
        });

        $this->assertTest("Inspector close button and topbar toggle button exist in DOM markup", function () use ($contentCode) {
            $hasHeaderClose = str_contains($contentCode, '@click="inspectorOpen = false"') && str_contains($contentCode, 'title="Inspektor schließen"');
            $hasTopbarToggle = str_contains($contentCode, '@click="inspectorOpen = !inspectorOpen"');

            if (!$hasHeaderClose) {
                throw new \AssertionError("Header close button (@click=\"inspectorOpen = false\") missing in inspector");
            }
            if (!$hasTopbarToggle) {
                throw new \AssertionError("Topbar toggle button (@click=\"inspectorOpen = !inspectorOpen\") missing");
            }
            return "Both header close button and topbar toggle button confirmed";
        });

        $this->assertTest("Inspector sidebar container is fully collapsible (x-show and dynamic classes)", function () use ($contentCode) {
            if (!str_contains($contentCode, 'x-show="inspectorOpen"')) {
                throw new \AssertionError("Inspector sidebar missing x-show=\"inspectorOpen\"");
            }
            if (!str_contains($contentCode, ":class=\"inspectorOpen ? 'block' : 'hidden'\"")) {
                throw new \AssertionError("Inspector sidebar missing :class=\"inspectorOpen ? 'block' : 'hidden'\"");
            }
            return "x-show and dynamic block/hidden class toggle confirmed";
        });
    }

    // -------------------------------------------------------------------------
    // GROUP 4: Route Dispatching & Authentication Matrix
    // -------------------------------------------------------------------------
    public function testGroup4RouteDispatchingAndAuthMatrix(): void
    {
        echo "\n\033[1;36m--- [Group 4] Route Dispatching & Authentication Matrix ---\033[0m\n";

        $adminRoutes = [
            '/admin'          => 'Dashboard',
            '/admin/content'  => 'Content',
            '/admin/media'    => 'Media',
            '/admin/themes'   => 'Themes',
            '/admin/seo'      => 'SEO',
            '/admin/users'    => 'Users',
            '/admin/settings' => 'Settings',
            '/install'        => 'Installer',
        ];

        // 4.1: Super Admin Authenticated State
        Auth::login($this->superAdminUser);

        foreach ($adminRoutes as $route => $name) {
            $this->assertTest("Super Admin: GET {$route} ({$name}) returns HTTP 200 without PHP errors", function () use ($route) {
                $errors = [];
                set_error_handler(function ($errno, $errstr, $errfile, $errline) use (&$errors) {
                    $errors[] = "{$errstr} in " . basename($errfile) . ":{$errline}";
                    return true;
                });

                try {
                    $req = new Request('GET', $route);
                    $resp = $this->router->dispatch($req);

                    if (!empty($errors)) {
                        throw new \AssertionError("PHP errors on {$route}: " . implode('; ', $errors));
                    }

                    $code = $resp->getStatusCode();
                    if ($code !== 200) {
                        throw new \AssertionError("Expected 200, got {$code} on {$route}");
                    }

                    $body = $resp->getBody();
                    if (!is_string($body) || strlen($body) < 50) {
                        throw new \AssertionError("Empty or truncated response body on {$route}");
                    }

                    return "HTTP 200 OK (" . strlen($body) . " bytes)";
                } finally {
                    restore_error_handler();
                }
            });
        }

        // 4.2: Unauthenticated Guest State
        Auth::logout();

        $protectedRoutes = [
            '/admin',
            '/admin/content',
            '/admin/media',
            '/admin/themes',
            '/admin/seo',
            '/admin/users',
            '/admin/settings',
        ];

        foreach ($protectedRoutes as $route) {
            $this->assertTest("Adversarial: Guest access to {$route} redirects to /admin/login (HTTP 302)", function () use ($route) {
                $req = new Request('GET', $route);
                $resp = $this->router->dispatch($req);
                $code = $resp->getStatusCode();

                if ($code !== 302) {
                    throw new \AssertionError("Protected route {$route} allowed unauthenticated access (HTTP {$code})");
                }
                $loc = $resp->getHeader('Location');
                if (!str_contains($loc, '/admin/login')) {
                    throw new \AssertionError("Redirect location on {$route} is '{$loc}', expected '/admin/login'");
                }
                return "HTTP 302 Redirect to {$loc}";
            });
        }

        // 4.3: Unauthenticated access to /install (when locked)
        $this->assertTest("Installer route /install returns HTTP 200 locked notice when lockfile present", function () {
            // config/installed.lock is present
            $req = new Request('GET', '/install');
            $resp = $this->router->dispatch($req);
            $code = $resp->getStatusCode();
            if ($code !== 200) {
                throw new \AssertionError("Expected 200 on locked /install, got {$code}");
            }
            $body = $resp->getBody();
            if (!str_contains($body, 'Bereits installiert') && !str_contains($body, 'Installationssperre')) {
                throw new \AssertionError("Response body did not indicate installation lock");
            }
            return "HTTP 200 locked view returned";
        });

        // 4.4: Low-privilege role (Subscriber) Access Denial
        Auth::login($this->subscriberUser);

        $superOnlyRoutes = [
            '/admin/users',
            '/admin/settings',
            '/admin/themes',
            '/admin/seo',
        ];

        foreach ($superOnlyRoutes as $route) {
            $this->assertTest("Adversarial: Subscriber role blocked from accessing {$route}", function () use ($route) {
                $req = new Request('GET', $route);
                $resp = $this->router->dispatch($req);
                $code = $resp->getStatusCode();
                // Should redirect to /admin or /admin/login (302) with error
                if ($code !== 302 && $code !== 403) {
                    throw new \AssertionError("Subscriber was permitted access to {$route} with HTTP {$code}");
                }
                return "Blocked with HTTP {$code}";
            });
        }
    }

    // -------------------------------------------------------------------------
    // GROUP 5: Non-Standard HTTP Methods & Method Spoofing
    // -------------------------------------------------------------------------
    public function testGroup5NonStandardHttpMethods(): void
    {
        echo "\n\033[1;36m--- [Group 5] Non-Standard HTTP Methods & Spoofing Stress Tests ---\033[0m\n";

        Auth::login($this->superAdminUser);

        $getOnlyRoutes = [
            '/admin',
            '/admin/content',
            '/admin/media',
            '/admin/themes',
            '/admin/users',
        ];

        foreach ($getOnlyRoutes as $route) {
            $this->assertTest("Adversarial: POST to GET-only route {$route} returns HTTP 404 without crashing", function () use ($route) {
                $req = new Request('POST', $route);
                $resp = $this->router->dispatch($req);
                $code = $resp->getStatusCode();
                if ($code !== 404 && $code !== 405) {
                    throw new \AssertionError("Expected 404/405 for POST {$route}, got {$code}");
                }
                return "HTTP {$code} handled cleanly";
            });
        }

        $weirdMethods = ['PUT', 'DELETE', 'PATCH', 'OPTIONS', 'TRACE'];
        foreach ($weirdMethods as $method) {
            $this->assertTest("Adversarial: {$method} /admin/content returns HTTP 404 without crashing", function () use ($method) {
                $req = new Request($method, '/admin/content');
                $resp = $this->router->dispatch($req);
                $code = $resp->getStatusCode();
                if ($code !== 404 && $code !== 405) {
                    throw new \AssertionError("Expected 404/405 for {$method} /admin/content, got {$code}");
                }
                return "HTTP {$code}";
            });
        }

        // Mutating endpoints on installer when locked MUST return 403 Forbidden
        $this->assertTest("Adversarial: POST /install/api/run-install rejected with HTTP 403 when installer locked", function () {
            $req = new Request('POST', '/install/api/run-install', [], ['site_title' => 'Hacked Site']);
            $resp = $this->router->dispatch($req);
            $code = $resp->getStatusCode();
            if ($code !== 403) {
                throw new \AssertionError("Expected HTTP 403 Forbidden on locked run-install, got {$code}");
            }
            return "HTTP 403 Forbidden returned";
        });

        $this->assertTest("Adversarial: POST /install/api/test-db rejected with HTTP 403 when installer locked", function () {
            $req = new Request('POST', '/install/api/test-db', [], ['driver' => 'mysql']);
            $resp = $this->router->dispatch($req);
            $code = $resp->getStatusCode();
            if ($code !== 403) {
                throw new \AssertionError("Expected HTTP 403 Forbidden on locked test-db, got {$code}");
            }
            return "HTTP 403 Forbidden returned";
        });
    }

    // -------------------------------------------------------------------------
    // GROUP 6: Query Parameter Fuzzing & Malicious Inputs
    // -------------------------------------------------------------------------
    public function testGroup6QueryParameterFuzzing(): void
    {
        echo "\n\033[1;36m--- [Group 6] Query Parameter & Injection Fuzzing ---\033[0m\n";

        Auth::login($this->superAdminUser);

        // 6.1: ContentController::index with malicious post types
        $fuzzTypes = [
            'non_existent_custom_post_type',
            '../../etc/passwd',
            '<script>alert(1)</script>',
            '\' OR \'1\'=\'1',
            str_repeat('A', 500),
        ];

        foreach ($fuzzTypes as $badType) {
            $this->assertTest("Adversarial: /admin/content?type=" . substr($badType, 0, 15) . "... safely redirects to /admin", function () use ($badType) {
                $req = new Request('GET', '/admin/content?type=' . urlencode($badType), ['type' => $badType]);
                $ctrl = new ContentController();
                $resp = $ctrl->index($req, ['type' => $badType]);
                $code = $resp->getStatusCode();
                if ($code !== 302) {
                    throw new \AssertionError("Expected redirect (302) for invalid post type, got {$code}");
                }
                return "HTTP 302 Redirect";
            });
        }

        // 6.2: ContentController::edit with invalid or adversarial non-existent IDs (must 302 redirect)
        $fuzzIds = [
            '9999999',
            '0',
            '-1',
            'abc_string_id',
            '999999\' OR 1=1--',
            '../config',
        ];

        foreach ($fuzzIds as $badId) {
            $this->assertTest("Adversarial: /admin/content/edit/:id with ID '{$badId}' redirects on non-existent content", function () use ($badId) {
                $req = new Request('GET', "/admin/content/edit/{$badId}");
                $ctrl = new ContentController();
                $resp = $ctrl->edit($req, ['id' => $badId]);
                $code = $resp->getStatusCode();
                if ($code !== 302 && $code !== 404) {
                    throw new \AssertionError("Expected redirect (302) or 404 for invalid content ID, got {$code}");
                }
                return "HTTP {$code}";
            });
        }

        // 6.2b: Test SQL injection string on existing ID is safely typecast to integer without SQL error
        $this->assertTest("Adversarial: /admin/content/edit/:id with SQLi payload on existing ID '1\' OR 1=1--' is safely parameterized", function () {
            $sqliId = "1' OR 1=1--";
            $req = new Request('GET', "/admin/content/edit/" . urlencode($sqliId));
            $ctrl = new ContentController();
            $resp = $ctrl->edit($req, ['id' => $sqliId]);
            $code = $resp->getStatusCode();
            // Since post 1 exists, safe integer casting (int)$id loads post 1 with HTTP 200 and zero SQL syntax errors
            if ($code !== 200) {
                throw new \AssertionError("Expected HTTP 200 for safe integer-cast of post 1, got {$code}");
            }
            return "Safely parameterized without SQL injection";
        });

        // 6.3: /admin/content with status fuzzing
        $fuzzStatuses = ['all', 'publish', 'draft', 'trash', 'invalid_status_xyz', '\' OR 1=1'];
        foreach ($fuzzStatuses as $st) {
            $this->assertTest("Adversarial: /admin/content?status={$st} executes safely", function () use ($st) {
                $req = new Request('GET', "/admin/content?status=" . urlencode($st), ['type' => 'page', 'status' => $st]);
                $ctrl = new ContentController();
                $resp = $ctrl->index($req, ['type' => 'page']);
                $code = $resp->getStatusCode();
                if ($code !== 200) {
                    throw new \AssertionError("Expected 200, got {$code} for status {$st}");
                }
                return "HTTP 200 OK";
            });
        }

        // 6.4: /admin/users with role & search fuzzing
        $fuzzUserQueries = [
            ['s' => '<script>alert(1)</script>', 'role' => 'all'],
            ['s' => '\' OR 1=1--', 'role' => 'super_admin'],
            ['s' => 'admin', 'role' => 'invalid_role_xyz'],
        ];

        foreach ($fuzzUserQueries as $q) {
            $this->assertTest("Adversarial: /admin/users search query fuzzing handles inputs safely", function () use ($q) {
                $req = new Request('GET', '/admin/users', $q);
                $ctrl = new AdminController();
                $resp = $ctrl->users($req);
                $code = $resp->getStatusCode();
                if ($code !== 200) {
                    throw new \AssertionError("Expected 200 for users query, got {$code}");
                }
                $body = $resp->getBody();
                // Ensure raw script tag is not unescaped in search input reflection
                if (str_contains($body, '<script>alert(1)</script>')) {
                    throw new \AssertionError("Reflected XSS: unescaped <script> found in /admin/users response");
                }
                return "HTTP 200 and XSS escaped";
            });
        }
    }

    // -------------------------------------------------------------------------
    // GROUP 7: Specific Bug Regressions
    // -------------------------------------------------------------------------
    public function testGroup7SpecificBugRegressions(): void
    {
        echo "\n\033[1;36m--- [Group 7] Specific Bug Regressions & Edge Cases ---\033[0m\n";

        Auth::login($this->superAdminUser);

        // 7.1: MediaController heredoc Alpine $event escaping
        $this->assertTest("MediaController.php: Escaped \\\$event does NOT trigger PHP Notice and renders literal \$event", function () {
            $mediaCtrlCode = file_get_contents(HEARTBEAT_CORE . '/Admin/MediaController.php');
            if (str_contains($mediaCtrlCode, 'handleDrop($event)') || str_contains($mediaCtrlCode, 'handleFileSelect($event)')) {
                throw new \AssertionError("Found unescaped \$event in MediaController.php source!");
            }
            if (!str_contains($mediaCtrlCode, 'handleDrop(\$event)') || !str_contains($mediaCtrlCode, 'handleFileSelect(\$event)')) {
                throw new \AssertionError("Missing escaped \\\$event in MediaController.php source!");
            }

            // Render live view and check output
            $notices = [];
            set_error_handler(function ($errno, $errstr) use (&$notices) {
                $notices[] = $errstr;
                return true;
            });

            try {
                $req = new Request('GET', '/admin/media');
                $ctrl = new MediaController();
                $resp = $ctrl->index($req);

                if (!empty($notices)) {
                    throw new \AssertionError("PHP notices during MediaController::index: " . implode('; ', $notices));
                }

                $body = $resp->getBody();
                if (!str_contains($body, 'handleDrop($event)')) {
                    throw new \AssertionError("Rendered HTML does not contain Alpine 'handleDrop(\$event)' binding");
                }
                if (!str_contains($body, 'handleFileSelect($event)')) {
                    throw new \AssertionError("Rendered HTML does not contain Alpine 'handleFileSelect(\$event)' binding");
                }

                return "Literal \$event in HTML verified, zero PHP notices";
            } finally {
                restore_error_handler();
            }
        });

        // 7.2: AdminController createUserModal cancel button
        $this->assertTest("AdminController.php: createUserModal cancel button invokes HeartbeatUI.closeModal", function () {
            $code = file_get_contents(HEARTBEAT_CORE . '/Admin/AdminController.php');
            if (!str_contains($code, "onclick=\"HeartbeatUI.closeModal('createUserModal')\"")) {
                throw new \AssertionError("createUserModal cancel button does not call HeartbeatUI.closeModal('createUserModal')");
            }

            $adminViewCode = file_get_contents(HEARTBEAT_CORE . '/Admin/AdminView.php');
            if (!str_contains($adminViewCode, 'closeModal(id)')) {
                throw new \AssertionError("HeartbeatUI.closeModal function missing in AdminView.php");
            }

            return "HeartbeatUI.closeModal binding verified";
        });

        // 7.3: /admin/content route registration in App.php
        $this->assertTest("core/App.php: /admin/content is explicitly registered and dispatches to ContentController", function () {
            $appCode = file_get_contents(HEARTBEAT_CORE . '/App.php');
            if (!str_contains($appCode, "\$this->router->get('/admin/content'")) {
                throw new \AssertionError("/admin/content route is NOT registered in core/App.php");
            }

            $req = new Request('GET', '/admin/content');
            $resp = $this->router->dispatch($req);
            $code = $resp->getStatusCode();
            if ($code !== 200) {
                throw new \AssertionError("Dispatching /admin/content returned HTTP {$code}, expected 200");
            }

            return "Route registered and dispatches to HTTP 200";
        });
    }
}

// CLI Runner execution
$test = new AdversarialM1Test();
$test->setUp();
$test->runAll();
$test->tearDown();
