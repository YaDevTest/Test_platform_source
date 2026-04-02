#!/bin/bash
set -e
set -a; source .env; set +a
PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BACKUP_DIR="$PROJECT_DIR/backups/$(date +%Y%m%d_%H%M%S)"
LOG_FILE="$PROJECT_DIR/logs/deploy.log"

mkdir -p "$BACKUP_DIR" "$PROJECT_DIR/logs"

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" | tee -a "$LOG_FILE"; }

log "=== DEPLOY STARTED ==="

sudo apt install -y stress-ng >> "$LOG_FILE" 2>&1

sudo apt install -y wrk >> "$LOG_FILE" 2>&1

if ! command -v rclone &> /dev/null; then
  curl https://rclone.org/install.sh | sudo bash >> "$LOG_FILE" 2>&1
  log "rclone: installed"
else
  log "rclone: already installed"
fi

if [ -f "$PROJECT_DIR/rclone.conf.bak" ]; then
  mkdir -p ~/.config/rclone
  cp "$PROJECT_DIR/rclone.conf.bak" ~/.config/rclone/rclone.conf
  log "rclone config: restored"
fi



# 1. Бэкап БД перед деплоем
log "Backing up MySQL..."
docker exec testplatform_mysql mysqldump \
  -u root -p"$MYSQL_ROOT_PASSWORD" testplatform \
  > "$BACKUP_DIR/db.sql" 2>/dev/null && \
  log "MySQL backup: OK" || log "MySQL backup: SKIPPED (DB empty)"
  
  # Бэкап именованных volumes
log "Backing up volumes..."
for VOLUME in portainer_data grafana_data prometheus_data loki_data; do
  docker run --rm \
    -v testplatform_${VOLUME}:/data \
    -v "$BACKUP_DIR":/backup \
    alpine tar -czf /backup/${VOLUME}.tar.gz -C /data . 2>/dev/null && \
    log "  Volume $VOLUME: OK" || log "  Volume $VOLUME: SKIPPED"
done

# 2. Бэкап текущего docker-compose.yml
cp "$PROJECT_DIR/docker-compose.yml" "$BACKUP_DIR/docker-compose.yml"
log "Config backup: OK"

# 3. Pull новых образов
log "Pulling images..."
cd "$PROJECT_DIR"
docker compose pull --quiet 2>/dev/null || true

# 4. Rebuild изменённых сервисов
log "Building services..."
docker compose build --quiet fastapi yii2

# 5. Деплой с zero-downtime (по одному)
log "Deploying services..."
for SERVICE in fastapi yii2 nginx; do
  log "  Restarting $SERVICE..."
  docker compose up -d --no-deps "$SERVICE"
  sleep 5

  # Проверка здоровья
  STATUS=$(docker compose ps "$SERVICE" --format json 2>/dev/null | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('State','unknown'))" 2>/dev/null || echo "unknown")
  log "  $SERVICE status: $STATUS"
done

# 6. Health checks
log "Running health checks..."
sleep 5

HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost)
API_CODE=$(curl -s http://localhost/api/health 2>/dev/null | python3 -c "import sys,json; d=json.load(sys.stdin); print('ok' if d.get('status') in ('ok','healthy') else 'fail')" 2>/dev/null || echo "fail")

log "  Web (Yii2):  HTTP $HTTP_CODE"
log "  API (FastAPI): $API_CODE"

if [ "$HTTP_CODE" != "200" ] || [ "$API_CODE" != "ok" ]; then
  log "!!! HEALTH CHECK FAILED — ROLLING BACK !!!"

  # Откат
  log "Restoring config..."
  cp "$BACKUP_DIR/docker-compose.yml" "$PROJECT_DIR/docker-compose.yml"

  log "Restarting services..."
  docker compose up -d --no-deps fastapi yii2 nginx

  # Восстановление БД если нужно
  if [ -s "$BACKUP_DIR/db.sql" ]; then
    log "Restoring database..."
    docker exec -i testplatform_mysql mysql \
      -u root -p"$MYSQL_ROOT_PASSWORD" testplatform \
      < "$BACKUP_DIR/db.sql"
    log "Database restored: OK"
  fi

  log "=== ROLLBACK COMPLETE ==="
  exit 1
fi

log "=== DEPLOY SUCCESSFUL ==="
log "Backup saved to: $BACKUP_DIR"
