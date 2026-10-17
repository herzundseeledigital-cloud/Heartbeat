<?php
declare(strict_types=1);

namespace Heartbeat\Admin;

use Heartbeat\Http\Request;
use Heartbeat\Http\Response;
use Heartbeat\Security\Security;
use Heartbeat\Security\Csrf;
use Heartbeat\Security\Session;
use Heartbeat\Auth\Auth;
use Heartbeat\CustomCode\CustomCodeService;

/**
 * Custom Code Injection & Safe-Mode Manager Controller
 */
final class CustomCodeController
{
    private CustomCodeService $codeService;

    public function __construct(?CustomCodeService $codeService = null)
    {
        $this->codeService = $codeService ?? CustomCodeService::getInstance();
    }

    /**
     * Custom Code Manager Overview
     */
    public function index(Request $request): Response
    {
        $redirect = $this->requireAuth('manage_code');
        if ($redirect) return $redirect;

        $headCode = Security::e($this->codeService->getHeadCode());
        $bodyStartCode = Security::e($this->codeService->getBodyStartCode());
        $bodyEndCode = Security::e($this->codeService->getBodyEndCode());
        $isEnabled = $this->codeService->isEnabled();
        $safeToken = $this->codeService->getSafeModeToken();

        $baseUrl = rtrim($request->getBaseUrl(), '/');
        $safeModeUrl = $baseUrl . '/?safe_mode=' . $safeToken;
        $simpleSafeUrl = $baseUrl . '/?hb_safe_mode=1';

        $csrfField = Csrf::field();
        $enabledChecked = $isEnabled ? 'checked' : '';

        $contentHtml = <<<HTML
<div class="max-w-4xl space-y-6" x-data="{ copied: false, copyUrl(url) { navigator.clipboard.writeText(url); this.copied = true; setTimeout(() => this.copied = false, 3000); } }">
    <!-- Safe Mode Emergency Recovery Card -->
    <div class="p-6 rounded-2xl bg-gradient-to-r from-amber-500/10 via-[#141e33] to-[#0f172a] border border-amber-500/30 shadow-xl">
        <div class="flex items-start space-x-4">
            <div class="w-10 h-10 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center font-bold text-lg flex-shrink-0">
                🛡️
            </div>
            <div class="flex-1">
                <div class="flex items-center space-x-2">
                    <h3 class="text-sm font-bold text-white">Disaster Recovery: Safe-Mode Notfall-Bypass</h3>
                    <span class="text-[10px] px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-400 font-mono font-semibold">Self-Healing</span>
                </div>
                <p class="text-xs text-slate-300 mt-1 leading-relaxed">
                    Sollte ein fehlerhaftes JavaScript-Snippet oder Tracking-Skript die Website oder das Admin-Panel blockieren,
                    können Sie alle benutzerdefinierten Skripte sofort durch den Safe-Mode URL-Parameter umgehen.
                </p>
                <div class="mt-4 flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                    <input type="text" readonly value="{$safeModeUrl}" class="flex-1 px-3.5 py-2 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs font-mono text-amber-300 select-all focus:outline-none">
                    <button type="button" @click="copyUrl('{$safeModeUrl}')" class="px-4 py-2 rounded-xl bg-amber-500/20 hover:bg-amber-500/30 text-amber-400 border border-amber-500/40 text-xs font-semibold transition flex items-center justify-center space-x-1.5 flex-shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 5H6a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2v-1M8 5a2 2 0 002 2h2a2 2 0 002-2M8 5a2 2 0 012-2h2a2 2 0 012 2m0 0h2a2 2 0 012 2v3m2 4H10m0 0l3-3m-3 3l3 3"></path></svg>
                        <span x-text="copied ? 'Kopiert!' : 'Safe-Mode Link kopieren'">Safe-Mode Link kopieren</span>
                    </button>
                </div>
                <div class="mt-2 text-[11px] text-slate-400 font-mono">
                    Alternativ für Administratoren: <code class="text-slate-300">{$simpleSafeUrl}</code>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Code Form -->
    <form method="POST" action="/admin/custom-code/save" class="bg-[#0f172a] border border-[#1e293b] rounded-2xl p-6 sm:p-8 shadow-xl space-y-6">
        {$csrfField}

        <!-- Master Switch -->
        <div class="flex items-center justify-between pb-6 border-b border-[#1e293b]">
            <div>
                <h2 class="text-sm font-bold text-white">Custom Code Injektion aktivieren</h2>
                <p class="text-xs text-slate-400 mt-0.5">Aktiviert oder pausiert die automatische Einbindung aller Snippets im Frontend.</p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" name="enabled" value="1" {$enabledChecked} class="sr-only peer">
                <div class="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#f97316]"></div>
            </label>
        </div>

        <!-- 1. Head Code -->
        <div class="space-y-2">
            <div class="flex items-center justify-between">
                <label class="block text-xs font-bold text-white uppercase tracking-wider">1. &lt;head&gt; Snippets</label>
                <span class="text-[11px] text-slate-500 font-mono">Wird direkt vor &lt;/head&gt; eingefügt</span>
            </div>
            <p class="text-xs text-slate-400">Geeignet für Meta-Tags, Canonical-Links, Tracking-Header oder externe Stylesheets.</p>
            <textarea name="head_code" rows="5" placeholder="<!-- z.B. <meta name='google-site-verification' content='...' /> -->"
                      class="w-full p-4 rounded-xl bg-[#090d16] border border-[#1e293b] text-white font-mono text-xs focus:outline-none focus:border-[#f97316] leading-relaxed selection:bg-orange-500/30">{$headCode}</textarea>
        </div>

        <!-- 2. Opening Body Code -->
        <div class="space-y-2 pt-4 border-t border-[#1e293b]">
            <div class="flex items-center justify-between">
                <label class="block text-xs font-bold text-white uppercase tracking-wider">2. &lt;body&gt; Start Snippets</label>
                <span class="text-[11px] text-slate-500 font-mono">Wird direkt nach &lt;body&gt; eingefügt</span>
            </div>
            <p class="text-xs text-slate-400">Geeignet für Google Tag Manager &lt;noscript&gt; oder Barrierefreiheits-Skip-Links.</p>
            <textarea name="body_start_code" rows="5" placeholder="<!-- z.B. <noscript><iframe src='https://www.googletagmanager.com/ns.html?id=...' ...></iframe></noscript> -->"
                      class="w-full p-4 rounded-xl bg-[#090d16] border border-[#1e293b] text-white font-mono text-xs focus:outline-none focus:border-[#f97316] leading-relaxed selection:bg-orange-500/30">{$bodyStartCode}</textarea>
        </div>

        <!-- 3. Closing Body Code -->
        <div class="space-y-2 pt-4 border-t border-[#1e293b]">
            <div class="flex items-center justify-between">
                <label class="block text-xs font-bold text-white uppercase tracking-wider">3. &lt;body&gt; End Snippets</label>
                <span class="text-[11px] text-slate-500 font-mono">Wird direkt vor &lt;/body&gt; eingefügt</span>
            </div>
            <p class="text-xs text-slate-400">Geeignet für Chat-Widgets, Analytics-Footer, Skripte und Consent-Tools.</p>
            <textarea name="body_end_code" rows="5" placeholder="<!-- z.B. <script>console.log('Heartbeat Custom JS');</script> -->"
                      class="w-full p-4 rounded-xl bg-[#090d16] border border-[#1e293b] text-white font-mono text-xs focus:outline-none focus:border-[#f97316] leading-relaxed selection:bg-orange-500/30">{$bodyEndCode}</textarea>
        </div>

        <!-- Footer Actions -->
        <div class="flex items-center justify-end space-x-3 pt-6 border-t border-[#1e293b]">
            <a href="/admin" class="px-4 py-2.5 rounded-xl bg-[#141e33] hover:bg-[#1e293b] text-xs text-slate-300 hover:text-white transition">
                Abbrechen
            </a>
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-[#f97316] hover:bg-[#ea580c] text-white text-xs font-semibold shadow-lg shadow-orange-500/20 transition flex items-center space-x-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                <span>Code-Snippets speichern</span>
            </button>
        </div>
    </form>
</div>
HTML;

        return Response::html(AdminView::renderShell('Custom Code Manager', 'custom_code', $contentHtml, [
            ['label' => 'Dashboard', 'url' => '/admin'],
            ['label' => 'Code Manager', 'url' => '/admin/custom-code'],
        ]));
    }

    /**
     * Save Custom Code (POST)
     */
    public function save(Request $request): Response
    {
        $redirect = $this->requireAuth('manage_code');
        if ($redirect) return $redirect;

        if (!Csrf::verifyRequest($request)) {
            Session::flash('error', 'Ungültiges Sicherheits-Token (CSRF).');
            return Response::redirect('/admin/custom-code');
        }

        $headCode = (string)$request->post('head_code', '');
        $bodyStartCode = (string)$request->post('body_start_code', '');
        $bodyEndCode = (string)$request->post('body_end_code', '');
        $enabled = $request->post('enabled') === '1';

        $saved = $this->codeService->save([
            'head_code'       => $headCode,
            'body_start_code' => $bodyStartCode,
            'body_end_code'   => $bodyEndCode,
            'enabled'         => $enabled,
        ]);

        if ($saved) {
            Session::flash('success', 'Custom Code Snippets wurden erfolgreich gespeichert und der Seiten-Cache geleert.');
        } else {
            Session::flash('error', 'Fehler beim Speichern der Custom Code Snippets.');
        }

        return Response::redirect('/admin/custom-code');
    }

    private function requireAuth(string $permission = 'manage_code'): ?Response
    {
        if (!Auth::check()) {
            return Response::redirect('/admin/login');
        }
        if (!Auth::can($permission)) {
            Session::flash('error', 'Sie haben keine Berechtigung für den Custom Code Manager (Client-Guard / RBAC).');
            return Response::redirect('/admin');
        }
        return null;
    }
}

