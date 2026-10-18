# Task Assignment: Explorer 3 (Survey: Local Google Fonts, Global & Granular SEO, Compliance & Tests)

**Working Directory**: /Users/risinger/Documents/VSCode/Heartbeat/.agents/teamwork/teamwork_preview_explorer_survey_3
**Role**: Codebase Researcher / Explorer
**Original Request Path**: /Users/risinger/Documents/VSCode/Heartbeat/ORIGINAL_REQUEST.md

## Scope & Objective
Survey the Heartbeat CMS codebase specifically for Requirements R5, R6, R7, R8, and test requirements:
- R5: Privacy-first GDPR-compliant local Google Fonts downloader in admin (`/admin/appearance/fonts` or `/admin/fonts`):
  - Catalog of popular Google Fonts (Inter, Roboto, Poppins, Playfair Display, Montserrat, Plus Jakarta Sans, etc.) with live previews.
  - Multi-select and download web fonts (`.woff2`) to `/public/assets/fonts/`.
  - Zero external Google CDN requests at runtime on frontend.
  - Assignment of local fonts to Theme Heading and Body styles.
- R6: Global site-wide SEO & Webmaster verifications in `/admin/seo`:
  - Global indexing switch (`index, follow` vs `noindex, nofollow`) with prominent warning banner in admin bar when `noindex` is active.
  - Google Search Console and Bing Webmaster verification meta tags / tokens.
  - Automated XML sitemap (`/sitemap.xml`) and `robots.txt` generation honoring indexation flag.
- R7: Per-page & per-post granular SEO controls in `/admin/content/edit/{id}`:
  - Custom SEO Title (fallback to post title).
  - Custom Meta Description with live Google SERP snippet counter and preview.
  - Per-item robots control (`noindex`, `nofollow`).
  - Canonical URL override.
- R8 & Testing:
  - `SOURCES.md` requirements and open source compliance.
  - Existing testing structure and how `tests/EnterpriseOverhaulTest.php` should be set up.

## Instructions
1. Read `/Users/risinger/Documents/VSCode/Heartbeat/ORIGINAL_REQUEST.md`.
2. Inspect the codebase (SEO controllers/models/views, fonts/assets structure, content editor, sitemap/robots, test suite).
3. Produce a structured survey report in your working directory at `analysis.md` and `handoff.md`.
4. Include file paths, line numbers, current structure, exact changes needed, dependencies, and risks.
5. Send a message to parent when complete referencing your report.


## 2026-10-18T15:50:19Z
Sender: 9bb52aef-126b-46f1-907e-b4b44da3f353
Content:
You are Explorer 3 for the Heartbeat CMS Enterprise Refinement project.
Your working directory: /Users/risinger/Documents/VSCode/Heartbeat/.agents/teamwork/teamwork_preview_explorer_survey_3
Read your task instructions at: /Users/risinger/Documents/VSCode/Heartbeat/.agents/teamwork/teamwork_preview_explorer_survey_3/DISPATCH.md
Read the immutable original request at: /Users/risinger/Documents/VSCode/Heartbeat/ORIGINAL_REQUEST.md
Investigate the codebase for Requirements R5, R6, R7, R8, and test requirements.
Document your findings in analysis.md and handoff.md in your working directory.
When done, use send_message to report back to parent.
