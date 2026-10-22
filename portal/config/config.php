<?php
return [
    'db' => [
        'driver' => 'sqlite',
        'host' => 'sql309.infinityfree.com',
        'port' => 3306,
        'database' => 'if0_42825914_mitid',
        'username' => 'if0_42825914',
        'password' => 'Ee1T75755mU',
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
    'app' => [
        'name' => 'MitID Portal',
        'debug' => true,
    ],
];
