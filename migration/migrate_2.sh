#!/bin/bash
set -e

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"; }

log "=== STEP 2: Deploy ==="

# 1. Установить rclone
if ! command -v rclone &> /dev/null; then
  log "Installing rclone..."
  curl https://rclone.org/install.sh | sudo -n bash 2>/dev/null && \
    log "rclone installed" || {
    log "rclone install skipped (sudo requires password)"
    echo ""
    echo "Press ENTER to install rclone manually (you will be asked for sudo password)..."
    read -r
    curl https://rclone.org/install.sh | sudo bash && \
      log "rclone installed" || log "rclone install failed — run manually later"
  }
else
  log "rclone already installed"
fi

# 3. Распаковать главный архив
log "Extracting full_backup.tar.gz..."
cd ~
tar -xzf full_backup.tar.gz
log "OK"

# 4. Распаковать конфиги проекта
log "Extracting testplatform config..."
tar -xzf testplatform_backup.tar.gz
log "OK"

# 3. Первый запуск стека
log "Starting stack..."
cd ~/testplatform
docker compose up -d
log "Waiting for MySQL and Redis to become healthy..."
sleep 30
docker compose ps

# 4. Восстановить Yii2
log "Restoring Yii2 app..."
docker cp ~/yii2_app.tar.gz testplatform_yii2:/tmp/
docker exec testplatform_yii2 tar -xzf /tmp/yii2_app.tar.gz \
  -C /var/www/html --skip-old-files
log "Yii2 OK"

# 5. Восстановить MySQL
log "Restoring MySQL..."
docker exec -i testplatform_mysql mysql \
  -uroot -prootpass123 testplatform < ~/testplatform_db.sql
log "MySQL OK"

# 6. Восстановить volumes
log "Restoring volumes..."
for VOLUME in redis_data grafana_data termix_data; do
  docker run --rm \
    -v testplatform_${VOLUME}:/data \
    -v ~/:/backup \
    alpine tar -xzf /backup/${VOLUME}.tar.gz -C /data
  log "  $VOLUME — OK"
done

# 7. Перезапустить стек
log "Restarting stack..."
docker compose restart
sleep 15
docker compose ps

# 8. Установить rclone
if ! command -v rclone &> /dev/null; then
  log "Installing rclone..."
  curl https://rclone.org/install.sh | sudo -n bash 2>/dev/null && \
    log "rclone installed" || {
    log "rclone install skipped (sudo requires password)"
    echo ""
    echo "Press ENTER to install rclone manually (you will be asked for sudo password)..."
    read -r
    curl https://rclone.org/install.sh | sudo bash && \
      log "rclone installed" || log "rclone install failed — run manually later"
  }
else
  log "rclone already installed"
fi

# 9. Health checks
log "Running health checks..."
sleep 5
HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost)
API_CODE=$(curl -s http://localhost/api/health 2>/dev/null | \
  python3 -c "import sys,json; d=json.load(sys.stdin); print('ok' if d.get('status') in ('ok','healthy') else 'fail')" 2>/dev/null || echo "fail")
log "  Web (Yii2):    HTTP $HTTP_CODE"
log "  API (FastAPI): $API_CODE"

# 10. deploy.sh
log "Running deploy.sh..."
chmod +x ~/testplatform/deploy.sh
cd ~/testplatform
./deploy.sh

log "=== MIGRATION COMPLETE ==="