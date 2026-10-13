# SEO-агент для am-tech.ru

ИИ-агент на базе Claude Code для продвижения **am-tech.ru** в Яндексе: аудит сайта, сбор и кластеризация
семантики (Wordstat), сверка с конкурентами, отчёты по позициям ТОП-3/10/20/30 и тексты под кластеры запросов.

- Инструкции агента: [`CLAUDE.md`](CLAUDE.md)
- Сценарии: `.claude/skills/` — `/seo-audit`, `/keyword-research`, `/competitor-gap`, `/positions-report`, `/write-article`
- Сборщики данных: `python -m seo --help`
- Паспорт проекта: [`config/project.yaml`](config/project.yaml), конкуренты: [`config/competitors.yaml`](config/competitors.yaml)

## Как пользоваться

Откройте сессию Claude Code в этом репозитории и пишите задачи обычным языком, например:

- «Проведи аудит сайта и дай список задач для программиста»
- «Вот конкуренты: …, сравни с нами и найди, чего не хватает»
- «Собери семантику по SLM 3D-принтерам и предложи новые направления»
- «Отчёт по позициям за месяц: что в ТОП-10, что можно быстро дотянуть»
- «Напиши статью под кластер „3D-сканер для реверс-инжиниринга“»

## Доступы

Без доступов работают: краулинг и аудит сайта, разбор страниц конкурентов, gap-анализ.
Для остального нужны ключи Яндекса. **Не присылайте токены в чат.**

Несекретные идентификаторы лежат в `config/project.yaml → yandex`: номер счётчика Метрики (`93773111`, он виден
в коде сайта) и ID каталога Yandex Cloud — его можно прислать в чат или вписать самому.

### Шаг 1. Получить ключи

**OAuth-токен для Вебмастера и Метрики** (один токен на оба сервиса, только чтение):
1. https://oauth.yandex.ru/ под аккаунтом, у которого есть доступ к am-tech.ru в Вебмастере и к счётчику Метрики →
   «Создать приложение» → «Для доступа к API или отладки».
2. Права: **Яндекс.Вебмастер → «Получение информации о сайтах» (`webmaster:hostinfo`)** и
   **Яндекс.Метрика → «Получение статистики…» (`metrika:read`)**.
3. Откройте `https://oauth.yandex.ru/authorize?response_type=token&client_id=<ClientID приложения>` — на странице
   будет токен (действует около года).

**API-ключ Yandex Cloud для Wordstat и выдачи:**
1. https://console.yandex.cloud/ → платёжный аккаунт → каталог; скопируйте **ID каталога**.
2. Сервисный аккаунт с ролью `search-api.webSearch.user` → создайте **API-ключ** (область `yc.search-api.execute`).
3. Запросы к Search API платные; агент предупреждает перед большими прогонами.

Альтернатива только для Wordstat — API Wordstat (`WORDSTAT_OAUTH_TOKEN`): OAuth-приложение + заявка в поддержку
Яндекс.Директа.

### Шаг 2. Положить ключи в облачное окружение Claude Code

claude.ai/code → меню облачного окружения в заголовке сессии → **Edit**
([документация](https://code.claude.com/docs/en/cloud-environments)). Изменения подхватит новая сессия.

**Вариант А — сетевые секреты (Network secrets, планы Pro и Max), рекомендуется.** Ключ хранится вне контейнера:
прокси окружения сам подставляет его в запросы к указанным хостам, агент ключ не видит. **Add secret**, тип Bearer:

| Name | Allowed websites | Custom header: Name / Prefix / Value |
|---|---|---|
| Yandex Metrika | `api-metrika.yandex.net` | `Authorization` / `OAuth` / OAuth-токен |
| Yandex Webmaster | `api.webmaster.yandex.net` | `Authorization` / `OAuth` / тот же OAuth-токен |
| Yandex Cloud Search API | `searchapi.api.cloud.yandex.net` | `Authorization` / `Api-Key` / API-ключ |

Переменные окружения для ключей при этом не нужны: если `YANDEX_OAUTH_TOKEN` / `YANDEX_CLOUD_API_KEY` не заданы,
скрипты не ставят заголовок сами, его добавляет прокси.

**Вариант Б — переменные окружения (если раздела Network secrets нет, например на планах Team/Enterprise).**
В поле Environment variables, формат `.env`:

```
YANDEX_OAUTH_TOKEN=...
YANDEX_CLOUD_API_KEY=...
YANDEX_CLOUD_FOLDER_ID=...
```

Значения переменных видны всем, кто пользуется этим окружением, и командам внутри сессии.

**Секреты GitHub (Settings → Secrets) сюда не подходят:** они доступны только GitHub Actions, в сессию Claude Code
не передаются. Использовать их можно, если сбор данных вынести в запланированный workflow GitHub Actions,
который сам выгружает CSV в репозиторий, — агент тогда только анализирует файлы.

### Известное ограничение: Вебмастер

На 13.10.2026 `api.webmaster.yandex.net` обрывает соединение (reset на этапе TLS) при запросах из облачного
окружения Claude Code, а `api-metrika.yandex.net` и `searchapi.api.cloud.yandex.net` отвечают нормально. Если после
добавления токена Вебмастер не заработает: запустите `python -m seo wm-queries` локально (с `.env`) и закоммитьте
`data/positions/webmaster_*.csv`, либо выгрузите отчёт «Поисковые запросы» из интерфейса Вебмастера в `data/webmaster/`.

### Сетевой доступ окружения

Если окружение Claude Code работает с ограниченным доступом в интернет, добавьте в разрешённые домены
(настройки окружения → Network access, https://code.claude.com/docs/en/cloud-environments#network-access):

```
api.webmaster.yandex.net
api-metrika.yandex.net
searchapi.api.cloud.yandex.net
api.wordstat.yandex.net
3dvision.su
lsystems.ru
www.lsystems.ru
onsint.ru
vilitek.ru
amfastform.ru
www.amfastform.ru
3dlam.com
lider-3d.ru
i3d.ru
industry3d.ru
amsolid.ru
scantech-3d.ru
fhzl.ru
```

Новых конкурентов добавляйте и в `config/competitors.yaml`, и в разрешённые домены.

## Установка (локально)

```bash
pip install -r requirements.txt
cp .env.example .env   # и заполните ключи
python -m seo crawl && python -m seo audit
python -m pytest -q tests
```

Модули API Яндекса (`seo/webmaster.py`, `seo/metrika.py`, `seo/wordstat.py`, `seo/serp.py`) написаны по
официальной документации, но ещё не проверены на живых ключах — первый запуск после выдачи доступов
покажет, нужны ли правки.
