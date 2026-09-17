#!/bin/bash
set -e

DB_PATH=${1:-"/var/www/html/database/database.sqlite"}
BACKUP_DIR=${2:-"/var/www/html/storage/backups"}
TIMESTAMP=$(date +%Y%m%d%H%M%S)
BACKUP_PATH="$BACKUP_DIR/db_backup_$TIMESTAMP.sqlite"
ENCRYPTED_PATH="$BACKUP_PATH.gpg"
PASSPHRASE=${BACKUP_PASSPHRASE:-"default_insecure_passphrase_for_testing"}

mkdir -p "$BACKUP_DIR"

echo "Running VACUUM INTO to create physical backup..."
sqlite3 "$DB_PATH" "VACUUM INTO '$BACKUP_PATH';"

echo "Encrypting backup..."
echo "$PASSPHRASE" | gpg --batch --yes --passphrase-fd 0 --symmetric --cipher-algo AES256 -o "$ENCRYPTED_PATH" "$BACKUP_PATH"

rm "$BACKUP_PATH"

echo "Backup complete: $ENCRYPTED_PATH"

# Retention policy: delete backups older than 30 days
find "$BACKUP_DIR" -type f -name "*.gpg" -mtime +30 -exec rm {} \;
echo "Old backups cleaned up."
