#!/usr/bin/env bash
set -euo pipefail

# Cleans duplicate security header directives and applies a stricter managed policy.
# Targets:
# - vigilantvoices.com
# - feautofab.com
#
# Usage:
#   sudo bash /var/www/vigilantvoices.com/scripts/harden_apache_headers_cleanup_v2.sh
#
# Optional dry-run (no reload):
#   CHECK_ONLY=1 sudo bash /var/www/vigilantvoices.com/scripts/harden_apache_headers_cleanup_v2.sh

if [[ "${EUID}" -ne 0 ]]; then
  echo "Run as root (or via sudo)."
  exit 1
fi

check_only="${CHECK_ONLY:-0}"
ts="$(date +%F-%H%M%S)"
managed_conf="/etc/apache2/conf-available/zz-global-hardening-v2.conf"

log() { echo "[+] $*"; }
warn() { echo "[!] $*"; }

backup_once() {
  local f="$1"
  local b="${f}.bak.${ts}"
  if [[ -f "$f" && ! -f "$b" ]]; then
    cp "$f" "$b"
  fi
}

clean_header_directives_in_file() {
  local f="$1"
  # Remove duplicate/legacy header directives to keep policy managed in one place.
  sed -i -E \
    -e '/^[[:space:]]*Header[[:space:]]+(always[[:space:]]+)?(set|append|add|merge)[[:space:]]+X-Content-Type-Options[[:space:]]+/Id' \
    -e '/^[[:space:]]*Header[[:space:]]+(always[[:space:]]+)?(set|append|add|merge)[[:space:]]+X-Frame-Options[[:space:]]+/Id' \
    -e '/^[[:space:]]*Header[[:space:]]+(always[[:space:]]+)?(set|append|add|merge)[[:space:]]+Referrer-Policy[[:space:]]+/Id' \
    -e '/^[[:space:]]*Header[[:space:]]+(always[[:space:]]+)?(set|append|add|merge)[[:space:]]+Permissions-Policy[[:space:]]+/Id' \
    -e '/^[[:space:]]*Header[[:space:]]+(always[[:space:]]+)?(set|append|add|merge)[[:space:]]+Cross-Origin-Opener-Policy[[:space:]]+/Id' \
    -e '/^[[:space:]]*Header[[:space:]]+(always[[:space:]]+)?(set|append|add|merge)[[:space:]]+Cross-Origin-Resource-Policy[[:space:]]+/Id' \
    -e '/^[[:space:]]*Header[[:space:]]+(always[[:space:]]+)?(set|append|add|merge)[[:space:]]+X-Permitted-Cross-Domain-Policies[[:space:]]+/Id' \
    -e '/^[[:space:]]*Header[[:space:]]+(always[[:space:]]+)?(set|append|add|merge)[[:space:]]+Content-Security-Policy[[:space:]]+/Id' \
    -e '/^[[:space:]]*Header[[:space:]]+(always[[:space:]]+)?(set|append|add|merge)[[:space:]]+X-XSS-Protection[[:space:]]+/Id' \
    "$f"
}

log "Starting header cleanup + hardening v2 (${ts})"

# Backup and disable previous managed conf (if present)
if [[ -f /etc/apache2/conf-available/zz-global-hardening.conf ]]; then
  backup_once /etc/apache2/conf-available/zz-global-hardening.conf
  a2disconf zz-global-hardening >/dev/null 2>&1 || true
fi

# Remove overlapping directives from common Apache conf locations
log "Cleaning overlapping header directives in Apache config files"
while IFS= read -r f; do
  [[ "$f" == "$managed_conf" ]] && continue
  backup_once "$f"
  clean_header_directives_in_file "$f"
done < <(find /etc/apache2 \( -path '/etc/apache2/conf-available/*.conf' -o -path '/etc/apache2/conf-enabled/*.conf' -o -path '/etc/apache2/sites-available/*.conf' \) -type f)

# Write a single managed policy conf with explicit unsets to avoid duplicates
log "Writing managed hardening config: ${managed_conf}"
cat > "$managed_conf" <<'CONFEOF'
<IfModule mod_headers.c>
    # Explicitly clear any inherited values so each header is emitted once.
    Header always unset X-Content-Type-Options
    Header always unset X-Frame-Options
    Header always unset Referrer-Policy
    Header always unset Permissions-Policy
    Header always unset Cross-Origin-Opener-Policy
    Header always unset Cross-Origin-Resource-Policy
    Header always unset X-Permitted-Cross-Domain-Policies
    Header always unset Content-Security-Policy
    Header always unset X-XSS-Protection

    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    Header always set Permissions-Policy "geolocation=(), microphone=(), camera=()"
    Header always set Cross-Origin-Opener-Policy "same-origin"
    Header always set Cross-Origin-Resource-Policy "same-origin"
    Header always set X-Permitted-Cross-Domain-Policies "none"

    # Tightened CSP vs prior baseline: removed script 'unsafe-inline' and broad https: for img/connect.
    Header always set Content-Security-Policy "default-src 'self'; base-uri 'self'; form-action 'self' mailto:; frame-ancestors 'self'; object-src 'none'; script-src 'self' https://ajax.googleapis.com https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net data:; img-src 'self' data: https://vigilantvoices.com https://feautofab.com; connect-src 'self'; upgrade-insecure-requests"
</IfModule>

TraceEnable Off
ServerSignature Off
ServerTokens Prod
CONFEOF

log "Enabling headers module + managed config"
a2enmod headers >/dev/null 2>&1 || true
a2enconf zz-global-hardening-v2 >/dev/null 2>&1 || true

log "Running apache2ctl configtest"
apache2ctl configtest

if [[ "$check_only" == "1" ]]; then
  warn "CHECK_ONLY=1 enabled; skipping reload and live verification"
  exit 0
fi

log "Reloading Apache"
systemctl reload apache2

# Verify critical headers are single-emission (or zero for X-XSS-Protection)
check_header_count() {
  local url="$1"
  local hdr="$2"
  local expected="$3"
  local count
  count="$(curl -k -sSI "$url" | awk -v h="$hdr" 'BEGIN{IGNORECASE=1} $0 ~ "^" h ":" {c++} END{print c+0}')"
  if [[ "$count" == "$expected" ]]; then
    echo "[OK] ${url} -> ${hdr}: ${count}"
  else
    echo "[WARN] ${url} -> ${hdr}: ${count} (expected ${expected})"
  fi
}

log "Verifying header counts"
for u in https://vigilantvoices.com/ https://feautofab.com/; do
  check_header_count "$u" "x-content-type-options" 1
  check_header_count "$u" "x-frame-options" 1
  check_header_count "$u" "referrer-policy" 1
  check_header_count "$u" "permissions-policy" 1
  check_header_count "$u" "cross-origin-opener-policy" 1
  check_header_count "$u" "cross-origin-resource-policy" 1
  check_header_count "$u" "x-permitted-cross-domain-policies" 1
  check_header_count "$u" "content-security-policy" 1
  check_header_count "$u" "x-xss-protection" 0
  echo
  curl -k -sSI "$u" | sed -n '1,30p'
  echo
 done

log "Hardening v2 complete"
