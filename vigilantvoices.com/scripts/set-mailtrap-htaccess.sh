#!/usr/bin/env bash
set -euo pipefail

# Updates Mailtrap-related exports in /etc/apache2/site-secrets.env (not .htaccess).
# Restart Apache after running: sudo systemctl restart apache2

SECRETS_FILE="${MAILTRAP_SECRETS_FILE:-/etc/apache2/site-secrets.env}"
TOKEN="${MAILTRAP_API_TOKEN:-}"
TO_EMAIL=""
FROM_EMAIL="hello@demomailtrap.co"
DRY_RUN=0

usage() {
  cat <<'USAGE'
Usage:
  sudo ./scripts/set-mailtrap-htaccess.sh --token <MAILTRAP_TOKEN> [options]

Options:
  --token <value>       Mailtrap API token (or set MAILTRAP_API_TOKEN env var)
  --to <email>          MAILTRAP_TO_EMAIL for each site (default: hello@<site>)
  --from <email>        MAILTRAP_FROM_EMAIL (default: hello@demomailtrap.co)
  --secrets <path>      Secrets file to edit (default: /etc/apache2/site-secrets.env)
  --dry-run             Show what would change without writing
  -h, --help            Show this help

Secrets are stored in /etc/apache2/site-secrets.env and loaded via /etc/apache2/envvars.
After changes, restart Apache: sudo systemctl restart apache2
USAGE
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --token)
      TOKEN="${2:-}"
      shift 2
      ;;
    --to)
      TO_EMAIL="${2:-}"
      shift 2
      ;;
    --from)
      FROM_EMAIL="${2:-}"
      shift 2
      ;;
    --secrets)
      SECRETS_FILE="${2:-}"
      shift 2
      ;;
    --dry-run)
      DRY_RUN=1
      shift
      ;;
    -h|--help)
      usage
      exit 0
      ;;
    *)
      echo "Unknown option: $1" >&2
      usage
      exit 1
      ;;
  esac
done

if [[ -z "$TOKEN" ]]; then
  echo "MAILTRAP token is required. Use --token or MAILTRAP_API_TOKEN env var." >&2
  exit 1
fi

upsert_export() {
  local file="$1"
  local key="$2"
  local value="$3"
  local tmp
  tmp=$(mktemp)
  if grep -qE "^export ${key}=" "$file" 2>/dev/null; then
    grep -vE "^export ${key}=" "$file" > "$tmp"
  else
    cp "$file" "$tmp"
  fi
  printf 'export %s=%q\n' "$key" "$value" >> "$tmp"
  mv "$tmp" "$file"
}

if [[ "$DRY_RUN" -eq 1 ]]; then
  echo "DRY RUN: would update ${SECRETS_FILE}"
  echo "  MAILTRAP_API_TOKEN=<redacted>"
  echo "  MAILTRAP_TO_EMAIL (per-site if --to not set)"
  echo "  MAILTRAP_FROM_EMAIL=${FROM_EMAIL}"
  echo "  VV_MAILTRAP_FROM_NAME / MM_MAILTRAP_FROM_NAME (defaults preserved if file exists)"
  exit 0
fi

if [[ ! -f "$SECRETS_FILE" ]]; then
  echo "Secrets file not found: ${SECRETS_FILE}" >&2
  exit 1
fi

backup_file="${SECRETS_FILE}.bak.$(date +%Y%m%d%H%M%S)"
cp "$SECRETS_FILE" "$backup_file"
echo "Backup: ${backup_file}"

upsert_export "$SECRETS_FILE" "MAILTRAP_API_TOKEN" "$TOKEN"
upsert_export "$SECRETS_FILE" "MAILTRAP_FROM_EMAIL" "$FROM_EMAIL"

if [[ -n "$TO_EMAIL" ]]; then
  upsert_export "$SECRETS_FILE" "MAILTRAP_TO_EMAIL" "$TO_EMAIL"
fi

chmod 600 "$SECRETS_FILE"
echo "Updated ${SECRETS_FILE}. Run: sudo systemctl restart apache2"
