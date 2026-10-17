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
                403
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
     * AJAX endpoint to execute Step 4: Run migrations, seed starter content, lock installer
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
</head>
<body class="bg-[#090d16] text-slate-100 min-h-screen flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-[#0f172a] border border-[#1e293b] rounded-2xl p-8 text-center shadow-2xl">
        <div class="w-16 h-16 mx-auto mb-4 bg-emerald-500/10 text-emerald-400 rounded-full flex items-center justify-center">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
        </div>
        <h1 class="text-2xl font-bold text-white mb-2">Heartbeat CMS ist aktiv</h1>
        <p class="text-slate-400 text-sm mb-6">Das System ist bereits installiert und gesichert (<code>config/installed.lock</code> aktiv).</p>
        <div class="space-y-3">
            <a href="/admin/login" class="block w-full py-3 px-4 bg-[#f97316] hover:bg-[#ea580c] text-white font-medium rounded-xl transition duration-150 shadow-lg shadow-orange-500/20">Zum Admin-Login</a>
            <a href="/" class="block w-full py-2.5 px-4 bg-[#1e293b] hover:bg-[#334155] text-slate-300 font-medium rounded-xl text-sm transition">Zur Startseite</a>
        </div>
    </div>
</body>
</html>
HTML;
    }

    private function renderWizard(array $checks, string $csrfToken, string $autoUrl): string
    {
        $allPassed = $checks['allPassed'] ? 'true' : 'false';
        $checksJson = htmlspecialchars(json_encode($checks, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8');
        $safeUrl = Security::e($autoUrl);

        return <<<HTML
<!DOCTYPE html>
<html lang="de" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Heartbeat CMS - Web Installer</title>
    <meta name="csrf-token" content="{$csrfToken}">
    <link rel="stylesheet" href="/assets/css/installer.css">
    <script src="/assets/js/alpine.min.js" defer></script>
</head>
<body class="bg-[#090d16] text-slate-100 font-sans antialiased min-h-screen flex flex-col justify-between selection:bg-[#f97316] selection:text-white"
      x-data="heartbeatInstaller({$allPassed}, '{$safeUrl}', '{$csrfToken}')">

    <!-- Header & Ambient Glow -->
    <header class="relative pt-8 pb-4 text-center">
        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-96 h-32 bg-gradient-to-r from-orange-500/20 to-amber-500/10 blur-3xl pointer-events-none"></div>
        <div class="relative z-10 flex items-center justify-center space-x-3 mb-2">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-orange-500 to-amber-600 flex items-center justify-center shadow-lg shadow-orange-500/25">
                <!-- Heartbeat Pulse Icon -->
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                </svg>
            </div>
            <span class="text-2xl font-black tracking-tight text-white">HEART<span class="text-[#f97316]">BEAT</span> <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-orange-500/10 text-orange-400 border border-orange-500/20">CMS</span></span>
        </div>
        <p class="text-xs text-slate-400 uppercase tracking-widest font-medium">Agency-Ready Site Factory & Web-Installer</p>
    </header>

    <!-- Main Wizard Card -->
    <main class="flex-grow flex items-center justify-center p-4">
        <div class="w-full max-w-3xl bg-[#0f172a] border border-[#1e293b] rounded-2xl shadow-2xl overflow-hidden backdrop-blur-xl">

            <!-- Stepper Progress Bar -->
            <div class="bg-[#090d16]/70 border-b border-[#1e293b] px-6 py-4">
                <div class="flex items-center justify-between">
                    <!-- Step 1 -->
                    <div class="flex items-center space-x-2" :class="step >= 1 ? 'text-[#f97316]' : 'text-slate-500'">
                        <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold transition-colors"
                              :class="step > 1 ? 'bg-emerald-500 text-white' : (step === 1 ? 'bg-[#f97316] text-white shadow-lg shadow-orange-500/30' : 'bg-[#1e293b] text-slate-400')">
                            <template x-if="step > 1"><span>✓</span></template>
                            <template x-if="step <= 1"><span>1</span></template>
                        </span>
                        <span class="text-xs font-medium hidden sm:inline" :class="step === 1 ? 'text-white' : ''">System</span>
                    </div>
                    <div class="flex-1 h-0.5 mx-3 bg-[#1e293b]">
                        <div class="h-full bg-[#f97316] transition-all duration-300" :style="'width: ' + ((step - 1) / 3 * 100) + '%'"></div>
                    </div>

                    <!-- Step 2 -->
                    <div class="flex items-center space-x-2" :class="step >= 2 ? 'text-[#f97316]' : 'text-slate-500'">
                        <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold transition-colors"
                              :class="step > 2 ? 'bg-emerald-500 text-white' : (step === 2 ? 'bg-[#f97316] text-white shadow-lg shadow-orange-500/30' : 'bg-[#1e293b] text-slate-400')">
                            <template x-if="step > 2"><span>✓</span></template>
                            <template x-if="step <= 2"><span>2</span></template>
                        </span>
                        <span class="text-xs font-medium hidden sm:inline" :class="step === 2 ? 'text-white' : ''">Datenbank</span>
                    </div>
                    <div class="flex-1 h-0.5 mx-3 bg-[#1e293b]"></div>

                    <!-- Step 3 -->
                    <div class="flex items-center space-x-2" :class="step >= 3 ? 'text-[#f97316]' : 'text-slate-500'">
                        <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold transition-colors"
                              :class="step > 3 ? 'bg-emerald-500 text-white' : (step === 3 ? 'bg-[#f97316] text-white shadow-lg shadow-orange-500/30' : 'bg-[#1e293b] text-slate-400')">
                            <template x-if="step > 3"><span>✓</span></template>
                            <template x-if="step <= 3"><span>3</span></template>
                        </span>
                        <span class="text-xs font-medium hidden sm:inline" :class="step === 3 ? 'text-white' : ''">Website & Admin</span>
                    </div>
                    <div class="flex-1 h-0.5 mx-3 bg-[#1e293b]"></div>

                    <!-- Step 4 -->
                    <div class="flex items-center space-x-2" :class="step === 4 ? 'text-[#f97316]' : 'text-slate-500'">
                        <span class="w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold transition-colors"
                              :class="installed ? 'bg-emerald-500 text-white' : (step === 4 ? 'bg-[#f97316] text-white shadow-lg shadow-orange-500/30' : 'bg-[#1e293b] text-slate-400')">
                            <template x-if="installed"><span>✓</span></template>
                            <template x-if="!installed"><span>4</span></template>
                        </span>
                        <span class="text-xs font-medium hidden sm:inline" :class="step === 4 ? 'text-white' : ''">Abschluss</span>
                    </div>
                </div>
            </div>

            <!-- Content Area -->
            <div class="p-6 sm:p-8">

                <!-- STEP 1: System Checks -->
                <div x-show="step === 1" x-transition>
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h2 class="text-xl font-bold text-white">Schritt 1: System- & Serverprüfung</h2>
                            <p class="text-sm text-slate-400">Prüfung von PHP-Version, Extensions und Schreibrechten für reibungslosen Betrieb.</p>
                        </div>
                        <template x-if="allChecksPassed">
                            <span class="px-3 py-1 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-semibold rounded-full flex items-center space-x-1">
                                <span>●</span> <span>Server bereit</span>
                            </span>
                        </template>
                    </div>

                    <!-- Check Items List -->
                    <div class="space-y-4 mb-6">
                        <!-- PHP Version -->
                        <div class="flex items-center justify-between p-3.5 bg-[#090d16] border border-[#1e293b] rounded-xl">
                            <div class="flex items-center space-x-3">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center text-xs font-bold"
                                     :class="systemData.phpVersion.pass ? 'bg-emerald-500/10 text-emerald-400' : 'bg-rose-500/10 text-rose-400'">
                                    PHP
                                </div>
                                <div>
                                    <div class="text-sm font-medium text-white flex items-center space-x-2">
                                        <span>PHP Laufzeit</span>
                                        <span class="text-xs text-slate-400" x-text="'(Aktiv: ' + systemData.phpVersion.current + ')'"></span>
                                        <template x-if="systemData.phpVersion.preferred">
                                            <span class="px-1.5 py-0.5 text-[10px] bg-emerald-500/20 text-emerald-300 rounded font-semibold">PHP 8.3+ Empfohlen</span>
                                        </template>
                                    </div>
                                    <div class="text-xs text-slate-400" x-text="'Mindestanforderung: ' + systemData.phpVersion.required"></div>
                                </div>
                            </div>
                            <span class="text-xs font-bold px-2.5 py-1 rounded-lg"
                                  :class="systemData.phpVersion.pass ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20'"
                                  x-text="systemData.phpVersion.pass ? 'Bestanden' : 'Nicht erfüllt'"></span>
                        </div>

                        <!-- Extensions -->
                        <div class="bg-[#090d16] border border-[#1e293b] rounded-xl divide-y divide-[#1e293b]">
                            <template x-for="(ext, key) in systemData.extensions" :key="key">
                                <div class="flex items-center justify-between p-3">
                                    <div>
                                        <div class="text-sm font-medium text-white" x-text="ext.name"></div>
                                        <div class="text-xs text-slate-400" x-text="ext.description"></div>
                                    </div>
                                    <span class="text-xs font-bold px-2.5 py-1 rounded-lg"
                                          :class="ext.pass ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20'"
                                          x-text="ext.pass ? 'Aktiv' : 'Fehlt'"></span>
                                </div>
                            </template>
                        </div>

                        <!-- Directory Permissions -->
                        <div class="bg-[#090d16] border border-[#1e293b] rounded-xl divide-y divide-[#1e293b]">
                            <template x-for="(dir, key) in systemData.directories" :key="key">
                                <div class="flex items-center justify-between p-3">
                                    <div>
                                        <div class="text-sm font-medium text-white" x-text="key.replace('_', '/')"></div>
                                        <div class="text-xs text-slate-400" x-text="dir.description"></div>
                                    </div>
                                    <span class="text-xs font-bold px-2.5 py-1 rounded-lg"
                                          :class="dir.pass ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20'"
                                          x-text="dir.pass ? 'Beschreibbar' : 'Schreibgeschützt'"></span>
                                </div>
                            </template>
                        </div>
                    </div>

                    <!-- Step 1 Controls -->
                    <div class="flex justify-end pt-4 border-t border-[#1e293b]">
                        <button type="button" @click="step = 2" :disabled="!allChecksPassed"
                                class="px-6 py-2.5 bg-[#f97316] hover:bg-[#ea580c] disabled:bg-slate-700 disabled:opacity-50 text-white font-medium text-sm rounded-xl transition duration-150 flex items-center space-x-2 shadow-lg shadow-orange-500/20">
                            <span>Weiter zu Schritt 2</span>
                            <span>&rarr;</span>
                        </button>
                    </div>
                </div>

                <!-- STEP 2: Database & Environment -->
                <div x-show="step === 2" x-transition>
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h2 class="text-xl font-bold text-white">Schritt 2: Datenbank & Umgebung</h2>
                            <p class="text-sm text-slate-400">Wähle zwischen MySQL/MariaDB für Production oder SQLite für rasante lokale Entwicklung.</p>
                        </div>
                    </div>

                    <!-- 1-Click SQLite Banner -->
                    <div class="mb-5 p-4 bg-orange-500/10 border border-orange-500/20 rounded-xl flex items-center justify-between">
                        <div class="text-sm">
                            <span class="font-bold text-orange-400">Lokale Core-Entwicklung?</span>
                            <span class="text-slate-300 block text-xs">Nutze die integrierte SQLite-Datenbank ohne manuelle MySQL-Erstellung.</span>
                        </div>
                        <button type="button" @click="useSqlitePreset()"
                                class="px-3.5 py-1.5 bg-[#f97316] hover:bg-[#ea580c] text-white text-xs font-semibold rounded-lg shadow transition">
                            1-Klick SQLite aktivieren
                        </button>
                    </div>

                    <!-- Form Fields -->
                    <div class="space-y-4 mb-6">
                        <!-- Driver Selection -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Datenbank-Treiber</label>
                            <div class="grid grid-cols-2 gap-3">
                                <label class="flex items-center space-x-2.5 p-3 rounded-xl border cursor-pointer transition"
                                       :class="form.db_driver === 'mysql' ? 'bg-orange-500/10 border-[#f97316] text-white' : 'bg-[#090d16] border-[#1e293b] text-slate-400'">
                                    <input type="radio" name="db_driver" value="mysql" x-model="form.db_driver" class="text-[#f97316] focus:ring-0">
                                    <span class="text-sm font-semibold">MySQL / MariaDB (Production)</span>
                                </label>
                                <label class="flex items-center space-x-2.5 p-3 rounded-xl border cursor-pointer transition"
                                       :class="form.db_driver === 'sqlite' ? 'bg-orange-500/10 border-[#f97316] text-white' : 'bg-[#090d16] border-[#1e293b] text-slate-400'">
                                    <input type="radio" name="db_driver" value="sqlite" x-model="form.db_driver" class="text-[#f97316] focus:ring-0">
                                    <span class="text-sm font-semibold">SQLite (Zero-Config Dev)</span>
                                </label>
                            </div>
                        </div>

                        <!-- MySQL Specific Fields -->
                        <div x-show="form.db_driver === 'mysql'" class="space-y-3" x-transition>
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-medium text-slate-300 mb-1">Host</label>
                                    <input type="text" x-model="form.db_host" class="w-full bg-[#090d16] border border-[#1e293b] focus:border-[#f97316] rounded-xl px-3.5 py-2 text-sm text-white focus:outline-none transition">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-300 mb-1">Port</label>
                                    <input type="number" x-model="form.db_port" class="w-full bg-[#090d16] border border-[#1e293b] focus:border-[#f97316] rounded-xl px-3.5 py-2 text-sm text-white focus:outline-none transition">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-slate-300 mb-1">Datenbankname</label>
                                    <input type="text" x-model="form.db_name" placeholder="z. B. heartbeat_db" class="w-full bg-[#090d16] border border-[#1e293b] focus:border-[#f97316] rounded-xl px-3.5 py-2 text-sm text-white focus:outline-none transition">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-300 mb-1">Tabellen-Präfix</label>
                                    <input type="text" x-model="form.db_prefix" class="w-full bg-[#090d16] border border-[#1e293b] focus:border-[#f97316] rounded-xl px-3.5 py-2 text-sm text-white focus:outline-none transition">
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-slate-300 mb-1">Benutzername</label>
                                    <input type="text" x-model="form.db_user" class="w-full bg-[#090d16] border border-[#1e293b] focus:border-[#f97316] rounded-xl px-3.5 py-2 text-sm text-white focus:outline-none transition">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-300 mb-1">Passwort</label>
                                    <input type="password" x-model="form.db_pass" class="w-full bg-[#090d16] border border-[#1e293b] focus:border-[#f97316] rounded-xl px-3.5 py-2 text-sm text-white focus:outline-none transition">
                                </div>
                            </div>
                        </div>

                        <!-- SQLite Info -->
                        <div x-show="form.db_driver === 'sqlite'" class="p-3 bg-[#090d16] border border-[#1e293b] rounded-xl text-xs text-slate-400" x-transition>
                            Die SQLite-Datei wird sicher unter <code>storage/database.sqlite</code> mit WAL-Modus und aktiviertem Fremdschlüssel-Support abgelegt.
                        </div>

                        <!-- Test Connection Button & Result -->
                        <div class="pt-2">
                            <div class="flex items-center space-x-3">
                                <button type="button" @click="testDatabaseConnection()" :disabled="testingDb"
                                        class="px-4 py-2 bg-[#1e293b] hover:bg-[#334155] text-white text-xs font-semibold rounded-xl border border-slate-700 transition flex items-center space-x-2">
                                    <span x-show="!testingDb">⚡ Verbindung testen</span>
                                    <span x-show="testingDb">Prüfe Verbindung...</span>
                                </button>
                                <template x-if="dbTestResult">
                                    <span class="text-xs font-medium px-3 py-1.5 rounded-lg flex items-center space-x-1.5"
                                          :class="dbTestResult.success ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20'">
                                        <span x-text="dbTestResult.success ? '✓' : '✗'"></span>
                                        <span x-text="dbTestResult.message"></span>
                                    </span>
                                </template>
                            </div>
                        </div>

                        <!-- Environment Mode -->
                        <div class="pt-2">
                            <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">System-Umgebung</label>
                            <div class="grid grid-cols-2 gap-3">
                                <label class="flex items-center space-x-2 p-2.5 rounded-xl border cursor-pointer text-xs transition"
                                       :class="form.app_env === 'development' ? 'bg-orange-500/10 border-[#f97316] text-white' : 'bg-[#090d16] border-[#1e293b] text-slate-400'">
                                    <input type="radio" value="development" x-model="form.app_env" class="text-[#f97316]">
                                    <span>Development (Debug ON)</span>
                                </label>
                                <label class="flex items-center space-x-2 p-2.5 rounded-xl border cursor-pointer text-xs transition"
                                       :class="form.app_env === 'production' ? 'bg-orange-500/10 border-[#f97316] text-white' : 'bg-[#090d16] border-[#1e293b] text-slate-400'">
                                    <input type="radio" value="production" x-model="form.app_env" class="text-[#f97316]">
                                    <span>Production (Debug OFF)</span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2 Controls -->
                    <div class="flex justify-between pt-4 border-t border-[#1e293b]">
                        <button type="button" @click="step = 1" class="px-5 py-2.5 bg-[#1e293b] hover:bg-[#334155] text-slate-300 font-medium text-sm rounded-xl transition">
                            &larr; Zurück
                        </button>
                        <button type="button" @click="step = 3"
                                class="px-6 py-2.5 bg-[#f97316] hover:bg-[#ea580c] text-white font-medium text-sm rounded-xl transition duration-150 flex items-center space-x-2 shadow-lg shadow-orange-500/20">
                            <span>Weiter zu Schritt 3</span>
                            <span>&rarr;</span>
                        </button>
                    </div>
                </div>

                <!-- STEP 3: Website, Agency & Admin Setup -->
                <div x-show="step === 3" x-transition>
                    <div class="mb-4">
                        <h2 class="text-xl font-bold text-white">Schritt 3: Website & Administrator</h2>
                        <p class="text-sm text-slate-400">Konfiguriere Basisdaten, Default-Theme, Agentur White-Labeling und den initialen Super-Admin.</p>
                    </div>

                    <div class="space-y-4 mb-6">
                        <!-- Site Settings -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-slate-300 mb-1">Website-Titel</label>
                                <input type="text" x-model="form.site_title" class="w-full bg-[#090d16] border border-[#1e293b] focus:border-[#f97316] rounded-xl px-3.5 py-2 text-sm text-white focus:outline-none transition">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-slate-300 mb-1">Basis-URL (Auto-Detected)</label>
                                <input type="text" x-model="form.site_url" class="w-full bg-[#090d16] border border-[#1e293b] focus:border-[#f97316] rounded-xl px-3.5 py-2 text-sm text-white focus:outline-none transition">
                            </div>
                        </div>

                        <!-- Theme Selection -->
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Start-Theme wählen</label>
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
                                <label class="p-3 bg-[#090d16] border rounded-xl cursor-pointer text-center transition"
                                       :class="form.theme === 'one-pager' ? 'border-[#f97316] bg-orange-500/10' : 'border-[#1e293b]'">
                                    <input type="radio" value="one-pager" x-model="form.theme" class="sr-only">
                                    <div class="text-sm font-bold text-white">One-Pager</div>
                                    <div class="text-[11px] text-slate-400 mt-0.5">Hero, Features, Kontakt</div>
                                </label>
                                <label class="p-3 bg-[#090d16] border rounded-xl cursor-pointer text-center transition"
                                       :class="form.theme === 'corporate' ? 'border-[#f97316] bg-orange-500/10' : 'border-[#1e293b]'">
                                    <input type="radio" value="corporate" x-model="form.theme" class="sr-only">
                                    <div class="text-sm font-bold text-white">Corporate</div>
                                    <div class="text-[11px] text-slate-400 mt-0.5">Multi-Page, Team, Services</div>
                                </label>
                                <label class="p-3 bg-[#090d16] border rounded-xl cursor-pointer text-center transition"
                                       :class="form.theme === 'real-estate' ? 'border-[#f97316] bg-orange-500/10' : 'border-[#1e293b]'">
                                    <input type="radio" value="real-estate" x-model="form.theme" class="sr-only">
                                    <div class="text-sm font-bold text-white">Real Estate</div>
                                    <div class="text-[11px] text-slate-400 mt-0.5">Immobilien-CPT & Filter</div>
                                </label>
                                <label class="p-3 bg-[#090d16] border rounded-xl cursor-pointer text-center transition"
                                       :class="form.theme === 'startup' ? 'border-[#f97316] bg-orange-500/10' : 'border-[#1e293b]'">
                                    <input type="radio" value="startup" x-model="form.theme" class="sr-only">
                                    <div class="text-sm font-bold text-white">Startup</div>
                                    <div class="text-[11px] text-slate-400 mt-0.5">Pricing & Dark/Light</div>
                                </label>
                            </div>
                        </div>

                        <!-- Agency Mode Accordion/Card -->
                        <div class="p-4 bg-[#090d16] border border-[#1e293b] rounded-xl space-y-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <span class="text-sm font-semibold text-white">Agency White-Label & Client-Guard</span>
                                    <span class="text-xs text-slate-400 block">Rebrande das CMS für deinen Kunden und schütze kritische Systembereiche.</span>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" x-model="form.agency_mode" class="sr-only peer">
                                    <div class="w-11 h-6 bg-slate-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#f97316]"></div>
                                </label>
                            </div>

                            <div x-show="form.agency_mode" class="pt-2 grid grid-cols-1 sm:grid-cols-2 gap-3" x-transition>
                                <div>
                                    <label class="block text-xs font-medium text-slate-300 mb-1">Agenturname</label>
                                    <input type="text" x-model="form.agency_name" class="w-full bg-[#0f172a] border border-[#1e293b] focus:border-[#f97316] rounded-xl px-3 py-1.5 text-xs text-white focus:outline-none">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-300 mb-1">Agentur Support-E-Mail</label>
                                    <input type="email" x-model="form.agency_support" class="w-full bg-[#0f172a] border border-[#1e293b] focus:border-[#f97316] rounded-xl px-3 py-1.5 text-xs text-white focus:outline-none">
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="flex items-center space-x-2 text-xs text-slate-300 cursor-pointer">
                                        <input type="checkbox" x-model="form.client_guard" class="text-[#f97316] rounded">
                                        <span>Client-Guard aktivieren (verbirgt Code-Injection und Core-Updater vor Standard-Admins)</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Super Admin Account -->
                        <div class="p-4 bg-[#090d16] border border-[#1e293b] rounded-xl space-y-3">
                            <div class="text-xs font-semibold text-orange-400 uppercase tracking-wider">Initialer Super-Admin Account</div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-slate-300 mb-1">Benutzername</label>
                                    <input type="text" x-model="form.admin_user" class="w-full bg-[#0f172a] border border-[#1e293b] focus:border-[#f97316] rounded-xl px-3.5 py-2 text-sm text-white focus:outline-none transition">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-300 mb-1">E-Mail-Adresse</label>
                                    <input type="email" x-model="form.admin_email" class="w-full bg-[#0f172a] border border-[#1e293b] focus:border-[#f97316] rounded-xl px-3.5 py-2 text-sm text-white focus:outline-none transition">
                                </div>
                            </div>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-slate-300 mb-1">Admin Passwort</label>
                                    <input type="password" x-model="form.admin_pass" class="w-full bg-[#0f172a] border border-[#1e293b] focus:border-[#f97316] rounded-xl px-3.5 py-2 text-sm text-white focus:outline-none transition">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-300 mb-1">Passwort bestätigen</label>
                                    <input type="password" x-model="form.admin_pass_confirm" class="w-full bg-[#0f172a] border border-[#1e293b] focus:border-[#f97316] rounded-xl px-3.5 py-2 text-sm text-white focus:outline-none transition">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3 Controls -->
                    <div class="flex justify-between pt-4 border-t border-[#1e293b]">
                        <button type="button" @click="step = 2" class="px-5 py-2.5 bg-[#1e293b] hover:bg-[#334155] text-slate-300 font-medium text-sm rounded-xl transition">
                            &larr; Zurück
                        </button>
                        <button type="button" @click="submitInstallation()" :disabled="installing"
                                class="px-6 py-2.5 bg-gradient-to-r from-orange-500 to-amber-600 hover:from-orange-600 hover:to-amber-700 text-white font-semibold text-sm rounded-xl transition duration-150 flex items-center space-x-2 shadow-lg shadow-orange-500/25">
                            <span x-show="!installing">Installation jetzt starten &rarr;</span>
                            <span x-show="installing">Installiere Heartbeat CMS...</span>
                        </button>
                    </div>
                </div>

                <!-- STEP 4: Completion / Success -->
                <div x-show="step === 4" x-transition class="text-center py-6">
                    <div class="w-16 h-16 mx-auto mb-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-2xl flex items-center justify-center shadow-lg shadow-emerald-500/20">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                    </div>

                    <h2 class="text-2xl font-black text-white mb-2 tracking-tight">Herzlichen Glückwunsch!</h2>
                    <p class="text-slate-400 text-sm max-w-md mx-auto mb-6">
                        Heartbeat CMS wurde erfolgreich eingerichtet, die Datenbank-Tabellen migriert und dein Super-Admin Account initialisiert.
                    </p>

                    <!-- Summary Stats Card -->
                    <div class="bg-[#090d16] border border-[#1e293b] rounded-xl p-4 max-w-md mx-auto mb-8 text-left space-y-2 text-xs">
                        <div class="flex justify-between text-slate-400">
                            <span>Sicherheits-Lock:</span>
                            <span class="text-emerald-400 font-mono">config/installed.lock aktiv</span>
                        </div>
                        <div class="flex justify-between text-slate-400">
                            <span>Konfiguration:</span>
                            <span class="text-slate-200 font-mono">config/env.php erstellt</span>
                        </div>
                        <div class="flex justify-between text-slate-400">
                            <span>Theme:</span>
                            <span class="text-orange-400 font-semibold" x-text="form.theme"></span>
                        </div>
                        <div class="flex justify-between text-slate-400">
                            <span>Admin-Benutzer:</span>
                            <span class="text-white font-mono" x-text="form.admin_user"></span>
                        </div>
                    </div>

                    <!-- Direct Action Buttons -->
                    <div class="flex flex-col sm:flex-row items-center justify-center gap-3">
                        <a href="/admin/login" class="w-full sm:w-auto px-6 py-3 bg-[#f97316] hover:bg-[#ea580c] text-white font-semibold text-sm rounded-xl transition duration-150 shadow-lg shadow-orange-500/25 flex items-center justify-center space-x-2">
                            <span>Admin Dashboard öffnen</span>
                            <span>&rarr;</span>
                        </a>
                        <a href="/" class="w-full sm:w-auto px-6 py-3 bg-[#1e293b] hover:bg-[#334155] text-slate-300 font-medium text-sm rounded-xl transition flex items-center justify-center">
                            Website-Frontend ansehen
                        </a>
                    </div>
                </div>

                <!-- Global Error Banner -->
                <div x-show="errorMessage" class="mt-4 p-3.5 bg-rose-500/10 border border-rose-500/20 rounded-xl text-rose-300 text-xs flex items-center space-x-2" x-transition>
                    <span>⚠️</span>
                    <span x-text="errorMessage"></span>
                </div>

            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="py-4 text-center text-xs text-slate-500">
        Heartbeat CMS &bull; High-Velocity PHP 8.3 &bull; Inspired by Herz &amp; Seele Digital
    </footer>

    <!-- Alpine.js Installer Controller Script -->
    <script>
    function heartbeatInstaller(allChecksPassed, autoUrl, csrfToken) {
        return {
            step: 1,
            allChecksPassed: allChecksPassed,
            systemData: {$checksJson},
            csrfToken: csrfToken,
            testingDb: false,
            dbTestResult: null,
            installing: false,
            installed: false,
            errorMessage: '',

            form: {
                db_driver: 'sqlite',
                db_host: '127.0.0.1',
                db_port: 3306,
                db_name: 'heartbeat',
                db_user: 'root',
                db_pass: '',
                db_prefix: 'hb_',
                app_env: 'development',
                site_title: 'Heartbeat Starter',
                site_url: autoUrl,
                theme: 'one-pager',
                agency_mode: true,
                agency_name: 'Herz & Seele Digital',
                agency_support: 'hallo@herzundseele-digital.de',
                client_guard: true,
                admin_user: 'admin',
                admin_email: 'admin@example.com',
                admin_pass: 'Admin123!',
                admin_pass_confirm: 'Admin123!',
                _csrf_token: csrfToken
            },

            useSqlitePreset() {
                this.form.db_driver = 'sqlite';
                this.testDatabaseConnection();
            },

            async testDatabaseConnection() {
                this.testingDb = true;
                this.dbTestResult = null;
                this.errorMessage = '';

                try {
                    const res = await fetch('/install/api/test-db', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken
                        },
                        body: JSON.stringify({
                            driver: this.form.db_driver,
                            host: this.form.db_host,
                            port: this.form.db_port,
                            database: this.form.db_name,
                            username: this.form.db_user,
                            password: this.form.db_pass
                        })
                    });

                    const data = await res.json();
                    this.dbTestResult = data;
                } catch (err) {
                    this.dbTestResult = { success: false, message: 'Netzwerkfehler beim Verbindungstest: ' + err.message };
                } finally {
                    this.testingDb = false;
                }
            },

            async submitInstallation() {
                this.errorMessage = '';

                if (!this.form.admin_user || !this.form.admin_email || !this.form.admin_pass) {
                    this.errorMessage = 'Bitte alle Administrator-Felder ausfüllen.';
                    return;
                }

                if (this.form.admin_pass !== this.form.admin_pass_confirm) {
                    this.errorMessage = 'Die angegebenen Administrator-Passwörter stimmen nicht überein.';
                    return;
                }

                this.installing = true;

                try {
                    const res = await fetch('/install/api/run-install', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': this.csrfToken
                        },
                        body: JSON.stringify(this.form)
                    });

                    const data = await res.json();

                    if (!data.success) {
                        this.errorMessage = data.message || 'Fehler bei der Installation.';
                        this.installing = false;
                        return;
                    }

                    this.installed = true;
                    this.step = 4;
                } catch (err) {
                    this.errorMessage = 'Verbindungsfehler bei der Installation: ' + err.message;
                } finally {
                    this.installing = false;
                }
            }
        };
    }
    </script>
</body>
</html>
HTML;
    }
}

