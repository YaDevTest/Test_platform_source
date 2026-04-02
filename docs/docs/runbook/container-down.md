# Падение контейнера

## Симптомы
- Сервис недоступен (502/503 от Nginx)
- Grafana показывает отсутствие метрик по сервису
- Алерт в Grafana container down

## Диагностика

Проверить какие контейнеры упали:

    docker ps -a | grep testplatform

Посмотреть логи упавшего контейнера:

    docker logs testplatform_<имя> --tail 100

Посмотреть причину остановки:

    docker inspect testplatform_<имя> | grep -A 10 State

## Решение

Перезапустить контейнер:

    cd /home/yaroslav/testplatform
    docker compose restart <имя>

Если не помогло — пересоздать:

    docker compose up -d <имя>

## Проверка

    docker ps | grep testplatform_<имя>
    curl http://192.168.100.20/api/health

## Воспроизведение на стенде
http://192.168.100.20/stand.html — сценарий Падение контейнера
