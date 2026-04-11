#!/usr/bin/env bash
set -euo pipefail

# Encrypted SSL backup helper for Let's Encrypt assets.
#
# Usage examples:
#   SSL_BACKUP_PASSPHRASE='strong-passphrase' ./scripts/backup-ssl-encrypted.sh
#   SSL_BACKUP_PASSPHRASE='strong-passphrase' SSL_BACKUP_MODE=full ./scripts/backup-ssl-encrypted.sh
#   SSL_BACKUP_PASSPHRASE='strong-passphrase' BACKUP_REPO_DIR=/opt/server-backup ./scripts/backup-ssl-encrypted.sh
#
# Modes:
#   config-only (default): renewal and renewal-hooks only (no private keys)
#   full: full /etc/letsencrypt tree (includes private keys) then encrypts archive

LE_DIR="${LE_DIR:-/etc/letsencrypt}"
OUTPUT_DIR="${OUTPUT_DIR:-/var/backups/ssl-encrypted}"
BACKUP_MODE="${SSL_BACKUP_MODE:-config-only}"
PASSPHRASE="${SSL_BACKUP_PASSPHRASE:-}"
BACKUP_REPO_DIR="${BACKUP_REPO_DIR:-}"
KEEP_COUNT="${KEEP_COUNT:-14}"

if [[ -z "$PASSPHRASE" ]]; then
  echo "ERROR: SSL_BACKUP_PASSPHRASE is required."
  exit 1
fi

if [[ "$BACKUP_MODE" != "config-only" && "$BACKUP_MODE" != "full" ]]; then
  echo "ERROR: SSL_BACKUP_MODE must be 'config-only' or 'full'."
  exit 1
fi

if [[ ! -d "$LE_DIR" ]]; then
  echo "ERROR: Let's Encrypt directory not found: $LE_DIR"
  exit 1
fi

mkdir -p "$OUTPUT_DIR"

STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
HOSTNAME_SHORT="$(hostname -s 2>/dev/null || hostname)"
BASE_NAME="${HOSTNAME_SHORT}-letsencrypt-${BACKUP_MODE}-${STAMP}"
TAR_PATH="${OUTPUT_DIR}/${BASE_NAME}.tar"
ENC_PATH="${TAR_PATH}.enc"
SHA_PATH="${ENC_PATH}.sha256"

TMP_DIR="$(mktemp -d)"
cleanup() {
  rm -rf "$TMP_DIR"
}
trap cleanup EXIT

if [[ "$BACKUP_MODE" == "config-only" ]]; then
  mkdir -p "$TMP_DIR/letsencrypt"

  if [[ -d "$LE_DIR/renewal" ]]; then
    rsync -a "$LE_DIR/renewal/" "$TMP_DIR/letsencrypt/renewal/"
  fi
  if [[ -d "$LE_DIR/renewal-hooks" ]]; then
    rsync -a "$LE_DIR/renewal-hooks/" "$TMP_DIR/letsencrypt/renewal-hooks/"
  fi
else
  rsync -a "$LE_DIR/" "$TMP_DIR/letsencrypt/"
fi

(
  cd "$TMP_DIR"
  tar -cf "$TAR_PATH" letsencrypt
)

openssl enc -aes-256-cbc -salt -pbkdf2 -iter 100000 \
  -in "$TAR_PATH" \
  -out "$ENC_PATH" \
  -pass "pass:${PASSPHRASE}"

sha256sum "$ENC_PATH" > "$SHA_PATH"
rm -f "$TAR_PATH"

echo "Encrypted backup created: $ENC_PATH"
echo "Checksum file created: $SHA_PATH"

if [[ "$KEEP_COUNT" =~ ^[0-9]+$ ]] && [[ "$KEEP_COUNT" -ge 1 ]]; then
  mapfile -t OLD_FILES < <(ls -1t "$OUTPUT_DIR"/*.enc 2>/dev/null | tail -n +$((KEEP_COUNT + 1)) || true)
  for f in "${OLD_FILES[@]}"; do
    rm -f "$f" "$f.sha256"
  done
fi

if [[ -n "$BACKUP_REPO_DIR" ]]; then
  if [[ ! -d "$BACKUP_REPO_DIR/.git" ]]; then
    echo "WARNING: BACKUP_REPO_DIR is set but is not a git repo: $BACKUP_REPO_DIR"
    exit 0
  fi

  DEST_DIR="$BACKUP_REPO_DIR/ssl-backups"
  mkdir -p "$DEST_DIR"
  cp "$ENC_PATH" "$DEST_DIR/"
  cp "$SHA_PATH" "$DEST_DIR/"

  (
    cd "$BACKUP_REPO_DIR"
    git add "ssl-backups/$(basename "$ENC_PATH")" "ssl-backups/$(basename "$SHA_PATH")"
    if ! git diff --cached --quiet; then
      git commit -m "Add encrypted SSL backup ${BASE_NAME}"
      git push origin main
      echo "Committed and pushed encrypted SSL backup to $BACKUP_REPO_DIR"
    else
      echo "No new SSL backup changes to commit"
    fi
  )
fi
