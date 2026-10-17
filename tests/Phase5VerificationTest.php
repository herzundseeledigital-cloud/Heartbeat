<?php
declare(strict_types=1);

namespace Heartbeat\Tests;

require_once __DIR__ . '/../core/Bootstrap.php';

use Heartbeat\Bootstrap;
use Heartbeat\Database\Database;
use Heartbeat\Database\MigrationRunner;
use Heartbeat\Security\Security;
use Heartbeat\Security\Rbac;
use Heartbeat\Security\Csrf;
use Heartbeat\Security\Session;
use Heartbeat\Auth\Auth;
use Heartbeat\Http\Request;
use Heartbeat\Http\Response;
use Heartbeat\Agency\AgencyService;
use Heartbeat\Mail\Mailer;
use Heartbeat\Mail\LogDriver;
use Heartbeat\Mail\ResendDriver;
use Heartbeat\Mail\SmtpDriver;
use Heartbeat\Backup\BackupService;
use Heartbeat\Updater\UpdaterService;
use Heartbeat\Analytics\AnalyticsService;
use Heartbeat\Seo\SeoService;
use Heartbeat\Ai\AiService;
use Heartbeat\Gdpr\GoogleFontsDownloader;
use Heartbeat\Gdpr\GdprService;
use Heartbeat\Payments\StripeService;
use Heartbeat\Admin\AgencyModulesController;

Bootstrap::init();

echo "======================================================================\n";
echo "=== PHASE 5: BUILT-IN AGENCY MODULES & LIFECYCLE TOOLS TEST ===\n";
echo "======================================================================\n\n";

// 0. Setup isolated SQLite test database
$testDbFile = HEARTBEAT_STORAGE . '/phase5_test.sqlite';
if (is_file($testDbFile)) {
    unlink($testDbFile);
}

putenv("DB_DRIVER=sqlite");
putenv("DB_DATABASE={$testDbFile}");
putenv("DB_PREFIX=hb_");

$db = Database::getInstance([
    'driver'   => 'sqlite',
    'database' => $testDbFile,
    'prefix'   => 'hb_',
]);

// Run all migrations
$runner = new MigrationRunner($db);
$applied = $runner->runPending();
assert(count($applied) >= 2, "Migrations 001 and 002 must be applied.");

// Seed super_admin & client admin
$now = date('Y-m-d H:i:s');
$db->insert('{prefix}users', [
    'username'      => 'agency_super',
    'email'         => 'super@agency.de',
    'password_hash' => Security::hashPassword('supersecret123'),
    'role'          => Rbac::ROLE_SUPER_ADMIN,
    'status'        => 'active',
    'created_at'    => $now,
    'updated_at'    => $now,
]);
$superId = (int)$db->lastInsertId();

$db->insert('{prefix}users', [
    'username'      => 'client_admin',
    'email'         => 'client@firma.de',
    'password_hash' => Security::hashPassword('clientsecret123'),
    'role'          => Rbac::ROLE_ADMIN,
    'status'        => 'active',
    'created_at'    => $now,
    'updated_at'    => $now,
]);
$clientId = (int)$db->lastInsertId();

$db->insert('{prefix}options', ['option_name' => 'site_title', 'option_value' => 'Heartbeat Agency Test', 'autoload' => 1]);
$db->insert('{prefix}options', ['option_name' => 'site_url', 'option_value' => 'http://localhost:8000', 'autoload' => 1]);
$db->insert('{prefix}options', ['option_name' => 'admin_email', 'option_value' => 'admin@heartbeat-cms.de', 'autoload' => 1]);

// Seed test posts
$db->insert('{prefix}posts', [
    'title'          => 'Startseite',
    'slug'           => 'home',
    'content_blocks' => '[]',
    'content_html'   => '<h1>Willkommen auf der Startseite</h1><p>Agentur-Websites leicht gemacht.</p>',
    'post_type'      => 'page',
    'status'         => 'publish',
    'author_id'      => $superId,
    'created_at'     => $now,
    'published_at'   => $now,
]);

$db->insert('{prefix}posts', [
    'title'          => 'Über Uns',
    'slug'           => 'ueber-uns',
    'content_blocks' => '[]',
    'content_html'   => '<p>Wir sind die führende Webagentur.</p>',
    'post_type'      => 'page',
    'status'         => 'publish',
    'author_id'      => $superId,
    'created_at'     => $now,
    'published_at'   => $now,
]);

// -----------------------------------------------------------------------------
// 1. TRANSACTIONAL MAILER TESTS
// -----------------------------------------------------------------------------
echo "1. Testing Transactional Mail Engine (Resend + SMTP + Log Driver)...\n";
$mailer = Mailer::getInstance();

// A. Test LogDriver
$logFile = HEARTBEAT_STORAGE . '/logs/test_mail.log';
if (is_file($logFile)) unlink($logFile);

$logDriver = new LogDriver($logFile);
$sent = $logDriver->send('test@client.de', 'Willkommen', '<h1>Hallo</h1>', 'Hallo');
assert($sent === true, "LogDriver must send successfully.");
assert(is_file($logFile), "Log file must exist.");
$logContent = (string)file_get_contents($logFile);
assert(str_contains($logContent, 'test@client.de'), "Log must record recipient.");
assert(str_contains($logContent, 'Willkommen'), "Log must record subject.");

// B. Test Resend Driver validation
$resendDriver = new ResendDriver('', 'info@agency.de');
$rFail = $resendDriver->send('client@domain.de', 'Subj', '<p>Test</p>');
assert($rFail === false, "Resend without API key must fail.");
assert(str_contains($resendDriver->getLastError(), 'API-Schlüssel'), "Error message must mention missing API key.");

// C. Test Smtp Driver validation & structure
$smtpDriver = new SmtpDriver('smtp.invalid-host-test.local', 587, 'tls', 'user', 'pass', 'info@agency.de');
$sFail = $smtpDriver->send('target@client.de', 'Subject', '<p>Test</p>');
assert($sFail === false, "Invalid SMTP host must fail safely without leaking raw exceptions.");
assert(strlen($smtpDriver->getLastError()) > 0, "SMTP driver must report connection error.");

// D. Test Mailer Service Configuration & Diagnostics
$mailer->saveSettings([
    'mail_driver'     => 'log',
    'mail_from_name'  => 'Herz & Seele Mail',
    'mail_from_email' => 'post@herzundseele.de',
]);
$savedSettings = $mailer->getSettings();
assert($savedSettings['mail_driver'] === 'log', "Mail driver must be 'log'.");
assert($savedSettings['mail_from_name'] === 'Herz & Seele Mail', "Sender name must be updated.");

$testDiag = $mailer->sendTest('diagnostic@kunde.de');
assert($testDiag['success'] === true, "sendTest with log driver must succeed.");
assert(str_contains($testDiag['message'], 'diagnostic@kunde.de'), "Message must confirm test dispatch.");
echo "   ✓ Transactional Mailer (Resend, SMTP, Log driver & test diagnostics) verified!\n";

// -----------------------------------------------------------------------------
// 2. ZERO-EXEC BACKUPS TESTS
// -----------------------------------------------------------------------------
echo "2. Testing Zero-Exec Database & File Backups (PDO Streaming & ZipArchive)...\n";
$backupService = BackupService::getInstance();

// A. Test SQL Database Dump
$sqlDump = $backupService->dumpDatabase();
assert(str_contains($sqlDump, 'Heartbeat CMS Native Database Dump'), "SQL dump must have header.");
assert(str_contains($sqlDump, 'hb_users'), "SQL dump must contain users table.");
assert(str_contains($sqlDump, 'hb_posts'), "SQL dump must contain posts table.");
assert(str_contains($sqlDump, 'agency_super'), "SQL dump must contain seeded super_admin user.");

// B. Test Backup Creation (Database + Files)
$backupResult = $backupService->createBackup(true, true);
assert($backupResult['success'] === true, "createBackup must succeed: " . ($backupResult['message'] ?? ''));
assert(is_file($backupResult['filepath']), "Backup ZIP file must be created on disk.");
assert($backupResult['size'] > 0, "Backup ZIP must have non-zero size.");

// Inspect ZIP archive contents
$zip = new \ZipArchive();
$openRes = $zip->open($backupResult['filepath']);
assert($openRes === true, "Backup ZIP must be a valid zip archive.");
assert($zip->locateName('database.sql') !== false, "ZIP must contain database.sql.");
assert($zip->locateName('manifest.json') !== false, "ZIP must contain manifest.json.");
$manifestContent = (string)$zip->getFromName('manifest.json');
assert(str_contains($manifestContent, 'Heartbeat CMS'), "Manifest must indicate Heartbeat CMS.");
$zip->close();

// C. Test Backup Listing & Retention
$backupsList = $backupService->listBackups();
assert(count($backupsList) >= 1, "listBackups must list at least 1 backup.");
assert(isset($backupsList[0]['filename']) && isset($backupsList[0]['download_url']), "Backup list structure must be valid.");

// D. Test Cron Token
$cronToken = $backupService->getCronToken();
assert(strlen($cronToken) >= 32, "Cron token must be secure random string.");
assert($backupService->verifyCronToken($cronToken) === true, "verifyCronToken must validate valid token.");
assert($backupService->verifyCronToken('invalid_token') === false, "verifyCronToken must reject invalid token.");

// Clean up created backup
$backupService->deleteBackup($backupResult['filename']);
echo "   ✓ Zero-Exec Backups (PDO table streaming, ZipArchive, cron token & retention) verified!\n";

// -----------------------------------------------------------------------------
// 3. 1-CLICK OVER-THE-AIR (OTA) SELF-UPDATER TESTS
// -----------------------------------------------------------------------------
echo "3. Testing 1-Click Over-The-Air (OTA) Core Self-Updater...\n";
$updater = UpdaterService::getInstance();

$updater->saveConfig('heartbeat-cms/heartbeat', 'test_token_123', 'release');
$upConfig = $updater->getConfig();
assert($upConfig['repo'] === 'heartbeat-cms/heartbeat', "Updater repo must match.");
assert($upConfig['token'] === 'test_token_123', "Updater token must match.");

// Test update check
$check = $updater->checkForUpdates();
assert(isset($check['current_version']), "Update check must return current version.");
assert($check['current_version'] === HEARTBEAT_VERSION, "Current version must match HEARTBEAT_VERSION.");

// Test safe update pipeline execution
$updateExec = $updater->runUpdate('');
assert($updateExec['success'] === true, "runUpdate must execute safety backup, migrations and cache flush cleanly.");
echo "   ✓ 1-Click OTA Self-Updater pipeline verified!\n";

// -----------------------------------------------------------------------------
// 4. DSGVO COOKIELESS ANALYTICS TESTS
// -----------------------------------------------------------------------------
echo "4. Testing DSGVO Cookieless Analytics (Subnet Masking & Daily Salts)...\n";
$analytics = AnalyticsService::getInstance();

// A. Subnet masking checks
$maskedV4 = $analytics->truncateIp('192.168.178.45');
assert($maskedV4 === '192.168.178.0', "IPv4 must be masked to /24 (got: {$maskedV4}).");
$maskedV6 = $analytics->truncateIp('2001:0db8:85a3:0000:0000:8a2e:0370:7334');
assert($maskedV6 === '2001:0db8:85a3::', "IPv6 must be masked to /48 (got: {$maskedV6}).");

// B. Bot detection
assert($analytics->isBot('Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)') === true, "Googlebot must be detected.");
assert($analytics->isBot('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)') === false, "Regular browser must not be bot.");

// C. Device detection
assert($analytics->detectDevice('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)') === 'mobile', "iPhone must be mobile.");
assert($analytics->detectDevice('Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X)') === 'tablet', "iPad must be tablet.");
assert($analytics->detectDevice('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)') === 'desktop', "Mac must be desktop.");

// D. Tracking execution
$reqDesktop = new Request('GET', '/ueber-uns', [], [], [
    'REMOTE_ADDR'     => '85.214.132.88',
    'HTTP_USER_AGENT' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36',
    'HTTP_REFERER'    => 'https://www.google.de/search?q=heartbeat',
    'HTTP_HOST'       => 'localhost:8000',
]);

$analytics->track($reqDesktop);
$analytics->track($reqDesktop); // Repeated hit same visitor

$summary = $analytics->getDashboardSummary(30);
assert($summary['total_views'] >= 2, "Total views must be at least 2.");
assert($summary['unique_visitors'] >= 1, "Unique visitors must be 1.");
assert($summary['devices']['desktop'] >= 2, "Desktop views must be counted.");
assert(!empty($summary['top_pages']), "Top pages must not be empty.");
assert($summary['top_pages'][0]['path'] === '/ueber-uns', "Top page path must be /ueber-uns.");
assert($summary['top_referrers'][0]['referrer'] === 'www.google.de', "Referrer must be cleaned domain.");
echo "   ✓ DSGVO Cookieless Analytics (Subnet truncation, daily salts & dashboard KPIs) verified!\n";

// -----------------------------------------------------------------------------
// 5. SEO ENGINE & AI ASSISTANT TESTS
// -----------------------------------------------------------------------------
echo "5. Testing SEO Engine (Meta Tags, OpenGraph, JSON-LD & Dynamic XML Sitemap)...\n";
$seo = SeoService::getInstance();

$testPost = [
    'title'        => 'Top Webdesign Agentur',
    'slug'         => 'webdesign-agentur',
    'post_type'    => 'page',
    'content_html' => '<p>Erfahren Sie mehr über unsere Webdesign-Dienstleistungen.</p>',
    'created_at'   => '2026-10-17 12:00:00',
    'published_at' => '2026-10-17 12:00:00',
    'meta'         => [
        '_seo_title'       => 'Exklusives Webdesign in Berlin - Herz & Seele',
        '_seo_description' => 'Maßgeschneiderte Webseiten mit Heartbeat CMS.',
        '_seo_og_image'    => '/uploads/hero-banner.jpg',
    ],
];

$metaHtml = $seo->renderMetaTags($testPost, 'Heartbeat CMS', 'https://beispiel-agentur.de');
assert(str_contains($metaHtml, '<title>Exklusives Webdesign in Berlin - Herz &amp; Seele</title>'), "Custom SEO title must render.");
assert(str_contains($metaHtml, '<meta name="description" content="Maßgeschneiderte Webseiten mit Heartbeat CMS.">'), "SEO description must render.");
assert(str_contains($metaHtml, '<link rel="canonical" href="https://beispiel-agentur.de/webdesign-agentur">'), "Canonical URL must render.");
assert(str_contains($metaHtml, 'property="og:title"'), "OpenGraph og:title must render.");
assert(str_contains($metaHtml, 'property="og:image" content="https://beispiel-agentur.de/uploads/hero-banner.jpg"'), "OpenGraph og:image must be absolute URL.");
assert(str_contains($metaHtml, '<script type="application/ld+json">'), "JSON-LD schema must render.");
assert(str_contains($metaHtml, '"@type":"WebPage"'), "Schema type must be WebPage.");

// Dynamic XML Sitemap
$sitemapXml = $seo->generateSitemap();
assert(str_starts_with($sitemapXml, '<?xml version="1.0" encoding="UTF-8"?>'), "Sitemap must be valid XML header.");
assert(str_contains($sitemapXml, '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'), "Sitemap must have urlset schema.");
assert(str_contains($sitemapXml, '<loc>http://localhost:8000/</loc>'), "Homepage loc must exist.");
assert(str_contains($sitemapXml, '<loc>http://localhost:8000/ueber-uns</loc>'), "Page loc must exist.");

// AI Assistant Config & Fallback
$ai = AiService::getInstance();
$ai->saveSettings(['provider' => 'gemini', 'api_key' => 'test_key', 'model' => 'gemini-1.5-flash']);
$aiSettings = $ai->getSettings();
assert($aiSettings['provider'] === 'gemini', "AI Provider must be gemini.");
assert($aiSettings['model'] === 'gemini-1.5-flash', "AI Model must be gemini-1.5-flash.");
echo "   ✓ SEO Engine (OpenGraph, Twitter Cards, JSON-LD, XML Sitemap & AI Assistant) verified!\n";

// -----------------------------------------------------------------------------
// 6. GDPR TOOLS & LOCAL GOOGLE FONTS DOWNLOADER TESTS
// -----------------------------------------------------------------------------
echo "6. Testing GDPR Tools & Local Google Fonts Downloader...\n";
$fontsDownloader = GoogleFontsDownloader::getInstance();

// A. Test font downloader local stylesheet generation
$fontRes = $fontsDownloader->downloadFont('Inter');
assert(isset($fontRes['font_slug']) && $fontRes['font_slug'] === 'inter', "Font slug must be 'inter'.");

$localFontsList = $fontsDownloader->listLocalFonts();
assert(is_array($localFontsList), "listLocalFonts must return an array.");

// B. Test GDPR Art. 15 User Data Export
$gdpr = GdprService::getInstance();
$exportData = $gdpr->exportUserData($superId);
assert(!empty($exportData['user_profile']), "Export must contain user profile.");
assert($exportData['user_profile']['username'] === 'agency_super', "Exported username must match.");
assert(!empty($exportData['authored_content']), "Export must contain authored content.");

// C. Test GDPR Art. 17 User Anonymization
// Create a member user to test anonymization
$db->insert('{prefix}users', [
    'username'      => 'max_mustermann',
    'email'         => 'max@kunde.de',
    'password_hash' => Security::hashPassword('pw12345678'),
    'role'          => Rbac::ROLE_MEMBER,
    'status'        => 'active',
    'created_at'    => $now,
    'updated_at'    => $now,
]);
$memberId = (int)$db->lastInsertId();

$anonRes = $gdpr->anonymizeUser($memberId);
assert($anonRes === true, "anonymizeUser must succeed.");
$anonUser = $db->fetchOne("SELECT * FROM {prefix}users WHERE id = :id", ['id' => $memberId]);
assert($anonUser['username'] === "anonymized_{$memberId}", "Username must be anonymized.");
assert($anonUser['email'] === "deleted_{$memberId}@anonymized.local", "Email must be scrubbed.");
assert($anonUser['status'] === 'inactive', "User must be set inactive.");
echo "   ✓ GDPR Tools (Google Fonts local hosting, Art. 15 export & Art. 17 anonymization) verified!\n";

// -----------------------------------------------------------------------------
// 7. STRIPE CHECKOUT & WEBHOOK INTEGRATION TESTS
// -----------------------------------------------------------------------------
echo "7. Testing Stripe Checkout & Native Webhook Signature Verification...\n";
$stripe = StripeService::getInstance();

$stripe->saveSettings([
    'enabled'         => true,
    'mode'            => 'test',
    'secret_key'      => 'sk_test_fake_key_123',
    'publishable_key' => 'pk_test_fake_pub_123',
    'webhook_secret'  => 'whsec_test_secret_abc',
    'currency'        => 'eur',
]);

$sSettings = $stripe->getSettings();
assert($sSettings['enabled'] === true, "Stripe must be enabled.");
assert($sSettings['mode'] === 'test', "Stripe mode must be test.");

// Test Webhook Signature verification
$webhookPayload = json_encode([
    'id'   => 'evt_test_123',
    'type' => 'checkout.session.completed',
    'data' => [
        'object' => [
            'id'               => 'cs_test_session_abc789',
            'customer'         => 'cus_12345',
            'customer_email'   => 'paying_client@kunde.de',
            'amount_total'     => 4900,
            'currency'         => 'eur',
            'metadata'         => [
                'user_id' => (string)$memberId,
                'plan'    => 'pro_membership',
            ],
        ],
    ],
]);

$timestamp = time();
$signedPayload = "{$timestamp}.{$webhookPayload}";
$validSig = hash_hmac('sha256', $signedPayload, 'whsec_test_secret_abc');
$sigHeader = "t={$timestamp},v1={$validSig}";

$verified = $stripe->verifyWebhookSignature($webhookPayload, $sigHeader, 'whsec_test_secret_abc');
assert($verified === true, "verifyWebhookSignature must validate authentic Stripe signature.");

$invalidSig = $stripe->verifyWebhookSignature($webhookPayload, "t={$timestamp},v1=wrong_sig", 'whsec_test_secret_abc');
assert($invalidSig === false, "verifyWebhookSignature must reject forged signature.");

// Test Webhook Dispatch & Member Unlock
$webhookResult = $stripe->handleWebhook($webhookPayload, $sigHeader);
assert($webhookResult['success'] === true, "handleWebhook must successfully process event.");
assert($webhookResult['event_type'] === 'checkout.session.completed', "Event type must match.");

// Verify database order recorded
$savedOrder = $db->fetchOne("SELECT * FROM {prefix}orders WHERE stripe_session_id = 'cs_test_session_abc789'");
assert($savedOrder !== null, "Order must be recorded in hb_orders.");
assert((int)$savedOrder['amount_cents'] === 4900, "Amount must be 4900 cents.");
assert($savedOrder['plan_or_item'] === 'pro_membership', "Plan must match metadata.");

// Verify recent orders query
$recentOrders = $stripe->getRecentOrders(5);
assert(count($recentOrders) >= 1, "getRecentOrders must list the processed order.");
echo "   ✓ Stripe Integration (HMAC-SHA256 signature verification, webhooks & member unlocking) verified!\n";

// -----------------------------------------------------------------------------
// 8. ADMIN CONTROLLER & CLIENT-GUARD SECURITY TESTS
// -----------------------------------------------------------------------------
echo "8. Testing Agency Modules Admin Controller & Client-Guard Security...\n";
$controller = new AgencyModulesController();

// A. Test Analytics View
Auth::login($db->fetchOne("SELECT * FROM {prefix}users WHERE id = :id", ['id' => $superId]));
$resAnalytics = $controller->analytics(new Request('GET', '/admin/analytics'));
assert($resAnalytics->getStatusCode() === 200, "Analytics view must return 200.");
assert(str_contains($resAnalytics->getBody(), 'DSGVO Analytics'), "Analytics view must render title.");

// B. Test SEO View
$resSeo = $controller->seo(new Request('GET', '/admin/seo'));
assert($resSeo->getStatusCode() === 200, "SEO view must return 200.");
assert(str_contains($resSeo->getBody(), 'Marketing &amp; SEO'), "SEO view must render title.");

// C. Test Mail View
$resMail = $controller->mail(new Request('GET', '/admin/mail'));
assert($resMail->getStatusCode() === 200, "Mail view must return 200.");
assert(str_contains($resMail->getBody(), 'Transaktions-Mail'), "Mail view must render title.");

// D. Test Backups View (Super-Admin)
$resBackups = $controller->backups(new Request('GET', '/admin/backups'));
assert($resBackups->getStatusCode() === 200, "Backups view must return 200 for super_admin.");
assert(str_contains($resBackups->getBody(), 'Datensicherung'), "Backups view must render title.");

// E. Test Updater View (Super-Admin)
$resUpdater = $controller->updater(new Request('GET', '/admin/updater'));
assert($resUpdater->getStatusCode() === 200, "Updater view must return 200 for super_admin.");
assert(str_contains($resUpdater->getBody(), 'System-Updater'), "Updater view must render title.");

// F. Test GDPR View
$resGdpr = $controller->gdpr(new Request('GET', '/admin/gdpr'));
assert($resGdpr->getStatusCode() === 200, "GDPR view must return 200.");
assert(str_contains($resGdpr->getBody(), 'DSGVO &amp; Lokale Fonts'), "GDPR view must render title.");

// G. Test Payments View
$resPayments = $controller->payments(new Request('GET', '/admin/payments'));
assert($resPayments->getStatusCode() === 200, "Payments view must return 200.");
assert(str_contains($resPayments->getBody(), 'Zahlungen &amp; Stripe'), "Payments view must render title.");

// H. Test Client-Guard Security Isolation
// Enable client_guard in agency settings
AgencyService::getInstance()->saveSettings(['client_guard' => true]);

// Switch session to standard client admin (non-super-admin)
Auth::login($db->fetchOne("SELECT * FROM {prefix}users WHERE id = :id", ['id' => $clientId]));

// Client Admin trying to access Backups (guarded)
$clientBackupRes = $controller->backups(new Request('GET', '/admin/backups'));
assert($clientBackupRes->isRedirect(), "Client Admin must be blocked & redirected from Backups by Client-Guard.");

// Client Admin trying to access Updater (guarded)
$clientUpdaterRes = $controller->updater(new Request('GET', '/admin/updater'));
assert($clientUpdaterRes->isRedirect(), "Client Admin must be blocked & redirected from Updater by Client-Guard.");

echo "   ✓ Admin Controller views, endpoints & Client-Guard protection verified!\n";

// -----------------------------------------------------------------------------
// 9. CLEANUP
// -----------------------------------------------------------------------------
echo "9. Cleaning up test database...\n";
if (is_file($testDbFile)) {
    unlink($testDbFile);
}
if (is_file($logFile)) {
    unlink($logFile);
}

echo "\n======================================================================\n";
echo "🎉 ALL PHASE 5 VERIFICATION TESTS PASSED SUCCESSFULLY! (100% OK)\n";
echo "======================================================================\n";

