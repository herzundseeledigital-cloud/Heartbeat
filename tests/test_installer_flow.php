<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/Bootstrap.php';
\Heartbeat\Bootstrap::init();

echo "Testing Installer Execution...\n";

$payload = [
    'db_driver'       => 'sqlite',
    'app_env'         => 'development',
    'site_title'      => 'Heartbeat Test Site',
    'site_url'        => 'http://localhost:8000',
    'theme'           => 'one-pager',
    'agency_mode'     => true,
    'agency_name'     => 'Herz & Seele Digital',
    'agency_support'  => 'test@example.com',
    'client_guard'    => true,
    'admin_user'      => 'superadmin',
    'admin_email'     => 'admin@heartbeat.test',
    'admin_pass'      => 'SuperSecret123!',
];

$res = \Heartbeat\Installer\InstallerService::runInstallation($payload);
echo "Installation result:\n";
print_r($res);

assert(file_exists(__DIR__ . '/../config/installed.lock'));
assert(file_exists(__DIR__ . '/../config/env.php'));
assert(file_exists(__DIR__ . '/../storage/database.sqlite'));

$dbConfig = [
    'driver'   => 'sqlite',
    'database' => __DIR__ . '/../storage/database.sqlite',
    'prefix'   => 'hb_',
];
$db = new \Heartbeat\Database\Database($dbConfig);
$user = $db->fetchOne("SELECT * FROM hb_users WHERE username = 'superadmin'");
echo "Created user:\n";
print_r($user);
assert($user['role'] === 'super_admin');
assert(password_verify('SuperSecret123!', $user['password_hash']));

$post = $db->fetchOne("SELECT * FROM hb_posts WHERE slug = 'home'");
echo "Created home page:\n";
print_r($post['title']);
assert(!empty($post['content_blocks']));

// Clean up so repository remains in fresh uninstalled state ready for the user
unlink(__DIR__ . '/../config/installed.lock');
unlink(__DIR__ . '/../config/env.php');
unlink(__DIR__ . '/../storage/database.sqlite');
if (file_exists(__DIR__ . '/../storage/database.sqlite-wal')) {
    unlink(__DIR__ . '/../storage/database.sqlite-wal');
}
if (file_exists(__DIR__ . '/../storage/database.sqlite-shm')) {
    unlink(__DIR__ . '/../storage/database.sqlite-shm');
}

echo "\n✓ INSTALLER TEST COMPLETED AND CLEANED UP SUCCESSFULLY!\n";

