#!/bin/bash

# ================== НАСТРОЙКИ ==================
BACKUP_DIR="/tmp/backups"


SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
LOG_DIR="${PROJECT_DIR}/logs"


TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BASE_NAME="testplatform_full_${TIMESTAMP}"
DUMP_FILE="${BACKUP_DIR}/${BASE_NAME}.sql"
GZIP_FILE="${DUMP_FILE}.gz"
HASH_FILE="${GZIP_FILE}.sha256"
GPG_FILE="${GZIP_FILE}.gpg"
REMOTE_NAME="b2-testplatform"
BUCKET_PATH="testplatform-backups"
LOG_FILE="${LOG_DIR}/backup.log"

mkdir -p "$LOG_DIR" "$BACKUP_DIR"

# Загружаем .env
set -a
source "${PROJECT_DIR}/.env"
set +a

echo "=== Начинаем ПОЛНЫЙ бэкап $(date) ===" | tee -a "$LOG_FILE"

# 1. mysqldump (root + --no-tablespaces — решает проблему PROCESS)
docker exec -i testplatform_mysql \
    mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" \
    --single-transaction --quick --lock-tables=false \
    --no-tablespaces --databases "$MYSQL_DATABASE" > "$DUMP_FILE"

if [ $? -ne 0 ]; then
    echo "ОШИБКА: mysqldump завершился с ошибкой" | tee -a "$LOG_FILE"
    exit 1
fi

# 2. gzip
gzip -c "$DUMP_FILE" > "$GZIP_FILE"

# 3. sha256
sha256sum "$GZIP_FILE" | awk '{print $1}' > "$HASH_FILE"

# 4. gpg symmetric
echo "$GPG_BACKUP_PASSWORD" | gpg --batch --yes --passphrase-fd 0 \
    --symmetric --cipher-algo AES256 -o "$GPG_FILE" "$GZIP_FILE"

if [ $? -ne 0 ]; then
    echo "ОШИБКА: gpg шифрование не удалось" | tee -a "$LOG_FILE"
    exit 1
fi

# 5. rclone в Backblaze B2
rclone copy "$GPG_FILE" "${REMOTE_NAME}:${BUCKET_PATH}/" --progress
rclone copy "$HASH_FILE" "${REMOTE_NAME}:${BUCKET_PATH}/" --progress

# Очистка
rm -f "$DUMP_FILE" "$GZIP_FILE" "$GPG_FILE" "$HASH_FILE"

echo "✅ ПОЛНЫЙ бэкап успешно завершён: ${BASE_NAME}.sql.gz.gpg + .sha256" | tee -a "$LOG_FILE"
echo "=== Завершено $(date) ===" | tee -a "$LOG_FILE"
