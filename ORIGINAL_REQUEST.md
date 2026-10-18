# Original User Request

## 2026-10-18T15:47:50Z

Heartbeat CMS comprehensive enterprise refinement: De-cramp and widen the UI/Editor canvas, decouple theme color schemes from CMS brand colors, build a live Theme Customizer CSS editor (WordPress style), create a local Google Fonts downloader with live previews, and implement full global and per-content SEO controls (Google Search Console, Bing Webmaster, noindex/nofollow, custom meta title and description).

Working directory: /Users/risinger/Documents/VSCode/Heartbeat
Integrity mode: development

## Requirements

### R1. UI & Editor De-Cramping & Canvas Widescreen Freedom
- Eliminate the cramped ("gestauchte") layout in the admin area and visual page editor. Remove the rigid `max-w-7xl` (1280px) bottleneck on editor screens in `AdminView.php` and allow the canvas to utilize widescreen displays (`max-w-[1800px]` or fluid `w-full` with comfortable padding).
- Provide a collapsible Inspector sidebar so users can focus on a wide, immersive editing experience.
- Ensure cards, columns, and widgets render with clean margins, paddings, and true visual hierarchy: 3-column cards must have ample width (at least 360px+ each on 1080p/widescreen displays) without squishing content.

### R2. Complete UX & Navigation Audit Across All Admin Routes
- Audit all admin routes (`/admin`, `/admin/content`, `/admin/media`, `/admin/themes`, `/admin/seo`, `/admin/users`, `/admin/settings`, `/install`):
  - Ensure zero horizontal overflows and consistent 8pt spacing rhythm.
  - Maintain legible typography and clear visual cues.
  - Guarantee solid contrast without transparent overlapping panels.
  - Keep UI copy concise and professional.

### R3. Decouple Theme Color Schemes from CMS CI
- Decouple frontend theme color schemes completely from the Heartbeat CMS admin CI (`#fb326f`).
- Each theme (One-Pager, Corporate, Real Estate, Startup) must have its own distinct, polished color palette and styling variables defined independently of CMS admin branding.
- CMS admin brand accent (`#fb326f`) remains exclusive to the admin backend.

### R4. Theme Customizer & Live CSS Editor (WordPress Customizer Style)
- Build an interactive Theme CSS Editor / Customizer under `/admin/themes/customize`:
  - Code editor textarea with line numbers, monospace font, and clean syntax editing.
  - Live preview frame reflecting changes on the active theme in real time.
  - One-click "Änderungen speichern" persisting to `storage/custom-css/{theme}.css` or `themes/{theme}/custom.css`.
  - Frontend themes automatically inject the compiled custom CSS safely into the `<head>`.

### R5. GDPR-Compliant Local Google Fonts Downloader & Typography System
- Implement a privacy-first, GDPR-compliant local Google Fonts downloader inside the CMS admin under `/admin/appearance/fonts` (or `/admin/fonts`):
  - Searchable or curated catalog of popular Google Fonts (Inter, Roboto, Poppins, Playfair Display, Montserrat, Plus Jakarta Sans, etc.) with live typography previews.
  - Ability to select and download multiple web font families (`.woff2`) directly to the local filesystem (`/public/assets/fonts/`).
  - Zero external Google CDN requests at runtime on the frontend (100% self-hosted).
  - One-click assignment of downloaded local fonts to Theme Heading and Body styles.

### R6. Global Site-Wide SEO & Webmaster Verifications
- In the CMS SEO settings (`/admin/seo`):
  - Global site indexing switch: Toggle between `index, follow` (Live) and `noindex, nofollow` (Staging / Development mode) with a prominent warning banner in the admin bar when `noindex` is active.
  - Direct integration fields for Google Search Console verification meta tag / token.
  - Direct integration fields for Bing Webmaster Tools verification meta tag / token.
  - Automated XML Sitemap (`/sitemap.xml`) and `robots.txt` generation honoring the indexation flag.

### R7. Per-Page & Per-Post Granular SEO Controls
- Add a dedicated SEO & Social Meta panel to all content editing screens (`/admin/content/edit/{id}` for pages, posts, CPTs):
  - Custom SEO Title (with fallback to post title).
  - Custom Meta Description with live Google SERP snippet character counter and preview.
  - Per-item robots control: Individual toggle for `noindex` and `nofollow`.
  - Canonical URL override option.

### R8. Legal & Open Source Compliance Documentation
- Ensure no copyright, licensing, or GDPR violations occur.
- Only utilize commercially permissible open source (MIT, Apache 2.0, OFL for fonts).
- Document all inspirations, external libraries, and font licenses in a dedicated `SOURCES.md` file in the project root.

## Verification & Acceptance Criteria

### Automated Verification Suite
- [ ] Create and run `tests/EnterpriseOverhaulTest.php` verifying:
  - 1. Editor container width is unconstrained by `max-w-7xl` and allows widescreen canvas layout.
  - 2. All admin routes return HTTP 200 without PHP errors or template warnings.
  - 3. Theme custom CSS saves and injects into frontend `<head>`.
  - 4. Local font downloading writes `.woff2` files to `/public/assets/fonts/` and generates valid `@font-face` CSS without calling Google CDN.
  - 5. Global `noindex` and per-page `noindex` directives render correctly in frontend HTML.
  - 6. Google Search Console and Bing Webmaster meta tags render in frontend `<head>`.
  - 7. `SOURCES.md` exists and documents all utilized open-source licenses and fonts.

### UX & Visual Acceptance Criteria
- [ ] Visual editor displays 3-column cards with at least 360px+ width per card on desktop, eliminating any cramped appearance.
- [ ] Themes have distinct color schemes independent of `#fb326f`.
- [ ] Theme Customizer allows live CSS editing with instant preview.
- [ ] Fonts can be browsed, previewed, downloaded locally, and applied to theme typography.
