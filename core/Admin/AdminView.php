<?php
declare(strict_types=1);

namespace Heartbeat\Admin;

use Heartbeat\Auth\Auth;
use Heartbeat\Agency\AgencyService;
use Heartbeat\Security\Security;
use Heartbeat\Security\Session;
use Heartbeat\Security\Csrf;
use Heartbeat\Security\Rbac;

/**
 * Modern Dark-Mode Admin UI Shell Renderer
 * Precision 8pt Grid, Google Material Symbols, Zero-Dependency Vanilla JS
 */
final class AdminView
{
    /**
     * Render a complete admin page with Sidebar, Topbar, Content and Toasts
     *
     * @param string $title Page title
     * @param string $activeNav Active navigation slug (e.g., 'dashboard', 'users', 'settings')
     * @param string $contentHtml Inner HTML content
     * @param array $breadcrumbs List of ['label' => '...', 'url' => '...']
     * @param string $actionsHtml Optional header action buttons
     */
    public static function renderShell(
        string $title,
        string $activeNav,
        string $contentHtml,
        array $breadcrumbs = [],
        string $actionsHtml = ''
    ): string {
        $user = Auth::user() ?? [
            'username' => 'Gast',
            'email'    => '',
            'role'     => 'guest',
        ];

        $agency = AgencyService::getInstance();
        $agencyName = Security::e($agency->getAgencyName());
        $isClientGuard = $agency->isClientGuardActive();

        $safeTitle = Security::e($title);
        $username = Security::e((string)($user['username'] ?? 'Admin'));
        $userRole = (string)($user['role'] ?? 'guest');
        $roleLabel = Security::e(Rbac::getLabel($userRole));
        $userInitials = strtoupper(substr($username, 0, 2));

        $csrfField = Csrf::field();
        $csrfMeta = Csrf::meta();

        // Retrieve flash messages
        $flashes = Session::getFlashes();
        $flashesJson = json_encode($flashes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]';

        // Navigation definition
        $navSections = self::getNavigationSections($activeNav, $userRole, $isClientGuard);
        $sidebarNavHtml = self::renderSidebarNav($navSections);
        $breadcrumbsHtml = self::renderBreadcrumbs($breadcrumbs, $safeTitle);

        // Page Header if title or actions are provided
        $pageHeaderHtml = '';
        if ($title !== '' || $actionsHtml !== '') {
            $titleEl = $title !== '' ? "<div><h1 class=\"text-2xl sm:text-3xl font-black text-white tracking-tight\">{$safeTitle}</h1></div>" : '<div></div>';
            $pageHeaderHtml = <<<HTML
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
                    {$titleEl}
                    {$actionsHtml}
                </div>
HTML;
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="de" class="dark scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$safeTitle} - Heartbeat Admin</title>
    {$csrfMeta}
    <link rel="stylesheet" href="/assets/css/admin.css">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <script src="/assets/js/alpine.min.js" defer></script>
</head>
<body class="bg-[#090d16] text-slate-100 font-sans antialiased selection:bg-[#fb326f] selection:text-white">

    <!-- Toast Notification Container (Native Vanilla JS) -->
    <div id="toast-container" class="fixed top-5 right-5 z-50 flex flex-col space-y-3 pointer-events-none max-w-sm w-full" aria-live="polite"></div>

    <!-- Mobile Sidebar Backdrop -->
    <div id="sidebar-backdrop" onclick="HeartbeatUI.toggleMobileSidebar(false)"
         class="fixed inset-0 z-40 bg-black/70 backdrop-blur-md hidden lg:hidden"></div>

    <div class="admin-shell-layout">
        <!-- Sidebar Navigation -->
        <aside id="admin-sidebar" class="admin-sidebar fixed inset-y-0 left-0 z-50 w-64 bg-[#0a0f1d] border-r border-[#1e293b] flex flex-col justify-between -translate-x-full lg:static lg:translate-x-0 transition-transform duration-200">
            
            <!-- Sidebar Header & Logo -->
            <div class="flex flex-col min-h-0 flex-1">
                <div class="h-16 px-6 flex items-center justify-between border-b border-[#1e293b] flex-shrink-0">
                    <a href="/admin" class="flex items-center space-x-3 group text-decoration-none">
                        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-[#fb326f] to-[#e01e5a] flex items-center justify-center text-white font-black text-sm shadow-lg shadow-[#fb326f]/25 flex-shrink-0">
                            HB
                        </div>
                        <div class="flex flex-col">
                            <span class="font-bold text-white tracking-tight text-sm">Heartbeat CMS</span>
                            <span class="text-[10px] text-slate-400 font-mono tracking-wider">{$agencyName}</span>
                        </div>
                    </a>
                    <button type="button" onclick="HeartbeatUI.toggleMobileSidebar(false)" class="lg:hidden p-1 text-slate-400 hover:text-white">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <!-- Nav Items (Scrollable independently) -->
                <nav class="flex-1 px-3 py-3 overflow-y-auto">
                    {$sidebarNavHtml}
                </nav>
            </div>

            <!-- Sidebar Footer / User Profile & Logout -->
            <div class="p-4 border-t border-[#1e293b] bg-[#070b14]">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-3 overflow-hidden">
                        <div class="w-9 h-9 rounded-xl bg-slate-800 border border-slate-700 flex items-center justify-center text-white font-bold text-xs flex-shrink-0">
                            {$userInitials}
                        </div>
                        <div class="overflow-hidden">
                            <p class="text-xs font-semibold text-white truncate">{$username}</p>
                            <span class="inline-block text-[10px] text-[#fb326f] font-medium truncate">{$roleLabel}</span>
                        </div>
                    </div>
                    <form method="POST" action="/admin/logout">
                        {$csrfField}
                        <button type="submit" title="Abmelden" class="p-2 rounded-lg text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 transition flex items-center">
                            <span class="material-symbols-outlined text-[20px]">logout</span>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <div class="admin-main-content">
            <!-- Topbar -->
            <header class="h-16 bg-[#090d16] border-b border-[#1e293b] sticky top-0 z-30 px-4 sm:px-8 flex items-center justify-between">
                <!-- Left: Mobile Toggle & Breadcrumbs -->
                <div class="flex items-center space-x-4">
                    <button type="button" onclick="HeartbeatUI.toggleMobileSidebar(true)" class="lg:hidden p-2 rounded-xl bg-[#141e33] border border-[#1e293b] text-slate-300 hover:text-white transition flex items-center">
                        <span class="material-symbols-outlined text-[20px]">menu</span>
                    </button>
                    {$breadcrumbsHtml}
                </div>

                <!-- Right: Quick Links & Actions -->
                <div class="flex items-center space-x-3">
                    <!-- Website Preview Link (Direct to Homepage) -->
                    <a href="/" target="_blank" rel="noopener" class="flex items-center space-x-1.5 px-3 py-1.5 rounded-xl bg-[#141e33] border border-[#1e293b] text-slate-300 hover:text-white hover:border-slate-700 hover:bg-[#1e293b] text-xs font-semibold transition" title="Startseite Vorschau öffnen">
                        <span class="material-symbols-outlined text-[18px] text-[#fb326f]">visibility</span>
                        <span>Vorschau</span>
                        <span class="material-symbols-outlined text-[14px] text-slate-500">open_in_new</span>
                    </a>

                    <!-- User Dropdown Menu (Guaranteed Solid Dark, High Contrast) -->
                    <div class="relative">
                        <button type="button" id="user-menu-btn" onclick="HeartbeatUI.toggleUserMenu()" class="user-menu-btn">
                            <div class="w-7 h-7 rounded-lg bg-gradient-to-br from-[#fb326f] to-[#e01e5a] text-white font-bold text-xs flex items-center justify-center flex-shrink-0">
                                {$userInitials}
                            </div>
                            <span class="text-xs font-semibold text-white hidden sm:inline">{$username}</span>
                            <span class="material-symbols-outlined text-slate-400 text-[18px]">expand_more</span>
                        </button>

                        <div id="user-dropdown-menu" class="hidden absolute right-0 mt-2 w-56 bg-[#0f172a] border border-[#1e293b] rounded-2xl shadow-2xl py-2 z-50">
                            <div class="px-4 py-2 border-b border-[#1e293b]">
                                <p class="text-xs font-bold text-white truncate">{$username}</p>
                                <p class="text-[11px] text-[#fb326f] font-mono truncate">{$roleLabel}</p>
                            </div>
                            <a href="/admin/users/edit/{$user['id']}" class="flex items-center space-x-2 px-4 py-2 text-xs text-slate-300 hover:text-white hover:bg-[#141e33] transition">
                                <span class="material-symbols-outlined text-[18px] text-slate-400">person</span>
                                <span>Mein Profil</span>
                            </a>
                            <a href="/admin/settings" class="flex items-center space-x-2 px-4 py-2 text-xs text-slate-300 hover:text-white hover:bg-[#141e33] transition">
                                <span class="material-symbols-outlined text-[18px] text-slate-400">settings</span>
                                <span>Einstellungen</span>
                            </a>
                            <div class="border-t border-[#1e293b] my-1"></div>
                            <form method="POST" action="/admin/logout">
                                {$csrfField}
                                <button type="submit" class="w-full text-left flex items-center space-x-2 px-4 py-2 text-xs text-rose-400 hover:bg-rose-500/10 transition">
                                    <span class="material-symbols-outlined text-[18px] text-rose-400">logout</span>
                                    <span>Abmelden</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content Container -->
            <main class="flex-1 p-4 sm:p-6 md:p-8 max-w-[1800px] w-full mx-auto">
                {$pageHeaderHtml}
                <!-- Page Body -->
                {$contentHtml}
            </main>

            <!-- Footer -->
            <footer class="border-t border-[#1e293b] py-5 px-8 text-center sm:flex sm:justify-between sm:items-center text-xs text-slate-500 bg-[#070b14]">
                <div>
                    &copy; 2026 Heartbeat CMS &bull; <span class="text-slate-400 font-semibold">{$agencyName}</span>
                </div>
                <div class="mt-2 sm:mt-0 font-mono text-[11px] text-slate-600">
                    Version 1.0.0 &bull; PHP 8.3 &bull; Enterprise Core
                </div>
            </footer>
        </div>
    </div>

    <!-- Zero-Dependency Native Vanilla JS Shell Controller -->
    <script>
    window.HeartbeatUI = {
        toggleMobileSidebar(open) {
            const sidebar = document.getElementById('admin-sidebar');
            const backdrop = document.getElementById('sidebar-backdrop');
            if (open) {
                sidebar.classList.remove('-translate-x-full');
                sidebar.classList.add('translate-x-0');
                backdrop.classList.remove('hidden');
            } else {
                sidebar.classList.add('-translate-x-full');
                sidebar.classList.remove('translate-x-0');
                backdrop.classList.add('hidden');
            }
        },
        toggleUserMenu() {
            const menu = document.getElementById('user-dropdown-menu');
            if (menu) {
                menu.classList.toggle('hidden');
            }
        },
        openModal(id) {
            const el = document.getElementById(id);
            if (el) el.classList.remove('hidden');
        },
        closeModal(id) {
            const el = document.getElementById(id);
            if (el) el.classList.add('hidden');
        },
        toast(type, message) {
            const container = document.getElementById('toast-container');
            if (!container) return;
            const toast = document.createElement('div');
            toast.className = 'pointer-events-auto flex items-start space-x-3 p-4 rounded-xl border shadow-2xl backdrop-blur-md transition-all duration-300 bg-[#0f172a] border-[#1e293b] mb-2';
            
            let icon = 'info';
            let colorClass = 'text-[#fb326f]';
            if (type === 'success') { icon = 'check_circle'; colorClass = 'text-emerald-400 border-emerald-500/30'; }
            else if (type === 'error') { icon = 'error'; colorClass = 'text-rose-400 border-rose-500/30'; }
            else if (type === 'warning') { icon = 'warning'; colorClass = 'text-amber-400 border-amber-500/30'; }

            toast.classList.add(colorClass);
            toast.innerHTML = 
                '<span class="material-symbols-outlined ' + colorClass + '">' + icon + '</span>' +
                '<div class="flex-1 text-xs font-medium text-slate-200">' + message + '</div>' +
                '<button type="button" class="text-slate-400 hover:text-white" onclick="this.parentElement.remove()">' +
                    '<span class="material-symbols-outlined text-[16px]">close</span>' +
                '</button>';
            container.appendChild(toast);
            setTimeout(() => { toast.remove(); }, 4500);
        }
    };

    window.Heartbeat = {
        toast: (type, message) => window.HeartbeatUI.toast(type, message),
        openModal: (id) => window.HeartbeatUI.openModal(id),
        closeModal: (id) => window.HeartbeatUI.closeModal(id)
    };

    // Auto-dismiss user menu on outside clicks
    document.addEventListener('click', (e) => {
        const btn = document.getElementById('user-menu-btn');
        const menu = document.getElementById('user-dropdown-menu');
        if (btn && menu && !btn.contains(e.target) && !menu.contains(e.target)) {
            menu.classList.add('hidden');
        }
    });

    // Auto-display any flash messages passed from PHP Session
    try {
        const flashes = {$flashesJson};
        if (Array.isArray(flashes)) {
            flashes.forEach(f => window.HeartbeatUI.toast(f.type, f.message));
        }
    } catch (e) {}
    </script>
</body>
</html>
HTML;
    }

    /**
     * Render the standalone Admin Login page
     */
    public static function renderLogin(string $errorMessage = '', string $rateLimitWarning = ''): string
    {
        $agency = AgencyService::getInstance();
        $agencyName = Security::e($agency->getAgencyName());
        $csrfField = Csrf::field();
        $csrfMeta = Csrf::meta();

        $errorAlert = '';
        if ($errorMessage !== '') {
            $safeErr = Security::e($errorMessage);
            $errorAlert = <<<HTML
<div class="mb-5 p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs flex items-start space-x-3">
    <span class="material-symbols-outlined text-rose-400 flex-shrink-0">error</span>
    <span>{$safeErr}</span>
</div>
HTML;
        }

        $rateLimitAlert = '';
        if ($rateLimitWarning !== '') {
            $safeWarn = Security::e($rateLimitWarning);
            $rateLimitAlert = <<<HTML
<div class="mb-5 p-4 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-400 text-xs flex items-start space-x-3">
    <span class="material-symbols-outlined text-amber-400 flex-shrink-0">warning</span>
    <span>{$safeWarn}</span>
</div>
HTML;
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="de" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - Heartbeat Admin</title>
    {$csrfMeta}
    <link rel="stylesheet" href="/assets/css/admin.css">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
</head>
<body class="bg-[#090d16] text-slate-100 font-sans antialiased min-h-screen flex items-center justify-center p-4 selection:bg-[#fb326f] selection:text-white">

    <!-- Ambient Glow Background -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none">
        <div class="absolute -top-40 left-1/2 -translate-x-1/2 w-96 h-96 bg-[#fb326f]/10 rounded-full blur-3xl"></div>
    </div>

    <!-- Login Card -->
    <div class="w-full max-w-md bg-[#0f172a] border border-[#1e293b] rounded-2xl p-8 shadow-2xl relative z-10">
        <!-- Logo & Branding -->
        <div class="text-center mb-8">
            <div class="w-12 h-12 mx-auto rounded-xl bg-gradient-to-br from-[#fb326f] to-[#e01e5a] flex items-center justify-center text-white font-black text-lg shadow-lg shadow-[#fb326f]/25 mb-4">
                HB
            </div>
            <h1 class="text-xl font-bold text-white tracking-tight">Heartbeat CMS</h1>
            <p class="text-xs text-slate-400 mt-1">{$agencyName}</p>
        </div>

        {$errorAlert}
        {$rateLimitAlert}

        <!-- Login Form -->
        <form method="POST" action="/admin/login" class="space-y-4">
            {$csrfField}

            <!-- Username / Email -->
            <div>
                <label for="username" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Benutzername oder E-Mail</label>
                <input type="text" id="username" name="username" required autofocus autocomplete="username"
                       placeholder="admin">
            </div>

            <!-- Password -->
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label for="password" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">Passwort</label>
                </div>
                <input type="password" id="password" name="password" required autocomplete="current-password"
                       placeholder="••••••••••••">
            </div>

            <!-- Remember me checkbox -->
            <div class="flex items-center justify-between pt-1">
                <label class="flex items-center space-x-2 cursor-pointer">
                    <input type="checkbox" name="remember" value="1">
                    <span class="text-xs text-slate-400">Angemeldet bleiben</span>
                </label>
            </div>

            <!-- Submit Button -->
            <div class="pt-2">
                <button type="submit" class="btn-primary w-full py-3.5">
                    <span>Anmelden</span>
                    <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                </button>
            </div>
        </form>

        <!-- Back to Website -->
        <div class="mt-8 text-center border-t border-[#1e293b] pt-6">
            <a href="/" class="text-xs text-slate-400 hover:text-[#fb326f] transition inline-flex items-center space-x-1">
                <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                <span>Zurück zur Website</span>
            </a>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Build structured navigation sections considering user role and client guard
     * Inspired by WordPress & modern CMS architectures (Content, Appearance, Analytics, Tools, Settings)
     *
     * @return array<int, array{title: ?string, items: array<int, array<string, mixed>>}>
     */
    private static function getNavigationSections(string $activeNav, string $role, bool $isClientGuard): array
    {
        $sections = [
            [
                'title' => null,
                'items' => [
                    [
                        'slug'       => 'dashboard',
                        'label'      => 'Dashboard',
                        'url'        => '/admin',
                        'icon'       => 'dashboard',
                        'permission' => 'access_admin',
                    ],
                ],
            ],
            [
                'title' => 'Content',
                'items' => [
                    [
                        'slug'       => 'pages',
                        'label'      => 'Seiten',
                        'url'        => '/admin/pages',
                        'icon'       => 'description',
                        'permission' => 'manage_content',
                    ],
                    [
                        'slug'       => 'posts',
                        'label'      => 'Beiträge',
                        'url'        => '/admin/posts',
                        'icon'       => 'article',
                        'permission' => 'edit_posts',
                    ],
                    [
                        'slug'       => 'media',
                        'label'      => 'Mediathek',
                        'url'        => '/admin/media',
                        'icon'       => 'photo_library',
                        'permission' => 'manage_media',
                    ],
                ],
            ],
            [
                'title' => 'Appearance',
                'items' => [
                    [
                        'slug'       => 'themes',
                        'label'      => 'Design & Themes',
                        'url'        => '/admin/themes',
                        'icon'       => 'palette',
                        'permission' => 'manage_themes',
                    ],
                    [
                        'slug'       => 'custom_code',
                        'label'      => 'Code Manager',
                        'url'        => '/admin/custom-code',
                        'icon'       => 'code',
                        'permission' => 'manage_code',
                        'guard_lock' => true,
                    ],
                ],
            ],
            [
                'title' => 'Analytics & SEO',
                'items' => [
                    [
                        'slug'       => 'analytics',
                        'label'      => 'Analytics',
                        'url'        => '/admin/analytics',
                        'icon'       => 'analytics',
                        'permission' => 'manage_content',
                    ],
                    [
                        'slug'       => 'seo',
                        'label'      => 'SEO & AI',
                        'url'        => '/admin/seo',
                        'icon'       => 'auto_awesome',
                        'permission' => 'manage_options',
                    ],
                ],
            ],
            [
                'title' => 'Tools',
                'items' => [
                    [
                        'slug'       => 'backups',
                        'label'      => 'Backups',
                        'url'        => '/admin/backups',
                        'icon'       => 'backup',
                        'permission' => 'manage_backups',
                        'guard_lock' => true,
                    ],
                    [
                        'slug'       => 'gdpr',
                        'label'      => 'DSGVO & Fonts',
                        'url'        => '/admin/gdpr',
                        'icon'       => 'shield',
                        'permission' => 'manage_options',
                    ],
                    [
                        'slug'       => 'payments',
                        'label'      => 'Zahlungen',
                        'url'        => '/admin/payments',
                        'icon'       => 'credit_card',
                        'permission' => 'manage_options',
                    ],
                    [
                        'slug'       => 'mail',
                        'label'      => 'E-Mail',
                        'url'        => '/admin/mail',
                        'icon'       => 'mail',
                        'permission' => 'manage_options',
                    ],
                    [
                        'slug'       => 'updater',
                        'label'      => 'System-Update',
                        'url'        => '/admin/updater',
                        'icon'       => 'sync',
                        'permission' => 'update_core',
                        'guard_lock' => true,
                    ],
                ],
            ],
            [
                'title' => 'Settings',
                'items' => [
                    [
                        'slug'       => 'users',
                        'label'      => 'Benutzer',
                        'url'        => '/admin/users',
                        'icon'       => 'group',
                        'permission' => 'manage_users',
                    ],
                    [
                        'slug'       => 'settings',
                        'label'      => 'Einstellungen',
                        'url'        => '/admin/settings',
                        'icon'       => 'settings',
                        'permission' => 'manage_options',
                    ],
                    [
                        'slug'       => 'system',
                        'label'      => 'Systemstatus',
                        'url'        => '/admin/system',
                        'icon'       => 'memory',
                        'permission' => 'manage_backups',
                        'guard_lock' => true,
                    ],
                ],
            ],
        ];

        // Filter items per section based on RBAC and Client-Guard
        $filteredSections = [];
        foreach ($sections as $section) {
            $filteredItems = [];
            foreach ($section['items'] as $item) {
                if (Rbac::can($role, $item['permission'], $isClientGuard)) {
                    $item['active'] = ($item['slug'] === $activeNav);
                    $filteredItems[] = $item;
                }
            }
            if (!empty($filteredItems)) {
                $filteredSections[] = [
                    'title' => $section['title'],
                    'items' => $filteredItems,
                ];
            }
        }

        return $filteredSections;
    }

    /**
     * Backward-compatible flat navigation items getter
     */
    public static function getNavigationItems(string $activeNav, string $role, bool $isClientGuard): array
    {
        $sections = self::getNavigationSections($activeNav, $role, $isClientGuard);
        $flat = [];
        foreach ($sections as $s) {
            foreach ($s['items'] as $item) {
                $flat[] = $item;
            }
        }
        return $flat;
    }

    /**
     * Generate HTML for sidebar links grouped into structured sections
     */
    private static function renderSidebarNav(array $sections): string
    {
        $html = '';

        foreach ($sections as $section) {
            $html .= '<div class="nav-section">';

            if (!empty($section['title'])) {
                $safeTitle = Security::e($section['title']);
                $html .= "<div class=\"nav-section-title\">{$safeTitle}</div>";
            }

            $html .= '<div class="space-y-0.5">';
            foreach ($section['items'] as $item) {
                $isActive = !empty($item['active']);
                $activeClass = $isActive ? ' active' : '';

                $badgeHtml = '';
                if (!empty($item['badge'])) {
                    $badgeHtml = '<span class="ml-auto text-[10px] px-2 py-0.5 rounded-full bg-slate-800 text-slate-400 border border-slate-700 font-mono">' . Security::e($item['badge']) . '</span>';
                }

                $iconName = Security::e($item['icon']);
                $safeLabel = Security::e($item['label']);

                $html .= <<<HTML
<a href="{$item['url']}" class="nav-item{$activeClass}">
    <span class="material-symbols-outlined nav-icon">{$iconName}</span>
    <span class="truncate">{$safeLabel}</span>
    {$badgeHtml}
</a>
HTML;
            }
            $html .= '</div></div>';
        }

        return $html;
    }

    /**
     * Render Topbar Breadcrumbs
     */
    private static function renderBreadcrumbs(array $breadcrumbs, string $currentPage): string
    {
        $html = '<nav class="admin-breadcrumbs flex items-center space-x-2 text-xs text-white" aria-label="Brotkrumen-Navigation">';
        $html .= '<a href="/admin" class="breadcrumb-link text-white font-medium hover:text-white transition">Admin</a>';

        foreach ($breadcrumbs as $crumb) {
            $html .= '<span class="text-slate-600">/</span>';
            $html .= '<a href="' . Security::e($crumb['url']) . '" class="breadcrumb-link text-white font-medium hover:text-white transition">' . Security::e($crumb['label']) . '</a>';
        }

        if (!empty($breadcrumbs) || $currentPage !== 'Dashboard') {
            $html .= '<span class="text-slate-600">/</span>';
            $html .= '<span class="text-white font-bold">' . $currentPage . '</span>';
        }

        $html .= '</nav>';
        return $html;
    }
}
