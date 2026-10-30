"""Технический и on-page аудит по результатам краулинга."""
import csv
from collections import Counter, defaultdict
from datetime import date
from pathlib import Path
from urllib.parse import urlparse

import requests

from . import http
from .config import REPORTS_DIR
from .crawler import SKIP_EXT, host_of

TITLE_MIN, TITLE_MAX = 30, 70
DESC_MIN, DESC_MAX = 70, 160
# Порог «мало текста» для коммерческих страниц (товар, категория, услуга).
THIN_WORDS = 250


def _norm(url: str) -> str:
    p = urlparse(url)
    return f"{p.scheme}://{host_of(url)}{p.path.rstrip('/') or '/'}"


def section(url: str) -> str:
    parts = [p for p in urlparse(url).path.split("/") if p]
    return parts[0] if parts else "/"


def page_issues(rows: list[dict]) -> list[dict]:
    ok = [r for r in rows if r.get("status") == 200 and "title" in r]
    titles = Counter(r["title"].strip().lower() for r in ok if r["title"].strip())
    descs = Counter(r["description"].strip().lower() for r in ok if r["description"].strip())

    issues = []

    def add(row, severity, code, detail=""):
        issues.append({"url": row["url"], "section": section(row["url"]), "severity": severity, "issue": code, "detail": detail})

    for r in rows:
        status = r.get("status")
        if status != 200:
            add(r, "high", "HTTP-статус не 200", f"{status} {r.get('error', '')}".strip())
            continue
        if r.get("final_url"):
            add(r, "medium", "URL в sitemap отдаёт редирект", f"{r.get('redirect_codes')} → {r['final_url']}")
        if "title" not in r:
            continue
        robots = f"{r.get('meta_robots', '')} {r.get('x_robots', '')}".lower()
        if "noindex" in robots:
            add(r, "high", "noindex на странице из sitemap", robots.strip())
        if r["canonical"] and _norm(r["canonical"]) != _norm(r.get("final_url") or r["url"]):
            add(r, "medium", "canonical указывает на другой URL", r["canonical"])
        if not r["canonical"]:
            add(r, "low", "нет canonical")

        t = r["title"].strip()
        if not t:
            add(r, "high", "пустой title")
        else:
            if len(t) < TITLE_MIN:
                add(r, "medium", "короткий title", f"{len(t)} симв.: {t}")
            elif len(t) > TITLE_MAX:
                add(r, "low", "длинный title", f"{len(t)} симв.: {t}")
            if titles[t.lower()] > 1:
                add(r, "high", "дубль title", t)

        d = r["description"].strip()
        if not d:
            add(r, "medium", "нет meta description")
        else:
            if len(d) < DESC_MIN:
                add(r, "low", "короткий description", f"{len(d)} симв.")
            elif len(d) > DESC_MAX:
                add(r, "low", "длинный description", f"{len(d)} симв.")
            if descs[d.lower()] > 1:
                add(r, "medium", "дубль description", d[:120])

        h1 = [h for h in r["h1"] if h.strip()]
        if not h1:
            add(r, "high", "нет H1" if not r["h1"] else "пустой H1")
        elif len(h1) > 1:
            add(r, "medium", "несколько H1", " | ".join(h1)[:200])
        elif t and h1[0].strip().lower() == t.lower():
            add(r, "low", "H1 полностью совпадает с title", t)

        sec = section(r["url"])
        if sec in ("product", "katalog", "product-category", "shop") and r["words"] < THIN_WORDS:
            add(r, "medium", "мало уникального текста", f"≈{r['words']} слов")
        if sec == "product" and "Product" not in r["schema"]:
            add(r, "medium", "нет разметки schema.org/Product", ", ".join(r["schema"]) or "разметки нет")
        if r["images_no_alt"]:
            add(r, "low", "изображения без alt", f"{r['images_no_alt']} из {r['images']}")
    return issues


def check_internal_links(rows: list[dict], max_checks: int = 300) -> list[dict]:
    """Проверяет статус внутренних ссылок, которых нет в sitemap (битые ссылки и ссылки на редиректы)."""
    known = {_norm(r["url"]) for r in rows}
    sources: dict[str, list[str]] = defaultdict(list)
    for r in rows:
        for link in r.get("internal_links", []):
            if SKIP_EXT.search(urlparse(link).path) or "?" in link:
                continue
            if _norm(link) not in known:
                sources[link].append(r["url"])
    sess = http.session()
    issues = []
    for link, pages in sorted(sources.items(), key=lambda kv: -len(kv[1]))[:max_checks]:
        refs = f"ссылаются {len(pages)} стр., напр. {pages[0]}"
        try:
            resp = sess.head(link, timeout=20, allow_redirects=True)
            if resp.status_code in (405, 403):
                resp = sess.get(link, timeout=20, allow_redirects=True)
        except requests.RequestException as exc:
            issues.append({"url": link, "section": section(link), "severity": "high", "issue": "ссылка недоступна",
                           "detail": f"{exc.__class__.__name__}; {refs}"})
            continue
        code, chain = resp.status_code, [r.status_code for r in resp.history]
        if code >= 400:
            sev, name = "high", f"битая внутренняя ссылка ({' → '.join(map(str, chain + [code]))})"
        elif chain:
            sev, name = "low", f"внутренняя ссылка на редирект ({chain[0]})"
        else:
            sev, name = "low", "страница не в sitemap"
        target = f"→ {resp.url} ; " if chain else ""
        issues.append({"url": link, "section": section(link), "severity": sev, "issue": name, "detail": target + refs})
    return issues


def write_report(domain: str, rows: list[dict], issues: list[dict]) -> tuple[Path, str]:
    REPORTS_DIR.mkdir(exist_ok=True)
    stamp = date.today().isoformat()
    csv_path = REPORTS_DIR / f"audit-{domain}-{stamp}.csv"
    with csv_path.open("w", encoding="utf-8-sig", newline="") as fh:
        w = csv.DictWriter(fh, fieldnames=["severity", "issue", "section", "url", "detail"])
        w.writeheader()
        order = {"high": 0, "medium": 1, "low": 2}
        for i in sorted(issues, key=lambda x: (order[x["severity"]], x["issue"], x["url"])):
            w.writerow({k: i[k] for k in w.fieldnames})

    by_section = Counter(section(r["url"]) for r in rows)
    ok = [r for r in rows if r.get("status") == 200 and "words" in r]
    lines = [f"# Аудит {domain} — {stamp}", "", f"Страниц в sitemap: {len(rows)}; успешно разобрано: {len(ok)}", "",
             "## Разделы", ""]
    lines += [f"- `{'/' if s == '/' else f'/{s}/'}` — {n}" for s, n in by_section.most_common()]
    lines += ["", "## Проблемы (кол-во страниц)", "", "| Важность | Проблема | Страниц |", "|---|---|---|"]
    counts = Counter((i["severity"], i["issue"]) for i in issues)
    for (sev, name), n in sorted(counts.items(), key=lambda kv: ({"high": 0, "medium": 1, "low": 2}[kv[0][0]], -kv[1])):
        lines.append(f"| {sev} | {name} | {n} |")
    lines += ["", f"Полный список: `{csv_path.relative_to(REPORTS_DIR.parent)}`"]
    return csv_path, "\n".join(lines)
