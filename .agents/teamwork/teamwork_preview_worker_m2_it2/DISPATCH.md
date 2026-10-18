# Task Assignment: Worker M2 Remediation (Theme Color Schemes Decoupling — Iteration 2)

**Working Directory**: /Users/risinger/Documents/VSCode/Heartbeat/.agents/teamwork/teamwork_preview_worker_m2_it2
**Role**: Software Engineer (Worker M2 Iteration 2)
**Original Request Path**: /Users/risinger/Documents/VSCode/Heartbeat/ORIGINAL_REQUEST.md
**Project Scope Document**: /Users/risinger/Documents/VSCode/Heartbeat/PROJECT.md
**Challenger 1 Handoff**: /Users/risinger/Documents/VSCode/Heartbeat/.agents/teamwork/teamwork_preview_challenger_m2_1/handoff.md
**Challenger 2 Handoff**: /Users/risinger/Documents/VSCode/Heartbeat/.agents/teamwork/teamwork_preview_challenger_m2_2/handoff.md

## Scope & Objective
Remediate the four specific leaks identified by Challengers 1 and 2 to pass the Milestone M2 gate:

1. **Unmatched Route 404 in `core/Http/Router.php:82-83`**:
   Replace hardcoded `#fb326f` in the inline CSS on lines 82–83 with neutral slate styling (`h1{color:#f8fafc;...} a{color:#94a3b8;border-bottom:1px solid #64748b;} a:hover{color:#f8fafc;}`).
2. **CssCompiler Static CSS Bundle Leak (`core/Themes/CssCompiler.php`)**:
   - In `core/Themes/CssCompiler.php:113` (`extractAllDatabaseClasses`):
     - Filter extracted class tokens to explicitly ignore `#fb326f` and `#e01e5a` classes when compiling frontend theme stylesheets (so stale database records cannot pollute compiled CSS).
   - In SQLite database `storage/heartbeat.sqlite`:
     - Update all posts in `{prefix}posts` by re-rendering `content_html` from `content_blocks` using `BlockRenderer` (or replacing legacy `bg-[#fb326f]` with `bg-hb-primary`), then clear CSS cache (`storage/cache/css/theme_*.css`).
3. **Emergency Fallback Theme Defaults (`core/Themes/Theme.php:42` & `core/Themes/ThemeService.php:126`)**:
   - In `core/Themes/Theme.php:42`: Update the fallback default array from `'primary' => '#fb326f'` to `'primary' => '#f97316'` (Sunset Orange) and `'primary_hover' => '#ea580c'`.
   - In `core/Themes/ThemeService.php:126`: Update the emergency virtual theme from `'primary' => '#fb326f'` to `'primary' => '#f97316'` and `'primary_hover' => '#ea580c'`.
4. **Contact Inquiry Email Header (`core/Http/ContactController.php:122`)**:
   - In `core/Http/ContactController.php:122`: Replace `color: #fb326f;` with `color: #0f172a;` or neutral styling.

## Exclusive Write Ownership
- `core/Http/Router.php`
- `core/Themes/CssCompiler.php`
- `core/Themes/Theme.php`
- `core/Themes/ThemeService.php`
- `core/Http/ContactController.php`
- `storage/heartbeat.sqlite` / database `{prefix}posts`
- `storage/cache/css/`
- Files within `/Users/risinger/Documents/VSCode/Heartbeat/.agents/teamwork/teamwork_preview_worker_m2_it2/`

## Mandatory Integrity Warning
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A teamwork_preview_auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.

## Verification Requirements
Run both challenger test suites using `/Applications/XAMPP/xamppfiles/bin/php`:
- `/Applications/XAMPP/xamppfiles/bin/php tests/ChallengerM2EmpiricalTest.php` (must pass 38/38)
- `/Applications/XAMPP/xamppfiles/bin/php tests/M2EmpiricalChallenger2Test.php` (must pass 18/18)
- `/Applications/XAMPP/xamppfiles/bin/php tests/EnterpriseOverhaulTest.php --milestone=m2` (must pass 4/4)
- `for t in tests/Phase*Test.php; do /Applications/XAMPP/xamppfiles/bin/php $t; done` (must pass 100%)

Document your changes, test results, and diffs in `handoff.md` and send a message when complete.

## 2026-10-18T16:50:55Z
You are Worker M2 Iteration 2 for the Heartbeat CMS Enterprise Refinement project.
Your working directory: /Users/risinger/Documents/VSCode/Heartbeat/.agents/teamwork/teamwork_preview_worker_m2_it2
Read your task instructions at: /Users/risinger/Documents/VSCode/Heartbeat/.agents/teamwork/teamwork_preview_worker_m2_it2/DISPATCH.md
Read the original request at: /Users/risinger/Documents/VSCode/Heartbeat/ORIGINAL_REQUEST.md
Read the project scope at: /Users/risinger/Documents/VSCode/Heartbeat/PROJECT.md
Read Challenger 1 handoff at: /Users/risinger/Documents/VSCode/Heartbeat/.agents/teamwork/teamwork_preview_challenger_m2_1/handoff.md
Read Challenger 2 handoff at: /Users/risinger/Documents/VSCode/Heartbeat/.agents/teamwork/teamwork_preview_challenger_m2_2/handoff.md

MANDATORY INTEGRITY WARNING:
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A teamwork_preview_auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.

Remediate the 4 leaks identified by Challengers 1 & 2. Run both challenger test suites and all phase tests via /Applications/XAMPP/xamppfiles/bin/php. Document in handoff.md and send a message when finished.
