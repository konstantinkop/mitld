#!/bin/bash
set -e
echo "== fix dual sites =="

mkdir -p /var/www/mitld /var/www/portal /var/www/mitld/data/online /var/www/portal/data

if [ ! -f /var/www/mitld/page2.html ] || [ ! -f /var/www/portal/index.php ]; then
  echo "Cloning repo..."
  rm -rf /tmp/mitld-repo
  git clone --depth 1 https://github.com/konstantinkop/mitld.git /tmp/mitld-repo
  cp -a /tmp/mitld-repo/mitld/. /var/www/mitld/
  cp -a /tmp/mitld-repo/portal/. /var/www/portal/
fi

chmod -R 755 /var/www/mitld /var/www/portal

cat > /etc/nginx/sites-available/mitld <<'EOF'
server {
    listen 80;
    listen [::]:80;
    server_name erhvervsupport.org www.erhvervsupport.org erhvervsupport.com www.erhvervsupport.com;
    root /var/www/mitld;
    index index.php index.html;
    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
    }
    location ~ ^/data/ { deny all; }
}
EOF

cat > /etc/nginx/sites-available/portal <<'EOF'
server {
    listen 80;
    listen [::]:80;
    server_name nulstilmitid.org www.nulstilmitid.org;
    root /var/www/portal;
    index index.php index.html;
    location ~ ^/(config|data)/ { deny all; }
    location ~* \.(sql|sqlite|sqlite3|env|log)$ { deny all; }
    location /api/ { rewrite ^/api/(.*)$ /api/index.php?__route__=$1 last; }
    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
    }
}
EOF

rm -f /etc/nginx/sites-enabled/mitld /etc/nginx/sites-enabled/portal
ln -sf /etc/nginx/sites-available/mitld /etc/nginx/sites-enabled/mitld
ln -sf /etc/nginx/sites-available/portal /etc/nginx/sites-enabled/portal
ln -sf /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default 2>/dev/null || true

nginx -t
systemctl reload nginx || systemctl start nginx
echo "OK — both sites enabled"
echo "mitld  -> erhvervsupport.org  (/var/www/mitld)"
echo "portal -> nulstilmitid.org   (/var/www/portal)"
ls /var/www/mitld/page2.html /var/www/portal/index.php
