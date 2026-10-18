<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../core/Bootstrap.php';
\Heartbeat\Bootstrap::init();

echo "========================================================\n";
echo "=== WORKER M1: VERIFICATION & AUDIT SUITE ===\n";
echo "========================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertCondition(bool $cond, string $message): void {
    global $passCount, $failCount;
    if ($cond) {
        echo "  [PASS] {$message}\n";
        $passCount++;
    } else {
        echo "  [FAIL] {$message}\n";
        $failCount++;
    }
}

// 1. AdminView.php: Container width
echo "1. Checking AdminView.php Container Width...\n";
$adminViewContent = file_get_contents(__DIR__ . '/../../../core/Admin/AdminView.php');
assertCondition(
    str_contains($adminViewContent, '<main class="flex-1 p-4 sm:p-6 md:p-8 max-w-[1800px] w-full mx-auto">'),
    'AdminView container uses max-w-[1800px]'
);
assertCondition(
    !str_contains($adminViewContent, '<main class="flex-1 p-4 sm:p-6 md:p-8 max-w-7xl w-full mx-auto">'),
    'AdminView container no longer has max-w-7xl bottleneck'
);

// 2. admin.css: Utility and Breakpoints
echo "\n2. Checking public/assets/css/admin.css...\n";
$adminCssContent = file_get_contents(__DIR__ . '/../../../public/assets/css/admin.css');
assertCondition(
    str_contains($adminCssContent, '.max-w-\[1800px\] { max-width: 1800px; }'),
    'admin.css declares .max-w-\[1800px\] utility'
);
assertCondition(
    str_contains($adminCssContent, '.editor-col-3') &&
    str_contains($adminCssContent, 'min-width: 360px !important;'),
    'admin.css declares .editor-col-3 with min-width: 360px !important'
);

// 3. ContentController.php: Collapsible Inspector
echo "\n3. Checking ContentController.php Collapsible Inspector...\n";
$contentCtrl = file_get_contents(__DIR__ . '/../../../core/Admin/ContentController.php');
assertCondition(
    str_contains($contentCtrl, 'hb_inspector_open'),
    'ContentController persists inspector state to localStorage (hb_inspector_open)'
);
assertCondition(
    str_contains($contentCtrl, 'title="Inspektor schließen"'),
    'ContentController has accessible close button in inspector header'
);

// 4. App.php: Route /admin/content
echo "\n4. Checking App.php Route Registration...\n";
$appContent = file_get_contents(__DIR__ . '/../../../core/App.php');
assertCondition(
    str_contains($appContent, "\$this->router->get('/admin/content'"),
    'App.php registers /admin/content route'
);

// 5. MediaController.php: Escaped $event
echo "\n5. Checking MediaController.php heredoc variables...\n";
$mediaCtrl = file_get_contents(__DIR__ . '/../../../core/Admin/MediaController.php');
assertCondition(
    str_contains($mediaCtrl, 'handleDrop(\$event)') && str_contains($mediaCtrl, 'handleFileSelect(\$event)'),
    'MediaController escapes \$event in heredoc strings'
);
assertCondition(
    !str_contains($mediaCtrl, 'handleDrop($event)') && !str_contains($mediaCtrl, 'handleFileSelect($event)'),
    'MediaController does not contain unescaped $event in heredoc strings'
);

// 6. AdminController.php: User modal close fix
echo "\n6. Checking AdminController.php Modal Cancel Button...\n";
$adminCtrl = file_get_contents(__DIR__ . '/../../../core/Admin/AdminController.php');
assertCondition(
    str_contains($adminCtrl, 'onclick="HeartbeatUI.closeModal(\'createUserModal\')" class="px-4 py-2.5 rounded-xl bg-[#141e33] text-xs text-slate-300 hover:text-white"'),
    'createUserModal cancel button uses HeartbeatUI.closeModal'
);

// 7. InstallerController.php: Locked 200 HTTP code
echo "\n7. Checking InstallerController.php Locked Response Code...\n";
$installerCtrl = file_get_contents(__DIR__ . '/../../../core/Installer/InstallerController.php');
assertCondition(
    str_contains($installerCtrl, '$this->renderLockedView(),') && str_contains($installerCtrl, '200'),
    'InstallerController returns 200 on locked view'
);

// 8. Dynamic Route Dispatching: All 8 admin routes return 200
echo "\n8. Dispatching All 8 Admin Routes...\n";
$app = new \Heartbeat\App();
$ref = new ReflectionClass($app);

$routerProp = $ref->getProperty('router');
$routerProp->setAccessible(true);
$router = $routerProp->getValue($app);

// In App.php, /install is registered before core routes
$router->get('/install', [\Heartbeat\Installer\InstallerController::class, 'index']);

$m = $ref->getMethod('registerCoreRoutes');
$m->setAccessible(true);
$m->invoke($app);

\Heartbeat\Auth\Auth::login([
    'id' => 1,
    'username' => 'admin',
    'email' => 'admin@test.local',
    'role' => 'super_admin'
]);

$routes = [
    '/admin',
    '/admin/content',
    '/admin/media',
    '/admin/themes',
    '/admin/seo',
    '/admin/users',
    '/admin/settings',
    '/install',
];

foreach ($routes as $route) {
    try {
        $req = new \Heartbeat\Http\Request('GET', $route);
        $res = $router->dispatch($req);
        $code = $res->getStatusCode();
        assertCondition($code === 200, "Route {$route} returned HTTP {$code}");
    } catch (\Throwable $e) {
        assertCondition(false, "Route {$route} threw exception: " . $e->getMessage());
    }
}

echo "\n--------------------------------------------------------\n";
echo "Results: {$passCount} passed, {$failCount} failed.\n";
if ($failCount === 0) {
    echo "✓ ALL VERIFICATION CHECKS PASSED PERFECTLY!\n";
    exit(0);
} else {
    echo "❌ SOME VERIFICATION CHECKS FAILED!\n";
    exit(1);
}
