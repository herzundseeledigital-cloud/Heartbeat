# Codebase Investigation & Survey Report: Requirements R5, R6, R7, R8 & Enterprise Testing

**Date**: 2026-10-18  
**Investigator**: Explorer 3 (Heartbeat CMS Enterprise Refinement)  
**Working Directory**: `/Users/risinger/Documents/VSCode/Heartbeat/.agents/teamwork/teamwork_preview_explorer_survey_3`  
**PHP Binary**: `/Applications/XAMPP/xamppfiles/bin/php` (PHP 8.2.4 CLI)  

---

## Executive Summary

This report delivers a comprehensive, line-by-line codebase survey of Heartbeat CMS for:
- **R5**: Privacy-first GDPR-compliant local Google Fonts downloader in admin (`/admin/appearance/fonts` or `/admin/fonts`) with live previews, multi-select download to `/public/assets/fonts/`, zero runtime Google CDN requests, and one-click theme typography assignment.
- **R6**: Global site-wide SEO & Webmaster verifications in `/admin/seo` (global indexing switch `index, follow` vs `noindex, nofollow`, admin bar warning banner, Google Search Console & Bing Webmaster tokens, dynamic `/sitemap.xml` and `/robots.txt`).
- **R7**: Granular SEO & Social Meta controls in `/admin/content/edit/{id}` (Custom SEO title, description with live SERP preview & counter, individual `noindex`/`nofollow` toggles, canonical URL override).
- **R8 & Enterprise Testing**: `SOURCES.md` open source & license compliance, and `tests/EnterpriseOverhaulTest.php` architecture and verification criteria.

---

## 1. Requirement R5: Local Google Fonts Downloader & Typography System

### 1.1 Current Architecture & Code Observations
1. **Engine**: `core/Gdpr/GoogleFontsDownloader.php` (Lines 1-207)
   - Method `downloadFont(string $urlOrFamily): array` (Lines 39-167):
     - Takes a family name or Google Fonts CSS URL.
     - Issues a cURL request with modern User-Agent (`Chrome/120.0.0.0 Safari/537.36`) to `https://fonts.googleapis.com/css2?family={family}:wght@300;400;500;600;700;800&display=swap`.
     - Extracts `.woff2` URLs using `preg_match_all('/url\((https:\/\/[^)]+\.woff2)\)/', $rawCss, $fontUrls)`.
     - Downloads each binary file to `public/assets/fonts/{fontSlug}_{hash}.woff2`.
     - Replaces remote URLs with local path `/assets/fonts/{fontSlug}_{hash}.woff2`.
     - Writes local `@font-face` CSS bundle to `public/assets/fonts/{fontSlug}.css`.
   - Method `listLocalFonts(): array` (Lines 173-192):
     - Scans `public/assets/fonts/*.css` using `glob()`.
     - Returns slug, css_file path, css_url (`/assets/fonts/{slug}.css`), and modified timestamp.

2. **Current Limitations & Deficiencies**:
   - **Route & Navigation**: Currently only reachable via `/admin/gdpr` (tab `'fonts'`) in `core/Admin/AgencyModulesController.php:952-1038`. No route exists for `/admin/appearance/fonts` or `/admin/fonts`. It is not present under the "Appearance" group in `core/Admin/AdminView.php:465-483`.
   - **UI Deficiency**: `/admin/gdpr` only has a single text input (`font_input`). There is **no font catalog**, no visual previews, no multi-select capability, and no one-click theme assignment.
   - **cURL SSL Issue in Local / XAMPP**: In `GoogleFontsDownloader.php:74-88`, `CURLOPT_SSL_VERIFYPEER` is not configured. On macOS / XAMPP environments without system certificate bridge, cURL fails with `SSL certificate problem: unable to get local issuer certificate`. A fallback or `CURLOPT_SSL_VERIFYPEER => false` (with optional offline mocking for tests) is needed.
   - **External Google Font Leaks**:
     - `public/assets/css/installer.css:6`: Contains `@import url("https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Material+Symbols+Outlined:...");`.
     - `public/assets/css/admin.css:6`: Same external import.
     - Because theme templates (`themes/default/templates/header.php:38`, `themes/corporate/templates/header.php:33`, etc.) include `<link rel="stylesheet" href="/assets/css/installer.css">`, frontend requests leak HTTP requests to `fonts.googleapis.com` unless resolved!
   - **Theme Typography Connection**:
     - `core/Themes/Theme.php:50-53` stores `typography['font_family']` and `typography['heading_font']`.
     - `Theme::getCssVariables()` (Lines 159-174) outputs `--hb-font-family: {family}, sans-serif;` and `--hb-heading-font: {heading}, sans-serif;`.
     - However, neither the theme templates nor `ThemeService` automatically inject the local font `@font-face` stylesheet (`/assets/fonts/{slug}.css`) into `<head>`.

### 1.2 Required Changes for R5
1. **Catalog Definition**:
   Define a curated list of high-quality, commercially permissible open-source Google Fonts (SIL OFL 1.1 / Apache 2.0):
   - **Inter** (Rasmus Andersson / SIL OFL 1.1) - Sans-Serif
   - **Roboto** (Google / Apache 2.0) - Sans-Serif
   - **Poppins** (Indian Type Foundry / SIL OFL 1.1) - Geometric Sans
   - **Playfair Display** (Claus Eggers Sørensen / SIL OFL 1.1) - Editorial Serif
   - **Montserrat** (Julieta Ulanovsky / SIL OFL 1.1) - Geometric Sans
   - **Plus Jakarta Sans** (Tokotype / SIL OFL 1.1) - Modern Agency Sans
   - **Outfit** (Onsen Studio / SIL OFL 1.1) - Geometric Display
   - **Lora** (Cyreal / SIL OFL 1.1) - Contemporary Serif
2. **Dedicated Admin Fonts Controller & View (`/admin/appearance/fonts` & `/admin/fonts`)**:
   - Register routes in `core/App.php`:
     - `GET /admin/appearance/fonts` -> `ThemeController::fonts()`
     - `GET /admin/fonts` -> redirect or alias to `/admin/appearance/fonts`
     - `POST /admin/appearance/fonts/download` -> batch or single download action
     - `POST /admin/appearance/fonts/assign` -> one-click assignment to active theme
   - Add sidebar navigation item under `Appearance` in `core/Admin/AdminView.php`:
     ```php
     [
         'slug'       => 'fonts',
         'label'      => 'Lokale Fonts',
         'url'        => '/admin/appearance/fonts',
         'icon'       => 'font_download',
         'permission' => 'manage_themes',
     ]
     ```
3. **Interactive UI with Live Previews & Multi-Select**:
   - Typography Specimen Card for each font family:
     - Shows family name, category, license (OFL / Apache 2.0).
     - Live preview text: "The quick brown fox jumps over the lazy dog", "Ag 0123456789", specimen pangrams.
     - Multi-select checkbox: `selected_fonts[]`.
     - Download button: "Herunterladen & lokal hosten".
     - Status badge: "Lokal installiert" (when `.woff2` and `.css` exist on disk).
     - Assignment buttons for installed fonts:
       - "Als Fließtext setzen" (sets active theme `font_family`)
       - "Als Überschrift setzen" (sets active theme `heading_font`)
   - Batch Action: "Ausgewählte Schriftarten herunterladen" (Multi-Download).
   - Custom font family / Google Fonts URL input for arbitrary Google Fonts.
4. **Theme Assignment & Frontend Head Injection**:
   - In `ThemeController::assignFont()`:
     - Read target (`body` or `heading`) and font name.
     - Call `$theme = $themeService->getActive();`
     - Call `$theme->setTypography($target === 'heading' ? 'heading_font' : 'font_family', $fontName);`
     - Save manifest `$theme->save();` and recompile `$themeService->compileActiveThemeCss();`.
   - In `themes/*/templates/header.php` and `core/App.php`:
     - Determine active theme body and heading font slugs.
     - Inject `<link rel="stylesheet" href="/assets/fonts/{slug}.css">` into `<head>` if local file exists.
5. **Eliminating External Font Requests (100% DSGVO-compliant)**:
   - In `public/assets/css/installer.css` and `public/assets/css/admin.css`: remove `@import url("https://fonts.googleapis.com/...");` and use system font fallbacks or pre-installed local `@font-face`.

---

## 2. Requirement R6: Global Site-Wide SEO & Webmaster Verifications

### 2.1 Current Architecture & Code Observations
1. **Service**: `core/Seo/SeoService.php` (Lines 1-214)
   - `renderMetaTags(array $post, string $siteTitle, string $siteUrl): string` (Lines 39-118):
     - Renders `<title>`, `<meta name="description">`, `<meta name="robots">`, `<link rel="canonical">`, OpenGraph tags, Twitter tags, and Schema.org JSON-LD.
     - Line 69-70: Currently hardcoded to check only `$meta['_seo_noindex']`. If set: `'noindex, nofollow'`, else `'index, follow, max-image-preview:large'`.
     - **Missing**: Does not check global site indexing option (`seo_indexing`).
     - **Missing**: Does not render Google Search Console verification meta tag.
     - **Missing**: Does not render Bing Webmaster Tools verification meta tag.
   - `generateSitemap(): string` (Lines 168-211):
     - Queries all published posts: `SELECT slug, post_type, updated_at, published_at FROM {prefix}posts WHERE status = 'publish'`.
     - **Missing**: Does not check global indexing flag (if site is noindex, sitemap should be empty or blocked).
     - **Missing**: Does not exclude items where `_seo_noindex = 1`.
   - **Missing `robots.txt`**: `SeoService` has NO method to generate `robots.txt`, and `core/App.php` has no route for `/robots.txt`.

2. **Controller**: `core/Admin/AgencyModulesController.php::seo()` (Lines 289-424)
   - Renders tabs: `seo` (default title, description, og image), `sitemap` (view sitemap link), `ai` (Gemini/OpenAI settings).
   - Saves to `{prefix}options`:
     - `seo_default_title`
     - `seo_default_description`
     - `seo_default_og_image`
   - **Missing**:
     - Global indexing switch (`index, follow` vs `noindex, nofollow`).
     - Field for Google Search Console verification (`seo_google_verification`).
     - Field for Bing Webmaster Tools verification (`seo_bing_verification`).

3. **Admin Shell**: `core/Admin/AdminView.php` (Lines 147-210)
   - Topbar renders breadcrumbs, preview link, and user profile menu.
   - **Missing**: No warning banner when `seo_indexing === 'noindex'`.

### 2.2 Required Changes for R6
1. **Option Storage in `{prefix}options`**:
   - `seo_indexing`: `'index'` (Live: index, follow) vs `'noindex'` (Staging/Dev: noindex, nofollow). Default: `'index'`.
   - `seo_google_verification`: Token string or meta tag content for Google Search Console (e.g. `google-site-verification=abc...` or raw verification string).
   - `seo_bing_verification`: Token string or meta tag content for Bing Webmaster Tools (`msvalidate.01`).

2. **Admin View `/admin/seo` Form Enhancements**:
   - In `AgencyModulesController.php::seo()`:
     - Add **Global Site Indexing Switch**:
       - Radio / Select: `index` ("Live-Modus (index, follow) — Vollständige Indexierung erlaubt") vs `noindex` ("Staging- & Entwicklungsmodus (noindex, nofollow) — Suchmaschinen blockieren").
     - Add **Google Search Console Integration**:
       - Text input `seo_google_verification` with helper text: "Verifizierungs-Token oder vollständiger Meta-Tag (`<meta name="google-site-verification" content="...">`)".
     - Add **Bing Webmaster Tools Integration**:
       - Text input `seo_bing_verification` with helper text: "Verifizierungs-Token oder Meta-Tag (`<meta name="msvalidate.01" content="...">`)".
     - Save all 3 options in POST handler (Lines 302-306).

3. **Prominent Admin Bar Warning Banner**:
   - In `core/Admin/AdminView.php::renderShell()`:
     - Query `seo_indexing` from `{prefix}options`.
     - When `seo_indexing === 'noindex'`:
       Inject a prominent amber/rose banner right below the topbar or in the shell header:
       ```html
       <div class="bg-amber-500/15 border-b border-amber-500/30 text-amber-300 px-4 sm:px-8 py-2.5 text-xs font-semibold flex items-center justify-between">
           <div class="flex items-center space-x-2">
               <span class="material-symbols-outlined text-[18px] text-amber-400">warning</span>
               <span><strong>Suchmaschinen-Indexierung blockiert:</strong> Website ist im Staging-Modus (<code>noindex, nofollow</code>).</span>
           </div>
           <a href="/admin/seo" class="underline text-amber-200 hover:text-white text-[11px]">In SEO-Einstellungen ändern &rarr;</a>
       </div>
       ```

4. **Meta Tag Generation in `SeoService::renderMetaTags()`**:
   - Check `seo_indexing`:
     ```php
     $globalIndex = (string)$this->db->fetchColumn("SELECT option_value FROM {prefix}options WHERE option_name = 'seo_indexing'") ?: 'index';
     if ($globalIndex === 'noindex') {
         $robots = 'noindex, nofollow';
     } else {
         // evaluate per-post noindex / nofollow
     }
     ```
   - Inject Google Search Console verification:
     ```php
     $googleVer = trim((string)$this->db->fetchColumn("SELECT option_value FROM {prefix}options WHERE option_name = 'seo_google_verification'"));
     if ($googleVer !== '') {
         if (preg_match('/content=["\']([^"\']+)["\']/', $googleVer, $m)) $googleVer = $m[1];
         $tags[] = '<meta name="google-site-verification" content="' . Security::e($googleVer) . '">';
     }
     ```
   - Inject Bing Webmaster verification:
     ```php
     $bingVer = trim((string)$this->db->fetchColumn("SELECT option_value FROM {prefix}options WHERE option_name = 'seo_bing_verification'"));
     if ($bingVer !== '') {
         if (preg_match('/content=["\']([^"\']+)["\']/', $bingVer, $m)) $bingVer = $m[1];
         $tags[] = '<meta name="msvalidate.01" content="' . Security::e($bingVer) . '">';
     }
     ```

5. **Dynamic `robots.txt` Route & Generator**:
   - In `core/Seo/SeoService.php`, add `generateRobotsTxt(): string`:
     ```php
     public function generateRobotsTxt(): string
     {
         $siteUrl = rtrim((string)$this->db->fetchColumn("SELECT option_value FROM {prefix}options WHERE option_name = 'site_url'") ?: 'http://localhost:8000', '/');
         $globalIndex = (string)$this->db->fetchColumn("SELECT option_value FROM {prefix}options WHERE option_name = 'seo_indexing'") ?: 'index';

         if ($globalIndex === 'noindex') {
             return "User-agent: *\nDisallow: /\n";
         }

         return "User-agent: *\nAllow: /\n\nSitemap: {$siteUrl}/sitemap.xml\n";
     }
     ```
   - In `core/App.php`: Register route:
     ```php
     $this->router->get('/robots.txt', function (Request $r) {
         $txt = SeoService::getInstance()->generateRobotsTxt();
         return new Response($txt, 200, [
             'Content-Type'  => 'text/plain; charset=UTF-8',
             'Cache-Control' => 'public, max-age=3600',
         ]);
     });
     ```

6. **Honoring Indexation Flag in `generateSitemap()`**:
   - If `seo_indexing === 'noindex'`, return empty `<urlset>` with comment.
   - For individual posts, exclude posts that have `_seo_noindex = 1` in `{prefix}meta_data`.

---

## 3. Requirement R7: Granular SEO Controls for Content Items

### 3.1 Current Architecture & Code Observations
1. **Controller**: `core/Admin/ContentController.php` (Lines 1-1486)
   - Method `edit(Request $request, array $params = []): Response` (Lines 291-314):
     - Finds post by ID, hydrates meta via `$post['meta']`, calls `renderEditor($post, $cpt, $blocks, $meta)`.
   - Method `save(Request $request, array $params = []): Response` (Lines 319-398):
     - Lines 348-368: Custom meta handling currently reads:
       ```php
       $metaInputs = (array)$request->post('meta', []);
       $metaValues = [];
       if ($request->post('featured_image')) {
           $metaValues['_thumbnail_url'] = (string)$request->post('featured_image');
       }
       if ($request->post('excerpt')) {
           $metaValues['_excerpt'] = (string)$request->post('excerpt');
       }
       // Only loops through CPT registered meta fields!
       ```
     - **Deficiency**: Does NOT extract or persist `_seo_title`, `_seo_description`, `_seo_noindex`, `_seo_nofollow`, or `_seo_canonical`!
   - Method `renderEditor()` (Lines 467-1325):
     - Lines 1167-1211: Document Settings tab in Inspector only contains:
       - Status dropdown
       - Parent page select
       - Featured image picker
       - Excerpt textarea
       - CPT custom meta fields
     - **Deficiency**: Completely missing the SEO & Social Meta panel.

2. **Service**: `core/Seo/SeoService.php` (Lines 44-71)
   - Checks `$meta['_seo_title']` for title fallback.
   - Checks `$meta['_seo_description']` for description fallback.
   - Checks `$meta['_seo_noindex']` for robots.
   - **Deficiency**: Does NOT check `$meta['_seo_nofollow']`.
   - **Deficiency**: Hardcodes canonical URL generation without checking `$meta['_seo_canonical']`.

### 3.2 Required Changes for R7
1. **UI Panel in `ContentController::renderEditor()`**:
   Add an expandable "SEO & Suchmaschinen-Optimierung" accordion card in the Inspector's Document tab (or below editor canvas):
   - **Custom SEO Title**:
     - Input `name="meta[_seo_title]"`
     - Fallback placeholder dynamically derived from post title (`{$post['title']}`).
   - **Custom Meta Description**:
     - Textarea `name="meta[_seo_description]"` rows="3"
     - Character counter (e.g. `x-data="{ desc: '...' }"` -> `<span x-text="desc.length + ' / 160 Zeichen'"></span>`).
   - **Live Google SERP Snippet Preview**:
     - Card styled like a Google search engine result:
       - Favicon + Site Title + Breadcrumb URL
       - Headline in Google Blue (`#1a0dab` or `#8ab4f8` for dark mode) showing custom title or fallback title.
       - Snippet text in slate-300 showing custom description or truncated content excerpt.
   - **Robots Directives**:
     - Checkbox `name="meta[_seo_noindex]"`: "Suchmaschinen anweisen, diesen Beitrag nicht zu indexieren (`noindex`)"
     - Checkbox `name="meta[_seo_nofollow]"`: "Suchmaschinen anweisen, Links in diesem Beitrag nicht zu folgen (`nofollow`)"
   - **Canonical URL Override**:
     - Input `name="meta[_seo_canonical]"`
     - Placeholder showing the auto-computed canonical URL.

2. **Persistence in `ContentController::save()`**:
   Add explicit extraction and sanitization of SEO meta values:
   ```php
   $metaValues['_seo_title'] = trim((string)($metaInputs['_seo_title'] ?? ''));
   $metaValues['_seo_description'] = trim((string)($metaInputs['_seo_description'] ?? ''));
   $metaValues['_seo_noindex'] = !empty($metaInputs['_seo_noindex']) ? 1 : 0;
   $metaValues['_seo_nofollow'] = !empty($metaInputs['_seo_nofollow']) ? 1 : 0;
   $metaValues['_seo_canonical'] = trim((string)($metaInputs['_seo_canonical'] ?? ''));
   ```
   `PostService::update($id, $postData, $metaValues)` and `PostService::create($postData, $metaValues)` then automatically store these in `{prefix}meta_data` via `MetaService::setMultiple()`.

3. **Rendering in `SeoService::renderMetaTags()`**:
   - **Canonical Override**:
     ```php
     $customCanonical = trim((string)($meta['_seo_canonical'] ?? ''));
     if ($customCanonical !== '') {
         $canonicalUrl = $customCanonical;
     }
     ```
   - **Individual Robots Directives**:
     ```php
     $itemNoindex = !empty($meta['_seo_noindex']);
     $itemNofollow = !empty($meta['_seo_nofollow']);
     if ($itemNoindex && $itemNofollow) {
         $robots = 'noindex, nofollow';
     } elseif ($itemNoindex) {
         $robots = 'noindex, follow';
     } elseif ($itemNofollow) {
         $robots = 'index, nofollow';
     } else {
         $robots = 'index, follow, max-image-preview:large';
     }
     ```

---

## 4. Requirement R8 & Enterprise Overhaul Testing

### 4.1 `SOURCES.md` Legal & Open Source Compliance
- **File Location**: `/Users/risinger/Documents/VSCode/Heartbeat/SOURCES.md` in project root.
- **Required Content**:
  1. **Legal Framework & Open Source License Policy**:
     - 100% commercially permissible open-source software (MIT, Apache 2.0, SIL Open Font License 1.1, BSD-3-Clause).
     - Full compliance with German DSGVO / EU GDPR (zero runtime 3rd party CDN requests, cookieless telemetry, server-side data residency).
  2. **Core Third-Party Libraries & Software**:
     - **Alpine.js**: MIT License (Caleb Porzio) - `/public/assets/js/alpine.min.js`.
     - **Tailwind CSS Concepts**: MIT License (Tailwind Labs) - JIT compilation implementation in `core/Themes/CssCompiler.php`.
     - **PHP & SQLite**: Standard PHP 3.01 / Public Domain PDO driver.
  3. **Typography & Font Assets (OFL & Apache 2.0)**:
     - **Inter**: SIL Open Font License 1.1 (Rasmus Andersson).
     - **Roboto**: Apache License 2.0 (Google / Christian Robertson).
     - **Poppins**: SIL Open Font License 1.1 (Indian Type Foundry).
     - **Playfair Display**: SIL Open Font License 1.1 (Claus Eggers Sørensen).
     - **Montserrat**: SIL Open Font License 1.1 (Julieta Ulanovsky).
     - **Plus Jakarta Sans**: SIL Open Font License 1.1 (Tokotype).
     - **Outfit**: SIL Open Font License 1.1 (Onsen Studio).
     - **Lora**: SIL Open Font License 1.1 (Cyreal).
     - **Google Material Symbols Outlined**: Apache License 2.0 (Google).
  4. **Attribution & Architectural Design Inspirations**:
     - WordPress Gutenberg visual canvas & customizer design patterns.
     - Modern headless hybrid CMS architecture.

### 4.2 `tests/EnterpriseOverhaulTest.php` Architecture & Test Matrix
- **Test Runner**: `/Applications/XAMPP/xamppfiles/bin/php tests/EnterpriseOverhaulTest.php`
- **Execution Model**: Follows repo standard (like `Phase4VerificationTest.php`, `Phase5VerificationTest.php`, `Phase6VerificationTest.php`):
  - Self-contained CLI test with isolated SQLite database (`storage/test_enterprise_overhaul.sqlite`).
  - Bootstraps Heartbeat core via `core/Bootstrap.php` or `core/Autoloader.php`.
  - Runs all migrations via `MigrationRunner`.
  - Seeds admin user and test pages/posts.

- **7 Mandatory Verification Suites**:
  | Test Case | Verification Target | Assertion & Invalidation Condition |
  |---|---|---|
  | **Test 1: Widescreen Editor Canvas Layout** | `AdminView.php` and `ContentController.php` | Verify editor screen does not contain rigid `max-w-7xl` wrapper. Confirms canvas uses `max-w-[1800px]` or fluid `w-full`. |
  | **Test 2: All Admin Routes HTTP 200** | Full Route Audit | Authenticates as Admin. Dispatches GET requests to `/admin`, `/admin/content`, `/admin/pages`, `/admin/posts`, `/admin/media`, `/admin/themes`, `/admin/themes/customize`, `/admin/appearance/fonts`, `/admin/seo`, `/admin/users`, `/admin/settings`, `/admin/gdpr`, `/admin/custom-code`, `/admin/analytics`, `/admin/mail`, `/admin/backups`, `/admin/updater`, `/admin/payments`, and `/install`. All must return HTTP 200 without PHP notices or warnings. |
  | **Test 3: Theme Custom CSS Lifecycle** | Customizer Save & Frontend Injection | Saves custom CSS snippet via `/admin/themes/save` or customizer. Fetches frontend homepage (`/`) or `/assets/css/theme.css`. Asserts custom CSS rules are compiled and present in response. |
  | **Test 4: Local Font Downloader & CSS Generation** | `GoogleFontsDownloader` | Executes local font downloading. Verifies `.woff2` files are written to `public/assets/fonts/` and valid `@font-face` CSS is written to `public/assets/fonts/{slug}.css` without containing any external URL (`fonts.googleapis.com` or `fonts.gstatic.com`). |
  | **Test 5: Global & Granular `noindex` Rendering** | `SeoService` & Frontend HTML | When global `seo_indexing = 'noindex'`, frontend `<head>` renders `<meta name="robots" content="noindex, nofollow">`. When global is `index`, a post with `_seo_noindex = 1` renders `noindex, follow` (or `noindex, nofollow`), and normal post renders `index, follow`. |
  | **Test 6: Search Console & Bing Webmaster Tags** | `SeoService` & Frontend HTML | Sets `seo_google_verification = 'test-gsc-token'` and `seo_bing_verification = 'test-bing-token'`. Verifies frontend `<head>` renders `<meta name="google-site-verification" content="test-gsc-token">` and `<meta name="msvalidate.01" content="test-bing-token">`. |
  | **Test 7: `SOURCES.md` Compliance Documentation** | File System Audit | Asserts `/Users/risinger/Documents/VSCode/Heartbeat/SOURCES.md` exists and contains required sections for MIT, Apache 2.0, SIL OFL 1.1, font attributions, and GDPR compliance notes. |

---

## 5. Risk Assessment & Key Dependencies

1. **Network Connectivity & Offline Test Resiliency**:
   - In automated test runners (or offline build environments), downloading fonts from Google might encounter network timeouts or SSL CA certificate mismatches.
   - *Mitigation*: Ensure `GoogleFontsDownloader` has fallback handling (or offline fixture mode) for test runs so `EnterpriseOverhaulTest.php` executes deterministically regardless of outbound internet connectivity.
2. **Font File Decontamination in Frontend CSS**:
   - Leaving `@import url("https://fonts.googleapis.com/...")` in `installer.css` or `admin.css` creates a GDPR violation under Munich regional court rulings (LG München, Az. 3 O 17493/20).
   - *Mitigation*: Strip external `@import` and ensure only locally hosted fonts in `/public/assets/fonts/` are loaded.
3. **Database Schema Compatibility**:
   - All options (`seo_indexing`, `seo_google_verification`, `seo_bing_verification`) use the existing `{prefix}options` table.
   - All granular post meta (`_seo_title`, `_seo_description`, `_seo_noindex`, `_seo_nofollow`, `_seo_canonical`) use the existing `{prefix}meta_data` table.
   - No database schema migrations are required; zero breaking changes to database structure.
