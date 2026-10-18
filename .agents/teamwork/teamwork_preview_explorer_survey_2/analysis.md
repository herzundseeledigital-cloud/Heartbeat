# Survey Analysis: Requirements R3 & R4
**Heartbeat CMS Enterprise Refinement**
**Agent**: Explorer 2 (Survey: Theme Color Schemes & Theme Customizer / Live CSS Editor)
**Date**: 2026-10-18
**Working Directory**: `/Users/risinger/Documents/VSCode/Heartbeat/.agents/teamwork/teamwork_preview_explorer_survey_2`

---

## Executive Summary
This survey provides a comprehensive investigation of the Heartbeat CMS codebase for:
1. **Requirement R3**: Decoupling frontend theme color schemes completely from Heartbeat CMS admin CI (`#fb326f`).
2. **Requirement R4**: Building an interactive Theme Customizer & Live CSS Editor under `/admin/themes/customize` with line numbers, real-time iframe preview, dedicated persistence to `storage/custom-css/{theme}.css` or `themes/{theme}/custom.css`, and automatic `<head>` injection.

All existing test suites (`Phase1` through `Phase6`) currently pass on the system when executed via `/Applications/XAMPP/xamppfiles/bin/php`. This survey documents the current codebase state, identified bugs, architectural gaps, and exact code changes required to implement R3 and R4 cleanly and safely without regressions.

---

## Part 1: Requirement R3 — Decouple Theme Color Schemes from CMS CI

### 1.1 Problem Statement & Background
The Heartbeat CMS administrative brand identity is `#fb326f` (Heartbeat Magenta) with hover `#e01e5a`. In the current codebase, this admin CI color has bled into frontend themes and shared frontend rendering services:
- **One-Pager Starter Theme (`themes/default`)**: Its `theme.json` was created with `"primary": "#fb326f"`, and its templates (`header.php`, `home.php`, `404.php`) have `#fb326f` hardcoded in more than 35 locations.
- **Modern Startup Theme (`themes/startup`)**: Although its primary color is Violet (`#8b5cf6`), its `home.php` template contains 7 hardcoded `#fb326f` references in gradient stops and accent text.
- **Structured Block Renderer (`core/Blocks/BlockRenderer.php`)**: When rendering core blocks (Hero, Buttons, Cards, CTAs, FAQs, Badges, Testimonials, Alert, Modals) on ANY frontend theme, 22 instances of `#fb326f` and `#e01e5a` are hardcoded. Consequently, a user on the Corporate theme (`#2563eb`) or Real Estate theme (`#059669`) still sees pink buttons and pink badges on block-rendered pages.
- **Core Fallback Templates (`core/App.php`)**: Fallback rendering in `renderFallbackPostHtml` (lines 525, 530, 541) and `renderFallbackHomeHtml` (lines 584, 589, 595) hardcodes `#fb326f`.
- **System Fallback Tokens (`core/Themes/Theme.php` & `ThemeService.php`)**: Default fallback arrays specify `'primary' => '#fb326f'`.

### 1.2 Audit of Existing Themes

| Theme | Directory | Slug | Current Primary | Proposed Primary | Status & Observations |
|---|---|---|---|---|---|
| **One-Pager Starter** | `themes/default` | `one-pager` | `#fb326f` (or `#f97316` in test) | `#f97316` (Sunset Orange), hover `#ea580c`, accent `#38bdf8` | **Needs Full Decoupling**: 35+ hardcoded `#fb326f` in `header.php`, `home.php`, `404.php`. Update `theme.json` to Sunset Orange palette. |
| **Clean Corporate** | `themes/corporate` | `corporate` | `#2563eb` (Trust Blue) | `#2563eb`, hover `#1d4ed8`, accent `#38bdf8` | **Clean**: 0 occurrences of `#fb326f` in templates. Palette is distinct. |
| **Classic Real Estate** | `themes/real-estate` | `real-estate` | `#059669` (Forest Emerald) | `#059669`, hover `#047857`, accent `#34d399` | **Clean**: 0 occurrences of `#fb326f` in templates. Palette is distinct. |
| **Modern Startup** | `themes/startup` | `startup` | `#8b5cf6` (Vibrant Violet) | `#8b5cf6`, hover `#7c3aed`, accent `#f43f5e` (Rose) | **Minor Leak**: 7 instances of `#fb326f` in `home.php` gradient stops. Replace with `#f43f5e` / `violet-600` / `purple-500`. |

### 1.3 Detailed Code Locations of Hardcoded CMS CI Colors

#### A. `themes/default/theme.json`
- **Line 8**: `"primary": "#fb326f"` (or updated to `#f97316` by Phase 4 test).
- **Line 9**: `"primary_hover": "#e01e5a"` &rarr; Must be changed to `#ea580c`.
- **Line 14**: `"accent": "#38bdf8"`.

#### B. `themes/default/templates/header.php`
- **Line 42**: `selection:bg-[#fb326f]` &rarr; Replace with `selection:bg-[var(--hb-primary,#f97316)]` or `selection:bg-orange-500`.
- **Line 49**: `bg-gradient-to-br from-[#fb326f] to-[#e01e5a] ... shadow-[#fb326f]/20` &rarr; Replace with `from-orange-500 to-amber-600 shadow-orange-500/20` or CSS variables.
- **Lines 65, 76, 83**: `hover:text-[#fb326f]` &rarr; Replace with `hover:text-orange-400` or `hover:text-[var(--hb-primary)]`.
- **Line 92**: `bg-[#fb326f] hover:bg-[#e01e5a] shadow-[#fb326f]/20` &rarr; Replace with `bg-orange-500 hover:bg-orange-600 shadow-orange-500/20`.
- **Lines 119, 128, 135, 141**: Mobile menu links and contact button using `#fb326f`.

#### C. `themes/default/templates/home.php`
- **Line 16**: Hero glow backdrop `from-[#fb326f]/20 via-[#e01e5a]/10` &rarr; `from-orange-500/20 via-amber-500/10`.
- **Lines 20-21**: Hero badge `bg-[#fb326f]/10 border-[#fb326f]/25 text-[#fb326f]` and pulse `bg-[#fb326f]`.
- **Line 37**: Hero CTA button `bg-[#fb326f] hover:bg-[#e01e5a] shadow-[#fb326f]/25`.
- **Line 48**: Card gradient `from-[#fb326f]/30`.
- **Line 76**: Feature icon container `bg-[#fb326f]/10 border-[#fb326f]/20 text-[#fb326f]`.
- **Line 110**: Stat counter `text-[#fb326f]`.
- **Lines 129, 136, 137, 147, 158**: "Warum wir" section headers and card borders `hover:border-[#fb326f]/40`, icon badges `bg-[#fb326f]/10 text-[#fb326f]`.
- **Lines 175, 182, 188, 194, 200**: Process step numbers `text-[#fb326f]/30` and header `text-[#fb326f]`.
- **Lines 212, 223**: Testimonial section header and avatar `bg-[#fb326f]/20 text-[#fb326f]`.
- **Lines 256, 265, 276, 287**: FAQ toggle symbols `text-[#fb326f]`.
- **Lines 301, 386, 390, 396, 406, 410**: Contact form section header, input focus states `focus:border-[#fb326f]`, and submit CTA button `bg-[#fb326f] hover:bg-[#e01e5a] shadow-[#fb326f]/25`.

#### D. `themes/default/templates/404.php`
- **Line 8**: Icon container `bg-[#fb326f]/10 text-[#fb326f]`.
- **Line 15**: Button `bg-[#fb326f] hover:bg-[#e01e5a] shadow-[#fb326f]/20`.

#### E. `themes/startup/templates/home.php`
- **Line 16**: Hero glow `to-[#fb326f]/20` &rarr; Replace with `to-rose-500/20` or `to-fuchsia-600/20`.
- **Line 21**: Date badge `to-[#fb326f]` &rarr; Replace with `to-rose-500` or `to-fuchsia-500`.
- **Line 27**: Headline gradient `to-[#fb326f]` &rarr; Replace with `to-rose-400` or `to-fuchsia-400`.
- **Line 37**: Pricing CTA button `to-[#fb326f]` &rarr; Replace with `to-rose-500`.
- **Line 49**: Avatar gradient `from-[#fb326f]` &rarr; Replace with `from-rose-500`.
- **Line 59**: Glow border `to-[#fb326f]/30` &rarr; Replace with `to-rose-500/30`.
- **Line 107**: Stat number `text-[#fb326f]` &rarr; Replace with `text-rose-400` or `text-violet-400`.

#### F. `core/Blocks/BlockRenderer.php`
In `BlockRenderer.php`, blocks are rendered for the active theme's pages. The hardcoded `#fb326f` must be replaced with design-token classes or CSS variables:
- **Line 150**: Hero block badge `bg-[#fb326f]/10 border-[#fb326f]/20 text-[#fb326f]` &rarr; `bg-[var(--hb-primary)]/10 border-[var(--hb-primary)]/20 text-[var(--hb-primary)]`.
- **Line 154**: Hero block button `bg-[#fb326f] hover:bg-[#e01e5a] shadow-[#fb326f]/25` &rarr; `bg-[var(--hb-primary)] hover:bg-[var(--hb-primary-hover)] shadow-[var(--hb-primary)]/25`.
- **Line 203**: Section header subtitle `text-[#fb326f]` &rarr; `text-[var(--hb-primary)]`.
- **Line 293**: Outline button `hover:bg-[#fb326f]/10 text-[#fb326f] border-[#fb326f]/40` &rarr; `hover:bg-[var(--hb-primary)]/10 text-[var(--hb-primary)] border-[var(--hb-primary)]/40`.
- **Line 295**: Primary button default `bg-[#fb326f] hover:bg-[#e01e5a] shadow-[#fb326f]/20` &rarr; `bg-[var(--hb-primary)] hover:bg-[var(--hb-primary-hover)] shadow-[var(--hb-primary)]/20`.
- **Line 348**: Card badge `bg-[#fb326f]/10 text-[#fb326f] border-[#fb326f]/20` &rarr; `bg-[var(--hb-primary)]/10 text-[var(--hb-primary)] border-[var(--hb-primary)]/20`.
- **Line 352**: Card title link `hover:text-[#fb326f]` &rarr; `hover:text-[var(--hb-primary)]`.
- **Line 359**: Card icon `bg-[#fb326f]/10 text-[#fb326f]` &rarr; `bg-[var(--hb-primary)]/10 text-[var(--hb-primary)]`.
- **Line 396**: Feature icon `bg-[#fb326f]/10 text-[#fb326f]` &rarr; `bg-[var(--hb-primary)]/10 text-[var(--hb-primary)]`.
- **Line 431**: CTA background blur `bg-[#fb326f]/15` &rarr; `bg-[var(--hb-primary)]/15`.
- **Line 435**: CTA button `bg-[#fb326f] hover:bg-[#e01e5a] shadow-[#fb326f]/25` &rarr; `bg-[var(--hb-primary)] hover:bg-[var(--hb-primary-hover)] shadow-[var(--hb-primary)]/25`.
- **Lines 456, 458**: FAQ accordion hover `hover:text-[#fb326f]` and toggle text `text-[#fb326f]`.
- **Line 483**: Testimonial avatar `bg-[#fb326f]/10 text-[#fb326f] border-[#fb326f]/20`.
- **Line 513**: Alert default style `bg-[#fb326f]/10 border-[#fb326f]/30 text-[#fb326f]`.
- **Lines 585, 586, 591**: Form / Newsletter card border and button.
- **Lines 613, 620**: Banner block border and button.
- **Lines 640, 646**: Modal block icon and button.

Note: In `CssCompiler.php`, utility classes `.bg-hb-primary`, `.text-hb-primary`, `.border-hb-primary`, `.bg-hb-card`, `.border-hb-border` are already generated from `:root` CSS variables! Using either `var(--hb-primary)` or `bg-hb-primary` ensures that whatever theme is loaded, blocks automatically match its exact palette without hardcoded fallbacks to `#fb326f`.

#### G. `core/App.php`
- **Lines 525, 530, 541**: In `renderFallbackPostHtml`, replace `selection:bg-[#fb326f]` and `bg-[#fb326f]` with `var(--hb-primary)`.
- **Lines 584, 589, 595**: In `renderFallbackHomeHtml`, replace `selection:bg-[#fb326f]` and `bg-[#fb326f]` with `var(--hb-primary)`.

#### H. `core/Themes/Theme.php` & `ThemeService.php`
- Fallback arrays: Change the fallback color from `#fb326f` to a generic neutral or default theme color (e.g., `#f97316` or `#2563eb`), keeping `#fb326f` strictly reserved for the admin panel.

---

## Part 2: Requirement R4 — Theme Customizer & Live CSS Editor

### 2.1 Problem Statement & Architectural Flaws in Current Implementation
The current implementation of `/admin/themes/customize` in `core/Admin/ThemeController.php` suffers from three key issues:

1. **Minified Bundle Leaking into Editor (Recursion / Pollution Bug)**:
   In `ThemeController::customize` (line 205):
   ```php
   'custom_css' => $this->compiler->getCss($theme->getSlug()),
   ```
   `$this->compiler->getCss($slug)` reads `storage/cache/css/theme_{slug}.css`, which contains the entire compiled Tailwind bundle (500+ minified lines of utilities like `.-translate-x-1\/2`, `@media (min-width: 768px)`, etc.).
   When the user opens the Customizer, the "Zusätzliches CSS" textarea is filled with minified CSS instead of their own custom rules. If they save, `compileForTheme` appends this entire bundle back onto itself, duplicating CSS and degrading performance.
2. **Missing Code Editor Capabilities**:
   The current editor is a plain HTML `<textarea rows="4">` without line numbers, without indentation/Tab key support, and without clean code editing aesthetics.
3. **No Real-Time Custom CSS Live Preview**:
   While `updatePreview()` sets CSS variables on the iframe root, typing into the Custom CSS textarea does NOT update the iframe DOM in real time. The user must save and reload the entire page to see custom CSS changes.
4. **No Dedicated User Persistence**:
   User CSS is not persisted to an isolated file like `storage/custom-css/{theme}.css` or `themes/{theme}/custom.css`.
5. **Missing Safe Frontend `<head>` Injection**:
   Frontend themes do not independently inject user custom CSS into `<head>`, relying instead solely on the global compiled bundle `/assets/css/theme.css`.

### 2.2 Target Architecture for R4

```
                  ┌────────────────────────────────────────────────────────┐
                  │           /admin/themes/customize                      │
                  │   Theme Customizer & Live Studio (WordPress Style)      │
                  └──────────┬───────────────────────────────┬─────────────┘
                             │                               │
                [Left Sidebar: Code Editor]       [Right Area: Live Iframe Preview]
                - Monospace font (text-xs)        - Auto-injected <style id="live-custom-css">
                - Dynamic line numbers gutter     - Real-time keystroke DOM reflection
                - Scroll synchronization          - Live CSS variable reflection
                - Tab key indentation (2 spaces)  - Responsive device switchers (100%, 768, 375)
                - Enter key indent preservation   - Instant feedback with zero reload
                             │
                             ▼
                [One-Click "Änderungen speichern"]
                POST /admin/themes/save
                             │
                             ├─────────────────────────────────────────────────┐
                             ▼                                                 ▼
        storage/custom-css/{theme}.css                      themes/{theme}/theme.json
        (and themes/{theme}/custom.css)                     (tokens & typography)
                             │                                                 │
                             └────────────────────────┬────────────────────────┘
                                                      ▼
                                       CssCompiler::compileForTheme()
                                       storage/cache/css/theme_{slug}.css
                                                      │
                                                      ▼
                                       Cache::page()->flush()
                                                      │
                             ┌────────────────────────┴────────────────────────┐
                             ▼                                                 ▼
             [Frontend Themes <head> Injection]                [/assets/css/theme.css Route]
             <style id="theme-custom-css">                     Static bundle with custom CSS
             <?= ThemeService::getCustomCss($slug) ?>          ETag & Cache-Control headers
             </style>
```

### 2.3 Component Specifications

#### A. Dedicated Custom CSS Storage (`ThemeService.php`)
Add two methods to `ThemeService`:
```php
public function getCustomCss(string $slug): string
{
    $storageFile = (defined('HEARTBEAT_STORAGE') ? HEARTBEAT_STORAGE : dirname(__DIR__, 2) . '/storage') . '/custom-css/' . $slug . '.css';
    if (is_file($storageFile)) {
        return (string)file_get_contents($storageFile);
    }
    
    $themeFile = $this->themesDir . '/' . $slug . '/custom.css';
    if (is_file($themeFile)) {
        return (string)file_get_contents($themeFile);
    }
    
    return '';
}

public function saveCustomCss(string $slug, string $css): bool
{
    $dir = (defined('HEARTBEAT_STORAGE') ? HEARTBEAT_STORAGE : dirname(__DIR__, 2) . '/storage') . '/custom-css';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    
    $storageFile = $dir . '/' . $slug . '.css';
    $saved = @file_put_contents($storageFile, $css) !== false;
    
    $themeCustom = $this->themesDir . '/' . $slug . '/custom.css';
    if (is_dir(dirname($themeCustom))) {
        @file_put_contents($themeCustom, $css);
    }
    
    return $saved;
}
```

In `ThemeController::customize`:
Pass `$this->themes->getCustomCss($theme->getSlug())` instead of `$this->compiler->getCss($theme->getSlug())`. This completely eliminates the bundle pollution bug.

In `ThemeController::save`:
Call `$this->themes->saveCustomCss($theme->getSlug(), $customCss)` alongside saving `theme.json` settings and recompiling the bundle.

#### B. Code Editor with Line Numbers & Tab Handling
Implement a robust, dependency-free Alpine.js component directly within `ThemeController::customize`:
- **Line Numbers Gutter**:
  - Computed property or reactive array `lineCount`: dynamically calculated from `(config.custom_css.match(/\n/g) || []).length + 1`.
  - Displayed in a sticky, non-selectable gutter (`select-none text-right text-slate-500 font-mono text-xs`).
  - Textarea scroll synchronized with line numbers container: `@scroll="syncScroll($event)"`.
- **Tab Key Handling**:
  - `@keydown.tab.prevent="handleTab($event)"`: Inserts 2 spaces at current selection start and updates cursor position smoothly.
- **Enter Key Handling**:
  - Automatically maintains current indentation level on newline.
- **Styling**:
  - Monospace font (`font-mono`, text-xs, leading-5).
  - Background `#090d16`, border `#1e293b`, subtle syntax contrast.
  - Comfortable height (at least 280px–360px) in a dedicated Custom CSS card with clear visual framing.

#### C. Real-Time Iframe DOM Injection
In the Alpine.js `themeStudio` definition:
```javascript
updatePreviewCss() {
    try {
        const iframe = document.getElementById('themePreviewFrame');
        if (iframe && iframe.contentDocument) {
            const doc = iframe.contentDocument;
            let styleEl = doc.getElementById('live-custom-css');
            if (!styleEl) {
                styleEl = doc.createElement('style');
                styleEl.id = 'live-custom-css';
                doc.head.appendChild(styleEl);
            }
            styleEl.textContent = this.config.custom_css;
        }
    } catch (e) {
        // Handle cross-origin or frame access gracefully
    }
}
```
Every input into the textarea triggers `updatePreviewCss()` via `@input="updatePreviewCss()"`, applying the stylesheet in real-time to the active theme preview without reloading the iframe.

#### D. Automatic `<head>` Injection across Frontend Themes
In `themes/default/templates/header.php`, `themes/corporate/templates/header.php`, `themes/real-estate/templates/header.php`, `themes/startup/templates/header.php`, and `core/App.php` fallbacks:
```php
<?php
$customCss = isset($theme) ? ThemeService::getInstance()->getCustomCss($theme->getSlug()) : '';
?>
<?php if (!empty($customCss)): ?>
    <style id="theme-custom-css">
        <?= $customCss ?>
    </style>
<?php endif; ?>
```
This guarantees that:
1. Custom CSS is safely injected on every frontend page request.
2. The custom CSS is isolated per theme (`$theme->getSlug()`).
3. Automated tests inspecting the `<head>` of frontend responses can easily verify the presence of custom styles via `#theme-custom-css`.

---

## Part 3: Step-by-Step Implementation Plan for Implementer Agents

### Phase 1: R3 Theme Palette Decoupling
1. **Update `themes/default/theme.json`**:
   - Set `"primary": "#f97316"`, `"primary_hover": "#ea580c"`, `"accent": "#38bdf8"`.
2. **Update `themes/default/templates/header.php`, `home.php`, `404.php`**:
   - Replace hardcoded `#fb326f` and `#e01e5a` with Orange palette tokens or CSS variables (`var(--hb-primary)`).
3. **Update `themes/startup/templates/home.php`**:
   - Replace 7 instances of `#fb326f` with Rose/Violet palette tokens (`#f43f5e`, `rose-500`, `purple-500`).
4. **Update `core/Blocks/BlockRenderer.php`**:
   - Replace all 22 occurrences of `#fb326f` and `#e01e5a` with `var(--hb-primary)` and `var(--hb-primary-hover)`.
5. **Update `core/App.php` Fallbacks**:
   - Replace `#fb326f` in `renderFallbackPostHtml` and `renderFallbackHomeHtml` with dynamic theme tokens.

### Phase 2: R4 Theme Customizer & Live CSS Editor
1. **Add Custom CSS Storage in `core/Themes/ThemeService.php`**:
   - Implement `getCustomCss(string $slug): string` and `saveCustomCss(string $slug, string $css): bool`.
2. **Refactor `core/Admin/ThemeController.php`**:
   - Populate `custom_css` using `$this->themes->getCustomCss($slug)` (fixing the bundle pollution bug).
   - In `customize()`, replace the plain 4-row textarea with the enhanced Code Editor component (line numbers gutter, monospace font, tab indentation, scroll sync).
   - In `themeStudio` Alpine.js script, implement `updatePreviewCss()` for instant live DOM injection into the iframe.
   - In `save()`, persist custom CSS to `storage/custom-css/{theme}.css` and `themes/{theme}/custom.css`.
3. **Inject Custom CSS into `<head>` Across All Themes**:
   - Update `header.php` in `default`, `corporate`, `real-estate`, `startup`, and `App.php` fallbacks.
4. **Create Verification Tests in `tests/EnterpriseOverhaulTest.php`**:
   - Verify that theme custom CSS saves to disk and injects into `<head>`.
   - Verify that all themes have independent color schemes without `#fb326f`.

---

## Part 4: Risk Analysis & Mitigation

| Risk | Impact | Likelihood | Mitigation |
|---|---|---|---|
| Existing tests expecting specific colors | Medium | Low | `Phase4VerificationTest.php` already expects `#f97316` for One-Pager (`assert($onePager->getColor('primary') === '#f97316')`). Other phase tests check discovery and rendering without checking for `#fb326f`. |
| Customizer iframe cross-origin restriction | Low | Low | The preview iframe loads `/?theme_preview=...` from the same origin, so `iframe.contentDocument` is accessible without CORS issues. |
| Code editor performance with large CSS files | Low | Low | Alpine.js line numbers count computation is $O(N)$ string split on linebreaks; negligible overhead for CSS files under 10,000 lines. |
| Cache invalidation on custom CSS save | Medium | Low | `ThemeService::updateThemeSettings` already invokes `Cache::page()->flush()`. Calling `saveCustomCss` within that flow ensures instant cache invalidation. |
| Storage permissions on shared hosting | Low | Low | `storage/custom-css/` directory is created with `0755` permissions using existing `HEARTBEAT_STORAGE` pattern. |

---

## Conclusion
Requirements R3 and R4 are well-defined, structurally sound, and can be implemented with zero regressions to the existing 6 verification phases. The implementer can follow the explicit file paths and code snippets documented here.
