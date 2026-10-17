# Heartbeat CMS - Features & Manual

## 1. Feature Matrix

| Feature | Status | Description |
| :--- | :--- | :--- |
| **GUI Web Installer** | Phase 1 (Completed) | 4-step wizard verifying requirements, database connectivity, site setup, and auto-migrations. |
| **Dual-Mode Routing** | Phase 1 (Completed) | Works via local built-in server (`router.php`) and shared Apache/Nginx hosting (`.htaccess`). |
| **Zero-Dependency PSR-4** | Phase 1 (Completed) | Autoloads core classes even if Composer is not installed on the destination server. |
| **Dual Database Support** | Phase 1 (Completed) | Native PDO wrapper supporting MySQL 8+/MariaDB 10.5+ and SQLite 3 for zero-config dev. |
| **CSRF & Security Core** | Phase 1/2 (Completed) | 256-bit token verification, strict security headers, context-aware output escaping. |
| **RBAC & Auth Shell** | Phase 2 (Completed) | 5 roles (Super-Admin, Admin, Editor, User, Member) with granular permissions & hierarchy. |
| **Agency White-Label & Client-Guard** | Phase 2 (Completed) | Rebrand backend with agency name/support; lock destructive tools from clients. |
| **Brute-Force Rate Limiter** | Phase 2 (Completed) | 5 attempts / 15-minute window on auth endpoints with decay countdown. |
| **Dark-Mode Admin Shell** | Phase 2 (Completed) | Modern dashboard, responsive sidebar, user dropdown, Alpine.js toasts. |
| **Flexible Content Engine (CPT)** | Phase 3 (Completed) | Built-in & dynamic CPTs (Immobilien, Team, Referenzen) with indexed JSON custom fields. |
| **Structured JSON Block Builder** | Phase 3 (Completed) | Visual drag/reorder canvas, 13 core blocks, live preview, raw JSON mode, instant HTML pre-render. |
| **Mediathek & WebP Pipeline** | Phase 3 (Completed) | Drag & drop uploads, strict MIME checks, native WebP/AVIF conversions, responsive srcset. |
| **Popup & Paywall Condition Engine** | Phase 3 (Completed) | Triggers on time delay, scroll %, exit intent, or Member-role paywalls via Alpine.js. |
| **Live Theme Engine & Studio Customizer** | Phase 4 (Completed) | Theme discovery, active switcher, live studio customizer with split-screen preview. |
| **Shared-Hosting Tailwind JIT Compiler** | Phase 4 (Completed) | Scans database blocks & theme tokens, generates minified static CSS bundle to `/storage/cache/css/`. |
| **Multi-Tier Caching Engine** | Phase 4 (Completed) | Static Full-Page HTML Cache (<20ms TTFB) with auto-invalidation & APCu/File Object Cache. |
| **Safe Custom Code Manager & Safe-Mode** | Phase 4 (Completed) | Head/Body snippet injection with emergency bypass token (`?hb_safe_mode=1` & `?safe_mode={token}`). |
| **Transactional Mailer** | Phase 5 | Resend REST API (primary) with native SMTP fallback and live diagnostics. |
| **DSGVO No-Consent Analytics** | Phase 5 | 100% cookieless, non-reversible daily visitor hashes. |
| **1-Click Over-The-Air Updater** | Phase 5 | Updates `/core` safely while strictly preserving `/config`, `/uploads`, and themes. |

---

## 2. 4-Step Web Installer Guide (`/install`)

The installer is automatically triggered if `config/installed.lock` is missing.

### Step 1: System- & Serverprüfung
- Checks PHP Version (`>= 8.2.0`, recommends `8.3.0+`).
- Validates required extensions: `pdo`, `pdo_mysql` / `pdo_sqlite`, `curl`, `gd` / `imagick`, `zip`, `mbstring`, `json`.
- Audits write permissions for: `config/`, `storage/cache/`, `storage/logs/`, `storage/backups/`, `uploads/`.

### Step 2: Datenbank & Umgebung
- **Driver Selection:** Choose between `MySQL / MariaDB` (Production) and `SQLite` (Local Dev).
- **1-Klick SQLite:** Automatically configures `storage/database.sqlite` with WAL mode and foreign keys enabled.
- **Live Verbindungstest:** Sends an AJAX fetch to `/install/api/test-db` without page reload.
- **Environment:** Select `development` (Debug ON) or `production` (Debug OFF).

### Step 3: Website, Agentur & Administrator
- **Site Title & URL:** Auto-detects protocol and hostname.
- **Theme Selection:** Choose initial starter theme (`one-pager`, `corporate`, `real-estate`, `startup`).
- **Agency Mode:** Configure custom Agency Name, Support Email, and activate **Client-Guard**.
- **Super-Admin:** Initial administrator credentials (hashed via `password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12])`).

### Step 4: Installation & Abschluss
- Executes initial schema migrations (`hb_options`, `hb_users`, `hb_posts`, `hb_meta_data`, `hb_terms`, `hb_term_relationships`).
- Seeds starter content with structured JSON block tree.
- Generates `config/env.php` and locks the installer via `config/installed.lock`.
- Direct redirect to Admin Login (`/admin/login`).

---

## 3. Security Core & Authentication (`/admin/login`)

### Login & Session Security
- **URL:** `/admin/login`
- **Brute-Force Rate Limiting:** Enforces a maximum of 5 failed login attempts per 15-minute window per IP. Exceeding the threshold temporarily locks the endpoint and displays a countdown to the user.
- **Session Fixation Defense:** Calls `session_regenerate_id(true)` upon authentication to prevent session hijacking.
- **Subnet & User-Agent Fingerprinting:** Sessions are tied to the client's User-Agent and IP subnet (`/24` IPv4, `/48` IPv6).
- **Remember-Me Tokens:** Secure 256-bit cryptographic token stored in `{prefix}meta_data` and transmitted via an `HttpOnly`, `SameSite=Lax` cookie.

---

## 4. User Roles & RBAC Matrix

Heartbeat CMS provides 5 clearly defined roles:

| Role | Label | Target User | Key Permissions |
| :--- | :--- | :--- | :--- |
| `super_admin` | **Super-Administrator (Agentur)** | Digitalagentur / Lead Dev | Vollzugriff auf alle System-, Code-, Update- und White-Label-Einstellungen (`*`). |
| `admin` | **Administrator (Kunde)** | Kunde / Hauptverantwortlicher | Verwaltung von Inhalten, Benutzern, Themes und Einstellungen (vorbehaltlich Client-Guard). |
| `editor` | **Redakteur** | Content Manager | Erstellung und Bearbeitung aller Seiten, Beiträge, Custom Post Types und Medien. |
| `user` | **Autor / Standard-Benutzer** | Freier Mitarbeiter | Verfassen und Bearbeiten eigener Artikel und Hochladen von Medien. |
| `member` | **Mitglied (Frontend)** | Endkunde / Abonnent | Zugriff auf geschützte Mitgliederbereiche im Frontend. **Kein Zugriff auf das Backend.** |

### Role Hierarchy & Security Safeguards
- **Hierarchy:** Super-Admins can manage all users. Client-Admins can only manage Editors, Authors, and Members.
- **No Self-Deletion:** Administrators cannot delete their own account.
- **Last Super-Admin Protection:** The system prevents deleting the last remaining Super-Administrator.
- **Escalation Protection:** Non-super-admins cannot assign or elevate any user to the `super_admin` role.

---

## 5. Agency Mode & Client-Guard

### Agency White-Labeling
The backend can be customized with your digital agency's branding:
- **Agency Name:** Displayed in the sidebar header and footer (e.g. `Herz & Seele Digital`).
- **Support Email:** Integrated into the dashboard support card for 1-click customer inquiries.
- **Config Flags:** Can be defined in `config/env.php` or managed by Super-Admins in `/admin/settings`.

### Client-Guard Isolation
When `client_guard` is active:
- Standard Client Administrators (`admin`) cannot access or modify:
  1. **Custom Code Injection** (Head / Body scripts)
  2. **1-Click Core Updater** (OTA GitHub updates)
  3. **Raw Database Backups** (SQL export / restoration)
  4. **Agency White-Label Configuration**
- Prevents clients from accidentally breaking their production website while granting full autonomy over daily content, pages, themes, and users.

---

## 6. Admin Shell & UI Navigation

The Admin Shell features a dark modern aesthetic inspired by `herzundseele-digital.de`:
- **Sidebar Navigation:**
  - `Dashboard` (`/admin`): Key metrics, Agency Support card, recent users, quick actions.
  - `Seiten & Inhalte` (`/admin/pages`): Page & block builder (Phase 3).
  - `Beiträge (CPT)` (`/admin/posts`): Blog posts & custom post types (Phase 3).
  - `Mediathek` (`/admin/media`): Media library with WebP/AVIF conversions (Phase 3).
  - `Design & Themes` (`/admin/themes`): Theme manager & live customizer (Phase 4).
  - `Benutzer & Rollen` (`/admin/users`): Complete user management with search & role filter.
  - `Einstellungen` (`/admin/settings`): General site, agency white-label, and security settings.
  - `System & Wartung` (`/admin/system`): Server runtime health, DB driver, and 1-click cache purge.
- **Topbar:**
  - Dynamic breadcrumbs navigation.
  - One-click "Website ansehen" link opening in a new tab.
  - User profile dropdown with role badge, profile link, and secure CSRF logout.
- **Interactive Toasts:**
  - Floating, auto-dismissing notifications powered by Alpine.js.
  - Visual distinction for `success`, `error`, `warning`, and `info` messages.

---

## 7. Configuration Reference (`config/env.php`)

```php
return [
    'app' => [
        'name'      => 'Heartbeat CMS',
        'key'       => 'your-32-byte-hex-key',
        'env'       => 'production', // 'development' or 'production'
        'debug'     => false,
        'url'       => 'https://client-domain.de',
        'timezone'  => 'Europe/Berlin',
    ],
    'database' => [
        'driver'    => 'mysql', // or 'sqlite'
        'host'      => '127.0.0.1',
        'port'      => 3306,
        'database'  => 'db_name',
        'username'  => 'db_user',
        'password'  => 'db_password',
        'prefix'    => 'hb_',
    ],
    'agency' => [
        'white_label'   => true,
        'name'          => 'Herz & Seele Digital',
        'support_email' => 'hallo@herzundseele-digital.de',
        'client_guard'  => true, // Locks code-injection, backups, core updater for standard Admins
    ],
];
```

---

## 8. Content Engine, CPT & JSON Block Builder (`/admin/pages`, `/admin/posts`)

### Custom Post Types (CPT)
- **Built-in Types:**
  - `page`: Inhaltsseiten und Landingpages (Slug: `/:slug`).
  - `post`: Blogartikel und Unternehmens-Neuigkeiten (Slug: `/blog/:slug`).
  - `property`: Immobilienangebote mit Kaufpreis, Zimmern, Wohnfläche, Standort, Objekttyp, Status und Energieklasse (Slug: `/immobilien/:slug`).
  - `team_member`: Mitarbeiterprofile mit Position, Abteilung, E-Mail, Telefon und LinkedIn (Slug: `/team/:slug`).
  - `case_study`: Kundenreferenzen mit Auftraggeber, Jahr, Leistungen und Live-URL (Slug: `/referenzen/:slug`).
- **Dynamic Blueprints:** Drop any `{type}.json` into `config/cpt/` or `themes/{theme}/cpt/` to automatically register new content types and UI fields without modifying database tables.

### Visual JSON Block Builder Canvas
- **Inserter:** Categorized drawer for 13 core blocks (Hero, Heading, Paragraph, Image, Button, Columns/Grid, Card, Features, FAQ, Quote, Alert, Spacer, Popup).
- **Controls:** Move up/down, duplicate, delete, and visual attribute inputs per block.
- **Code View:** 1-click toggle to inspect and edit the clean raw JSON block tree directly (ideal for LLM/AI editing).
- **Auto Pre-rendering:** Saves both `content_blocks` (structured JSON) and compiled `content_html` into `{prefix}posts` on save for instant frontend rendering.

---

## 9. Mediathek & Asset Pipeline (`/admin/media`)

### Features & Security
- **Strict MIME Check:** Validates file signatures via `finfo_file` against an allowed whitelist (`jpg`, `jpeg`, `png`, `webp`, `avif`, `gif`, `svg`, `pdf`). Rejects double extensions (e.g. `.php.jpg`).
- **Script Guard:** Auto-generates an `.htaccess` inside `/uploads` disabling PHP execution engine (`php_flag engine off` / `Deny from all`).
- **Native WebP Conversion:** Converts raster images (JPEG, PNG) directly to `.webp` (quality 85) on upload using native GD/Imagick.
- **Responsive Sizes:** Automatically generates `thumbnail` (150x150 crop), `medium` (600px width), and `large` (1200px width) variants alongside WebP versions.
- **Responsive Srcset Helper:** `MediaService::getSrcset($attachment)` and `MediaService::renderPictureTag($attachment)` for optimal Core Web Vitals.
- **Modal Media Picker:** Embedded directly into the Block Editor for 1-click image selection or live dropzone uploads.

---

## 10. Live Theme Engine & Studio Customizer (`/admin/themes`, `/admin/themes/customize`)

### Overview & Capabilities
- **Theme Library (`/admin/themes`):** Grid of discovered themes with active status indicator, author, version, color palette dots, and 1-click activation.
- **Live Studio Customizer (`/admin/themes/customize`):**
  - **Left Studio Drawer:** Live color picker for design tokens (`primary`, `primary_hover`, `background`, `card`, `border`, `text`, `accent`), typography selectors (`font_family`, `heading_font`), container width (`max-w-5xl`, `max-w-6xl`, `max-w-7xl`), and border radius.
  - **1-Click Presets:** Instant design switching ("Herz & Seele Dark", "Emerald Agency", "Royal Purple", "Clean Light", "Ocean Blue").
  - **Live Preview Iframe:** Real-time preview with viewport switches (Desktop 100%, Tablet 768px, Mobile 375px) updating CSS variables without page reloads.
  - **Custom CSS Area:** Write custom CSS rules injected into the theme bundle.
- **Shared-Hosting JIT CSS Compiler (`CssCompiler`):**
  - Automatically scans database JSON block trees and template HTML.
  - Compiles used utility classes, responsive media queries (`sm:`, `md:`, `lg:`), hover/focus variants, and custom styles into a clean, static, minified bundle saved at `/storage/cache/css/theme_{slug}.css`.
  - Zero Node.js or exec() dependency on the server.
  - Delivered via `/assets/css/theme.css` with 304 ETag and public caching headers.

---

## 11. Multi-Tier Caching Engine (`PageCache` & `ObjectCache`)

### Tier 1: Static Full-Page HTML Cache (`PageCache`)
- **Storage:** `/storage/cache/html/{hash}.html`.
- **Target Performance:** Delivers pure static HTML to anonymous visitors for **< 20ms TTFB**.
- **Headers:** Emits `X-Cache: HIT` (or `X-Cache: MISS`) and `X-Heartbeat-Cache: Static Full-Page`.
- **Automatic Invalidation:** Completely invalidates static pages when:
  - Any page, post, or CPT is created, updated, trashed, or restored (`PostService`).
  - Active theme is switched or customized (`ThemeService`).
  - Custom code snippets are updated (`CustomCodeService`).
  - Admin clicks "Cache jetzt leeren" in `/admin/system`.
- **Bypass Rules:** Authenticated users (`Auth::check()`), POST requests, admin routes, and parameters `?nocache=1` or `?preview=1` immediately bypass the cache.

### Tier 2: Object & Query Cache (`ObjectCache`)
- **Drivers:** Automatic APCu detection (`apcu_fetch`) with atomic serialized file-based fallback in `/storage/cache/objects/`.
- **API:** `Cache::get()`, `Cache::set()`, `Cache::has()`, `Cache::remember($key, $ttl, $fn)`, `Cache::forget()`, `Cache::flush()`.

---

## 12. Custom Code Manager & Disaster Recovery Safe-Mode (`/admin/custom-code`)

### Injection Targets
- **`<head>` Snippets:** Injected immediately before `</head>` (for meta tags, canonical links, font links, header scripts).
- **`<body>` Start Snippets:** Injected immediately after `<body...>` (for Google Tag Manager `<noscript>` or accessibility skip-links).
- **`<body>` End Snippets:** Injected immediately before `</body>` (for analytics scripts, tracking pixels, chat widgets).

### Disaster Recovery: Safe-Mode Emergency Bypass
- In case a faulty JavaScript snippet causes an infinite loop, white screen, or blocks the admin or frontend, administrators can instantly bypass all custom scripts.
- **Trigger Methods:**
  1. Generic URL parameter: `https://your-domain.de/?hb_safe_mode=1`
  2. Cryptographic Secret URL: `https://your-domain.de/?safe_mode={safe_mode_token}`
- **1-Click Copy in Admin UI:** The Custom Code Manager displays the pre-generated emergency URL with a 1-click clipboard copy button.
- **Client-Guard Protection:** Only `super_admin` can access the Custom Code Manager; client `admin` accounts are locked out if Client-Guard is active.

---

## 13. DSGVO Cookieless Analytics (`/admin/analytics`)

### Overview & Compliance Guarantee
- **100% Cookieless:** Zero cookies written, zero `localStorage` tokens used, zero external analytics beacons.
- **TDDDG & DSGVO Compliant:** Does not require a cookie banner or user consent.
- **Privacy Architecture:**
  - IPv4 addresses masked to `/24` (e.g. `192.168.1.0`).
  - IPv6 addresses masked to `/48` (e.g. `2001:db8:85a3::`).
  - Visitor Hash calculated daily via `sha256(MaskedIP + UserAgent + DailyRotatingSalt)`. Non-reversible across days, eliminating persistent profiling.
- **Dashboard Metrics:**
  - KPI Cards: Total Views, Unique Daily Visitors, Today's Views, Today's Uniques.
  - Interactive Trend Chart (pure CSS/HTML bar chart, zero external charting libraries).
  - Top 10 Pages Table with view & visitor counts.
  - Top Referrers Table with cleaned domain attribution.
  - Device Breakdown (Desktop %, Mobile %, Tablet %) with visual progress bars.
  - Quick Period Filters (7 Tage, 30 Tage, 90 Tage).

---

## 14. Marketing, SEO & Universal AI Assistant (`/admin/seo`)

### SEO Engine
- **Automated Meta Tags:** Title templates (`%title% - %sitename%`), meta descriptions, robots indexing directives (`max-image-preview:large`).
- **OpenGraph & Twitter Cards:** Generates `og:title`, `og:description`, `og:image`, `og:url`, `og:site_name`, `twitter:card` (summary_large_image).
- **Dynamic XML Sitemap:** Automatically available at `/sitemap.xml`, listing all published pages, posts, and CPTs with `<lastmod>` and dynamic `<priority>` values.
- **JSON-LD Structured Data Schema:** Emits Schema.org `WebSite`, `WebPage`, and `BlogPosting` schemas directly in `<head>`.

### Universal AI Assistant
- **Provider Agnostic:** Native cURL support for **Google Gemini** (`gemini-1.5-flash`), **OpenAI** (`gpt-4o-mini`), and **Anthropic Claude** (`claude-3-haiku`).
- **SEO Meta Generator:** Generates punchy title tags, 155-character meta descriptions, and keyword suggestions.
- **Copy Generator:** Generates web copy and section content for the Block Editor.
- **Interactive API Endpoint:** `POST /admin/api/ai/generate` for real-time suggestions.

---

## 15. Transactional Mailer (`/admin/mail`)

### Dual-Driver Architecture
1. **Resend REST API (Primary/Recommended):**
   - Lightweight cURL integration communicating directly with `https://api.resend.com/emails`.
   - Sends from client's verified domain (e.g. `info@client-domain.de`).
   - Configurable Sender Name and `Reply-To`.
2. **Native SMTP Driver (Fallback):**
   - Pure-PHP socket implementation using `stream_socket_client` without external libraries.
   - Supports `STARTTLS` (Port 587), `SSL/TLS` (Port 465), and unencrypted (Port 25).
   - Works with all standard German hosters (Webgo, IONOS, HostEurope, Mittwald).
3. **Log Driver (Local Dev):**
   - Automatically writes outgoing emails to `/storage/logs/mail.log` during local development.

### Live Diagnostic Tool
- **1-Click Test Email:** Send a diagnostic test email to any address directly from `/admin/mail` with real-time socket and API error reporting.

---

## 16. Zero-Exec Backups (`/admin/backups`)

### Zero-Exec Database & File Archiving
- **Pure-PHP PDO Streaming:** Dumps full database schema and row data in chunks via PDO without requiring `exec()`, `shell_exec()`, or `mysqldump`.
- **ZipArchive Integration:** Packages database `.sql` and system files (`/config`, `/uploads`, `/themes`) into a timestamped `.zip` archive.
- **Retention Pruning:** Automatically retains the top 5 backups and prunes older archives to prevent disk exhaustion.
- **Automated Cron URL:** Periodically trigger backups via hoster cronjobs using a secure token:
  `GET /api/backup/cron?token={secret_token}`
- **Client-Guard Protection:** Only `super_admin` can create or download backups.

---

## 17. 1-Click Over-The-Air (OTA) Core Self-Updater (`/admin/updater`)

### Update Workflow
1. **Pre-Update Safety Backup:** Automatically generates an immediate database snapshot.
2. **Download Release:** Downloads release package via cURL from configured GitHub repository or private token.
3. **Selective Extraction:** Updates `/core` and `/public/assets/` while **strictly preserving** `/config`, `/uploads`, `/storage`, and custom `/themes`.
4. **Auto-Migration:** Discovers and applies any pending SQL migrations (`MigrationRunner`).
5. **Cache Purge:** Automatically clears static full-page and object caches.
- **Client-Guard Protection:** Locked against client administrators.

---

## 18. GDPR Tools & Local Google Fonts Downloader (`/admin/gdpr`)

### Google Fonts Downloader
- **100% German Court & DSGVO Compliant:** Eliminates third-party browser requests to Google servers.
- **Workflow:** Input any Google Fonts CSS URL or font family name (e.g. `Plus Jakarta Sans`, `Inter`). The engine fetches modern `.woff2` files, downloads them to `/public/assets/fonts/`, and generates a standalone local `@font-face` stylesheet.
- **Embed Snippet:** Displays ready-to-use HTML `<link rel="stylesheet" href="/assets/fonts/{slug}.css">`.

### GDPR Betroffenenrechte (Art. 15 & Art. 17)
- **Art. 15 / 20 Data Export:** Exports all stored personal data (profile, metadata, authored content) into a formatted JSON archive.
- **Art. 17 Right to Erasure / Anonymization:** Scrubs username, email, and password to anonymized identifiers and deletes personal meta while retaining referential integrity of authored articles.

---

## 19. Stripe Integration & Paywall Memberships (`/admin/payments`)

### Checkout & Webhook Engine
- **Direct Stripe REST API:** Creates Stripe Checkout sessions for one-time payments or memberships via cURL.
- **Native HMAC-SHA256 Webhook Verification:** Verifies `Stripe-Signature` timestamp and payload signature in pure PHP without the Stripe PHP SDK.
- **Automatic Member Unlocking:** On `checkout.session.completed`, logs order in `{prefix}orders` and automatically unlocks the customer's account with the `member` role.
- **Alpine.js Paywall Integration:** Protects paywalled blocks for visitors not possessing the `member` role.

---

## 20. The 4 Default Themes & Interactive Features

Heartbeat CMS ships with four distinct, fully localized production themes out of the box:

### 1. One-Pager Starter Theme (`one-pager`)
- **Ideal For:** Landing pages, freelancers, consultants, product launches, localized service businesses.
- **Interactive Features:**
  - Smooth anchor scrolling (`html { scroll-behavior: smooth; }`).
  - Hero banner with primary CTA and secondary demo trigger.
  - Interactive feature and service grids with responsive Tailwind classes.
  - Client testimonial slider and Alpine.js accordion FAQ component.
  - Universal Contact Form with invisible Honeypot anti-spam trap (`_hb_hp`) and CSRF protection.

### 2. Clean Corporate Theme (`corporate`)
- **Ideal For:** Multi-page consulting firms, corporate agencies, law firms, enterprise providers.
- **Interactive Features:**
  - Sticky header with accessible multi-level dropdown menus (`aria-haspopup="true"`, `aria-expanded`, `role="menu"`).
  - CPT-powered Team Profiles (`team_member`): Filterable by department, displaying job title, bio, and social media links.
  - CPT-powered Case Studies (`case_study`): Displays client case stories, problem/solution breakdowns, and key performance metric badges.

### 3. Classic Real Estate Theme (`real-estate`)
- **Ideal For:** Real estate agencies, brokers, property management firms, holiday home rentals.
- **Interactive Features:**
  - Alpine.js Client-Side Filterable Grid: Filter listings dynamically in real time by location, property type (Villa, Penthouse, etc.), price ceiling slider, and bedroom count.
  - Gallery Lightbox Modal: Fullscreen high-resolution property image modal with keyboard ESC dismissal and navigation arrows.
  - Property Inquiry Modal: 1-click inquiry form automatically linking `property_id` and `property_title` into the submission to agency advisors.

### 4. Modern Startup Theme (`startup`)
- **Ideal For:** SaaS products, mobile apps, tech startups, developer tools.
- **Interactive Features:**
  - Instant Dark/Light Mode: Persistent toggle using local storage with zero screen flicker.
  - Interactive Pricing Tables: Switch between monthly and yearly billing with automatic "Save 20%" discount badge recalculation.
  - Direct Stripe Checkout links (`/api/stripe/checkout?plan=pro`) ready for immediate monetization.

---

## 21. Production Release Packaging & Shared Hosting Deployment

### Generating Release Packages
Deploying Heartbeat CMS is identical to installing WordPress on classic cPanel, Plesk, or German managed hosting (Webgo, IONOS, HostEurope, Mittwald).

Build the production release package using either tool:
```bash
# Option A: Pure-PHP Zero-Exec Packager (No Node.js needed)
php scripts/build-release.php

# Option B: Node.js Packager
node scripts/build-release.js
```

### 1-Click Deployment Instructions
1. Download or locate `dist/heartbeat-cms-install.zip` (~0.29 MB).
2. Upload the ZIP to your web root (`public_html` or `htdocs`) via FTP or your hoster's Web-FTP/File Manager.
3. Extract all files directly into the web root.
4. Navigate to your domain in any browser:
   `https://yourdomain.com/`
5. The 5-Step Web-Installer launches automatically, verifies server compatibility, configures database credentials, selects the starter theme, and provisions the initial super-admin account in under 30 seconds.



