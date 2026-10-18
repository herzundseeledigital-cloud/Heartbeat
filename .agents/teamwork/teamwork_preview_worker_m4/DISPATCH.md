# DISPATCH — Milestone M4: GDPR-Compliant Local Google Fonts Downloader & Catalog

## Objective
Implement Requirement R5:
1. Provide a GDPR-compliant Google Fonts catalog and downloader at `/admin/appearance/fonts` (and route alias `/admin/fonts`).
2. Catalog UI: search, preview font specimens, and one-click download/install.
3. Local Font Downloader (`core/Themes/GoogleFontsDownloader.php`):
   - Downloads `.woff2` files into `/public/assets/fonts/{slug}/` (e.g. `inter-400.woff2`, `inter-700.woff2`).
   - Generates valid `@font-face` CSS at `/public/assets/fonts/{slug}/font.css` (or `/public/assets/fonts/{slug}.css`) linking to local `.woff2` files using `format('woff2')`.
   - Never references external `fonts.gstatic.com` or `fonts.googleapis.com` in generated CSS!
   - Robust offline fallback fixture: if network/curl fails or in offline testing, provide deterministic fallback so testing passes seamlessly.
4. One-click theme assignment:
   - Provide endpoint/action to assign downloaded font to active theme typography (`font_family`, `heading_font`).
5. Zero Google CDN requests:
   - Remove Google Fonts `@import url("https://fonts.googleapis.com/css2?family=Inter:...")` from `public/assets/css/installer.css` (line 6) and `public/assets/css/admin.css` (line 6).
   - Ensure rendered frontend `<head>` contains ZERO external Google CDN font requests (`fonts.googleapis.com` or `fonts.gstatic.com`).

## Verification
- Test command:
  `/Applications/XAMPP/xamppfiles/bin/php tests/EnterpriseOverhaulTest.php --milestone=m4`
  Suite 5 must pass 100% (3/3 tests).
- Also verify full test suite:
  `/Applications/XAMPP/xamppfiles/bin/php tests/EnterpriseOverhaulTest.php`

## Mandatory Warning
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A teamwork_preview_auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.
