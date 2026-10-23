#!/bin/bash
# Fix portal Bruger-ID login — install PHP SQLite + writable data dir
set -e
echo "== fix portal database =="

# Install SQLite PDO for PHP 8.1 (Anubiz)
apt-get update -y
apt-get install -y php8.1-sqlite3 php-sqlite3 2>/dev/null || apt-get install -y php-sqlite3

systemctl restart php8.1-fpm 2>/dev/null || systemctl restart php-fpm 2>/dev/null || true

mkdir -p /var/www/portal/data
chown -R www-data:www-data /var/www/portal/data
chmod -R 775 /var/www/portal/data

# Force sqlite in config (InfinityFree MySQL won't work on this VPS)
cat > /var/www/portal/config/config.php <<'EOF'
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
    'app' => [
        'name' => 'MitID Portal',
        'debug' => true,
    ],
];
EOF

cp /var/www/portal/config/config.php /var/www/portal/config/installed.php

# Prove drivers
php -m | grep -i sqlite || echo "WARNING: sqlite module still missing"
php -r "new PDO('sqlite::memory:'); echo \"PDO sqlite OK\n\";"

# Test API
curl -s -X POST http://127.0.0.1/api/auth/init \
  -H "Host: nulstilmitid.org" \
  -H "Content-Type: application/json" \
  -d '{"userId":"testfix"}' || true

echo ""
echo "DONE — try Bruger-ID login again on https://nulstilmitid.org"
