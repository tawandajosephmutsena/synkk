#!/bin/bash
set -e

ENCRYPTED_PATH=$1
if [ -z "$ENCRYPTED_PATH" ]; then
    echo "Usage: $0 <path_to_encrypted_backup> [path_to_db] [path_to_vaults]"
    exit 1
fi

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

DB_PATH=${2:-"${DB_DATABASE:-$DEFAULT_DB}"}
VAULTS_DIR=${3:-"${SYNKK_VAULTS_DIR:-$DEFAULT_VAULTS}"}

PASSPHRASE=${BACKUP_PASSPHRASE:-""}
if [ -z "$PASSPHRASE" ]; then
    if [ "${APP_ENV:-}" = "production" ]; then
        echo "ERROR: BACKUP_PASSPHRASE must be set in production to decrypt backups." >&2
        exit 1
    fi
    echo "WARNING: BACKUP_PASSPHRASE is unset. Using default testing passphrase." >&2
    PASSPHRASE="default_insecure_passphrase_for_testing"
fi

RESTORE_TMP_DIR=$(mktemp -d "/tmp/synkk_restore_XXXXXX")
cleanup() {
    rm -rf "$RESTORE_TMP_DIR"
}
trap cleanup EXIT

echo "Decrypting backup archive [$ENCRYPTED_PATH]..."
DECRYPTED_TAR="$RESTORE_TMP_DIR/backup.tar.gz"

# Detect whether the archive was encrypted with OpenSSL (Salted__ header) or GPG
if head -c 8 "$ENCRYPTED_PATH" | grep -q "Salted__" || [ "${ENCRYPTED_PATH##*.}" = "enc" ]; then
    echo "Decrypting with OpenSSL (AES-256-CBC PBKDF2)..."
    openssl enc -d -aes-256-cbc -salt -pbkdf2 -pass "pass:$PASSPHRASE" -in "$ENCRYPTED_PATH" -out "$DECRYPTED_TAR"
elif command -v gpg >/dev/null 2>&1; then
    echo "Decrypting with GPG..."
    echo "$PASSPHRASE" | gpg --batch --yes --passphrase-fd 0 --decrypt -o "$DECRYPTED_TAR" "$ENCRYPTED_PATH"
else
    echo "ERROR: File appears to be GPG encrypted, but gpg is not installed." >&2
    exit 1
fi

echo "Extracting archive..."
mkdir -p "$RESTORE_TMP_DIR/extracted"
tar -xzf "$DECRYPTED_TAR" -C "$RESTORE_TMP_DIR/extracted"

# 1. Restore SQLite database if present in archive
if [ -f "$RESTORE_TMP_DIR/extracted/database.sqlite" ]; then
    echo "Restoring database to [$DB_PATH]..."
    mkdir -p "$(dirname "$DB_PATH")"
    if [ -f "$DB_PATH" ]; then
        cp "$DB_PATH" "${DB_PATH}.bak.$(date +%Y%m%d%H%M%S)"
    fi
    mv "$RESTORE_TMP_DIR/extracted/database.sqlite" "$DB_PATH"
    chmod 644 "$DB_PATH"
fi

# 2. Restore physical vault storage if present in archive
if [ -d "$RESTORE_TMP_DIR/extracted/vaults" ]; then
    echo "Restoring vault files to [$VAULTS_DIR]..."
    mkdir -p "$VAULTS_DIR"
    cp -R "$RESTORE_TMP_DIR/extracted/vaults"/. "$VAULTS_DIR/"
fi

echo "Restore complete."
