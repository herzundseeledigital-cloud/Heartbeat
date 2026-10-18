# Task Assignment: Worker M3 (Theme Customizer & Live CSS Editor)

**Working Directory**: /Users/risinger/Documents/VSCode/Heartbeat/.agents/teamwork/teamwork_preview_worker_m3
**Role**: Software Engineer (Worker M3)
**Original Request Path**: /Users/risinger/Documents/VSCode/Heartbeat/ORIGINAL_REQUEST.md
**Project Scope Document**: /Users/risinger/Documents/VSCode/Heartbeat/PROJECT.md
**Explorer Survey Reference**: /Users/risinger/Documents/VSCode/Heartbeat/.agents/teamwork/teamwork_preview_explorer_survey_2/handoff.md and analysis.md
**Test Suite Reference**: /Users/risinger/Documents/VSCode/Heartbeat/TEST_READY.md (Suite 4)

## Scope & Objective
Implement Milestone M3 (Requirement R4: Theme Customizer & Live CSS Editor):

1. **Custom CSS Persistence in `core/Themes/ThemeService.php`**:
   - Implement `getCustomCss(string $slug): string`:
     - Reads from `storage/custom-css/{slug}.css` (resolving via `HEARTBEAT_STORAGE`), falling back to `themes/{slug}/custom.css`.
     - Returns clean custom CSS string (empty string if neither exists).
   - Implement `saveCustomCss(string $slug, string $css): bool`:
     - Ensures `storage/custom-css/` directory exists with 0755 permissions.
     - Saves `$css` to `storage/custom-css/{slug}.css`.
     - Also mirrors to `themes/{slug}/custom.css` if the theme directory exists and is writable.
     - Returns `true` on success.

2. **Theme Customizer View & Controller in `core/Admin/ThemeController.php`**:
   - Fix the critical bug in `ThemeController.php:208`: Replace `$theme->isActive()` with `ThemeService::getInstance()->getActive()->getSlug() === $theme->getSlug()`.
   - In `ThemeController::customize()` (lines 199–215):
     - Pass the clean user custom CSS from `$this->themes->getCustomCss($theme->getSlug())` into the view JSON (do NOT load `$this->compiler->getCss()` which pollutes the editor with the minified framework bundle!).
   - In `ThemeController::save()` (lines 230–270):
     - Extract `custom_css` from input and persist via `$this->themes->saveCustomCss($theme->getSlug(), $customCss)`.
   - In Customizer Alpine.js UI:
     - Interactive code editor textarea with line numbers gutter, monospace font, scroll synchronization, and 2-space Tab indentation (`@keydown.tab.prevent`).
     - Real-time live preview: an `updatePreviewCss()` function in `themeStudio` dynamically updates or injects `<style id="live-custom-css">` in `themePreviewFrame.contentDocument.head` on every `@input` event.
     - "Änderungen speichern" button submitting to `/admin/themes/save`.

3. **Frontend `<head>` Custom CSS Injection**:
   - In `header.php` across all 4 themes (`themes/default/templates/header.php`, `themes/corporate/templates/header.php`, `themes/real-estate/templates/header.php`, `themes/startup/templates/header.php`):
     - Retrieve custom CSS for the active theme via `ThemeService::getInstance()->getCustomCss($theme->getSlug())`.
     - Safely inject into `<head>`:
       ```php
       <?php if (!empty($customCss)): ?>
       <style id="theme-custom-css"><?= $customCss ?></style>
       <?php endif; ?>
       ```
   - In `core/App.php` (fallback header):
     - Also inject `<style id="theme-custom-css"><?= $customCss ?></style>` if custom CSS exists for the active theme.

## Exclusive Write Ownership
- `core/Themes/ThemeService.php`
- `core/Admin/ThemeController.php`
- `themes/default/templates/header.php`
- `themes/corporate/templates/header.php`
- `themes/real-estate/templates/header.php`
- `themes/startup/templates/header.php`
- `core/App.php`
- `storage/custom-css/`
- Files within `/Users/risinger/Documents/VSCode/Heartbeat/.agents/teamwork/teamwork_preview_worker_m3/`

## Mandatory Integrity Warning
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A teamwork_preview_auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.

## Verification Requirements
- Run `/Applications/XAMPP/xamppfiles/bin/php tests/EnterpriseOverhaulTest.php --milestone=m3` (Suite 4 must pass 100%).
- Run all existing phase tests: `for t in tests/Phase*Test.php; do /Applications/XAMPP/xamppfiles/bin/php $t; done`.
- Document your changes, test results, and diffs in `handoff.md` and send a message when complete.


## 2026-10-18T16:59:43Z
[Message] timestamp=2026-10-18T16:59:43Z sender=9bb52aef-126b-46f1-907e-b4b44da3f353 priority=MESSAGE_PRIORITY_HIGH content=You are Worker M3 for the Heartbeat CMS Enterprise Refinement project.
Your working directory: /Users/risinger/Documents/VSCode/Heartbeat/.agents/teamwork/teamwork_preview_worker_m3
Read your task instructions at: /Users/risinger/Documents/VSCode/Heartbeat/.agents/teamwork/teamwork_preview_worker_m3/DISPATCH.md
Read the original request at: /Users/risinger/Documents/VSCode/Heartbeat/ORIGINAL_REQUEST.md
Read the project scope at: /Users/risinger/Documents/VSCode/Heartbeat/PROJECT.md
Read explorer survey at: /Users/risinger/Documents/VSCode/Heartbeat/.agents/teamwork/teamwork_preview_explorer_survey_2/handoff.md
Read test suite requirements in: /Users/risinger/Documents/VSCode/Heartbeat/TEST_READY.md (Suite 4)

MANDATORY INTEGRITY WARNING:
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A teamwork_preview_auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.

Implement Milestone M3 (Requirement R4), run tests via /Applications/XAMPP/xamppfiles/bin/php tests/EnterpriseOverhaulTest.php --milestone=m3, document in handoff.md, and send a message when finished.
