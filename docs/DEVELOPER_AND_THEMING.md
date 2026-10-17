# Heartbeat CMS - Developer & Theming Guide

## 1. Theming Architecture

Themes reside in `/themes/{theme-slug}/` and require a `theme.json` manifest.

### `theme.json` Schema

```json
{
  "name": "Corporate Pro Theme",
  "slug": "corporate",
  "version": "1.0.0",
  "author": "Agency Name",
  "description": "Multi-page corporate agency theme",
  "colors": {
    "primary": "#f97316",
    "primary_hover": "#ea580c",
    "background": "#090d16",
    "card": "#0f172a",
    "border": "#1e293b",
    "text": "#f8fafc"
  },
  "typography": {
    "font_family": "Inter",
    "heading_font": "Inter"
  },
  "cpt_blueprints": [
    "team_member",
    "service",
    "case_study"
  ],
  "templates": [
    "home",
    "page",
    "single",
    "404"
  ]
}
```

---

## 2. Structured JSON Block Engine

Page content is stored in `{prefix}posts.content_blocks` as an indexed JSON tree.

### Example Block Structure
```json
[
  {
    "id": "blk_hero_101",
    "type": "core/hero",
    "attrs": {
      "headline": "Modern Digital Architecture",
      "subheadline": "Crafted for speed, built for security.",
      "primary_btn_text": "Get Started",
      "primary_btn_url": "/contact"
    },
    "styles": {
      "class": "py-24"
    },
    "innerBlocks": []
  }
]
```

### Core Blocks Catalog
1. `core/hero`: Headline, subheadline, badge, CTA buttons, alignment.
2. `core/heading`: Heading tags (h1-h6), text, kicker/subtitle, alignment.
3. `core/paragraph`: Formatted rich text, lead styling option.
4. `core/image`: Responsive image, alt text, caption, aspect ratio, link.
5. `core/button`: CTA buttons with variants (`primary`, `secondary`, `outline`, `ghost`).
6. `core/columns`: Multi-column container (1-4 cols) supporting recursive `innerBlocks`.
7. `core/card`: Card component with icon, title, description, link, badge.
8. `core/features`: Feature grid with icons, titles, and descriptions.
9. `core/faq`: Accordion FAQ with questions and answers (Alpine.js).
10. `core/quote`: Testimonial quote with author, role, avatar, and star rating.
11. `core/alert`: Callout box (info, success, warning, alert).
12. `core/spacer`: Vertical spacer and divider line.
13. `core/popup`: Marketing popup & paywall toolkit (time delay, scroll depth, exit intent, or member role gate).

### Programmatic Rendering
```php
use Heartbeat\Blocks\BlockRenderer;

$html = BlockRenderer::getInstance()->render($blocksJsonOrArray);
```

---

## 3. Registering Custom Post Types (CPTs)

CPTs do not require custom database tables. They are defined via declarative JSON blueprints and stored in `{prefix}posts` with custom fields in `{prefix}meta_data`.

### Example Blueprint: `property.json`
```json
{
  "post_type": "property",
  "singular": "Immobilie",
  "plural": "Immobilien",
  "icon": "home",
  "supports": ["title", "blocks", "thumbnail", "excerpt"],
  "meta_fields": [
    {
      "key": "price",
      "label": "Kaufpreis (€)",
      "type": "number",
      "required": true
    },
    {
      "key": "rooms",
      "label": "Zimmer",
      "type": "number",
      "step": 0.5
    },
    {
      "key": "living_space",
      "label": "Wohnfläche (m²)",
      "type": "number"
    },
    {
      "key": "location",
      "label": "Standort / Ort",
      "type": "text"
    }
  ]
}
```

---

## 4. Live Server Tailwind JIT Strategy & CssCompiler

Because client websites are customized live on managed hosting servers where Node.js and npm are absent, Heartbeat CMS utilizes a dual JIT strategy:

### 1. Native Shared-Hosting PHP Compiler (`CssCompiler`)
- Can compile CSS 100% on the server without any client/browser or Node.js runtime.
- Scans `{prefix}posts.content_blocks` (all used blocks across all pages) and HTML content.
- Generates:
  - `:root` design token variables (`--hb-primary`, `--hb-bg`, `--hb-card`, `--hb-border`, etc.).
  - Base resets & heading fonts.
  - Standard utility classes (`flex`, `grid-cols-3`, `max-w-6xl`, `p-6`, `bg-[#090d16]`, etc.).
  - Responsive variants inside `@media (min-width: 640px/768px/1024px/1280px)` for `sm:`, `md:`, `lg:`, `xl:`.
  - State variants (`hover:`, `focus:`).
- Minifies output and writes directly to `/storage/cache/css/theme_{slug}.css`.

### 2. In-Browser / Editor JIT API
- When designing in the Visual Block Builder or Live Studio Customizer, new classes can also be compiled or posted via:
  `POST /admin/api/themes/compile-css`
- Payload: `{"theme_slug": "one-pager", "classes": ["p-8", "gap-6"], "custom_css": ".badge { color: red; }"}`

---

## 5. Multi-Tier Caching API

### Full-Page Static HTML Cache (`PageCache`)
```php
use Heartbeat\Cache\Cache;

// Retrieve PageCache instance
$pageCache = Cache::page();

// Invalidate specific URL path
$pageCache->invalidate('/leistungen');

// Flush entire HTML page cache
$pageCache->flush();
```

### Object & Query Cache (`ObjectCache`)
```php
use Heartbeat\Cache\Cache;

// Store value with 1 hour TTL
Cache::set('featured_properties', $properties, 3600);

// Retrieve value
$properties = Cache::get('featured_properties');

// Remember pattern (fetch or compute & store)
$stats = Cache::remember('site_stats', 300, function () {
    return Database::getInstance()->fetchAll("SELECT status, count(*) FROM {prefix}posts GROUP BY status");
});

// Flush all object caches
Cache::object()->flush();
```

---

## 6. Custom Code Injection & Safe-Mode Recovery

### Programmatic Injection
```php
use Heartbeat\CustomCode\CustomCodeService;

$service = CustomCodeService::getInstance();

// Save snippets
$service->save([
    'head_code'       => '<meta name="google-site-verification" content="...">',
    'body_start_code' => '<noscript>GTM</noscript>',
    'body_end_code'   => '<script src="https://analytics.example.com/script.js"></script>',
    'enabled'         => true,
]);

// Injects snippets into HTML (bypasses automatically if in Safe Mode)
$cleanHtml = $service->inject($rawHtml, $request);
```

### Disaster Recovery: Safe-Mode Parameters
If client scripts cause white screens or infinite loops, access the site with either:
- Generic Parameter: `/?hb_safe_mode=1`
- Cryptographic Secret Token: `/?safe_mode={safe_mode_token}`
All custom code will be skipped immediately.

---

## 7. Agency Modules & Lifecycle Programmatic APIs

### Transactional Mail Engine
```php
use Heartbeat\Mail\Mailer;

// Dispatch transactional email using the active driver (Resend or SMTP)
$success = Mailer::getInstance()->send(
    'kunde@beispiel.de',
    'Ihre Buchungsbestätigung',
    '<h1>Vielen Dank!</h1><p>Ihre Anfrage wurde erfolgreich bearbeitet.</p>',
    'Vielen Dank! Ihre Anfrage wurde erfolgreich bearbeitet.'
);

// Diagnostic test
$diag = Mailer::getInstance()->sendTest('test@agency.de');
```

### Zero-Exec Backups
```php
use Heartbeat\Backup\BackupService;

// Create combined DB + Files backup
$backup = BackupService::getInstance()->createBackup(includeDb: true, includeFiles: true);
// Result: ['success' => true, 'filename' => '...', 'size' => 1234567, 'filepath' => '...']

// List existing archives
$list = BackupService::getInstance()->listBackups();
```

### Cookieless Analytics
```php
use Heartbeat\Analytics\AnalyticsService;

// Automatically called for frontend GET requests
AnalyticsService::getInstance()->track($request);

// Query KPI metrics for custom widgets
$metrics = AnalyticsService::getInstance()->getDashboardSummary(days: 30);
```

### SEO & AI Meta Generation
```php
use Heartbeat\Seo\SeoService;
use Heartbeat\Ai\AiService;

// Generate full HTML meta tags & JSON-LD
$metaTags = SeoService::getInstance()->renderMetaTags($post, $siteTitle, $siteUrl);

// AI-assisted SEO generation (Gemini, OpenAI, Anthropic)
$seoData = AiService::getInstance()->generateSeoMeta($post['title'], $post['content_html']);
// Result: ['title' => '...', 'description' => '...', 'keywords' => '...']
```

### Stripe Checkout & Paywalls
```php
use Heartbeat\Payments\StripeService;

// Initiate Stripe Checkout session for Member access
$checkout = StripeService::getInstance()->createCheckoutSession([
    'title'        => 'Exklusiver Mitglieder-Zugang',
    'amount_cents' => 2900, // 29,00 €
    'currency'     => 'eur',
    'user_id'      => $user['id'],
    'plan'         => 'annual_pro',
]);

// Redirect customer
Response::redirect($checkout['checkout_url'])->send();
```

---

## 7. Theme Template Development (Phase 6)

### Directory Structure of a Heartbeat Theme
```text
themes/my-custom-theme/
├── theme.json                  # Theme manifest (name, version, tokens, CPT blueprints)
├── assets/
│   ├── css/
│   ├── js/
│   └── img/
└── templates/
    ├── header.php              # Global navigation, meta tags, and opening <body>
    ├── footer.php              # Global footer, closing scripts, and </body>
    ├── home.php                # Homepage template (rendered for route /)
    ├── page.php                # Default page template
    ├── single.php              # Default single post/item template
    ├── archive.php             # Default collection / CPT archive template
    ├── 404.php                 # Error 404 not found template
    └── {cpt_prefix}.php        # Optional CPT-specific templates (e.g. property.php, team.php)
```

### Theme Template Includes & Variables
Inside theme templates, `$theme`, `$post`, `$siteTitle`, `$siteUrl`, and template-specific variables (such as `$teamMembers`, `$properties`, `$plans`) are passed into the scope:

```php
<?php
// Include theme header partial
require __DIR__ . '/header.php';
?>

<main class="max-w-7xl mx-auto px-4 py-12">
    <h1 class="text-3xl font-extrabold text-[var(--hb-text)]"><?= htmlspecialchars($post['title']) ?></h1>
    <div class="prose mt-6">
        <?= $post['content_html'] ?? '' ?>
    </div>
</main>

<?php
// Include theme footer partial
require __DIR__ . '/footer.php';
?>
```

### Querying Custom Post Types in Theme Templates
```php
use Heartbeat\Content\PostService;

// Fetch all published properties sorted by creation date
$properties = PostService::getInstance()->all([
    'type'   => 'property',
    'status' => 'published',
    'limit'  => 50,
]);

// Iterate and access custom metadata
foreach ($properties as $prop) {
    $meta = $prop['meta'] ?? [];
    $price = number_format((float)($meta['price'] ?? 0), 0, ',', '.') . ' €';
    $location = htmlspecialchars($meta['location'] ?? 'Deutschland');
}
```

### Universal Contact Form with Anti-Spam Honeypot
Any theme template can include the universal contact form. When using Alpine.js or native forms, always include the CSRF token and the hidden anti-spam honeypot field `_hb_hp`:

```html
<form action="/api/contact" method="POST" class="space-y-4">
    <!-- CSRF Token (Mandatory) -->
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\Heartbeat\Security\Csrf::token()) ?>">
    
    <!-- Anti-Spam Honeypot: Invisible to human users, traps spam bots -->
    <div style="display:none;" aria-hidden="true">
        <input type="text" name="_hb_hp" tabindex="-1" autocomplete="off">
    </div>

    <!-- Optional: Link form to a specific CPT item (e.g. real estate inquiry) -->
    <input type="hidden" name="property_id" value="<?= htmlspecialchars((string)($post['id'] ?? '')) ?>">
    <input type="hidden" name="property_title" value="<?= htmlspecialchars($post['title'] ?? '') ?>">

    <div>
        <label for="name" class="block text-sm font-medium">Name</label>
        <input type="text" id="name" name="name" required class="w-full rounded border px-3 py-2">
    </div>

    <div>
        <label for="email" class="block text-sm font-medium">E-Mail</label>
        <input type="email" id="email" name="email" required class="w-full rounded border px-3 py-2">
    </div>

    <div>
        <label for="message" class="block text-sm font-medium">Nachricht</label>
        <textarea id="message" name="message" rows="4" required class="w-full rounded border px-3 py-2"></textarea>
    </div>

    <button type="submit" class="bg-[var(--hb-primary)] text-white font-bold py-2 px-6 rounded">
        Absenden
    </button>
</form>
```




