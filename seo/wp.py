"""Правки карточек товаров am-tech.ru через REST API WooCommerce: резервная копия, запись, проверка на сайте.

Авторизация — пароль приложения WordPress пользователя агента: сетевым секретом окружения (заголовок
Authorization: Basic …, его подставляет прокси) или в WP_USER + WP_APP_PASSWORD. Что можно менять — README,
раздел «Доступ к сайту». Правки вносятся только после подтверждения во вкладке «Правки на сайте».
"""
import html
import json
import re
import time
from datetime import datetime

from . import config, crawler, http
from .config import DATA_DIR

BACKUP_DIR = DATA_DIR / "wp_backup"

# Поле правки → мета-поле Yoast у товара (доступно в meta_data WooCommerce).
META = {"title": "_yoast_wpseo_title", "description": "_yoast_wpseo_metadesc",
        "noindex": "_yoast_wpseo_meta-robots-noindex"}
# В HTML-полях меняется только текст внутри разметки, сама разметка остаётся.
HTML_FIELDS = ("short_description",)
PLAIN_FIELDS = ("name",)
FIELDS = (*META, *HTML_FIELDS, *PLAIN_FIELDS)
# Любое другое мета-поле товара — «meta:<ключ>», например meta:_custom_advantages (блок «Преимущества»).
# В нём меняется фрагмент текста: "old" — что заменить, "value" — на что.
META_PREFIX = "meta:"


def text(value) -> str:
    """Видимый текст: без тегов, с раскрытыми сущностями и схлопнутыми пробелами."""
    return " ".join(html.unescape(re.sub(r"<[^>]+>", " ", str(value or ""))).split())


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

    def product(self, slug: str) -> dict:
        found = self.api("GET", "wc/v3/products", params={"slug": slug, "status": "any"})
        if not found:
            raise http.ApiError(f"WordPress: товар {slug} не найден")
        return found[0]

    def update_product(self, product_id: int, body: dict) -> dict:
        return self.api("PUT", f"wc/v3/products/{product_id}", json=body)

    def resave(self, product_id: int) -> None:
        """Пересохраняет товар без изменений, как кнопка «Обновить» в админке: срабатывает save_post,
        Yoast пересобирает title/description, кэш страницы сбрасывается. WooCommerce при правке одних мета-полей
        запись не пересохраняет."""
        self.api("POST", f"wp/v2/product/{product_id}", json={})


def meta_key(field: str) -> str | None:
    return META.get(field) or (field[len(META_PREFIX):] if field.startswith(META_PREFIX) else None)


def get_value(product: dict, field: str) -> str:
    key = meta_key(field)
    if key:
        return next((str(m["value"]) for m in product.get("meta_data", []) if m["key"] == key), "")
    return product.get(field) or ""


def _replace_text(product: dict, field: str, raw: str, old: str, value: str) -> str:
    if not old or old not in raw:  # текст разбит тегами или записан сущностями — вслепую не перезаписываем
        raise ValueError(f"{product['slug']}: в {field} нет текста «{old[:60]}», правка не применена")
    return raw.replace(old, html.escape(value, quote=False), 1)


def build_update(product: dict, changes: list[dict]) -> dict:
    """Тело PUT для одного товара. changes: [{"field", "value", "old"?}]; old — прежний текст HTML-поля,
    если в поле больше одного фрагмента текста."""
    body, meta = {}, []
    for ch in changes:
        field, value = ch["field"], ch["value"]
        if field in META:
            item = next((m for m in product.get("meta_data", []) if m["key"] == META[field]), None)
            meta.append({"id": item["id"], "key": item["key"], "value": value} if item
                        else {"key": META[field], "value": value})
        elif field.startswith(META_PREFIX):
            key = meta_key(field)
            item = next((m for m in product.get("meta_data", []) if m["key"] == key), None)
            if not item or not isinstance(item["value"], str):
                raise ValueError(f"{product['slug']}: у товара нет текстового поля {key}")
            meta.append({"id": item["id"], "key": key,
                         "value": _replace_text(product, field, item["value"], ch.get("old", ""), value)})
        elif field in HTML_FIELDS:
            raw = body.get(field, product.get(field) or "")
            body[field] = _replace_text(product, field, raw, ch.get("old") or text(raw), value)
        elif field in PLAIN_FIELDS:
            body[field] = value
        else:
            raise ValueError(f"поле {field} не поддерживается, доступны: {', '.join(FIELDS)}")
    if meta:
        body["meta_data"] = meta
    return body


def fetch_page(url: str, fresh: bool = True) -> dict:
    """SEO-поля страницы. fresh — с параметром в адресе, чтобы получить страницу в обход кэша."""
    params = {"nocache": int(time.time())} if fresh else None
    resp = http.session().get(url, params=params, timeout=90)
    return crawler.parse_page(url, resp.content)


def on_page(page: dict, field: str, value: str) -> bool:
    if field == "title":
        return text(page["title"]) == text(value)
    if field == "description":
        return text(page["description"]) == text(value)
    if field == "noindex":
        return ("noindex" in page["meta_robots"]) == (value == "1")
    if field == "name":
        return text(value) in [text(h) for h in page["h1"]]
    return text(value) in page["text"]


def backup(product: dict, stamp: str) -> None:
    folder = BACKUP_DIR / stamp
    folder.mkdir(parents=True, exist_ok=True)
    path = folder / f"product-{product['id']}-{product['slug']}.json"
    path.write_text(json.dumps(product, ensure_ascii=False, indent=1), encoding="utf-8")


def apply(changes: list[dict], dry_run: bool = False) -> list[dict]:
    """Применяет правки [{"slug", "field", "value", "old"?}] и проверяет каждую на сайте.

    Перед записью полная копия товара сохраняется в data/wp_backup/<время>/ — из неё можно вернуть прежние значения.
    """
    wp = WordPress()
    stamp = datetime.now().strftime("%Y-%m-%d_%H%M%S")
    by_slug: dict[str, list[dict]] = {}
    for ch in changes:
        by_slug.setdefault(ch["slug"], []).append(ch)

    results = []
    for slug, items in by_slug.items():
        product = wp.product(slug)
        body = build_update(product, items)
        rows = [{**ch, "id": product["id"], "url": product["permalink"], "before": get_value(product, ch["field"])}
                for ch in items]
        if dry_run:
            results += [{**r, "status": "проверка без записи"} for r in rows]
            continue
        backup(product, stamp)
        updated = wp.update_product(product["id"], body)
        page, cached = fetch_page(product["permalink"]), fetch_page(product["permalink"], fresh=False)
        # Мета-поля пишутся после сохранения записи, и Yoast или кэш могут отдавать старое — тогда пересохраняем.
        if not all(on_page(pg, r["field"], r["value"]) for r in rows for pg in (page, cached)):
            wp.resave(product["id"])
            page, cached = fetch_page(product["permalink"]), fetch_page(product["permalink"], fresh=False)
        for r in rows:
            stored = get_value(updated, r["field"])
            fragment = r["field"] in HTML_FIELDS or r["field"].startswith(META_PREFIX)
            saved = text(stored) == text(r["value"]) or (fragment and text(r["value"]) in text(stored))
            results.append({**r, "after": stored, "saved": saved,
                            "on_page": on_page(page, r["field"], r["value"]),
                            "on_cached_page": on_page(cached, r["field"], r["value"]),
                            "status": "применено" if saved else "не сохранилось"})
    if not dry_run and results:
        out = BACKUP_DIR / stamp / "result.json"
        out.write_text(json.dumps(results, ensure_ascii=False, indent=1), encoding="utf-8")
    return results
