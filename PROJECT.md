# Project: Heartbeat CMS Enterprise Refinement

## Architecture
Heartbeat CMS is a modular PHP CMS structured as:
- `core/`: Core kernel, routing, controllers, models, and services:
  - `Admin/`: Admin controllers (`AdminController.php`, `ContentController.php`, `MediaController.php`, `ThemeController.php`, `AgencyModulesController.php`) and shell rendering (`AdminView.php`).
  - `Blocks/`: Block rendering engine (`BlockRenderer.php`).
  - `Gdpr/`: Local privacy-first font downloader (`GoogleFontsDownloader.php`).
  - `Seo/`: SEO services, sitemap, and meta tag generator (`SeoService.php`).
  - `Themes/`: Theme management and Tailwind compilation (`ThemeService.php`, `CssCompiler.php`).
  - `Http/`: Request and Response primitives.
- `themes/`: Independent frontend themes:
  - `default/`: One-Pager theme (Sunset Orange `#f97316`).
  - `corporate/`: Corporate theme (Blue `#2563eb`).
  - `real-estate/`: Real Estate theme (Emerald `#059669`).
  - `startup/`: Modern Startup theme (Violet `#8b5cf6`).
- `public/assets/`: Compiled assets (`css/admin.css`, `css/installer.css`, `fonts/`).
- `storage/`: Custom CSS files (`storage/custom-css/{theme}.css`), database SQLite files, cache.
- `tests/`: Automated PHP test suites run via `/Applications/XAMPP/xamppfiles/bin/php`.

## Feature Inventory
| # | Feature | Description | Milestone | Source |
|---|---------|-------------|-----------|--------|
| 1 | Canvas Widescreen Freedom | Replace `max-w-7xl` with `max-w-[1800px]` in `AdminView.php` and add `.max-w-[1800px]` utility to `admin.css` | M1 | Survey 1 / R1 |
| 2 | Collapsible Inspector Sidebar | Toggleable inspector sidebar with clean desktop margins and responsive persistence | M1 | Survey 1 / R1 |
| 3 | 3-Column Card Widescreen Layout | Ensure cards render >= 360px width on widescreen without squishing (`.editor-col-3`) | M1 | Survey 1 / R1 |
| 4 | Admin Routes HTTP 200 Audit | Ensure all admin routes (`/admin`, `/admin/content`, `/admin/media`, `/admin/themes`, `/admin/seo`, `/admin/users`, `/admin/settings`, `/install`) return HTTP 200 | M1 | Survey 1 / R2 |
| 5 | Media Controller Syntax Fix | Escape `\$event` in heredocs in `MediaController.php` lines 153 & 156 | M1 | Survey 1 / R2 |
| 6 | Content Route Registration | Register `/admin/content` route in `core/App.php` | M1 | Survey 1 / R2 |
| 7 | User Modal Close Fix | Fix `createUserModal` cancel button event handler in `AdminController.php` | M1 | Survey 1 / R2 |
| 8 | Installer 200 HTTP Response | Ensure `InstallerController::index` returns HTTP 200 when installation lock is present | M1 | Survey 1 / R2 |
| 9 | Decouple Theme Color Schemes | Remove hardcoded `#fb326f` from frontend themes and use independent theme palettes | M2 | Survey 2 / R3 |
| 10 | One-Pager Sunset Orange Palette | Standardize One-Pager (`themes/default/`) on `#f97316` / `#ea580c` and clean 35+ hardcoded CMS CI references | M2 | Survey 2 / R3 |
| 11 | Startup Theme Color Decoupling | Clean 7 hardcoded `#fb326f` occurrences in `themes/startup/templates/home.php` | M2 | Survey 2 / R3 |
| 12 | BlockRenderer CSS Variable Integration | Replace 22 hardcoded `#fb326f` in `core/Blocks/BlockRenderer.php` and fallbacks in `core/App.php` with `var(--hb-primary)` | M2 | Survey 2 / R3 |
| 13 | Custom CSS File Storage | Implement isolated custom CSS storage in `storage/custom-css/{theme}.css` and `themes/{theme}/custom.css` via `ThemeService` | M3 | Survey 2 / R4 |
| 14 | Theme Customizer Code Editor | Monospace code editor with dynamic line numbers gutter, scroll sync, and 2-space Tab indentation | M3 | Survey 2 / R4 |
| 15 | Real-time Live Preview | Dynamic `<style id="live-custom-css">` DOM injection into preview iframe on `@input` | M3 | Survey 2 / R4 |
| 16 | Frontend Head Custom CSS Injection | Inject `<style id="theme-custom-css">` into `<head>` across all themes and fallback templates | M3 | Survey 2 / R4 |
| 17 | Local Google Fonts Catalog & UI | Curated font catalog UI with live specimens at `/admin/appearance/fonts` (and `/admin/fonts`) | M4 | Survey 3 / R5 |
| 18 | Multi-family Local Download | Download `.woff2` files to `/public/assets/fonts/` with valid `@font-face` generation | M4 | Survey 3 / R5 |
| 19 | Zero External Google CDN Requests | Eliminate external Google CDN `@import` in `installer.css` and `admin.css` | M4 | Survey 3 / R5 |
| 20 | One-Click Theme Typography Assignment | Assign downloaded local fonts to active theme's headings and body styles | M4 | Survey 3 / R5 |
| 21 | Global Site Indexing Toggle | Toggle between `index, follow` and `noindex, nofollow` in `/admin/seo` | M5 | Survey 3 / R6 |
| 22 | Admin Bar Warning Banner | Prominent amber warning banner in `AdminView` topbar when `noindex` is active | M5 | Survey 3 / R6 |
| 23 | Webmaster Verification Tokens | Google Search Console and Bing Webmaster meta tag verification fields and rendering | M5 | Survey 3 / R6 |
| 24 | Dynamic Robots.txt Generation | Dynamic `/robots.txt` route honoring global indexing flag (`Disallow: /` on noindex) | M5 | Survey 3 / R6 |
| 25 | Sitemap Indexation Filtering | Filter out noindexed pages from `/sitemap.xml` | M5 | Survey 3 / R6 |
| 26 | Granular Content SEO Panel | Custom SEO Title, Meta Description, character counter & Google SERP preview in content editor | M5 | Survey 3 / R7 |
| 27 | Per-Item Robots & Canonical Override | Per-page `noindex`, `nofollow`, and canonical URL override in content editor and `SeoService` | M5 | Survey 3 / R7 |
| 28 | Legal Compliance Documentation | Document all open-source libraries, licenses (MIT/Apache 2.0/OFL), and fonts in `SOURCES.md` | M6 | Survey 3 / R8 |
| 29 | Enterprise E2E Test Suite | 4-Tier requirement-driven test suite in `tests/EnterpriseOverhaulTest.php` passing 100% | M7 | Survey / E2E Track |

## Milestones
| # | Name | Scope | Dependencies | Status |
|---|------|-------|-------------|--------|
| M1 | Admin Shell & Editor Canvas Freedom & UX | Features 1-8: `AdminView.php`, `admin.css`, `ContentController.php`, routes fixes in `core/App.php`, `MediaController.php`, `AdminController.php`, `InstallerController.php` | none | DONE |
| M2 | Theme Palette Decoupling from CMS CI | Features 9-12: `themes/default/`, `themes/startup/`, `BlockRenderer.php`, `core/App.php` | none | DONE |
| M3 | Theme Customizer & Live CSS Editor | Features 13-16: `ThemeService.php`, `ThemeController.php`, `themes/*/templates/header.php`, `core/App.php` | M2 | IN_PROGRESS |
| M4 | GDPR Local Google Fonts Downloader | Features 17-20: `GoogleFontsDownloader.php`, `/admin/appearance/fonts` route/view, `installer.css`, `admin.css`, theme typography binding | none | PLANNED |
| M5 | Global & Granular SEO & Webmaster System | Features 21-27: `AgencyModulesController.php`, `AdminView.php`, `SeoService.php`, `ContentController.php`, `core/App.php` | M1 | PLANNED |
| M6 | Legal Compliance & SOURCES.md | Feature 28: `SOURCES.md` creation and licensing verification | none | DONE |
| M7 | Final Integration & Adversarial Hardening | Feature 29: Execute 100% of E2E tests (`tests/EnterpriseOverhaulTest.php`) + Tier 5 Adversarial Coverage Hardening | M1, M2, M3, M4, M5, M6, E2E Track | PLANNED |

## Interface Contracts
### M3 (Theme Customizer) ↔ M2 (Themes / Frontend)
- `ThemeService::getCustomCss(string $slug): string`
- `ThemeService::saveCustomCss(string $slug, string $css): bool`
- Frontend `<head>` injection:
  `<?php if (!empty($customCss)): ?><style id="theme-custom-css"><?= $customCss ?></style><?php endif; ?>`

### M4 (Local Google Fonts) ↔ M2 (Themes / Frontend)
- Downloaded fonts stored in `/public/assets/fonts/{slug}/{slug}-{weight}.woff2`
- Generated font stylesheet in `/public/assets/fonts/{slug}/font.css`
- Active theme `font_family` / `heading_font` updated via `ThemeService::updateActiveThemeConfig(['typography' => ...])`

### M5 (SEO System) ↔ M1 (Admin Shell & Content Editor)
- `OptionService::get('seo_indexing', 'index')` used by `AdminView::renderShell()` for topbar warning banner
- Content Editor SEO inputs submitted as:
  `_seo_title`, `_seo_description`, `_seo_noindex`, `_seo_nofollow`, `_seo_canonical`
- Handled and persisted in `ContentController::save()` via `MetaService::set('post', $id, $key, $value)`
- Rendered in frontend `<head>` by `SeoService::renderMetaTags(?array $post = null)`

### M7 (E2E Test Suite) ↔ System
- CLI runner: `/Applications/XAMPP/xamppfiles/bin/php tests/EnterpriseOverhaulTest.php`
- Assertions cover:
  1. Editor container width is unconstrained by `max-w-7xl` and allows widescreen canvas layout.
  2. All admin routes return HTTP 200 without PHP errors or template warnings.
  3. Theme custom CSS saves and injects into frontend `<head>`.
  4. Local font downloading writes `.woff2` files to `/public/assets/fonts/` and generates valid `@font-face` CSS without calling Google CDN.
  5. Global `noindex` and per-page `noindex` directives render correctly in frontend HTML.
  6. Google Search Console and Bing Webmaster meta tags render in frontend `<head>`.
  7. `SOURCES.md` exists and documents all utilized open-source licenses and fonts.

## Code Layout
- `core/Admin/AdminView.php` — Admin container and shell layout, topbar warning banner (M1, M5)
- `public/assets/css/admin.css` — Admin styling, widescreen utility, editor columns, font imports (M1, M4)
- `public/assets/css/installer.css` — Installer styling and font imports (M4)
- `core/Admin/ContentController.php` — Visual content editor, collapsible inspector, SEO panel, save handler (M1, M5)
- `core/Admin/MediaController.php` — Media upload view and handler (M1)
- `core/Admin/AdminController.php` — Admin routes and user modals (M1)
- `core/Installer/InstallerController.php` — Installer route (M1)
- `core/App.php` — Core routing, fallback templates (M1, M2, M3, M4, M5)
- `themes/default/` — One-Pager theme palette and templates (M2, M3)
- `themes/startup/` — Modern Startup theme palette and templates (M2, M3)
- `themes/corporate/` — Corporate theme templates (M3)
- `themes/real-estate/` — Real Estate theme templates (M3)
- `core/Blocks/BlockRenderer.php` — Dynamic block rendering with theme CSS variables (M2)
- `core/Themes/ThemeService.php` — Theme configuration and custom CSS persistence (M3)
- `core/Admin/ThemeController.php` — Theme customizer view/save, font catalog view/save (M3, M4)
- `core/Gdpr/GoogleFontsDownloader.php` — Font parsing, `.woff2` downloading, and `@font-face` generator (M4)
- `core/Admin/AgencyModulesController.php` — SEO settings view and save handler (M5)
- `core/Seo/SeoService.php` — Meta tag generation, sitemap, and robots.txt generation (M5)
- `SOURCES.md` — Open source and font licenses documentation (M6)
- `tests/EnterpriseOverhaulTest.php` — Comprehensive 4-tier E2E test suite (E2E Track)
