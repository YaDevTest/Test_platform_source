# Рост latency

## Симптомы
- Grafana показывает рост времени ответа выше 500ms
- Пользователи жалуются на медленную работу
- Nginx логи показывают долгие запросы

## Диагностика

Посмотреть загрузку CPU и памяти контейнеров:

    docker stats --no-stream | grep testplatform

Проверить медленные запросы MySQL:

    docker exec testplatform_mysql mysql -u root -p$MYSQL_ROOT_PASSWORD -e "SHOW PROCESSLIST;"

Проверить очередь Redis:

    docker exec testplatform_redis redis-cli -a $REDIS_PASSWORD info stats | grep instantaneous

Посмотреть Nginx access лог:

    docker logs testplatform_nginx --tail 50

Проверить метрики FastAPI в Prometheus:

    http://192.168.100.20:9090 — запрос http_request_duration_seconds

## Решение

Если перегружен MySQL — найти тяжёлые запросы:

    docker exec testplatform_mysql mysql -u root -p$REDIS_PASSWORD -e "SHOW FULL PROCESSLIST;"

Перезапустить перегруженный сервис:

    cd /home/yaroslav/testplatform
    docker compose restart <имя>

Если проблема в Redis — проверить количество ключей:

    docker exec testplatform_redis redis-cli -a $REDIS_PASSWORD dbsize

## Проверка

    docker stats --no-stream | grep testplatform
    curl -w "%{time_total}" http://192.168.100.20/api/health

## Воспроизведение на стенде
http://192.168.100.20/stand.html — сценарий Нагрузка на БД
