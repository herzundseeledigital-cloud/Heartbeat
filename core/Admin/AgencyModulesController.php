<?php
declare(strict_types=1);

namespace Heartbeat\Admin;

use Heartbeat\Http\Request;
use Heartbeat\Http\Response;
use Heartbeat\Security\Csrf;
use Heartbeat\Security\Security;
use Heartbeat\Security\Rbac;
use Heartbeat\Security\Session;
use Heartbeat\Auth\Auth;
use Heartbeat\Database\Database;
use Heartbeat\Agency\AgencyService;
use Heartbeat\Mail\Mailer;
use Heartbeat\Backup\BackupService;
use Heartbeat\Updater\UpdaterService;
use Heartbeat\Analytics\AnalyticsService;
use Heartbeat\Seo\SeoService;
use Heartbeat\Ai\AiService;
use Heartbeat\Gdpr\GoogleFontsDownloader;
use Heartbeat\Gdpr\GdprService;
use Heartbeat\Payments\StripeService;

/**
 * Controller for Phase 5 Agency Modules & Lifecycle Tools:
 * Analytics, SEO & AI, Mail, Backups, OTA Updater, GDPR & Local Fonts, Stripe.
 */
final class AgencyModulesController
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    private function requireAuth(string $permission = 'access_admin'): ?Response
    {
        if (!Auth::check()) {
            return Response::redirect('/admin/login');
        }

        $user = Auth::user();
        $role = (string)($user['role'] ?? 'user');
        $isClientGuard = AgencyService::getInstance()->isClientGuardActive();

        if (!Rbac::can($role, $permission, $isClientGuard)) {
            Session::flash('error', 'Zugriff verweigert: Unzureichende Berechtigungen oder durch Client-Guard gesperrt.');
            return Response::redirect('/admin');
        }

        return null;
    }

    private function csrfField(): string
    {
        return Csrf::formInput();
    }

    // =========================================================================
    // 1. DSGVO COOKIELESS ANALYTICS
    // =========================================================================

    public function analytics(Request $request): Response
    {
        $redirect = $this->requireAuth('manage_content');
        if ($redirect) return $redirect;

        $analytics = AnalyticsService::getInstance();
        $days = (int)$request->query('days', 30);
        if ($days <= 0 || $days > 90) $days = 30;

        $summary = $analytics->getDashboardSummary($days);

        $totalViews = number_format($summary['total_views'], 0, ',', '.');
        $totalUniques = number_format($summary['unique_visitors'], 0, ',', '.');
        $todayViews = number_format($summary['today_views'], 0, ',', '.');
        $todayUniques = number_format($summary['today_uniques'], 0, ',', '.');

        // Top pages rows
        $topPagesHtml = '';
        if (empty($summary['top_pages'])) {
            $topPagesHtml = '<tr><td colspan="3" class="py-6 text-center text-xs text-slate-500">Noch keine Seitenaufrufe im gewählten Zeitraum erfasst.</td></tr>';
        } else {
            foreach ($summary['top_pages'] as $p) {
                $path = Security::e($p['path']);
                $views = number_format((int)$p['views'], 0, ',', '.');
                $uniques = number_format((int)$p['uniques'], 0, ',', '.');
                $topPagesHtml .= <<<HTML
<tr class="border-b border-[#1e293b] hover:bg-[#141e33]/50 transition">
    <td class="py-3 px-4 text-xs font-mono text-slate-200">{$path}</td>
    <td class="py-3 px-4 text-xs text-right font-bold text-white">{$views}</td>
    <td class="py-3 px-4 text-xs text-right text-orange-400 font-mono">{$uniques}</td>
</tr>
HTML;
            }
        }

        // Top referrers rows
        $referrersHtml = '';
        if (empty($summary['top_referrers'])) {
            $referrersHtml = '<tr><td colspan="2" class="py-6 text-center text-xs text-slate-500">Bisher nur direkte Aufrufe.</td></tr>';
        } else {
            foreach ($summary['top_referrers'] as $r) {
                $ref = Security::e($r['referrer']);
                $cnt = number_format((int)$r['views'], 0, ',', '.');
                $referrersHtml .= <<<HTML
<tr class="border-b border-[#1e293b] hover:bg-[#141e33]/50 transition">
    <td class="py-3 px-4 text-xs text-slate-200">{$ref}</td>
    <td class="py-3 px-4 text-xs text-right font-bold text-white">{$cnt}</td>
</tr>
HTML;
            }
        }

        // Devices progress bars
        $devs = $summary['devices'];
        $sumDevs = array_sum($devs) ?: 1;
        $desktopPct = round(($devs['desktop'] / $sumDevs) * 100);
        $mobilePct = round(($devs['mobile'] / $sumDevs) * 100);
        $tabletPct = round(($devs['tablet'] / $sumDevs) * 100);

        // Daily Trend Chart Bars (pure CSS/HTML)
        $chartBars = '';
        $maxDaily = 1;
        foreach ($summary['daily_trend'] as $d) {
            if ((int)$d['views'] > $maxDaily) $maxDaily = (int)$d['views'];
        }
        foreach ($summary['daily_trend'] as $d) {
            $h = max(6, round(((int)$d['views'] / $maxDaily) * 100));
            $dFormatted = date('d.m', strtotime($d['date']));
            $chartBars .= <<<HTML
<div class="flex-1 flex flex-col items-center group relative">
    <div class="w-full bg-[#1e293b] rounded-t-md hover:bg-orange-500 transition-colors" style="height: {$h}%;"></div>
    <span class="text-[9px] text-slate-500 mt-2 font-mono hidden sm:block">{$dFormatted}</span>
    <!-- Tooltip -->
    <div class="absolute bottom-full mb-2 hidden group-hover:block bg-[#0f172a] border border-[#1e293b] text-white text-[10px] py-1 px-2 rounded shadow-xl whitespace-nowrap z-20">
        {$dFormatted}: {$d['views']} Aufrufe ({$d['uniques']} Besucher)
    </div>
</div>
HTML;
        }

        $actionsHtml = <<<HTML
<div class="flex items-center space-x-2">
    <a href="/admin/analytics?days=7" class="px-3 py-1.5 rounded-lg text-xs font-medium {$this->filterBtnClass($days, 7)}">7 Tage</a>
    <a href="/admin/analytics?days=30" class="px-3 py-1.5 rounded-lg text-xs font-medium {$this->filterBtnClass($days, 30)}">30 Tage</a>
    <a href="/admin/analytics?days=90" class="px-3 py-1.5 rounded-lg text-xs font-medium {$this->filterBtnClass($days, 90)}">90 Tage</a>
</div>
HTML;

        $contentHtml = <<<HTML
<div class="space-y-6">
    <!-- DSGVO Banner -->
    <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs flex items-center justify-between">
        <div class="flex items-center space-x-2.5">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
            <span><strong>100% DSGVO- &amp; TDDDG-konform:</strong> Vollständig cookielos, subnetz-maskiert (/24 &amp; /48) und ohne externe Drittanbieter-Anfragen. Kein Consent-Banner erforderlich.</span>
        </div>
        <span class="text-[11px] font-mono px-2 py-0.5 rounded bg-emerald-500/20 text-emerald-300">TDDDG-Safe</span>
    </div>

    <!-- KPI Cards Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        <div class="p-5 bg-[#0f172a] border border-[#1e293b] rounded-2xl shadow-lg">
            <span class="text-xs font-semibold text-slate-400 block mb-1">Seitenaufrufe ({$days} Tage)</span>
            <div class="text-2xl font-black text-white tracking-tight">{$totalViews}</div>
            <p class="text-[11px] text-slate-500 mt-1">Insgesamte Seitenabrufe</p>
        </div>
        <div class="p-5 bg-[#0f172a] border border-[#1e293b] rounded-2xl shadow-lg">
            <span class="text-xs font-semibold text-slate-400 block mb-1">Eindeutige Besucher ({$days} Tage)</span>
            <div class="text-2xl font-black text-orange-400 tracking-tight">{$totalUniques}</div>
            <p class="text-[11px] text-slate-500 mt-1">Anonymisierte Daily Hashes</p>
        </div>
        <div class="p-5 bg-[#0f172a] border border-[#1e293b] rounded-2xl shadow-lg">
            <span class="text-xs font-semibold text-slate-400 block mb-1">Heute Aufrufe</span>
            <div class="text-2xl font-black text-emerald-400 tracking-tight">{$todayViews}</div>
            <p class="text-[11px] text-slate-500 mt-1">Aktueller Kalendertag</p>
        </div>
        <div class="p-5 bg-[#0f172a] border border-[#1e293b] rounded-2xl shadow-lg">
            <span class="text-xs font-semibold text-slate-400 block mb-1">Heute Besucher</span>
            <div class="text-2xl font-black text-blue-400 tracking-tight">{$todayUniques}</div>
            <p class="text-[11px] text-slate-500 mt-1">Eindeutige Besucher heute</p>
        </div>
    </div>

    <!-- Trend Visualizer -->
    <div class="bg-[#0f172a] border border-[#1e293b] rounded-2xl p-6 shadow-xl">
        <h3 class="text-sm font-bold text-white mb-6">Aufruf-Trend ({$days} Tage)</h3>
        <div class="h-44 flex items-end space-x-1 sm:space-x-2 pt-4 border-b border-[#1e293b]">
            {$chartBars}
        </div>
    </div>

    <!-- Two-column tables: Top Pages & Top Referrers -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Top Pages -->
        <div class="bg-[#0f172a] border border-[#1e293b] rounded-2xl p-6 shadow-xl">
            <h3 class="text-sm font-bold text-white mb-4">Meistbesuchte Seiten</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b border-[#1e293b] text-[11px] text-slate-400 uppercase">
                            <th class="py-2 px-4">Pfad / URL</th>
                            <th class="py-2 px-4 text-right">Aufrufe</th>
                            <th class="py-2 px-4 text-right">Besucher</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$topPagesHtml}
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Devices & Referrers -->
        <div class="space-y-6">
            <!-- Devices -->
            <div class="bg-[#0f172a] border border-[#1e293b] rounded-2xl p-6 shadow-xl">
                <h3 class="text-sm font-bold text-white mb-4">Geräte-Verteilung</h3>
                <div class="space-y-3">
                    <div>
                        <div class="flex justify-between text-xs mb-1">
                            <span class="text-slate-300">🖥️ Desktop ({$devs['desktop']})</span>
                            <span class="text-white font-bold">{$desktopPct}%</span>
                        </div>
                        <div class="w-full bg-[#090d16] rounded-full h-2">
                            <div class="bg-orange-500 h-2 rounded-full" style="width: {$desktopPct}%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between text-xs mb-1">
                            <span class="text-slate-300">📱 Mobile ({$devs['mobile']})</span>
                            <span class="text-white font-bold">{$mobilePct}%</span>
                        </div>
                        <div class="w-full bg-[#090d16] rounded-full h-2">
                            <div class="bg-blue-500 h-2 rounded-full" style="width: {$mobilePct}%"></div>
                        </div>
                    </div>
                    <div>
                        <div class="flex justify-between text-xs mb-1">
                            <span class="text-slate-300">📟 Tablet ({$devs['tablet']})</span>
                            <span class="text-white font-bold">{$tabletPct}%</span>
                        </div>
                        <div class="w-full bg-[#090d16] rounded-full h-2">
                            <div class="bg-purple-500 h-2 rounded-full" style="width: {$tabletPct}%"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Referrers -->
            <div class="bg-[#0f172a] border border-[#1e293b] rounded-2xl p-6 shadow-xl">
                <h3 class="text-sm font-bold text-white mb-4">Top Herkunftsquellen (Referrer)</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b border-[#1e293b] text-[11px] text-slate-400 uppercase">
                                <th class="py-2 px-4">Domain / Quelle</th>
                                <th class="py-2 px-4 text-right">Aufrufe</th>
                            </tr>
                        </thead>
                        <tbody>
                            {$referrersHtml}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
HTML;

        return Response::html(AdminView::renderShell('DSGVO Analytics', 'analytics', $contentHtml, [], $actionsHtml));
    }

    private function filterBtnClass(int $current, int $target): string
    {
        return $current === $target
            ? 'bg-orange-500 text-white font-bold'
            : 'bg-[#141e33] text-slate-400 hover:text-white border border-[#1e293b]';
    }

    // =========================================================================
    // 2. SEO ENGINE & AI ASSISTANT
    // =========================================================================

    public function seo(Request $request): Response
    {
        $redirect = $this->requireAuth('manage_options');
        if ($redirect) return $redirect;

        $aiService = AiService::getInstance();

        if ($request->getMethod() === 'POST') {
            if (!Csrf::verifyRequest($request)) {
                Session::flash('error', 'Ungültiges CSRF-Token.');
                return Response::redirect('/admin/seo');
            }

            // Save SEO defaults
            $this->updateOption('seo_default_title', trim((string)$request->post('seo_default_title', '')));
            $this->updateOption('seo_default_description', trim((string)$request->post('seo_default_description', '')));
            $this->updateOption('seo_default_og_image', trim((string)$request->post('seo_default_og_image', '')));

            // Save AI Settings
            $aiService->saveSettings([
                'provider' => trim((string)$request->post('ai_provider', 'gemini')),
                'api_key'  => trim((string)$request->post('ai_api_key', '')),
                'model'    => trim((string)$request->post('ai_model', '')),
            ]);

            Session::flash('success', 'SEO- & AI-Einstellungen wurden erfolgreich gespeichert.');
            return Response::redirect('/admin/seo');
        }

        $seoTitle = Security::e((string)$this->db->fetchColumn("SELECT option_value FROM {prefix}options WHERE option_name = 'seo_default_title'") ?: '%title% - %sitename%');
        $seoDesc = Security::e((string)$this->db->fetchColumn("SELECT option_value FROM {prefix}options WHERE option_name = 'seo_default_description'") ?: '');
        $ogImage = Security::e((string)$this->db->fetchColumn("SELECT option_value FROM {prefix}options WHERE option_name = 'seo_default_og_image'") ?: '');

        $aiSettings = $aiService->getSettings();
        $aiProvider = Security::e($aiSettings['provider']);
        $aiApiKey = Security::e($aiSettings['api_key']);
        $aiModel = Security::e($aiSettings['model']);

        $csrfField = $this->csrfField();

        $contentHtml = <<<HTML
<div class="max-w-4xl space-y-6" x-data="{ tab: 'seo' }">
    <!-- Tabs Header -->
    <div class="flex border-b border-[#1e293b] space-x-2">
        <button @click="tab = 'seo'" :class="tab === 'seo' ? 'border-[#f97316] text-white font-bold' : 'border-transparent text-slate-400 hover:text-slate-200'"
                class="py-3 px-4 border-b-2 text-xs font-medium transition">
            SEO &amp; OpenGraph Standardwerte
        </button>
        <button @click="tab = 'sitemap'" :class="tab === 'sitemap' ? 'border-[#f97316] text-white font-bold' : 'border-transparent text-slate-400 hover:text-slate-200'"
                class="py-3 px-4 border-b-2 text-xs font-medium transition">
            XML Sitemap
        </button>
        <button @click="tab = 'ai'" :class="tab === 'ai' ? 'border-[#f97316] text-white font-bold' : 'border-transparent text-slate-400 hover:text-slate-200'"
                class="py-3 px-4 border-b-2 text-xs font-medium transition flex items-center space-x-1.5">
            <span>AI Copy &amp; Meta Assistant</span>
            <span class="text-[10px] px-1.5 py-0.5 rounded bg-orange-500/10 text-orange-400 border border-orange-500/20">Gemini/OpenAI</span>
        </button>
    </div>

    <form method="POST" action="/admin/seo" class="bg-[#0f172a] border border-[#1e293b] rounded-2xl p-6 sm:p-8 shadow-xl space-y-6">
        {$csrfField}

        <!-- Tab 1: SEO -->
        <div x-show="tab === 'seo'" class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1">Standard Titel-Template</label>
                <input type="text" name="seo_default_title" value="{$seoTitle}" required
                       class="w-full px-3.5 py-2.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white focus:outline-none focus:border-[#f97316]">
                <span class="text-[11px] text-slate-500 mt-1 block">Variablen: %title%, %sitename%</span>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1">Globale Meta-Beschreibung (Fallback)</label>
                <textarea name="seo_default_description" rows="3"
                          class="w-full px-3.5 py-2.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white focus:outline-none focus:border-[#f97316]">{$seoDesc}</textarea>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1">Standard Social Share Bild (OpenGraph &amp; Twitter)</label>
                <input type="text" name="seo_default_og_image" value="{$ogImage}" placeholder="/uploads/og-default.jpg"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white focus:outline-none focus:border-[#f97316]">
                <span class="text-[11px] text-slate-500 mt-1 block">Empfohlene Auflösung: 1200 x 630 Pixel.</span>
            </div>
        </div>

        <!-- Tab 2: Sitemap -->
        <div x-show="tab === 'sitemap'" class="space-y-4">
            <div class="p-5 rounded-xl bg-[#090d16] border border-[#1e293b] flex items-center justify-between">
                <div>
                    <h4 class="text-sm font-bold text-white mb-1">Dynamische XML-Sitemap aktiv</h4>
                    <p class="text-xs text-slate-400">Automatische Erfassung aller veröffentlichten Seiten, Beiträge und Custom Post Types.</p>
                </div>
                <a href="/sitemap.xml" target="_blank" class="px-4 py-2 rounded-xl bg-[#141e33] hover:bg-[#1e293b] border border-[#1e293b] text-xs font-semibold text-white transition flex items-center space-x-1.5">
                    <span>Sitemap ansehen</span>
                    <svg class="w-3.5 h-3.5 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
                </a>
            </div>
        </div>

        <!-- Tab 3: AI Assistant -->
        <div x-show="tab === 'ai'" class="space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">AI-Anbieter</label>
                    <select name="ai_provider" class="w-full px-3.5 py-2.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white focus:outline-none focus:border-[#f97316]">
                        <option value="gemini" {$this->selected($aiProvider, 'gemini')}>Google Gemini (Empfohlen &amp; Kosteneffizient)</option>
                        <option value="openai" {$this->selected($aiProvider, 'openai')}>OpenAI (GPT-4o / GPT-4o-mini)</option>
                        <option value="anthropic" {$this->selected($aiProvider, 'anthropic')}>Anthropic Claude (Haiku / Sonnet)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Modell-Identifier</label>
                    <input type="text" name="ai_model" value="{$aiModel}" placeholder="z.B. gemini-1.5-flash oder gpt-4o-mini"
                           class="w-full px-3.5 py-2.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white focus:outline-none focus:border-[#f97316]">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1">API-Schlüssel</label>
                <input type="password" name="ai_api_key" value="{$aiApiKey}" placeholder="••••••••••••••••••••••••••••••••"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white focus:outline-none focus:border-[#f97316]">
                <span class="text-[11px] text-slate-500 mt-1 block">Wird sicher in der Datenbank gespeichert und ausschließlich server-seitig via cURL verwendet.</span>
            </div>
        </div>

        <div class="flex items-center justify-end space-x-3 pt-6 border-t border-[#1e293b]">
            <button type="submit" class="px-5 py-2.5 rounded-xl bg-[#f97316] hover:bg-[#ea580c] text-white text-xs font-semibold shadow-lg shadow-orange-500/20">
                Einstellungen speichern
            </button>
        </div>
    </form>
</div>
HTML;

        return Response::html(AdminView::renderShell('Marketing &amp; SEO', 'seo', $contentHtml));
    }

    /**
     * Interactive AI generation endpoint (POST JSON)
     */
    public function generateAiContent(Request $request): Response
    {
        $redirect = $this->requireAuth('manage_content');
        if ($redirect) return $redirect;

        $prompt = trim((string)$request->post('prompt', ''));
        $context = trim((string)$request->post('context', ''));
        $type = (string)$request->post('type', 'copy');

        $ai = AiService::getInstance();

        if ($type === 'seo') {
            $result = $ai->generateSeoMeta($prompt, $context);
            return Response::json(['success' => true, 'data' => $result]);
        }

        $text = $ai->generateCopy($prompt, $context);
        return Response::json([
            'success' => $text !== '',
            'text'    => $text,
            'error'   => $ai->getLastError(),
        ]);
    }

    // =========================================================================
    // 3. TRANSACTIONAL MAILER
    // =========================================================================

    public function mail(Request $request): Response
    {
        $redirect = $this->requireAuth('manage_options');
        if ($redirect) return $redirect;

        $mailer = Mailer::getInstance();

        if ($request->getMethod() === 'POST') {
            if (!Csrf::verifyRequest($request)) {
                Session::flash('error', 'Ungültiges CSRF-Token.');
                return Response::redirect('/admin/mail');
            }

            // Save Settings
            $mailer->saveSettings([
                'mail_driver'     => trim((string)$request->post('mail_driver', 'resend')),
                'mail_from_name'  => trim((string)$request->post('mail_from_name', 'Heartbeat CMS')),
                'mail_from_email' => trim((string)$request->post('mail_from_email', 'info@example.com')),
                'mail_reply_to'   => trim((string)$request->post('mail_reply_to', '')),
                'resend_api_key'  => trim((string)$request->post('resend_api_key', '')),
                'smtp_host'       => trim((string)$request->post('smtp_host', '')),
                'smtp_port'       => (int)$request->post('smtp_port', 587),
                'smtp_encryption' => trim((string)$request->post('smtp_encryption', 'tls')),
                'smtp_user'       => trim((string)$request->post('smtp_user', '')),
                'smtp_pass'       => (string)$request->post('smtp_pass', ''),
            ]);

            Session::flash('success', 'E-Mail-Einstellungen erfolgreich gespeichert.');
            return Response::redirect('/admin/mail');
        }

        $s = $mailer->getSettings();
        $driver = Security::e((string)$s['mail_driver']);
        $fromName = Security::e((string)$s['mail_from_name']);
        $fromEmail = Security::e((string)$s['mail_from_email']);
        $replyTo = Security::e((string)$s['mail_reply_to']);
        $resendKey = Security::e((string)$s['resend_api_key']);
        $smtpHost = Security::e((string)$s['smtp_host']);
        $smtpPort = (int)$s['smtp_port'];
        $smtpEnc = Security::e((string)$s['smtp_encryption']);
        $smtpUser = Security::e((string)$s['smtp_user']);
        $smtpPass = Security::e((string)$s['smtp_pass']);

        $csrfField = $this->csrfField();

        $contentHtml = <<<HTML
<div class="max-w-4xl space-y-6" x-data="{ driver: '{$driver}', testLoading: false, testResult: null }">
    <form method="POST" action="/admin/mail" class="bg-[#0f172a] border border-[#1e293b] rounded-2xl p-6 sm:p-8 shadow-xl space-y-6">
        {$csrfField}

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1">Standard Versand-Treiber</label>
                <select name="mail_driver" x-model="driver" class="w-full px-3.5 py-2.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white focus:outline-none focus:border-[#f97316]">
                    <option value="resend">Resend REST API (Empfohlen für Agenturen &amp; Kunden)</option>
                    <option value="smtp">Nativer SMTP-Treiber (Shared Hosting Fallback)</option>
                    <option value="log">Lokaler Log-Treiber (storage/logs/mail.log)</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1">Absender-Name</label>
                <input type="text" name="mail_from_name" value="{$fromName}" required
                       class="w-full px-3.5 py-2.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white focus:outline-none focus:border-[#f97316]">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1">Absender E-Mail</label>
                <input type="email" name="mail_from_email" value="{$fromEmail}" required
                       class="w-full px-3.5 py-2.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white focus:outline-none focus:border-[#f97316]">
                <span class="text-[11px] text-slate-500 mt-1 block">Bei Resend muss die Domain verifiziert sein.</span>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1">Antwort an (Reply-To, optional)</label>
                <input type="email" name="mail_reply_to" value="{$replyTo}"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white focus:outline-none focus:border-[#f97316]">
            </div>
        </div>

        <!-- Driver: Resend Section -->
        <div x-show="driver === 'resend'" class="p-5 rounded-xl bg-[#090d16] border border-[#1e293b] space-y-3">
            <h4 class="text-xs font-bold text-white uppercase tracking-wider">Resend REST API Konfiguration</h4>
            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1">Resend API Key</label>
                <input type="password" name="resend_api_key" value="{$resendKey}" placeholder="re_••••••••••••••••••••"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-[#0f172a] border border-[#1e293b] text-xs text-white focus:outline-none focus:border-[#f97316]">
            </div>
        </div>

        <!-- Driver: SMTP Section -->
        <div x-show="driver === 'smtp'" class="p-5 rounded-xl bg-[#090d16] border border-[#1e293b] space-y-4">
            <h4 class="text-xs font-bold text-white uppercase tracking-wider">Nativer SMTP-Server (Zero-Exec)</h4>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-300 mb-1">SMTP-Host</label>
                    <input type="text" name="smtp_host" value="{$smtpHost}" placeholder="smtp.webgo.de oder smtp.ionos.de"
                           class="w-full px-3.5 py-2.5 rounded-xl bg-[#0f172a] border border-[#1e293b] text-xs text-white focus:outline-none focus:border-[#f97316]">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Port</label>
                    <input type="number" name="smtp_port" value="{$smtpPort}"
                           class="w-full px-3.5 py-2.5 rounded-xl bg-[#0f172a] border border-[#1e293b] text-xs text-white focus:outline-none focus:border-[#f97316]">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Verschlüsselung</label>
                    <select name="smtp_encryption" class="w-full px-3.5 py-2.5 rounded-xl bg-[#0f172a] border border-[#1e293b] text-xs text-white focus:outline-none focus:border-[#f97316]">
                        <option value="tls" {$this->selected($smtpEnc, 'tls')}>STARTTLS (Port 587)</option>
                        <option value="ssl" {$this->selected($smtpEnc, 'ssl')}>SSL/TLS (Port 465)</option>
                        <option value="none" {$this->selected($smtpEnc, 'none')}>Keine (Port 25)</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">SMTP-Benutzername</label>
                    <input type="text" name="smtp_user" value="{$smtpUser}"
                           class="w-full px-3.5 py-2.5 rounded-xl bg-[#0f172a] border border-[#1e293b] text-xs text-white focus:outline-none focus:border-[#f97316]">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">SMTP-Passwort</label>
                    <input type="password" name="smtp_pass" value="{$smtpPass}"
                           class="w-full px-3.5 py-2.5 rounded-xl bg-[#0f172a] border border-[#1e293b] text-xs text-white focus:outline-none focus:border-[#f97316]">
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end space-x-3 pt-6 border-t border-[#1e293b]">
            <button type="submit" class="px-5 py-2.5 rounded-xl bg-[#f97316] hover:bg-[#ea580c] text-white text-xs font-semibold shadow-lg shadow-orange-500/20">
                Einstellungen speichern
            </button>
        </div>
    </form>

    <!-- Test Email Diagnostic Card -->
    <div class="bg-[#0f172a] border border-[#1e293b] rounded-2xl p-6 sm:p-8 shadow-xl">
        <h3 class="text-sm font-bold text-white mb-2">Live-Diagnose: Test-E-Mail versenden</h3>
        <p class="text-xs text-slate-400 mb-4">Senden Sie eine Testnachricht, um die SMTP- bzw. Resend-Verbindung auf Herz und Nieren zu prüfen.</p>
        
        <form method="POST" action="/admin/mail/test" class="flex flex-col sm:flex-row items-center gap-3">
            {$csrfField}
            <input type="email" name="test_recipient" placeholder="empfaenger@beispiel.de" required
                   class="w-full sm:w-80 px-3.5 py-2.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white focus:outline-none focus:border-[#f97316]">
            <button type="submit" class="w-full sm:w-auto px-5 py-2.5 rounded-xl bg-[#141e33] hover:bg-[#1e293b] border border-[#1e293b] text-slate-200 text-xs font-semibold transition flex items-center space-x-2">
                <span>Test-E-Mail senden &rarr;</span>
            </button>
        </form>
    </div>
</div>
HTML;

        return Response::html(AdminView::renderShell('Transaktions-Mail', 'mail', $contentHtml));
    }

    public function sendTestMail(Request $request): Response
    {
        $redirect = $this->requireAuth('manage_options');
        if ($redirect) return $redirect;

        if (!Csrf::verifyRequest($request)) {
            Session::flash('error', 'Ungültiges CSRF-Token.');
            return Response::redirect('/admin/mail');
        }

        $recipient = trim((string)$request->post('test_recipient', ''));
        if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            Session::flash('error', 'Ungültige Empfänger-E-Mail-Adresse.');
            return Response::redirect('/admin/mail');
        }

        $res = Mailer::getInstance()->sendTest($recipient);
        if ($res['success']) {
            Session::flash('success', $res['message']);
        } else {
            Session::flash('error', $res['message']);
        }

        return Response::redirect('/admin/mail');
    }

    // =========================================================================
    // 4. ZERO-EXEC BACKUPS
    // =========================================================================

    public function backups(Request $request): Response
    {
        $redirect = $this->requireAuth('manage_backups');
        if ($redirect) return $redirect;

        $backupService = BackupService::getInstance();
        $backups = $backupService->listBackups();
        $cronToken = $backupService->getCronToken();
        $siteUrl = rtrim((string)$this->db->fetchColumn("SELECT option_value FROM {prefix}options WHERE option_name = 'site_url'"), '/');
        $cronUrl = "{$siteUrl}/api/backup/cron?token={$cronToken}";

        $csrfField = $this->csrfField();

        $rowsHtml = '';
        if (empty($backups)) {
            $rowsHtml = '<tr><td colspan="4" class="py-6 text-center text-xs text-slate-500">Noch keine Datensicherungen vorhanden. Erstellen Sie jetzt Ihr erstes Backup.</td></tr>';
        } else {
            foreach ($backups as $b) {
                $fname = Security::e($b['filename']);
                $size = Security::e($b['size_formatted']);
                $date = Security::e($b['created_at']);
                $downUrl = Security::e($b['download_url']);

                $rowsHtml .= <<<HTML
<tr class="border-b border-[#1e293b] hover:bg-[#141e33]/50 transition">
    <td class="py-3 px-4 text-xs font-mono text-white flex items-center space-x-2">
        <svg class="w-4 h-4 text-orange-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"></path></svg>
        <span>{$fname}</span>
    </td>
    <td class="py-3 px-4 text-xs text-slate-400 font-mono">{$size}</td>
    <td class="py-3 px-4 text-xs text-slate-400">{$date}</td>
    <td class="py-3 px-4 text-xs text-right space-x-2">
        <a href="{$downUrl}" class="px-3 py-1.5 rounded-lg bg-[#141e33] hover:bg-[#1e293b] text-slate-200 text-xs font-medium transition inline-block">
            Download
        </a>
        <form method="POST" action="/admin/backups/delete/{$fname}" class="inline-block" onsubmit="return confirm('Soll dieses Backup wirklich gelöscht werden?');">
            {$csrfField}
            <button type="submit" class="px-3 py-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 text-xs font-medium transition">
                Löschen
            </button>
        </form>
    </td>
</tr>
HTML;
            }
        }

        $actionsHtml = <<<HTML
<form method="POST" action="/admin/backups/create" class="flex items-center space-x-2">
    {$csrfField}
    <input type="hidden" name="type" value="full">
    <button type="submit" class="px-4 py-2.5 rounded-xl bg-[#f97316] hover:bg-[#ea580c] text-white text-xs font-semibold shadow-lg shadow-orange-500/20 transition flex items-center space-x-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
        <span>1-Klick Backup erstellen</span>
    </button>
</form>
HTML;

        $contentHtml = <<<HTML
<div class="space-y-6">
    <!-- Zero-Exec Notice -->
    <div class="p-4 rounded-2xl bg-orange-500/10 border border-orange-500/20 text-orange-400 text-xs flex items-center space-x-3">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
        <span><strong>Zero-Exec Technologie:</strong> Backups werden via PDO-Tabellen-Streaming und PHP ZipArchive erzeugt. Funktioniert auf jedem Shared Hosting ohne SSH, exec() oder mysqldump.</span>
    </div>

    <!-- Backups List Table -->
    <div class="bg-[#0f172a] border border-[#1e293b] rounded-2xl shadow-xl overflow-hidden">
        <div class="p-6 border-b border-[#1e293b] flex items-center justify-between">
            <h3 class="text-sm font-bold text-white">Gespeicherte Datensicherungen</h3>
            <span class="text-xs text-slate-400 font-mono">Automatischer Vorrat: 5 Backups</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-[#1e293b] text-[11px] text-slate-400 uppercase bg-[#090d16]">
                        <th class="py-3 px-4">Archiv-Datei</th>
                        <th class="py-3 px-4">Größe</th>
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

    <!-- Cron Link Card -->
    <div class="bg-[#0f172a] border border-[#1e293b] rounded-2xl p-6 shadow-xl">
        <h3 class="text-sm font-bold text-white mb-2">Automatisierte Cronjob-Sicherung</h3>
        <p class="text-xs text-slate-400 mb-4">Richten Sie bei Ihrem Webhoster einen periodischen Web-Aufruf (GET) auf folgende gesicherte URL ein:</p>
        <div class="flex items-center space-x-3 p-3 rounded-xl bg-[#090d16] border border-[#1e293b] font-mono text-xs text-slate-300 overflow-x-auto">
            <span class="text-orange-400 font-bold select-none">URL:</span>
            <span class="select-all">{$cronUrl}</span>
        </div>
    </div>
</div>
HTML;

        return Response::html(AdminView::renderShell('Datensicherung &amp; Backups', 'backups', $contentHtml, [], $actionsHtml));
    }

    public function createBackupAction(Request $request): Response
    {
        $redirect = $this->requireAuth('manage_backups');
        if ($redirect) return $redirect;

        if (!Csrf::verifyRequest($request)) {
            Session::flash('error', 'Ungültiges CSRF-Token.');
            return Response::redirect('/admin/backups');
        }

        $type = (string)$request->post('type', 'full');
        $includeDb = ($type === 'full' || $type === 'db');
        $includeFiles = ($type === 'full' || $type === 'files');

        $res = BackupService::getInstance()->createBackup($includeDb, $includeFiles);
        if ($res['success']) {
            Session::flash('success', $res['message']);
        } else {
            Session::flash('error', $res['message']);
        }

        return Response::redirect('/admin/backups');
    }

    public function downloadBackup(Request $request, array $params): Response
    {
        $redirect = $this->requireAuth('manage_backups');
        if ($redirect) return $redirect;

        $filename = basename((string)($params['file'] ?? ''));
        $filepath = HEARTBEAT_STORAGE . '/backups/' . $filename;

        if (!is_file($filepath) || !str_ends_with($filename, '.zip')) {
            Session::flash('error', 'Datensicherung nicht gefunden.');
            return Response::redirect('/admin/backups');
        }

        $content = (string)file_get_contents($filepath);
        return new Response($content, 200, [
            'Content-Type'        => 'application/zip',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Content-Length'      => (string)filesize($filepath),
        ]);
    }

    public function deleteBackupAction(Request $request, array $params): Response
    {
        $redirect = $this->requireAuth('manage_backups');
        if ($redirect) return $redirect;

        if (!Csrf::verifyRequest($request)) {
            Session::flash('error', 'Ungültiges CSRF-Token.');
            return Response::redirect('/admin/backups');
        }

        $filename = (string)($params['file'] ?? '');
        if (BackupService::getInstance()->deleteBackup($filename)) {
            Session::flash('success', "Datensicherung {$filename} gelöscht.");
        } else {
            Session::flash('error', 'Fehler beim Löschen der Datensicherung.');
        }

        return Response::redirect('/admin/backups');
    }

    // =========================================================================
    // 5. 1-CLICK OTA CORE UPDATER
    // =========================================================================

    public function updater(Request $request): Response
    {
        $redirect = $this->requireAuth('update_core');
        if ($redirect) return $redirect;

        $updater = UpdaterService::getInstance();
        $config = $updater->getConfig();

        if ($request->getMethod() === 'POST') {
            if (!Csrf::verifyRequest($request)) {
                Session::flash('error', 'Ungültiges CSRF-Token.');
                return Response::redirect('/admin/updater');
            }

            $updater->saveConfig(
                (string)$request->post('updater_repo', 'heartbeat-cms/heartbeat'),
                (string)$request->post('updater_github_token', ''),
                (string)$request->post('updater_channel', 'release')
            );

            Session::flash('success', 'Updater-Einstellungen gespeichert.');
            return Response::redirect('/admin/updater');
        }

        $updateInfo = $updater->checkForUpdates();
        $currentVer = Security::e(HEARTBEAT_VERSION);
        $latestVer = Security::e($updateInfo['latest_version']);
        $hasUpdate = $updateInfo['has_update'];
        $notes = Security::e($updateInfo['release_notes']);
        $repo = Security::e($config['repo']);
        $token = Security::e($config['token']);

        $csrfField = $this->csrfField();

        $statusBadge = $hasUpdate
            ? '<span class="px-3 py-1 rounded-full bg-orange-500/20 text-orange-400 font-bold text-xs border border-orange-500/30">Update verfügbar!</span>'
            : '<span class="px-3 py-1 rounded-full bg-emerald-500/20 text-emerald-400 font-bold text-xs border border-emerald-500/30">System auf dem neuesten Stand</span>';

        $updateButtonHtml = '';
        if ($hasUpdate) {
            $updateButtonHtml = <<<HTML
<form method="POST" action="/admin/updater/apply" class="mt-4" onsubmit="return confirm('Möchten Sie das Update jetzt durchführen? Es wird automatisch ein Sicherheits-Backup erstellt.');">
    {$csrfField}
    <input type="hidden" name="download_url" value="{$updateInfo['download_url']}">
    <button type="submit" class="px-6 py-3 rounded-xl bg-[#f97316] hover:bg-[#ea580c] text-white text-xs font-bold shadow-lg shadow-orange-500/25 transition">
        1-Klick Update auf v{$latestVer} starten &rarr;
    </button>
</form>
HTML;
        }

        $hasUpdateStr = $hasUpdate ? 'true' : 'false';

        $contentHtml = <<<HTML
<div class="max-w-4xl space-y-6">
    <!-- Version Card -->
    <div class="bg-[#0f172a] border border-[#1e293b] rounded-2xl p-6 sm:p-8 shadow-xl flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
            <div class="flex items-center space-x-3 mb-2">
                <h3 class="text-xl font-bold text-white">Heartbeat CMS v{$currentVer}</h3>
                {$statusBadge}
            </div>
            <p class="text-xs text-slate-400 max-w-xl">
                Neueste verfügbare Version: <strong class="text-white">v{$latestVer}</strong> &bull; Kanal: Offizielle GitHub Releases
            </p>
            {$updateButtonHtml}
        </div>
        <div class="p-4 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs space-y-1.5 flex-shrink-0">
            <div class="text-slate-400">Automatische Sicherheit:</div>
            <div class="text-emerald-400 font-medium">✓ DB- &amp; Core-Snapshot vorab</div>
            <div class="text-emerald-400 font-medium">✓ Preserves /config &amp; /uploads</div>
            <div class="text-emerald-400 font-medium">✓ Auto-Migration &amp; Cache-Purge</div>
        </div>
    </div>

    <!-- Release Notes if update available -->
    <div x-show="{$hasUpdateStr}" class="bg-[#0f172a] border border-[#1e293b] rounded-2xl p-6 shadow-xl">
        <h4 class="text-sm font-bold text-white mb-2">Versionshinweise (Changelog)</h4>
        <div class="p-4 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-slate-300 font-mono whitespace-pre-wrap max-h-60 overflow-y-auto">
            {$notes}
        </div>
    </div>

    <!-- Settings Card -->
    <form method="POST" action="/admin/updater" class="bg-[#0f172a] border border-[#1e293b] rounded-2xl p-6 sm:p-8 shadow-xl space-y-4">
        {$csrfField}
        <h4 class="text-sm font-bold text-white">Repository- &amp; Zugangsdaten</h4>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1">GitHub Repository (Owner/Repo)</label>
                <input type="text" name="updater_repo" value="{$repo}" required
                       class="w-full px-3.5 py-2.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white focus:outline-none focus:border-[#f97316]">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1">GitHub Personal Access Token (für private Repos)</label>
                <input type="password" name="updater_github_token" value="{$token}" placeholder="ghp_••••••••••••••••••••••••••••••••••••"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white focus:outline-none focus:border-[#f97316]">
            </div>
        </div>
        <div class="flex items-center justify-end pt-4 border-t border-[#1e293b]">
            <button type="submit" class="px-5 py-2.5 rounded-xl bg-[#141e33] hover:bg-[#1e293b] text-slate-200 text-xs font-semibold transition border border-[#1e293b]">
                Updater-Einstellungen speichern
            </button>
        </div>
    </form>
</div>
HTML;

        return Response::html(AdminView::renderShell('System-Updater', 'updater', $contentHtml));
    }

    public function applyUpdate(Request $request): Response
    {
        $redirect = $this->requireAuth('update_core');
        if ($redirect) return $redirect;

        if (!Csrf::verifyRequest($request)) {
            Session::flash('error', 'Ungültiges CSRF-Token.');
            return Response::redirect('/admin/updater');
        }

        $downloadUrl = (string)$request->post('download_url', '');
        $res = UpdaterService::getInstance()->runUpdate($downloadUrl);

        if ($res['success']) {
            Session::flash('success', $res['message']);
        } else {
            Session::flash('error', $res['message']);
        }

        return Response::redirect('/admin/updater');
    }

    // =========================================================================
    // 6. GDPR TOOLS & LOCAL GOOGLE FONTS DOWNLOADER
    // =========================================================================

    public function gdpr(Request $request): Response
    {
        $redirect = $this->requireAuth('manage_options');
        if ($redirect) return $redirect;

        $fontsDownloader = GoogleFontsDownloader::getInstance();
        $localFonts = $fontsDownloader->listLocalFonts();

        $csrfField = $this->csrfField();

        $fontsRowsHtml = '';
        if (empty($localFonts)) {
            $fontsRowsHtml = '<tr><td colspan="3" class="py-6 text-center text-xs text-slate-500">Noch keine lokalen Schriftarten installiert. Fügen Sie oben eine Google Fonts URL hinzu.</td></tr>';
        } else {
            foreach ($localFonts as $lf) {
                $slug = Security::e($lf['slug']);
                $url = Security::e($lf['css_url']);
                $mod = Security::e($lf['modified']);

                $fontsRowsHtml .= <<<HTML
<tr class="border-b border-[#1e293b] hover:bg-[#141e33]/50 transition">
    <td class="py-3 px-4 text-xs font-bold text-white capitalize">{$slug}</td>
    <td class="py-3 px-4 text-xs font-mono text-orange-400">&lt;link rel="stylesheet" href="{$url}"&gt;</td>
    <td class="py-3 px-4 text-xs text-slate-400">{$mod}</td>
</tr>
HTML;
            }
        }

        $contentHtml = <<<HTML
<div class="max-w-4xl space-y-6" x-data="{ tab: 'fonts' }">
    <!-- Tabs Header -->
    <div class="flex border-b border-[#1e293b] space-x-2">
        <button @click="tab = 'fonts'" :class="tab === 'fonts' ? 'border-[#f97316] text-white font-bold' : 'border-transparent text-slate-400 hover:text-slate-200'"
                class="py-3 px-4 border-b-2 text-xs font-medium transition">
            Google Fonts Downloader
        </button>
        <button @click="tab = 'rights'" :class="tab === 'rights' ? 'border-[#f97316] text-white font-bold' : 'border-transparent text-slate-400 hover:text-slate-200'"
                class="py-3 px-4 border-b-2 text-xs font-medium transition">
            DSGVO Betroffenenrechte (Art. 15 / 17)
        </button>
    </div>

    <!-- Tab 1: Google Fonts Downloader -->
    <div x-show="tab === 'fonts'" class="space-y-6">
        <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs flex items-center space-x-3">
            <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
            <span><strong>100% Abmahnsicher:</strong> Keine dynamischen Anfragen an US-Server von Google Fonts. Alle WOFF2-Dateien und @font-face CSS-Dateien werden lokal auf Ihrem Server gehostet.</span>
        </div>

        <!-- Download Input Card -->
        <form method="POST" action="/admin/gdpr/download-font" class="bg-[#0f172a] border border-[#1e293b] rounded-2xl p-6 sm:p-8 shadow-xl space-y-4">
            {$csrfField}
            <h4 class="text-sm font-bold text-white">Neue Schriftart lokal herunterladen</h4>
            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1">Google Fonts CSS URL oder Schriftfamilien-Name</label>
                <div class="flex flex-col sm:flex-row gap-3">
                    <input type="text" name="font_input" placeholder="z.B. Plus Jakarta Sans oder https://fonts.googleapis.com/css2?family=Poppins:wght@400;600&display=swap" required
                           class="flex-1 px-3.5 py-2.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white focus:outline-none focus:border-[#f97316]">
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-[#f97316] hover:bg-[#ea580c] text-white text-xs font-semibold shadow-lg shadow-orange-500/20 transition whitespace-nowrap">
                        Herunterladen &amp; Hosten
                    </button>
                </div>
            </div>
        </form>

        <!-- Installed Local Fonts Table -->
        <div class="bg-[#0f172a] border border-[#1e293b] rounded-2xl shadow-xl overflow-hidden">
            <div class="p-6 border-b border-[#1e293b]">
                <h4 class="text-sm font-bold text-white">Lokal gehostete Schriftarten</h4>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b border-[#1e293b] text-[11px] text-slate-400 uppercase bg-[#090d16]">
                            <th class="py-3 px-4">Schriftart</th>
                            <th class="py-3 px-4">Lokale CSS-Einbindung</th>
                            <th class="py-3 px-4">Zuletzt aktualisiert</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$fontsRowsHtml}
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Tab 2: GDPR User Rights -->
    <div x-show="tab === 'rights'" class="space-y-6">
        <div class="bg-[#0f172a] border border-[#1e293b] rounded-2xl p-6 sm:p-8 shadow-xl space-y-4">
            <h4 class="text-sm font-bold text-white mb-2">Benutzerdaten-Export (DSGVO Art. 15 / 20)</h4>
            <p class="text-xs text-slate-400">Erstellen Sie ein vollständiges JSON-Archiv mit allen gespeicherten personenbezogenen Daten eines Benutzers.</p>
            <form method="POST" action="/admin/gdpr/export-user" class="flex flex-col sm:flex-row gap-3">
                {$csrfField}
                <input type="number" name="user_id" placeholder="Benutzer-ID" required
                       class="w-full sm:w-48 px-3.5 py-2.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white focus:outline-none focus:border-[#f97316]">
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-[#141e33] hover:bg-[#1e293b] border border-[#1e293b] text-slate-200 text-xs font-semibold transition">
                    JSON-Datenexport generieren
                </button>
            </form>
        </div>

        <div class="bg-[#0f172a] border border-[#1e293b] rounded-2xl p-6 sm:p-8 shadow-xl space-y-4">
            <h4 class="text-sm font-bold text-white mb-2">Recht auf Vergessenwerden / Anonymisierung (DSGVO Art. 17)</h4>
            <p class="text-xs text-slate-400">Anonymisiert Name, E-Mail und Passwörter eines Benutzers und löscht persönliche Metadaten, während verfasste Artikel im System erhalten bleiben.</p>
            <form method="POST" action="/admin/gdpr/anonymize-user" class="flex flex-col sm:flex-row gap-3" onsubmit="return confirm('Möchten Sie diesen Benutzer wirklich unwiderruflich anonymisieren?');">
                {$csrfField}
                <input type="number" name="user_id" placeholder="Benutzer-ID" required
                       class="w-full sm:w-48 px-3.5 py-2.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white focus:outline-none focus:border-[#f97316]">
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/30 text-xs font-semibold transition">
                    Benutzer jetzt anonymisieren
                </button>
            </form>
        </div>
    </div>
</div>
HTML;

        return Response::html(AdminView::renderShell('DSGVO &amp; Lokale Fonts', 'gdpr', $contentHtml));
    }

    public function downloadFontAction(Request $request): Response
    {
        $redirect = $this->requireAuth('manage_options');
        if ($redirect) return $redirect;

        if (!Csrf::verifyRequest($request)) {
            Session::flash('error', 'Ungültiges CSRF-Token.');
            return Response::redirect('/admin/gdpr');
        }

        $input = trim((string)$request->post('font_input', ''));
        $res = GoogleFontsDownloader::getInstance()->downloadFont($input);

        if ($res['success']) {
            Session::flash('success', $res['message']);
        } else {
            Session::flash('error', $res['message']);
        }

        return Response::redirect('/admin/gdpr');
    }

    public function exportUserAction(Request $request): Response
    {
        $redirect = $this->requireAuth('manage_options');
        if ($redirect) return $redirect;

        if (!Csrf::verifyRequest($request)) {
            Session::flash('error', 'Ungültiges CSRF-Token.');
            return Response::redirect('/admin/gdpr');
        }

        $userId = (int)$request->post('user_id', 0);
        $data = GdprService::getInstance()->exportUserData($userId);

        if (empty($data)) {
            Session::flash('error', 'Benutzer nicht gefunden.');
            return Response::redirect('/admin/gdpr');
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        return new Response((string)$json, 200, [
            'Content-Type'        => 'application/json; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"gdpr_export_user_{$userId}.json\"",
        ]);
    }

    public function anonymizeUserAction(Request $request): Response
    {
        $redirect = $this->requireAuth('manage_options');
        if ($redirect) return $redirect;

        if (!Csrf::verifyRequest($request)) {
            Session::flash('error', 'Ungültiges CSRF-Token.');
            return Response::redirect('/admin/gdpr');
        }

        $userId = (int)$request->post('user_id', 0);
        $success = GdprService::getInstance()->anonymizeUser($userId);

        if ($success) {
            Session::flash('success', "Benutzer-ID #{$userId} wurde erfolgreich nach DSGVO Art. 17 anonymisiert.");
        } else {
            Session::flash('error', 'Anonymisierung fehlgeschlagen (Benutzer nicht gefunden oder letzter Super-Administrator).');
        }

        return Response::redirect('/admin/gdpr');
    }

    // =========================================================================
    // 7. STRIPE & MEMBERSHIP PAYMENTS
    // =========================================================================

    public function payments(Request $request): Response
    {
        $redirect = $this->requireAuth('manage_options');
        if ($redirect) return $redirect;

        $stripe = StripeService::getInstance();

        if ($request->getMethod() === 'POST') {
            if (!Csrf::verifyRequest($request)) {
                Session::flash('error', 'Ungültiges CSRF-Token.');
                return Response::redirect('/admin/payments');
            }

            $stripe->saveSettings([
                'enabled'          => !empty($request->post('stripe_enabled')),
                'mode'             => trim((string)$request->post('stripe_mode', 'test')),
                'secret_key'       => trim((string)$request->post('stripe_secret_key', '')),
                'publishable_key'  => trim((string)$request->post('stripe_publishable_key', '')),
                'webhook_secret'   => trim((string)$request->post('stripe_webhook_secret', '')),
                'currency'         => trim((string)$request->post('stripe_currency', 'eur')),
            ]);

            Session::flash('success', 'Stripe-Einstellungen erfolgreich gespeichert.');
            return Response::redirect('/admin/payments');
        }

        $s = $stripe->getSettings();
        $enabled = $s['enabled'];
        $mode = Security::e((string)$s['mode']);
        $secretKey = Security::e((string)$s['secret_key']);
        $pubKey = Security::e((string)$s['publishable_key']);
        $webhookSecret = Security::e((string)$s['webhook_secret']);
        $currency = Security::e((string)$s['currency']);

        $siteUrl = rtrim((string)$this->db->fetchColumn("SELECT option_value FROM {prefix}options WHERE option_name = 'site_url'"), '/');
        $webhookEndpoint = "{$siteUrl}/api/stripe/webhook";

        $recentOrders = $stripe->getRecentOrders(10);
        $ordersRows = '';
        if (empty($recentOrders)) {
            $ordersRows = '<tr><td colspan="5" class="py-6 text-center text-xs text-slate-500">Noch keine Transaktionen erfasst.</td></tr>';
        } else {
            foreach ($recentOrders as $o) {
                $sid = Security::e(substr((string)$o['stripe_session_id'], 0, 16) . '...');
                $email = Security::e((string)$o['customer_email']);
                $amount = number_format($o['amount_cents'] / 100, 2, ',', '.') . ' ' . strtoupper($o['currency']);
                $date = Security::e((string)$o['created_at']);
                $status = Security::e((string)$o['status']);

                $ordersRows .= <<<HTML
<tr class="border-b border-[#1e293b] hover:bg-[#141e33]/50 transition">
    <td class="py-3 px-4 text-xs font-mono text-white">{$sid}</td>
    <td class="py-3 px-4 text-xs text-slate-300">{$email}</td>
    <td class="py-3 px-4 text-xs font-bold text-emerald-400">{$amount}</td>
    <td class="py-3 px-4 text-xs"><span class="px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">{$status}</span></td>
    <td class="py-3 px-4 text-xs text-slate-400">{$date}</td>
</tr>
HTML;
            }
        }

        $csrfField = $this->csrfField();

        $contentHtml = <<<HTML
<div class="max-w-4xl space-y-6">
    <form method="POST" action="/admin/payments" class="bg-[#0f172a] border border-[#1e293b] rounded-2xl p-6 sm:p-8 shadow-xl space-y-6">
        {$csrfField}

        <div class="flex items-center justify-between p-4 rounded-xl bg-[#090d16] border border-[#1e293b]">
            <div>
                <span class="block text-xs font-semibold text-white">Stripe Checkout &amp; Paywall aktivieren</span>
                <span class="text-[11px] text-slate-400">Ermöglicht kostenpflichtige Mitgliedschaften und Paywall-Freischaltungen.</span>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" name="stripe_enabled" value="1" {$this->checked($enabled)} class="sr-only peer">
                <div class="w-11 h-6 bg-slate-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#f97316]"></div>
            </label>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1">Betriebsmodus</label>
                <select name="stripe_mode" class="w-full px-3.5 py-2.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white focus:outline-none focus:border-[#f97316]">
                    <option value="test" {$this->selected($mode, 'test')}>Test-Modus (Sandbox)</option>
                    <option value="live" {$this->selected($mode, 'live')}>Live-Modus (Echte Zahlungen)</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1">Standardwährung</label>
                <select name="stripe_currency" class="w-full px-3.5 py-2.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white focus:outline-none focus:border-[#f97316]">
                    <option value="eur" {$this->selected($currency, 'eur')}>EUR (€)</option>
                    <option value="chf" {$this->selected($currency, 'chf')}>CHF</option>
                    <option value="usd" {$this->selected($currency, 'usd')}>USD ($)</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1">Stripe Secret Key</label>
                <input type="password" name="stripe_secret_key" value="{$secretKey}" placeholder="sk_test_••••••••••••••••••••"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white focus:outline-none focus:border-[#f97316]">
            </div>
            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-1">Stripe Publishable Key</label>
                <input type="text" name="stripe_publishable_key" value="{$pubKey}" placeholder="pk_test_••••••••••••••••••••"
                       class="w-full px-3.5 py-2.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white focus:outline-none focus:border-[#f97316]">
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-300 mb-1">Stripe Webhook Signing Secret</label>
            <input type="password" name="stripe_webhook_secret" value="{$webhookSecret}" placeholder="whsec_••••••••••••••••••••"
                   class="w-full px-3.5 py-2.5 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs text-white focus:outline-none focus:border-[#f97316]">
            <span class="text-[11px] text-slate-500 mt-1 block">Zur kryptografischen Verifizierung eingehender Webhooks im puren PHP-Code.</span>
        </div>

        <div class="p-4 rounded-xl bg-[#090d16] border border-[#1e293b] text-xs">
            <span class="text-slate-400 block mb-1 font-semibold">Ihre Stripe Webhook-URL (im Stripe Dashboard eintragen):</span>
            <span class="font-mono text-orange-400 select-all">{$webhookEndpoint}</span>
        </div>

        <div class="flex items-center justify-end space-x-3 pt-6 border-t border-[#1e293b]">
            <button type="submit" class="px-5 py-2.5 rounded-xl bg-[#f97316] hover:bg-[#ea580c] text-white text-xs font-semibold shadow-lg shadow-orange-500/20">
                Stripe-Einstellungen speichern
            </button>
        </div>
    </form>

    <!-- Recent Transactions Table -->
    <div class="bg-[#0f172a] border border-[#1e293b] rounded-2xl shadow-xl overflow-hidden">
        <div class="p-6 border-b border-[#1e293b]">
            <h4 class="text-sm font-bold text-white">Letzte Stripe-Transaktionen &amp; Freischaltungen</h4>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-[#1e293b] text-[11px] text-slate-400 uppercase bg-[#090d16]">
                        <th class="py-3 px-4">Session-ID</th>
                        <th class="py-3 px-4">Kunden-E-Mail</th>
                        <th class="py-3 px-4">Betrag</th>
                        <th class="py-3 px-4">Status</th>
                        <th class="py-3 px-4">Datum</th>
                    </tr>
                </thead>
                <tbody>
                    {$ordersRows}
                </tbody>
            </table>
        </div>
    </div>
</div>
HTML;

        return Response::html(AdminView::renderShell('Zahlungen &amp; Stripe', 'payments', $contentHtml));
    }

    // Helper methods
    private function updateOption(string $key, string $value): void
    {
        $existing = $this->db->fetchOne("SELECT id FROM {prefix}options WHERE option_name = :k LIMIT 1", ['k' => $key]);
        if ($existing) {
            $this->db->update('{prefix}options', ['option_value' => $value], 'option_name = :k', ['k' => $key]);
        } else {
            $this->db->insert('{prefix}options', ['option_name' => $key, 'option_value' => $value, 'autoload' => 1]);
        }
    }

    private function selected(string $current, string $target): string
    {
        return $current === $target ? 'selected' : '';
    }

    private function checked(bool $val): string
    {
        return $val ? 'checked' : '';
    }
}
