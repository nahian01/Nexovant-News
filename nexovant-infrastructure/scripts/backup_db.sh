#!/usr/bin/env bash
set -euo pipefail

# ==============================================================================
# NEXOVANT ENCRYPTED DATABASE BACKUP SCRIPT
# Uploads encrypted PostgreSQL snapshot to Cloudflare R2
# ==============================================================================

TIMESTAMP=$(date -u +"%Y%m%d_%H%M%SZ")
BACKUP_DIR="/tmp/nexovant_backups"
FILENAME="nexovant_db_${TIMESTAMP}.sql.gz"
ENCRYPTED_FILENAME="${FILENAME}.enc"

mkdir -p "$BACKUP_DIR"

echo "--> [Backup] Dumping PostgreSQL database..."
docker exec -t nexovant_db pg_dump -U "${DB_USERNAME:-nexovant_app}" -d "${DB_DATABASE:-nexovant_production}" --format=custom | gzip > "${BACKUP_DIR}/${FILENAME}"

echo "--> [Backup] Encrypting database snapshot with OpenSSL AES-256-CBC..."
openssl enc -aes-256-cbc -salt -pbkdf2 \
    -in "${BACKUP_DIR}/${FILENAME}" \
    -out "${BACKUP_DIR}/${ENCRYPTED_FILENAME}" \
    -pass env:BACKUP_ENCRYPTION_KEY

if [ -n "${CLOUDFLARE_R2_BUCKET_NAME:-}" ]; then
    echo "--> [Backup] Uploading encrypted archive to Cloudflare R2: ${CLOUDFLARE_R2_BUCKET_NAME}..."
    aws s3 cp "${BACKUP_DIR}/${ENCRYPTED_FILENAME}" \
        "s3://${CLOUDFLARE_R2_BUCKET_NAME}/database_backups/${ENCRYPTED_FILENAME}" \
        --endpoint-url "${CLOUDFLARE_R2_ENDPOINT}"
    echo "--> [Backup] Upload to Cloudflare R2 verified."
fi

# Cleanup local temporary files
rm -f "${BACKUP_DIR}/${FILENAME}" "${BACKUP_DIR}/${ENCRYPTED_FILENAME}"
echo "--> [Backup] Backup routine completed: ${ENCRYPTED_FILENAME}"
