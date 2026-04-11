#!/usr/bin/env bash
set -euo pipefail

# Configure Mailtrap Apache SetEnv values for modelmesh.cloud and vigilantvoices.com.
# Run as root.
#
# Usage (preferred with environment variables):
#   sudo MAILTRAP_API_TOKEN_MODEL="..." \
#        MAILTRAP_API_TOKEN_VIGILANT="..." \
#        MAILTRAP_TO_EMAIL="ravenell@modelsignal.cloud" \
#        /var/www/modelmesh.cloud/scripts/configure-mailtrap-apache-env.sh
#
# Or with flags:
#   sudo /var/www/modelmesh.cloud/scripts/configure-mailtrap-apache-env.sh \
#        --token-model "..." --token-vigilant "..." --to-email "ravenell@modelsignal.cloud"

MODEL_TOKEN="${MAILTRAP_API_TOKEN_MODEL:-}"
VIGILANT_TOKEN="${MAILTRAP_API_TOKEN_VIGILANT:-}"
TO_EMAIL="${MAILTRAP_TO_EMAIL:-ravenell@modelsignal.cloud}"
FROM_EMAIL_MODEL="${MAILTRAP_FROM_EMAIL_MODEL:-hello@demomailtrap.co}"
FROM_EMAIL_VIGILANT="${MAILTRAP_FROM_EMAIL_VIGILANT:-hello@demomailtrap.co}"
FROM_NAME_MODEL="${MAILTRAP_FROM_NAME_MODEL:-Model Signal Website}"
FROM_NAME_VIGILANT="${MAILTRAP_FROM_NAME_VIGILANT:-Vigilant Voices Website}"

while [[ $# -gt 0 ]]; do
  case "$1" in
    --token-model)
      MODEL_TOKEN="${2:-}"
      shift 2
      ;;
    --token-vigilant)
      VIGILANT_TOKEN="${2:-}"
      shift 2
      ;;
    --to-email)
      TO_EMAIL="${2:-}"
      shift 2
      ;;
    --from-email-model)
      FROM_EMAIL_MODEL="${2:-}"
      shift 2
      ;;
    --from-email-vigilant)
      FROM_EMAIL_VIGILANT="${2:-}"
      shift 2
      ;;
    --from-name-model)
      FROM_NAME_MODEL="${2:-}"
      shift 2
      ;;
    --from-name-vigilant)
      FROM_NAME_VIGILANT="${2:-}"
      shift 2
      ;;
    --help|-h)
      sed -n '1,40p' "$0"
      exit 0
      ;;
    *)
      echo "Unknown argument: $1" >&2
      exit 1
      ;;
  esac
done

if [[ $EUID -ne 0 ]]; then
  echo "ERROR: Run as root (sudo)." >&2
  exit 1
fi

if [[ -z "$MODEL_TOKEN" || -z "$VIGILANT_TOKEN" ]]; then
  echo "ERROR: Missing Mailtrap tokens." >&2
  echo "Set MAILTRAP_API_TOKEN_MODEL and MAILTRAP_API_TOKEN_VIGILANT env vars or pass --token-model/--token-vigilant." >&2
  exit 1
fi

VHOST_MODEL_HTTPS="/etc/apache2/sites-available/modelmesh.cloud-le-ssl.conf"
VHOST_MODEL_HTTP="/etc/apache2/sites-available/modelmesh.cloud.conf"
VHOST_VIGILANT_HTTPS="/etc/apache2/sites-available/vigilantvoices.com-le-ssl.conf"
VHOST_VIGILANT_HTTP="/etc/apache2/sites-available/vigilantvoices.conf"

VHOSTS=(
  "$VHOST_MODEL_HTTPS"
  "$VHOST_MODEL_HTTP"
  "$VHOST_VIGILANT_HTTPS"
  "$VHOST_VIGILANT_HTTP"
)

for f in "${VHOSTS[@]}"; do
  if [[ ! -f "$f" ]]; then
    echo "ERROR: Missing vhost file: $f" >&2
    exit 1
  fi
done

backup_file() {
  local f="$1"
  cp "$f" "${f}.bak.$(date +%Y%m%d%H%M%S)"
}

upsert_env_block() {
  local file="$1"
  local token="$2"
  local to_email="$3"
  local from_email="$4"
  local from_name="$5"

  backup_file "$file"

  # Remove existing Mailtrap SetEnv lines so replacement is deterministic.
  sed -i '/^[[:space:]]*SetEnv[[:space:]]\+MAILTRAP_API_TOKEN[[:space:]]\+/d' "$file"
  sed -i '/^[[:space:]]*SetEnv[[:space:]]\+MAILTRAP_TO_EMAIL[[:space:]]\+/d' "$file"
  sed -i '/^[[:space:]]*SetEnv[[:space:]]\+MAILTRAP_FROM_EMAIL[[:space:]]\+/d' "$file"
  sed -i '/^[[:space:]]*SetEnv[[:space:]]\+MAILTRAP_FROM_NAME[[:space:]]\+/d' "$file"

  local tmp
  tmp="$(mktemp)"

  awk -v token="$token" -v to_email="$to_email" -v from_email="$from_email" -v from_name="$from_name" '
    /CustomLog[[:space:]]/ && !inserted {
      print $0
      print ""
      print "    SetEnv MAILTRAP_API_TOKEN " token
      print "    SetEnv MAILTRAP_TO_EMAIL " to_email
      print "    SetEnv MAILTRAP_FROM_EMAIL " from_email
      print "    SetEnv MAILTRAP_FROM_NAME \"" from_name "\""
      inserted=1
      next
    }
    { print }
  ' "$file" > "$tmp"

  mv "$tmp" "$file"
}

echo "Applying Mailtrap env config..."
upsert_env_block "$VHOST_MODEL_HTTPS" "$MODEL_TOKEN" "$TO_EMAIL" "$FROM_EMAIL_MODEL" "$FROM_NAME_MODEL"
upsert_env_block "$VHOST_MODEL_HTTP" "$MODEL_TOKEN" "$TO_EMAIL" "$FROM_EMAIL_MODEL" "$FROM_NAME_MODEL"
upsert_env_block "$VHOST_VIGILANT_HTTPS" "$VIGILANT_TOKEN" "$TO_EMAIL" "$FROM_EMAIL_VIGILANT" "$FROM_NAME_VIGILANT"
upsert_env_block "$VHOST_VIGILANT_HTTP" "$VIGILANT_TOKEN" "$TO_EMAIL" "$FROM_EMAIL_VIGILANT" "$FROM_NAME_VIGILANT"

echo "Validating Apache configuration..."
apache2ctl configtest

echo "Reloading Apache..."
systemctl reload apache2

echo "Running API health checks..."
echo "modelmesh: $(curl -sS https://modelmesh.cloud/api/send-form.php?health=1)"
echo "vigilant:  $(curl -sS https://vigilantvoices.com/api/send-form.php?health=1)"

echo "Done."
