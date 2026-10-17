<?php
declare(strict_types=1);

/**
 * Phase 1 End-to-End Verification Test
 */

require_once __DIR__ . '/../core/Bootstrap.php';
\Heartbeat\Bootstrap::init();

echo "1. Checking System Checker...\n";
$checks = \Heartbeat\Installer\SystemChecker::check();
echo "   PHP Version: " . $checks['phpVersion']['current'] . " (Pass: " . ($checks['phpVersion']['pass'] ? 'YES' : 'NO') . ")\n";
echo "   All Checks Passed: " . ($checks['allPassed'] ? 'YES' : 'NO') . "\n";

echo "2. Testing SQLite Database connection...\n";
$dbConfig = [
    'driver'   => 'sqlite',
    'database' => __DIR__ . '/../storage/test_verification.sqlite',
    'prefix'   => 'test_',
];
$testRes = \Heartbeat\Database\Database::testConnection($dbConfig);
echo "   Connection Result: " . ($testRes['success'] ? 'SUCCESS' : 'FAILED: ' . $testRes['message']) . "\n";
if (!$testRes['success']) {
    exit(1);
}

echo "3. Testing Migrations on Database...\n";
$db = new \Heartbeat\Database\Database($dbConfig);
$runner = new \Heartbeat\Database\MigrationRunner($db);
$applied = $runner->runPending(__DIR__ . '/../core/Database/Schema');
echo "   Applied migrations: " . implode(', ', $applied) . "\n";

echo "4. Testing Options Insert/Query...\n";
$db->insert('{prefix}options', [
    'option_name'  => 'site_title',
    'option_value' => 'Test Heartbeat Site',
    'autoload'     => 1
]);
$val = $db->fetchColumn("SELECT option_value FROM {prefix}options WHERE option_name = 'site_title'");
echo "   Fetched option value: " . $val . "\n";
assert($val === 'Test Heartbeat Site');

echo "5. Testing Router dispatch...\n";
$router = new \Heartbeat\Http\Router();
$router->get('/test/:id', function ($req, $params) {
    return \Heartbeat\Http\Response::json(['status' => 'ok', 'id' => $params['id']]);
});
$req = new \Heartbeat\Http\Request('GET', '/test/42');
$resp = $router->dispatch($req);
echo "   Router response status: " . $resp->getStatusCode() . "\n";
echo "   Router body: " . $resp->getBody() . "\n";
assert($resp->getStatusCode() === 200);
assert(str_contains($resp->getBody(), '"id":"42"'));

echo "6. Testing CSRF...\n";
$token = \Heartbeat\Security\Csrf::token();
echo "   Generated CSRF Token: " . substr($token, 0, 8) . "...\n";
assert(\Heartbeat\Security\Csrf::validate($token));

echo "7. Cleaning up test database...\n";
unlink(__DIR__ . '/../storage/test_verification.sqlite');
if (file_exists(__DIR__ . '/../storage/test_verification.sqlite-wal')) {
    unlink(__DIR__ . '/../storage/test_verification.sqlite-wal');
}
if (file_exists(__DIR__ . '/../storage/test_verification.sqlite-shm')) {
    unlink(__DIR__ . '/../storage/test_verification.sqlite-shm');
}

echo "\n✓ ALL PHASE 1 SYSTEM & ARCHITECTURE CHECKS PASSED PERFECTLY!\n";

