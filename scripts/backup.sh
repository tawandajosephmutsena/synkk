#!/bin/bash
set -e

# Default paths, supporting Docker environment (/var/www/html) and local repository
DEFAULT_DB="/var/www/html/storage/app/database.sqlite"
if [ ! -f "$DEFAULT_DB" ] && [ -f "/var/www/html/database/database.sqlite" ]; then
    DEFAULT_DB="/var/www/html/database/database.sqlite"
elif [ ! -f "$DEFAULT_DB" ] && [ -f "./database/database.sqlite" ]; then
    DEFAULT_DB="./database/database.sqlite"
fi

DEFAULT_VAULTS="/var/www/html/storage/app/vaults"
if [ ! -d "$DEFAULT_VAULTS" ] && [ -d "./storage/app/vaults" ]; then
    DEFAULT_VAULTS="./storage/app/vaults"
fi

DEFAULT_BACKUP_DIR="/var/www/html/storage/backups"
if [ ! -d "/var/www/html" ] && [ -d "./storage" ]; then
    DEFAULT_BACKUP_DIR="./storage/backups"
fi

DB_PATH=${1:-"${DB_DATABASE:-$DEFAULT_DB}"}
BACKUP_DIR=${2:-"${BACKUP_DIR:-$DEFAULT_BACKUP_DIR}"}
VAULTS_DIR=${3:-"${SYNKK_VAULTS_DIR:-$DEFAULT_VAULTS}"}

TIMESTAMP=$(date +%Y%m%d%H%M%S)
ARCHIVE_NAME="synkk_backup_$TIMESTAMP.tar.gz"
BACKUP_ARCHIVE="$BACKUP_DIR/$ARCHIVE_NAME"
ENCRYPTED_PATH="$BACKUP_ARCHIVE.enc"

PASSPHRASE=${BACKUP_PASSPHRASE:-""}
if [ -z "$PASSPHRASE" ]; then
    if [ "${APP_ENV:-}" = "production" ]; then
        echo "ERROR: BACKUP_PASSPHRASE must be set in production to encrypt backups." >&2
        exit 1
    fi
    echo "WARNING: BACKUP_PASSPHRASE is unset. Using default testing passphrase." >&2
    PASSPHRASE="default_insecure_passphrase_for_testing"
fi

mkdir -p "$BACKUP_DIR"

STAGING_DIR="$BACKUP_DIR/staging_$TIMESTAMP"
mkdir -p "$STAGING_DIR"

cleanup() {
    rm -rf "$STAGING_DIR"
    rm -f "$BACKUP_ARCHIVE"
}
trap cleanup EXIT

echo "Starting unified Synkk backup at $TIMESTAMP..."

# 1. Physical SQLite database backup via VACUUM INTO
if [ -f "$DB_PATH" ]; then
    echo "Running VACUUM INTO for database [$DB_PATH]..."
    sqlite3 "$DB_PATH" "VACUUM INTO '$STAGING_DIR/database.sqlite';"
else
    echo "WARNING: SQLite database not found at [$DB_PATH]. Skipping database snapshot." >&2
fi

# 2. Archive raw vault files, versions, and attachments
if [ -d "$VAULTS_DIR" ]; then
    echo "Packaging physical vault storage from [$VAULTS_DIR]..."
    mkdir -p "$STAGING_DIR/vaults"
    cp -R "$VAULTS_DIR"/. "$STAGING_DIR/vaults/" 2>/dev/null || true
else
    echo "Note: No vault storage directory found at [$VAULTS_DIR]. Creating empty placeholder."
    mkdir -p "$STAGING_DIR/vaults"
fi

# 3. Create unified tarball of database + vault storage
echo "Compressing archive to [$BACKUP_ARCHIVE]..."
tar -czf "$BACKUP_ARCHIVE" -C "$STAGING_DIR" .

# 4. Encrypt the combined archive (prefers GPG if available, falls back to OpenSSL AES256-CBC with PBKDF2)
if command -v gpg >/dev/null 2>&1; then
    ENCRYPTED_PATH="$BACKUP_ARCHIVE.gpg"
    echo "Encrypting backup archive with GPG (AES256)..."
    echo "$PASSPHRASE" | gpg --batch --yes --passphrase-fd 0 --symmetric --cipher-algo AES256 -o "$ENCRYPTED_PATH" "$BACKUP_ARCHIVE"
elif command -v openssl >/dev/null 2>&1; then
    echo "Encrypting backup archive with OpenSSL (AES-256-CBC PBKDF2)..."
    openssl enc -aes-256-cbc -salt -pbkdf2 -pass "pass:$PASSPHRASE" -in "$BACKUP_ARCHIVE" -out "$ENCRYPTED_PATH"
else
    echo "ERROR: Neither gpg nor openssl was found to encrypt the backup archive." >&2
    exit 1
fi

echo "Backup complete: $ENCRYPTED_PATH"

# 5. Retention policy: delete backups older than 30 days
find "$BACKUP_DIR" -type f \( -name "*.gpg" -o -name "*.enc" \) -mtime +30 -exec rm {} \;
echo "Old backups cleaned up."
