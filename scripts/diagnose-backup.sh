#!/bin/bash

echo "🔍 ДИАГНОСТИКА БЭКАПА testplatform"
echo "=================================="
echo "Дата: $(date)"
echo "Хост: $(hostname)"
echo ""

# 1. Проверка Docker
echo "1. Docker Compose"
if command -v docker compose >/dev/null 2>&1; then
    echo "✅ docker compose найден"
    docker compose -f /home/yaroslav/testplatform/docker-compose.yml ps --format "table {{.Name}}\t{{.Status}}\t{{.Image}}"
else
    echo "❌ docker compose НЕ найден"
fi
echo ""

# 2. Проверка контейнера MySQL
echo "2. MySQL контейнер"
if docker ps --filter "name=testplatform_mysql" --format "table {{.Names}}\t{{.Status}}" | grep -q testplatform_mysql; then
    echo "✅ контейнер testplatform_mysql запущен"
    docker inspect testplatform_mysql --format '   Volume: {{range .Mounts}}{{.Name}} → {{.Destination}}{{end}}'
else
    echo "❌ контейнер testplatform_mysql НЕ найден или не запущен"
fi
echo ""

# 3. Проверка .env
echo "3. Файл .env"
if [ -f /home/yaroslav/testplatform/.env ]; then
    echo "✅ .env существует"
    grep -E 'MYSQL_|GPG_BACKUP_PASSWORD' /home/yaroslav/testplatform/.env || echo "   (переменные не найдены)"
else
    echo "❌ .env НЕ найден!"
fi
echo ""

# 4. Проверка rclone
echo "4. rclone"
if command -v rclone >/dev/null 2>&1; then
    echo "✅ rclone установлен ($(rclone version | head -n1))"
    if rclone listremotes | grep -q b2-testplatform; then
        echo "✅ remote b2-testplatform настроен"
        rclone lsd b2-testplatform: --fast-list | head -5 || echo "   (не удалось прочитать бакеты)"
    else
        echo "❌ remote b2-testplatform НЕ настроен"
    fi
else
    echo "❌ rclone НЕ установлен"
fi
echo ""

# 5. Проверка прав и папок
echo "5. Права и папки"
mkdir -p /tmp/backups
touch /tmp/backups/test.txt 2>/dev/null && rm -f /tmp/backups/test.txt && echo "✅ /tmp/backups доступна для записи" || echo "❌ /tmp/backups НЕ доступна для записи"
echo ""

echo "=================================="
echo "✅ Диагностика завершена. Скопируй ВЕСЬ вывод выше и пришли мне."
