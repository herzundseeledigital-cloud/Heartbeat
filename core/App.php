<?php
declare(strict_types=1);

namespace Heartbeat;

use Heartbeat\Http\Request;
use Heartbeat\Http\Response;
use Heartbeat\Http\Router;
use Heartbeat\Security\Security;
use Heartbeat\Security\Csrf;
use Heartbeat\Installer\InstallerController;
use Heartbeat\Database\Database;

use Heartbeat\Admin\AdminController;
use Heartbeat\Admin\ContentController;
use Heartbeat\Admin\MediaController;
use Heartbeat\Admin\ThemeController;
use Heartbeat\Admin\CustomCodeController;
use Heartbeat\Blocks\BlockRenderer;
use Heartbeat\Content\PostService;
use Heartbeat\Content\PostTypeRegistry;
use Heartbeat\Auth\Auth;
use Heartbeat\Themes\ThemeService;
use Heartbeat\Themes\Theme;
use Heartbeat\Cache\Cache;
use Heartbeat\CustomCode\CustomCodeService;
use Heartbeat\Admin\AgencyModulesController;
use Heartbeat\Analytics\AnalyticsService;
use Heartbeat\Seo\SeoService;
use Heartbeat\Backup\BackupService;
use Heartbeat\Payments\StripeService;

/**
 * Application Kernel
 */
final class App
{
    private Router $router;
    private Request $request;

    public function __construct()
    {
        $this->router = new Router();
        $this->request = Request::capture();
    }

    public function run(): void
    {
        // 1. Send baseline security headers
        Security::sendSecurityHeaders();

        // 2. Check installation status
        $isInstalled = Bootstrap::isInstalled();
        $path = $this->request->getPath();

        // Favicon direct handler (never trigger 404 or 500)
        if ($path === '/favicon.ico') {
            $icoPath = dirname(__DIR__) . '/public/favicon.ico';
            if (file_exists($icoPath)) {
                (new Response(file_get_contents($icoPath), 200, [
                    'Content-Type'  => 'image/x-icon',
                    'Cache-Control' => 'public, max-age=86400',
                ]))->send();
                return;
            }
            (new Response('', 204))->send();
            return;
        }

        // 3. If not installed, redirect all non-install requests to /install
        if (!$isInstalled && !str_starts_with($path, '/install')) {
            Response::redirect('/install')->send();
            return;
        }

        // 4. Register installer routes
        $this->router->get('/install', [InstallerController::class, 'index']);
        $this->router->post('/install/api/test-db', [InstallerController::class, 'testDatabase']);
        $this->router->post('/install/api/run-install', [InstallerController::class, 'runInstall']);

        // 5. Register application routes if installed
        if ($isInstalled) {
            // Check static full-page cache for non-admin GET requests (<20ms TTFB)
            if ($this->request->getMethod() === 'GET' && !str_starts_with($path, '/admin') && !str_starts_with($path, '/install')) {
                AnalyticsService::getInstance()->track($this->request);

                $cachedHtml = Cache::page()->get($this->request);
                if ($cachedHtml !== null) {
                    Response::html($cachedHtml)
                        ->withHeader('X-Cache', 'HIT')
                        ->withHeader('X-Heartbeat-Cache', 'Static Full-Page')
                        ->send();
                    return;
                }
            }

            $this->registerCoreRoutes();
        } else {
            $this->router->get('/', fn() => Response::redirect('/install'));
        }

        // 6. Dispatch and send
        $response = $this->router->dispatch($this->request);
        $response->send();
    }

    private function registerCoreRoutes(): void
    {
        // Admin Authentication & Session
        $this->router->get('/admin/login', [AdminController::class, 'login']);
        $this->router->post('/admin/login', [AdminController::class, 'doLogin']);
        $this->router->post('/admin/logout', [AdminController::class, 'logout']);

        // Admin Dashboard & Core Shell
        $this->router->get('/admin', [AdminController::class, 'dashboard']);

        // Admin User & RBAC Management
        $this->router->get('/admin/users', [AdminController::class, 'users']);
        $this->router->post('/admin/users/create', [AdminController::class, 'createUser']);
        $this->router->get('/admin/users/edit/:id', [AdminController::class, 'editUser']);
        $this->router->post('/admin/users/edit/:id', [AdminController::class, 'editUser']);
        $this->router->post('/admin/users/delete/:id', [AdminController::class, 'deleteUser']);

        // Admin Settings & Agency Mode
        $this->router->get('/admin/settings', [AdminController::class, 'settings']);
        $this->router->post('/admin/settings', [AdminController::class, 'settings']);

        // Admin System Status & Cache Maintenance
        $this->router->get('/admin/system', [AdminController::class, 'system']);
        $this->router->post('/admin/system/clear-cache', [AdminController::class, 'clearCache']);

        // Phase 3: Content Management & Block Builder
        $this->router->get('/admin/content', fn(Request $r, array $p) => (new ContentController())->index($r, ['type' => 'page']));
        $this->router->get('/admin/pages', fn(Request $r, array $p) => (new ContentController())->index($r, ['type' => 'page']));
        $this->router->get('/admin/posts', fn(Request $r, array $p) => (new ContentController())->index($r, $p));
        $this->router->get('/admin/content/create', [ContentController::class, 'create']);
        $this->router->get('/admin/content/edit/:id', [ContentController::class, 'edit']);
        $this->router->post('/admin/content/save', [ContentController::class, 'save']);
        $this->router->post('/admin/content/save/:id', [ContentController::class, 'save']);
        $this->router->post('/admin/content/trash/:id', [ContentController::class, 'trash']);
        $this->router->post('/admin/content/restore/:id', [ContentController::class, 'restore']);
        $this->router->post('/admin/content/delete/:id', [ContentController::class, 'delete']);

        // Phase 3: Mediathek & Asset Pipeline
        $this->router->get('/admin/media', [MediaController::class, 'index']);
        $this->router->post('/admin/media/upload', [MediaController::class, 'upload']);
        $this->router->post('/admin/media/delete/:id', [MediaController::class, 'delete']);
        $this->router->get('/admin/api/media', [MediaController::class, 'apiList']);

        // Phase 4: Live Theme Engine & Customizer Studio
        $this->router->get('/admin/themes', [ThemeController::class, 'index']);
        $this->router->post('/admin/themes/activate/:slug', [ThemeController::class, 'activate']);
        $this->router->get('/admin/themes/customize', [ThemeController::class, 'customize']);
        $this->router->post('/admin/themes/save', [ThemeController::class, 'save']);
        $this->router->post('/admin/api/themes/compile-css', [ThemeController::class, 'compileCssApi']);

        // Phase 4: Safe Custom Code Manager
        $this->router->get('/admin/custom-code', [CustomCodeController::class, 'index']);
        $this->router->post('/admin/custom-code/save', [CustomCodeController::class, 'save']);

        // Phase 4: Compiled Theme CSS Static Bundle Route (with live theme preview support)
        $this->router->get('/assets/css/theme.css', function (Request $r) {
            $themeService = ThemeService::getInstance();
            $preview = (string)$r->get('theme_preview', '');
            if ($preview !== '' && ($theme = $themeService->get($preview))) {
                $compiler = CssCompiler::getInstance();
                $css = $compiler->compileForTheme($theme);
            } else {
                $css = $themeService->compileActiveThemeCss();
            }
            return new Response($css, 200, [
                'Content-Type'  => 'text/css; charset=UTF-8',
                'Cache-Control' => 'no-cache, must-revalidate',
            ]);
        });

        // Phase 5: Built-in Agency Modules & Lifecycle Tools
        // Analytics
        $this->router->get('/admin/analytics', [AgencyModulesController::class, 'analytics']);

        // Marketing, SEO & AI Assistant
        $this->router->get('/admin/seo', [AgencyModulesController::class, 'seo']);
        $this->router->post('/admin/seo', [AgencyModulesController::class, 'seo']);
        $this->router->post('/admin/api/ai/generate', [AgencyModulesController::class, 'generateAiContent']);

        // Transactional Mail
        $this->router->get('/admin/mail', [AgencyModulesController::class, 'mail']);
        $this->router->post('/admin/mail', [AgencyModulesController::class, 'mail']);
        $this->router->post('/admin/mail/test', [AgencyModulesController::class, 'sendTestMail']);

        // Zero-Exec Backups
        $this->router->get('/admin/backups', [AgencyModulesController::class, 'backups']);
        $this->router->post('/admin/backups/create', [AgencyModulesController::class, 'createBackupAction']);
        $this->router->get('/admin/backups/download/:file', [AgencyModulesController::class, 'downloadBackup']);
        $this->router->post('/admin/backups/delete/:file', [AgencyModulesController::class, 'deleteBackupAction']);
        $this->router->get('/api/backup/cron', function (Request $r) {
            $token = (string)$r->query('token', '');
            $backupService = BackupService::getInstance();
            if (!$backupService->verifyCronToken($token)) {
                return Response::json(['success' => false, 'error' => 'Ungültiges Sicherheits-Token'], 403);
            }
            $res = $backupService->createBackup(true, true);
            return Response::json($res);
        });

        // 1-Click OTA Core Updater
        $this->router->get('/admin/updater', [AgencyModulesController::class, 'updater']);
        $this->router->post('/admin/updater', [AgencyModulesController::class, 'updater']);
        $this->router->post('/admin/updater/apply', [AgencyModulesController::class, 'applyUpdate']);

        // GDPR Tools & Google Fonts Downloader
        $this->router->get('/admin/gdpr', [AgencyModulesController::class, 'gdpr']);
        $this->router->get('/admin/appearance/fonts', [AgencyModulesController::class, 'gdpr']);
        $this->router->get('/admin/fonts', [AgencyModulesController::class, 'gdpr']);
        $this->router->post('/admin/gdpr/download-font', [AgencyModulesController::class, 'downloadFontAction']);
        $this->router->post('/admin/fonts/download', [AgencyModulesController::class, 'downloadFontAction']);
        $this->router->post('/admin/gdpr/export-user', [AgencyModulesController::class, 'exportUserAction']);
        $this->router->post('/admin/gdpr/anonymize-user', [AgencyModulesController::class, 'anonymizeUserAction']);

        // Payments & Stripe Integration
        $this->router->get('/admin/payments', [AgencyModulesController::class, 'payments']);
        $this->router->post('/admin/payments', [AgencyModulesController::class, 'payments']);
        $this->router->post('/api/stripe/checkout', function (Request $r) {
            $stripe = StripeService::getInstance();
            $params = [
                'title'          => (string)$r->post('title', 'Mitgliedschaft & Premium-Zugang'),
                'amount_cents'   => (int)$r->post('amount_cents', 1000),
                'customer_email' => (string)$r->post('email', ''),
                'user_id'        => Auth::id() ?: null,
                'plan'           => (string)$r->post('plan', 'member_access'),
            ];
            $res = $stripe->createCheckoutSession($params);
            if ($res['success']) {
                if ($r->isAjax()) {
                    return Response::json($res);
                }
                return Response::redirect($res['checkout_url']);
            }
            return Response::json($res, 400);
        });
        $this->router->post('/api/stripe/webhook', function (Request $r) {
            $payload = $r->getRawBody();
            $sigHeader = $r->getHeader('stripe-signature');
            $res = StripeService::getInstance()->handleWebhook($payload, $sigHeader);
            return Response::json($res, $res['success'] ? 200 : 400);
        });

        // Dynamic XML Sitemap
        $this->router->get('/sitemap.xml', function (Request $r) {
            $xml = SeoService::getInstance()->generateSitemap();
            return new Response($xml, 200, [
                'Content-Type'  => 'text/xml; charset=UTF-8',
                'Cache-Control' => 'public, max-age=3600',
            ]);
        });

        // Dynamic robots.txt
        $this->router->get('/robots.txt', function (Request $r) {
            $txt = SeoService::getInstance()->generateRobotsTxt();
            return new Response($txt, 200, [
                'Content-Type'  => 'text/plain; charset=UTF-8',
                'Cache-Control' => 'public, max-age=3600',
            ]);
        });

        // Contact & Inquiry Submission Endpoints (Honeypot & CSRF Protected)
        $this->router->post('/api/contact', [\Heartbeat\Http\ContactController::class, 'submit']);
        $this->router->post('/contact', [\Heartbeat\Http\ContactController::class, 'submit']);

        // Frontend Homepage
        $this->router->get('/', fn(Request $r) => $this->renderFrontendPage('home', 'page'));

        // Frontend Dynamic CPT archive and single views: /{cpt_prefix} and /{cpt_prefix}/:slug
        $postTypes = PostTypeRegistry::getInstance()->getAll();
        foreach ($postTypes as $pt) {
            $prefix = $pt->getSlugPrefix();
            if ($prefix !== '' && $pt->getName() !== 'page') {
                $ptName = $pt->getName();
                $this->router->get('/' . $prefix, function (Request $r) use ($ptName, $prefix) {
                    return $this->renderCptArchivePage($ptName, $prefix);
                });
                $this->router->get('/' . $prefix . '/:slug', function (Request $r, array $params) use ($ptName) {
                    return $this->renderFrontendPage((string)($params['slug'] ?? ''), $ptName);
                });
            }
        }

        // Frontend Dynamic Pages: /:slug
        $this->router->get('/:slug', function (Request $r, array $params) {
            $slug = (string)($params['slug'] ?? '');
            if (str_starts_with($slug, 'admin') || str_starts_with($slug, 'install') || str_starts_with($slug, 'assets') || str_starts_with($slug, 'uploads')) {
                return $this->render404();
            }
            return $this->renderFrontendPage($slug, 'page');
        });

        // Frontend Nested Subpages: /:parent/:slug
        $this->router->get('/:parent/:slug', function (Request $r, array $params) {
            $parent = (string)($params['parent'] ?? '');
            $slug = (string)($params['slug'] ?? '');
            if (str_starts_with($parent, 'admin') || str_starts_with($parent, 'install') || str_starts_with($parent, 'assets') || str_starts_with($parent, 'uploads')) {
                return $this->render404();
            }
            $fullSlug = $parent . '/' . $slug;
            return $this->renderFrontendPage($fullSlug, 'page');
        });
    }

    private function renderFrontendPage(string $slug, string $postType = 'page'): Response
    {
        try {
            $db = Database::getInstance();
            $posts = PostService::getInstance();
            $renderer = BlockRenderer::getInstance();
            $themeService = ThemeService::getInstance();
            $activeTheme = $this->getRequestedTheme($themeService);

            $post = $posts->findBySlug($slug, $postType);

            $siteTitle = $db->fetchColumn("SELECT option_value FROM {prefix}options WHERE option_name = 'site_title'") ?: 'Heartbeat CMS';
            $siteUrl = (string)$db->fetchColumn("SELECT option_value FROM {prefix}options WHERE option_name = 'site_url'") ?: 'http://localhost:8000';
            
            $navData = $this->buildNavigationTree($db);
            $navTree = $navData['tree'];
            $navPages = $navData['flat'];

            // Build base context
            $renderedContent = '';
            if ($post) {
                // Permissions check for drafts
                if ($post['status'] !== 'publish') {
                    $canViewDraft = Auth::check() && Auth::can('edit_posts');
                    if (!$canViewDraft) {
                        return $this->render404();
                    }
                }

                $blocks = json_decode((string)($post['content_blocks'] ?? '[]'), true);
                if (!is_array($blocks) || empty($blocks)) {
                    $renderedContent = !empty($post['content_html']) ? $post['content_html'] : '<p class="text-slate-400">Keine Inhalte vorhanden.</p>';
                } else {
                    $renderedContent = $renderer->render($blocks, ['post' => $post]);
                }
            }

            $context = [
                'post'            => $post,
                'title'           => $post ? $post['title'] : (string)$siteTitle,
                'siteTitle'       => (string)$siteTitle,
                'siteUrl'         => $siteUrl,
                'theme'           => $activeTheme,
                'themeService'    => $themeService,
                'customCss'       => $themeService->getCustomCss($activeTheme->getSlug()),
                'contentHtml'     => $renderedContent,
                'navPages'        => $navPages,
                'navTree'         => $navTree,
                'postType'        => $postType,
                'slug'            => $slug,
                'request'         => $this->request,
                'db'              => $db,
                'csrfField'       => Csrf::field(),
                'csrfToken'       => Csrf::token(),
                'seoTags'         => $post ? SeoService::getInstance()->renderMetaTags($post, (string)$siteTitle, $siteUrl) : '',
            ];

            // Resolve template candidates from active theme
            $candidates = [];
            if ($slug === 'home') {
                $candidates = ['home', 'page'];
            } elseif ($postType === 'property') {
                $candidates = ['single-property', 'property', 'single', 'page'];
            } elseif ($postType === 'team_member') {
                $candidates = ['single-team_member', 'team_member', 'single', 'page'];
            } elseif ($postType === 'case_study') {
                $candidates = ['single-case_study', 'case_study', 'single', 'page'];
            } elseif ($slug === 'pricing') {
                $candidates = ['pricing', 'page'];
            } elseif ($slug === 'team') {
                $candidates = ['team', 'archive-team_member', 'page'];
            } elseif ($slug === 'referenzen') {
                $candidates = ['case-studies', 'archive-case_study', 'page'];
            } else {
                $candidates = ['page', 'single'];
            }

            // 1. Try rendering via active theme template
            foreach ($candidates as $cand) {
                $tplHtml = $themeService->renderTemplate($cand, $context);
                if ($tplHtml !== null) {
                    $tplHtml = CustomCodeService::getInstance()->inject($tplHtml, $this->request);
                    Cache::page()->put($this->request, $tplHtml);
                    return Response::html($tplHtml)->withHeader('X-Cache', 'MISS');
                }
            }

            // Handle Homepage fallback if 'home' slug page is missing and no template caught it
            if (!$post && $slug === 'home') {
                $html = $this->renderFallbackHomeHtml((string)$siteTitle, $activeTheme);
                $html = CustomCodeService::getInstance()->inject($html, $this->request);
                Cache::page()->put($this->request, $html);
                return Response::html($html)->withHeader('X-Cache', 'MISS');
            }

            if (!$post) {
                return $this->render404();
            }

            // 2. Fallback to default page shell
            $html = $this->renderPageShell($post['title'], (string)$siteTitle, $siteUrl, $activeTheme, $renderedContent, $navPages, $post, $navTree);
            $html = CustomCodeService::getInstance()->inject($html, $this->request);

            // Store in static full-page cache
            Cache::page()->put($this->request, $html);

            return Response::html($html)->withHeader('X-Cache', 'MISS');
        } catch (\Throwable $e) {
            return Response::html("<h1>Heartbeat CMS</h1><p>" . Security::e($e->getMessage()) . "</p>", 500);
        }
    }

    private function renderCptArchivePage(string $postType, string $prefix): Response
    {
        try {
            $db = Database::getInstance();
            $posts = PostService::getInstance();
            $themeService = ThemeService::getInstance();
            $activeTheme = $this->getRequestedTheme($themeService);

            $siteTitle = $db->fetchColumn("SELECT option_value FROM {prefix}options WHERE option_name = 'site_title'") ?: 'Heartbeat CMS';
            $siteUrl = (string)$db->fetchColumn("SELECT option_value FROM {prefix}options WHERE option_name = 'site_url'") ?: 'http://localhost:8000';
            
            $navData = $this->buildNavigationTree($db);
            $navTree = $navData['tree'];
            $navPages = $navData['flat'];

            $items = $posts->query(['post_type' => $postType, 'status' => 'publish', 'limit' => 50]);

            $candidates = [
                $prefix === 'team' ? 'team' : ($prefix === 'referenzen' ? 'case-studies' : ($prefix === 'immobilien' ? 'property' : 'archive-' . $postType)),
                'archive-' . $postType,
                $postType,
                'archive',
                'page',
            ];

            $context = [
                'items'        => $items,
                'postType'     => $postType,
                'prefix'       => $prefix,
                'title'        => ucfirst($postType),
                'siteTitle'    => (string)$siteTitle,
                'siteUrl'      => $siteUrl,
                'theme'        => $activeTheme,
                'themeService' => $themeService,
                'customCss'    => $themeService->getCustomCss($activeTheme->getSlug()),
                'navPages'     => $navPages,
                'navTree'      => $navTree,
                'request'      => $this->request,
                'db'           => $db,
                'csrfField'    => Csrf::field(),
                'csrfToken'    => Csrf::token(),
                'seoTags'      => '',
                'contentHtml'  => '',
            ];

            foreach ($candidates as $cand) {
                $tplHtml = $themeService->renderTemplate($cand, $context);
                if ($tplHtml !== null) {
                    $tplHtml = CustomCodeService::getInstance()->inject($tplHtml, $this->request);
                    Cache::page()->put($this->request, $tplHtml);
                    return Response::html($tplHtml)->withHeader('X-Cache', 'MISS');
                }
            }

            // Fallback: render dynamic page if created
            return $this->renderFrontendPage($prefix, 'page');
        } catch (\Throwable) {
            return $this->render404();
        }
    }

    private function renderPageShell(string $title, string $siteTitle, string $siteUrl, Theme $theme, string $contentHtml, array $navPages, array $post, array $navTree = []): string
    {
        $safePageTitle = Security::e($title);
        $safeSiteTitle = Security::e($siteTitle);
        $safeThemeName = Security::e($theme->getName());
        $cssVars = $theme->getCssVariables();
        $settings = $theme->getSettings();
        $containerWidth = Security::e((string)($settings['container_width'] ?? 'max-w-6xl'));
        $customCss = ThemeService::getInstance()->getCustomCss($theme->getSlug());
        $customCssTag = !empty($customCss) ? "\n    <style id=\"theme-custom-css\">{$customCss}</style>" : '';

        if (empty($navTree)) {
            $navTree = array_map(fn($p) => ['title' => $p['title'], 'slug' => $p['slug'], 'children' => []], $navPages);
        }

        $navLinks = '';
        foreach ($navTree as $item) {
            $npTitle = Security::e($item['title']);
            $npSlug = Security::e($item['slug']);
            $url = $npSlug === 'home' ? '/' : '/' . $npSlug;

            if (!empty($item['children'])) {
                $childrenHtml = '';
                foreach ($item['children'] as $child) {
                    $cTitle = Security::e($child['title']);
                    $cSlug = Security::e($child['slug']);
                    $childrenHtml .= "<a href=\"/{$cSlug}\" class=\"block px-4 py-2 text-xs text-slate-300 hover:text-white hover:bg-slate-800/60 transition\">{$cTitle}</a>";
                }
                $navLinks .= <<<HTML
<div class="relative group">
    <a href="{$url}" class="text-xs font-semibold text-slate-300 hover:text-white transition flex items-center space-x-1 py-2">
        <span>{$npTitle}</span>
        <svg class="w-3.5 h-3.5 opacity-60 group-hover:rotate-180 transition-transform" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
    </a>
    <div class="absolute left-0 mt-0.5 w-48 rounded-xl bg-[#0f172a] border border-[#1e293b] shadow-2xl py-1.5 hidden group-hover:block z-50">
        <a href="{$url}" class="block px-4 py-2 text-xs font-bold text-slate-200 hover:text-white hover:bg-slate-800/60 transition border-b border-[#1e293b]/60">{$npTitle} (Übersicht)</a>
        {$childrenHtml}
    </div>
</div>
HTML;
            } else {
                $navLinks .= "<a href=\"{$url}\" class=\"text-xs font-semibold text-slate-300 hover:text-white transition py-2\">{$npTitle}</a>";
            }
        }

        $seoTags = SeoService::getInstance()->renderMetaTags($post, $siteTitle, $siteUrl);

        return <<<HTML
<!DOCTYPE html>
<html lang="de" class="dark scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {$seoTags}
    <style>
        {$cssVars}
    </style>
    <link rel="stylesheet" href="/assets/css/installer.css">
    <link rel="stylesheet" href="/assets/css/theme.css">{$customCssTag}
</head>
<body class="bg-[#090d16] text-slate-100 font-sans antialiased selection:bg-[var(--hb-primary)] selection:text-white min-h-screen flex flex-col justify-between">
    <!-- Navbar -->
    <header class="border-b border-[#1e293b] bg-[#090d16]/80 backdrop-blur sticky top-0 z-50">
        <div class="{$containerWidth} mx-auto px-6 h-16 flex items-center justify-between">
            <a href="/" class="flex items-center space-x-3">
                <div class="w-8 h-8 rounded-lg bg-hb-primary flex items-center justify-center text-white font-black text-sm" style="background-color: var(--hb-primary);">
                    HB
                </div>
                <span class="font-bold text-white tracking-tight">{$safeSiteTitle}</span>
            </a>
            
            <nav class="hidden md:flex items-center space-x-6">
                {$navLinks}
            </nav>

            <div class="flex items-center space-x-4">
                <a href="/admin/login" class="text-xs font-semibold px-4 py-2 rounded-xl bg-hb-primary bg-hb-primary-hover text-white transition shadow-lg">
                    Adminbereich &rarr;
                </a>
            </div>
        </div>
    </header>

    <!-- Page Content Rendered from Structured Blocks -->
    <main class="{$containerWidth} mx-auto px-6 py-12 flex-grow w-full">
        {$contentHtml}
    </main>

    <!-- Footer -->
    <footer class="border-t border-[#1e293b] py-8 text-center text-xs text-slate-500 bg-[#090d16]">
        <div class="{$containerWidth} mx-auto px-6 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div>&copy; {$safeSiteTitle} &bull; Powered by Heartbeat CMS</div>
            <div class="text-[11px] text-slate-600 font-mono">Theme: {$safeThemeName} &bull; TTFB &lt; 20ms</div>
        </div>
    </footer>
</body>
</html>
HTML;
    }

    private function renderFallbackHomeHtml(string $siteTitle, Theme $theme): string
    {
        $safeTitle = Security::e($siteTitle);
        $safeTheme = Security::e($theme->getName());
        $cssVars = $theme->getCssVariables();
        $customCss = ThemeService::getInstance()->getCustomCss($theme->getSlug());
        $customCssTag = !empty($customCss) ? "\n    <style id=\"theme-custom-css\">{$customCss}</style>" : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="de" class="dark scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$safeTitle} - Heartbeat CMS</title>
    <style>
        {$cssVars}
    </style>
    <link rel="stylesheet" href="/assets/css/installer.css">
    <link rel="stylesheet" href="/assets/css/theme.css">{$customCssTag}
</head>
<body class="bg-[#090d16] text-slate-100 font-sans antialiased selection:bg-[var(--hb-primary)] selection:text-white">
    <header class="border-b border-[#1e293b] bg-[#090d16]/80 backdrop-blur sticky top-0 z-50">
        <div class="max-w-6xl mx-auto px-6 h-16 flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="w-8 h-8 rounded-lg bg-hb-primary flex items-center justify-center text-white font-black text-sm" style="background-color: var(--hb-primary);">
                    HB
                </div>
                <span class="font-bold text-white tracking-tight">{$safeTitle}</span>
            </div>
            <div class="flex items-center space-x-4">
                <span class="text-xs px-2.5 py-1 rounded-full bg-slate-800 text-slate-400 border border-slate-700">Theme: {$safeTheme}</span>
                <a href="/admin/login" class="text-xs font-semibold px-4 py-2 rounded-xl bg-hb-primary bg-hb-primary-hover text-white transition shadow-lg">
                    Adminbereich &rarr;
                </a>
            </div>
        </div>
    </header>

    <main class="max-w-6xl mx-auto px-6 py-20 text-center">
        <div class="inline-flex items-center space-x-2 px-3.5 py-1.5 rounded-full border text-xs font-semibold mb-6" style="background-color: color-mix(in srgb, var(--hb-primary) 10%, transparent); border-color: color-mix(in srgb, var(--hb-primary) 20%, transparent); color: var(--hb-primary);">
            <span>●</span>
            <span>Heartbeat CMS Core v1.0.0 Online</span>
        </div>
        <h1 class="text-4xl sm:text-6xl font-black text-white tracking-tight max-w-3xl mx-auto leading-tight mb-6">
            Willkommen bei Heartbeat CMS
        </h1>
        <p class="text-lg text-slate-400 max-w-2xl mx-auto mb-10">
            Das moderne, blitzschnelle und ultra-sichere Headless/Hybrid CMS für digitale Agenturen.
        </p>
        <div class="flex flex-wrap items-center justify-center gap-4">
            <a href="/admin/login" class="px-6 py-3 rounded-xl bg-hb-primary bg-hb-primary-hover text-white font-semibold text-sm transition shadow-lg">
                Admin Dashboard &rarr;
            </a>
        </div>
    </main>

    <footer class="border-t border-[#1e293b] py-8 text-center text-xs text-slate-500">
        &copy; Heartbeat CMS &bull; All rights reserved.
    </footer>
</body>
</html>
HTML;
    }

    private function render404(): Response
    {
        try {
            $themeService = ThemeService::getInstance();
            $theme = $this->getRequestedTheme($themeService);
            $context = [
                'theme'        => $theme,
                'themeService' => $themeService,
                'title'        => '404 - Seite nicht gefunden',
                'siteTitle'    => 'Heartbeat CMS',
            ];
            $tplHtml = $themeService->renderTemplate('404', $context);
            if ($tplHtml !== null) {
                return Response::html($tplHtml, 404);
            }
        } catch (\Throwable) {
            // Fallback to static 404
        }

        return Response::html(
            '<!DOCTYPE html><html lang="de" class="dark"><head><meta charset="utf-8"><title>404 - Seite nicht gefunden</title>' .
            '<link rel="stylesheet" href="/assets/css/installer.css"></head>' .
            '<body class="bg-[#090d16] text-slate-400 font-sans flex items-center justify-center min-h-screen m-0">' .
            '<div class="text-center p-8"><div class="w-16 h-16 rounded-2xl bg-slate-800 text-slate-200 mx-auto mb-4 flex items-center justify-center text-2xl font-bold">404</div>' .
            '<h1 class="text-white text-2xl font-bold mb-2">Seite nicht gefunden</h1>' .
            '<p class="text-xs text-slate-400 mb-6">Die gewünschte Seite oder dieser Beitrag existiert leider nicht.</p>' .
            '<a href="/" class="px-5 py-2.5 rounded-xl bg-slate-800 text-white text-xs font-semibold hover:bg-slate-700 transition">&larr; Zurück zur Startseite</a></div></body></html>',
            404
        );
    }

    /**
     * Resolve the active theme or preview theme requested via ?theme_preview=slug
     */
    private function getRequestedTheme(ThemeService $themeService): \Heartbeat\Themes\Theme
    {
        $preview = (string)$this->request->get('theme_preview', '');
        if ($preview !== '') {
            $previewTheme = $themeService->get($preview);
            if ($previewTheme !== null) {
                return $previewTheme;
            }
        }
        return $themeService->getActive();
    }

    /**
     * Build hierarchical navigation tree from published pages
     * @return array{tree: array<int, array<string, mixed>>, flat: array<int, array<string, mixed>>}
     */
    private function buildNavigationTree(Database $db): array
    {
        $allPages = $db->fetchAll(
            "SELECT id, uuid, parent_id, title, slug FROM {prefix}posts 
             WHERE post_type = 'page' AND status = 'publish' 
             ORDER BY id ASC"
        );

        $lookup = [];
        $tree = [];

        foreach ($allPages as $p) {
            $p['children'] = [];
            $lookup[(int)$p['id']] = $p;
        }

        foreach ($lookup as $id => $p) {
            $parentId = (int)($p['parent_id'] ?? 0);
            if ($parentId > 0 && isset($lookup[$parentId])) {
                $lookup[$parentId]['children'][] = &$lookup[$id];
            } else {
                $tree[] = &$lookup[$id];
            }
        }

        return [
            'tree' => $tree,
            'flat' => array_values(array_filter($allPages, fn($p) => $p['slug'] !== 'home')),
        ];
    }
}
