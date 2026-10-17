<?php
declare(strict_types=1);

/**
 * Phase 2 End-to-End Verification Test
 * Tests Security Core, Session, RateLimiter, RBAC, Client-Guard, Auth & Admin Shell
 */

require_once __DIR__ . '/../core/Bootstrap.php';
\Heartbeat\Bootstrap::init();

use Heartbeat\Security\Session;
use Heartbeat\Security\RateLimiter;
use Heartbeat\Security\Rbac;
use Heartbeat\Security\Security;
use Heartbeat\Security\Csrf;
use Heartbeat\Agency\AgencyService;
use Heartbeat\Auth\Auth;
use Heartbeat\Database\Database;
use Heartbeat\Database\MigrationRunner;
use Heartbeat\Http\Request;
use Heartbeat\Http\Response;
use Heartbeat\Http\Router;
use Heartbeat\Admin\AdminController;
use Heartbeat\Admin\AdminView;

echo "=== PHASE 2: SECURITY CORE, RBAC, AGENCY MODE & ADMIN SHELL TEST ===\n\n";

// -----------------------------------------------------------------------------
// 1. SESSION & CSRF VERIFICATION
// -----------------------------------------------------------------------------
echo "1. Testing Session & CSRF Core...\n";

Session::set('test_key', 'test_value_123');
assert(Session::get('test_key') === 'test_value_123');
assert(Session::has('test_key') === true);
Session::remove('test_key');
assert(Session::get('test_key') === null);

// Flash messages
Session::flash('success', 'Aktion erfolgreich');
Session::flash('error', 'Ein Fehler ist aufgetreten');
$flashes = Session::getFlashes();
assert(count($flashes) === 2);
assert($flashes[0]['type'] === 'success');
assert($flashes[1]['type'] === 'error');
assert(count(Session::getFlashes()) === 0, 'Flashes should be cleared after retrieval');

// CSRF tokens
$token = Csrf::token();
assert(is_string($token) && strlen($token) === 64, 'Token must be 64-char hex string');
assert(Csrf::validate($token) === true);
assert(Csrf::validate('invalid-token') === false);

$field = Csrf::field();
assert(str_contains($field, 'name="_csrf_token"'));
assert(str_contains($field, $token));

echo "   ✓ Session, Flash messaging, and CSRF token generation/validation verified!\n";

// -----------------------------------------------------------------------------
// 2. RATE LIMITER VERIFICATION
// -----------------------------------------------------------------------------
echo "2. Testing RateLimiter...\n";

$rateLimiter = new RateLimiter();
$testRateKey = 'test_ip_127_0_0_1';
$rateLimiter->clear($testRateKey);

assert($rateLimiter->attempts($testRateKey) === 0);
assert($rateLimiter->tooManyAttempts($testRateKey, 3) === false);

$rateLimiter->hit($testRateKey, 60);
$rateLimiter->hit($testRateKey, 60);
assert($rateLimiter->attempts($testRateKey) === 2);
assert($rateLimiter->remaining($testRateKey, 3) === 1);
assert($rateLimiter->tooManyAttempts($testRateKey, 3) === false);

$rateLimiter->hit($testRateKey, 60);
assert($rateLimiter->attempts($testRateKey) === 3);
assert($rateLimiter->remaining($testRateKey, 3) === 0);
assert($rateLimiter->tooManyAttempts($testRateKey, 3) === true);
assert($rateLimiter->availableIn($testRateKey) > 0);

$rateLimiter->clear($testRateKey);
assert($rateLimiter->attempts($testRateKey) === 0);
assert($rateLimiter->tooManyAttempts($testRateKey, 3) === false);

echo "   ✓ RateLimiter attempts, decay window, lockouts and clearance verified!\n";

// -----------------------------------------------------------------------------
// 3. RBAC & CLIENT-GUARD VERIFICATION
// -----------------------------------------------------------------------------
echo "3. Testing RBAC Matrix & Client-Guard Isolation...\n";

// 3.1 Super-Admin has all permissions regardless of Client-Guard
assert(Rbac::can(Rbac::ROLE_SUPER_ADMIN, 'access_admin', true) === true);
assert(Rbac::can(Rbac::ROLE_SUPER_ADMIN, 'manage_agency', true) === true);
assert(Rbac::can(Rbac::ROLE_SUPER_ADMIN, 'manage_code', true) === true);
assert(Rbac::can(Rbac::ROLE_SUPER_ADMIN, 'update_core', true) === true);
assert(Rbac::can(Rbac::ROLE_SUPER_ADMIN, 'manage_backups', true) === true);
assert(Rbac::can(Rbac::ROLE_SUPER_ADMIN, 'manage_users', true) === true);

// 3.2 Admin role with Client-Guard ACTIVE (Default agency mode)
assert(Rbac::can(Rbac::ROLE_ADMIN, 'access_admin', true) === true);
assert(Rbac::can(Rbac::ROLE_ADMIN, 'manage_users', true) === true);
assert(Rbac::can(Rbac::ROLE_ADMIN, 'manage_options', true) === true);
assert(Rbac::can(Rbac::ROLE_ADMIN, 'manage_content', true) === true);
// Client-Guard MUST lock these destructive areas:
assert(Rbac::can(Rbac::ROLE_ADMIN, 'manage_agency', true) === false, 'Admin must not manage agency');
assert(Rbac::can(Rbac::ROLE_ADMIN, 'manage_code', true) === false, 'Client-Guard must lock custom code');
assert(Rbac::can(Rbac::ROLE_ADMIN, 'update_core', true) === false, 'Client-Guard must lock core updater');
assert(Rbac::can(Rbac::ROLE_ADMIN, 'manage_backups', true) === false, 'Client-Guard must lock raw backups');

// 3.3 Admin role with Client-Guard INACTIVE
assert(Rbac::can(Rbac::ROLE_ADMIN, 'manage_code', false) === true);
assert(Rbac::can(Rbac::ROLE_ADMIN, 'update_core', false) === true);
assert(Rbac::can(Rbac::ROLE_ADMIN, 'manage_backups', false) === true);
assert(Rbac::can(Rbac::ROLE_ADMIN, 'manage_agency', false) === false, 'Admin can never edit agency white-label');

// 3.4 Editor role
assert(Rbac::can(Rbac::ROLE_EDITOR, 'access_admin') === true);
assert(Rbac::can(Rbac::ROLE_EDITOR, 'manage_content') === true);
assert(Rbac::can(Rbac::ROLE_EDITOR, 'manage_media') === true);
assert(Rbac::can(Rbac::ROLE_EDITOR, 'manage_users') === false, 'Editor cannot manage users');
assert(Rbac::can(Rbac::ROLE_EDITOR, 'manage_options') === false, 'Editor cannot manage options');

// 3.5 User role
assert(Rbac::can(Rbac::ROLE_USER, 'access_admin') === true);
assert(Rbac::can(Rbac::ROLE_USER, 'edit_posts') === true);
assert(Rbac::can(Rbac::ROLE_USER, 'manage_content') === false, 'User cannot manage all content/layouts');
assert(Rbac::can(Rbac::ROLE_USER, 'manage_users') === false);

// 3.6 Member role (Frontend Only)
assert(Rbac::can(Rbac::ROLE_MEMBER, 'access_admin') === false, 'Member must never access admin panel');
assert(Rbac::can(Rbac::ROLE_MEMBER, 'access_member_content') === true);

// 3.7 Role Hierarchy Management
assert(Rbac::canManageRole(Rbac::ROLE_SUPER_ADMIN, Rbac::ROLE_ADMIN) === true);
assert(Rbac::canManageRole(Rbac::ROLE_SUPER_ADMIN, Rbac::ROLE_SUPER_ADMIN) === true);
assert(Rbac::canManageRole(Rbac::ROLE_ADMIN, Rbac::ROLE_EDITOR) === true);
assert(Rbac::canManageRole(Rbac::ROLE_ADMIN, Rbac::ROLE_USER) === true);
assert(Rbac::canManageRole(Rbac::ROLE_ADMIN, Rbac::ROLE_SUPER_ADMIN) === false, 'Admin cannot manage Super-Admin');

// 3.8 Role Assignment Limits
assert(Rbac::canAssignRole(Rbac::ROLE_SUPER_ADMIN, Rbac::ROLE_SUPER_ADMIN) === true);
assert(Rbac::canAssignRole(Rbac::ROLE_ADMIN, Rbac::ROLE_EDITOR) === true);
assert(Rbac::canAssignRole(Rbac::ROLE_ADMIN, Rbac::ROLE_SUPER_ADMIN) === false, 'Admin cannot assign Super-Admin role');

echo "   ✓ RBAC 5-role matrix, Client-Guard isolation and role hierarchy verified!\n";

// -----------------------------------------------------------------------------
// 4. DATABASE & AUTHENTICATION END-TO-END
// -----------------------------------------------------------------------------
echo "4. Testing Database Auth, Password Hashing & Login Lifecycle...\n";

$testDbPath = __DIR__ . '/../storage/phase2_test.sqlite';
if (file_exists($testDbPath)) {
    unlink($testDbPath);
}

$dbConfig = [
    'driver'   => 'sqlite',
    'database' => $testDbPath,
    'prefix'   => 'hb_',
];

$db = new Database($dbConfig);
Database::setInstance($db);

$runner = new MigrationRunner($db);
$runner->runPending(__DIR__ . '/../core/Database/Schema');

// Seed agency options
$db->insert('{prefix}options', ['option_name' => 'site_title', 'option_value' => 'Heartbeat CMS', 'autoload' => 1]);
$db->insert('{prefix}options', ['option_name' => 'site_url', 'option_value' => 'http://localhost:8000', 'autoload' => 1]);
$db->insert('{prefix}options', ['option_name' => 'agency_mode', 'option_value' => '1', 'autoload' => 1]);
$db->insert('{prefix}options', ['option_name' => 'agency_name', 'option_value' => 'Herz & Seele Digital', 'autoload' => 1]);
$db->insert('{prefix}options', ['option_name' => 'agency_support_email', 'option_value' => 'hallo@herzundseele-digital.de', 'autoload' => 1]);
$db->insert('{prefix}options', ['option_name' => 'client_guard', 'option_value' => '1', 'autoload' => 1]);

// Password hashing
$password = 'Secret123!';
$hash = Security::hashPassword($password);
assert(Security::verifyPassword($password, $hash) === true);
assert(Security::verifyPassword('WrongPass', $hash) === false);

// Seed Super Admin user
$superAdminId = (int)$db->insert('{prefix}users', [
    'username'      => 'agency_boss',
    'email'         => 'boss@agency.de',
    'password_hash' => $hash,
    'role'          => Rbac::ROLE_SUPER_ADMIN,
    'status'        => 'active',
    'created_at'    => date('Y-m-d H:i:s'),
    'updated_at'    => date('Y-m-d H:i:s'),
]);

// Seed Member user (frontend only)
$memberId = (int)$db->insert('{prefix}users', [
    'username'      => 'test_member',
    'email'         => 'member@domain.de',
    'password_hash' => $hash,
    'role'          => Rbac::ROLE_MEMBER,
    'status'        => 'active',
    'created_at'    => date('Y-m-d H:i:s'),
    'updated_at'    => date('Y-m-d H:i:s'),
]);

// Test Auth::attempt with invalid credentials
assert(Auth::attempt('agency_boss', 'WrongPassword') === false);
assert(Auth::attempt('unknown_user', $password) === false);
assert(Auth::check() === false);

// Test Auth::attempt with valid credentials
assert(Auth::attempt('agency_boss', $password) === true);
assert(Auth::check() === true);
assert(Auth::id() === $superAdminId);
assert(Auth::role() === Rbac::ROLE_SUPER_ADMIN);
assert(Auth::isSuperAdmin() === true);
assert(Auth::can('manage_agency') === true);
assert(Auth::can('manage_code') === true);

// Test Auth::logout
Auth::logout();
assert(Auth::check() === false);
assert(Auth::user() === null);

echo "   ✓ Authentication lifecycle (attempt, verify, session binding, logout) verified!\n";

// -----------------------------------------------------------------------------
// 5. AGENCY MODE & CLIENT-GUARD SERVICE
// -----------------------------------------------------------------------------
echo "5. Testing Agency Mode & White-Label Service...\n";

$agency = AgencyService::getInstance();
assert($agency->isWhiteLabel() === true);
assert($agency->getAgencyName() === 'Herz & Seele Digital');
assert($agency->getSupportEmail() === 'hallo@herzundseele-digital.de');
assert($agency->isClientGuardActive() === true);

$widgetHtml = $agency->renderSupportWidget();
assert(str_contains($widgetHtml, 'Herz &amp; Seele Digital') || str_contains($widgetHtml, 'Herz & Seele Digital'));
assert(str_contains($widgetHtml, 'Client-Guard: Aktiv'));
assert(str_contains($widgetHtml, 'hallo@herzundseele-digital.de'));

echo "   ✓ Agency White-Label & Support widget rendering verified!\n";

// -----------------------------------------------------------------------------
// 6. ADMIN CONTROLLER & UI SHELL ENDPOINTS
// -----------------------------------------------------------------------------
echo "6. Testing Admin Controller & Views...\n";

$controller = new AdminController();

// 6.1 Login view when guest
$loginReq = new Request('GET', '/admin/login');
$loginResp = $controller->login($loginReq);
assert($loginResp->getStatusCode() === 200);
assert(str_contains($loginResp->getBody(), 'Heartbeat Admin'));
assert(str_contains($loginResp->getBody(), 'Benutzername oder E-Mail'));

// 6.2 Login attempt with valid credentials
$csrfToken = Csrf::token();
$postLoginReq = new Request('POST', '/admin/login', [], [
    '_csrf_token' => $csrfToken,
    'username'    => 'agency_boss',
    'password'    => $password,
]);
$doLoginResp = $controller->doLogin($postLoginReq);
assert($doLoginResp->getStatusCode() === 302);
assert($doLoginResp->getBody() === '');
assert(Auth::check() === true);

// 6.3 Dashboard view when authenticated
$dashReq = new Request('GET', '/admin');
$dashResp = $controller->dashboard($dashReq);
assert($dashResp->getStatusCode() === 200);
assert(str_contains($dashResp->getBody(), 'Willkommen im Kontrollzentrum'));
assert(str_contains($dashResp->getBody(), 'Seiten &amp; Inhalte'));
assert(str_contains($dashResp->getBody(), 'Heartbeat CMS'));
assert(str_contains($dashResp->getBody(), 'Herz &amp; Seele Digital') || str_contains($dashResp->getBody(), 'Herz & Seele Digital'));

// 6.4 Users view
$usersReq = new Request('GET', '/admin/users');
$usersResp = $controller->users($usersReq);
assert($usersResp->getStatusCode() === 200);
assert(str_contains($usersResp->getBody(), 'Benutzer &amp; Rollen'));
assert(str_contains($usersResp->getBody(), 'agency_boss'));

// 6.5 User creation via Controller
$csrfToken = Csrf::token();
$createUserReq = new Request('POST', '/admin/users/create', [], [
    '_csrf_token' => $csrfToken,
    'username'    => 'client_editor',
    'email'       => 'editor@client.de',
    'password'    => 'EditorSecure2026!',
    'role'        => 'editor',
    'status'      => 'active',
]);
$createUserResp = $controller->createUser($createUserReq);
assert($createUserResp->getStatusCode() === 302);

$createdUser = $db->fetchOne("SELECT * FROM {prefix}users WHERE username = 'client_editor'");
assert($createdUser !== null);
assert($createdUser['email'] === 'editor@client.de');
assert($createdUser['role'] === 'editor');
assert(Security::verifyPassword('EditorSecure2026!', $createdUser['password_hash']) === true);

// 6.6 Settings view
$settingsReq = new Request('GET', '/admin/settings');
$settingsResp = $controller->settings($settingsReq);
assert($settingsResp->getStatusCode() === 200);
assert(str_contains($settingsResp->getBody(), 'Allgemeine Website-Einstellungen'));
assert(str_contains($settingsResp->getBody(), 'Agentur &amp; White-Label'));

// 6.7 System Status view
$systemReq = new Request('GET', '/admin/system');
$systemResp = $controller->system($systemReq);
assert($systemResp->getStatusCode() === 200);
assert(str_contains($systemResp->getBody(), 'Laufzeit- &amp; Server-Umgebung'));
assert(str_contains($systemResp->getBody(), 'Cache jetzt leeren'));

// 6.8 Clear Cache
$csrfToken = Csrf::token();
$clearCacheReq = new Request('POST', '/admin/system/clear-cache', [], ['_csrf_token' => $csrfToken]);
$clearCacheResp = $controller->clearCache($clearCacheReq);
assert($clearCacheResp->getStatusCode() === 302);

// 6.9 Placeholders for Phase 3 & 4
$placeholderReq = new Request('GET', '/admin/pages');
$placeholderResp = $controller->placeholder($placeholderReq, 'Seiten & Inhalte', 'Phase 3: Flexible Content Engine');
assert($placeholderResp->getStatusCode() === 200);
assert(str_contains($placeholderResp->getBody(), 'Phase 3: Flexible Content Engine'));

// 6.10 Logout
$csrfToken = Csrf::token();
$logoutReq = new Request('POST', '/admin/logout', [], ['_csrf_token' => $csrfToken]);
$logoutResp = $controller->logout($logoutReq);
assert($logoutResp->getStatusCode() === 302);
assert(Auth::check() === false);

echo "   ✓ All Admin Shell routes, views, widgets, and user CRUD operations verified!\n";

// -----------------------------------------------------------------------------
// 7. CLEANUP
// -----------------------------------------------------------------------------
echo "7. Cleaning up test artifacts...\n";
if (file_exists($testDbPath)) {
    unlink($testDbPath);
}
if (file_exists($testDbPath . '-wal')) {
    unlink($testDbPath . '-wal');
}
if (file_exists($testDbPath . '-shm')) {
    unlink($testDbPath . '-shm');
}

echo "\n========================================================================\n";
echo "✓ ALL PHASE 2 SECURITY CORE, RBAC, AGENCY MODE & ADMIN SHELL TESTS PASSED!\n";
echo "========================================================================\n";

