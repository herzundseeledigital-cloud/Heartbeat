<?php
declare(strict_types=1);

namespace Heartbeat\Admin;

use Heartbeat\Http\Request;
use Heartbeat\Http\Response;
use Heartbeat\Security\Security;
use Heartbeat\Security\Csrf;
use Heartbeat\Security\Session;
use Heartbeat\Security\Rbac;
use Heartbeat\Auth\Auth;
use Heartbeat\Themes\ThemeService;
use Heartbeat\Themes\CssCompiler;

/**
 * Theme Manager & Live Studio Customizer Controller
 */
final class ThemeController
{
    private ThemeService $themes;
    private CssCompiler $compiler;

    public function __construct(?ThemeService $themes = null, ?CssCompiler $compiler = null)
    {
        $this->themes = $themes ?? ThemeService::getInstance();
        $this->compiler = $compiler ?? CssCompiler::getInstance();
    }

    /**
     * Theme Overview & Library
     */
    public function index(Request $request): Response
    {
        $redirect = $this->requireAuth('manage_themes');
        if ($redirect) return $redirect;

        $allThemes = $this->themes->getAll();
        $activeTheme = $this->themes->getActive();
        $csrfField = Csrf::field();

        $themeCards = '';
        foreach ($allThemes as $slug => $theme) {
            // Deduplicate if keyed by both slug and dir
            if ($theme->getSlug() !== $slug && isset($allThemes[$theme->getSlug()])) {
                continue;
            }

            $isActive = ($theme->getSlug() === $activeTheme->getSlug());
            $safeName = Security::e($theme->getName());
            $safeAuthor = Security::e($theme->getAuthor());
            $safeVersion = Security::e($theme->getVersion());
            $safeDesc = Security::e($theme->getDescription());
            $safeSlug = Security::e($theme->getSlug());

            // Color dots preview
            $colors = $theme->getColors();
            $colorDots = '';
            foreach (['primary', 'background', 'card', 'text', 'accent'] as $ck) {
                if (!empty($colors[$ck])) {
                    $hex = Security::e($colors[$ck]);
                    $colorDots .= "<span class=\"w-4 h-4 rounded-full border border-white/10 shadow-sm inline-block\" style=\"background-color: {$hex};\" title=\"{$ck}: {$hex}\"></span>";
                }
            }

            $activeBadge = $isActive
                ? '<span class="inline-flex items-center space-x-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20"><span>●</span><span>Aktiv</span></span>'
                : '<span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] text-slate-500 font-mono">v' . $safeVersion . '</span>';

            $actionBtn = $isActive
                ? "<a href=\"/admin/themes/customize?theme={$safeSlug}\" class=\"px-4 py-2 rounded-xl bg-[#fb326f] hover:bg-[#e01e5a] text-white text-xs font-semibold shadow-lg shadow-[#fb326f]/20 transition flex items-center space-x-1.5\">
                     <svg class=\"w-3.5 h-3.5\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z\"></path></svg>
                     <span>Live anpassen</span>
                   </a>"
                : "<form method=\"POST\" action=\"/admin/themes/activate/{$safeSlug}\" class=\"inline\">
                     {$csrfField}
                     <button type=\"submit\" class=\"px-3.5 py-2 rounded-xl bg-[#fb326f] hover:bg-[#e01e5a] text-white text-xs font-semibold transition shadow-lg shadow-[#fb326f]/20\">
                       Aktivieren
                     </button>
                   </form>
                   <a href=\"/admin/themes/customize?theme={$safeSlug}\" class=\"px-3 py-2 rounded-xl bg-[#141e33] hover:bg-[#1e293b] text-slate-300 hover:text-white text-xs font-medium transition border border-[#1e293b]\">
                     Live-Vorschau
                   </a>";

            $borderClass = $isActive ? 'border-[#fb326f]/40 ring-1 ring-[#fb326f]/20' : 'border-[#1e293b]';

            $themeCards .= <<<HTML
<div class="bg-[#0f172a] border {$borderClass} rounded-2xl p-6 shadow-xl flex flex-col justify-between transition hover:border-slate-700">
    <div>
        <div class="flex items-start justify-between mb-4">
            <div>
                <h3 class="text-base font-bold text-white tracking-tight">{$safeName}</h3>
                <p class="text-[11px] text-slate-400 font-mono mt-0.5">Autor: {$safeAuthor}</p>
            </div>
            {$activeBadge}
        </div>

        <p class="text-xs text-slate-400 line-clamp-3 leading-relaxed mb-5">
            {$safeDesc}
        </p>

        <!-- Color Palette Dots -->
        <div class="mb-6 p-3 rounded-xl bg-[#090d16] border border-[#1e293b] flex items-center justify-between">
            <span class="text-[11px] text-slate-400 font-medium">Farbpalette</span>
            <div class="flex items-center space-x-1.5">
                {$colorDots}
            </div>
        </div>
    </div>

    <div class="flex items-center justify-between pt-4 border-t border-[#1e293b]">
        <span class="text-[11px] text-slate-500 font-mono">Slug: {$safeSlug}</span>
        <div class="flex items-center space-x-2">
            {$actionBtn}
        </div>
    </div>
</div>
HTML;
        }

        $headerActions = <<<HTML
<a href="/admin/themes/customize" class="inline-flex items-center space-x-2 text-xs font-semibold px-4 py-2.5 rounded-xl bg-[#fb326f] hover:bg-[#e01e5a] text-white transition shadow-lg shadow-[#fb326f]/20">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z\"></path></svg>
    <span>Theme Studio öffnen</span>
</a>
HTML;

        $contentHtml = <<<HTML
<div class="space-y-6">
    <div class="flex items-center justify-between bg-[#141e33]/40 border border-[#1e293b] rounded-2xl p-4 sm:p-5">
        <div>
            <h2 class="text-sm font-bold text-white">Live Theme Engine &amp; Design Studio</h2>
            <p class="text-xs text-slate-400 mt-0.5">Wählen Sie das aktive Theme aus und passen Sie Farben, Schriften und Styles in Echtzeit an.</p>
        </div>
        <div class="hidden sm:block">
            <span class="text-xs px-3 py-1.5 rounded-xl bg-[#fb326f]/10 text-[#fb326f] border border-[#fb326f]/20 font-semibold">
                JIT CSS Engine aktiv
            </span>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {$themeCards}
    </div>
</div>
HTML;

        return Response::html(AdminView::renderShell('Design &amp; Themes', 'themes', $contentHtml, [
            ['label' => 'Dashboard', 'url' => '/admin'],
            ['label' => 'Themes', 'url' => '/admin/themes'],
        ], $headerActions));
    }

    /**
     * Activate a Theme (POST)
     */
    public function activate(Request $request, array $params): Response
    {
        $redirect = $this->requireAuth('manage_themes');
        if ($redirect) return $redirect;

        if (!Csrf::verifyRequest($request)) {
            Session::flash('error', 'Ungültiges Sicherheits-Token (CSRF).');
            return Response::redirect('/admin/themes');
        }

        $slug = (string)($params['slug'] ?? '');
        if ($this->themes->setActive($slug)) {
            $theme = $this->themes->get($slug);
            $name = $theme ? $theme->getName() : $slug;
            Session::flash('success', "Theme '{$name}' wurde erfolgreich aktiviert und das CSS-Bundle kompiliert.");
        } else {
            Session::flash('error', "Das Theme '{$slug}' konnte nicht gefunden oder aktiviert werden.");
        }

        return Response::redirect('/admin/themes');
    }

    /**
     * Live Theme Studio / Customizer
     */
    public function customize(Request $request): Response
    {
        $redirect = $this->requireAuth('manage_themes');
        if ($redirect) return $redirect;

        $slug = (string)$request->get('theme', '');
        $theme = $slug !== '' ? $this->themes->get($slug) : null;
        if (!$theme) {
            $theme = $this->themes->getActive();
        }

        $safeThemeSlug = Security::e($theme->getSlug());
        $safeThemeName = Security::e($theme->getName());
        $colors = $theme->getColors();
        $typography = $theme->getTypography();
        $settings = $theme->getSettings();
        $csrfField = Csrf::field();

        // Encode current theme config for Alpine
        $themeConfigJson = json_encode([
            'colors'     => $colors,
            'typography' => $typography,
            'settings'   => $settings,
            'custom_css' => $this->themes->getCustomCss($theme->getSlug()),
        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        $safeConfigAttr = htmlspecialchars($themeConfigJson, ENT_QUOTES, 'UTF-8');

        $isActive = ($this->themes->getActive()->getSlug() === $theme->getSlug());
        $activationBlock = $isActive
            ? '<span class="inline-flex items-center space-x-1 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20"><span>●</span><span>Aktiv</span></span>'
            : '<form method="POST" action="/admin/themes/activate/' . $safeThemeSlug . '" class="inline">' .
              $csrfField .
              '<button type="submit" class="px-2.5 py-1 text-[11px] font-semibold rounded-lg bg-[#fb326f] hover:bg-[#e01e5a] text-white shadow-sm transition flex items-center space-x-1">
                  <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                  <span>Aktivieren</span>
              </button></form>';

        $contentHtml = <<<HTML
<div x-data="themeStudio({$safeConfigAttr})" class="flex flex-col lg:flex-row gap-6 h-[calc(100vh-12rem)]">
    <!-- Left Sidebar: Controls & Presets -->
    <div class="w-full lg:w-96 flex flex-col bg-[#0f172a] border border-[#1e293b] rounded-2xl shadow-2xl overflow-hidden flex-shrink-0">
        <!-- Studio Header -->
        <div class="p-4 border-b border-[#1e293b] bg-[#141e33]/50 flex items-center justify-between">
            <div>
                <h3 class="text-xs font-bold text-white tracking-wide uppercase">Theme Customizer</h3>
                <span class="text-[11px] text-[#fb326f] font-mono">{$safeThemeName}</span>
            </div>
            <div class="flex items-center space-x-2">
                {$activationBlock}
                <!-- Preset Dropdown -->
                <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                    <button type="button" @click="open = !open" class="px-2.5 py-1 text-[11px] font-semibold rounded-lg bg-slate-800 text-slate-300 hover:text-white border border-slate-700 flex items-center space-x-1">
                        <span>Presets</span>
                        <svg class="w-3 h-3 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>
                    <div x-show="open" x-cloak class="absolute right-0 mt-1 w-44 bg-[#090d16] border border-[#1e293b] rounded-xl shadow-2xl py-1 z-30 text-xs">
                        <button type="button" @click="applyPreset('herzundseele'); open = false" class="w-full text-left px-3 py-1.5 hover:bg-[#141e33] text-slate-300 hover:text-white">Herz &amp; Seele Dark</button>
                        <button type="button" @click="applyPreset('emerald'); open = false" class="w-full text-left px-3 py-1.5 hover:bg-[#141e33] text-slate-300 hover:text-white">Emerald Agency</button>
                        <button type="button" @click="applyPreset('purple'); open = false" class="w-full text-left px-3 py-1.5 hover:bg-[#141e33] text-slate-300 hover:text-white">Royal Purple</button>
                        <button type="button" @click="applyPreset('ocean'); open = false" class="w-full text-left px-3 py-1.5 hover:bg-[#141e33] text-slate-300 hover:text-white">Ocean Blue</button>
                        <button type="button" @click="applyPreset('light'); open = false" class="w-full text-left px-3 py-1.5 hover:bg-[#141e33] text-slate-300 hover:text-white">Clean Light</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Scrollable Controls -->
        <form method="POST" action="/admin/themes/save" class="flex-1 overflow-y-auto p-4 space-y-6 text-xs" id="customizerForm">
            {$csrfField}
            <input type="hidden" name="theme_slug" value="{$safeThemeSlug}">

            <!-- Colors Section -->
            <div class="space-y-3">
                <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-400">Design Tokens (Farben)</span>
                
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] text-slate-400 mb-1">Primärfarbe</label>
                        <div class="flex items-center space-x-2">
                            <input type="color" x-model="config.colors.primary" @input="updatePreview()" class="w-7 h-7 rounded border-0 cursor-pointer bg-transparent">
                            <input type="text" name="colors[primary]" x-model="config.colors.primary" @input="updatePreview()" class="w-full px-2 py-1.5 bg-[#090d16] border border-[#1e293b] rounded-lg text-white font-mono text-[11px]">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[11px] text-slate-400 mb-1">Primär (Hover)</label>
                        <div class="flex items-center space-x-2">
                            <input type="color" x-model="config.colors.primary_hover" class="w-7 h-7 rounded border-0 cursor-pointer bg-transparent">
                            <input type="text" name="colors[primary_hover]" x-model="config.colors.primary_hover" class="w-full px-2 py-1.5 bg-[#090d16] border border-[#1e293b] rounded-lg text-white font-mono text-[11px]">
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] text-slate-400 mb-1">Hintergrund</label>
                        <div class="flex items-center space-x-2">
                            <input type="color" x-model="config.colors.background" @input="updatePreview()" class="w-7 h-7 rounded border-0 cursor-pointer bg-transparent">
                            <input type="text" name="colors[background]" x-model="config.colors.background" @input="updatePreview()" class="w-full px-2 py-1.5 bg-[#090d16] border border-[#1e293b] rounded-lg text-white font-mono text-[11px]">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[11px] text-slate-400 mb-1">Karten / Boxen</label>
                        <div class="flex items-center space-x-2">
                            <input type="color" x-model="config.colors.card" @input="updatePreview()" class="w-7 h-7 rounded border-0 cursor-pointer bg-transparent">
                            <input type="text" name="colors[card]" x-model="config.colors.card" @input="updatePreview()" class="w-full px-2 py-1.5 bg-[#090d16] border border-[#1e293b] rounded-lg text-white font-mono text-[11px]">
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] text-slate-400 mb-1">Rahmen (Border)</label>
                        <div class="flex items-center space-x-2">
                            <input type="color" x-model="config.colors.border" @input="updatePreview()" class="w-7 h-7 rounded border-0 cursor-pointer bg-transparent">
                            <input type="text" name="colors[border]" x-model="config.colors.border" @input="updatePreview()" class="w-full px-2 py-1.5 bg-[#090d16] border border-[#1e293b] rounded-lg text-white font-mono text-[11px]">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[11px] text-slate-400 mb-1">Textfarbe</label>
                        <div class="flex items-center space-x-2">
                            <input type="color" x-model="config.colors.text" @input="updatePreview()" class="w-7 h-7 rounded border-0 cursor-pointer bg-transparent">
                            <input type="text" name="colors[text]" x-model="config.colors.text" @input="updatePreview()" class="w-full px-2 py-1.5 bg-[#090d16] border border-[#1e293b] rounded-lg text-white font-mono text-[11px]">
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] text-slate-400 mb-1">Akzentfarbe</label>
                    <div class="flex items-center space-x-2">
                        <input type="color" x-model="config.colors.accent" @input="updatePreview()" class="w-7 h-7 rounded border-0 cursor-pointer bg-transparent">
                        <input type="text" name="colors[accent]" x-model="config.colors.accent" @input="updatePreview()" class="w-full px-2 py-1.5 bg-[#090d16] border border-[#1e293b] rounded-lg text-white font-mono text-[11px]">
                    </div>
                </div>
            </div>

            <!-- Typography Section -->
            <div class="space-y-3 pt-4 border-t border-[#1e293b]">
                <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-400">Typografie</span>
                
                <div>
                    <label class="block text-[11px] text-slate-400 mb-1">Schriftart Fließtext</label>
                    <select name="typography[font_family]" x-model="config.typography.font_family" class="w-full px-2.5 py-1.5 rounded-lg bg-[#090d16] border border-[#1e293b] text-white">
                        <option value="Inter">Inter (Self-Hosted Standard)</option>
                        <option value="Roboto">Roboto</option>
                        <option value="Plus Jakarta Sans">Plus Jakarta Sans</option>
                        <option value="Poppins">Poppins</option>
                        <option value="Outfit">Outfit</option>
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] text-slate-400 mb-1">Überschriften-Schrift</label>
                    <select name="typography[heading_font]" x-model="config.typography.heading_font" class="w-full px-2.5 py-1.5 rounded-lg bg-[#090d16] border border-[#1e293b] text-white">
                        <option value="Inter">Inter (Matching)</option>
                        <option value="Plus Jakarta Sans">Plus Jakarta Sans</option>
                        <option value="Outfit">Outfit (Bold Editorial)</option>
                        <option value="Poppins">Poppins</option>
                    </select>
                </div>
            </div>

            <!-- Layout Section -->
            <div class="space-y-3 pt-4 border-t border-[#1e293b]">
                <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-400">Layout &amp; Ecken</span>
                
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[11px] text-slate-400 mb-1">Container Breite</label>
                        <select name="settings[container_width]" x-model="config.settings.container_width" class="w-full px-2 py-1.5 rounded-lg bg-[#090d16] border border-[#1e293b] text-white font-mono text-[11px]">
                            <option value="max-w-5xl">5XL (1024px)</option>
                            <option value="max-w-6xl">6XL (1152px Standard)</option>
                            <option value="max-w-7xl">7XL (1280px Wide)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] text-slate-400 mb-1">Eckenrundung</label>
                        <select name="settings[border_radius]" x-model="config.settings.border_radius" class="w-full px-2 py-1.5 rounded-lg bg-[#090d16] border border-[#1e293b] text-white font-mono text-[11px]">
                            <option value="rounded-lg">Kompakt (rounded-lg)</option>
                            <option value="rounded-xl">Standard (rounded-xl)</option>
                            <option value="rounded-2xl">Modern Soft (rounded-2xl)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Custom CSS Section -->
            <div class="space-y-2 pt-4 border-t border-[#1e293b]">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Zusätzliches CSS</span>
                    <span class="text-[10px] text-slate-500 font-mono">Live Injected</span>
                </div>
                <div class="relative flex rounded-xl bg-[#090d16] border border-[#1e293b] overflow-hidden focus-within:border-[#fb326f] shadow-inner font-mono text-[11px]">
                    <!-- Line Numbers Gutter -->
                    <div id="cssLineNumbers"
                         class="select-none py-2.5 px-2 bg-[#0c1220] border-r border-[#1e293b] text-slate-500 text-right leading-5 min-w-[2.5rem] overflow-hidden"
                         aria-hidden="true">
                        <template x-for="line in (config.custom_css ? config.custom_css.split('\\n').length : 1)" :key="line">
                            <div x-text="line"></div>
                        </template>
                    </div>
                    <!-- Code Editor Textarea -->
                    <textarea id="customCssEditor"
                              name="custom_css"
                              x-model="config.custom_css"
                              @input="updatePreviewCss()"
                              @scroll="syncScroll(\$event)"
                              @keydown.tab.prevent="handleTab(\$event)"
                              rows="8"
                              spellcheck="false"
                              placeholder="/* Eigene Tailwind oder CSS-Regeln */"
                              class="flex-1 p-2.5 bg-transparent text-white leading-5 resize-y focus:outline-none whitespace-pre overflow-auto font-mono"></textarea>
                </div>
            </div>
        </form>

        <!-- Studio Footer: Save Button -->
        <div class="p-4 border-t border-[#1e293b] bg-[#141e33]/50 flex items-center justify-between">
            <a href="/admin/themes" class="text-xs text-slate-400 hover:text-white transition">&larr; Zurück</a>
            <button type="submit" form="customizerForm" class="px-5 py-2.5 rounded-xl bg-[#fb326f] hover:bg-[#e01e5a] text-white text-xs font-semibold shadow-lg shadow-[#fb326f]/20 transition flex items-center space-x-1.5">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                <span>Änderungen speichern</span>
            </button>
        </div>
    </div>

    <!-- Right Area: Live Preview Iframe -->
    <div class="flex-1 flex flex-col bg-[#0f172a] border border-[#1e293b] rounded-2xl shadow-2xl overflow-hidden min-w-0">
        <!-- Preview Bar -->
        <div class="h-12 px-4 border-b border-[#1e293b] bg-[#141e33]/50 flex items-center justify-between">
            <div class="flex items-center space-x-2">
                <div class="w-3 h-3 rounded-full bg-rose-500/80"></div>
                <div class="w-3 h-3 rounded-full bg-amber-500/80"></div>
                <div class="w-3 h-3 rounded-full bg-emerald-500/80"></div>
                <span class="text-[11px] text-slate-400 font-mono ml-2">Live-Vorschau</span>
            </div>

            <!-- Viewport switches -->
            <div class="flex items-center space-x-1 bg-[#090d16] p-1 rounded-xl border border-[#1e293b]">
                <button type="button" @click="deviceWidth = '100%'" :class="deviceWidth === '100%' ? 'bg-slate-800 text-white' : 'text-slate-400 hover:text-white'" class="p-1 rounded-lg transition" title="Desktop">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                </button>
                <button type="button" @click="deviceWidth = '768px'" :class="deviceWidth === '768px' ? 'bg-slate-800 text-white' : 'text-slate-400 hover:text-white'" class="p-1 rounded-lg transition" title="Tablet">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                </button>
                <button type="button" @click="deviceWidth = '375px'" :class="deviceWidth === '375px' ? 'bg-slate-800 text-white' : 'text-slate-400 hover:text-white'" class="p-1 rounded-lg transition" title="Mobile">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                </button>
            </div>

            <!-- Refresh Button -->
            <button type="button" @click="refreshIframe()" class="p-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white transition" title="Aktualisieren">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
            </button>
        </div>

        <!-- Iframe Container -->
        <div class="flex-1 bg-[#070b14] flex items-center justify-center p-2 overflow-hidden">
            <div :style="'width: ' + deviceWidth + '; height: 100%; transition: width 0.3s ease;'" class="shadow-2xl rounded-xl overflow-hidden border border-[#1e293b]">
                <iframe id="themePreviewFrame" src="/?theme_preview={$safeThemeSlug}&preview=1&nocache=1" @load="updatePreview(); updatePreviewCss()" class="w-full h-full bg-[#090d16] border-0"></iframe>
            </div>
        </div>
    </div>
</div>

<script>
function themeStudio(initialConfig) {
    return {
        config: initialConfig,
        deviceWidth: '100%',
        init() {
            this.\$nextTick(() => {
                this.updatePreview();
                this.updatePreviewCss();
            });
        },
        syncScroll(e) {
            const gutter = document.getElementById('cssLineNumbers');
            if (gutter) {
                gutter.scrollTop = e.target.scrollTop;
            }
        },
        handleTab(e) {
            const textarea = e.target;
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const value = textarea.value;
            textarea.value = value.substring(0, start) + "  " + value.substring(end);
            this.config.custom_css = textarea.value;
            textarea.selectionStart = textarea.selectionEnd = start + 2;
            this.updatePreviewCss();
        },
        updatePreviewCss() {
            try {
                const iframe = document.getElementById('themePreviewFrame');
                if (iframe && iframe.contentDocument) {
                    const doc = iframe.contentDocument;
                    let style = doc.getElementById('live-custom-css');
                    if (!style) {
                        style = doc.createElement('style');
                        style.id = 'live-custom-css';
                        if (doc.head) {
                            doc.head.appendChild(style);
                        } else if (doc.documentElement) {
                            doc.documentElement.appendChild(style);
                        }
                    }
                    if (style) {
                        style.textContent = this.config.custom_css || '';
                    }
                }
            } catch (e) {
                // Ignore cross-origin frame access if any
            }
        },
        applyPreset(preset) {
            const presets = {
                'herzundseele': {
                    primary: '#fb326f', primary_hover: '#e01e5a', background: '#090d16',
                    card: '#0f172a', border: '#1e293b', text: '#f8fafc', accent: '#38bdf8'
                },
                'emerald': {
                    primary: '#10b981', primary_hover: '#059669', background: '#061a14',
                    card: '#0a2e23', border: '#134e3a', text: '#f0fdf4', accent: '#34d399'
                },
                'purple': {
                    primary: '#a855f7', primary_hover: '#9333ea', background: '#100b1a',
                    card: '#1e1430', border: '#322150', text: '#faf5ff', accent: '#f43f5e'
                },
                'ocean': {
                    primary: '#0284c7', primary_hover: '#0369a1', background: '#071526',
                    card: '#0d233a', border: '#173a5e', text: '#f0f9ff', accent: '#38bdf8'
                },
                'light': {
                    primary: '#fb326f', primary_hover: '#e01e5a', background: '#f8fafc',
                    card: '#ffffff', border: '#e2e8f0', text: '#0f172a', accent: '#0284c7'
                }
            };
            if (presets[preset]) {
                this.config.colors = Object.assign({}, presets[preset]);
                this.updatePreview();
            }
        },
        updatePreview() {
            try {
                const iframe = document.getElementById('themePreviewFrame');
                if (iframe && iframe.contentDocument) {
                    const doc = iframe.contentDocument;
                    const root = doc.documentElement;
                    root.style.setProperty('--hb-primary', this.config.colors.primary);
                    root.style.setProperty('--hb-primary-hover', this.config.colors.primary_hover);
                    root.style.setProperty('--hb-bg', this.config.colors.background);
                    root.style.setProperty('--hb-card', this.config.colors.card);
                    root.style.setProperty('--hb-border', this.config.colors.border);
                    root.style.setProperty('--hb-text', this.config.colors.text);
                    root.style.setProperty('--hb-accent', this.config.colors.accent);
                }
            } catch (e) {
                // Ignore cross-origin frame access if any
            }
        },
        refreshIframe() {
            const iframe = document.getElementById('themePreviewFrame');
            if (iframe) {
                iframe.src = '/?theme_preview=' + encodeURIComponent('{$safeThemeSlug}') + '&preview=1&nocache=' + Date.now();
            }
        }
    };
}
</script>
HTML;

        return Response::html(AdminView::renderShell("Theme Customizer: {$theme->getName()}", 'themes', $contentHtml, [
            ['label' => 'Dashboard', 'url' => '/admin'],
            ['label' => 'Themes', 'url' => '/admin/themes'],
            ['label' => 'Studio', 'url' => '/admin/themes/customize'],
        ]));
    }

    /**
     * Save theme settings from Customizer (POST)
     */
    public function save(Request $request): Response
    {
        $redirect = $this->requireAuth('manage_themes');
        if ($redirect) return $redirect;

        if (!Csrf::verifyRequest($request)) {
            Session::flash('error', 'Ungültiges Sicherheits-Token (CSRF).');
            return Response::redirect('/admin/themes/customize');
        }

        $slug = (string)$request->post('theme_slug', '');
        $theme = $this->themes->get($slug);
        if (!$theme) {
            Session::flash('error', 'Theme nicht gefunden.');
            return Response::redirect('/admin/themes');
        }

        $colors = (array)$request->post('colors', []);
        $typography = (array)$request->post('typography', []);
        $settings = (array)$request->post('settings', []);
        $customCss = (string)$request->post('custom_css', '');

        // Persist clean custom CSS directly
        $this->themes->saveCustomCss($theme->getSlug(), $customCss);

        $success = $this->themes->updateThemeSettings($theme->getSlug(), [
            'colors'     => $colors,
            'typography' => $typography,
            'settings'   => $settings,
            'custom_css' => $customCss,
        ]);

        if ($success) {
            Session::flash('success', "Design-Tokens &amp; Styles für Theme '{$theme->getName()}' erfolgreich gespeichert und CSS kompiliert!");
        } else {
            Session::flash('error', "Fehler beim Speichern der Theme-Einstellungen.");
        }

        return Response::redirect("/admin/themes/customize?theme={$theme->getSlug()}");
    }

    /**
     * In-Browser / Editor JIT CSS Compilation Endpoint
     */
    public function compileCssApi(Request $request): Response
    {
        $redirect = $this->requireAuth('manage_themes');
        if ($redirect) return $redirect;

        $slug = (string)$request->post('theme_slug', $this->themes->getActive()->getSlug());
        $customCss = (string)$request->post('custom_css', '');
        $additionalClasses = (array)$request->post('classes', []);

        $theme = $this->themes->get($slug) ?? $this->themes->getActive();
        $compiledCss = $this->compiler->compileForTheme($theme, $additionalClasses, $customCss);

        return Response::json([
            'success'    => true,
            'theme_slug' => $theme->getSlug(),
            'css_path'   => $this->compiler->getCssFilePath($theme->getSlug()),
            'bytes'      => strlen($compiledCss),
        ]);
    }

    private function requireAuth(string $permission = 'manage_themes'): ?Response
    {
        if (!Auth::check()) {
            return Response::redirect('/admin/login');
        }
        if (!Auth::can($permission)) {
            Session::flash('error', 'Keine Berechtigung für das Theme-Management.');
            return Response::redirect('/admin');
        }
        return null;
    }
}

