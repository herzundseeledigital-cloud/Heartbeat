# Task Assignment: Worker M2 (Theme Palette Decoupling from CMS CI)

**Working Directory**: /Users/risinger/Documents/VSCode/Heartbeat/.agents/teamwork/teamwork_preview_worker_m2
**Role**: Software Engineer (Worker M2)
**Original Request Path**: /Users/risinger/Documents/VSCode/Heartbeat/ORIGINAL_REQUEST.md
**Project Scope Document**: /Users/risinger/Documents/VSCode/Heartbeat/PROJECT.md
**Explorer Survey Reference**: /Users/risinger/Documents/VSCode/Heartbeat/.agents/teamwork/teamwork_preview_explorer_survey_2/handoff.md and analysis.md

## Scope & Objective
Implement Milestone M2 (Requirement R3: Decouple Frontend Theme Color Schemes from CMS CI `#fb326f`):
1. **One-Pager Theme Palette Standardisation**:
   - `themes/default/theme.json`: Verify and set primary color to `#f97316` (Sunset Orange) and primary_hover to `#ea580c`.
   - In `themes/default/templates/header.php`, `home.php`, and `404.php`: Replace all 35+ hardcoded `#fb326f` and `#e01e5a` occurrences with theme variables or orange tokens (`#f97316`, hover `#ea580c`).
2. **Modern Startup Theme Color Decoupling**:
   - In `themes/startup/templates/home.php`: Replace the 7 hardcoded `#fb326f` occurrences in gradient stops and stat text with startup's Rose accent (`#f43f5e`) or theme primary (`#8b5cf6`).
3. **Shared Block Renderer & App Fallback Decoupling**:
   - In `core/Blocks/BlockRenderer.php`: Replace all 22 hardcoded `#fb326f` references with dynamic CSS variable classes or inline styles using `var(--hb-primary)` and `var(--hb-primary-hover)`.
   - In `core/App.php` (lines 525, 530, 541, 584, 589, 595): Replace hardcoded `#fb326f` in fallback templates with theme CSS variables.
4. **Verification**:
   - Ensure `#fb326f` remains exclusive to the admin backend.
   - Run `/Applications/XAMPP/xamppfiles/bin/php tests/EnterpriseOverhaulTest.php --milestone=m2` (Suite 3 must pass 100%).
   - Run all existing phase tests: `for t in tests/Phase*Test.php; do /Applications/XAMPP/xamppfiles/bin/php $t; done`.

## Exclusive Write Ownership
- `themes/default/theme.json`
- `themes/default/templates/header.php`
- `themes/default/templates/home.php`
- `themes/default/templates/404.php`
- `themes/startup/templates/home.php`
- `core/Blocks/BlockRenderer.php`
- `core/App.php`
- Files within `/Users/risinger/Documents/VSCode/Heartbeat/.agents/teamwork/teamwork_preview_worker_m2/`

## Mandatory Integrity Warning
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A teamwork_preview_auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.

Document all diffs and test results in `handoff.md` and send a message when complete.


## 2026-10-18T16:21:07Z
You are Worker M2 for the Heartbeat CMS Enterprise Refinement project.
Your working directory: /Users/risinger/Documents/VSCode/Heartbeat/.agents/teamwork/teamwork_preview_worker_m2
Read your task instructions at: /Users/risinger/Documents/VSCode/Heartbeat/.agents/teamwork/teamwork_preview_worker_m2/DISPATCH.md
Read the original request at: /Users/risinger/Documents/VSCode/Heartbeat/ORIGINAL_REQUEST.md
Read the project scope at: /Users/risinger/Documents/VSCode/Heartbeat/PROJECT.md
Read explorer survey at: /Users/risinger/Documents/VSCode/Heartbeat/.agents/teamwork/teamwork_preview_explorer_survey_2/handoff.md

MANDATORY INTEGRITY WARNING:
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A teamwork_preview_auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.

Implement Milestone M2 (Requirement R3), run tests via /Applications/XAMPP/xamppfiles/bin/php tests/EnterpriseOverhaulTest.php --milestone=m2, document in handoff.md, and send a message when finished.
