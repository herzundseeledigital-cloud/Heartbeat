# Heartbeat CMS - Architecture Blueprint

## 1. System Overview & Philosophy
Heartbeat CMS is a modern, lightweight, ultra-secure, and lightning-fast Hybrid/Headless Content Management System tailored for digital agencies. It eliminates the overhead and vulnerabilities of legacy CMS platforms while providing a frictionless WordPress-style deployment, a hardened RBAC security core, and a live-server Low-Code/AI Theme Editor.

| Metric | Target Standard | Implementation Strategy |
| :--- | :--- | :--- |
| **Backend Runtime** | PHP 8.2+ / 8.3+ | `declare(strict_types=1);`, PSR-12, Zero-leak error handling |
| **Database** | MySQL 8.0+ / MariaDB 10.5+ / SQLite 3 | PDO abstraction, automatic prefixing (`hb_`), prepared queries |
| **Frontend/Admin** | Tailwind CSS + Alpine.js | Modern dark slate/warm coral design, zero SPA build on server |
| **Auth & RBAC** | 5-Role Model + Client-Guard | Granular permissions, session fixation defense, brute-force guard |
| **Hosting Constraints** | Managed Hosting (IONOS, Webgo, Mittwald) | No root access required, no SSH required, zero `exec()` dependency |
| **Response Time** | < 20ms TTFB | Full-page static HTML caching with instant invalidation |

---

## 2. Directory Structure

```
Heartbeat/
├── .htaccess                   # Root router & security layer for shared hosting
├── composer.json               # PSR-4 definition: Heartbeat\ -> core/
├── package.json                # Local dev scripts (dev, build:release)
├── router.php                  # Built-in PHP server router for local dev
├── bin/php                     # Local environment CLI wrapper
├── config/
│   ├── .gitignore              # Ignores env.php & installed.lock
│   ├── env.example.php         # Production config template
│   ├── env.php                 # Generated runtime environment configuration
│   └── installed.lock          # Cryptographic installation lock
├── core/                       # Core CMS Engine (Read-only during updates)
│   ├── Autoloader.php          # Zero-dependency PSR-4 autoloader
│   ├── Bootstrap.php           # Error handling, session & environment setup
│   ├── App.php                 # Application kernel & route dispatcher
│   ├── Http/
│   │   ├── Request.php         # HTTP request encapsulation & input sanitization
│   │   ├── Response.php        # HTTP response factory (HTML, JSON, Redirect)
│   │   └── Router.php          # High-performance route matching & dispatch
│   ├── Database/
│   │   ├── Database.php        # PDO wrapper (MySQL & SQLite), transactions
│   │   ├── MigrationRunner.php # Automatic schema versioning & migration runner
│   │   └── Schema/
│   │       └── 001_initial_schema.php # Initial relational & JSON schema
│   ├── Security/
│   │   ├── Csrf.php            # 256-bit CSRF token generation & validation
│   │   ├── Security.php        # Context-aware escaping, password hashing & CSP headers
│   │   ├── Session.php         # Fixation defense, subnet fingerprinting & flash messages
│   │   ├── RateLimiter.php     # File-backed brute force rate limiter (attempts & decay)
│   │   └── Rbac.php            # 5-role permission matrix & Client-Guard isolation
│   ├── Auth/
│   │   └── Auth.php            # Authentication manager, remember-me & user state
│   ├── Agency/
│   │   └── AgencyService.php   # White-label branding & Client-Guard service
│   ├── Content/
│   │   ├── MetaService.php     # Custom meta data & JSON field engine
│   │   ├── PostType.php        # CPT blueprint data model & supports
│   │   ├── PostTypeRegistry.php# Central CPT registry & auto-discovery
│   │   └── PostService.php     # High-level post/page/CPT CRUD & query repository
│   ├── Blocks/
│   │   ├── BlockRegistry.php   # Registry of 13 core blocks & attributes
│   │   └── BlockRenderer.php   # Block tree HTML compiler & Alpine condition engine
│   ├── Media/
│   │   └── MediaService.php    # Native GD/Imagick WebP/AVIF conversions & srcset helper
│   ├── Themes/
│   │   ├── Theme.php           # Theme blueprint, manifest parsing & CSS variable generator
│   │   ├── ThemeService.php    # Theme discovery, activation, token update & template loader
│   │   └── CssCompiler.php     # Shared-hosting Tailwind JIT utility extractor & CSS compiler
│   ├── Cache/
│   │   ├── PageCache.php       # Full-page static HTML caching with instant invalidation
│   │   ├── ObjectCache.php     # APCu & atomic file-based object/query cache
│   │   └── Cache.php           # Unified static facade for page & object caching
│   ├── CustomCode/
│   │   └── CustomCodeService.php # Head & Body injection engine with Safe-Mode bypass
│   ├── Mail/
│   │   ├── MailDriverInterface.php # Contract for transactional mail drivers
│   │   ├── ResendDriver.php    # Resend REST API driver via lightweight cURL
│   │   ├── SmtpDriver.php      # Pure-PHP socket SMTP driver (EHLO, STARTTLS, AUTH)
│   │   ├── LogDriver.php       # Local dev mail logger
│   │   └── Mailer.php          # Central transactional mail facade & diagnostics
│   ├── Backup/
│   │   └── BackupService.php   # Zero-exec PDO table streaming & ZipArchive backups
│   ├── Updater/
│   │   └── UpdaterService.php  # 1-click OTA self-updater with safety backup & auto-migration
│   ├── Analytics/
│   │   └── AnalyticsService.php # Cookieless DSGVO tracking (subnet masking & daily salt)
│   ├── Seo/
│   │   └── SeoService.php      # OpenGraph, Twitter Cards, JSON-LD Schema & XML Sitemap
│   ├── Ai/
│   │   └── AiService.php       # Universal AI adapter (Gemini, OpenAI, Anthropic via cURL)
│   ├── Gdpr/
│   │   ├── GoogleFontsDownloader.php # Local WOFF2 font downloader & @font-face generator
│   │   └── GdprService.php     # User data export (Art. 15) & erasure/anonymization (Art. 17)
│   ├── Payments/
│   │   └── StripeService.php   # Stripe Checkout & native HMAC-SHA256 webhook engine
│   ├── Admin/
│   │   ├── AdminController.php # Login, Dashboard, User CRUD & Settings controller
│   │   ├── AdminView.php       # Dark-mode Admin UI shell, toasts, sidebar & topbar
│   │   ├── ContentController.php # Pages, Posts, CPT CRUD & Visual Block Builder
│   │   ├── MediaController.php # Mediathek UI, drag & drop uploads & picker API
│   │   ├── ThemeController.php # Theme library, Live Studio Customizer & JIT CSS API
│   │   ├── CustomCodeController.php # Head & Body code manager with Safe-Mode URL recovery
│   │   └── AgencyModulesController.php # Analytics, SEO, Mail, Backups, Updater, GDPR & Stripe
│   └── Installer/
│       ├── SystemChecker.php   # System requirement & folder permission audit
│       ├── InstallerService.php # DB testing, env writing, migrations, seeding
│       └── InstallerController.php # 4-step interactive GUI Web Installer
├── docs/                       # Living architectural & developer documentation
├── public/                     # DocumentRoot entry point
│   ├── .htaccess               # Production URL rewrite & security rules
│   ├── index.php               # Public front controller
│   └── assets/
│       ├── css/installer.css   # Precompiled system & admin stylesheet
│       └── js/alpine.min.js    # Self-hosted micro-reactivity runtime
├── storage/                    # Persistent storage (Cache, Logs, Backups)
│   ├── cache/                  # Multi-tier caching
│   │   ├── html/               # Static full-page HTML files (<20ms TTFB)
│   │   ├── objects/            # Atomic serialized query & data objects
│   │   └── css/                # JIT-compiled theme CSS stylesheets
│   ├── logs/                   # System and exception log files
│   └── backups/                # Zero-exec database and file backups
├── uploads/                    # Media library uploads (WebP/AVIF conversions & .htaccess guard)
├── themes/                     # Theme directories with theme.json manifests
│   ├── default/                # One-Pager starter theme
│   ├── corporate/              # Clean corporate agency theme
│   ├── real-estate/            # Classic real estate listing theme
│   └── startup/                # Modern startup theme
└── plugins/                    # Modular agency plugins
```

---

## 3. Request Lifecycle

```
[Incoming HTTP Request]
       │
       ▼
[Root / Public .htaccess]  --> Static assets served directly (<1ms)
       │
       ▼
[public/index.php]
       │
       ▼
[core/Bootstrap.php]       --> Autoloader, Secure Session, Error Handler
       │
       ▼
[core/App.php]             --> Security Headers (CSP, Frame-Options, nosniff)
       │
       ├─ If NOT Installed ──► Route to /install (4-Step Web Installer)
       │
       ├─ If Installed
       │     ├─ /admin/login ────────► Rate Limiter -> Csrf Check -> Auth::attempt
       │     │
       │     ├─ /admin/pages & posts ► Session Validation -> Auth::check -> ContentController -> Visual Block Builder
       │     │
       │     ├─ /admin/media ────────► Session Validation -> Auth::check -> MediaController -> WebP Conversion
       │     │
       │     ├─ /admin/themes ───────► RBAC manage_themes -> ThemeController -> Studio Customizer / JIT CSS
       │     │
       │     ├─ /admin/custom-code ──► RBAC manage_code + Client-Guard -> CustomCodeController
       │     │
       │     ├─ /admin/* ────────────► RBAC Permission Check -> AdminController -> AdminView Shell
       │     │
       │     ├─ /assets/css/theme.css► Static compiled CSS bundle (<1ms) -> 304 Not Modified / Cache-Control
       │     │
       │     └─ Frontend Routes (/, /:slug, /{cpt}/:slug)
       │           │
       │           ├─ [PageCache Lookup] ──► HIT (<20ms) -> Serve static HTML with X-Cache: HIT
       │           │
       │           └─ MISS ────────────────► BlockRenderer (JSON -> HTML)
       │                                      -> Inject Theme CSS Variables & /assets/css/theme.css
       │                                      -> CustomCodeService::inject (with Safe-Mode check)
       │                                      -> PageCache::put
       │                                      -> Serve with X-Cache: MISS
       ▼
[HTTP Response Sent]
```

---

## 4. Security Model & RBAC Matrix

### Security by Design (OWASP Top 10)
1. **Direct File Protection:** Direct HTTP access to `/core`, `/config`, `/storage`, `/plugins` is blocked via `.htaccess` (or 403 in `router.php`).
2. **Session Fixation Defense:** Every authentication state transition calls `Session::regenerate(true)`. Subnet and user-agent fingerprinting prevents session hijacking.
3. **Brute-Force Rate Limiting:** Auth endpoints enforce a 5-attempt limit per 15-minute window (`login:ip:*`), providing decay countdowns and preventing credential stuffing.
4. **Context-Aware Escaping:** All output is escaped using `Security::e()` (`htmlspecialchars(..., ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')`).
5. **100% Prepared Statements:** All database queries utilize PDO prepared statements with parameter binding; raw concatenation is forbidden.
6. **256-Bit CSRF Protection:** All state-changing requests (POST, PUT, DELETE) require a cryptographically secure token validated via form input or `X-CSRF-TOKEN` header.
7. **Client-Guard Lock:** Destructive areas (Custom Code, Core Updater, Raw Backups, White-Label config) are inaccessible to standard client Admins.

### RBAC Permission Matrix

| Permission | Super-Admin (Agency) | Admin (Client) | Editor | User (Author) | Member (Frontend) |
| :--- | :---: | :---: | :---: | :---: | :---: |
| `access_admin` | ✅ | ✅ | ✅ | ✅ | ❌ |
| `manage_agency` | ✅ | ❌ | ❌ | ❌ | ❌ |
| `manage_code` (Custom Code) | ✅ | 🔒 *(Locked if Client-Guard)* | ❌ | ❌ | ❌ |
| `update_core` (OTA Updater) | ✅ | 🔒 *(Locked if Client-Guard)* | ❌ | ❌ | ❌ |
| `manage_backups` (Raw DB) | ✅ | 🔒 *(Locked if Client-Guard)* | ❌ | ❌ | ❌ |
| `manage_users` | ✅ | ✅ *(Cannot edit Super-Admin)* | ❌ | ❌ | ❌ |
| `manage_options` | ✅ | ✅ | ❌ | ❌ | ❌ |
| `manage_themes` | ✅ | ✅ | ✅ | ❌ | ❌ |
| `manage_content` | ✅ | ✅ | ✅ | ❌ | ❌ |
| `manage_media` | ✅ | ✅ | ✅ | ✅ | ❌ |
| `edit_posts` | ✅ | ✅ | ✅ | ✅ | ❌ |
| `access_member_content` | ✅ | ✅ | ✅ | ✅ | ✅ |

---

## 5. Database Schema Blueprint

### Relational + JSON Hybrid Schema

| Table Name | Purpose | Key Columns |
| :--- | :--- | :--- |
| `{prefix}options` | Site & system settings | `id`, `option_name` (UNIQUE), `option_value` (LONGTEXT), `autoload` |
| `{prefix}users` | RBAC accounts & authentication | `id`, `username` (UNIQUE), `email` (UNIQUE), `password_hash`, `role`, `status` |
| `{prefix}posts` | Pages, Posts & Custom Post Types | `id`, `title`, `slug`, `content_blocks` (JSON), `content_html`, `post_type`, `status` |
| `{prefix}meta_data`| Indexed custom fields | `id`, `object_type`, `object_id`, `meta_key`, `meta_value` (JSON) |
| `{prefix}terms` | Categories, tags & taxonomies | `id`, `name`, `slug`, `taxonomy`, `parent_id` |
| `{prefix}term_relationships` | Post-to-taxonomy bindings | `object_id`, `term_id` (PRIMARY KEY) |
| `{prefix}migrations` | Schema version history | `id`, `migration` (UNIQUE), `batch`, `applied_at` |
| `{prefix}analytics_daily` | DSGVO cookieless daily aggregates | `id`, `date`, `path`, `visitor_hash`, `views`, `device`, `browser`, `referrer` |
| `{prefix}orders` | Stripe transactions & member unlockings | `id`, `user_id`, `stripe_session_id`, `stripe_customer_id`, `amount_cents`, `currency`, `status` |

---

## 6. Live Theme Engine & JIT CSS Extraction Pipeline

```
[Theme Selection / Studio Customizer]
               │
               ▼
   Read / Write `theme.json`
   (Colors, Typography, Layout, CPT Blueprints)
               │
               ▼
[Shared-Hosting JIT CssCompiler]
   ├─ Scans database JSON blocks ({prefix}posts.content_blocks)
   ├─ Scans theme templates & HTML
   ├─ Injects :root CSS design tokens (--hb-primary, --hb-bg, etc.)
   ├─ Generates responsive utilities (sm:, md:, lg:, hover:, focus:)
   └─ Compiles minified bundle -> /storage/cache/css/theme_{slug}.css
               │
               ▼
Served via `/assets/css/theme.css` with 304 ETag / HTTP Cache-Control
```

---

## 7. Multi-Tier Caching & Safe Custom Code Architecture

### Caching Architecture

| Cache Tier | Storage Location | Driver | TTL / Invalidation | Target TTFB |
| :--- | :--- | :--- | :--- | :--- |
| **Full-Page Cache** (`PageCache`) | `/storage/cache/html/{hash}.html` | Static Atomic File | 24h default; instant purge on post save, theme switch, or custom code update | < 20ms |
| **Object / Query Cache** (`ObjectCache`) | `/storage/cache/objects/` | APCu / Atomic File | Configurable per item; atomic serialization | < 1ms |

- **Bypass Conditions:** Authenticated sessions (`Auth::check()`), POST/PUT/DELETE requests, admin routes (`/admin/*`), and query parameters (`?nocache=1`, `?preview=1`, `?hb_safe_mode=1`) immediately bypass `PageCache`.

### Safe Custom Code Injection & Disaster Recovery

```
[Frontend Request Render]
           │
           ▼
[CustomCodeService::inject]
   ├─ If NOT enabled OR in Safe-Mode ──► Skip all injections
   └─ If enabled & normal request:
         ├─ Head Snippets        ──────► Injected before </head>
         ├─ Body Start Snippets  ──────► Injected after <body...>
         └─ Body End Snippets    ──────► Injected before </body>
```

- **Disaster Recovery Safe-Mode URL:** Appending `?hb_safe_mode=1` or `?safe_mode={token}` completely suppresses all custom scripts, ensuring self-healing recovery if a faulty client script causes a browser crash.

---

## 8. Built-in Agency Modules & Lifecycle Architecture

### Transactional Mail Pipeline (`core/Mail/`)

```
[Mailer::send($to, $subject, $html)]
               │
               ├─ Driver: 'resend' ──► Resend REST API (https://api.resend.com/emails) via cURL Bearer Token
               ├─ Driver: 'smtp'   ──► Pure-PHP Sockets (stream_socket_client, EHLO, STARTTLS, AUTH LOGIN, DATA)
               └─ Driver: 'log'    ──► storage/logs/mail.log (Local Dev & Testing)
```

- **Zero Composer Sockets:** The SMTP driver connects via native PHP stream sockets and manual RFC 5321 protocol handling, eliminating SwiftMailer/PHPMailer baggage.
- **Built-in Diagnostic Tool:** `Mailer::sendTest($to)` tests connectivity, authentication, and returns actionable error codes directly in the Admin UI.

### Zero-Exec Database & File Backups (`core/Backup/`)

```
[Backup Request (UI or Cron)]
               │
               ▼
   [BackupService::createBackup]
        ├─ dumpDatabase() ───────► Pure PHP PDO streaming (SHOW CREATE TABLE + Batched SELECTs)
        │                          Produces standard SQL dump without exec() or mysqldump.
        ├─ ZipArchive Streaming ─► Adds database.sql, manifest.json, /config, /uploads, /themes.
        └─ Retention Pruning ────► Automatically prunes archives older than top 5.
```

- **Cron Automation:** Triggerable via `GET /api/backup/cron?token={secret_token}`.

### 1-Click Over-The-Air (OTA) Core Self-Updater (`core/Updater/`)

```
[1-Click Update Trigger]
           │
           ▼
1. Auto Safety Backup ─────► Creates DB snapshot in storage/backups/
           │
2. Download Release ───────► Fetches release ZIP from GitHub Releases via cURL
           │
3. Safe Extraction ────────► Extracts to storage/cache/tmp_update/
           │
4. Selective Replace ──────► Updates /core and /public/assets/
                             STRICTLY PRESERVES /config, /uploads, /storage, /themes
           │
5. Auto-Migration & Purge ─► MigrationRunner::runPending() -> Cache::flush()
```

### DSGVO Cookieless Analytics Pipeline (`core/Analytics/`)

```
[Incoming Frontend Request]
           │
           ▼
[AnalyticsService::track]
   ├─ Ignore Bots & Static Assets (.css, .js, .png, /admin, /api)
   ├─ Truncate IP: IPv4 to /24 (192.168.1.0), IPv6 to /48 (2001:db8:85a3::)
   ├─ Hash: sha256(TruncatedIP + UserAgent + DailyRotatingSalt)
   └─ Upsert into {prefix}analytics_daily (views, device, browser, clean referrer)
```

- **100% Cookieless & TDDDG Compliant:** No cookie headers, no persistent user tracking across days, no external CDN or analytics beacons.

### Built-in SEO & Universal AI Assistant (`core/Seo/` & `core/Ai/`)

- **Dynamic Sitemap:** Serves RFC-compliant XML at `/sitemap.xml` with automatic publication updates and priority weighting.
- **Meta & OpenGraph:** Automates `<title>`, `<meta name="description">`, `og:title`, `og:image`, `twitter:card`, and JSON-LD structured data schema.
- **AI Assistant:** Universal cURL client supporting **Google Gemini** (`gemini-1.5-flash`), **OpenAI** (`gpt-4o-mini`), and **Anthropic** (`claude-3-haiku`) for 1-click generation of SEO titles, meta descriptions, and marketing copy.

### GDPR Tools & Local Google Fonts Downloader (`core/Gdpr/`)

- **Local Fonts Downloader:** Fetches Google Fonts CSS with a modern User-Agent, downloads `.woff2` font files to `public/assets/fonts/`, and rewrites `@font-face` definitions to local paths. 100% German court / DSGVO compliant.
- **User Privacy Rights:** 1-click JSON export (Art. 15) and complete PII anonymization (Art. 17).

### Stripe Checkout & Native HMAC-SHA256 Webhooks (`core/Payments/`)

- **Direct REST API:** Generates Stripe Checkout sessions via `https://api.stripe.com/v1/checkout/sessions`.
- **Pure-PHP Signature Verification:** Validates `Stripe-Signature` timestamp and `hash_hmac('sha256', ...)` with 300s replay tolerance.
- **Member Unlocking:** On `checkout.session.completed`, logs transaction in `{prefix}orders` and promotes user to `member` role.

---

## 8. Theme Architecture & Production Release Builder (Phase 6)

### The 4 Default Production Themes

Heartbeat CMS bundles four standalone, zero-external-dependency production themes located in `themes/`:

1. **One-Pager Starter Theme (`themes/default/`, slug: `one-pager`)**
   - Single-page, high-converting layout with smooth scrolling anchor navigation (`scroll-smooth`).
   - Hero section with dual action CTAs, feature grids, services overview, client testimonials, Alpine.js accordion FAQ, and honeypot-protected contact form.
   - Design Tokens: Clean modern blue palette (`#2563eb`), dark slate backgrounds, rounded components.

2. **Clean Corporate Theme (`themes/corporate/`, slug: `corporate`)**
   - Multi-page agency and enterprise layout with sticky header navigation.
   - Accessible multi-level dropdown menus adhering strictly to WCAG specifications (`aria-haspopup="true"`, `aria-expanded`, `role="menu"`).
   - Dynamic Custom Post Type (CPT) integration:
     - Team Profiles (`team_member`): Position, department tags, social links, bio.
     - Case Studies (`case_study`): Client names, challenge/solution narratives, KPI metric badges.

3. **Classic Real Estate Theme (`themes/real-estate/`, slug: `real-estate`)**
   - Luxury property showcase powered by the `property` CPT.
   - Interactive Alpine.js reactive filter grid: Instant client-side filtering by location, property type, price ceiling, and bedroom count.
   - Fullscreen Gallery Lightbox modal with thumbnail preview, next/prev navigation, and ESC dismiss.
   - Property-specific inquiry modal and form pre-filled with property ID and title.

4. **Modern Startup Theme (`themes/startup/`, slug: `startup`)**
   - Tech and SaaS theme with instant Dark/Light mode toggle (`localStorage` persistence, no page reload or FOUC).
   - Interactive pricing tables with monthly/yearly billing toggle and dynamic "Save 20%" discount badge.
   - Direct Stripe Checkout button integration (`/api/stripe/checkout?plan=pro`).

### Template Resolution Hierarchy

`App::renderFrontendPage()` and `App::renderCptArchivePage()` follow a strict cascading resolution hierarchy:

```
[Incoming Frontend Route]
          │
          ▼
   Is Homepage (/) ?
   ├── YES ──► Check themes/{active}/templates/home.php ──► Fallback: default core shell
   │
   └── NO
       ├── CPT Archive (e.g. /immobilien, /team, /referenzen)
       │   └── Check themes/{active}/templates/{cpt_prefix}.php
       │       └── Fallback: themes/{active}/templates/archive.php
       │           └── Fallback: core archive shell
       │
       ├── Single Post / CPT View (e.g. /immobilien/{slug})
       │   └── Check themes/{active}/templates/{post_type}.php
       │       └── Fallback: themes/{active}/templates/single.php
       │           └── Fallback: core single shell
       │
       └── Standard Page (e.g. /ueber-uns, /preise)
           └── Check themes/{active}/templates/{slug}.php
               └── Fallback: themes/{active}/templates/page.php
                   └── Fallback: core block page shell
```

### Universal Contact & Inquiry Controller (`core/Http/ContactController.php`)

```
[Form Submission: POST /api/contact or POST /contact]
                     │
                     ▼
             1. CSRF Verification
                     │  (Invalid token ──► 403 Forbidden)
                     ▼
      2. Invisible Honeypot Spam Trap (_hb_hp)
                     │
         Is _hb_hp populated?
         ├── YES (Bot detected) ──► Silently returns HTTP 200 {"success":true}
         │                          (NO email sent, bot remains unaware)
         └── NO  (Human user)
                     │
                     ▼
             3. Input Sanitization & Validation
                     │
                     ▼
             4. Dispatch Transactional Notification
                via Mailer::send() to site administrator
                     │
                     ▼
             5. Return JSON or Redirect Response
```

### Production Release Builder Pipeline (`scripts/`)

Heartbeat CMS includes dual packaging tools generating clean, production-ready release archives (`dist/heartbeat-cms-install.zip`):

- **Zero-Exec Pure-PHP Builder (`scripts/build-release.php`):** Uses native PHP `ZipArchive` to build release packages on shared hosting or CI pipelines without Node.js or exec privileges.
- **Node.js Builder (`scripts/build-release.js`):** Fast automated packaging script with SHA-256 integrity verification.
- **Strict Clean-State Whitelist:** Packages exclude `.git`, `node_modules`, `config/installed.lock`, `config/env.php`, `.sqlite` databases, and development logs, while pre-creating all required `/storage` cache subdirectories and `/uploads/.htaccess` security execution blocks.


