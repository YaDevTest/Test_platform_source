# Переполнение диска

## Симптомы
- Контейнеры падают с ошибкой no space left on device
- MySQL не может записать данные
- Grafana алерт disk usage > 90%

## Диагностика

Посмотреть заполненность диска:

    df -h

Найти что занимает место:

    du -sh /var/lib/docker/*

Посмотреть размер Docker логов:

    du -sh /var/lib/docker/containers/*/*-json.log | sort -rh | head -10

Посмотреть размер volumes:

    docker system df

## Решение

Очистить неиспользуемые Docker объекты:

    docker system prune -f

Очистить логи конкретного контейнера:

    truncate -s 0 /var/lib/docker/containers/<id>/<id>-json.log

Очистить все логи разом:

    truncate -s 0 /var/lib/docker/containers/*/*-json.log

Ограничить размер логов в docker-compose.yml для каждого сервиса:

    logging:
      driver: json-file
      options:
        max-size: 10m
        max-file: 3

## Проверка

    df -h
    docker system df

## Воспроизведение на стенде
http://192.168.100.20/stand.html — сценарий Заполнение диска
