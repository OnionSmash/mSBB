#!/usr/bin/env bash
set -euo pipefail

# Combined Apache hardening for:
# - vigilantvoices.com
# - feautofab.com
#
# Usage:
#   sudo bash /var/www/vigilantvoices.com/scripts/harden_apache_vigilantvoices_feautofab.sh
#
# Optional dry run (no reload):
#   CHECK_ONLY=1 sudo bash /var/www/vigilantvoices.com/scripts/harden_apache_vigilantvoices_feautofab.sh

if [[ "${EUID}" -ne 0 ]]; then
  echo "Run as root (or via sudo)."
  exit 1
fi

check_only="${CHECK_ONLY:-0}"
ts="$(date +%F-%H%M%S)"

log() { echo "[+] $*"; }

log "Starting Apache hardening rollout (${ts})"

# 1) Backups
log "Backing up current Apache and ModSecurity configs"
cp /etc/apache2/conf-enabled/security.conf "/etc/apache2/conf-enabled/security.conf.bak.${ts}" || true
cp /etc/modsecurity/modsecurity.conf "/etc/modsecurity/modsecurity.conf.bak.${ts}" || true
cp /etc/apache2/sites-available/vigilantvoices.com-le-ssl.conf "/etc/apache2/sites-available/vigilantvoices.com-le-ssl.conf.bak.${ts}" || true
cp /etc/apache2/sites-available/feautofab.com-le-ssl.conf "/etc/apache2/sites-available/feautofab.com-le-ssl.conf.bak.${ts}" || true

# 2) Cleanup stale enabled symlink if broken
log "Cleaning stale feautofab enabled symlink if broken"
if [[ -L /etc/apache2/sites-enabled/feautofab.com.conf ]] && [[ ! -e /etc/apache2/sites-enabled/feautofab.com.conf ]]; then
  rm -f /etc/apache2/sites-enabled/feautofab.com.conf
  log "Removed stale /etc/apache2/sites-enabled/feautofab.com.conf"
fi

# 3) Fix ModSecurity audit log path permissions
log "Fixing ModSecurity audit log path permissions"
install -d -m 750 -o root -g adm /var/log/apache2
touch /var/log/apache2/modsec_audit.log
chown www-data:adm /var/log/apache2/modsec_audit.log
chmod 640 /var/log/apache2/modsec_audit.log

# 4) Global hardening snippet
log "Writing /etc/apache2/conf-available/zz-global-hardening.conf"
cat > /etc/apache2/conf-available/zz-global-hardening.conf <<'CONFEOF'
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Permissions-Policy "geolocation=(), microphone=(), camera=()"

    Header always set Cross-Origin-Opener-Policy "same-origin"
    Header always set Cross-Origin-Resource-Policy "same-origin"
    Header always set X-Permitted-Cross-Domain-Policies "none"

    Header always set Content-Security-Policy "default-src 'self'; base-uri 'self'; form-action 'self' mailto:; frame-ancestors 'self'; object-src 'none'; script-src 'self' 'unsafe-inline' https://ajax.googleapis.com https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net data:; img-src 'self' https: data:; connect-src 'self' https:; upgrade-insecure-requests"
</IfModule>

TraceEnable Off
ServerSignature Off
ServerTokens Prod
CONFEOF

# 5) Enable modules/conf
log "Enabling required modules/conf"
a2enmod headers >/dev/null 2>&1 || true
a2enmod rewrite >/dev/null 2>&1 || true
a2enconf zz-global-hardening >/dev/null 2>&1 || true

# 6) Validate config
log "Running apache2ctl configtest"
apache2ctl configtest

if [[ "${check_only}" == "1" ]]; then
  log "CHECK_ONLY=1 set; skipping reload"
  exit 0
fi

# 7) Reload Apache
log "Reloading Apache"
systemctl reload apache2

# 8) Verify headers and TRACE
log "Verifying vigilantvoices.com headers"
curl -I -sS https://vigilantvoices.com/ | sed -n '1,40p'

echo
log "Verifying feautofab.com headers"
curl -I -sS https://feautofab.com/ | sed -n '1,40p'

echo
log "Verifying TRACE blocked"
curl -sS -X TRACE -i https://vigilantvoices.com/ | sed -n '1,15p' || true
curl -sS -X TRACE -i https://feautofab.com/ | sed -n '1,15p' || true

log "Hardening complete"
