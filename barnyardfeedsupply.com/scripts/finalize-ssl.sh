#!/usr/bin/env bash
set -euo pipefail

DOMAIN="barnyardfeedsupply.com"
WWW="www.barnyardfeedsupply.com"
WEBROOT="/var/www/barnyardfeedsupply.com"
EMAIL="ravenell@modelmesh.cloud"

echo "[1/5] Issuing Let's Encrypt cert for ${DOMAIN} + ${WWW}..."
sudo certbot certonly --webroot \
  -w "${WEBROOT}" \
  -d "${DOMAIN}" \
  -d "${WWW}" \
  --non-interactive --agree-tos \
  --email "${EMAIL}" \
  --keep-until-expiring

echo "[2/5] Enabling SSL vhost..."
sudo a2enmod ssl headers >/dev/null || true
sudo a2ensite barnyardfeedsupply.com-le-ssl.conf

echo "[3/5] Validating Apache config..."
sudo apache2ctl configtest

echo "[4/5] Reloading Apache..."
sudo systemctl reload apache2

echo "[5/5] Validating redirect + HTTPS + cert path..."
curl -sS -I "http://${DOMAIN}/" | sed -n '1,5p'
curl -k -sS -I "https://${DOMAIN}/" | sed -n '1,8p'

if [[ -f /etc/letsencrypt/live/${DOMAIN}/fullchain.pem ]]; then
  echo "Certificate installed: /etc/letsencrypt/live/${DOMAIN}/fullchain.pem"
fi

echo "Done."
