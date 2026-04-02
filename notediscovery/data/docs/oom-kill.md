# OOM Kill

## Симптомы
- Контейнер неожиданно перезапустился
- В Grafana видно резкий рост потребления памяти перед падением
- Алерт container restarted

## Диагностика

Проверить был ли OOM kill:

    dmesg | grep -i oom | tail -20

Посмотреть сколько раз контейнер перезапускался:

    docker inspect testplatform_<имя> | grep RestartCount

Посмотреть потребление памяти сейчас:

    docker stats --no-stream | grep testplatform

Посмотреть логи перед падением:

    docker logs testplatform_<имя> --tail 50

## Решение

Если разовый инцидент — перезапустить:

    cd /home/yaroslav/testplatform
    docker compose restart <имя>

Если повторяется — добавить memory limit в docker-compose:

    mem_limit: 512m
    memswap_limit: 512m

Применить изменения:

    docker compose up -d <имя>

## Проверка

    docker stats --no-stream | grep testplatform_<имя>

## Воспроизведение на стенде
http://192.168.100.20/stand.html — сценарий OOM kill
