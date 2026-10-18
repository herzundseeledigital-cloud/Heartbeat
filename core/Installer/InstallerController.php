<?php
declare(strict_types=1);

namespace Heartbeat\Installer;

use Heartbeat\Http\Request;
use Heartbeat\Http\Response;
use Heartbeat\Security\Csrf;
use Heartbeat\Security\Security;
use Throwable;

/**
 * Web Installer Controller for Heartbeat CMS
 */
final class InstallerController
{
    /**
     * Entry point: Render installer wizard
     */
    public function index(Request $request): Response
    {
        if (InstallerService::isLocked()) {
            return Response::html(
                $this->renderLockedView(),
                200
            );
        }

        $systemChecks = SystemChecker::check();
        $csrfToken = Csrf::token();
        $autoUrl = $request->baseUrl();

        $html = $this->renderWizard($systemChecks, $csrfToken, $autoUrl);
        return Response::html($html);
    }

    /**
     * AJAX endpoint to test database credentials live
     */
    public function testDatabase(Request $request): Response
    {
        if (InstallerService::isLocked()) {
            return Response::json(['success' => false, 'message' => 'Installer ist gesperrt.'], 403);
        }

        $driver = (string)$request->input('driver', 'mysql');
        $host = (string)$request->input('host', '127.0.0.1');
        $port = (int)$request->input('port', 3306);
        $database = (string)$request->input('database', '');
        $username = (string)$request->input('username', 'root');
        $password = (string)$request->input('password', '');
        $charset = (string)$request->input('charset', 'utf8mb4');

        $result = InstallerService::testDatabase([
            'driver'   => $driver,
            'host'     => $host,
            'port'     => $port,
            'database' => $database,
            'username' => $username,
            'password' => $password,
            'charset'  => $charset,
        ]);

        return Response::json($result);
    }

    /**
     * AJAX endpoint to execute installation: Run migrations, seed starter content, lock installer
     */
    public function runInstall(Request $request): Response
    {
        if (InstallerService::isLocked()) {
            return Response::json(['success' => false, 'message' => 'Installer ist gesperrt.'], 403);
        }

        if (!Csrf::verifyRequest($request)) {
            return Response::json(['success' => false, 'message' => 'Ungültiges CSRF-Sicherheitstoken. Bitte Seite neu laden.'], 419);
        }

        try {
            $payload = $request->input();
            $result = InstallerService::runInstallation($payload);
            return Response::json($result);
        } catch (Throwable $e) {
            return Response::json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    private function renderLockedView(): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="de" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Heartbeat CMS - Bereits installiert</title>
    <link rel="stylesheet" href="/assets/css/installer.css">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
</head>
<body class="bg-[#090d16] text-slate-100 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-[#0f172a] border border-[#1e293b] rounded-2xl p-8 text-center shadow-2xl">
        <div class="w-16 h-16 mx-auto mb-4 bg-emerald-500/10 text-emerald-400 rounded-2xl flex items-center justify-center border border-emerald-500/20">
            <span class="material-symbols-outlined text-3xl">check_circle</span>
        </div>
        <h1 class="text-2xl font-bold text-white mb-2 tracking-tight">Heartbeat CMS ist aktiv</h1>
        <p class="text-slate-400 text-xs mb-6">Das System ist bereits installiert und gesichert (<code>config/installed.lock</code> aktiv).</p>
        <div class="space-y-3">
            <a href="/admin/login" class="btn-primary w-full justify-center">Zum Admin-Login</a>
            <a href="/" class="btn-secondary w-full justify-center">Zur Startseite</a>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function renderWizard(array $checks, string $csrfToken, string $autoUrl): string
    {
        $allPassed = $checks['allPassed'];
        $safeUrl = Security::e($autoUrl);
        $phpVersion = Security::e($checks['phpVersion']['current'] ?? PHP_VERSION);

        // System check status HTML
        if ($allPassed) {
            $systemHtml = <<<HTML
            <div class="p-3 bg-emerald-500/10 border border-emerald-500/20 rounded-xl flex items-center justify-between text-xs text-emerald-400 mb-6">
                <span class="flex items-center space-x-2">
                    <span class="material-symbols-outlined text-[18px]">check_circle</span>
                    <span class="font-medium">System bereit &bull; PHP {$phpVersion} &bull; Alle Module aktiv</span>
                </span>
                <button type="button" onclick="toggleDetails()" class="text-slate-400 hover:text-white underline text-[11px] ml-2">Details</button>
            </div>
HTML;
        } else {
            $systemHtml = <<<HTML
            <div class="p-4 bg-rose-500/10 border border-rose-500/20 rounded-xl text-xs text-rose-300 mb-6">
                <div class="flex items-center space-x-2 font-bold mb-1">
                    <span class="material-symbols-outlined text-[18px]">warning</span>
                    <span>Server-Anforderungen nicht vollständig erfüllt</span>
                </div>
                <p>PHP 8.3+ und Erweiterungen (PDO, mbstring, etc.) erforderlich.</p>
                <button type="button" onclick="toggleDetails()" class="text-white underline font-semibold mt-2 inline-block">Details einblenden</button>
            </div>
HTML;
        }

        // Detailed checks list (initially hidden)
        $checksDetails = '';
        foreach ($checks['extensions'] ?? [] as $ext) {
            $passBadge = $ext['pass']
                ? '<span class="text-emerald-400 font-semibold">Aktiv</span>'
                : '<span class="text-rose-400 font-semibold">Fehlt</span>';
            $checksDetails .= "<div class=\"flex justify-between py-1 text-slate-300\"><span>{$ext['name']}</span>{$passBadge}</div>";
        }
        foreach ($checks['directories'] ?? [] as $key => $dir) {
            $dirName = str_replace('_', '/', $key);
            $passBadge = $dir['pass']
                ? '<span class="text-emerald-400 font-semibold">Beschreibbar</span>'
                : '<span class="text-rose-400 font-semibold">Schreibgeschützt</span>';
            $checksDetails .= "<div class=\"flex justify-between py-1 text-slate-300\"><span>{$dirName}</span>{$passBadge}</div>";
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="de" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Heartbeat CMS - Installation</title>
    <meta name="csrf-token" content="{$csrfToken}">
    <link rel="stylesheet" href="/assets/css/installer.css">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <style>
        .step-panel { display: none; }
        .step-panel.active { display: block; }
    </style>
</head>
<body class="bg-[#090d16] text-slate-100 font-sans antialiased min-h-screen flex flex-col justify-between selection:bg-[#fb326f] selection:text-white">

    <!-- Header & Ambient Glow -->
    <header class="relative pt-8 pb-4 text-center">
        <div class="relative z-10 flex items-center justify-center space-x-3 mb-1">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-[#fb326f] to-[#e01e5a] flex items-center justify-center shadow-lg shadow-[#fb326f]/25 text-white font-bold text-sm">
                HB
            </div>
            <span class="text-2xl font-black tracking-tight text-white">HEART<span class="text-[#fb326f]">BEAT</span> <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-[#fb326f]/10 text-[#fb326f] border border-[#fb326f]/20">CMS</span></span>
        </div>
        <p class="text-xs text-slate-400 font-medium">Installation &amp; Einrichtung</p>
    </header>

    <!-- Main Wizard Card (Apple / Vercel max-w-xl Grid) -->
    <main class="flex-grow flex items-center justify-center p-4">
        <div class="w-full max-w-xl bg-[#0f172a] border border-[#1e293b] rounded-2xl shadow-2xl overflow-hidden">

            <!-- Horizontal Stepper Progress Bar -->
            <div class="bg-[#090d16]/70 border-b border-[#1e293b] px-6 py-4">
                <div class="flex items-center justify-between">
                    <!-- Step 1 -->
                    <div class="flex items-center space-x-2 text-white">
                        <span id="step-dot-1" class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold bg-[#fb326f] text-white ring-4 ring-[#fb326f]/20 shadow-lg shadow-[#fb326f]/25 transition-all">1</span>
                        <span class="text-xs font-semibold hidden sm:inline">Umgebung</span>
                    </div>

                    <div id="step-line-1" class="flex-1 h-0.5 mx-3 bg-[#1e293b] transition-all"></div>

                    <!-- Step 2 -->
                    <div class="flex items-center space-x-2 text-slate-400">
                        <span id="step-dot-2" class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold bg-[#1e293b] text-slate-400 transition-all">2</span>
                        <span class="text-xs font-medium hidden sm:inline">Konfiguration</span>
                    </div>

                    <div id="step-line-2" class="flex-1 h-0.5 mx-3 bg-[#1e293b] transition-all"></div>

                    <!-- Step 3 -->
                    <div class="flex items-center space-x-2 text-slate-400">
                        <span id="step-dot-3" class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold bg-[#1e293b] text-slate-400 transition-all">3</span>
                        <span class="text-xs font-medium hidden sm:inline">Abschluss</span>
                    </div>
                </div>
            </div>

            <!-- Content Area -->
            <div class="p-6 sm:p-8">

                <!-- ========================================================= -->
                <!-- SCHRITT 1: Umgebungsauswahl (Lokal vs. Webserver)         -->
                <!-- ========================================================= -->
                <div id="step-1" class="step-panel active">
                    <div class="mb-6">
                        <h2 class="text-xl font-bold text-white tracking-tight">Umgebung wählen</h2>
                        <p class="text-xs text-slate-400 mt-1">Wo wird Heartbeat CMS betrieben?</p>
                    </div>

                    {$systemHtml}

                    <!-- Expandable Details List -->
                    <div id="system-details-box" style="display: none;" class="mb-6 p-4 bg-[#090d16] border border-[#1e293b] rounded-xl text-xs space-y-1">
                        <div class="font-bold text-white mb-2">Erweiterte Systemprüfung:</div>
                        {$checksDetails}
                    </div>

                    <!-- Environment Selection Cards -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
                        <!-- Option A: Lokale Entwicklung (SQLite) -->
                        <div id="env-card-local" onclick="selectEnv('local')"
                             class="p-5 rounded-2xl border-2 border-[#fb326f] bg-[#fb326f]/5 cursor-pointer transition flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between mb-3">
                                    <span class="material-symbols-outlined text-2xl text-[#fb326f]">laptop_mac</span>
                                    <span class="px-2 py-0.5 text-[10px] font-bold bg-[#fb326f]/20 text-[#fb326f] rounded-full uppercase tracking-wider">Lokal</span>
                                </div>
                                <h3 class="text-sm font-bold text-white mb-1">Lokale Entwicklung</h3>
                                <p class="text-xs text-slate-400 leading-relaxed mb-3">Integrierte SQLite-Datenbank.</p>
                                <div class="text-[11px] text-slate-300 space-y-1.5">
                                    <div class="flex items-center space-x-1.5"><span class="material-symbols-outlined text-emerald-400 text-[16px]">check_circle</span><span>SQLite-Datenbank</span></div>
                                    <div class="flex items-center space-x-1.5"><span class="material-symbols-outlined text-emerald-400 text-[16px]">check_circle</span><span>Keine DB-Zugangsdaten nötig</span></div>
                                    <div class="flex items-center space-x-1.5"><span class="material-symbols-outlined text-emerald-400 text-[16px]">check_circle</span><span>Startklar in 5 Sekunden</span></div>
                                </div>
                            </div>
                            <div class="mt-4 pt-3 border-t border-[#1e293b] flex items-center justify-between">
                                <span class="text-xs font-semibold text-white">Auswählen</span>
                                <input type="radio" id="radio-local" name="env_type" value="local" checked class="text-[#fb326f] focus:ring-0">
                            </div>
                        </div>

                        <!-- Option B: Webserver / Produktion (MySQL) -->
                        <div id="env-card-server" onclick="selectEnv('server')"
                             class="p-5 rounded-2xl border-2 border-[#1e293b] bg-[#090d16] cursor-pointer transition hover:border-slate-600 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between mb-3">
                                    <span class="material-symbols-outlined text-2xl text-slate-400">dns</span>
                                    <span class="px-2 py-0.5 text-[10px] font-semibold bg-slate-800 text-slate-400 rounded-full uppercase tracking-wider">Webserver</span>
                                </div>
                                <h3 class="text-sm font-bold text-white mb-1">Webserver / MySQL</h3>
                                <p class="text-xs text-slate-400 leading-relaxed mb-3">Für Hosting mit MySQL/MariaDB.</p>
                                <div class="text-[11px] text-slate-300 space-y-1.5">
                                    <div class="flex items-center space-x-1.5"><span class="material-symbols-outlined text-emerald-400 text-[16px]">check_circle</span><span>MySQL 8.0+ / MariaDB</span></div>
                                    <div class="flex items-center space-x-1.5"><span class="material-symbols-outlined text-emerald-400 text-[16px]">check_circle</span><span>Standard-Hosting</span></div>
                                    <div class="flex items-center space-x-1.5"><span class="material-symbols-outlined text-emerald-400 text-[16px]">check_circle</span><span>Live-Betrieb</span></div>
                                </div>
                            </div>
                            <div class="mt-4 pt-3 border-t border-[#1e293b] flex items-center justify-between">
                                <span class="text-xs font-medium text-slate-400">Auswählen</span>
                                <input type="radio" id="radio-server" name="env_type" value="server" class="text-[#fb326f] focus:ring-0">
                            </div>
                        </div>
                    </div>

                    <!-- Step 1 Controls -->
                    <div class="flex justify-end pt-4 border-t border-[#1e293b]">
                        <button type="button" onclick="goToStep(2)" class="btn-primary">
                            <span>Weiter</span>
                            <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                        </button>
                    </div>
                </div>

                <!-- ========================================================= -->
                <!-- SCHRITT 2: Konfiguration, Datenbank & Administrator       -->
                <!-- ========================================================= -->
                <div id="step-2" class="step-panel">
                    <div class="mb-5">
                        <h2 class="text-xl font-bold text-white tracking-tight">Konfiguration</h2>
                        <p class="text-xs text-slate-400 mt-1">Website-Details und Administrator-Zugang einrichten.</p>
                    </div>

                    <!-- DYNAMIC DATABASE CONTAINER -->
                    <!-- SQLite Info Box (visible when local selected) -->
                    <div id="box-sqlite" class="mb-6 p-4 bg-[#090d16] border border-[#1e293b] rounded-xl flex items-center space-x-3.5">
                        <div class="w-10 h-10 rounded-xl bg-[#fb326f]/10 text-[#fb326f] flex items-center justify-center flex-shrink-0">
                            <span class="material-symbols-outlined text-2xl">database</span>
                        </div>
                        <div>
                            <div class="text-xs font-bold text-white">SQLite aktiv</div>
                            <div class="text-xs text-slate-400 mt-0.5">
                                Datenbank wird in <code class="text-slate-200 font-mono">storage/database.sqlite</code> erstellt. Keine Passwörter nötig.
                            </div>
                        </div>
                    </div>

                    <!-- MySQL Fields Box (visible when webserver selected) -->
                    <div id="box-mysql" style="display: none;" class="mb-6 p-4 bg-[#090d16] border border-[#1e293b] rounded-xl space-y-3">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-bold text-white uppercase tracking-wider">MySQL Verbindungsdaten</span>
                            <span id="db-test-badge" class="text-xs text-slate-400"></span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div class="sm:col-span-2">
                                <label class="block text-xs font-medium text-slate-300 mb-1">Host</label>
                                <input type="text" id="mysql_host" value="127.0.0.1" class="w-full h-11 px-3.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white focus:outline-none focus:border-[#fb326f]">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-300 mb-1">Port</label>
                                <input type="number" id="mysql_port" value="3306" class="w-full h-11 px-3.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white focus:outline-none focus:border-[#fb326f]">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-300 mb-1">Datenbank</label>
                                <input type="text" id="mysql_name" placeholder="heartbeat" class="w-full h-11 px-3.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white focus:outline-none focus:border-[#fb326f]">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-300 mb-1">Benutzer</label>
                                <input type="text" id="mysql_user" value="root" class="w-full h-11 px-3.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white focus:outline-none focus:border-[#fb326f]">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-300 mb-1">Passwort</label>
                                <input type="password" id="mysql_pass" placeholder="Passwort" class="w-full h-11 px-3.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white focus:outline-none focus:border-[#fb326f]">
                            </div>
                        </div>
                        <div class="pt-2 flex justify-end">
                            <button type="button" onclick="testDb()" class="btn-secondary text-xs">
                                <span>Verbindung testen</span>
                            </button>
                        </div>
                    </div>

                    <!-- Website Settings -->
                    <div class="mb-6 p-4 bg-[#090d16] border border-[#1e293b] rounded-xl space-y-3">
                        <span class="block text-xs font-bold text-white uppercase tracking-wider">Website</span>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-300 mb-1">Website-Titel</label>
                                <input type="text" id="site_title" value="Meine Heartbeat Website" class="w-full h-11 px-3.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white focus:outline-none focus:border-[#fb326f]">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-300 mb-1">Website-URL</label>
                                <input type="text" id="site_url" value="{$safeUrl}" class="w-full h-11 px-3.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white focus:outline-none focus:border-[#fb326f]">
                            </div>
                        </div>
                    </div>

                    <!-- Super Admin Account -->
                    <div class="mb-6 p-4 bg-[#090d16] border border-[#1e293b] rounded-xl space-y-3">
                        <span class="block text-xs font-bold text-white uppercase tracking-wider">Administrator</span>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-300 mb-1">Benutzername</label>
                                <input type="text" id="admin_user" value="admin" class="w-full h-11 px-3.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white focus:outline-none focus:border-[#fb326f]">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-300 mb-1">E-Mail</label>
                                <input type="email" id="admin_email" value="admin@example.com" class="w-full h-11 px-3.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white focus:outline-none focus:border-[#fb326f]">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-300 mb-1">Passwort</label>
                                <input type="password" id="admin_pass" value="Admin123!" class="w-full h-11 px-3.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white focus:outline-none focus:border-[#fb326f]">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-300 mb-1">Passwort bestätigen</label>
                                <input type="password" id="admin_pass_confirm" value="Admin123!" class="w-full h-11 px-3.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white focus:outline-none focus:border-[#fb326f]">
                            </div>
                        </div>
                    </div>

                    <!-- All Themes Notice -->
                    <div class="mb-6 p-4 bg-[#141e33]/50 border border-[#1e293b] rounded-xl flex items-start space-x-3">
                        <span class="material-symbols-outlined text-[#fb326f] text-xl mt-0.5">palette</span>
                        <div class="text-xs text-slate-300 leading-relaxed">
                            <span class="font-bold text-white block mb-0.5">4 Themes vorinstalliert</span>
                            <span>One-Pager, Corporate, Real Estate und Startup werden eingerichtet. Im CMS unter <em>Design &amp; Themes</em> mit 1 Klick und Live-Vorschau wechselbar.</span>
                        </div>
                    </div>

                    <!-- Error Banner -->
                    <div id="step-2-error" style="display: none;" class="mb-6 p-3.5 bg-rose-500/10 border border-rose-500/20 rounded-xl text-rose-300 text-xs"></div>

                    <!-- Step 2 Controls -->
                    <div class="flex justify-between pt-4 border-t border-[#1e293b]">
                        <button type="button" onclick="goToStep(1)" class="btn-secondary">
                            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                            <span>Zurück</span>
                        </button>
                        <button type="button" id="btn-run-install" onclick="submitInstall()" class="btn-primary">
                            <span>CMS jetzt installieren</span>
                            <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                        </button>
                    </div>
                </div>

                <!-- ========================================================= -->
                <!-- SCHRITT 3: Fertig!                                       -->
                <!-- ========================================================= -->
                <div id="step-3" class="step-panel text-center py-4">
                    <div class="w-16 h-16 mx-auto mb-4 bg-[#fb326f]/10 border border-[#fb326f]/20 text-[#fb326f] rounded-2xl flex items-center justify-center shadow-lg shadow-[#fb326f]/20">
                        <span class="material-symbols-outlined text-4xl">check_circle</span>
                    </div>

                    <h2 class="text-2xl font-bold text-white mb-2 tracking-tight">Installation abgeschlossen</h2>
                    <p class="text-slate-400 text-xs max-w-md mx-auto mb-6 leading-relaxed">
                        Heartbeat CMS ist einsatzbereit.
                    </p>

                    <!-- Summary Stats Card -->
                    <div class="bg-[#090d16] border border-[#1e293b] rounded-xl p-4 max-w-md mx-auto mb-8 text-left space-y-2 text-xs">
                        <div class="flex justify-between text-slate-400">
                            <span>Admin-Benutzer:</span>
                            <span id="summary-user" class="text-white font-mono font-bold">admin</span>
                        </div>
                        <div class="flex justify-between text-slate-400">
                            <span>Datenbank:</span>
                            <span id="summary-db" class="text-emerald-400 font-mono">SQLite</span>
                        </div>
                        <div class="flex justify-between text-slate-400">
                            <span>Themes:</span>
                            <span class="text-[#fb326f] font-semibold">4 Themes einsatzbereit</span>
                        </div>
                        <div class="flex justify-between text-slate-400">
                            <span>Sicherheits-Lock:</span>
                            <span class="text-emerald-400 font-mono">config/installed.lock aktiv</span>
                        </div>
                    </div>

                    <!-- Direct Action Buttons -->
                    <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
                        <a href="/admin/login" class="btn-primary w-full sm:w-auto">
                            <span>Admin Dashboard öffnen</span>
                            <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                        </a>
                        <a href="/" class="btn-secondary w-full sm:w-auto">
                            <span>Website ansehen</span>
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="py-4 text-center text-xs text-slate-500">
        Heartbeat CMS &bull; PHP 8.3 &bull; Designed for Creators &amp; Agencies
    </footer>

    <!-- Zero-Dependency Vanilla JS Installer Script -->
    <script>
    const csrfToken = "{$csrfToken}";
    let currentEnv = 'local';

    function toggleDetails() {
        const box = document.getElementById('system-details-box');
        if (box) {
            box.style.display = box.style.display === 'none' ? 'block' : 'none';
        }
    }

    function selectEnv(type) {
        currentEnv = type;
        const cardLocal = document.getElementById('env-card-local');
        const cardServer = document.getElementById('env-card-server');
        const radioLocal = document.getElementById('radio-local');
        const radioServer = document.getElementById('radio-server');
        const boxSqlite = document.getElementById('box-sqlite');
        const boxMysql = document.getElementById('box-mysql');

        if (type === 'local') {
            radioLocal.checked = true;
            radioServer.checked = false;
            cardLocal.className = 'p-5 rounded-2xl border-2 border-[#fb326f] bg-[#fb326f]/5 cursor-pointer transition flex flex-col justify-between';
            cardServer.className = 'p-5 rounded-2xl border-2 border-[#1e293b] bg-[#090d16] cursor-pointer transition hover:border-slate-600 flex flex-col justify-between';
            if (boxSqlite) boxSqlite.style.display = 'flex';
            if (boxMysql) boxMysql.style.display = 'none';
        } else {
            radioLocal.checked = false;
            radioServer.checked = true;
            cardServer.className = 'p-5 rounded-2xl border-2 border-[#fb326f] bg-[#fb326f]/5 cursor-pointer transition flex flex-col justify-between';
            cardLocal.className = 'p-5 rounded-2xl border-2 border-[#1e293b] bg-[#090d16] cursor-pointer transition hover:border-slate-600 flex flex-col justify-between';
            if (boxSqlite) boxSqlite.style.display = 'none';
            if (boxMysql) boxMysql.style.display = 'block';
        }
    }

    function goToStep(step) {
        // Hide all step panels
        document.querySelectorAll('.step-panel').forEach(function(el) {
            el.style.display = 'none';
            el.classList.remove('active');
        });

        // Show target step
        const target = document.getElementById('step-' + step);
        if (target) {
            target.style.display = 'block';
            target.classList.add('active');
        }

        // Update horizontal stepper indicators
        for (let i = 1; i <= 3; i++) {
            const dot = document.getElementById('step-dot-' + i);
            const line = document.getElementById('step-line-' + i);

            if (i < step) {
                if (dot) {
                    dot.className = 'w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold bg-emerald-500 text-white shadow transition-all';
                    dot.innerHTML = '<span class="material-symbols-outlined text-[16px]">check</span>';
                }
                if (line) line.className = 'flex-1 h-0.5 mx-3 bg-[#fb326f] transition-all';
            } else if (i === step) {
                if (dot) {
                    dot.className = 'w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold bg-[#fb326f] text-white ring-4 ring-[#fb326f]/20 shadow-lg shadow-[#fb326f]/25 transition-all';
                    dot.textContent = '' + i;
                }
                if (line) line.className = 'flex-1 h-0.5 mx-3 bg-[#1e293b] transition-all';
            } else {
                if (dot) {
                    dot.className = 'w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold bg-[#1e293b] text-slate-400 transition-all';
                    dot.textContent = '' + i;
                }
                if (line) line.className = 'flex-1 h-0.5 mx-3 bg-[#1e293b] transition-all';
            }
        }

        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    async function testDb() {
        const badge = document.getElementById('db-test-badge');
        badge.className = 'text-xs text-slate-400';
        badge.textContent = 'Prüfe Verbindung...';

        const payload = {
            driver: 'mysql',
            host: document.getElementById('mysql_host').value.trim() || '127.0.0.1',
            port: parseInt(document.getElementById('mysql_port').value.trim(), 10) || 3306,
            database: document.getElementById('mysql_name').value.trim(),
            username: document.getElementById('mysql_user').value.trim(),
            password: document.getElementById('mysql_pass').value
        };

        try {
            const res = await fetch('/install/api/test-db', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (data.success) {
                badge.className = 'text-xs text-emerald-400 font-semibold';
                badge.textContent = '✓ Verbindung erfolgreich!';
            } else {
                badge.className = 'text-xs text-rose-400 font-semibold';
                badge.textContent = '✗ ' + (data.message || 'Verbindung fehlgeschlagen');
            }
        } catch (e) {
            badge.className = 'text-xs text-rose-400 font-semibold';
            badge.textContent = '✗ Netzwerkfehler: ' + e.message;
        }
    }

    async function submitInstall() {
        const errBox = document.getElementById('step-2-error');
        errBox.style.display = 'none';

        const adminUser = document.getElementById('admin_user').value.trim();
        const adminEmail = document.getElementById('admin_email').value.trim();
        const adminPass = document.getElementById('admin_pass').value;
        const adminPassConfirm = document.getElementById('admin_pass_confirm').value;

        if (!adminUser || !adminEmail || !adminPass) {
            errBox.textContent = 'Bitte alle Administrator-Felder ausfüllen.';
            errBox.style.display = 'block';
            return;
        }

        if (adminPass !== adminPassConfirm) {
            errBox.textContent = 'Die Passwörter stimmen nicht überein.';
            errBox.style.display = 'block';
            return;
        }

        const btn = document.getElementById('btn-run-install');
        btn.disabled = true;
        btn.innerHTML = '<span class="inline-block animate-pulse mr-2">⚡</span> Installiere...';

        const payload = {
            db_driver: currentEnv === 'local' ? 'sqlite' : 'mysql',
            db_host: document.getElementById('mysql_host') ? document.getElementById('mysql_host').value.trim() : '127.0.0.1',
            db_port: document.getElementById('mysql_port') ? parseInt(document.getElementById('mysql_port').value.trim(), 10) : 3306,
            db_name: document.getElementById('mysql_name') ? document.getElementById('mysql_name').value.trim() : 'heartbeat',
            db_user: document.getElementById('mysql_user') ? document.getElementById('mysql_user').value.trim() : 'root',
            db_pass: document.getElementById('mysql_pass') ? document.getElementById('mysql_pass').value : '',
            db_prefix: 'hb_',
            site_title: document.getElementById('site_title').value.trim() || 'Heartbeat Starter',
            site_url: document.getElementById('site_url').value.trim() || window.location.origin,
            theme: 'one-pager',
            agency_mode: true,
            agency_name: 'Herz & Seele Digital',
            agency_support: 'hallo@herzundseele-digital.de',
            client_guard: true,
            admin_user: adminUser,
            admin_email: adminEmail,
            admin_pass: adminPass,
            _csrf_token: csrfToken
        };

        try {
            const res = await fetch('/install/api/run-install', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify(payload)
            });
            const data = await res.json();
            if (!data.success) {
                errBox.textContent = data.message || 'Installation fehlgeschlagen.';
                errBox.style.display = 'block';
                btn.disabled = false;
                btn.innerHTML = '<span>CMS jetzt installieren &rarr;</span>';
                return;
            }

            document.getElementById('summary-user').textContent = adminUser;
            document.getElementById('summary-db').textContent = currentEnv === 'local' ? 'SQLite (storage/database.sqlite)' : 'MySQL (' + payload.db_name + ')';
            goToStep(3);
        } catch (e) {
            errBox.textContent = 'Verbindungsfehler: ' + e.message;
            errBox.style.display = 'block';
            btn.disabled = false;
            btn.innerHTML = '<span>CMS jetzt installieren &rarr;</span>';
        }
    }
    </script>
</body>
</html>
HTML;
    }
}
