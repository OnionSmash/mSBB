#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="/var/www"
SITES=("modelmesh.cloud" "vigilantvoices.com")

TOKEN="${MAILTRAP_API_TOKEN:-}"
TO_EMAIL=""
FROM_EMAIL="hello@demomailtrap.co"
FROM_NAME=""
DRY_RUN=0

usage() {
  cat <<'USAGE'
Usage:
  set-mailtrap-htaccess.sh --token <MAILTRAP_TOKEN> [options]

Options:
  --token <value>       Mailtrap API token (or set MAILTRAP_API_TOKEN env var)
  --to <email>          MAILTRAP_TO_EMAIL for all target sites
  --from <email>        MAILTRAP_FROM_EMAIL (default: hello@demomailtrap.co)
  --name <value>        MAILTRAP_FROM_NAME for all target sites
  --sites <csv>         Comma-separated site folders under /var/www
                        Default: modelmesh.cloud,vigilantvoices.com
  --dry-run             Show what would change without writing files
  -h, --help            Show this help

Examples:
  ./scripts/set-mailtrap-htaccess.sh --token "your_token_here"
  ./scripts/set-mailtrap-htaccess.sh --token "your_token_here" --to "hello@modelsignal.cloud"
  ./scripts/set-mailtrap-htaccess.sh --token "your_token_here" --sites "modelmesh.cloud"
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
    --name)
      FROM_NAME="${2:-}"
      shift 2
      ;;
    --sites)
      IFS=',' read -r -a SITES <<< "${2:-}"
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

upsert_setenv() {
  local file="$1"
  local key="$2"
  local value_line="$3"

  if grep -q "^SetEnv ${key} " "$file"; then
    KEY="$key" NEW_LINE="$value_line" perl -i -pe 's/^SetEnv \Q$ENV{KEY}\E .*$/$ENV{NEW_LINE}/' "$file"
  else
    printf "%s\n" "$value_line" >> "$file"
  fi
}

for site in "${SITES[@]}"; do
  site="${site// /}"
  [[ -z "$site" ]] && continue

  htaccess_file="${ROOT_DIR}/${site}/.htaccess"
  if [[ ! -f "$htaccess_file" ]]; then
    echo "Skipping ${site}: ${htaccess_file} not found"
    continue
  fi

  site_to_email="$TO_EMAIL"
  if [[ -z "$site_to_email" ]]; then
    site_to_email="hello@${site}"
  fi

  site_from_name="$FROM_NAME"
  if [[ -z "$site_from_name" ]]; then
    if [[ "$site" == "modelmesh.cloud" ]]; then
      site_from_name="Model Signal Website"
    elif [[ "$site" == "vigilantvoices.com" ]]; then
      site_from_name="Vigilant Voices Website"
    else
      site_from_name="${site} Website"
    fi
  fi

  echo "\nTarget: ${htaccess_file}"
  echo "  MAILTRAP_TO_EMAIL=${site_to_email}"
  echo "  MAILTRAP_FROM_EMAIL=${FROM_EMAIL}"
  echo "  MAILTRAP_FROM_NAME=${site_from_name}"

  if [[ "$DRY_RUN" -eq 1 ]]; then
    echo "  DRY RUN: no file changes written"
    continue
  fi

  backup_file="${htaccess_file}.bak.$(date +%Y%m%d%H%M%S)"
  cp "$htaccess_file" "$backup_file"
  echo "  Backup created: ${backup_file}"

  upsert_setenv "$htaccess_file" "MAILTRAP_API_TOKEN" "SetEnv MAILTRAP_API_TOKEN ${TOKEN}"
  upsert_setenv "$htaccess_file" "MAILTRAP_TO_EMAIL" "SetEnv MAILTRAP_TO_EMAIL ${site_to_email}"
  upsert_setenv "$htaccess_file" "MAILTRAP_FROM_EMAIL" "SetEnv MAILTRAP_FROM_EMAIL ${FROM_EMAIL}"
  upsert_setenv "$htaccess_file" "MAILTRAP_FROM_NAME" "SetEnv MAILTRAP_FROM_NAME \"${site_from_name}\""

  echo "  Updated Mailtrap SetEnv values"
done

echo "\nDone."
