<?php
declare(strict_types=1);

/**
 * Heartbeat CMS - Public Front Controller
 */

// Load Core Bootstrap
require_once dirname(__DIR__) . '/core/Bootstrap.php';

// Initialize System
\Heartbeat\Bootstrap::init();

// Run Application Kernel
$app = new \Heartbeat\App();
$app->run();

