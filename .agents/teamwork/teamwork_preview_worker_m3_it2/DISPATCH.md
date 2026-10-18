# DISPATCH — Worker M3 (Iteration 2: Frontend Remediation)

## Objective
Remediate the two frontend execution defects in `core/Admin/ThemeController.php` identified by Challenger 2 during the Milestone M3 gate check.

## Defect Details & Required Fixes
Read Challenger 2's full report at:
`/Users/risinger/Documents/VSCode/Heartbeat/.agents/teamwork/teamwork_preview_challenger_m3_2/handoff.md`

1. **Defect 1 (`core/Admin/ThemeController.php:219`)**:
   - Issue: `x-data="themeStudio({$themeConfigJson})"` causes HTML attribute collision because syntactic JSON double quotes terminate the `x-data="..."` attribute at byte 14 (`themeStudio({`). This crashes Alpine.js initialization with `SyntaxError: Unexpected end of input`.
   - Fix:
     In `ThemeController::customize()`, prepare:
     `$safeConfigAttr = htmlspecialchars($themeConfigJson, ENT_QUOTES, 'UTF-8');`
     and interpolate in HTML:
     `<div x-data="themeStudio({$safeConfigAttr})" class="flex flex-col lg:flex-row gap-6 h-[calc(100vh-12rem)]">`
     *(Or wrap the attribute in single quotes: `<div x-data='themeStudio({$themeConfigJson})' ...>`)*.

2. **Defect 2 (`core/Admin/ThemeController.php:377`)**:
   - Issue: Inside the heredoc `<<<HTML ... HTML;`, `split('\n')` evaluates `\n` in PHP to an ASCII newline byte `0x0A`. In JavaScript, single-quoted string literals spanning a raw newline cause `SyntaxError: Invalid or unexpected token` and break line numbers rendering.
   - Fix:
     Escape the newline in heredoc so it emits a literal `\n` to the browser:
     `<template x-for="line in (config.custom_css ? config.custom_css.split('\\n').length : 1)" :key="line">`

## Mandatory Verification Commands (via /Applications/XAMPP/xamppfiles/bin/php)
1. `/Applications/XAMPP/xamppfiles/bin/php tests/M3EmpiricalChallenger2Test.php`
   Must pass 100% (14 / 14 PASS).
2. `/Applications/XAMPP/xamppfiles/bin/php tests/ChallengerM3StressTest.php`
   Must pass 100% (19 / 19 PASS).
3. `/Applications/XAMPP/xamppfiles/bin/php tests/AdversarialM3Test.php`
   Must pass 100% (6 / 6 PASS).
4. `/Applications/XAMPP/xamppfiles/bin/php tests/EnterpriseOverhaulTest.php --milestone=m3`
   Must pass 100% (4 / 4 PASS).
5. Full regression across existing phases:
   `for t in tests/Phase*Test.php; do /Applications/XAMPP/xamppfiles/bin/php $t || exit 1; done`

## Mandatory Warning
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A teamwork_preview_auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.

Write your report to `handoff.md` and send a message when finished.


## 2026-10-18T17:23:34Z
You are Worker M3 Iteration 2 for the Heartbeat CMS Enterprise Refinement project.
Your working directory: /Users/risinger/Documents/VSCode/Heartbeat/.agents/teamwork/teamwork_preview_worker_m3_it2
Read your instructions at: /Users/risinger/Documents/VSCode/Heartbeat/.agents/teamwork/teamwork_preview_worker_m3_it2/DISPATCH.md
Read the original request at: /Users/risinger/Documents/VSCode/Heartbeat/ORIGINAL_REQUEST.md
Read the project scope at: /Users/risinger/Documents/VSCode/Heartbeat/PROJECT.md
Read Challenger 2 handoff at: /Users/risinger/Documents/VSCode/Heartbeat/.agents/teamwork/teamwork_preview_challenger_m3_2/handoff.md

MANDATORY INTEGRITY WARNING:
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A teamwork_preview_auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.

Remediate the 2 frontend defects in core/Admin/ThemeController.php. Run tests via /Applications/XAMPP/xamppfiles/bin/php tests/M3EmpiricalChallenger2Test.php and all test suites. Document in handoff.md and send a message when finished.
