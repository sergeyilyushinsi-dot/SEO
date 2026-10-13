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
Для остального нужны ключи. **Не присылайте токены в чат** — добавьте их как переменные окружения
в настройках облачного окружения Claude Code (или в файл `.env` при локальном запуске, шаблон — `.env.example`).

### 1. Яндекс.Вебмастер и Яндекс.Метрика — `YANDEX_OAUTH_TOKEN`, `YANDEX_METRIKA_COUNTER`

1. Зайдите на https://oauth.yandex.ru/ под аккаунтом, у которого есть доступ к am-tech.ru в Вебмастере
   и к счётчику Метрики → «Создать приложение».
2. Платформа «Веб-сервисы», Redirect URI: `https://oauth.yandex.ru/verification_code`.
3. Доступы (только чтение): **Яндекс.Вебмастер → «Получение информации о сайтах» (`webmaster:hostinfo`)** и
   **Яндекс.Метрика → «Получение статистики, чтение параметров своих и доверенных счётчиков» (`metrika:read`)**.
4. Откройте `https://oauth.yandex.ru/authorize?response_type=token&client_id=<ClientID приложения>` — получите
   токен (действует около года).
5. Номер счётчика Метрики — в списке счётчиков (https://metrika.yandex.ru/list).

### 2. Wordstat и позиции в выдаче — `YANDEX_CLOUD_API_KEY`, `YANDEX_CLOUD_FOLDER_ID`

Рекомендуемый вариант — Yandex Cloud Search API: один ключ даёт и Wordstat, и выдачу Яндекса для снятия позиций.

1. https://console.yandex.cloud/ → платёжный аккаунт → каталог; скопируйте **ID каталога**.
2. Сервисный аккаунт с ролью `search-api.webSearch.user` → создайте **API-ключ** (область `yc.search-api.execute`).
3. Запросы к Search API платные (тарифы — в документации Yandex Cloud); агент предупреждает перед большими прогонами.

Альтернатива только для Wordstat — API Wordstat (`WORDSTAT_OAUTH_TOKEN`): OAuth-приложение + заявка в
поддержку Яндекс.Директа на доступ (см. справку Wordstat → API).

### 3. Сетевой доступ окружения

Если окружение Claude Code работает с ограниченным доступом в интернет, добавьте в разрешённые домены
(настройки окружения → Network access, https://code.claude.com/docs/en/cloud-environments#network-access):

```
api.webmaster.yandex.net
api-metrika.yandex.net
searchapi.api.cloud.yandex.net
api.wordstat.yandex.net
<домены конкурентов>
```

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
