# Execution Plan: Heartbeat CMS Enterprise Refinement

## Objective
Coordinate the implementation, verification, and hardening of Heartbeat CMS requirements R1 through R8, tests/EnterpriseOverhaulTest.php, and SOURCES.md.

## Phases

### Phase 0: Survey & Scope Mapping
- Dispatch 3 parallel Explorers:
  - Explorer 1: UI/Editor De-cramping, Canvas Widescreen, Inspector, Card layouts, Admin routes UX/navigation (R1, R2).
  - Explorer 2: Theme color decoupling, Theme Customizer & Live CSS editor, Frontend theme injection (R3, R4).
  - Explorer 3: GDPR Local Google Fonts Downloader, Global SEO & Webmaster verifications, Per-page/post SEO controls, Legal compliance (R5, R6, R7, R8).
- Output: Synthesize reports into `PROJECT.md` with complete Feature Inventory.

### Phase 1: Architecture & Decomposition
- Define milestones M1 - M5 (or as needed) in `PROJECT.md`:
  - M1: Admin & Visual Editor Canvas Widescreen Freedom & UX Overhaul (R1, R2)
  - M2: Theme Color Decoupling & Independent Palettes (R3)
  - M3: Theme Customizer & Live CSS Editor (R4)
  - M4: Local Google Fonts Downloader & Typography System (R5)
  - M5: Global & Granular SEO System & Webmaster Verification (R6, R7)
  - M6: Legal Compliance Documentation & SOURCES.md (R8)
  - M7: Final Milestone: E2E Test Suite Execution & Adversarial Hardening
- Launch Dual Track:
  - Track A: E2E Testing Track Orchestrator (Tiers 1-4 test suite, creating `tests/EnterpriseOverhaulTest.php` and `TEST_READY.md`)
  - Track B: Implementation Track (Sub-orchestrators for milestones)

### Phase 2: Dual Track Execution
- Monitor sub-orchestrators for milestones M1 through M6.
- Enforce strict iteration gate on each: Explorer -> Worker -> Reviewer(s) -> Challenger(s) -> Auditor.
- Monitor E2E Testing Track until `TEST_READY.md` is published.

### Phase 3: Final Integration & Hardening
- Phase 1 of Final Milestone: Pass 100% of E2E tests in `tests/EnterpriseOverhaulTest.php` across Tiers 1-4.
- Phase 2 of Final Milestone: Tier 5 Adversarial Coverage Hardening with Challenger -> Worker -> Reviewer -> Auditor.

### Phase 4: Final Reporting & Closure
- Verify all acceptance criteria and automated tests.
- Verify `SOURCES.md`.
- Report full results to parent.
