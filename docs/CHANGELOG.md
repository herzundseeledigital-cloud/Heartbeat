# Heartbeat CMS - Living Changelog

All notable changes and architectural milestones are documented in this file.

---

## [Phase 6: The 4 Default Themes & Production Release Builder] - 2026-10-17

### Added
- **The 4 Production Default Themes (`themes/`):**
  - **One-Pager Starter Theme (`themes/default/`, slug: `one-pager`):** Single-page marketing website featuring smooth scrolling (`scroll-smooth`), high-impact Hero with dual CTAs, responsive features and services grids, client testimonials, Alpine.js accordion FAQ, and honeypot-protected contact form.
    - Templates: `header.php`, `footer.php`, `home.php`, `page.php`, `single.php`, `404.php`.
  - **Clean Corporate Theme (`themes/corporate/`, slug: `corporate`):** Multi-page agency and enterprise theme with sticky navigation, accessible multi-level dropdown menus (`aria-haspopup="true"`, `aria-expanded`, `role="menu"`), and CPT-powered Team profiles (`team_member`) and Case Studies (`case_study`).
    - Templates: `header.php`, `footer.php`, `home.php`, `page.php`, `single.php`, `team.php`, `case-studies.php`, `archive.php`, `404.php`.
  - **Classic Real Estate Theme (`themes/real-estate/`, slug: `real-estate`):** Luxury property listing website powered by the `property` CPT. Features Alpine.js live filterable property grid (location, type, price, bedrooms), gallery lightbox modal with keyboard navigation, and property-specific inquiry form.
    - Templates: `header.php`, `footer.php`, `home.php`, `property.php`, `page.php`, `single.php`, `404.php`.
  - **Modern Startup Theme (`themes/startup/`, slug: `startup`):** SaaS and tech theme featuring instant Dark/Light mode toggle (`localStorage` persistence), interactive monthly/yearly pricing tables with "Save 20%" discount badge calculation, and 1-click Stripe Checkout integration (`/api/stripe/checkout?plan=pro`).
    - Templates: `header.php`, `footer.php`, `home.php`, `pricing.php`, `page.php`, `single.php`, `404.php`.
- **Universal Contact & Inquiry Controller (`core/Http/ContactController.php`):**
  - Unified endpoints `POST /api/contact` and `POST /contact` handling form submissions with AJAX or standard HTTP fallback.
  - Invisible Honeypot Anti-Spam Trap (`_hb_hp`): Silently catches and drops bot submissions returning HTTP 200 without notifying the spammer or sending an email.
  - Strict CSRF token verification, input sanitization, and structured email notifications via `Mailer::send()`.
  - Handles general inquiries and CPT property inquiries (`property_id`, `property_title`).
- **Template Resolution & Dynamic CPT Archive Routing in `core/App.php`:**
  - Robust template resolution hierarchy: verifies theme-specific templates (`home.php`, `page.php`, `single.php`, `team.php`, `case-studies.php`, `property.php`, `pricing.php`, `archive.php`, `404.php`) before falling back to default core shells.
  - Dynamic CPT archive routes (`/immobilien`, `/team`, `/referenzen`, etc.) querying `PostService` and injecting records into theme templates.
- **Theme Template Scanning in Tailwind JIT Compiler (`core/Themes/CssCompiler.php`):**
  - `extractThemeClasses(Theme $theme)`: Scans all theme `.php` and `.html` templates for CSS classes and compiles them dynamically alongside block editor classes.
  - Expanded Tailwind utility palette including responsive modifiers (`sm:`, `md:`, `lg:`, `xl:`), dark mode variants (`dark:`), CSS variables, and layout classes.
- **Theme-Aware Starter Content Seeding in Installer (`core/Installer/InstallerService.php`):**
  - Updated `seedStarterContent()` to seed custom post types corresponding to the selected or default theme (team members & case studies for Corporate, luxury listings for Real Estate, pricing tiers for Startup).
- **Dual Production Release Builders (`scripts/`):**
  - `scripts/build-release.js`: Node.js packaging tool with file verification, strict exclusion of secrets and dev state, computing SHA-256 checksums.
  - `scripts/build-release.php`: Zero-exec pure-PHP packager utilizing native `ZipArchive` requiring zero Node.js or shell dependencies.
  - Produces clean `dist/heartbeat-cms-install.zip` (~0.29 MB) ready for 1-click deployment on Webgo, IONOS, HostEurope, Mittwald, or any cPanel/Plesk host.
- **Automated Verification Suite:**
  - `tests/Phase6VerificationTest.php`: Complete 10-dimension test suite verifying theme discovery, template integrity, all 4 theme features, universal contact controller, live Tailwind JIT compiler, and production release zip. 100% passed with zero regressions.

### Verified
- Automated test suite `tests/Phase6VerificationTest.php` executed with 100% pass rate.
- Full regression across all 6 phases (`Phase1VerificationTest.php` through `Phase6VerificationTest.php`) executed with 100% pass rate.
- Full syntax/lint check on all 95 PHP files in the project (`0 syntax errors detected`).
- Production release packages verified (`0.29 MB` pure-PHP and `0.31 MB` Node.js).

---

## [Phase 5: Built-in Agency Modules & Lifecycle Tools] - 2026-10-17

### Added
- **Transactional Mail Engine (`core/Mail/`):**
  - `MailDriverInterface.php`: Contract for transactional email drivers.
  - `ResendDriver.php`: Direct REST API integration via lightweight cURL communicating with `https://api.resend.com/emails` with Bearer auth, custom domain verification, and Reply-To support.
  - `SmtpDriver.php`: Native pure-PHP socket SMTP client using `stream_socket_client` and manual RFC 5321 protocol commands (`EHLO`, `STARTTLS`, `AUTH LOGIN`, `MAIL FROM`, `RCPT TO`, `DATA`, `QUIT`) with zero external composer dependencies.
  - `LogDriver.php`: Local development mail logger saving to `storage/logs/mail.log`.
  - `Mailer.php`: Central mail facade with driver auto-resolution, options store, and `sendTest()` diagnostic tool providing real-time connection status.
- **Zero-Exec Database & File Backups (`core/Backup/`):**
  - `BackupService.php`: Complete backup engine generating pure-PHP SQL dumps via PDO table streaming (`SHOW CREATE TABLE` + batched `SELECT`), zipped with `/config`, `/uploads`, and `/themes` via native `ZipArchive` without requiring `exec()`, `shell_exec()`, or `mysqldump`. Features 5-backup automated retention pruning and secure cron trigger endpoint (`/api/backup/cron?token=...`).
- **1-Click Over-The-Air (OTA) Core Self-Updater (`core/Updater/`):**
  - `UpdaterService.php`: Checks GitHub releases (supports private repository tokens), compares semantic versions, creates automated pre-update safety snapshots, extracts release bundles into `/core` and `/public/assets/` while **strictly preserving** `/config`, `/uploads`, `/storage`, and custom `/themes`, runs pending schema migrations, and purges all caches.
- **DSGVO Cookieless Analytics Engine (`core/Analytics/`):**
  - `AnalyticsService.php`: 100% cookieless and TDDDG-compliant privacy analytics without consent banners. Masks IPv4 addresses to `/24` and IPv6 to `/48`, computes irreversible daily visitor hashes using daily rotating cryptographic salts, tracks devices, browsers, and clean referrers, and aggregates visits in `{prefix}analytics_daily`.
- **Built-in SEO Engine & Universal AI Assistant (`core/Seo/` & `core/Ai/`):**
  - `SeoService.php`: Generates automated `<title>` templates, meta descriptions, robots directives, OpenGraph tags, Twitter Cards, Canonical links, dynamic RFC-compliant XML Sitemap (`/sitemap.xml`), and Schema.org JSON-LD structured data.
  - `AiService.php`: Universal cURL adapter supporting Google Gemini (`gemini-1.5-flash`), OpenAI (`gpt-4o-mini`), and Anthropic Claude (`claude-3-haiku`) for 1-click SEO title/description generation and Block Editor copy generation.
- **GDPR Tools & Local Google Fonts Downloader (`core/Gdpr/`):**
  - `GoogleFontsDownloader.php`: Downloads Google Fonts `.woff2` files locally to `/public/assets/fonts/` and generates local `@font-face` CSS bundles to prevent third-party requests to US servers (100% German court compliant).
  - `GdprService.php`: Implements GDPR Art. 15 user data export (JSON) and Art. 17 right to erasure / anonymization.
- **Stripe Integration & Membership Paywalls (`core/Payments/`):**
  - `StripeService.php`: Direct cURL client creating Stripe Checkout sessions and verifying incoming `Stripe-Signature` headers via pure-PHP HMAC-SHA256 signature verification with 300s replay tolerance. Unlocks `member` role upon successful checkout and logs transactions in `{prefix}orders`.
- **Agency Modules Admin Controller (`core/Admin/`):**
  - `AgencyModulesController.php`: Unified controller handling `/admin/analytics`, `/admin/seo`, `/admin/mail`, `/admin/backups`, `/admin/updater`, `/admin/gdpr`, and `/admin/payments` with Client-Guard isolation.
  - `AdminView.php`: Updated sidebar navigation adding all 7 Phase 5 agency modules with custom Material Design 3 SVG icons.
- **Database Schema Migration (`core/Database/Schema/`):**
  - `002_phase5_modules.php`: Adds `{prefix}analytics_daily` and `{prefix}orders` with proper indexing for SQLite and MySQL.
- **Automated Verification Suite:**
  - `tests/Phase5VerificationTest.php`: Complete automated test suite covering Mailer, BackupService, UpdaterService, AnalyticsService, SeoService, AiService, GoogleFontsDownloader, GdprService, StripeService, Admin controller, and Client-Guard isolation.

### Verified
- Automated test suite `tests/Phase5VerificationTest.php` executed with 100% pass rate.
- Zero regressions on Phase 1, Phase 2, Phase 3, and Phase 4 test suites.
- Full syntax/lint check on all 49 PHP files in the project (`0 syntax errors detected`).
- Production release builder (`node scripts/build-release.js`) executed, generating a clean `0.23 MB` `heartbeat-cms-install.zip`.

---

## [Phase 4: Live Theme Engine, Caching & Custom Code Manager] - 2026-10-17

### Added
- **Theme Architecture & Discovery Engine (`core/Themes/`):**
  - `Theme.php`: OOP theme model with design tokens (primary, hover, background, card, border, text, accent), typography tokens, CPT blueprints, templates, settings, and CSS variable generation (`:root { --hb-primary: ... }`).
  - `ThemeService.php`: Central theme manager for discovery across `themes/`, active theme resolution, switching, live token updating, template loading with fallback, and CSS compilation hooks.
  - 4 Default Theme Manifests: One-Pager Starter (`themes/default/`), Clean Corporate (`themes/corporate/`), Classic Real Estate (`themes/real-estate/`), and Modern Startup (`themes/startup/`).
- **Live-Server Tailwind JIT Utility Compiler (`core/Themes/`):**
  - `CssCompiler.php`: Server-side native PHP Tailwind CSS extractor and compiler running without Node.js, npm, or exec(). Scans JSON block trees from `{prefix}posts` and template HTML, compiles utility classes, responsive media queries (`sm:`, `md:`, `lg:`, `xl:`), hover/focus variants, and custom rules into a minified static bundle saved at `/storage/cache/css/theme_{slug}.css`.
  - HTTP Static CSS Asset Route: `/assets/css/theme.css` serving the active theme bundle with 304 ETag validation and browser caching headers.
- **Multi-Tier Caching Engine (`core/Cache/`):**
  - `PageCache.php`: Static Full-Page HTML caching in `/storage/cache/html/` delivering pure static HTML to anonymous visitors for **< 20ms TTFB**. Emits `X-Cache: HIT`/`MISS` and `X-Heartbeat-Cache` headers. Features automatic cache invalidation upon any content mutation (`create`, `update`, `trash`, `restore`, `delete` in `PostService`), theme change, or custom code update.
  - `ObjectCache.php`: Multi-driver query and object cache supporting APCu (`apcu_fetch`) with atomic serialized file-based fallback in `/storage/cache/objects/`.
  - `Cache.php`: Unified static facade (`Cache::page()`, `Cache::object()`, `Cache::get()`, `Cache::set()`, `Cache::remember()`, `Cache::flush()`).
- **Safe Custom Code Manager & Safe-Mode Recovery (`core/CustomCode/`):**
  - `CustomCodeService.php`: Injects custom snippets into `<head>` (before `</head>`), opening `<body>` (after `<body...>`), and closing `</body>` (before `</body>`).
  - Self-Healing Disaster Recovery Safe-Mode: Instantly bypasses all custom code via URL query parameters (`?hb_safe_mode=1` or `?safe_mode={token}`).
- **Admin Theme Studio & Code Manager Controllers (`core/Admin/`):**
  - `ThemeController.php`: Theme library with active indicators and the Live Studio Customizer (`/admin/themes/customize`) featuring live color pickers, typography and container width controls, 1-click design presets, split-screen live preview iframe with device toggles (Desktop, Tablet, Mobile), and an in-browser JIT compilation API (`/admin/api/themes/compile-css`).
  - `CustomCodeController.php`: Interface (`/admin/custom-code`) for managing Head, Body Start, and Body End snippets with 1-click Safe-Mode URL generator, protected by `manage_code` permission and Client-Guard isolation.
  - `AdminView.php`: Updated sidebar navigation removing Phase 4 badges, activating "Design & Themes", and adding the "Code Manager" entry.
- **Automated Verification Suite:**
  - `tests/Phase4VerificationTest.php`: Complete automated test suite covering theme discovery, active switching, JIT CSS compilation, PageCache (<20ms TTFB), ObjectCache, Custom Code injection, Safe-Mode bypass, and Client-Guard isolation.

### Verified
- Automated test suite `tests/Phase4VerificationTest.php` executed with 100% pass rate.
- Zero regressions on Phase 1, Phase 2, and Phase 3 verification suites.
- Full syntax/lint check on all 39 PHP files in the project (`0 syntax errors detected`).
- Production release builder (`npm run build:release`) executed, generating a clean `0.16 MB` `heartbeat-cms-install.zip`.

---

## [Phase 3: Flexible Content Engine, CPT & JSON Block Builder] - 2026-10-17

### Added
- **Custom Meta Data Engine (`core/Content/`):**
  - `MetaService.php`: Complete key-value and JSON custom field manager for posts, pages, CPTs, and users with automatic array encoding/decoding and object cleanup.
- **Custom Post Type (CPT) Engine (`core/Content/`):**
  - `PostType.php`: CPT blueprint data model specifying labels, icon, URL prefix, supports, and custom meta field schemas.
  - `PostTypeRegistry.php`: Central CPT registry managing built-in types (`page`, `post`, `property`, `team_member`, `case_study`), auto-discovering JSON blueprints from `config/cpt/` and `themes/{theme}/cpt/`.
  - `PostService.php`: High-level content repository for querying, status filtering, collision-free slug generation, pre-rendering HTML from blocks, and trash lifecycle.
- **Structured JSON Block Engine & Condition Toolkit (`core/Blocks/`):**
  - `BlockRegistry.php`: Registry of 13 core blocks (`hero`, `heading`, `paragraph`, `image`, `button`, `columns`, `card`, `features`, `faq`, `quote`, `alert`, `spacer`, `popup`) with categorized schemas and default attributes.
  - `BlockRenderer.php`: Block tree HTML compiler generating semantic, accessible HTML with Tailwind utility classes. Includes the Alpine.js reactive condition engine for time delays, scroll depth %, exit intent, and Member-role paywalls.
- **Media Manager & Image Pipeline (`core/Media/`):**
  - `MediaService.php`: Secure uploads with strict MIME inspection via `finfo_file`, defense-in-depth `.htaccess` execution guard, native GD WebP/AVIF conversions, intermediate sizes (`thumbnail`, `medium`, `large`), responsive `srcset` and HTML5 `<picture>` tag generator.
- **Admin Content & Media Controllers (`core/Admin/`):**
  - `ContentController.php`: Unified manager for Pages, Posts, and CPTs featuring status tabs, search, and the Visual JSON Block Builder Canvas with Alpine.js reactivity, inserter drawer, inline block controls, live media picker, and raw JSON code view.
  - `MediaController.php`: Mediathek manager with grid/list view, drag-and-drop upload dropzone, media details modal, and JSON API endpoint for the block editor.
  - `AdminView.php`: Updated sidebar navigation removing Phase 3 placeholders and setting active navigation highlighting.
- **Dynamic Frontend Routing (`core/App.php`):**
  - Dynamic page resolution for `/`, `/:slug`, and CPT single views (`/{cpt_prefix}/:slug`), with full block tree rendering and branded 404 handler.
- **Automated Verification Suite:**
  - `tests/Phase3VerificationTest.php`: Complete end-to-end verification covering MetaService, CPT registry, all 13 blocks, PostService CRUD, MediaService WebP conversions, controller CSRF/RBAC, and frontend routing.

### Verified
- Automated test suite `tests/Phase3VerificationTest.php` executed with 100% pass rate.
- Zero regressions on Phase 1 & Phase 2 verification suites.
- Full syntax/lint check on all 37 PHP files in the project (`0 syntax errors detected`).
- Production release builder (`npm run build:release`) executed, generating a clean `0.12 MB` `heartbeat-cms-install.zip`.

---

## [Phase 2: Security Core, RBAC, Agency Mode & Admin Shell] - 2026-10-17

### Added
- **Security Core & Session Management (`core/Security/`):**
  - `Session.php`: Session fixation protection (`session_regenerate_id(true)`), subnet & User-Agent fingerprinting to prevent session hijacking, idle timeout checks, and flash messaging store.
  - `RateLimiter.php`: File-backed brute-force rate limiter with sliding decay window (5 attempts / 15 minutes) protecting auth endpoints, tracking remaining attempts and lockout durations.
  - `Rbac.php`: Complete 5-role RBAC engine (`super_admin`, `admin`, `editor`, `user`, `member`), permission lookup matrix, role hierarchy validation (`canManageRole`, `canAssignRole`), and German role labeling.
  - `Security.php`: Enhanced with BCrypt cost 12 password hashing (`hashPassword`, `verifyPassword`, `needsRehash`) and comprehensive Content-Security-Policy (CSP) headers.
- **Authentication Engine (`core/Auth/`):**
  - `Auth.php`: Complete user authentication manager with `attempt()`, session binding, `check()`, `user()`, `id()`, `role()`, `hasRole()`, `can()`, `isSuperAdmin()`, `logout()`, and secure SHA-256 Remember-Me token cookie handling.
- **Agency Mode & Client-Guard (`core/Agency/`):**
  - `AgencyService.php`: White-label branding service reading from `config/env.php` and `hb_options`, dynamic agency support widget renderer, and Client-Guard isolation restricting non-super-admins from destructive system tools.
- **Dark-Mode Admin UI Shell (`core/Admin/`):**
  - `AdminView.php`: Material Design 3 shell inspired by `herzundseele-digital.de` (deep slate/charcoal `#090d16`, warm coral `#f97316`), responsive collapsible sidebar, breadcrumbs, user dropdown menu, and Alpine.js reactive auto-dismissing toast notifications.
  - `AdminController.php`: Full controller handling login, brute-force lockout warnings, logout, interactive KPI dashboard, full User CRUD (create, edit, delete with self-protection and last super-admin protection), tabbed site settings, system status and 1-click cache purge, plus placeholder views for upcoming phases.
- **Admin Routing (`core/App.php`):**
  - Complete `/admin/*` routing tree registered with proper HTTP verbs and CSRF protection.
- **Automated Verification Suite:**
  - `tests/Phase2VerificationTest.php`: Comprehensive end-to-end test verifying session security, rate limiter, RBAC matrix, client-guard, auth lifecycle, user CRUD, admin endpoints, and views.

### Verified
- Automated test suite `tests/Phase2VerificationTest.php` executed with 100% pass rate.
- Zero regression on Phase 1 test suites (`tests/Phase1VerificationTest.php` and `tests/test_installer_flow.php`).
- Full syntax/lint check on all 27 PHP files in the project (`0 syntax errors detected`).

---

## [Phase 1: Core Foundation, Local Dev Runner & 4-Step Web Installer] - 2026-10-17

### Added
- **Directory Structure:** Clean core architecture separating updatable `/core` logic strictly from `/config`, `/uploads`, `/themes`, `/plugins`, and `/storage`.
- **Autoloader (`core/Autoloader.php`):** High-performance, zero-dependency PSR-4 autoloader (`Heartbeat\` -> `core/`) with Composer vendor fallback.
- **Bootstrap (`core/Bootstrap.php`):** Central system initialization, environment configuration loader, zero-leak error/exception handlers, and secure PHP session setup.
- **HTTP Engine (`core/Http/`):**
  - `Request.php`: Full request abstraction (JSON body, POST, GET, headers, secure IP, base URL).
  - `Response.php`: Fluent HTTP response generator (`html`, `json`, `redirect`, custom headers).
  - `Router.php`: Fast regex-based route matcher with parameterized routes (`:param`) and controller dispatch.
- **Security Core (`core/Security/`):**
  - `Csrf.php`: Cryptographic 256-bit CSRF token generation, session binding, form inputs, meta tags, and request verification.
  - `Security.php`: Context-aware HTML escaping (`Security::e()`), slug/filename sanitization, and strict HTTP security headers.
- **Database Engine & Auto-Migration (`core/Database/`):**
  - `Database.php`: Unified PDO wrapper supporting MySQL 8+/MariaDB 10.5+ and SQLite 3 (with WAL mode & foreign keys), transactions, dynamic table prefixing (`{prefix}`).
  - `MigrationRunner.php`: Automated schema discovery and execution engine tracking batches in `{prefix}migrations`.
  - `Schema/001_initial_schema.php`: Cross-database migration creating options, users, posts, meta_data, terms, and term_relationships.
- **Web Installer Wizard (`core/Installer/`):**
  - `SystemChecker.php`: Audits PHP version (8.2+/8.3+), PDO extensions, cURL, GD/Imagick, ZipArchive, MBString, JSON, and directory permissions.
  - `InstallerService.php`: Database connectivity testing, `config/env.php` writer, migration runner, initial Super-Admin & starter page seeder, and installer locking (`config/installed.lock`).
  - `InstallerController.php`: 4-step interactive GUI Web Installer styled in Heartbeat dark slate & warm coral aesthetic, powered by local Alpine.js.
- **Dual-Mode Web Routing:**
  - `router.php`: Local development server router for `php -S localhost:8000 -t public router.php` serving static assets and protecting core folders.
  - `.htaccess` & `public/.htaccess`: Production Apache/Nginx rewrite rules with directory protection and Gzip/MIME headers.
- **Frontend & Admin Entry Points:**
  - `public/index.php`: Front controller booting `Heartbeat\App`.
  - `public/assets/css/installer.css`: Standalone precompiled stylesheet.
  - `public/assets/js/alpine.min.js`: Self-hosted micro-reactivity library (zero external requests).
- **Tooling & Release Packager:**
  - `scripts/dev.js`: One-command local dev runner (`npm run dev`).
  - `scripts/build-release.js`: Self-contained production packager (`npm run build:release`) creating `heartbeat-cms-install.zip`.
- **Master Documentation:**
  - `PROMPT.md`: Complete master instructions archived in project root.
  - `docs/ARCHITECTURE.md`, `docs/FEATURES_AND_MANUAL.md`, `docs/DEVELOPER_AND_THEMING.md`, `docs/LOCAL_SETUP.md`, `docs/MANAGED_HOSTING_GUIDE.md`, and `docs/CHANGELOG.md`.

### Verified
- Full syntax/lint check on all PHP files (`php -l`).
- Executable scripts verified (`scripts/dev.js`, `scripts/build-release.js`, `bin/php`).
- Web Installer verified: System checks, live SQLite connection, initial migrations, user creation, starter page seeding, and installer locking.
