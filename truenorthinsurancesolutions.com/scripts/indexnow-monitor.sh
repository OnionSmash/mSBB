#!/usr/bin/env bash
set -euo pipefail

HOST="truenorthinsurancesolutions.com"
KEY="b7f2a1d4-9c3e-4a8b-bf6e-7d2c5a91e8f0"
KEY_LOCATION="https://${HOST}/${KEY}.txt"
API_URL="https://api.indexnow.org/indexnow"
TEST_URL="https://${HOST}/"
SITEMAP_URL="https://${HOST}/sitemap.xml"
SUBMISSION_LOG="/var/www/truenorthinsurancesolutions.com/scripts/logs/indexnow-submissions.log"
CRON_LOG="/var/www/truenorthinsurancesolutions.com/scripts/logs/indexnow-cron.log"

print_header() {
  echo
  echo "============================================================"
  echo "$1"
  echo "============================================================"
}

print_header "1) Key File Reachability"
KEY_STATUS=$(curl -sS -o /tmp/indexnow-key-check.txt -w '%{http_code}' "$KEY_LOCATION")
echo "Key URL: $KEY_LOCATION"
echo "HTTP Status: $KEY_STATUS"
if [[ "$KEY_STATUS" == "200" ]]; then
  echo "Key file content preview:"
  head -n 1 /tmp/indexnow-key-check.txt
else
  echo "Key file check failed."
fi

print_header "2) GET Submission Test (curl)"
ENCODED_TEST_URL=$(python3 - <<'PY'
import urllib.parse
print(urllib.parse.quote('https://truenorthinsurancesolutions.com/', safe=''))
PY
)
GET_ENDPOINT="${API_URL}?url=${ENCODED_TEST_URL}&key=${KEY}&keyLocation=$(python3 - <<'PY'
import urllib.parse
print(urllib.parse.quote('https://truenorthinsurancesolutions.com/b7f2a1d4-9c3e-4a8b-bf6e-7d2c5a91e8f0.txt', safe=''))
PY
)"
GET_STATUS=$(curl -sS -o /tmp/indexnow-get-response.txt -w '%{http_code}' "$GET_ENDPOINT")
echo "Endpoint: $GET_ENDPOINT"
echo "HTTP Status: $GET_STATUS"
echo "Response body:"
cat /tmp/indexnow-get-response.txt || true

print_header "3) POST Batch Submission Test (curl)"
POST_PAYLOAD=$(cat <<JSON
{"host":"${HOST}","key":"${KEY}","keyLocation":"${KEY_LOCATION}","urlList":["${TEST_URL}","${SITEMAP_URL}"]}
JSON
)
POST_STATUS=$(curl -sS -o /tmp/indexnow-post-response.txt -w '%{http_code}' \
  -X POST "$API_URL" \
  -H 'Content-Type: application/json; charset=utf-8' \
  --data "$POST_PAYLOAD")
echo "HTTP Status: $POST_STATUS"
echo "Response body:"
cat /tmp/indexnow-post-response.txt || true

print_header "4) Recent Script Logs"
if [[ -f "$CRON_LOG" ]]; then
  echo "Cron log tail (${CRON_LOG}):"
  tail -n 20 "$CRON_LOG"
else
  echo "Cron log file not found: $CRON_LOG"
fi

print_header "5) Structured Submission Summary"
if [[ -f "$SUBMISSION_LOG" ]]; then
  python3 - <<'PY'
import json
from collections import Counter

path = '/var/www/truenorthinsurancesolutions.com/scripts/logs/indexnow-submissions.log'
status_counts = Counter()
result_counts = Counter()
retries = 0
lines = 0
last = []

with open(path, 'r', encoding='utf-8', errors='ignore') as f:
    for raw in f:
        raw = raw.strip()
        if not raw:
            continue
        lines += 1
        try:
            entry = json.loads(raw)
        except Exception:
            continue
        status = entry.get('http_status')
        result = entry.get('submission_result', 'unknown')
        retry_attempts = int(entry.get('retry_attempts', 0) or 0)
        status_counts[str(status)] += 1
        result_counts[result] += 1
        retries += retry_attempts
        last.append(entry)
        if len(last) > 5:
            last.pop(0)

print(f'Total log entries: {lines}')
print('Submission result counts:')
for k, v in sorted(result_counts.items()):
    print(f'  {k}: {v}')
print('HTTP status counts:')
for k, v in sorted(status_counts.items()):
    print(f'  {k}: {v}')
print(f'Total retry attempts recorded: {retries}')
print('Last 5 entries:')
for item in last:
    ts = item.get('timestamp')
    url = item.get('url')
    status = item.get('http_status')
    result = item.get('submission_result')
    ra = item.get('retry_attempts')
    print(f'  {ts} | {status} | {result} | retries={ra} | {url}')
PY
else
  echo "Structured log file not found: $SUBMISSION_LOG"
fi

echo
echo "Done."
