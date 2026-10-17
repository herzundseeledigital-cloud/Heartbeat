<?php
declare(strict_types=1);

/**
 * Heartbeat CMS - Environment Configuration Template
 * Copy this file to config/env.php or let the Web Installer generate it.
 */

return [
    'app' => [
        'name'      => 'Heartbeat CMS',
        'key'       => 'base64:GENERATE_YOUR_SECURE_KEY_HERE',
        'env'       => 'production', // 'development' or 'production'
        'debug'     => false,
        'url'       => 'https://your-domain.de',
        'timezone'  => 'Europe/Berlin',
        'locale'    => 'de',
    ],

    'database' => [
        'driver'    => 'mysql', // 'mysql' or 'sqlite'
        'host'      => '127.0.0.1',
        'port'      => 3306,
        'database'  => 'heartbeat_db',
        'username'  => 'heartbeat_user',
        'password'  => 'your_secure_password',
        'prefix'    => 'hb_',
        'charset'   => 'utf8mb4',
    ],

    'agency' => [
        'white_label'   => true,
        'name'          => 'Herz & Seele Digital',
        'support_email' => 'hallo@herzundseele-digital.de',
        'client_guard'  => true, // Locks destructive system settings from non-super admins
    ],

    'mail' => [
        'driver'        => 'resend', // 'resend' or 'smtp'
        'resend_api_key'=> 're_123456789',
        'from_address'  => 'info@your-domain.de',
        'from_name'     => 'Heartbeat Mailer',
        'smtp' => [
            'host'      => 'smtp.webgo.de',
            'port'      => 587,
            'encryption'=> 'tls',
            'username'  => 'user@your-domain.de',
            'password'  => 'smtp_password',
        ],
    ],

    'cache' => [
        'page_cache'    => true,
        'ttl_seconds'   => 3600,
        'driver'        => 'file', // 'file', 'apcu', 'redis'
    ],
];

