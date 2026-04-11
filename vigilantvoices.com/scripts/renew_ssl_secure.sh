#!/usr/bin/env bash
set -euo pipefail

# Secure renewal flow:
# - cert/key material stays in /etc/letsencrypt (outside docroot)
# - only work/logs are site-local for operational isolation

DOMAIN="vigilantvoices.com"
ALT_DOMAIN="www.vigilantvoices.com"
WEBROOT="/var/www/vigilantvoices.com"
LE_CONFIG="/etc/letsencrypt"
SITE_SSL="/var/tmp/vigilantvoices.com/ssl"
WORK_DIR="${SITE_SSL}/work"
LOGS_DIR="${SITE_SSL}/logs"

mkdir -p "${WORK_DIR}" "${LOGS_DIR}"

sudo certbot certonly \
  --webroot -w "${WEBROOT}" \
  -d "${DOMAIN}" -d "${ALT_DOMAIN}" \
  --config-dir "${LE_CONFIG}" \
  --work-dir "${WORK_DIR}" \
  --logs-dir "${LOGS_DIR}" \
  --agree-tos \
  --non-interactive \
  --keep-until-expiring

sudo apache2ctl configtest
sudo systemctl reload apache2

echo "Renewal complete. Active cert path: /etc/letsencrypt/live/${DOMAIN}/fullchain.pem"
