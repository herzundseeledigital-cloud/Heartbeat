# Handoff Report: Explorer 2 (Theme Color Schemes & Live CSS Customizer)
**Heartbeat CMS Enterprise Refinement (Requirements R3 & R4)**
**Agent**: Explorer 2 (Survey Phase)
**Working Directory**: `/Users/risinger/Documents/VSCode/Heartbeat/.agents/teamwork/teamwork_preview_explorer_survey_2`
**Date**: 2026-10-18T16:05:00Z
**Handoff Type**: Hard (Task Complete)

---

## 1. Observation

### R3 Observations (Theme Palette Decoupling from CMS CI `#fb326f`):
1. **CMS CI Accent**: `#fb326f` is the Heartbeat CMS admin brand color, defined in `tailwind.config.js` lines 20-25 (`brand: { 500: '#fb326f', DEFAULT: '#fb326f' }`).
2. **One-Pager Theme Palette**: In `themes/default/theme.json` lines 7-15:
   ```json
   "colors": {
       "primary": "#f97316",
       "primary_hover": "#e01e5a",
       "background": "#090d16",
       "card": "#0f172a",
       "border": "#1e293b",
       "text": "#f8fafc",
       "accent": "#38bdf8"
   }
   ```
   Note: `primary_hover` is `#e01e5a` (CMS brand hover). Furthermore, grep revealed 35+ occurrences of hardcoded `#fb326f` across One-Pager templates:
   - `themes/default/templates/header.php`: Line 42 (`selection:bg-[#fb326f]`), Line 49 (`from-[#fb326f] to-[#e01e5a]`), Lines 65, 76, 83 (`hover:text-[#fb326f]`), Line 92 (`bg-[#fb326f]`), Lines 119, 128, 135, 141.
   - `themes/default/templates/home.php`: Lines 16, 20, 21, 37, 48, 76, 110, 129, 136, 137, 147, 158, 175, 182, 188, 194, 200, 212, 223, 256, 265, 276, 287, 301, 386, 390, 396, 406, 410.
   - `themes/default/templates/404.php`: Line 8 (`bg-[#fb326f]/10 text-[#fb326f]`), Line 15 (`bg-[#fb326f] hover:bg-[#e01e5a]`).
3. **Modern Startup Theme**: In `themes/startup/theme.json` lines 7-15:
   ```json
   "colors": {
       "primary": "#8b5cf6",
       "primary_hover": "#7c3aed",
       "background": "#0c0a1a",
       "card": "#16122e",
       "border": "#271f4d",
       "text": "#faf5ff",
       "accent": "#f43f5e"
   }
   ```
   In `themes/startup/templates/home.php`, 7 lines contain hardcoded `#fb326f` (lines 16, 21, 27, 37, 49, 59, 107) in gradient stops and stat text.
4. **Corporate & Real Estate Themes**:
   - `themes/corporate/theme.json`: primary `#2563eb`, primary_hover `#1d4ed8`, accent `#38bdf8`. Grep for `#fb326f` in `themes/corporate` returned 0 results.
   - `themes/real-estate/theme.json`: primary `#059669`, primary_hover `#047857`, accent `#34d399`. Grep for `#fb326f` in `themes/real-estate` returned 0 results.
5. **Shared Block Renderer**: In `core/Blocks/BlockRenderer.php`, grep for `#fb326f` identified 22 occurrences hardcoded into block rendering (lines 150, 154, 203, 293, 295, 348, 352, 359, 396, 431, 435, 456, 458, 483, 513, 585, 586, 591, 613, 620, 640, 646).
6. **Core Fallbacks**: In `core/App.php` lines 525, 530, 541, 584, 589, 595, fallback rendering templates contain hardcoded `#fb326f`.

### R4 Observations (Theme Customizer & Live CSS Editor):
1. **Theme Customizer Route**: In `core/App.php` lines 152-153:
   - `GET /admin/themes/customize` &rarr; `Heartbeat\Admin\ThemeController::customize`
   - `POST /admin/themes/save` &rarr; `Heartbeat\Admin\ThemeController::save`
2. **Current Custom CSS Read Flaw**: In `core/Admin/ThemeController.php` lines 201-206:
   ```php
   $themeConfigJson = json_encode([
       'colors'     => $colors,
       'typography' => $typography,
       'settings'   => $settings,
       'custom_css' => $this->compiler->getCss($theme->getSlug()),
   ], ...);
   ```
   `$this->compiler->getCss($theme->getSlug())` reads `storage/cache/css/theme_{slug}.css`, returning the entire minified Tailwind CSS bundle into the editor instead of user-authored custom CSS snippets.
3. **Current Editor UI**: In `core/Admin/ThemeController.php` lines 371-373:
   `<textarea name="custom_css" x-model="config.custom_css" rows="4" placeholder="/* Eigene Tailwind oder CSS-Regeln */"...>`
   There is no line number gutter, no monospace syntax structure, and no Tab/indentation handling.
4. **Current Preview Mechanism**: In `core/Admin/ThemeController.php` lines 458-475:
   `updatePreview()` sets CSS properties on `iframe.contentDocument.documentElement.style`, but does NOT inject `config.custom_css` into the iframe in real time.
5. **Current Custom CSS Storage**: In `core/Themes/ThemeService.php` lines 200-205, custom CSS is passed to `CssCompiler::compileForTheme` and bundled into `storage/cache/css/theme_{slug}.css`. It is not stored in a discrete file at `storage/custom-css/{theme}.css` or `themes/{theme}/custom.css`.
6. **Current `<head>` Injection**: Themes link to `<link rel="stylesheet" href="/assets/css/theme.css">` and output `<style><?= $cssVars ?></style>`. None of the four themes inject a `<style id="theme-custom-css">` block containing user custom CSS directly into `<head>`.
7. **Test Environment**: Running `/Applications/XAMPP/xamppfiles/bin/php tests/Phase*Test.php` succeeded across all 6 phases (100% PASS). Note that `Phase4VerificationTest.php` line 106 specifically verifies that One-Pager's primary color is `#f97316` (`assert($onePager->getColor('primary') === '#f97316')`).

---

## 2. Logic Chain

1. **Decoupling Rationale**:
   - Observations 1.1 and 1.2 demonstrate that the One-Pager theme and Modern Startup theme contain hardcoded CMS brand colors (`#fb326f`). Observation 1.5 shows that `BlockRenderer.php` forces `#fb326f` onto every theme's blocks.
   - Therefore, to satisfy Requirement R3 ("Decouple frontend theme color schemes completely from the Heartbeat CMS admin CI (`#fb326f`)"):
     - `themes/default/theme.json` must standardize on Sunset Orange (`#f97316`, hover `#ea580c`), matching `Phase4VerificationTest.php`.
     - All hardcoded instances of `#fb326f` in `themes/default/templates/` must be replaced with orange tokens or CSS variable classes (`var(--hb-primary)`).
     - The 7 instances of `#fb326f` in `themes/startup/templates/home.php` must be replaced with startup's Rose accent (`#f43f5e`) or Violet stops.
     - `BlockRenderer.php` and `App.php` fallback templates must use dynamic CSS variables (`var(--hb-primary)` / `var(--hb-primary-hover)`) instead of static `#fb326f`.
   - Result: Each of the 4 themes possesses a distinct, cohesive palette (One-Pager: Orange `#f97316`; Corporate: Blue `#2563eb`; Real Estate: Emerald `#059669`; Startup: Violet `#8b5cf6`), and `#fb326f` remains exclusive to the admin backend.

2. **Custom CSS Architecture Rationale**:
   - Observation 2.2 reveals that reading `$this->compiler->getCss()` pollutes the Custom CSS textarea with the minified bundle.
   - Observation 2.5 shows that custom CSS lacks an isolated persistence target.
   - Therefore, `ThemeService` must introduce:
     - `getCustomCss(string $slug): string` (reads from `storage/custom-css/{slug}.css` with fallback to `themes/{slug}/custom.css`).
     - `saveCustomCss(string $slug, string $css): bool` (writes to `storage/custom-css/{slug}.css` and mirrors to `themes/{slug}/custom.css`).
   - In `ThemeController::customize`, `$this->themes->getCustomCss($slug)` will be passed to the view. This ensures the editor displays ONLY the user's custom CSS snippets.
   - In `ThemeController::save`, `$this->themes->saveCustomCss($slug, $customCss)` will persist the user's CSS before compiling.

3. **Code Editor & Live Preview Rationale**:
   - Observation 2.3 shows the editor is a basic 4-line textarea. Observation 2.4 shows custom CSS typing does not update the preview iframe.
   - Therefore, in `ThemeController.php`:
     - An Alpine.js-powered editor with dynamic line numbers gutter (`(config.custom_css.match(/\n/g) || []).length + 1`), scroll synchronization (`@scroll`), and Tab key handler (`@keydown.tab.prevent` inserting 2 spaces) provides a zero-dependency, lightweight code editor.
     - An `updatePreviewCss()` function in `themeStudio` dynamically creates or updates `<style id="live-custom-css">` in `themePreviewFrame.contentDocument.head` on every keystroke (`@input`).
   - Result: Instant, zero-reload visual feedback matching WordPress Customizer behavior.

4. **Frontend `<head>` Injection Rationale**:
   - Observation 2.6 shows themes lack `<style id="theme-custom-css">`.
   - Adding `<?php if (!empty($customCss)): ?><style id="theme-custom-css"><?= $customCss ?></style><?php endif; ?>` into `header.php` across all 4 themes and `App.php` guarantees immediate injection of user styles on all frontend pages, fulfilling acceptance criteria and automated test requirements.

---

## 3. Caveats

1. **Shared Hosting File Permissions**: `storage/custom-css/` must be created automatically with `0755` permissions using `HEARTBEAT_STORAGE` path resolution.
2. **Iframe Same-Origin Requirement**: Live DOM injection into `iframe.contentDocument` requires that the iframe URL (`/?theme_preview=...`) is served on the same origin. Since the admin runs on the same domain as the frontend, this works natively.
3. **Existing Phase 4 Test Assertion**: `tests/Phase4VerificationTest.php` line 106 requires `assert($onePager->getColor('primary') === '#f97316')`. The One-Pager palette MUST maintain `#f97316` as its primary color to avoid breaking existing verification tests.

---

## 4. Conclusion

Requirements R3 and R4 are thoroughly investigated and mapped out. Implementation requires modifications to:
1. `themes/default/theme.json` and templates in `themes/default/templates/` (`header.php`, `home.php`, `404.php`)
2. `themes/startup/templates/home.php`
3. `core/Blocks/BlockRenderer.php`
4. `core/App.php`
5. `core/Themes/ThemeService.php`
6. `core/Admin/ThemeController.php`
7. `themes/*/templates/header.php`

All proposed changes preserve full compatibility with existing tests and enable the creation of the target test suite `tests/EnterpriseOverhaulTest.php`.

---

## 5. Verification Method

1. **Verify Existing Tests**:
   Run all existing phase test suites using the system PHP binary:
   ```bash
   for t in tests/Phase*Test.php; do /Applications/XAMPP/xamppfiles/bin/php $t; done
   ```
2. **Verify Theme Decoupling (R3)**:
   Grep `themes/` and `core/Blocks/BlockRenderer.php` to verify zero instances of `#fb326f`:
   ```bash
   grep -rn "#fb326f" themes/default/ themes/startup/ core/Blocks/BlockRenderer.php
   ```
   (Expected: No matches in frontend themes or BlockRenderer).
3. **Verify Custom CSS Persistence & Injection (R4)**:
   - Save custom CSS for a theme via `ThemeService::saveCustomCss('corporate', '.custom-test { color: red; }')`.
   - Check that `storage/custom-css/corporate.css` exists on disk and contains `.custom-test { color: red; }`.
   - Request frontend homepage (`GET /`) and inspect HTML output:
     `<style id="theme-custom-css">` must be present inside `<head>`.
4. **Invalidation Conditions**:
   - Any failure in `Phase1Test.php` through `Phase6Test.php`.
   - Presence of `#fb326f` in rendered frontend theme output when active theme is Corporate, Real Estate, or Startup.
   - Textarea in `/admin/themes/customize` containing minified CSS bundles instead of clean custom CSS.
