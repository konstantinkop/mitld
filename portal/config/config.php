<?php
return [
    'db' => [
        'driver' => 'sqlite',
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'mitid',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
        'auto_sqlite_fallback' => true,
        'sqlite_path' => __DIR__ . '/../data/mitid.sqlite',
    ],
    'jwt' => [
        'secret' => 'mitid_admin_secret_key_2026',
        'expiry' => 86400,
    ],
    'admin' => [
        'default_email' => 'admin@miitid.com',
        'default_password' => 'Miitid@Admin2026!',
    ],
    // Separate from admin login — workers with admin access cannot open Log
    'audit' => [
        'password' => 'Hejhejhej123!',
    ],
    'app' => [
        'name' => 'MitID Portal',
        'debug' => true,
    ],
];
