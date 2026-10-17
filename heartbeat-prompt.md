# AGENT INSTRUCTIONS: Development of "Heartbeat CMS"

## 1. PROJECT OVERVIEW & GOAL
Build a modern, lightweight, ultra-secure, and lightning-fast PHP/MySQL-based Hybrid/Headless Content Management System (CMS) named **"Heartbeat"**. 
The primary goal is an agency-ready "Site Factory" alternative to WordPress—allowing rapid local core development in VS Code, effortless FTP/ZIP installation onto any standard Managed Hosting server, and complete live site customization directly in the CMS on the client's server.

- **Primary Stack (Production Runtime):** 
  - **Backend:** PHP 8.3+ (`declare(strict_types=1);`), PDO (MySQL/MariaDB + SQLite local fallback), Composer (PSR-4 Autoloading, bundled in the production release so the target server requires neither SSH nor Composer).
  - **Admin UI & Theme Engine:** Tailwind CSS, Material Design 3 principles, HTML5, Alpine.js (for lightweight reactivity without heavy SPA runtime dependencies).
- **Local Development vs. Production Hosting (CRITICAL DISTINCTION):**
  - **Local Core Dev (VS Code on macOS):** The developer has Node.js/npm available locally. Provide a seamless one-command local dev workflow (`npm run dev` or `composer dev`) that starts PHP's built-in server (`php -S localhost:8000 -t public router.php`) alongside the Tailwind CSS watcher.
  - **Client Rollout Workflow (WordPress-Style):** To launch a client project, the developer runs `npm run build:release` which creates a self-contained `heartbeat-cms-install.zip`. This ZIP is uploaded to the client's Managed Hosting server (Webgo, IONOS, HostEurope, Mittwald), installed via the GUI Web-Installer, and the actual client website is then built and styled directly inside the CMS Theme/Block Editor on the live server.
  - **Server Constraints:** Must run smoothly on standard PHP-FPM + Apache/Nginx setups **without root access, without Node.js/Prisma on the server, without SSH requirement, and without relying on PHP `exec()` / `shell_exec()`**.
  - **Live Server Tailwind Strategy:** Because client customization happens directly on the live Managed Hosting server (where Node.js is unavailable), the Low-Code/AI Theme Editor must include an in-browser Tailwind JIT compiler (or PHP-based CSS generator) that extracts all used classes upon saving in the editor and writes a clean, static, minified `.css` bundle directly to `/storage/cache/css/` on the server.
- **Brand Identity (Admin UI):** Inspired by `https://www.herzundseele-digital.de`.
  - **Typography:** **Inter** (100% self-hosted `.woff2`).
  - **Aesthetic:** Dark modern aesthetic (deep slate/charcoal backgrounds), warm vibrant accent colors, high contrast, generous whitespace, clean Material Design UI components.

---

## 2. EXECUTION MODE: HIGH-VELOCITY PRINCIPAL ENGINEERING (NO PING-PONG)

1. **Zero Ping-Pong & Autonomous Decision Making:**
   - Operate with the autonomy and decisiveness of a **world-class Principal PHP Architect, Lead UI/UX Designer, and Senior Security Engineer**.
   - **Do NOT engage in simulated multi-agent roleplay chat** and **do NOT stop to ask the user trivial questions**. When multiple technical approaches exist, autonomously select the cleanest, most secure, and most maintainable industry best practice for PHP 8.3 and Managed Hosting.
   - Only ask a question if a critical business decision is genuinely ambiguous; otherwise, build and document your decision.

2. **Full-Phase Execution & Model Allocation Matrix:**
   - Execute the project strictly along the **6-Phase Epic Roadmap** below. Within each Phase, build all required files, classes, views, tests, and documentation updates cleanly and completely in one cohesive flow:
     - **Phase 1: Core Foundation, Local Dev Runner & 4-Step Web Installer** *(Recommended Model: **Gemini Pro / Claude Sonnet**)* -> Directory structure, PSR-4 Autoloader, Local Dev Server router (`router.php`), Shared-Hosting `.htaccess`, PDO Database Wrapper, Auto-Migration Runner, and the complete GUI Web-Installer Wizard (`/install`).
     - **Phase 2: Security Core, RBAC, Agency Mode & Admin Shell** *(Recommended Model: **Gemini Pro / Claude Sonnet**)* -> CSRF, Session Security, Security Headers, Auth (Login, Rate Limiting), Role Management (Super-Admin/Agency, Admin, Editor, User, Member), White-Label Config, and the complete Dark-Mode Admin UI Shell (Sidebar, Topbar, Dashboard, Toast notifications).
     - **Phase 3: Flexible Content Engine (CPT & JSON Block Builder)** *(Recommended Model: **Gemini Pro / Claude Sonnet**)* -> Dynamic Content Types (Pages, Posts, Custom Post Types like Real Estate/Team), Custom Meta Fields (JSON-based), Media Manager (native GD/Imagick WebP/AVIF conversion), and the structured JSON Block Editor.
     - **Phase 4: Live Theme Engine, Caching & Custom Code Manager** *(Recommended Model: **Gemini Pro** for JIT CSS logic, or **Gemini Flash**)* -> Theme architecture, Live-Server AI/Tailwind CSS static extraction, Full-Page HTML & Object Caching, and Safe Custom Code Injection (`<head>`, `<body>` with Safe-Mode bypass).
     - **Phase 5: Built-in Agency Modules & Lifecycle Tools** *(Recommended Model: **Gemini Flash**)* -> Transactional Mailer (**Resend API + SMTP Fallback**), **1-Click GitHub Self-Updater**, DSGVO Cookieless Analytics, SEO Engine + AI Assistant, Popup/Paywall Condition Engine, GDPR Tools (Local Google Fonts Downloader), Native PHP Backup (`ZipArchive`), and Stripe Webhook/Checkout integration.
     - **Phase 6: The 4 Default Themes & Production Release Builder** *(Recommended Model: **Gemini Flash**)* -> Implementation of the One-Pager, Corporate, Real Estate, and Startup themes + the `build:release` script that packages the clean, client-ready `heartbeat-cms-install.zip`.

3. **Silent Internal Quality Standard (Built-In Excellence):**
   Every single file you write must inherently satisfy four top-tier engineering standards without needing verbose commentary:
   - **Principal Architecture & QA:** `declare(strict_types=1);` everywhere, PSR-12 formatting, clean separation of concerns, zero dead code, explicit exception handling (never leak raw stack traces), and automated verification.
   - **Lead UX & Design:** Zero unnecessary clicks, clear empty states, instant feedback on save/error, WCAG AA contrast, responsive admin views, faithful to the dark slate/warm accent brand identity.
   - **Senior AppSec (Security by Design):** OWASP Top 10 compliance, 100% prepared statements, context-aware output escaping, CSRF validation on all state-changing requests, strict MIME verification on uploads, and automatic installer locking.

4. **Continuous Living Documentation (STRICT DEFINITION OF DONE):**
   A Phase is NEVER complete until its architecture and features are thoroughly documented in `/docs` (keep documentation concise, structured, and tabular to save context tokens in future sessions!):
   - `/PROMPT.md`: Save a verbatim copy of these master instructions in the project root during Phase 1 so future chat sessions can reference it immediately.
   - `/docs/ARCHITECTURE.md`: Complete system blueprint, directory structure, request lifecycle, security model, caching pipeline, and full SQL/JSON database schemas.
   - `/docs/FEATURES_AND_MANUAL.md`: Comprehensive manual of all implemented CMS features, Admin UI workflows, RBAC permissions, Agency White-Label/Client-Guard flags (`config/env.php`), Resend/SMTP setup, Safe-Mode URL parameters, and AI integration.
   - `/docs/DEVELOPER_AND_THEMING.md`: Technical guide on registering Custom Post Types (CPTs), creating custom JSON blocks, building themes (`theme.json`), and hooking into PSR-14 Actions/Filters.
   - `/docs/LOCAL_SETUP.md` & `/docs/MANAGED_HOSTING_GUIDE.md`: Step-by-step instructions for local VS Code development (`npm run dev`, SQLite/MySQL testing, building the release ZIP) and deploying/installing on Managed Hosting (Webgo, IONOS, DNS for Resend, Cronjobs).
   - `/docs/CHANGELOG.md`: Detailed log of every completed Phase, added classes, database migrations, and architectural decisions.

5. **Token-Guard & Phase Handover Protocol (MANDATORY AT THE END OF EVERY PHASE):**
   To protect the user's Pro Plan token quota and prevent context bloat, you MUST conclude every completed Phase with a visually distinct **🚀 PHASE HANDOVER & TOKEN-GUARD** box containing:
   - **1. Phase Summary:** Bullet list of what was built and verified.
   - **2. Context Reset Reminder:** Explicitly remind the user: *"⚠️ TOKEN-GUARD: Bitte starte jetzt einen **NEUEN CHAT (New Session)** in Antigravity, um deinen Token-Verbrauch minimal zu halten!"*
   - **3. Model Switch Recommendation:** Explicitly tell the user which model to select in the Antigravity dropdown for the upcoming Phase (based on the Model Allocation Matrix in Rule 2).
   - **4. Ready-to-Copy Next Prompt:** Provide a single copy-pasteable code block for the user to paste into the new chat, formatted exactly like this:
     `Lies /PROMPT.md, /docs/ARCHITECTURE.md und /docs/CHANGELOG.md. Führe jetzt Phase [X] ([Name der Phase]) vollständig und autonom gemäß allen Regeln aus.`

---

## 3. CORE SYSTEM ARCHITECTURE & TECHNICAL SPECIFICATIONS

### A. Directory Structure, Web Installer & Deployment Pipeline
- **Clean Separation for 1-Click Updates:** Separate updatable core logic (`/core`) strictly from persistent client directories (`/config`, `/uploads`, `/themes`, `/plugins`, `/storage`).
- **Dual-Mode Routing:** Must work seamlessly via `php -S` locally (`router.php`) AND on Apache/Nginx Managed Hosting (via root `.htaccess` and `public/.htaccess` so it works even if the hoster does not allow changing the domain's Document Root to `/public`, while strictly blocking direct HTTP access to `/core`, `/config`, `/storage`, and `/plugins`).
- **4-Step GUI Web Installer (`/install`):**
  - Automatically triggers if `config/installed.lock` is missing.
  - **Step 1 (System Check):** Verifies PHP version (8.3+), PDO (MySQL/SQLite), cURL, GD/Imagick, ZipArchive, MBString, and folder write permissions (`/storage`, `/uploads`, `/config`).
  - **Step 2 (Database & Environment):** Tests MySQL/MariaDB credentials live via Fetch (plus a 1-click "Use Local SQLite for quick testing" option in local dev), supports custom table prefixes (e.g., `hb_`), and writes a high-performance PHP config array (`config/env.php`).
  - **Step 3 (Site, Agency White-Label & Admin Setup):** Site Title, Base URL (auto-detected), Default Theme selection, optional Agency White-Label toggle, and initial Super-Admin User creation.
  - **Step 4 (Migration & Lock):** Runs all SQL schema migrations, seeds starter pages for the chosen theme, creates `config/installed.lock`, and locks the installer.
- **Release Packaging Script:** A local CLI command (`npm run build:release`) that compiles core assets and packages a clean `heartbeat-cms-install.zip` ready to be uploaded and extracted on any Managed Server.

### B. Flexible Content Engine & Block Architecture (Gutenberg/Spectra Alternative)
- **Custom Post Types (CPT) & Meta Blueprints:** Register custom content types (e.g., `property`, `team_member`, `case_study`) via simple JSON blueprints or Admin UI without writing custom SQL tables. Store flexible custom fields in an indexed JSON column (`meta_data`) alongside standard relational columns.
- **Structured Block Storage:** Store page layouts as a clean **JSON Block Tree** (Block Type, Attributes, Tailwind Classes, InnerBlocks) and render dynamically via clean PHP block templates (with full HTML page caching after first render).
- **Popup, Notification & Paywall Toolkit:** Integrated condition engine (scroll %, time delay, exit intent, or `Member` role gate) rendered via Alpine.js.

### C. High Performance & Caching Engine
- **Multi-Tier Caching:** 
  1. Full-Page Static HTML Cache (serves pure static HTML to anonymous visitors for <20ms TTFB; automatically invalidates when content or theme settings are saved).
  2. Object/Query Cache (APCu/Redis support with automatic fallback to fast local file caching in `/storage/cache`).
- **Asset Pipeline:** Automatic CSS/JS minification, native PHP WebP/AVIF image conversion upon upload with automatic `srcset` helper functions, `<link rel="preload">` and `103 Early Hints` support.

### D. User Roles, RBAC & Agency White-Label Mode
- **Agency Mode & Client-Guard (`config/env.php` & UI):**
  - Allows rebranding the CMS backend (Custom Agency Name, Logo, Accent Color, and a "Contact Agency Support" dashboard widget).
  - **Client-Guard Lock:** Even if a client is given an `Admin` account, the Agency Super-Admin can lock/hide destructive system areas (Custom Code Injection, Core Updater, Raw DB Backups, Agency Branding Settings) via a master flag or Super-Admin permission so clients cannot accidentally break their site.
- **Roles:**
  - **Super-Admin (Agency):** Unrestricted access to all system, code, update, and white-label settings.
  - **Admin (Client Admin):** Full access to users, content, themes, SEO, analytics, and settings (respecting Client-Guard locks).
  - **Editor:** Content management, CPT management, theme configuration, visual block adjustments, SEO & Popups.
  - **User:** Standard content editor (articles, pages, media) — restricted from layout/system changes.
  - **Member:** Frontend-only user role with access to gated/paywalled content areas (includes self-contained Frontend Auth: Login, Registration, Double-Opt-In Email verification, Password Reset).

### E. Theme Engine & The 4 Agency Default Themes
- **Theme Structure:** Located in `/themes/{theme-slug}/` with a `theme.json` manifest (colors, fonts, CPT blueprints, global Tailwind tokens).
- **Live Server Low-Code / AI-Assisted Editor:** Visual block/section inspector allowing admins to design and customize the website directly on the live server, tweak layouts, or prompt an LLM API to modify block JSON/Tailwind classes—compiling the result into a static production CSS bundle on save.
- **4 Default Themes (Zero External Dependencies):**
  1. **One-Pager Theme:** Smooth scrolling, Hero CTA, interactive feature sections, contact form with Honeypot + CSRF protection.
  2. **Clean Corporate Theme:** Multi-page layout, CPT-powered Team profiles, service grids, case studies, multi-level accessible navigation.
  3. **Classic Real Estate Theme:** CPT-powered Property listings, Alpine.js filterable grid (location, price, rooms, status), gallery lightbox modal, property-specific inquiry form.
  4. **Startup Theme:** Bold modern design, dark/light mode toggle, interactive pricing tables (monthly/yearly toggle), micro-interactions.

### F. Native DSGVO No-Consent Analytics
- **100% Cookieless & Local:** Zero cookies, zero `localStorage` identifiers, zero external requests. Fully DSGVO & TDDDG compliant without a consent banner.
- **Privacy Mechanism:** Daily rotating cryptographic salt combined with truncated IP (`/24` IPv4, `/48` IPv6) + User-Agent to generate a non-reversible daily visitor hash. Aggregated into lightweight daily summary tables.

### G. Native Custom Code Manager, SEO & GDPR Core
- **Custom Code Manager:** GUI to inject snippets into `<head>`, opening `<body>`, or closing `</body>`. Includes a **"Safe Mode" recovery token** to bypass custom scripts if a faulty snippet breaks the UI.
- **Built-in SEO Engine:** Meta Title/Description, OpenGraph, Canonical URLs, dynamic XML Sitemap, automated JSON-LD Schema Generator, and an **AI Assistant interface** (Gemini / OpenAI / Anthropic compatible via cURL) for generating meta tags and copy.
- **GDPR & Privacy Core:** 100% zero third-party browser requests out of the box. Includes a **Google Fonts Local Downloader** (fetches `.woff2` files and generates local `@font-face` CSS directly onto the host server) and 1-click user data export/erasure tools.

### H. Transactional Mail Engine (Resend + SMTP), 1-Click Updater, Backup & Stripe
- **Dual-Driver Mail Engine (Resend API + Native SMTP):**
  - Central Mailer Service supporting two configurable drivers in the Admin UI:
    1. **Resend REST API (Primary/Recommended):** Lightweight cURL integration sending via `https://api.resend.com/emails` using the client's verified custom domain (e.g., `info@client-domain.de`), configurable Sender Name, and `Reply-To` headers.
    2. **Native SMTP Driver (Fallback):** Standard SMTP authentication (Host, Port, TLS/SSL, User, Password) for clients using standard Webgo/IONOS mailboxes without API setup.
  - Includes a **"Send Test Email"** diagnostic tool with clear error logging in the Admin UI.
- **1-Click Over-The-Air Core Updater (GitHub Release Integration):**
  - Checks a configurable GitHub repository (supports Private Repo tokens) or custom JSON update endpoint for new Heartbeat CMS releases.
  - **Safe Update Pipeline:** 1. Automatically triggers a quick backup of `/core` and DB -> 2. Downloads the new release ZIP via cURL -> 3. Replaces `/core` and system assets while strictly preserving `/config`, `/uploads`, `/storage`, and custom `/themes` -> 4. Runs any pending SQL schema migrations -> 5. Clears all caches.
- **Zero-Exec Backups:** 1-click and cron-triggered backups using native PHP `PDO` table streaming (for SQL dumps) and `ZipArchive` (for `/storage`, `/uploads`, `/config`) with automated local retention cleanup.
- **Stripe Integration:** Lightweight native webhook & Checkout Session handler (via cURL/Stripe REST API) to unlock `Member` role access or gated content.

---

## 4. IMMEDIATE EXECUTION TASK (START PHASE 1 NOW)

Do not ask preliminary questions. Begin **Phase 1 (Core Foundation, Local Dev Runner & 4-Step Web Installer)** immediately:
1. Save these complete instructions to `/PROMPT.md` in the project root so future sessions can reference them.
2. Initialize the `/docs` directory (`ARCHITECTURE.md`, `FEATURES_AND_MANUAL.md`, `DEVELOPER_AND_THEMING.md`, `LOCAL_SETUP.md`, `MANAGED_HOSTING_GUIDE.md`, and `CHANGELOG.md`) with the Master Blueprint and Phase 1 documentation.
3. Scaffold the complete Phase 1 codebase (`composer.json`, `package.json`, `router.php`, root and public `.htaccess`, Core Bootstrap, PSR-4 Autoloader, Request/Response Router, PDO Database & Migration Engine with MySQL + SQLite support, and the complete 4-Step GUI Web Installer under `/install` styled in the Heartbeat dark slate/warm accent aesthetic).
4. Run syntax/lint checks to verify that the local dev server and Web Installer run out-of-the-box, then output the mandatory **🚀 PHASE HANDOVER & TOKEN-GUARD** box for Phase 2.