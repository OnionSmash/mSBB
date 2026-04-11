#!/usr/bin/env bash
set -euo pipefail

# Validate links defined in partials/navbar.html and report non-200/301 responses.
# Usage:
#   ./scripts/check-navbar-links.sh
#   BASE_URL="http://localhost" ./scripts/check-navbar-links.sh

BASE_URL="${BASE_URL:-https://vigilantvoices.com}"
NAV_FILE="partials/navbar.html"

if [[ ! -f "$NAV_FILE" ]]; then
  echo "ERROR: Cannot find $NAV_FILE"
  exit 2
fi

mapfile -t HREFS < <(
  grep -oE 'href="[^"]+"' "$NAV_FILE" \
    | sed -E 's/^href="(.*)"$/\1/' \
    | grep -Ev '^(#|javascript:|mailto:|tel:|sms:)$' \
    | sort -u
)

if [[ ${#HREFS[@]} -eq 0 ]]; then
  echo "ERROR: No links found in $NAV_FILE"
  exit 2
fi

BROKEN=0
printf "Checking %d navbar links against %s\n" "${#HREFS[@]}" "$BASE_URL"

for href in "${HREFS[@]}"; do
  if [[ "$href" =~ ^https?:// ]]; then
    url="$href"
  else
    url="${BASE_URL%/}$href"
  fi

  code=$(curl -k -sS -o /dev/null -w "%{http_code}" --max-redirs 0 "$url" || echo "000")

  if [[ "$code" == "200" || "$code" == "301" ]]; then
    printf "OK    %-4s %s\n" "$code" "$url"
  else
    printf "FAIL  %-4s %s\n" "$code" "$url"
    BROKEN=$((BROKEN + 1))
  fi
 done

if [[ "$BROKEN" -gt 0 ]]; then
  echo "Navbar health check completed: $BROKEN broken link(s)."
  exit 1
fi

echo "Navbar health check completed: all links returned 200/301."
