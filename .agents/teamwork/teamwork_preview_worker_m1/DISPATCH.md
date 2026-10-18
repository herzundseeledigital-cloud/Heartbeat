# Task Assignment: Worker M1 (Admin Shell & Editor Canvas Freedom & UX Overhaul)

**Working Directory**: /Users/risinger/Documents/VSCode/Heartbeat/.agents/teamwork/teamwork_preview_worker_m1
**Role**: Software Engineer / Worker
**Original Request Path**: /Users/risinger/Documents/VSCode/Heartbeat/ORIGINAL_REQUEST.md
**Project Scope Document**: /Users/risinger/Documents/VSCode/Heartbeat/PROJECT.md
**Explorer Survey Reference**: /Users/risinger/Documents/VSCode/Heartbeat/.agents/teamwork/teamwork_preview_explorer_survey_1/handoff.md and analysis.md

## Scope & Objective
Implement Milestone M1 (Requirements R1 and R2):
1. **Admin Container Widescreen Freedom (R1)**:
   - In `core/Admin/AdminView.php:203`: Replace `max-w-7xl` with `max-w-[1800px]` in the main container `<main class="flex-1 p-4 sm:p-6 md:p-8 max-w-[1800px] w-full mx-auto">`.
   - In `public/assets/css/admin.css`: Add the utility class `.max-w-\[1800px\] { max-width: 1800px; }`.
2. **Editor Canvas & Column Breakpoints (R1)**:
   - In `public/assets/css/admin.css:836-847`: Update `.editor-col-3` so that:
     - On smaller screens (< 768px): 100% width.
     - On medium screens (768px to 1279px): 50% width (2 columns) to avoid cramped cards.
     - On widescreen (>= 1280px / 1440px): 3 columns (`calc(33.333% - 1rem)`) with `min-width: 360px` to guarantee 360px+ per card without squishing.
3. **Collapsible Inspector Sidebar (R1)**:
   - In `core/Admin/ContentController.php`: Ensure the visual page editor has an easily accessible toggle/close button for the Inspector sidebar, allowing an expansive, uninhibited visual editing experience.
4. **Admin Routes Audit & Critical Bug Fixes (R2)**:
   - `core/App.php`: Register route `/admin/content`:
     `$this->router->get('/admin/content', fn($r, $p) => (new \Heartbeat\Admin\ContentController())->index($r, ['type' => 'page']));`
   - `core/Admin/MediaController.php:153, 156`: Escape `\$event` in heredocs:
     `@drop.prevent="handleDrop(\$event)"` and `@change="handleFileSelect(\$event)"` to eliminate fatal PHP undefined variable error.
   - `core/Admin/AdminController.php:533`: Fix `createUserModal` cancel button from `@click="modalOpen = false"` to `onclick="HeartbeatUI.closeModal('createUserModal')"`.
   - `core/Installer/InstallerController.php:25`: Return HTTP 200 when installation is locked (`return Response::html($this->renderLockedView(), 200);`) so route audit succeeds.

## Exclusive Write Ownership
You own and may ONLY edit:
- `core/Admin/AdminView.php`
- `public/assets/css/admin.css`
- `core/Admin/ContentController.php`
- `core/App.php`
- `core/Admin/MediaController.php`
- `core/Admin/AdminController.php`
- `core/Installer/InstallerController.php`
- Files within your working directory: `/Users/risinger/Documents/VSCode/Heartbeat/.agents/teamwork/teamwork_preview_worker_m1/`

## Mandatory Integrity Warning
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A teamwork_preview_auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.

## Verification Requirements
- Execute existing test suites using `/Applications/XAMPP/xamppfiles/bin/php`:
  `/Applications/XAMPP/xamppfiles/bin/php tests/Phase1VerificationTest.php`
  `/Applications/XAMPP/xamppfiles/bin/php tests/Phase2VerificationTest.php`
  `/Applications/XAMPP/xamppfiles/bin/php tests/Phase3VerificationTest.php`
- Execute route verification script testing all 8 admin routes (`/admin`, `/admin/content`, `/admin/media`, `/admin/themes`, `/admin/seo`, `/admin/users`, `/admin/settings`, `/install`) and verify each returns HTTP 200.
- Report all commands, test outputs, and diffs in `handoff.md`. Send completion message when finished.


## 2026-10-18T16:01:12Z
You are Worker M1 for the Heartbeat CMS Enterprise Refinement project.
Your working directory: /Users/risinger/Documents/VSCode/Heartbeat/.agents/teamwork/teamwork_preview_worker_m1
Read your task instructions at: /Users/risinger/Documents/VSCode/Heartbeat/.agents/teamwork/teamwork_preview_worker_m1/DISPATCH.md
Read the immutable original request at: /Users/risinger/Documents/VSCode/Heartbeat/ORIGINAL_REQUEST.md
Read the project scope at: /Users/risinger/Documents/VSCode/Heartbeat/PROJECT.md
Read explorer findings at: /Users/risinger/Documents/VSCode/Heartbeat/.agents/teamwork/teamwork_preview_explorer_survey_1/handoff.md

MANDATORY INTEGRITY WARNING:
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A teamwork_preview_auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.

Implement Milestone M1 (Requirements R1 and R2), run test verification using /Applications/XAMPP/xamppfiles/bin/php, document in handoff.md, and send a message when finished.
