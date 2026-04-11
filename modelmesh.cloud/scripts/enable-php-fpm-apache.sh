#!/usr/bin/env bash
set -euo pipefail

# Enable PHP execution for Apache vhosts used by modelmesh.cloud and vigilantvoices.com.
# Run as root: sudo bash /var/www/modelmesh.cloud/scripts/enable-php-fpm-apache.sh

PHP_VERSION="8.3"
PHP_SOCK="/run/php/php${PHP_VERSION}-fpm.sock"
ALT_PHP_SOCK="/run/php/php-fpm.sock"

VHOST_FILES=(
  "/etc/apache2/sites-available/modelmesh.cloud.conf"
  "/etc/apache2/sites-available/modelmesh.cloud-le-ssl.conf"
  "/etc/apache2/sites-available/vigilantvoices.conf"
  "/etc/apache2/sites-available/vigilantvoices.com-le-ssl.conf"
)

echo "[1/7] Installing PHP-FPM and fcgid"
apt-get update
apt-get install -y "php${PHP_VERSION}-fpm" libapache2-mod-fcgid

echo "[2/7] Enabling required Apache modules/conf"
a2enmod proxy proxy_fcgi setenvif
if [[ -f "/etc/apache2/conf-available/php${PHP_VERSION}-fpm.conf" ]]; then
  a2enconf "php${PHP_VERSION}-fpm"
fi

echo "[3/7] Ensuring PHP-FPM service is active"
systemctl enable --now "php${PHP_VERSION}-fpm"

if [[ ! -S "$PHP_SOCK" ]]; then
  if [[ -S "$ALT_PHP_SOCK" ]]; then
    PHP_SOCK="$ALT_PHP_SOCK"
    echo "Using fallback socket: $PHP_SOCK"
  else
    echo "ERROR: Expected PHP-FPM socket not found: $PHP_SOCK or $ALT_PHP_SOCK"
    exit 1
  fi
fi

echo "[4/7] Fixing ModSecurity audit log path for configtest"
mkdir -p /var/log/apache2
touch /var/log/apache2/modsec_audit.log
chown root:adm /var/log/apache2/modsec_audit.log
chmod 640 /var/log/apache2/modsec_audit.log

echo "[5/7] Wiring PHP handler into target vhosts"
for file in "${VHOST_FILES[@]}"; do
  if [[ ! -f "$file" ]]; then
    echo "WARN: vhost file not found: $file"
    continue
  fi

  if grep -q "proxy:unix:${PHP_SOCK}|fcgi://localhost/" "$file"; then
    echo "  - already configured: $file"
    continue
  fi

  tmp_file="$(mktemp)"
  awk -v sock="$PHP_SOCK" '
    /<\/VirtualHost>/ {
      print "    <FilesMatch \\.php$>"
      print "        SetHandler \"proxy:unix:" sock "|fcgi://localhost/\""
      print "    </FilesMatch>"
    }
    { print }
  ' "$file" > "$tmp_file"

  cp "$file" "${file}.bak.$(date +%Y%m%d%H%M%S)"
  cat "$tmp_file" > "$file"
  rm -f "$tmp_file"
  echo "  - updated: $file"
done

echo "[6/7] Apache config test"
apache2ctl configtest

echo "[7/7] Reloading Apache"
systemctl reload apache2

echo
echo "DONE: PHP handler is enabled for target vhosts."
echo "Verify with:"
echo "  curl -I https://modelmesh.cloud/api/ai-gateway-test.php?health=1"
echo "  curl -I https://vigilantvoices.com/api/ai-gateway-test.php?health=1"
