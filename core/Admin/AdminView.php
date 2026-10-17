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
 * Inspired by herzundseele-digital.de (Deep Slate/Charcoal & Warm Coral)
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
        $flashesJson = htmlspecialchars(json_encode($flashes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]', ENT_QUOTES, 'UTF-8');

        // Navigation definition
        $navItems = self::getNavigationItems($activeNav, $userRole, $isClientGuard);
        $sidebarNavHtml = self::renderSidebarNav($navItems);
        $breadcrumbsHtml = self::renderBreadcrumbs($breadcrumbs, $safeTitle);

        return <<<HTML
<!DOCTYPE html>
<html lang="de" class="dark scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{$safeTitle} - Heartbeat Admin</title>
    {$csrfMeta}
    <link rel="stylesheet" href="/assets/css/installer.css">
    <style>
        /* Custom UI Enhancements for Admin Shell */
        [x-cloak] { display: none !important; }
        .admin-sidebar {
            scrollbar-width: thin;
            scrollbar-color: #1e293b #090d16;
        }
        .admin-sidebar::-webkit-scrollbar {
            width: 5px;
        }
        .admin-sidebar::-webkit-scrollbar-thumb {
            background-color: #1e293b;
            border-radius: 9999px;
        }
    </style>
    <script src="/assets/js/alpine.min.js" defer></script>
</head>
<body class="bg-[#090d16] text-slate-100 font-sans antialiased selection:bg-[#f97316] selection:text-white"
      x-data="{ 
          mobileSidebarOpen: false, 
          userMenuOpen: false, 
          toasts: {$flashesJson},
          addToast(type, message) {
              const id = Date.now() + Math.random();
              this.toasts.push({ id, type, message });
              setTimeout(() => this.removeToast(id), 5000);
          },
          removeToast(id) {
              this.toasts = this.toasts.filter(t => t.id !== id);
          }
      }"
      x-init="
          window.Heartbeat = {
              toast: (type, message) => addToast(type, message)
          };
          // Auto dismiss pre-loaded flashes
          toasts.forEach(t => {
              t.id = t.id || (Date.now() + Math.random());
              setTimeout(() => removeToast(t.id), 5000);
          });
      ">

    <!-- Toast Notification Container -->
    <div class="fixed top-5 right-5 z-50 flex flex-col space-y-3 pointer-events-none max-w-sm w-full" aria-live="polite">
        <template x-for="toast in toasts" :key="toast.id">
            <div class="pointer-events-auto flex items-start space-x-3 p-4 rounded-xl border shadow-2xl backdrop-blur-md transition-all duration-300 transform translate-y-0"
                 :class="{
                     'bg-[#0f172a]/95 border-emerald-500/30 text-emerald-400': toast.type === 'success',
                     'bg-[#0f172a]/95 border-rose-500/30 text-rose-400': toast.type === 'error',
                     'bg-[#0f172a]/95 border-amber-500/30 text-amber-400': toast.type === 'warning',
                     'bg-[#0f172a]/95 border-orange-500/30 text-orange-400': toast.type === 'info'
                 }">
                <!-- Icon -->
                <div class="flex-shrink-0 mt-0.5">
                    <template x-if="toast.type === 'success'">
                        <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    </template>
                    <template x-if="toast.type === 'error'">
                        <svg class="w-5 h-5 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </template>
                    <template x-if="toast.type === 'warning'">
                        <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    </template>
                    <template x-if="toast.type === 'info'">
                        <svg class="w-5 h-5 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </template>
                </div>
                <!-- Message -->
                <div class="flex-1 text-xs font-medium text-slate-200" x-text="toast.message"></div>
                <!-- Close Button -->
                <button @click="removeToast(toast.id)" class="text-slate-400 hover:text-white transition">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
        </template>
    </div>

    <!-- Mobile Sidebar Backdrop -->
    <div x-show="mobileSidebarOpen" 
         x-cloak 
         @click="mobileSidebarOpen = false" 
         class="fixed inset-0 z-40 bg-black/60 backdrop-blur-sm lg:hidden"></div>

    <div class="min-h-screen flex flex-col lg:flex-row">
        <!-- Sidebar Navigation -->
        <aside :class="mobileSidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'"
               class="admin-sidebar fixed inset-y-0 left-0 z-50 w-64 bg-[#0a0f1d] border-r border-[#1e293b] flex flex-col justify-between transition-transform duration-300 ease-in-out lg:static lg:translate-x-0">
            
            <!-- Sidebar Header & Logo -->
            <div>
                <div class="h-16 px-6 flex items-center justify-between border-b border-[#1e293b]">
                    <a href="/admin" class="flex items-center space-x-3 group">
                        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-orange-500 to-amber-600 flex items-center justify-center text-white font-black text-sm shadow-lg shadow-orange-500/20 group-hover:scale-105 transition-transform">
                            HB
                        </div>
                        <div class="flex flex-col">
                            <span class="font-bold text-white tracking-tight text-sm">Heartbeat CMS</span>
                            <span class="text-[10px] text-slate-400 font-mono tracking-wider">{$agencyName}</span>
                        </div>
                    </a>
                    <button @click="mobileSidebarOpen = false" class="lg:hidden text-slate-400 hover:text-white">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>

                <!-- Nav Items -->
                <nav class="p-4 space-y-1">
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
                            <span class="inline-block text-[10px] text-orange-400 font-medium truncate">{$roleLabel}</span>
                        </div>
                    </div>
                    <form method="POST" action="/admin/logout">
                        {$csrfField}
                        <button type="submit" title="Abmelden" class="p-2 rounded-lg text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col min-w-0">
            <!-- Topbar -->
            <header class="h-16 bg-[#090d16]/90 border-b border-[#1e293b] sticky top-0 z-30 backdrop-blur-md px-4 sm:px-8 flex items-center justify-between">
                <!-- Left: Mobile Toggle & Breadcrumbs -->
                <div class="flex items-center space-x-4">
                    <button @click="mobileSidebarOpen = true" class="lg:hidden p-2 rounded-xl bg-slate-800 text-slate-400 hover:text-white transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    </button>
                    {$breadcrumbsHtml}
                </div>

                <!-- Right: Quick Links & Actions -->
                <div class="flex items-center space-x-3">
                    <!-- View Site Link -->
                    <a href="/" target="_blank" class="inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-xl bg-[#141e33] hover:bg-[#1e293b] border border-[#1e293b] text-xs font-medium text-slate-300 hover:text-white transition">
                        <span>Website ansehen</span>
                        <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                    </a>

                    <!-- User Dropdown Menu -->
                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                        <button @click="open = !open" class="flex items-center space-x-2 p-1.5 rounded-xl hover:bg-slate-800/60 transition border border-transparent hover:border-slate-700">
                            <div class="w-7 h-7 rounded-lg bg-gradient-to-br from-orange-500 to-amber-600 text-white font-bold text-xs flex items-center justify-center">
                                {$userInitials}
                            </div>
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>

                        <div x-show="open" 
                             x-cloak
                             class="absolute right-0 mt-2 w-56 bg-[#0f172a] border border-[#1e293b] rounded-2xl shadow-2xl py-2 z-50">
                            <div class="px-4 py-2 border-b border-[#1e293b]">
                                <p class="text-xs font-bold text-white truncate">{$username}</p>
                                <p class="text-[11px] text-orange-400 truncate">{$roleLabel}</p>
                            </div>
                            <a href="/admin/users/edit/{$user['id']}" class="flex items-center space-x-2 px-4 py-2 text-xs text-slate-300 hover:text-white hover:bg-[#1e293b] transition">
                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                                <span>Mein Profil bearbeiten</span>
                            </a>
                            <a href="/admin/settings" class="flex items-center space-x-2 px-4 py-2 text-xs text-slate-300 hover:text-white hover:bg-[#1e293b] transition">
                                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                <span>Einstellungen</span>
                            </a>
                            <div class="border-t border-[#1e293b] my-1"></div>
                            <form method="POST" action="/admin/logout">
                                {$csrfField}
                                <button type="submit" class="w-full text-left flex items-center space-x-2 px-4 py-2 text-xs text-rose-400 hover:bg-rose-500/10 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                                    <span>Abmelden</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content Container -->
            <main class="flex-1 p-4 sm:p-8 max-w-7xl w-full mx-auto">
                <!-- Page Header -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight">{$safeTitle}</h1>
                    </div>
                    {$actionsHtml}
                </div>

                <!-- Page Body -->
                {$contentHtml}
            </main>

            <!-- Footer -->
            <footer class="border-t border-[#1e293b] py-6 px-8 text-center sm:flex sm:justify-between sm:items-center text-xs text-slate-500 bg-[#070b14]">
                <div>
                    &copy; 2026 Heartbeat CMS &bull; Bereitgestellt von <span class="text-slate-400 font-semibold">{$agencyName}</span>
                </div>
                <div class="mt-2 sm:mt-0 font-mono text-[11px] text-slate-600">
                    Version 1.0.0 &bull; PHP 8.3 &bull; All Systems Operational
                </div>
            </footer>
        </div>
    </div>
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
    <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
    </svg>
    <span>{$safeErr}</span>
</div>
HTML;
        }

        $rateLimitAlert = '';
        if ($rateLimitWarning !== '') {
            $safeWarn = Security::e($rateLimitWarning);
            $rateLimitAlert = <<<HTML
<div class="mb-5 p-4 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-400 text-xs flex items-start space-x-3">
    <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
    </svg>
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
    <title>Anmeldung - Heartbeat CMS</title>
    {$csrfMeta}
    <link rel="stylesheet" href="/assets/css/installer.css">
    <script src="/assets/js/alpine.min.js" defer></script>
</head>
<body class="bg-[#090d16] text-white min-h-screen flex items-center justify-center p-4 selection:bg-[#f97316] selection:text-white">
    <!-- Ambient Glow Background -->
    <div class="fixed inset-0 pointer-events-none overflow-hidden">
        <div class="absolute -top-40 -left-40 w-96 h-96 bg-orange-500/10 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-40 -right-40 w-96 h-96 bg-amber-600/10 rounded-full blur-3xl"></div>
    </div>

    <div class="relative w-full max-w-md bg-[#0f172a] border border-[#1e293b] rounded-2xl p-8 shadow-2xl backdrop-blur-xl"
         x-data="{ showPassword: false, loading: false }">
        
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br from-orange-500 to-amber-600 mx-auto mb-4 flex items-center justify-center text-white font-black text-xl shadow-lg shadow-orange-500/25">
                HB
            </div>
            <h1 class="text-2xl font-black tracking-tight text-white">Heartbeat Admin</h1>
            <p class="text-xs text-slate-400 mt-1">Sicherer Zugang für {$agencyName}</p>
        </div>

        {$errorAlert}
        {$rateLimitAlert}

        <!-- Login Form -->
        <form method="POST" action="/admin/login" @submit="loading = true" class="space-y-4">
            {$csrfField}

            <div>
                <label for="username" class="block text-xs font-semibold text-slate-300 mb-1.5">Benutzername oder E-Mail</label>
                <div class="relative">
                    <input type="text" id="username" name="username" required autocomplete="username" autofocus
                           class="w-full px-4 py-3 rounded-xl bg-[#090d16] border border-[#1e293b] text-sm text-white placeholder-slate-500 focus:outline-none focus:border-[#f97316] transition"
                           placeholder="admin oder name@beispiel.de">
                </div>
            </div>

            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label for="password" class="block text-xs font-semibold text-slate-300">Passwort</label>
                    <button type="button" @click="showPassword = !showPassword" class="text-[11px] text-slate-400 hover:text-white transition">
                        <span x-text="showPassword ? 'Verbergen' : 'Anzeigen'"></span>
                    </button>
                </div>
                <div class="relative">
                    <input :type="showPassword ? 'text' : 'password'" id="password" name="password" required autocomplete="current-password"
                           class="w-full px-4 py-3 rounded-xl bg-[#090d16] border border-[#1e293b] text-sm text-white placeholder-slate-500 focus:outline-none focus:border-[#f97316] transition"
                           placeholder="••••••••••••">
                </div>
            </div>

            <!-- Remember me checkbox -->
            <div class="flex items-center justify-between pt-1">
                <label class="flex items-center space-x-2 cursor-pointer">
                    <input type="checkbox" name="remember" value="1" class="w-4 h-4 rounded bg-[#090d16] border-[#1e293b] text-[#f97316] focus:ring-0 focus:ring-offset-0">
                    <span class="text-xs text-slate-400">Angemeldet bleiben</span>
                </label>
                <span class="text-[11px] text-slate-500 font-mono">100% Prepared &amp; Salted</span>
            </div>

            <!-- Submit Button -->
            <button type="submit" 
                    :disabled="loading"
                    class="w-full py-3.5 px-4 rounded-xl bg-[#f97316] hover:bg-[#ea580c] disabled:opacity-50 text-white font-semibold text-sm transition shadow-lg shadow-orange-500/25 flex items-center justify-center space-x-2 mt-6">
                <span x-show="!loading">Anmelden &rarr;</span>
                <span x-show="loading" class="inline-flex items-center space-x-2">
                    <svg class="animate-spin w-4 h-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    <span>Authentifiziere...</span>
                </span>
            </button>
        </form>

        <!-- Back to Website -->
        <div class="mt-8 text-center border-t border-[#1e293b] pt-6">
            <a href="/" class="text-xs text-slate-400 hover:text-orange-400 transition inline-flex items-center space-x-1">
                <span>&larr;</span>
                <span>Zurück zur Website</span>
            </a>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Build navigation item array considering user role and client guard
     */
    private static function getNavigationItems(string $activeNav, string $role, bool $isClientGuard): array
    {
        $items = [
            [
                'slug'       => 'dashboard',
                'label'      => 'Dashboard',
                'url'        => '/admin',
                'icon'       => 'dashboard',
                'permission' => 'access_admin',
            ],
            [
                'slug'       => 'pages',
                'label'      => 'Seiten & Inhalte',
                'url'        => '/admin/pages',
                'icon'       => 'document',
                'permission' => 'manage_content',
            ],
            [
                'slug'       => 'posts',
                'label'      => 'Beiträge (CPT)',
                'url'        => '/admin/posts',
                'icon'       => 'newspaper',
                'permission' => 'edit_posts',
            ],
            [
                'slug'       => 'media',
                'label'      => 'Mediathek',
                'url'        => '/admin/media',
                'icon'       => 'photo',
                'permission' => 'manage_media',
            ],
            [
                'slug'       => 'analytics',
                'label'      => 'DSGVO Analytics',
                'url'        => '/admin/analytics',
                'icon'       => 'chart',
                'permission' => 'manage_content',
            ],
            [
                'slug'       => 'seo',
                'label'      => 'Marketing & SEO',
                'url'        => '/admin/seo',
                'icon'       => 'sparkles',
                'permission' => 'manage_options',
            ],
            [
                'slug'       => 'themes',
                'label'      => 'Design & Themes',
                'url'        => '/admin/themes',
                'icon'       => 'paint',
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
            [
                'slug'       => 'gdpr',
                'label'      => 'DSGVO & Lokale Fonts',
                'url'        => '/admin/gdpr',
                'icon'       => 'shield',
                'permission' => 'manage_options',
            ],
            [
                'slug'       => 'payments',
                'label'      => 'Zahlungen & Stripe',
                'url'        => '/admin/payments',
                'icon'       => 'credit_card',
                'permission' => 'manage_options',
            ],
            [
                'slug'       => 'mail',
                'label'      => 'Transaktions-Mail',
                'url'        => '/admin/mail',
                'icon'       => 'mail',
                'permission' => 'manage_options',
            ],
            [
                'slug'       => 'backups',
                'label'      => 'Datensicherung',
                'url'        => '/admin/backups',
                'icon'       => 'archive',
                'permission' => 'manage_backups',
                'guard_lock' => true,
            ],
            [
                'slug'       => 'updater',
                'label'      => 'System-Updater',
                'url'        => '/admin/updater',
                'icon'       => 'refresh',
                'permission' => 'update_core',
                'guard_lock' => true,
            ],
            [
                'slug'       => 'users',
                'label'      => 'Benutzer & Rollen',
                'url'        => '/admin/users',
                'icon'       => 'users',
                'permission' => 'manage_users',
            ],
            [
                'slug'       => 'settings',
                'label'      => 'Einstellungen',
                'url'        => '/admin/settings',
                'icon'       => 'cog',
                'permission' => 'manage_options',
            ],
            [
                'slug'       => 'system',
                'label'      => 'System & Wartung',
                'url'        => '/admin/system',
                'icon'       => 'chip',
                'permission' => 'manage_backups',
                'guard_lock' => true,
            ],
        ];

        // Filter items based on permissions and set active flag
        $filtered = [];
        foreach ($items as $item) {
            if (Rbac::can($role, $item['permission'], $isClientGuard)) {
                $item['active'] = ($item['slug'] === $activeNav);
                $filtered[] = $item;
            }
        }
        return $filtered;
    }

    /**
     * Generate HTML for sidebar links
     */
    private static function renderSidebarNav(array $items): string
    {
        $html = '';

        foreach ($items as $item) {
            $isActive = ($item['active'] ?? false) || (isset($item['is_active']) && $item['is_active']);
            $activeClass = $isActive
                ? 'bg-orange-500/10 text-orange-400 font-semibold border-orange-500/30'
                : 'text-slate-400 hover:text-slate-200 hover:bg-[#141e33] border-transparent';

            $badgeHtml = '';
            if (!empty($item['badge'])) {
                $badgeHtml = '<span class="ml-auto text-[10px] px-2 py-0.5 rounded-full bg-slate-800 text-slate-400 border border-slate-700 font-mono">' . Security::e($item['badge']) . '</span>';
            }

            $iconSvg = self::getIconSvg($item['icon']);

            $html .= <<<HTML
<a href="{$item['url']}" class="flex items-center space-x-3 px-3.5 py-2.5 rounded-xl border text-xs transition {$activeClass}">
    {$iconSvg}
    <span>{$item['label']}</span>
    {$badgeHtml}
</a>
HTML;
        }

        return $html;
    }

    /**
     * Render SVG Icons
     */
    private static function getIconSvg(string $icon): string
    {
        return match ($icon) {
            'dashboard' => '<svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>',
            'document'  => '<svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>',
            'newspaper' => '<svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"></path></svg>',
            'photo'     => '<svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>',
            'paint'       => '<svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4 5 5 0 015-5h4a2 2 0 012 2v2a2 2 0 01-2 2h-1a2 2 0 00-2 2 1 1 0 01-1 1h-1z"></path></svg>',
            'code'        => '<svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path></svg>',
            'chart'       => '<svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>',
            'sparkles'    => '<svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path></svg>',
            'mail'        => '<svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>',
            'archive'     => '<svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 4H6a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-2m-4-1v8m0 0l3-3m-3 3L9 8m-5 5h2.586a1 1 0 01.707.293l2.414 2.414a1 1 0 00.707.293h2.586a1 1 0 00.707-.293l2.414-2.414a1 1 0 01.707-.293H20"></path></svg>',
            'refresh'     => '<svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>',
            'shield'      => '<svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>',
            'credit_card' => '<svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>',
            'users'       => '<svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>',
            'cog'       => '<svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>',
            'chip'      => '<svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path></svg>',
            default     => '<svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>',
        };
    }

    /**
     * Render Topbar Breadcrumbs
     */
    private static function renderBreadcrumbs(array $breadcrumbs, string $currentPage): string
    {
        $html = '<nav class="flex items-center space-x-2 text-xs text-slate-400">';
        $html .= '<a href="/admin" class="hover:text-white transition">Admin</a>';

        foreach ($breadcrumbs as $crumb) {
            $html .= '<span class="text-slate-600">/</span>';
            $html .= '<a href="' . Security::e($crumb['url']) . '" class="hover:text-white transition">' . Security::e($crumb['label']) . '</a>';
        }

        if (!empty($breadcrumbs) || $currentPage !== 'Dashboard') {
            $html .= '<span class="text-slate-600">/</span>';
            $html .= '<span class="text-white font-medium">' . $currentPage . '</span>';
        }

        $html .= '</nav>';
        return $html;
    }
}

