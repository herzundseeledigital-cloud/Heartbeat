# DISPATCH — Milestone M5: Global & Granular SEO & Webmaster System

## Objective
Implement Requirements R6 and R7:
1. Global SEO Controls (`/admin/seo`):
   - Global indexing toggle: `seo_indexing` ('index' or 'noindex').
   - Webmaster verification tokens: `seo_google_verification`, `seo_bing_verification`.
   - Admin topbar warning banner when `seo_indexing === 'noindex'` in `core/Admin/AdminView.php`.
   - `/robots.txt` dynamic generation or handler in `core/App.php`:
     - If `seo_indexing === 'noindex'`: outputs `User-agent: *\nDisallow: /`
     - If `seo_indexing === 'index'`: outputs `User-agent: *\nAllow: /`
   - `/sitemap.xml` dynamic generation or handler in `core/App.php` or `AgencyModulesController.php`.
   - `core/Services/SeoService.php` `renderMetaTags(?array $post = null)`:
     - Global `noindex`: outputs `<meta name="robots" content="noindex, nofollow">`
     - Global `index`: outputs `<meta name="robots" content="index, follow">`
     - Includes GSC token `<meta name="google-site-verification" content="...">` and Bing `<meta name="msvalidate.01" content="...">` when configured.
2. Granular Content SEO Controls on Edit Screens (`core/Admin/ContentController.php`):
   - Collapsible SEO panel / tab in content editor.
   - Fields: `_seo_title`, `_seo_description` (with character counter 0-160 & SERP preview), `_seo_noindex`, `_seo_nofollow`, `_seo_canonical`.
   - Persist fields in post meta or database columns via `ContentController::save()`.
   - Frontend integration in `SeoService::renderMetaTags`:
     - Per-page `_seo_noindex = 1` overrides global index to `noindex, nofollow`.
     - Granular `_seo_title`, `_seo_description`, and `_seo_canonical` overrides render faithfully in `<head>`.
3. Verification:
   `/Applications/XAMPP/xamppfiles/bin/php tests/EnterpriseOverhaulTest.php --milestone=m5`
   Suite 6 must pass 100%.

## Mandatory Warning
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A teamwork_preview_auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.
