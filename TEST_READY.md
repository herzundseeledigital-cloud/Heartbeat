# TEST_READY — Heartbeat CMS Enterprise Overhaul Test Suite

## Executive Overview
The comprehensive E2E test suite `tests/EnterpriseOverhaulTest.php` has been designed and implemented to validate all 7 acceptance criteria (R1 – R8) from `ORIGINAL_REQUEST.md` and `PROJECT.md`.

The test runner is self-contained, requires no third-party test runners, operates under **PHP 8.2 CLI**, and provides ANSI colorized reporting, timing diagnostics, and progressive milestone testing.

---

## How to Run the Tests

### Primary CLI Command (All Suites)
```bash
/Applications/XAMPP/xamppfiles/bin/php tests/EnterpriseOverhaulTest.php
```

### Targeted Milestone Runs
Milestone workers can execute verification exclusively for their scope:

```bash
# Milestone 1: Canvas Widescreen Freedom & Complete 8-Route Audit (Suites 1 & 2)
/Applications/XAMPP/xamppfiles/bin/php tests/EnterpriseOverhaulTest.php --milestone=m1

# Milestone 2: Decoupled Theme Color Schemes (Suite 3)
/Applications/XAMPP/xamppfiles/bin/php tests/EnterpriseOverhaulTest.php --milestone=m2

# Milestone 3: Theme Customizer & Live CSS Editor (Suite 4)
/Applications/XAMPP/xamppfiles/bin/php tests/EnterpriseOverhaulTest.php --milestone=m3

# Milestone 4: GDPR Local Google Fonts Downloader (Suite 5)
/Applications/XAMPP/xamppfiles/bin/php tests/EnterpriseOverhaulTest.php --milestone=m4

# Milestone 5: Global & Granular SEO Controls (Suite 6)
/Applications/XAMPP/xamppfiles/bin/php tests/EnterpriseOverhaulTest.php --milestone=m5

# Milestone 6: Legal & Open Source Compliance Documentation (Suite 7)
/Applications/XAMPP/xamppfiles/bin/php tests/EnterpriseOverhaulTest.php --milestone=m6
```

### Granular Suite Execution
```bash
# Run a specific suite (1 through 7)
/Applications/XAMPP/xamppfiles/bin/php tests/EnterpriseOverhaulTest.php --suite=1
/Applications/XAMPP/xamppfiles/bin/php tests/EnterpriseOverhaulTest.php --suite=2
/Applications/XAMPP/xamppfiles/bin/php tests/EnterpriseOverhaulTest.php --suite=3

# Non-zero exit code suppression (reporting mode only)
/Applications/XAMPP/xamppfiles/bin/php tests/EnterpriseOverhaulTest.php --soft

# View available suites and CLI options
/Applications/XAMPP/xamppfiles/bin/php tests/EnterpriseOverhaulTest.php --list
/Applications/XAMPP/xamppfiles/bin/php tests/EnterpriseOverhaulTest.php --help
```

---

## Test Architecture & Tier Breakdown

The test suite is structured across 4 architecture tiers and 7 requirement suites:

### Tier 1: UI Physics, Layout Hierarchy & Admin Shell Audit (M1)
- **Suite 1: R1 Canvas & Layout Widescreen Freedom**
  - Asserts `core/Admin/AdminView.php` main shell eliminates `max-w-7xl` constraint.
  - Asserts `max-w-[1800px]` widescreen container is present in `AdminView.php` or `admin.css`.
  - Asserts `.editor-col-3` ensures >= 360px column width on widescreen displays (1920x1080 canvas yields ~477px per card).
  - Asserts collapsible Inspector sidebar state and toggle handlers exist in `ContentController.php`.
- **Suite 2: R2 Complete Admin Routes HTTP 200 Audit**
  - Dispatches GET requests to all 8 core admin endpoints:
    1. `/admin` (Dashboard Kontrollzentrum)
    2. `/admin/content` (Content Overview)
    3. `/admin/media` (Mediathek Asset Pipeline)
    4. `/admin/themes` (Themes & Live Preview)
    5. `/admin/seo` (SEO & Webmaster Tools)
    6. `/admin/users` (Users & RBAC Management)
    7. `/admin/settings` (General Settings & Agency White-Label)
    8. `/install` (Web Installer Endpoint)
  - Asserts all 8 return HTTP 200 without fatal PHP errors, unescaped variables (e.g. `\$event`), or template notices.
  - Asserts adversarial unauthenticated guest access to `/admin` redirects to login (HTTP 302).

### Tier 2: Theme Decoupling & Customizer CSS Pipeline (M2 & M3)
- **Suite 3: R3 Decoupled Theme Color Schemes**
  - Asserts `themes/default/theme.json` primary color is `#f97316` (Sunset Orange).
  - Audits all template files in `themes/default/` for 0 occurrences of hardcoded CMS CI `#fb326f`.
  - Audits all template files in `themes/startup/` for 0 occurrences of hardcoded CMS CI `#fb326f`.
  - Audits `core/Blocks/BlockRenderer.php` for 0 occurrences of hardcoded `#fb326f` and verifies dynamic CSS variable binding (`var(--hb-primary)`).
- **Suite 4: R4 Theme Customizer & Live CSS Editor**
  - Asserts `ThemeService::saveCustomCss()` cleanly persists custom CSS to `storage/custom-css/{theme}.css` or `themes/{theme}/custom.css`.
  - Asserts rendered frontend `<head>` injects `<style id="theme-custom-css">` with user-defined CSS.
  - Asserts `/admin/themes/customize` view loads custom CSS cleanly without minified framework bundle pollution.
  - Adversarial verification: preserves quotes, special characters, CSS comments, and Unicode without entity corruption.

### Tier 3: Privacy & SEO Engineering Systems (M4 & M5)
- **Suite 5: R5 GDPR-Compliant Local Google Fonts Downloader**
  - Asserts route `/admin/appearance/fonts` (or `/admin/fonts`) exists and returns HTTP 200 with font catalog specimens.
  - Asserts font downloading saves `.woff2` files into `/public/assets/fonts/` and generates valid `@font-face` CSS. Includes deterministic fallback fixture for offline environments.
  - Asserts zero Google CDN `@import` or `<link>` (`fonts.googleapis.com` or `fonts.gstatic.com`) in `installer.css`, `admin.css`, and rendered frontend `<head>`.
- **Suite 6: R6 & R7 Global & Granular SEO Controls**
  - Asserts global `noindex` setting outputs `<meta name="robots" content="noindex, nofollow">` in frontend HTML.
  - Asserts global `noindex` setting causes `/robots.txt` to output `Disallow: /`.
  - Asserts global `index` setting outputs `index, follow` and Allow in `/robots.txt`.
  - Asserts per-page `_seo_noindex = 1` overrides global index to `noindex, nofollow`.
  - Asserts granular `_seo_title`, `_seo_description`, and `_seo_canonical` overrides render faithfully in `<head>`.
  - Asserts Google Search Console and Bing Webmaster verification meta tags render when configured.

### Tier 4: Open Source Governance & Legal Compliance (M6)
- **Suite 7: R8 Legal & Open Source Compliance Documentation**
  - Asserts `SOURCES.md` exists in project root.
  - Asserts `SOURCES.md` documents open source licenses (MIT, Apache 2.0, SIL Open Font License).
  - Asserts `SOURCES.md` documents local Google Font assets, typography creators, and upstream repositories.

---

## Current Test Execution Baseline

| Suite | Focus | Status | Passing | Pending / Failing | Notes |
|---|---|---|---|---|---|
| **Suite 1** | R1 Canvas & Layout | **PASSED** | 4 / 4 | 0 | Widescreen container, 3-col editor rules, and collapsible inspector verified. |
| **Suite 2** | R2 Admin Routes Audit | **PASSED** | 9 / 9 | 0 | All 8 admin routes return HTTP 200 OK without errors; guest access protected. |
| **Suite 3** | R3 Theme Color Decoupling | **PENDING** | 1 / 4 | 3 | `theme.json` updated; template files in `default/`, `startup/`, and `BlockRenderer.php` awaiting M2 decoupling. |
| **Suite 4** | R4 Theme Customizer | **PENDING** | 1 / 4 | 3 | Adversarial escaping passed; custom CSS storage and customize view method awaiting M3. |
| **Suite 5** | R5 Local Google Fonts | **PENDING** | 1 / 3 | 2 | Font downloader and .woff2 parsing passed; font route and installer.css CDN cleanup awaiting M4. |
| **Suite 6** | R6 & R7 SEO & Webmaster | **PENDING** | 2 / 6 | 4 | Per-page title/desc/noindex passed; global noindex, robots.txt, and webmaster tokens awaiting M5. |
| **Suite 7** | R8 Legal Compliance | **PENDING** | 0 / 3 | 3 | `SOURCES.md` creation and license documentation awaiting M6. |

---

## Escalated Implementation Defects Discovered

1. **`ThemeController.php:208` — Undefined method `Theme::isActive()`**:
   - In `ThemeController::customize()`, line 208 calls `$theme->isActive()`, which throws `Error: Call to undefined method Heartbeat\Themes\Theme::isActive()`.
   - Action for Worker M3: Check `ThemeService::getActive()->getSlug() === $theme->getSlug()` instead of `$theme->isActive()`.
2. **`SeoService.php:64-67` — Missing Canonical URL Override**:
   - `SeoService::renderMetaTags()` computes `$canonicalUrl` using site URL and post slug, but ignores `_seo_canonical` if specified in `$meta`.
   - Action for Worker M5: Check `if (!empty($meta['_seo_canonical'])) $canonicalUrl = $meta['_seo_canonical'];`.
3. **`SeoService.php` — Missing Webmaster Verification Meta Tags**:
   - Options `seo_google_verification` and `seo_bing_verification` are not yet queried or included in `$tags` array in `SeoService::renderMetaTags()`.
   - Action for Worker M5: Fetch options and append `<meta name="google-site-verification" content="...">` and `<meta name="msvalidate.01" content="...">`.
4. **`public/assets/css/installer.css:6` — External Google CDN Leak**:
   - Line 6 imports `https://fonts.googleapis.com/css2?family=Inter:...`.
   - Action for Worker M4: Remove external `@import` and replace with self-hosted font or system font stack.
