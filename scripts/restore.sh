#!/bin/bash
set -e

ENCRYPTED_PATH=$1
DB_PATH=${2:-"/var/www/html/database/database.sqlite"}
PASSPHRASE=${BACKUP_PASSPHRASE:-"default_insecure_passphrase_for_testing"}

if [ -z "$ENCRYPTED_PATH" ]; then
    echo "Usage: $0 <path_to_encrypted_backup> [path_to_db]"
    exit 1
fi

echo "Decrypting backup..."
echo "$PASSPHRASE" | gpg --batch --yes --passphrase-fd 0 --decrypt -o "${DB_PATH}.restored" "$ENCRYPTED_PATH"

echo "Replacing old database with restored copy..."
mv "${DB_PATH}.restored" "$DB_PATH"
chmod 644 "$DB_PATH"

echo "Restore complete."
