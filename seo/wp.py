"""Правки am-tech.ru через REST API WordPress/WooCommerce: резервная копия, запись, проверка на сайте.

Авторизация — пароль приложения WordPress пользователя агента: сетевым секретом окружения (заголовок
Authorization: Basic …, его подставляет прокси) или в WP_USER + WP_APP_PASSWORD. Что можно менять — README,
раздел «Доступ к сайту». Правки вносятся только после подтверждения во вкладке «Правки на сайте».

Правка — {"type"?, "slug", "field", "value", "old"?}. type: product (по умолчанию), category, news, page, case, video.
У новостей, страниц, кейсов и видеообзоров поля Yoast (title, description, noindex) пишутся через meta записи —
это работает после того, как разработчик откроет их для API (задача 41 плана, код в README).
"""
import html
import json
import re
import time
from datetime import datetime

from . import config, crawler, http
from .config import DATA_DIR

BACKUP_DIR = DATA_DIR / "wp_backup"

# Тип объекта → коллекция REST API и метод записи.
TYPES = {
    "product": ("wc/v3/products", "PUT"),
    "category": ("wp/v2/product_cat", "POST"),
    "news": ("wp/v2/news", "POST"),
    "page": ("wp/v2/pages", "POST"),
    "case": ("wp/v2/cases", "POST"),
    "video": ("wp/v2/video_reviews", "POST"),
}
POST_TYPES = ("news", "page", "case", "video")

# Товар: поле правки → мета-поле Yoast (доступно в meta_data WooCommerce).
META = {"title": "_yoast_wpseo_title", "description": "_yoast_wpseo_metadesc",
        "noindex": "_yoast_wpseo_meta-robots-noindex"}
# HTML-поля. Меняется фрагмент: "old" — что заменить (текст или точный кусок HTML), "value" — на что;
# пустой "value" удаляет фрагмент. В short_description без "old" заменяется весь текст внутри разметки,
# в body (описание товара или категории) без "old" заменяется весь HTML.
# У новостей, страниц и кейсов: content — HTML записи, elementor — данные конструктора Elementor (там ищется
# и обычный, и экранированный вид адреса: "/a/" и "\/a\/"). content с "whole": true заменяет весь текст записи,
# featured_media — id главного изображения.
HTML_FIELDS = {"short_description": "short_description", "body": "description", "content": "content"}
PLAIN_FIELDS = ("name", "status")
# Рубрики товара: "value" — список id категорий WooCommerce, заменяет текущие.
LIST_FIELDS = ("categories",)
FIELDS = (*META, *HTML_FIELDS, "elementor", *PLAIN_FIELDS, *LIST_FIELDS, "attribute")
# Title и description категорий: Yoast хранит их вне категории, в API их открывает сниппет WPCode
# «Поля Yoast категорий для REST API» — поле yoast_seo {"title", "desc"}.
CATEGORY_YOAST = {"title": "title", "description": "desc"}
# Любое другое мета-поле товара — «meta:<ключ>», например meta:_custom_advantages (блок «Преимущества»).
# В нём меняется фрагмент, как в HTML-полях.
META_PREFIX = "meta:"


def text(value) -> str:
    """Видимый текст: без тегов, стилей и скриптов, с раскрытыми сущностями и схлопнутыми пробелами."""
    visible = re.sub(r"<(style|script)\b.*?</\1\s*>", " ", str(value or ""), flags=re.S | re.I)
    return " ".join(html.unescape(re.sub(r"<[^>]+>", " ", visible)).split())


class WordPress:
    def __init__(self, domain: str | None = None):
        self.base = f"https://{domain or config.own_domain()}"
        self.sess = http.session()
        user, password = config.optional_env("WP_USER"), config.optional_env("WP_APP_PASSWORD")
        if user and password:
            self.sess.auth = (user, password)

    def api(self, method: str, path: str, **kw):
        resp = self.sess.request(method, f"{self.base}/wp-json/{path}", timeout=90, **kw)
        return http.check(resp, "WordPress")

    def me(self) -> dict:
        return self.api("GET", "wp/v2/users/me", params={"context": "edit"})

    def find(self, kind: str, slug: str) -> dict:
        params = {"slug": slug, "context": "edit"}
        if kind != "category":
            params["status"] = "any"
        found = self.api("GET", TYPES[kind][0], params=params)
        if not found:
            raise http.ApiError(f"WordPress: {kind} {slug} не найден")
        return found[0]

    def product(self, slug: str) -> dict:
        return self.find("product", slug)

    def update(self, kind: str, item_id: int, body: dict) -> dict:
        path, method = TYPES[kind]
        updated = self.api(method, f"{path}/{item_id}", json=body)
        # wp/v2 отвечает без raw-полей — перечитываем, чтобы сравнить сохранённое.
        return updated if kind == "product" else self.api("GET", f"{path}/{item_id}", params={"context": "edit"})

    def resave(self, kind: str, item_id: int) -> None:
        """Пересохраняет запись без изменений, как кнопка «Обновить» в админке: срабатывает save_post,
        Yoast пересобирает title/description, кэш страницы сбрасывается. WooCommerce при правке одних мета-полей
        запись не пересохраняет."""
        path = "wp/v2/product" if kind == "product" else TYPES[kind][0]
        self.api("POST", f"{path}/{item_id}", json={})


# Разметка, которую WordPress вырезает при записи через API (проверено 16.10.2026 по копиям из data/wp_backup):
# - описание категории на любой записи (имя, Yoast, текст) проходит через wp_filter_kses — остаются только ссылки и
#   <strong>, пропадают заголовки, абзацы, списки. Сниппет «Описания категорий: HTML» (README) заменяет его на
#   wp_filter_post_kses и отдаёт в API поле html_description;
# - WooCommerce API пропускает описание товара через wp_filter_post_kses — пропадают <style>, <svg>, <iframe>,
#   поэтому описание товара пишется через API записей WordPress (wp/v2/product), там оно сохраняется как есть.
STRIPPED_IN_POSTS = {"style", "script", "svg", "iframe", "form", "input", "button", "select", "textarea",
                     "object", "embed", "noscript", "link", "meta", "canvas"}
TERM_KSES_ALLOWED = {"a", "abbr", "acronym", "b", "blockquote", "cite", "code", "del", "em", "i", "q", "s",
                     "strike", "strong"}


def tag_counts(html_text: str) -> dict[str, int]:
    counts: dict[str, int] = {}
    for tag in re.findall(r"<([a-zA-Z][a-zA-Z0-9]*)\b", html_text or ""):
        counts[tag.lower()] = counts.get(tag.lower(), 0) + 1
    return counts


def html_fields(kind: str, item: dict) -> dict[str, str]:
    """HTML объекта, который WordPress заново пропускает через фильтры при записи."""
    if kind == "product":
        return {"description": item.get("description") or "", "short_description": item.get("short_description") or ""}
    if kind == "category":
        return {"description": item.get("description") or ""}
    return {"content": (item.get("content") or {}).get("raw", "")}


def markup_risk(kind: str, item: dict, body: dict) -> list[str]:
    """Теги, которые пропадут из описания категории при записи. Пусто — писать можно."""
    if kind != "category":
        return []
    tags = tag_counts(body.get("description", item.get("description") or ""))
    if item.get("html_description"):
        return sorted(t for t in tags if t in STRIPPED_IN_POSTS)
    return sorted(t for t in tags if t not in TERM_KSES_ALLOWED)


def layout_risk(kind: str, item: dict, body: dict) -> bool:
    """Правка добавляет обёртку class="table-responsive": тема на экранах до 768 px ставит ей overflow:hidden!important,
    широкая таблица растягивает колонку и обрезает текст справа (проверка 18.10.2026). Нужна
    <div style="overflow:auto;width:0;min-width:100%">."""
    current = html_fields(kind, item)
    before = sum(v.count("table-responsive") for v in current.values())
    return sum(body.get(f, v).count("table-responsive") for f, v in current.items()) > before


def markup_lost(expected: dict[str, str], stored: dict[str, str]) -> dict[str, int]:
    """Сколько тегов каждого вида пропало после записи."""
    lost: dict[str, int] = {}
    for field, html_text in expected.items():
        now = tag_counts(stored.get(field, ""))
        for tag, n in tag_counts(html_text).items():
            if n > now.get(tag, 0):
                lost[tag] = lost.get(tag, 0) + n - now.get(tag, 0)
    return lost


def kind_of(ch: dict) -> str:
    return ch.get("type", "product")


def meta_key(field: str) -> str | None:
    return META.get(field) or (field[len(META_PREFIX):] if field.startswith(META_PREFIX) else None)


def item_url(item: dict) -> str:
    return item.get("permalink") or item.get("link") or ""


def get_value(item: dict, field: str, kind: str = "product"):
    if kind in POST_TYPES:
        if field == "content":
            return item["content"]["raw"]
        if field == "elementor":
            return (item.get("meta") or {}).get("_elementor_data") or ""
        if field == "name":
            return item["title"]["raw"]
        if field in META:
            return (item.get("meta") or {}).get(META[field], "")
        return item.get(field) or ""
    if kind == "category" and field == "body":
        return item.get("description") or ""
    if kind == "category" and field in CATEGORY_YOAST:
        return (item.get("yoast_seo") or {}).get(CATEGORY_YOAST[field], "")
    if field == "attribute":
        return {a["name"]: a["options"] for a in item.get("attributes", [])}
    key = meta_key(field)
    if key:
        return next((str(m["value"]) for m in item.get("meta_data", []) if m["key"] == key), "")
    if field in LIST_FIELDS:
        return sorted(c["id"] for c in item.get(field) or [])
    return item.get(HTML_FIELDS.get(field, field)) or ""


def is_fragment(ch: dict) -> bool:
    """Правка заменяет кусок поля, а не всё поле."""
    field = ch["field"]
    return (field.startswith(META_PREFIX) or field in ("content", "elementor") or
            (field in HTML_FIELDS and (bool(ch.get("old")) or field == "short_description")))


def _replace_text(item: dict, field: str, raw: str, old: str, value: str, escape: bool = True) -> str:
    if not old or old not in raw:  # текст разбит тегами или записан сущностями — вслепую не перезаписываем
        raise ValueError(f"{item['slug']}: в {field} нет текста «{old[:60]}», правка не применена")
    return raw.replace(old, html.escape(value, quote=False) if escape else value, 1)


def _url_forms(url: str) -> list[str]:
    """Адрес как целое значение атрибута или строки JSON: в кавычках, с обычными и экранированными «/»."""
    forms = []
    for u in (url, url.replace("/", "\\/")):
        forms += [f'"{u}"', f"'{u}'", f'\\"{u}\\"']
    return forms


def replace_link(item: dict, field: str, raw: str, old: str, new: str) -> str:
    """Меняет адрес ссылки везде, где он стоит целым значением (href="…", "url":"…"), а не частью другого адреса."""
    count = 0
    for o, n in zip(_url_forms(old), _url_forms(new)):
        count += raw.count(o)
        raw = raw.replace(o, n)
    if not count:
        raise ValueError(f"{item['slug']}: в {field} нет ссылки «{old}», правка не применена")
    return raw


def _replace_elementor(item: dict, raw: str, old: str, value: str) -> str:
    """Заменяет все вхождения фрагмента в JSON Elementor: адреса там бывают и с «\\/», и с «/»."""
    variants = [(old, value), (old.replace("/", "\\/"), value.replace("/", "\\/"))]
    found = [(o, v) for o, v in variants if o and o in raw]
    if not found:
        raise ValueError(f"{item['slug']}: в данных Elementor нет «{old[:60]}», правка не применена")
    for o, v in found:
        raw = raw.replace(o, v)
    return raw


def build_update(item: dict, changes: list[dict], kind: str = "product") -> dict:
    """Тело запроса на запись для одного объекта. changes: [{"field", "value", "old"?}]."""
    body, meta = {}, []
    for ch in changes:
        field, value = ch["field"], ch["value"]
        if ch.get("link"):  # замена адреса ссылки в HTML или данных Elementor
            api_field = {"content": "content", "body": "description", "short_description": "short_description",
                         "elementor": "_elementor_data"}[field]
            if field == "elementor":
                raw = body.get("meta", {}).get(api_field, get_value(item, "elementor", kind))
                body.setdefault("meta", {})[api_field] = replace_link(item, field, raw, ch["old"], value)
            else:
                raw = body.get(api_field, get_value(item, field, kind))
                body[api_field] = replace_link(item, field, raw, ch["old"], value)
            continue
        if kind in POST_TYPES:
            if field == "content" and ch.get("whole"):  # весь текст записи (склейка дублей)
                body["content"] = value
            elif field == "content":
                raw = body.get("content", item["content"]["raw"])
                body["content"] = _replace_text(item, field, raw, ch.get("old", ""), value, escape=False)
            elif field == "elementor":
                raw = body.get("meta", {}).get("_elementor_data", get_value(item, "elementor", kind))
                body.setdefault("meta", {})["_elementor_data"] = _replace_elementor(item, raw, ch.get("old", ""), value)
            elif field == "name":
                body["title"] = value
            elif field == "status":
                body["status"] = value
            elif field == "featured_media":  # главное изображение записи, id медиафайла
                body["featured_media"] = int(value)
            elif field in META:
                if META[field] not in (item.get("meta") or {}):
                    raise ValueError(f"{item['slug']}: поле Yoast {META[field]} закрыто для API — нужна задача 41")
                body.setdefault("meta", {})[META[field]] = value
            else:
                raise ValueError(f"{kind}: поле {field} не поддерживается (content, elementor, name, status, "
                                 "featured_media, title, description, noindex)")
            continue
        if kind == "category":
            if field in CATEGORY_YOAST:
                if item.get("yoast_seo") is None:
                    raise ValueError(f"{item['slug']}: поля Yoast категорий закрыты для API — нужен сниппет "
                                     "«Поля Yoast категорий для REST API» (задача 49)")
                # Yoast (WPSEO_Taxonomy_Meta::set_values) сбрасывает непереданные поля в пустые: запись одного
                # title стёрла description 14 категорий 16.10.2026. Поэтому title и description пишутся всегда вместе.
                yoast = body.setdefault("yoast_seo", dict(item["yoast_seo"]))
                yoast[CATEGORY_YOAST[field]] = value
            elif field == "name":
                body["name"] = value
            elif field == "body":
                raw = body.get("description", item.get("description") or "")
                body["description"] = (_replace_text(item, field, raw, ch["old"], value, escape=False)
                                       if ch.get("old") else value)
            else:
                raise ValueError(f"category: поле {field} не поддерживается (name, body, title, description)")
            continue
        if field in META:
            found = next((m for m in item.get("meta_data", []) if m["key"] == META[field]), None)
            meta.append({"id": found["id"], "key": found["key"], "value": value} if found
                        else {"key": META[field], "value": value})
        elif field.startswith(META_PREFIX):
            key = meta_key(field)
            found = next((m for m in item.get("meta_data", []) if m["key"] == key), None)
            if not found or not isinstance(found["value"], str):
                raise ValueError(f"{item['slug']}: у товара нет текстового поля {key}")
            if any(c in value for c in "<>&"):  # новый текст экранируется — теги и &nbsp; стали бы видимым текстом
                raise ValueError(f"{item['slug']}: в {field} заменяется только текст, без тегов и сущностей: «{value[:60]}»")
            # Несколько правок одного поля накапливаются: каждая следующая меняет уже исправленный текст.
            prev = next((m for m in meta if m["key"] == key), None)
            raw = prev["value"] if prev else found["value"]
            new = _replace_text(item, field, raw, ch.get("old", ""), value)
            if prev:
                prev["value"] = new
            else:
                meta.append({"id": found["id"], "key": key, "value": new})
        elif field in HTML_FIELDS:
            api_field = HTML_FIELDS[field]
            raw = body.get(api_field, item.get(api_field) or "")
            if field == "body" and not ch.get("old"):
                body[api_field] = value
            elif field == "body":  # в описании товара меняется кусок HTML: ссылка, правило CSS
                body[api_field] = _replace_text(item, field, raw, ch["old"], value, escape=False)
            elif not ch.get("old") and not text(raw):  # пустое краткое описание заполняется HTML целиком
                body[api_field] = value
            else:
                body[api_field] = _replace_text(item, field, raw, ch.get("old") or text(raw), value)
        elif field == "attribute":  # характеристика: "old" — её название, "value" — {"name", "options", "id"?}
            attrs = body.get("attributes") or [dict(a) for a in item.get("attributes", [])]
            found = next((a for a in attrs if a["name"] == ch["old"]), None)
            if not found:
                raise ValueError(f"{item['slug']}: у товара нет характеристики «{ch['old']}»")
            new = {"name": value["name"], "options": list(value["options"]), "visible": found.get("visible", True),
                   "variation": found.get("variation", False), "position": found.get("position", 0)}
            if value.get("id"):  # глобальный атрибут WooCommerce; без id — свой атрибут товара
                new["id"] = int(value["id"])
            attrs[attrs.index(found)] = new
            body["attributes"] = attrs
        elif field in PLAIN_FIELDS:
            body[field] = value
        elif field in LIST_FIELDS:
            body[field] = [{"id": int(x)} for x in value]
        else:
            raise ValueError(f"поле {field} не поддерживается, доступны: {', '.join(FIELDS)}")
    if meta:
        body["meta_data"] = meta
    return body


def fetch_page(url: str, fresh: bool = True) -> dict:
    """SEO-поля страницы. fresh — с параметром в адресе, чтобы получить страницу в обход кэша."""
    params = {"nocache": int(time.time())} if fresh else None
    resp = http.session().get(url, params=params, timeout=90)
    page = crawler.parse_page(url, resp.content)
    page["http_status"] = resp.status_code
    page["redirected"] = bool(resp.history)
    page["html"] = resp.text
    return page


def _href_on_page(url: str, page_html: str) -> bool:
    return bool(re.search(rf'href=["\']{re.escape(url)}["\']', page_html))


def on_page(page: dict, field: str, value, old: str = "", link: bool = False) -> bool | None:
    """Видна ли правка на странице. None — поле на странице не проверяется (рубрики)."""
    if field in LIST_FIELDS:
        return None
    if link:  # новый адрес стоит в ссылке, старого нет
        return _href_on_page(value, page["html"]) and not _href_on_page(old, page["html"])
    if field == "featured_media":
        return None
    if field == "attribute":  # название характеристики и её значения есть в тексте карточки
        return all(_typo(x) in _typo(page["text"]) for x in (value["name"], *value["options"]))
    if field == "content" and not old and value:  # весь текст записи: ищем на странице его начало
        return _typo(" ".join(text(value).split()[:12])) in _typo(page["text"])
    if field == "status":  # снятая с публикации запись для посетителей — 404 или 301 на другую страницу
        if value == "publish":
            return page["http_status"] == 200 and not page.get("redirected")
        return page["http_status"] == 404 or bool(page.get("redirected"))
    if old and not value:  # удаление фрагмента: его больше нет в HTML страницы
        return text(old) not in text(page["html"])
    if field in ("content", "elementor") or (field == "body" and old):
        if value.startswith(("/", "http")):  # замена адреса ссылки: новый есть, старого нет
            return _href_on_page(value, page["html"]) and not _href_on_page(old, page["html"])
        return value in page["html"] or _typo(text(value)) in _typo(page["text"])
    if field == "body":  # новое описание целиком: ищем его начало
        return _typo(" ".join(text(value).split()[:12])) in _typo(page["text"])
    if field == "title":
        return text(page["title"]) == text(value)
    if field == "description":
        return text(page["description"]) == text(value)
    if field == "noindex":
        return ("noindex" in page["meta_robots"]) == (value == "1")
    if field == "name":
        return text(value) in [text(h) for h in page["h1"]]
    return _typo(text(value)) in _typo(page["text"])


def _typo(value: str) -> str:
    """WordPress при выводе меняет « - » на тире и прямые кавычки на типографские (wptexturize) — для сравнения
    приводим к одному виду."""
    return value.translate(str.maketrans({"–": "-", "—": "-", "«": '"', "»": '"', "“": '"', "”": '"', "„": '"',
                                          "‘": "'", "’": "'"})).replace("…", "...")


def backup(kind: str, item: dict, stamp: str) -> None:
    folder = BACKUP_DIR / stamp
    folder.mkdir(parents=True, exist_ok=True)
    path = folder / f"{kind}-{item['id']}-{item['slug']}.json"
    path.write_text(json.dumps(item, ensure_ascii=False, indent=1), encoding="utf-8")


def saved_ok(stored, r: dict) -> bool:
    if r.get("link"):
        forms_old, forms_new = _url_forms(r["old"]), _url_forms(r["value"])
        return any(f in str(stored) for f in forms_new) and not any(f in str(stored) for f in forms_old)
    if r["field"] in LIST_FIELDS:
        return stored == sorted(int(x) for x in r["value"])
    if r["field"] == "attribute":
        return (stored.get(r["value"]["name"]) == list(r["value"]["options"]) and
                (r["old"] == r["value"]["name"] or r["old"] not in stored))
    if r["field"] == "elementor":
        flat = str(stored).replace("\\/", "/")
        return r["value"] in flat and r.get("old", "") not in flat
    if is_fragment(r) or r.get("old"):
        if not r["value"]:
            return text(r["old"]) not in text(stored)
        return r["value"] in str(stored) or text(r["value"]) in text(stored)
    return text(stored) == text(r["value"])


def apply(changes: list[dict], dry_run: bool = False) -> list[dict]:
    """Применяет правки и проверяет каждую на сайте.

    Перед записью полная копия объекта сохраняется в data/wp_backup/<время>/ — из неё можно вернуть прежние значения.
    """
    wp = WordPress()
    stamp = datetime.now().strftime("%Y-%m-%d_%H%M%S")
    groups: dict[tuple[str, str], list[dict]] = {}
    for ch in changes:
        groups.setdefault((kind_of(ch), ch["slug"]), []).append(ch)

    results = []
    for (kind, slug), items in groups.items():
        try:
            item = wp.find(kind, slug)
            body = build_update(item, items, kind)
        except (ValueError, http.ApiError) as exc:  # одна несработавшая правка не останавливает пакет
            results += [{**ch, "type": kind, "status": f"ошибка: {exc}", "saved": False, "on_page": None,
                         "on_cached_page": None, "before": ""} for ch in items]
            continue
        url = item_url(item)
        rows = [{**ch, "type": kind, "id": item["id"], "url": url,
                 "before": get_value(item, ch["field"], kind)} for ch in items]
        risk = markup_risk(kind, item, body)
        if risk:
            tags = ", ".join(f"<{t}>" for t in risk)
            results += [{**r, "status": f"ошибка: при записи WordPress вырежет из описания категории {tags} — "
                                        "нужен сниппет «Описания категорий: HTML» (README)",
                         "saved": False, "on_page": None, "on_cached_page": None} for r in rows]
            continue
        if layout_risk(kind, item, body):
            results += [{**r, "status": "ошибка: обёртка class=\"table-responsive\" на телефоне обрезает текст — нужна "
                                        "<div style=\"overflow:auto;width:0;min-width:100%\">",
                         "saved": False, "on_page": None, "on_cached_page": None} for r in rows]
            continue
        if dry_run:
            results += [{**r, "status": "проверка без записи"} for r in rows]
            continue
        backup(kind, item, stamp)
        current = html_fields(kind, item)
        expected = {f: body.get(f, current[f]) for f in current}
        if kind == "product":  # описание — через API записей, чтобы WooCommerce не вырезал <style> и <svg>
            post = {new: body.pop(old) for old, new in (("description", "content"), ("short_description", "excerpt"))
                    if old in body}
            if post:
                wp.api("POST", f"wp/v2/product/{item['id']}", json=post)
        if body or kind != "product":
            updated = wp.update(kind, item["id"], body)
        else:
            updated = wp.api("GET", f"{TYPES[kind][0]}/{item['id']}", params={"context": "edit"})
        page, cached = fetch_page(url), fetch_page(url, fresh=False)
        # Мета-поля пишутся после сохранения записи, и Yoast или кэш могут отдавать старое — тогда пересохраняем.
        stale = any(on_page(pg, r["field"], r["value"], r.get("old", ""), r.get("link", False)) is False
                    for r in rows for pg in (page, cached))
        if stale and kind != "category":
            wp.resave(kind, item["id"])
            page, cached = fetch_page(url), fetch_page(url, fresh=False)
        final = wp.api("GET", f"{TYPES[kind][0]}/{item['id']}", params={"context": "edit"})
        lost = markup_lost(expected, html_fields(kind, final))
        if kind == "product" and expected != current:
            updated = final  # ответ WooCommerce на запись не содержит описания, записанного через wp/v2
        for r in rows:
            stored = get_value(updated, r["field"], kind)
            ok = saved_ok(stored, r) and not lost
            if lost:
                r = {**r, "markup_lost": lost}
            hidden = r["field"] == "elementor"  # JSON конструктора в отчёт не пишем — он в копии
            results.append({**r, "before": "(данные Elementor)" if hidden else r["before"],
                            "after": "(данные Elementor)" if hidden else stored, "saved": ok,
                            "on_page": on_page(page, r["field"], r["value"], r.get("old", ""), r.get("link", False)),
                            "on_cached_page": on_page(cached, r["field"], r["value"], r.get("old", ""),
                                                      r.get("link", False)),
                            "status": ("РАЗМЕТКА ВЫРЕЗАНА: " + ", ".join(f"<{t}>×{n}" for t, n in lost.items()) +
                                       " — вернуть из копии в data/wp_backup/" + stamp) if lost else
                                      ("применено" if ok else "не сохранилось")})
    if not dry_run and results:
        out = BACKUP_DIR / stamp / "result.json"
        out.write_text(json.dumps(results, ensure_ascii=False, indent=1), encoding="utf-8")
    return results
