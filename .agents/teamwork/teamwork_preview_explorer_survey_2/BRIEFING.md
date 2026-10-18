# BRIEFING — 2026-10-18T15:52:00Z

## Mission
Survey the Heartbeat CMS codebase for Requirement R3 (Theme Color Scheme Decoupling) and Requirement R4 (Theme Customizer & Live CSS Editor).

## 🔒 My Identity
- Archetype: Explorer
- Roles: Codebase Researcher / Explorer
- Working directory: /Users/risinger/Documents/VSCode/Heartbeat/.agents/teamwork/teamwork_preview_explorer_survey_2
- Original parent: 9bb52aef-126b-46f1-907e-b4b44da3f353
- Milestone: Survey Phase (Requirements R3 & R4)

## 🔒 Key Constraints
- Read-only investigation — do NOT implement
- Scope limited to R3 and R4
- Produce structured findings in analysis.md and handoff.md
- Message parent upon completion

## Current Parent
- Conversation ID: 9bb52aef-126b-46f1-907e-b4b44da3f353
- Updated: not yet

## Investigation State
- **Explored paths**: `themes/` (`default`, `corporate`, `real-estate`, `startup`), `core/Themes/Theme.php`, `core/Themes/ThemeService.php`, `core/Themes/CssCompiler.php`, `core/Admin/ThemeController.php`, `core/Blocks/BlockRenderer.php`, `core/App.php`, `tests/Phase*Test.php`.
- **Key findings**:
  - R3: `#fb326f` is hardcoded across One-Pager templates (35+ occurrences), Startup `home.php` (7 occurrences), `BlockRenderer.php` (22 occurrences), and `App.php` fallback templates. One-Pager should standardize on Sunset Orange (`#f97316`), matching existing Phase 4 test assertions, and BlockRenderer should use CSS variables (`var(--hb-primary)`).
  - R4: Customizer currently pulls minified bundle into textarea via `this->compiler->getCss()`. Needs dedicated `ThemeService::getCustomCss()` / `saveCustomCss()` targeting `storage/custom-css/{theme}.css` and `themes/{theme}/custom.css`, an Alpine-powered code editor with dynamic line numbers and Tab indentation, real-time iframe `<style id="live-custom-css">` injection, and `<head>` injection via `<style id="theme-custom-css">`.
- **Unexplored areas**: None within R3/R4 scope.

## Key Decisions Made
- Confirmed PHP binary location at `/Applications/XAMPP/xamppfiles/bin/php` (all Phase 1-6 tests pass).
- Recommended Sunset Orange (`#f97316`) for One-Pager to align with `Phase4VerificationTest.php`.
- Designed dependency-free Alpine.js code editor with line number gutter and Tab indentation.

## Artifact Index
- `analysis.md` — Detailed survey report with before/after specifications, file paths, line numbers, and risk mitigations.
- `handoff.md` — 5-component hard handoff report for parent agent / orchestrator.
- `progress.md` — Liveness heartbeat and progress tracking.
- `DISPATCH.md` — Task assignment and incoming messages log.
