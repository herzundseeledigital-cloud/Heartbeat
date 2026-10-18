# Task Assignment: E2E Test Suite Creator

**Working Directory**: /Users/risinger/Documents/VSCode/Heartbeat/.agents/teamwork/teamwork_preview_test_writer_e2e
**Role**: E2E Test Writer
**Original Request Path**: /Users/risinger/Documents/VSCode/Heartbeat/ORIGINAL_REQUEST.md
**Project Scope Document**: /Users/risinger/Documents/VSCode/Heartbeat/PROJECT.md

## Scope & Objective
Design and implement the complete, comprehensive enterprise test suite `tests/EnterpriseOverhaulTest.php` and publish `TEST_READY.md` at the project root.

## Architecture & Requirements
1. **PHP CLI Environment**:
   - PHP 8.2 is at `/Applications/XAMPP/xamppfiles/bin/php`.
   - The test must be runnable via `/Applications/XAMPP/xamppfiles/bin/php tests/EnterpriseOverhaulTest.php` and exit with 0 on success.
   - Inspect existing tests (`tests/Phase1VerificationTest.php`, `tests/Phase2VerificationTest.php`, etc.) to see how bootstrap, database initialization, and session mocking work.
2. **Automated Verification Suite (4-Tier Requirement-Driven Design)**:
   Cover all 7 acceptance criteria from `ORIGINAL_REQUEST.md`:
   - **Suite 1 (R1 Canvas & Layout)**:
     - Verify `core/Admin/AdminView.php` does NOT contain `max-w-7xl` in main shell.
     - Verify `AdminView.php` or `public/assets/css/admin.css` contains `max-w-[1800px]`.
     - Verify `.editor-col-3` responsive width rules ensure at least 360px+ on desktop/widescreen.
   - **Suite 2 (R2 Admin Routes HTTP 200 Audit)**:
     - Dispatch requests to all 8 admin routes: `/admin`, `/admin/content`, `/admin/media`, `/admin/themes`, `/admin/seo`, `/admin/users`, `/admin/settings`, `/install`.
     - Assert all return HTTP 200 without fatal PHP errors, warnings, or exceptions.
   - **Suite 3 (R3 Theme Color Decoupling)**:
     - Verify `themes/default/theme.json` primary color is `#f97316`.
     - Verify frontend templates in `themes/default/`, `themes/startup/`, and `core/Blocks/BlockRenderer.php` do NOT hardcode CMS brand `#fb326f`.
   - **Suite 4 (R4 Custom CSS Editor & Head Injection)**:
     - Verify `ThemeService::saveCustomCss()` writes cleanly to `storage/custom-css/{theme}.css` (or `themes/{theme}/custom.css`).
     - Verify frontend rendered `<head>` injects `<style id="theme-custom-css">` with user custom CSS.
     - Verify `/admin/themes/customize` view loads custom CSS without minified bundle pollution.
   - **Suite 5 (R5 Local Google Fonts)**:
     - Verify `/admin/appearance/fonts` (or `/admin/fonts`) route exists and returns HTTP 200.
     - Verify font downloading creates `.woff2` files in `/public/assets/fonts/{slug}/` and generates valid `@font-face` CSS.
     - Verify zero Google CDN `@import` or `<link>` in `installer.css`, `admin.css`, and rendered frontend `<head>`.
     - In offline mode or test environment, provide a deterministic fixture or mock fallback if external Google CDN is unreachable so tests run reliably offline.
   - **Suite 6 (R6 & R7 SEO & Webmaster Tags)**:
     - Verify global `noindex` setting causes frontend HTML `<meta name="robots" content="noindex, nofollow">` and `/robots.txt` outputting `Disallow: /`.
     - Verify global `index` setting outputs `index, follow` and `/robots.txt` outputting `Allow: /`.
     - Verify per-page `_seo_noindex` overrides global index to `noindex`.
     - Verify per-page `_seo_title`, `_seo_description`, and `_seo_canonical` render in frontend `<head>`.
     - Verify Google Search Console and Bing Webmaster meta tags render in frontend `<head>` when configured.
   - **Suite 7 (R8 Legal Compliance)**:
     - Verify `SOURCES.md` exists in project root.
     - Verify `SOURCES.md` documents licenses (MIT, Apache 2.0, OFL) and font assets.
3. **Publish TEST_READY.md**:
   Create `/Users/risinger/Documents/VSCode/Heartbeat/TEST_READY.md` summarizing the test runner command and tier coverage.

## Mandatory Rules
- Exclusive write ownership: `tests/EnterpriseOverhaulTest.php`, `TEST_READY.md`, and metadata in your working directory.
- DO NOT modify core application code files.
- Test runner must be executable and report clear pass/fail output.
- Write your completion report in `handoff.md` and message parent when complete.


## 2026-10-18T16:01:12Z
You are the E2E Test Writer for the Heartbeat CMS Enterprise Refinement project.
Your working directory: /Users/risinger/Documents/VSCode/Heartbeat/.agents/teamwork/teamwork_preview_test_writer_e2e
Read your task instructions at: /Users/risinger/Documents/VSCode/Heartbeat/.agents/teamwork/teamwork_preview_test_writer_e2e/DISPATCH.md
Read the immutable original request at: /Users/risinger/Documents/VSCode/Heartbeat/ORIGINAL_REQUEST.md
Read the project architecture and features at: /Users/risinger/Documents/VSCode/Heartbeat/PROJECT.md
Design and implement tests/EnterpriseOverhaulTest.php and create TEST_READY.md.
Document your results in handoff.md and send a completion message when finished.
