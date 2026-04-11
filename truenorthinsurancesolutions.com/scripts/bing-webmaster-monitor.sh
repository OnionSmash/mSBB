#!/usr/bin/env bash
set -euo pipefail

SITE_DIR="/var/www/truenorthinsurancesolutions.com"
SCRIPTS_DIR="${SITE_DIR}/scripts"
ENV_FILE="${SCRIPTS_DIR}/bing-webmaster.env"
PY_SCRIPT="${SCRIPTS_DIR}/bing_webmaster_submit.py"
CRON_LOG="${SCRIPTS_DIR}/logs/bing-webmaster-cron.log"

if [[ -f "$ENV_FILE" ]]; then
  set -a
  # shellcheck disable=SC1090
  source "$ENV_FILE"
  set +a
fi

echo "============================================================"
echo "Bing Webmaster Monitor: truenorthinsurancesolutions.com"
echo "============================================================"

echo "1) Auth variable check"
if [[ -n "${BING_WEBMASTER_API_KEY:-}" ]]; then
  echo "BING_WEBMASTER_API_KEY: set"
elif [[ -n "${BING_WEBMASTER_OAUTH_TOKEN:-}" ]]; then
  echo "BING_WEBMASTER_OAUTH_TOKEN: set"
else
  echo "No Bing auth variables set. Configure ${ENV_FILE}."
  exit 1
fi

echo
echo "2) Dry-run URL gather"
/usr/bin/python3 "$PY_SCRIPT" --dry-run

echo
echo "3) Recent cron log tail"
if [[ -f "$CRON_LOG" ]]; then
  tail -n 25 "$CRON_LOG"
else
  echo "No cron log yet: $CRON_LOG"
fi
