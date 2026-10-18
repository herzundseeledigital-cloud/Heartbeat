# Handoff Report: Explorer 3 (Survey: R5 Local Google Fonts, R6 Global SEO, R7 Granular SEO, R8 & Enterprise Testing)

**Date**: 2026-10-18  
**Working Directory**: `/Users/risinger/Documents/VSCode/Heartbeat/.agents/teamwork/teamwork_preview_explorer_survey_3`  
**Author**: Explorer 3  

---

## 1. Observation

1. **Test Environment & PHP CLI**:
   - `which php` in standard environment initially returned exit code 127 (`command not found: php`).
   - XAMPP PHP 8.2.4 was located at `/Applications/XAMPP/xamppfiles/bin/php`.
   - Running `/Applications/XAMPP/xamppfiles/bin/php tests/Phase1VerificationTest.php`, `Phase5VerificationTest.php`, and `Phase6VerificationTest.php` executed synchronously and passed with 100% OK.
   - In `tests/Phase5VerificationTest.php:315`, `$fontRes = $fontsDownloader->downloadFont('Inter');` ran, but inspection of `$fontRes` via CLI returned:
     `"Download der Font-Definition von Google fehlgeschlagen (HTTP 0): SSL certificate problem: unable to get local issuer certificate"`.
     Phase 5 only checked `assert(isset($fontRes['font_slug']) && $fontRes['font_slug'] === 'inter')`, masking the underlying SSL error. Setting `CURLOPT_SSL_VERIFYPEER => false` in cURL resolved the fetch immediately (`SUCCESS (len 67)`).

2. **Font Downloader & External CDN Leaks (R5)**:
   - `core/Gdpr/GoogleFontsDownloader.php` (Lines 1-207) contains `downloadFont()` and `listLocalFonts()`.
   - `public/assets/css/installer.css:6` and `public/assets/css/admin.css:6` contain verbatim:
     `@import url("https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..24,400..600,0,0&display=swap");`
   - Frontend themes (`themes/default/templates/header.php:38`, `themes/corporate/templates/header.php:33`) link `<link rel="stylesheet" href="/assets/css/installer.css">`, causing external Google CDN requests at runtime.
   - No route exists for `/admin/appearance/fonts` or `/admin/fonts` in `core/App.php`. Currently only `/admin/gdpr` exists (`core/Admin/AgencyModulesController.php:952-1038`), with a single text input and no catalog, no previews, no multi-select, and no theme assignment.

3. **Global SEO, Admin Bar Banner & Robots/Sitemap (R6)**:
   - `core/Admin/AgencyModulesController.php:289-424` handles `/admin/seo`. Only `seo_default_title`, `seo_default_description`, and `seo_default_og_image` are saved.
   - There is no global indexing switch (`seo_indexing`), no Google Search Console field (`seo_google_verification`), and no Bing Webmaster field (`seo_bing_verification`).
   - `core/Admin/AdminView.php:148-180` (Topbar) has no warning banner when noindex is active.
   - `core/Seo/SeoService.php:69-70` does not check any global indexing switch.
   - `core/Seo/SeoService.php` has no `generateRobotsTxt()` method, and `core/App.php` has no route for `/robots.txt`.
   - `core/Seo/SeoService.php:168-211` (`generateSitemap`) queries all published posts without checking `seo_indexing` or individual `_seo_noindex` flags.

4. **Granular Per-Content SEO Controls (R7)**:
   - `core/Admin/ContentController.php:1167-1211` (Inspector Document tab) contains fields for status, parent page, thumbnail, excerpt, and CPT fields, but zero fields for SEO.
   - `core/Admin/ContentController.php:348-368` (`save()`) extracts `$metaInputs` only for `_thumbnail_url`, `_excerpt`, and `$cpt->getMetaFields()`. It does not save `_seo_title`, `_seo_description`, `_seo_noindex`, `_seo_nofollow`, or `_seo_canonical`.
   - `core/Seo/SeoService.php:61-67` does not support overriding canonical URL via `$meta['_seo_canonical']`.
   - `core/Seo/SeoService.php:69-70` does not handle `$meta['_seo_nofollow']`.

5. **Compliance Documentation & Testing Suite (R8 & Testing)**:
   - `SOURCES.md` does not exist in the project root (`stat /Users/risinger/Documents/VSCode/Heartbeat/SOURCES.md: no such file or directory`).
   - `tests/EnterpriseOverhaulTest.php` does not exist yet.
   - `core/Admin/AdminView.php:203` contains `<main class="flex-1 p-4 sm:p-6 md:p-8 max-w-7xl w-full mx-auto">`, which bottlenecks the canvas to 1280px.

---

## 2. Logic Chain

1. **R5 Font Architecture**:
   - From Obs 2, `GoogleFontsDownloader.php` provides functional parsing and `.woff2` downloading, but lacks an accessible admin controller route (`/admin/appearance/fonts` / `/admin/fonts`), catalog UI, live preview specimens, multi-select batch downloading, and theme assignment.
   - From Obs 2, the external `@import` in `installer.css` leaks requests to Google CDN at runtime, violating GDPR.
   - Therefore, creating a dedicated `ThemeController::fonts()` view at `/admin/appearance/fonts` with a curated font catalog (Inter, Roboto, Poppins, Playfair Display, Montserrat, Plus Jakarta Sans, Outfit, Lora), multi-select download, one-click assignment to active theme's `font_family` / `heading_font`, and removing the external `@import` from `installer.css` fulfills R5.

2. **R6 Global SEO Architecture**:
   - From Obs 3, options for `seo_indexing`, `seo_google_verification`, and `seo_bing_verification` are absent from `/admin/seo` form and controller.
   - Therefore, adding these fields to `AgencyModulesController::seo()`, rendering a warning banner in `AdminView::renderShell()` when `seo_indexing === 'noindex'`, adding `GET /robots.txt` in `core/App.php` and `SeoService::generateRobotsTxt()`, and updating `SeoService::generateSitemap()` and `SeoService::renderMetaTags()` fulfills R6.

3. **R7 Granular Content SEO Architecture**:
   - From Obs 4, the content editor in `ContentController.php` does not render SEO fields and `ContentController::save()` ignores them.
   - Since `MetaService` and `PostService` already support arbitrary post metadata via `{prefix}meta_data`, adding the UI panel (SEO title, description with counter & live SERP preview, noindex/nofollow checkboxes, canonical override) to `renderEditor()` and persisting them in `save()` fulfills R7.

4. **R8 & Automated Verification Suite**:
   - From Obs 1 & 5, creating `SOURCES.md` documents all open-source libraries and fonts.
   - Creating `tests/EnterpriseOverhaulTest.php` running via `/Applications/XAMPP/xamppfiles/bin/php` with 7 specific test cases (canvas width, all admin routes HTTP 200, custom CSS injection, local font downloader, global/page noindex rendering, webmaster meta tags, SOURCES.md existence) provides automated regression defense.

---

## 3. Caveats

- **Network-Free Test Verification**: In automated environments or when running tests offline, `downloadFont()` might fail if Google's CDN is unreachable. `GoogleFontsDownloader` should include a deterministic offline/fixture mode or mock fallback for `tests/EnterpriseOverhaulTest.php`.
- **Database Schema**: No schema migrations are required because `{prefix}options` and `{prefix}meta_data` dynamically store key-value records.

---

## 4. Conclusion

The Heartbeat CMS codebase is cleanly structured and well-suited for the Enterprise Refinement. All necessary extension points exist:
- `GoogleFontsDownloader` can be extended with a curated catalog and wired to `/admin/appearance/fonts`.
- `AgencyModulesController::seo()`, `SeoService`, and `AdminView` have exact insertion points for R6 global SEO, webmaster verification tags, `robots.txt`, and the noindex warning banner.
- `ContentController` and `SeoService` have exact insertion points for R7 granular SEO controls, SERP live preview, and canonical overrides.
- `SOURCES.md` and `tests/EnterpriseOverhaulTest.php` can be created to verify all 7 acceptance criteria using `/Applications/XAMPP/xamppfiles/bin/php`.

Detailed file-by-file survey and proposed code structures are documented in `analysis.md`.

---

## 5. Verification Method

1. **Verify Existing Tests**:
   ```bash
   /Applications/XAMPP/xamppfiles/bin/php tests/Phase1VerificationTest.php
   /Applications/XAMPP/xamppfiles/bin/php tests/Phase5VerificationTest.php
   /Applications/XAMPP/xamppfiles/bin/php tests/Phase6VerificationTest.php
   ```
2. **Verify Target Enterprise Test Execution** (after implementation):
   ```bash
   /Applications/XAMPP/xamppfiles/bin/php tests/EnterpriseOverhaulTest.php
   ```
3. **Invalidation Conditions**:
   - If `/admin/appearance/fonts` does not return HTTP 200.
   - If frontend HTML makes any network request to `fonts.googleapis.com` or `fonts.gstatic.com`.
   - If `/robots.txt` fails to return `Disallow: /` when `seo_indexing = 'noindex'`.
   - If granular `_seo_title` or `_seo_canonical` are not output in frontend `<head>`.
   - If `SOURCES.md` is absent or incomplete.
