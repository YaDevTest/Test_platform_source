# Grafana Alerting — YAML инструкция

# Grafana Alerting через YAML

Все алерты, контакты и политики уведомлений описываются декларативно через три YAML-файла. Grafana читает их автоматически при старте через механизм **provisioning**.

## Структура файлов

```
/root/testplatform/monitoring/grafana/provisioning/alerting/
├── rules.yml                  # правила срабатывания
├── contact-points.yml         # куда отправлять (Telegram)
└── notification-policies.yml  # когда и как часто
```

---

# 1. Синтаксис

## rules.yml — правила алертов

```yaml
apiVersion: 1

groups:
  - orgId: 1
    name: testplatform          # название группы (произвольное)
    folder: TestPlatform        # папка в Grafana UI
    interval: 1m                # как часто проверять условие

    rules:
      - uid: alert-cpu          # уникальный ID (произвольный, но уникальный)
        title: "CPU > 85%"      # название алерта (попадает в .Labels.alertname)
        condition: C            # какой refId является финальным условием

        data:
          - refId: A            # шаг 1: запрос данных
            relativeTimeRange: {from: 300, to: 0}   # последние 5 минут
            datasourceUid: PBFA97CFB590B2093         # UID источника данных (Prometheus)
            model:
              expr: 100 - (avg(rate(node_cpu_seconds_total{mode="idle"}[5m])) * 100)
              refId: A

          - refId: C            # шаг 2: условие срабатывания
            datasourceUid: __expr__   # встроенный движок выражений Grafana
            model:
              type: classic_conditions
              refId: C
              conditions:
                - evaluator: {type: gt, params: [85]}  # gt=больше, lt=меньше, eq=равно
                  operator: {type: and}
                  query: {params: [A]}                 # берём данные из refId A
                  reducer: {type: last}                # берём последнее значение

        for: 2m                 # держаться 2 минуты перед firing (0s = сразу)
        noDataState: NoData     # что делать если нет данных: NoData / OK / Alerting
        execErrState: Error     # что делать при ошибке выполнения

        annotations:
          summary: "CPU перегрузка"   # текст, попадает в .Annotations.summary

        isPaused: false         # true = алерт выключен
```

### Типы evaluator (условий)

| type | значение |
| --- | --- |
| `gt` | больше |
| `lt` | меньше |
| `gte` | больше или равно |
| `lte` | меньше или равно |
| `eq` | равно |
| `within_range` | в диапазоне |
| `outside_range` | вне диапазона |

### noDataState — важно

Если Prometheus не отдаёт метрики — алерт падает в `noDataState`. Текущее значение `NoData` вызывает уведомления вида `🚨 DatasourceNoData`. Чтобы не спамило при отсутствии данных — поставь `OK`.

---

## contact-points.yml — куда отправлять

```yaml
apiVersion: 1

contactPoints:
  - orgId: 1
    name: telegram              # имя, на него ссылается notification-policies
    receivers:
      - uid: telegram-receiver  # уникальный ID
        type: telegram
        settings:
          bottoken: "<токен от BotFather>"
          chatid: "<chat_id>"
          message: |            # шаблон сообщения (Go template)
            {{ range .Alerts }}
            🚨 {{ .Labels.alertname }}
            📋 {{ .Annotations.summary }}
            🔴 Статус: {{ .Status }}
            {{ end }}
```

### Переменные шаблона

| переменная | что содержит |
| --- | --- |
| `.Labels.alertname` | название алерта (из title) |
| `.Annotations.summary` | текст из annotations.summary |
| `.Status` | `firing` или `resolved` |
| `.StartsAt` | время начала |
| `.EndsAt` | время завершения |
| `.GeneratorURL` | ссылка на алерт в Grafana |

---

## notification-policies.yml — когда и как часто

```yaml
apiVersion: 1

policies:
  - orgId: 1
    receiver: telegram          # имя из contact-points.yml
    group_wait: 30s             # ждать перед первой отправкой (группировка)
    group_interval: 5m          # интервал между группами
    repeat_interval: 1h         # повторять если алерт не resolved
```

---

# 2. Используемые команды

## Найти файлы конфигурации

```bash
ls /root/testplatform/monitoring/grafana/provisioning/alerting/
```

## Редактировать файл

```bash
nano /root/testplatform/monitoring/grafana/provisioning/alerting/rules.yml
# или
vim /root/testplatform/monitoring/grafana/provisioning/alerting/rules.yml
```

## Проверить синтаксис YAML перед деплоем

```bash
python3 -c "import yaml, sys; yaml.safe_load(open(sys.argv[1]))" rules.yml && echo OK
```

## Посмотреть текущие контейнеры

```bash
docker ps | grep grafana
```

---

# 3. Деплой — как применить изменения

## Шаг 1 — Отредактировать нужный файл

```bash
nano /root/testplatform/monitoring/grafana/provisioning/alerting/rules.yml
```

## Шаг 2 — Перезапустить контейнер Grafana

```bash
cd /root/testplatform
docker-compose restart grafana
```

## Шаг 3 — Проверить что контейнер поднялся

```bash
docker ps | grep grafana
# ожидаем статус: Up
```

## Шаг 4 — Посмотреть логи (если что-то пошло не так)

```bash
docker logs testplatform_grafana --tail 50
# или следить в реальном времени:
docker logs testplatform_grafana -f
```

## Шаг 5 — Проверить в Grafana UI

Открыть `http://178.104.117.86:3000` → **Alerting** → **Alert rules** — новые правила должны появиться автоматически.

---

# Типичные проблемы

## 🚨 DatasourceNoData в сообщениях

**Причина:** Prometheus не отдаёт метрики (node_exporter недоступен), алерт не может выполнить запрос.

**Решение 1 — поменять поведение при NoData:**

```yaml
noDataState: OK   # вместо NoData
```

**Решение 2 — проверить node_exporter:**

```bash
docker ps | grep exporter
curl -s http://localhost:9100/metrics | head -5
```

## Изменения не применились после рестарта

Проверить что файл сохранился и синтаксис корректен:

```bash
python3 -c "import yaml, sys; yaml.safe_load(open(sys.argv[1]))" /root/testplatform/monitoring/grafana/provisioning/alerting/rules.yml && echo OK
docker logs testplatform_grafana --tail 20 | grep -i error
```

## Найти UID источника данных (datasourceUid)

Grafana UI → **Connections** → **Data sources** → выбрать источник → в URL будет UID, например `PBFA97CFB590B2093`.

[Мониторинг — путь от сервера до Telegram (обзор)](https://www.notion.so/Telegram-33055804ab2481edbff2c8d97054c3ff?pvs=21)