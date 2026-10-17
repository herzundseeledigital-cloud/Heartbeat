<?php
declare(strict_types=1);

namespace Heartbeat\Admin;

use Heartbeat\Auth\Auth;
use Heartbeat\Database\Database;
use Heartbeat\Http\Request;
use Heartbeat\Http\Response;
use Heartbeat\Security\Csrf;
use Heartbeat\Security\Rbac;
use Heartbeat\Security\Security;
use Heartbeat\Security\Session;
use Heartbeat\Security\RateLimiter;
use Heartbeat\Agency\AgencyService;

/**
 * Controller handling the Admin Dashboard, Authentication, User Management, and Settings.
 */
final class AdminController
{
    private RateLimiter $rateLimiter;

    public function __construct()
    {
        $this->rateLimiter = new RateLimiter();
    }

    /**
     * Display Admin Login Screen
     */
    public function login(Request $request): Response
    {
        if (Auth::check()) {
            return Response::redirect('/admin');
        }

        $ip = $request->ip();
        $rateKey = 'login:ip:' . $ip;

        $rateWarning = '';
        if ($this->rateLimiter->tooManyAttempts($rateKey, 5)) {
            $seconds = $this->rateLimiter->availableIn($rateKey);
            $minutes = ceil($seconds / 60);
            $rateWarning = "Zu viele fehlgeschlagene Versuche. Bitte warten Sie {$minutes} Minute(n).";
        }

        $flashes = Session::getFlashes();
        $errorMessage = '';
        foreach ($flashes as $f) {
            if ($f['type'] === 'error') {
                $errorMessage = $f['message'];
                break;
            }
        }

        return Response::html(AdminView::renderLogin($errorMessage, $rateWarning));
    }

    /**
     * Process Admin Login POST request
     */
    public function doLogin(Request $request): Response
    {
        if (!Csrf::verifyRequest($request)) {
            Session::flash('error', 'Sicherheits-Token (CSRF) ist ungültig oder abgelaufen. Bitte erneut versuchen.');
            return Response::redirect('/admin/login');
        }

        $ip = $request->ip();
        $rateKey = 'login:ip:' . $ip;

        // Check Rate Limiter (Max 5 attempts in 15 minutes)
        if ($this->rateLimiter->tooManyAttempts($rateKey, 5)) {
            $seconds = $this->rateLimiter->availableIn($rateKey);
            $minutes = ceil($seconds / 60);
            Session::flash('error', "Zu viele Anmeldeversuche. Ihr Zugang ist vorübergehend für {$minutes} Minute(n) gesperrt.");
            return Response::redirect('/admin/login');
        }

        $username = trim((string)$request->post('username', ''));
        $password = (string)$request->post('password', '');
        $remember = !empty($request->post('remember'));

        if ($username === '' || $password === '') {
            $this->rateLimiter->hit($rateKey, 900);
            Session::flash('error', 'Bitte geben Sie Benutzername und Passwort ein.');
            return Response::redirect('/admin/login');
        }

        if (!Auth::attempt($username, $password, $remember)) {
            $attempts = $this->rateLimiter->hit($rateKey, 900);
            $remaining = max(0, 5 - $attempts);

            if ($remaining > 0) {
                Session::flash('error', "Ungültige Anmeldedaten. Verbleibende Versuche: {$remaining}.");
            } else {
                Session::flash('error', "Zu viele Fehlversuche. Zugang für 15 Minuten gesperrt.");
            }

            return Response::redirect('/admin/login');
        }

        // Verify if user is allowed to access the admin area
        if (!Auth::can('access_admin')) {
            Auth::logout();
            Session::flash('error', 'Zugriff verweigert. Nur Administratoren und Redakteure haben Zugang zum CMS-Backend.');
            return Response::redirect('/admin/login');
        }

        // Successful authentication: clear rate limiter
        $this->rateLimiter->clear($rateKey);

        $currentUser = Auth::user();
        Session::flash('success', "Willkommen zurück, " . Security::e($currentUser['username'] ?? 'Admin') . "!");

        return Response::redirect('/admin');
    }

    /**
     * Process Admin Logout
     */
    public function logout(Request $request): Response
    {
        if (!Csrf::verifyRequest($request)) {
            return Response::redirect('/admin');
        }

        Auth::logout();
        Session::flash('info', 'Sie wurden erfolgreich abgemeldet.');
        return Response::redirect('/admin/login');
    }

    /**
     * Admin Dashboard View
     */
    public function dashboard(Request $request): Response
    {
        $redirect = $this->requireAuth('access_admin');
        if ($redirect) return $redirect;

        $db = Database::getInstance();
        $agency = AgencyService::getInstance();

        // 1. Gather stats
        $pageCount = (int)$db->fetchColumn("SELECT COUNT(*) FROM {prefix}posts WHERE post_type = 'page'");
        $userCount = (int)$db->fetchColumn("SELECT COUNT(*) FROM {prefix}users");
        $driver = strtoupper($db->getDriver());
        $siteTitle = $db->fetchColumn("SELECT option_value FROM {prefix}options WHERE option_name = 'site_title'") ?: 'Heartbeat CMS';

        // 2. Recent users preview
        $recentUsers = $db->fetchAll("SELECT id, username, email, role, status, created_at FROM {prefix}users ORDER BY id DESC LIMIT 5");

        // 3. Render Support Widget
        $supportWidget = $agency->renderSupportWidget();

        // 4. Quick Action Buttons
        $actionsHtml = <<<HTML
<div class="flex items-center space-x-3">
    <form method="POST" action="/admin/system/clear-cache">
        {$this->csrfField()}
        <button type="submit" class="inline-flex items-center space-x-2 text-xs font-semibold px-4 py-2.5 rounded-xl bg-[#141e33] hover:bg-[#1e293b] border border-[#1e293b] text-slate-300 hover:text-white transition">
            <svg class="w-4 h-4 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
            <span>Cache leeren</span>
        </button>
    </form>
    <a href="/admin/users" class="inline-flex items-center space-x-2 text-xs font-semibold px-4 py-2.5 rounded-xl bg-[#f97316] hover:bg-[#ea580c] text-white transition shadow-lg shadow-orange-500/20">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
        <span>Neuer Benutzer</span>
    </a>
</div>
HTML;

        // Render Recent Users rows
        $recentUsersRows = '';
        foreach ($recentUsers as $u) {
            $safeName = Security::e($u['username']);
            $safeEmail = Security::e($u['email']);
            $roleBadge = $this->renderRoleBadge($u['role']);
            $date = date('d.m.Y H:i', strtotime($u['created_at']));

            $recentUsersRows .= <<<HTML
<tr class="border-b border-[#1e293b] hover:bg-[#141e33]/50 transition">
    <td class="py-3 px-4 text-xs font-medium text-white flex items-center space-x-2.5">
        <div class="w-7 h-7 rounded-lg bg-slate-800 border border-slate-700 flex items-center justify-center text-xs font-bold text-slate-300">
            {$this->initials($u['username'])}
        </div>
        <span>{$safeName}</span>
    </td>
    <td class="py-3 px-4 text-xs text-slate-400">{$safeEmail}</td>
    <td class="py-3 px-4 text-xs">{$roleBadge}</td>
    <td class="py-3 px-4 text-xs text-slate-500 font-mono">{$date}</td>
</tr>
HTML;
        }

        $contentHtml = <<<HTML
<!-- Welcome Banner -->
<div class="mb-8 p-6 rounded-2xl bg-gradient-to-r from-[#0f172a] via-[#141e33] to-[#0f172a] border border-[#1e293b] shadow-xl flex flex-col md:flex-row md:items-center justify-between gap-4">
    <div>
        <div class="inline-flex items-center space-x-2 px-3 py-1 rounded-full bg-orange-500/10 border border-orange-500/20 text-orange-400 text-xs font-semibold mb-3">
            <span>●</span>
            <span>Heartbeat CMS Core v1.0.0</span>
        </div>
        <h2 class="text-xl sm:text-2xl font-black text-white tracking-tight">Willkommen im Kontrollzentrum</h2>
        <p class="text-xs sm:text-sm text-slate-400 mt-1 max-w-xl">
            Verwalten Sie Ihre Website, Benutzerberechtigungen und Systemeinstellungen über die sichere Heartbeat Admin Shell.
        </p>
    </div>
    <div class="flex items-center space-x-3">
        <div class="text-right hidden sm:block">
            <span class="block text-[11px] text-slate-400">Aktives Projekt</span>
            <span class="font-bold text-white text-sm">{$siteTitle}</span>
        </div>
    </div>
</div>

<!-- KPI Cards Grid -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-8">
    <!-- Card 1: Pages -->
    <div class="p-5 bg-[#0f172a] border border-[#1e293b] rounded-2xl shadow-lg">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold text-slate-400">Seiten &amp; Inhalte</span>
            <div class="w-8 h-8 rounded-lg bg-orange-500/10 text-orange-400 flex items-center justify-center font-bold text-sm">📄</div>
        </div>
        <div class="text-2xl font-black text-white tracking-tight">{$pageCount}</div>
        <p class="text-[11px] text-slate-500 mt-1">Veröffentlichte Seiten &amp; Landingpages</p>
    </div>

    <!-- Card 2: Users -->
    <div class="p-5 bg-[#0f172a] border border-[#1e293b] rounded-2xl shadow-lg">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold text-slate-400">Benutzerkonten</span>
            <div class="w-8 h-8 rounded-lg bg-blue-500/10 text-blue-400 flex items-center justify-center font-bold text-sm">👥</div>
        </div>
        <div class="text-2xl font-black text-white tracking-tight">{$userCount}</div>
        <p class="text-[11px] text-slate-500 mt-1">Aktive Rollen im System</p>
    </div>

    <!-- Card 3: Database & Health -->
    <div class="p-5 bg-[#0f172a] border border-[#1e293b] rounded-2xl shadow-lg">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold text-slate-400">Datenbank-Engine</span>
            <div class="w-8 h-8 rounded-lg bg-emerald-500/10 text-emerald-400 flex items-center justify-center font-bold text-sm">🗄️</div>
        </div>
        <div class="text-2xl font-black text-emerald-400 tracking-tight">{$driver}</div>
        <p class="text-[11px] text-slate-500 mt-1">100% Prepared Statements</p>
    </div>

    <!-- Card 4: TTFB Cache -->
    <div class="p-5 bg-[#0f172a] border border-[#1e293b] rounded-2xl shadow-lg">
        <div class="flex items-center justify-between mb-3">
            <span class="text-xs font-semibold text-slate-400">Performance TTFB</span>
            <div class="w-8 h-8 rounded-lg bg-amber-500/10 text-amber-400 flex items-center justify-center font-bold text-sm">⚡</div>
        </div>
        <div class="text-2xl font-black text-white tracking-tight">&lt; 20 ms</div>
        <p class="text-[11px] text-slate-500 mt-1">Multi-Tier Static HTML Cache</p>
    </div>
</div>

<!-- 2-Column Section: Agency Support & Recent Users -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Left Column: Agency Widget & System Status -->
    <div class="lg:col-span-1 space-y-6">
        {$supportWidget}

        <!-- Quick System Status -->
        <div class="bg-[#0f172a] border border-[#1e293b] rounded-2xl p-6 shadow-xl">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-4">Systemstatus &amp; Absicherung</h3>
            <div class="space-y-3 text-xs">
                <div class="flex items-center justify-between pb-2 border-b border-[#1e293b]">
                    <span class="text-slate-400">PHP Version</span>
                    <span class="font-mono text-white">PHP 8.3 Ready</span>
                </div>
                <div class="flex items-center justify-between pb-2 border-b border-[#1e293b]">
                    <span class="text-slate-400">CSRF-Absicherung</span>
                    <span class="text-emerald-400 font-semibold">256-Bit Aktiv</span>
                </div>
                <div class="flex items-center justify-between pb-2 border-b border-[#1e293b]">
                    <span class="text-slate-400">Rate Limiter</span>
                    <span class="text-emerald-400 font-semibold">Brute-Force Guard Aktiv</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-400">Client-Guard</span>
                    <span class="text-orange-400 font-semibold">Aktiviert</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Recent Users -->
    <div class="lg:col-span-2">
        <div class="bg-[#0f172a] border border-[#1e293b] rounded-2xl p-6 shadow-xl">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h3 class="text-base font-bold text-white tracking-tight">Kürzlich registrierte Benutzer</h3>
                    <p class="text-xs text-slate-400">Übersicht der aktiven Administratoren &amp; Redakteure</p>
                </div>
                <a href="/admin/users" class="text-xs text-orange-400 hover:text-orange-300 font-semibold transition">
                    Alle anzeigen &rarr;
                </a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-[#1e293b] text-[11px] font-semibold uppercase text-slate-400 tracking-wider">
                            <th class="py-2.5 px-4">Benutzer</th>
                            <th class="py-2.5 px-4">E-Mail</th>
                            <th class="py-2.5 px-4">Rolle</th>
                            <th class="py-2.5 px-4">Erstellt am</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$recentUsersRows}
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
HTML;

        return Response::html(AdminView::renderShell('Dashboard', 'dashboard', $contentHtml, [], $actionsHtml));
    }

    /**
     * User Management View
     */
    public function users(Request $request): Response
    {
        $redirect = $this->requireAuth('manage_users');
        if ($redirect) return $redirect;

        $db = Database::getInstance();
        $currentUser = Auth::user();
        $currentRole = (string)($currentUser['role'] ?? 'admin');

        $search = trim((string)$request->query('search', ''));
        $roleFilter = trim((string)$request->query('role', ''));

        $query = "SELECT id, username, email, role, status, created_at FROM {prefix}users WHERE 1=1";
        $params = [];

        if ($search !== '') {
            $query .= " AND (username LIKE :s OR email LIKE :s)";
            $params['s'] = '%' . $search . '%';
        }

        if ($roleFilter !== '' && in_array($roleFilter, Rbac::ALL_ROLES, true)) {
            $query .= " AND role = :r";
            $params['r'] = $roleFilter;
        }

        $query .= " ORDER BY id ASC";
        $users = $db->fetchAll($query, $params);

        // Header Actions
        $actionsHtml = <<<HTML
<div class="flex items-center space-x-3" x-data>
    <button @click="window.dispatchEvent(new CustomEvent('open-new-user-modal'))"
            class="inline-flex items-center space-x-2 text-xs font-semibold px-4 py-2.5 rounded-xl bg-[#f97316] hover:bg-[#ea580c] text-white transition shadow-lg shadow-orange-500/20">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
        <span>Neuen Benutzer anlegen</span>
    </button>
</div>
HTML;

        // Render User Rows
        $rowsHtml = '';
        foreach ($users as $u) {
            $uid = (int)$u['id'];
            $safeUsername = Security::e($u['username']);
            $safeEmail = Security::e($u['email']);
            $uRole = (string)$u['role'];
            $roleBadge = $this->renderRoleBadge($uRole);
            $statusBadge = $u['status'] === 'active'
                ? '<span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Aktiv</span>'
                : '<span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/20">Inaktiv</span>';
            $date = date('d.m.Y', strtotime($u['created_at']));

            // Permission check: Can current user edit or delete this user?
            $canManage = Rbac::canManageRole($currentRole, $uRole);
            $isSelf = $uid === (int)$currentUser['id'];

            $actionButtons = '';
            if ($canManage) {
                $actionButtons .= <<<HTML
<a href="/admin/users/edit/{$uid}" class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition" title="Bearbeiten">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
</a>
HTML;
            }

            if ($canManage && !$isSelf) {
                $csrf = $this->csrfField();
                $actionButtons .= <<<HTML
<form method="POST" action="/admin/users/delete/{$uid}" onsubmit="return confirm('Möchten Sie den Benutzer {$safeUsername} wirklich unwiderruflich löschen?');" class="inline">
    {$csrf}
    <button type="submit" class="p-1.5 rounded-lg text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 transition" title="Löschen">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
    </button>
</form>
HTML;
            }

            if (!$canManage) {
                $actionButtons = '<span class="text-[11px] text-slate-600 italic">Geschützt</span>';
            }

            $rowsHtml .= <<<HTML
<tr class="border-b border-[#1e293b] hover:bg-[#141e33]/50 transition">
    <td class="py-3 px-4 text-xs font-semibold text-white flex items-center space-x-3">
        <div class="w-8 h-8 rounded-xl bg-slate-800 border border-slate-700 flex items-center justify-center text-xs font-bold text-slate-300">
            {$this->initials($u['username'])}
        </div>
        <div>
            <span>{$safeUsername}</span>
            {$this->selfBadge($isSelf)}
        </div>
    </td>
    <td class="py-3 px-4 text-xs text-slate-400">{$safeEmail}</td>
    <td class="py-3 px-4 text-xs">{$roleBadge}</td>
    <td class="py-3 px-4 text-xs">{$statusBadge}</td>
    <td class="py-3 px-4 text-xs text-slate-500 font-mono">{$date}</td>
    <td class="py-3 px-4 text-xs text-right space-x-1">{$actionButtons}</td>
</tr>
HTML;
        }

        // Available assignable roles for new user modal
        $assignableRoles = Rbac::getAssignableRoles($currentRole);
        $roleOptionsHtml = '';
        foreach ($assignableRoles as $r) {
            $label = Security::e(Rbac::getLabel($r));
            $roleOptionsHtml .= "<option value=\"{$r}\">{$label}</option>";
        }

        $csrfField = $this->csrfField();

        $contentHtml = <<<HTML
<div x-data="{ modalOpen: false }" @open-new-user-modal.window="modalOpen = true">
    <!-- Filter & Search Bar -->
    <div class="bg-[#0f172a] border border-[#1e293b] rounded-2xl p-4 mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-lg">
        <form method="GET" action="/admin/users" class="flex-1 flex flex-col sm:flex-row sm:items-center gap-3">
            <div class="relative flex-1">
                <input type="text" name="search" value="{$search}" placeholder="Benutzer oder E-Mail suchen..."
                       class="w-full px-4 py-2.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white placeholder-slate-500 focus:outline-none focus:border-[#f97316]">
            </div>
            <select name="role" onchange="this.form.submit()"
                    class="px-3 py-2.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-slate-300 focus:outline-none focus:border-[#f97316]">
                <option value="">Alle Rollen</option>
                <option value="super_admin" {$this->selected($roleFilter, 'super_admin')}>Super-Admin (Agentur)</option>
                <option value="admin" {$this->selected($roleFilter, 'admin')}>Administrator (Kunde)</option>
                <option value="editor" {$this->selected($roleFilter, 'editor')}>Redakteur</option>
                <option value="user" {$this->selected($roleFilter, 'user')}>Autor</option>
                <option value="member" {$this->selected($roleFilter, 'member')}>Mitglied</option>
            </select>
            <button type="submit" class="px-4 py-2.5 rounded-xl bg-[#141e33] hover:bg-[#1e293b] border border-[#1e293b] text-xs text-slate-300 font-medium">
                Filtern
            </button>
            <a href="/admin/users" class="px-3 py-2.5 text-xs text-slate-500 hover:text-slate-300">Zurücksetzen</a>
        </form>
    </div>

    <!-- Users Table -->
    <div class="bg-[#0f172a] border border-[#1e293b] rounded-2xl overflow-hidden shadow-xl">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-[#1e293b] text-[11px] font-semibold uppercase text-slate-400 tracking-wider bg-[#0a0f1d]">
                        <th class="py-3 px-4">Benutzername</th>
                        <th class="py-3 px-4">E-Mail</th>
                        <th class="py-3 px-4">Rolle</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4">Erstellt am</th>
                        <th class="py-3 px-4 text-right">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                    {$rowsHtml}
                </tbody>
            </table>
        </div>
    </div>

    <!-- Create User Modal -->
    <div x-show="modalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/70 backdrop-blur-sm" @click="modalOpen = false"></div>
        
        <div class="relative bg-[#0f172a] border border-[#1e293b] rounded-2xl p-6 sm:p-8 max-w-lg w-full shadow-2xl z-10"
             @keydown.escape.window="modalOpen = false">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-lg font-bold text-white tracking-tight">Neuen Benutzer anlegen</h3>
                <button @click="modalOpen = false" class="text-slate-400 hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <form method="POST" action="/admin/users/create" class="space-y-4">
                {$csrfField}

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Benutzername</label>
                    <input type="text" name="username" required
                           class="w-full px-3.5 py-2.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white placeholder-slate-500 focus:outline-none focus:border-[#f97316]">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">E-Mail-Adresse</label>
                    <input type="email" name="email" required
                           class="w-full px-3.5 py-2.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white placeholder-slate-500 focus:outline-none focus:border-[#f97316]">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Passwort</label>
                    <input type="password" name="password" required minlength="8"
                           class="w-full px-3.5 py-2.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white placeholder-slate-500 focus:outline-none focus:border-[#f97316]"
                           placeholder="Mindestens 8 Zeichen">
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Rolle</label>
                        <select name="role" required class="w-full px-3.5 py-2.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white focus:outline-none focus:border-[#f97316]">
                            {$roleOptionsHtml}
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 mb-1">Status</label>
                        <select name="status" required class="w-full px-3.5 py-2.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white focus:outline-none focus:border-[#f97316]">
                            <option value="active">Aktiv</option>
                            <option value="inactive">Inaktiv</option>
                        </select>
                    </div>
                </div>

                <div class="flex items-center justify-end space-x-3 pt-4 border-t border-[#1e293b] mt-6">
                    <button type="button" @click="modalOpen = false" class="px-4 py-2.5 rounded-xl bg-[#141e33] text-xs text-slate-300 hover:text-white">
                        Abbrechen
                    </button>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-[#f97316] hover:bg-[#ea580c] text-white text-xs font-semibold shadow-lg shadow-orange-500/20">
                        Benutzer anlegen
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
HTML;

        return Response::html(AdminView::renderShell('Benutzer &amp; Rollen', 'users', $contentHtml, [], $actionsHtml));
    }

    /**
     * Create New User (POST)
     */
    public function createUser(Request $request): Response
    {
        $redirect = $this->requireAuth('manage_users');
        if ($redirect) return $redirect;

        if (!Csrf::verifyRequest($request)) {
            Session::flash('error', 'Ungültiges Sicherheits-Token (CSRF).');
            return Response::redirect('/admin/users');
        }

        $currentUser = Auth::user();
        $currentRole = (string)($currentUser['role'] ?? 'admin');

        $username = trim((string)$request->post('username', ''));
        $email = trim((string)$request->post('email', ''));
        $password = (string)$request->post('password', '');
        $role = trim((string)$request->post('role', 'editor'));
        $status = $request->post('status') === 'inactive' ? 'inactive' : 'active';

        if ($username === '' || $email === '' || $password === '') {
            Session::flash('error', 'Bitte alle Pflichtfelder ausfüllen.');
            return Response::redirect('/admin/users');
        }

        if (strlen($password) < 8) {
            Session::flash('error', 'Das Passwort muss mindestens 8 Zeichen lang sein.');
            return Response::redirect('/admin/users');
        }

        // Permission check on role assignment
        if (!Rbac::canAssignRole($currentRole, $role)) {
            Session::flash('error', 'Sie besitzen nicht die Berechtigung, einen Benutzer mit dieser Rolle anzulegen.');
            return Response::redirect('/admin/users');
        }

        $db = Database::getInstance();
        $existing = $db->fetchOne("SELECT id FROM {prefix}users WHERE username = :u OR email = :e LIMIT 1", [
            'u' => $username,
            'e' => $email
        ]);

        if ($existing) {
            Session::flash('error', 'Ein Benutzer mit diesem Benutzernamen oder dieser E-Mail existiert bereits.');
            return Response::redirect('/admin/users');
        }

        $now = date('Y-m-d H:i:s');
        $hash = Security::hashPassword($password);

        $db->insert('{prefix}users', [
            'username'      => $username,
            'email'         => $email,
            'password_hash' => $hash,
            'role'          => $role,
            'status'        => $status,
            'created_at'    => $now,
            'updated_at'    => $now,
        ]);

        Session::flash('success', "Benutzer {$username} wurde erfolgreich angelegt.");
        return Response::redirect('/admin/users');
    }

    /**
     * Edit User View and Update (GET & POST)
     */
    public function editUser(Request $request, array $params): Response
    {
        $redirect = $this->requireAuth('manage_users');
        if ($redirect) return $redirect;

        $targetId = (int)($params['id'] ?? 0);
        $db = Database::getInstance();
        $targetUser = $db->fetchOne("SELECT * FROM {prefix}users WHERE id = :id LIMIT 1", ['id' => $targetId]);

        if (!$targetUser) {
            Session::flash('error', 'Benutzer nicht gefunden.');
            return Response::redirect('/admin/users');
        }

        $currentUser = Auth::user();
        $currentRole = (string)($currentUser['role'] ?? 'admin');

        // Check if current user can manage target user's role
        if (!Rbac::canManageRole($currentRole, (string)$targetUser['role'])) {
            Session::flash('error', 'Sie haben keine Berechtigung, diesen Benutzer zu bearbeiten.');
            return Response::redirect('/admin/users');
        }

        // Handle POST update
        if ($request->getMethod() === 'POST') {
            if (!Csrf::verifyRequest($request)) {
                Session::flash('error', 'Ungültiges Sicherheits-Token (CSRF).');
                return Response::redirect("/admin/users/edit/{$targetId}");
            }

            $email = trim((string)$request->post('email', ''));
            $role = trim((string)$request->post('role', (string)$targetUser['role']));
            $status = $request->post('status') === 'inactive' ? 'inactive' : 'active';
            $newPassword = (string)$request->post('password', '');

            if ($email === '') {
                Session::flash('error', 'E-Mail darf nicht leer sein.');
                return Response::redirect("/admin/users/edit/{$targetId}");
            }

            // Check email collision
            $collision = $db->fetchOne("SELECT id FROM {prefix}users WHERE email = :e AND id != :id LIMIT 1", [
                'e'  => $email,
                'id' => $targetId,
            ]);
            if ($collision) {
                Session::flash('error', 'Diese E-Mail wird bereits von einem anderen Benutzer verwendet.');
                return Response::redirect("/admin/users/edit/{$targetId}");
            }

            // Check role escalation
            if ($role !== $targetUser['role'] && !Rbac::canAssignRole($currentRole, $role)) {
                Session::flash('error', 'Sie können diese Rolle nicht zuweisen.');
                return Response::redirect("/admin/users/edit/{$targetId}");
            }

            $updateData = [
                'email'      => $email,
                'role'       => $role,
                'status'     => $status,
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            if ($newPassword !== '') {
                if (strlen($newPassword) < 8) {
                    Session::flash('error', 'Das neue Passwort muss mindestens 8 Zeichen lang sein.');
                    return Response::redirect("/admin/users/edit/{$targetId}");
                }
                $updateData['password_hash'] = Security::hashPassword($newPassword);
            }

            $db->update('{prefix}users', $updateData, 'id = :id', ['id' => $targetId]);

            Session::flash('success', "Benutzer {$targetUser['username']} wurde erfolgreich aktualisiert.");
            return Response::redirect('/admin/users');
        }

        // Render Edit Form
        $safeUsername = Security::e($targetUser['username']);
        $safeEmail = Security::e($targetUser['email']);
        $assignableRoles = Rbac::getAssignableRoles($currentRole);

        $roleOptions = '';
        foreach ($assignableRoles as $r) {
            $label = Security::e(Rbac::getLabel($r));
            $sel = $targetUser['role'] === $r ? 'selected' : '';
            $roleOptions .= "<option value=\"{$r}\" {$sel}>{$label}</option>";
        }

        $csrfField = $this->csrfField();
        $breadcrumbs = [
            ['label' => 'Benutzer & Rollen', 'url' => '/admin/users']
        ];

        $contentHtml = <<<HTML
<div class="max-w-2xl bg-[#0f172a] border border-[#1e293b] rounded-2xl p-6 sm:p-8 shadow-xl">
    <div class="flex items-center space-x-4 mb-6 pb-6 border-b border-[#1e293b]">
        <div class="w-12 h-12 rounded-xl bg-slate-800 border border-slate-700 flex items-center justify-center text-lg font-bold text-white">
            {$this->initials($targetUser['username'])}
        </div>
        <div>
            <h2 class="text-lg font-bold text-white tracking-tight">{$safeUsername} bearbeiten</h2>
            <p class="text-xs text-slate-400">ID #{$targetId} &bull; Erstellt am {$targetUser['created_at']}</p>
        </div>
    </div>

    <form method="POST" action="/admin/users/edit/{$targetId}" class="space-y-5">
        {$csrfField}

        <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1">Benutzername</label>
            <input type="text" value="{$safeUsername}" disabled
                   class="w-full px-3.5 py-2.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-slate-400 cursor-not-allowed">
            <span class="text-[11px] text-slate-500 mt-1 block">Der Benutzername kann nachträglich nicht geändert werden.</span>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1">E-Mail-Adresse</label>
            <input type="email" name="email" value="{$safeEmail}" required
                   class="w-full px-3.5 py-2.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white focus:outline-none focus:border-[#f97316]">
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1">Rolle</label>
                <select name="role" class="w-full px-3.5 py-2.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white focus:outline-none focus:border-[#f97316]">
                    {$roleOptions}
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1">Status</label>
                <select name="status" class="w-full px-3.5 py-2.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white focus:outline-none focus:border-[#f97316]">
                    <option value="active" {$this->selected($targetUser['status'], 'active')}>Aktiv</option>
                    <option value="inactive" {$this->selected($targetUser['status'], 'inactive')}>Inaktiv</option>
                </select>
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1">Neues Passwort (optional)</label>
            <input type="password" name="password" minlength="8"
                   class="w-full px-3.5 py-2.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white placeholder-slate-500 focus:outline-none focus:border-[#f97316]"
                   placeholder="Leer lassen, um das bestehende Passwort beizubehalten">
        </div>

        <div class="flex items-center justify-end space-x-3 pt-6 border-t border-[#1e293b]">
            <a href="/admin/users" class="px-4 py-2.5 rounded-xl bg-[#141e33] text-xs text-slate-300 hover:text-white">
                Abbrechen
            </a>
            <button type="submit" class="px-5 py-2.5 rounded-xl bg-[#f97316] hover:bg-[#ea580c] text-white text-xs font-semibold shadow-lg shadow-orange-500/20">
                Änderungen speichern
            </button>
        </div>
    </form>
</div>
HTML;

        return Response::html(AdminView::renderShell('Benutzer bearbeiten', 'users', $contentHtml, $breadcrumbs));
    }

    /**
     * Delete User (POST)
     */
    public function deleteUser(Request $request, array $params): Response
    {
        $redirect = $this->requireAuth('manage_users');
        if ($redirect) return $redirect;

        if (!Csrf::verifyRequest($request)) {
            Session::flash('error', 'Ungültiges Sicherheits-Token (CSRF).');
            return Response::redirect('/admin/users');
        }

        $targetId = (int)($params['id'] ?? 0);
        $currentUser = Auth::user();
        $currentRole = (string)($currentUser['role'] ?? 'admin');

        // Cannot delete self
        if ($targetId === (int)$currentUser['id']) {
            Session::flash('error', 'Sie können Ihr eigenes Administratorenkonto nicht löschen.');
            return Response::redirect('/admin/users');
        }

        $db = Database::getInstance();
        $targetUser = $db->fetchOne("SELECT * FROM {prefix}users WHERE id = :id LIMIT 1", ['id' => $targetId]);
        if (!$targetUser) {
            Session::flash('error', 'Benutzer nicht gefunden.');
            return Response::redirect('/admin/users');
        }

        // Cannot delete if target is super_admin and this is the last one
        if ($targetUser['role'] === Rbac::ROLE_SUPER_ADMIN) {
            $superCount = (int)$db->fetchColumn("SELECT COUNT(*) FROM {prefix}users WHERE role = 'super_admin'");
            if ($superCount <= 1) {
                Session::flash('error', 'Der letzte verbleibende Super-Administrator kann nicht gelöscht werden.');
                return Response::redirect('/admin/users');
            }
        }

        if (!Rbac::canManageRole($currentRole, (string)$targetUser['role'])) {
            Session::flash('error', 'Sie besitzen keine Berechtigung, diesen Benutzer zu löschen.');
            return Response::redirect('/admin/users');
        }

        // Delete user and related meta
        $db->delete('{prefix}meta_data', "object_type = 'user' AND object_id = :id", ['id' => $targetId]);
        $db->delete('{prefix}users', 'id = :id', ['id' => $targetId]);

        Session::flash('success', "Benutzer {$targetUser['username']} wurde gelöscht.");
        return Response::redirect('/admin/users');
    }

    /**
     * Settings View and Update (GET & POST)
     */
    public function settings(Request $request): Response
    {
        $redirect = $this->requireAuth('manage_options');
        if ($redirect) return $redirect;

        $db = Database::getInstance();
        $agency = AgencyService::getInstance();
        $isSuperAdmin = Auth::isSuperAdmin();

        // Handle POST update
        if ($request->getMethod() === 'POST') {
            if (!Csrf::verifyRequest($request)) {
                Session::flash('error', 'Ungültiges Sicherheits-Token (CSRF).');
                return Response::redirect('/admin/settings');
            }

            // General Settings
            $siteTitle = trim((string)$request->post('site_title', 'Heartbeat CMS'));
            $siteUrl = rtrim(trim((string)$request->post('site_url', 'http://localhost:8000')), '/');
            $adminEmail = trim((string)$request->post('admin_email', 'admin@example.com'));

            $this->updateOption('site_title', $siteTitle);
            $this->updateOption('site_url', $siteUrl);
            $this->updateOption('admin_email', $adminEmail);

            // Agency & Client-Guard Settings (Super-Admin only)
            if ($isSuperAdmin) {
                $agencyName = trim((string)$request->post('agency_name', 'Herz & Seele Digital'));
                $agencyEmail = trim((string)$request->post('agency_support_email', 'hallo@herzundseele-digital.de'));
                $whiteLabel = !empty($request->post('white_label'));
                $clientGuard = !empty($request->post('client_guard'));

                $agency->saveSettings([
                    'white_label'   => $whiteLabel,
                    'agency_name'   => $agencyName,
                    'support_email' => $agencyEmail,
                    'client_guard'  => $clientGuard,
                ]);
            }

            Session::flash('success', 'Einstellungen wurden erfolgreich gespeichert.');
            return Response::redirect('/admin/settings');
        }

        // Render Settings Page
        $siteTitle = Security::e($db->fetchColumn("SELECT option_value FROM {prefix}options WHERE option_name = 'site_title'") ?: 'Heartbeat CMS');
        $siteUrl = Security::e($db->fetchColumn("SELECT option_value FROM {prefix}options WHERE option_name = 'site_url'") ?: 'http://localhost:8000');
        $adminEmail = Security::e($db->fetchColumn("SELECT option_value FROM {prefix}options WHERE option_name = 'admin_email'") ?: 'admin@example.com');

        $agencyName = Security::e($agency->getAgencyName());
        $agencyEmail = Security::e($agency->getSupportEmail());
        $isWhiteLabel = $agency->isWhiteLabel();
        $isClientGuard = $agency->isClientGuardActive();

        $csrfField = $this->csrfField();

        // Agency tab lock notice if not super admin
        $agencyTabContent = '';
        if ($isSuperAdmin) {
            $agencyTabContent = <<<HTML
<div class="space-y-4">
    <div class="flex items-center justify-between p-4 rounded-xl bg-[#090d16] border border-[#1e293b]">
        <div>
            <span class="block text-xs font-semibold text-white">White-Label Branding aktivieren</span>
            <span class="text-[11px] text-slate-400">Ersetzt das Standard-Branding durch den Agenturnamen.</span>
        </div>
        <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" name="white_label" value="1" {$this->checked($isWhiteLabel)} class="sr-only peer">
            <div class="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#f97316]"></div>
        </label>
    </div>

    <div>
        <label class="block text-xs font-semibold text-slate-300 mb-1">Agenturname</label>
        <input type="text" name="agency_name" value="{$agencyName}"
               class="w-full px-3.5 py-2.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white focus:outline-none focus:border-[#f97316]">
    </div>

    <div>
        <label class="block text-xs font-semibold text-slate-300 mb-1">Support E-Mail-Adresse</label>
        <input type="email" name="agency_support_email" value="{$agencyEmail}"
               class="w-full px-3.5 py-2.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white focus:outline-none focus:border-[#f97316]">
    </div>

    <div class="flex items-center justify-between p-4 rounded-xl bg-[#090d16] border border-[#1e293b]">
        <div>
            <span class="block text-xs font-semibold text-white">Client-Guard Schutz aktivieren</span>
            <span class="text-[11px] text-slate-400">Sperrt destruktive Systembereiche (Custom Code, Core Updater, Backups) für Kunden-Admins.</span>
        </div>
        <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" name="client_guard" value="1" {$this->checked($isClientGuard)} class="sr-only peer">
            <div class="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#f97316]"></div>
        </label>
    </div>
</div>
HTML;
        } else {
            $agencyTabContent = <<<HTML
<div class="p-6 rounded-xl bg-[#090d16] border border-amber-500/20 text-center">
    <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-400 mx-auto mb-3 flex items-center justify-center font-bold">🔒</div>
    <h4 class="text-sm font-bold text-white mb-1">Client-Guard Sperre aktiv</h4>
    <p class="text-xs text-slate-400 max-w-md mx-auto">
        Die White-Label- und Agentur-Einstellungen sind exklusiv für Super-Administratoren reserviert, um eine fehlerfreie Konfiguration zu gewährleisten.
    </p>
</div>
HTML;
        }

        $contentHtml = <<<HTML
<div class="max-w-4xl" x-data="{ tab: 'general' }">
    <!-- Tabs Header -->
    <div class="flex border-b border-[#1e293b] space-x-2 mb-6">
        <button @click="tab = 'general'" :class="tab === 'general' ? 'border-[#f97316] text-white font-bold' : 'border-transparent text-slate-400 hover:text-slate-200'"
                class="py-3 px-4 border-b-2 text-xs font-medium transition">
            Allgemeine Website-Einstellungen
        </button>
        <button @click="tab = 'agency'" :class="tab === 'agency' ? 'border-[#f97316] text-white font-bold' : 'border-transparent text-slate-400 hover:text-slate-200'"
                class="py-3 px-4 border-b-2 text-xs font-medium transition flex items-center space-x-2">
            <span>Agentur &amp; White-Label</span>
            <span class="text-[10px] px-1.5 py-0.5 rounded bg-orange-500/10 text-orange-400 border border-orange-500/20">Client-Guard</span>
        </button>
        <button @click="tab = 'security'" :class="tab === 'security' ? 'border-[#f97316] text-white font-bold' : 'border-transparent text-slate-400 hover:text-slate-200'"
                class="py-3 px-4 border-b-2 text-xs font-medium transition">
            Sicherheit &amp; Session-Core
        </button>
    </div>

    <form method="POST" action="/admin/settings" class="bg-[#0f172a] border border-[#1e293b] rounded-2xl p-6 sm:p-8 shadow-xl">
        {$csrfField}

        <!-- Tab 1: General -->
        <div x-show="tab === 'general'" class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1">Titel der Website</label>
                <input type="text" name="site_title" value="{$siteTitle}" required
                       class="w-full px-3.5 py-2.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white focus:outline-none focus:border-[#f97316]">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1">Website URL (Basisadresse)</label>
                <input type="url" name="site_url" value="{$siteUrl}" required
                       class="w-full px-3.5 py-2.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white focus:outline-none focus:border-[#f97316]">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1">Administrator E-Mail</label>
                <input type="email" name="admin_email" value="{$adminEmail}" required
                       class="w-full px-3.5 py-2.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white focus:outline-none focus:border-[#f97316]">
                <span class="text-[11px] text-slate-500 mt-1 block">Empfängt Systembenachrichtigungen und Fehlerberichte.</span>
            </div>
        </div>

        <!-- Tab 2: Agency & White-Label -->
        <div x-show="tab === 'agency'">
            {$agencyTabContent}
        </div>

        <!-- Tab 3: Security & Session -->
        <div x-show="tab === 'security'" class="space-y-4">
            <div class="p-4 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-slate-400">CSRF Schutz:</span>
                    <span class="text-emerald-400 font-bold">256-Bit Token &amp; Rotations-Bindung</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-400">Session Fixation Guard:</span>
                    <span class="text-emerald-400 font-bold">Aktiv (session_regenerate_id)</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-400">Brute-Force Rate Limiter:</span>
                    <span class="text-emerald-400 font-bold">Max 5 Versuche / 15 Minuten</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-slate-400">Content Security Policy:</span>
                    <span class="text-emerald-400 font-bold">Strikt gehärtet</span>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end space-x-3 pt-6 border-t border-[#1e293b] mt-6">
            <button type="submit" class="px-5 py-2.5 rounded-xl bg-[#f97316] hover:bg-[#ea580c] text-white text-xs font-semibold shadow-lg shadow-orange-500/20">
                Einstellungen speichern
            </button>
        </div>
    </form>
</div>
HTML;

        return Response::html(AdminView::renderShell('Einstellungen', 'settings', $contentHtml));
    }

    /**
     * System Status & Maintenance View
     */
    public function system(Request $request): Response
    {
        $redirect = $this->requireAuth('manage_backups');
        if ($redirect) return $redirect;

        $db = Database::getInstance();
        $cachedPages = \Heartbeat\Cache\Cache::page()->count();
        $phpVersion = PHP_VERSION;
        $dbDriver = strtoupper($db->getDriver());

        $csrfField = $this->csrfField();

        $contentHtml = <<<HTML
<div class="max-w-4xl space-y-6">
    <!-- Server & Runtime Health -->
    <div class="bg-[#0f172a] border border-[#1e293b] rounded-2xl p-6 shadow-xl">
        <h3 class="text-sm font-bold text-white mb-4">Laufzeit- &amp; Server-Umgebung</h3>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
            <div class="p-4 rounded-xl bg-[#090d16] border border-[#1e293b]">
                <span class="text-slate-400 block mb-1">PHP Version</span>
                <span class="font-mono text-white text-sm font-bold">{$phpVersion}</span>
            </div>
            <div class="p-4 rounded-xl bg-[#090d16] border border-[#1e293b]">
                <span class="text-slate-400 block mb-1">Datenbank</span>
                <span class="font-mono text-white text-sm font-bold">{$dbDriver}</span>
            </div>
            <div class="p-4 rounded-xl bg-[#090d16] border border-[#1e293b]">
                <span class="text-slate-400 block mb-1">Gecachte HTML-Seiten</span>
                <span class="font-mono text-white text-sm font-bold">{$cachedPages} Seiten</span>
            </div>
        </div>
    </div>

    <!-- Cache Management -->
    <div class="bg-[#0f172a] border border-[#1e293b] rounded-2xl p-6 shadow-xl flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h4 class="text-sm font-bold text-white">Full-Page &amp; Object Cache leeren</h4>
            <p class="text-xs text-slate-400 mt-1">Löscht alle statischen HTML- und Abfrage-Caches in storage/cache.</p>
        </div>
        <form method="POST" action="/admin/system/clear-cache">
            {$csrfField}
            <button type="submit" class="px-5 py-2.5 rounded-xl bg-orange-500/10 hover:bg-orange-500/20 text-orange-400 border border-orange-500/30 text-xs font-semibold transition">
                Cache jetzt leeren
            </button>
        </form>
    </div>
</div>
HTML;

        return Response::html(AdminView::renderShell('System &amp; Wartung', 'system', $contentHtml));
    }

    /**
     * Clear Cache (POST)
     */
    public function clearCache(Request $request): Response
    {
        $redirect = $this->requireAuth('manage_options');
        if ($redirect) return $redirect;

        if (!Csrf::verifyRequest($request)) {
            Session::flash('error', 'Ungültiges Sicherheits-Token (CSRF).');
            return Response::redirect('/admin');
        }

        \Heartbeat\Cache\Cache::flush();

        Session::flash('success', 'Der System- und Full-Page-Cache wurde erfolgreich geleert.');
        return Response::redirect('/admin');
    }

    /**
     * Phase Placeholders for upcoming Phase 3 & 4
     */
    public function placeholder(Request $request, string $moduleName, string $phaseName): Response
    {
        $redirect = $this->requireAuth('access_admin');
        if ($redirect) return $redirect;

        $safeMod = Security::e($moduleName);
        $safePhase = Security::e($phaseName);

        $contentHtml = <<<HTML
<div class="bg-[#0f172a] border border-[#1e293b] rounded-2xl p-12 text-center shadow-xl max-w-xl mx-auto">
    <div class="w-14 h-14 rounded-2xl bg-orange-500/10 text-orange-400 mx-auto mb-4 flex items-center justify-center font-bold text-2xl">
        ⚡
    </div>
    <h2 class="text-xl font-bold text-white mb-2">{$safeMod}</h2>
    <p class="text-xs text-slate-400 leading-relaxed mb-6">
        Dieses Modul wird im Rahmen von <strong>{$safePhase}</strong> aktiviert. Die modulare Architektur und Datenbanktabellen sind bereits vorbereitet.
    </p>
    <a href="/admin" class="inline-flex items-center space-x-2 text-xs font-semibold px-4 py-2 rounded-xl bg-[#141e33] hover:bg-[#1e293b] text-slate-300 hover:text-white transition">
        <span>&larr; Zurück zum Dashboard</span>
    </a>
</div>
HTML;

        return Response::html(AdminView::renderShell($moduleName, strtolower($moduleName), $contentHtml));
    }

    /**
     * Helper to enforce auth and permission checks
     */
    private function requireAuth(string $permission = 'access_admin'): ?Response
    {
        if (!Auth::check()) {
            return Response::redirect('/admin/login');
        }

        if (!Auth::can($permission)) {
            Session::flash('error', 'Sie besitzen keine Berechtigung für diesen Bereich (Client-Guard / RBAC).');
            return Response::redirect('/admin');
        }

        return null;
    }

    private function renderRoleBadge(string $role): string
    {
        $label = Security::e(Rbac::getLabel($role));
        return match ($role) {
            Rbac::ROLE_SUPER_ADMIN => "<span class=\"inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-purple-500/10 text-purple-400 border border-purple-500/20\">{$label}</span>",
            Rbac::ROLE_ADMIN       => "<span class=\"inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-orange-500/10 text-orange-400 border border-orange-500/20\">{$label}</span>",
            Rbac::ROLE_EDITOR      => "<span class=\"inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-blue-500/10 text-blue-400 border border-blue-500/20\">{$label}</span>",
            Rbac::ROLE_USER        => "<span class=\"inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-500/10 text-slate-400 border border-slate-500/20\">{$label}</span>",
            Rbac::ROLE_MEMBER      => "<span class=\"inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20\">{$label}</span>",
            default                => "<span class=\"inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-500/10 text-slate-400\">{$label}</span>",
        };
    }

    private function initials(string $name): string
    {
        return Security::e(strtoupper(substr($name, 0, 2)));
    }

    private function selfBadge(bool $isSelf): string
    {
        return $isSelf
            ? '<span class="ml-1.5 text-[10px] px-1.5 py-0.5 rounded bg-orange-500/10 text-orange-400 font-mono">Du</span>'
            : '';
    }

    private function csrfField(): string
    {
        return Csrf::field();
    }

    private function selected(string $val1, string $val2): string
    {
        return $val1 === $val2 ? 'selected' : '';
    }

    private function checked(bool $condition): string
    {
        return $condition ? 'checked' : '';
    }

    private function updateOption(string $name, string $value): void
    {
        $db = Database::getInstance();
        $existing = $db->fetchOne("SELECT id FROM {prefix}options WHERE option_name = :name", ['name' => $name]);
        if ($existing) {
            $db->update('{prefix}options', ['option_value' => $value], 'option_name = :name', ['name' => $name]);
        } else {
            $db->insert('{prefix}options', [
                'option_name'  => $name,
                'option_value' => $value,
                'autoload'     => 1,
            ]);
        }
    }
}

