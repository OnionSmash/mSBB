#!/usr/bin/env bash
set -euo pipefail

# PAT-based GitHub backup for /var/www websites.
# Required env vars:
#   GITHUB_PAT    - Personal access token with repo permissions
#   GITHUB_OWNER  - GitHub owner/org (e.g., your username)
#   GITHUB_REPO   - Target repo name (will be created if missing)
# Optional env vars:
#   BACKUP_BRANCH - Default: main

: "${GITHUB_PAT:?Set GITHUB_PAT}"
: "${GITHUB_OWNER:?Set GITHUB_OWNER}"
: "${GITHUB_REPO:?Set GITHUB_REPO}"

normalize_github_owner() {
  local raw="${1%/}"
  raw="${raw#https://github.com/}"
  raw="${raw#http://github.com/}"
  raw="${raw#github.com/}"
  raw="${raw%%/*}"
  printf '%s' "$raw"
}

normalize_github_repo() {
  local raw="${1%/}"
  raw="${raw#https://github.com/}"
  raw="${raw#http://github.com/}"
  raw="${raw#github.com/}"
  raw="${raw#*/}"
  raw="${raw%.git}"
  printf '%s' "$raw"
}

GITHUB_OWNER="$(normalize_github_owner "$GITHUB_OWNER")"
GITHUB_REPO="$(normalize_github_repo "$GITHUB_REPO")"

: "${GITHUB_OWNER:?Set GITHUB_OWNER (name or github URL)}"
: "${GITHUB_REPO:?Set GITHUB_REPO (name or github URL)}"

BACKUP_BRANCH="${BACKUP_BRANCH:-main}"
STAMP="$(date +%F-%H%M%S)"
WORKDIR="$(mktemp -d /tmp/github-backup-${STAMP}-XXXXXX)"
REPO_URL="https://x-access-token:${GITHUB_PAT}@github.com/${GITHUB_OWNER}/${GITHUB_REPO}.git"

cleanup() {
  rm -rf "$WORKDIR"
}
trap cleanup EXIT

copy_site() {
  local site="$1"
  if [[ -d "/var/www/${site}" ]]; then
    rsync -a "/var/www/${site}/" "$WORKDIR/${site}/" \
      --exclude '.git/' \
      --exclude 'letsencrypt/' \
      --exclude 'ssl/' \
      --exclude 'scripts/logs/' \
      --exclude '*.env' \
      --exclude '*.pem' \
      --exclude '*.key'
  fi
}

copy_site "modelmesh.cloud"
copy_site "vigilantvoices.com"
copy_site "truenorthinsurancesolutions.com"
copy_site "feautofab.com"
copy_site "barnyardfeedsupply.com"
copy_site "monthlyjab.com"

cat > "$WORKDIR/BACKUP_MANIFEST.txt" <<EOF
Backup timestamp: ${STAMP}
Source: /var/www
Sites: modelmesh.cloud, vigilantvoices.com, truenorthinsurancesolutions.com
Excludes: letsencrypt/, ssl/, scripts/logs/, *.env, *.pem, *.key
Auth mode: PAT HTTPS
EOF

cd "$WORKDIR"
git init -q
git config user.name "backup-bot"
git config user.email "backup-bot@users.noreply.github.com"
git add .
git commit -q -m "Backup snapshot ${STAMP}"
git branch -M "$BACKUP_BRANCH"

# Create repo if missing (API), then push.
if ! curl -fsS -H "Authorization: token ${GITHUB_PAT}" "https://api.github.com/repos/${GITHUB_OWNER}/${GITHUB_REPO}" >/dev/null 2>&1; then
  AUTH_USER="$(curl -fsS -H "Authorization: token ${GITHUB_PAT}" https://api.github.com/user | sed -n 's/.*"login"[[:space:]]*:[[:space:]]*"\([^"]*\)".*/\1/p' | head -n 1)"
  CREATE_ENDPOINT="https://api.github.com/user/repos"
  if [[ -n "$AUTH_USER" && "$AUTH_USER" != "$GITHUB_OWNER" ]]; then
    CREATE_ENDPOINT="https://api.github.com/orgs/${GITHUB_OWNER}/repos"
  fi

  curl -fsS -X POST \
    -H "Authorization: token ${GITHUB_PAT}" \
    -H "Accept: application/vnd.github+json" \
    "${CREATE_ENDPOINT}" \
    -d "{\"name\":\"${GITHUB_REPO}\",\"private\":true}" >/dev/null
fi

git remote add origin "$REPO_URL"
git push -u -f origin "$BACKUP_BRANCH" >/dev/null

echo "Backup pushed to: https://github.com/${GITHUB_OWNER}/${GITHUB_REPO}"
echo "Commit: $(git rev-parse --short HEAD)"
