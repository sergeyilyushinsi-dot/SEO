"""Сбор страниц по sitemap и разбор on-page элементов (title, description, заголовки, текст, разметка)."""
import gzip
import json
import re
import sys
import time
from collections import defaultdict, deque
from datetime import date
from pathlib import Path
from urllib import robotparser
from urllib.parse import urldefrag, urljoin, urlparse
from xml.etree import ElementTree as ET

import requests
from bs4 import BeautifulSoup

from . import http
from .config import DATA_DIR

SKIP_EXT = re.compile(r"\.(jpe?g|png|gif|webp|svg|ico|pdf|zip|rar|7z|docx?|xlsx?|pptx?|mp4|avi|mov|stp|step|stl)$", re.I)
# Сколько страниц обходить по ссылкам, если у сайта нет sitemap.
DISCOVERY_LIMIT = 500
LOC_TAGS = ("loc", "{http://www.sitemaps.org/schemas/sitemap/0.9}loc")
WORD_RE = re.compile(r"[0-9A-Za-zА-Яа-яЁё]+(?:-[0-9A-Za-zА-Яа-яЁё]+)*")


def host_of(url: str) -> str:
    host = (urlparse(url).hostname or "").lower()
    return host[4:] if host.startswith("www.") else host


def _local(tag: str) -> str:
    return tag.rsplit("}", 1)[-1]


def load_robots(sess: requests.Session, base_url: str) -> tuple[robotparser.RobotFileParser | None, list[str]]:
    """Возвращает парсер robots.txt и список Sitemap из него."""
    try:
        resp = sess.get(urljoin(base_url, "/robots.txt"), timeout=20)
    except requests.RequestException:
        return None, []
    if resp.status_code != 200:
        return None, []
    lines = resp.text.splitlines()
    parser = robotparser.RobotFileParser()
    parser.parse(lines)
    sitemaps = [l.split(":", 1)[1].strip() for l in lines if l.lower().startswith("sitemap:")]
    return parser, sitemaps


def sitemap_urls(sess: requests.Session, base_url: str, sitemaps: list[str] | None = None) -> list[str]:
    """Обходит sitemap-индекс рекурсивно и возвращает URL страниц (без файлов и картинок)."""
    queue = list(sitemaps or []) or [urljoin(base_url, "/sitemap.xml"), urljoin(base_url, "/sitemap_index.xml")]
    seen_maps: set[str] = set()
    urls: dict[str, None] = {}
    while queue:
        sm_url = queue.pop(0)
        if sm_url in seen_maps:
            continue
        seen_maps.add(sm_url)
        try:
            resp = sess.get(sm_url, timeout=30)
        except requests.RequestException:
            continue
        if resp.status_code != 200:
            continue
        content = resp.content
        if content[:2] == b"\x1f\x8b":
            content = gzip.decompress(content)
        try:
            root = ET.fromstring(content)
        except ET.ParseError:
            continue
        # Берём только <loc> прямых потомков <url>/<sitemap>, чтобы не захватить image:loc.
        for entry in root:
            for child in entry:
                if child.tag not in LOC_TAGS or not child.text:
                    continue
                loc = child.text.strip()
                if _local(root.tag) == "sitemapindex":
                    queue.append(loc)
                elif not SKIP_EXT.search(urlparse(loc).path):
                    urls[loc] = None
    return list(urls)


def _schema_types(soup: BeautifulSoup) -> list[str]:
    types: set[str] = set()

    def walk(node):
        if isinstance(node, dict):
            t = node.get("@type")
            if isinstance(t, str):
                types.add(t)
            elif isinstance(t, list):
                types.update(x for x in t if isinstance(x, str))
            for v in node.values():
                walk(v)
        elif isinstance(node, list):
            for v in node:
                walk(v)

    for script in soup.find_all("script", type="application/ld+json"):
        try:
            walk(json.loads(script.string or ""))
        except (json.JSONDecodeError, TypeError):
            continue
    for el in soup.find_all(attrs={"itemtype": True}):
        types.add(str(el["itemtype"]).rstrip("/").rsplit("/", 1)[-1])
    return sorted(types)


def parse_page(url: str, html: bytes | str) -> dict:
    soup = BeautifulSoup(html, "lxml")
    site = host_of(url)

    def meta(attr: str, value: str) -> str:
        tag = soup.find("meta", attrs={attr: re.compile(f"^{re.escape(value)}$", re.I)})
        return (tag.get("content") or "").strip() if tag else ""

    canonical_tag = soup.find("link", rel=lambda v: v and "canonical" in v)
    internal, external = set(), set()
    for a in soup.find_all("a", href=True):
        href = a["href"].strip()
        if href.startswith(("mailto:", "tel:", "javascript:", "#")):
            continue
        absolute = urldefrag(urljoin(url, href))[0]
        if not absolute.startswith("http"):
            continue
        (internal if host_of(absolute) == site else external).add(absolute)

    images = soup.find_all("img")
    row = {
        "title": soup.title.get_text(" ", strip=True) if soup.title else "",
        "description": meta("name", "description"),
        "meta_robots": meta("name", "robots"),
        "canonical": urljoin(url, canonical_tag["href"]) if canonical_tag and canonical_tag.get("href") else "",
        "og_title": meta("property", "og:title"),
        "h1": [h.get_text(" ", strip=True) for h in soup.find_all("h1")],
        "h2": [h.get_text(" ", strip=True) for h in soup.find_all("h2")],
        "h3": [h.get_text(" ", strip=True) for h in soup.find_all("h3")],
        "outline": [f"{h.name.upper()}: {h.get_text(' ', strip=True)}" for h in soup.find_all(["h1", "h2", "h3"])],
        "schema": _schema_types(soup),
        "images": len(images),
        "images_no_alt": sum(1 for i in images if not (i.get("alt") or "").strip()),
        "internal_links": sorted(internal),
        "external_links": len(external),
    }

    # Текст без шаблонных блоков: шапка, подвал, меню, формы, всплывающие окна Elementor.
    for tag in soup(["script", "style", "noscript", "svg", "header", "footer", "nav", "form", "aside", "iframe"]):
        tag.decompose()
    for tag in soup.select('[data-elementor-type="header"], [data-elementor-type="footer"], [data-elementor-type="popup"]'):
        tag.decompose()
    main = soup.find("main") or soup.body or soup
    text = re.sub(r"\s+", " ", main.get_text(" ", strip=True))
    row["words"] = len(WORD_RE.findall(text))
    row["text"] = text
    return row


def fetch_page(sess: requests.Session, url: str) -> dict:
    row = {"url": url, "status": "", "final_url": "", "x_robots": ""}
    try:
        resp = sess.get(url, timeout=30)
    except requests.RequestException as exc:
        row.update(status="error", error=str(exc)[:200])
        return row
    row["status"] = resp.status_code
    if resp.history:
        row["final_url"] = resp.url
        row["redirect_codes"] = [r.status_code for r in resp.history]
    row["x_robots"] = resp.headers.get("X-Robots-Tag", "")
    if resp.ok and "html" in resp.headers.get("Content-Type", ""):
        row.update(parse_page(resp.url, resp.content))
    return row


def balanced(urls: list[str], limit: int | None) -> list[str]:
    """Берёт URL поровну из каждого раздела (первый сегмент пути), чтобы лимит не съел один раздел товаров."""
    if not limit or len(urls) <= limit:
        return urls
    groups: dict[str, deque] = defaultdict(deque)
    for u in urls:
        groups[(urlparse(u).path.strip("/").split("/") or [""])[0]].append(u)
    picked: list[str] = []
    while len(picked) < limit:
        for queue in groups.values():
            if queue and len(picked) < limit:
                picked.append(queue.popleft())
    return picked


def crawl(base_url: str, limit: int | None = None, delay: float = 1.0) -> Path:
    """Краулит сайт по sitemap (или по ссылкам, если sitemap нет) и сохраняет JSONL в data/crawl/<домен>/<дата>.jsonl."""
    sess = http.session()
    robots, sitemaps = load_robots(sess, base_url)
    urls = sitemap_urls(sess, base_url, sitemaps)
    discover = not urls
    queue = deque(balanced(urls, limit) if urls else [base_url])
    cap = (limit or DISCOVERY_LIMIT) if discover else len(queue)
    seen = set(queue)

    domain = host_of(base_url)
    out = DATA_DIR / "crawl" / domain / f"{date.today().isoformat()}.jsonl"
    out.parent.mkdir(parents=True, exist_ok=True)
    source = "sitemap не найден — обход по ссылкам" if discover else f"в sitemap {len(urls)} URL"
    print(f"{domain}: {source}, к обходу до {cap} → {out}", file=sys.stderr)
    done = ok = 0
    first_error = ""
    with out.open("w", encoding="utf-8") as fh:
        while queue and done < cap:
            url = queue.popleft()
            if robots and not robots.can_fetch(http.BOT_TOKEN, url):
                row = {"url": url, "status": "robots-blocked"}
            else:
                row = fetch_page(sess, url)
                time.sleep(delay)
            row["in_sitemap"] = not discover
            ok += row.get("status") == 200
            first_error = first_error or row.get("error", "")
            fh.write(json.dumps(row, ensure_ascii=False) + "\n")
            done += 1
            if discover:
                for link in row.get("internal_links", []):
                    if link not in seen and "?" not in link and not SKIP_EXT.search(urlparse(link).path):
                        seen.add(link)
                        queue.append(link)
            if done % 50 == 0:
                print(f"  {domain}: {done}/{cap}", file=sys.stderr)
    if not ok:
        out.unlink()
        raise http.ApiError(f"{domain}: ни одна страница не загрузилась ({first_error or 'нет ответа 200'})")
    print(f"{domain}: готово, {done} страниц, из них 200 OK — {ok}", file=sys.stderr)
    return out


def latest_crawl(domain: str) -> Path | None:
    files = sorted((DATA_DIR / "crawl" / domain).glob("*.jsonl"))
    return files[-1] if files else None


def load_crawl(path: Path) -> list[dict]:
    with path.open(encoding="utf-8") as fh:
        return [json.loads(line) for line in fh if line.strip()]
