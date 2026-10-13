"""CLI SEO-агента: python -m seo <команда> --help"""
import argparse
import json
import re
import sys
import time
from collections import Counter
from concurrent.futures import ThreadPoolExecutor, as_completed
from datetime import date, timedelta
from pathlib import Path

from . import audit, config, crawler, gap, http, positions
from .config import DATA_DIR, REPORTS_DIR

TODAY = date.today()
# Лимит страниц на конкурента: выборка равномерно по разделам сайта.
COMPETITOR_LIMIT = 1000


def _days_range(days: int, lag: int = 0) -> tuple[str, str]:
    end = TODAY - timedelta(days=lag)
    return (end - timedelta(days=days - 1)).isoformat(), end.isoformat()


def _site_url(domain: str | None) -> str:
    domain = domain or config.own_domain()
    return domain if domain.startswith("http") else f"https://{domain}/"


def _load_keywords(path: Path) -> list[dict]:
    if path.suffix == ".csv":
        return [r for r in positions.read_csv(path)
                if r.get("keyword", "").strip() and r.get("status") != "rejected"]
    lines = path.read_text(encoding="utf-8").splitlines()
    return [{"keyword": l.strip()} for l in lines if l.strip() and not l.startswith("#")]


def _slug(text: str) -> str:
    return re.sub(r"[^0-9a-zа-яё]+", "-", text.lower()).strip("-")[:40] or "query"


# --- сайт и конкуренты -----------------------------------------------------------------------

def cmd_crawl(a):
    if a.competitors:
        targets = [c.get("url") or _site_url(c["domain"]) for c in config.competitors()]
        if not targets:
            sys.exit("config/competitors.yaml пуст — добавьте конкурентов")
        limit = a.limit or COMPETITOR_LIMIT
    else:
        targets = [_site_url(a.domain)]
        limit = a.limit
    # Сайты обходятся параллельно, но каждый — последовательно, с паузой между запросами.
    with ThreadPoolExecutor(max_workers=len(targets)) as pool:
        futures = {pool.submit(crawler.crawl, url, limit, a.delay): url for url in targets}
        for future in as_completed(futures):
            try:
                print(future.result())
            except Exception as exc:  # один недоступный сайт не должен останавливать остальные
                print(f"! {futures[future]}: {exc}", file=sys.stderr)


def cmd_audit(a):
    domain = crawler.host_of(_site_url(a.domain))
    path = crawler.latest_crawl(domain)
    if not path:
        sys.exit(f"Нет краулинга для {domain}: сначала python -m seo crawl --domain {domain}")
    rows = crawler.load_crawl(path)
    issues = audit.page_issues(rows)
    if a.check_links:
        issues += audit.check_internal_links(rows, a.check_links)
    _, summary = audit.write_report(domain, rows, issues)
    md = REPORTS_DIR / f"audit-{domain}-{TODAY.isoformat()}.md"
    md.write_text(summary + "\n", encoding="utf-8")
    print(summary)


def cmd_page(a):
    row = crawler.fetch_page(http.session(), a.url)
    if "title" not in row:
        sys.exit(f"Не удалось разобрать страницу: {row}")
    print(f"URL: {a.url}\nСтатус: {row['status']}\nTitle: {row['title']}\nDescription: {row['description']}")
    print(f"Слов: {row['words']}; schema: {', '.join(row['schema']) or '—'}; картинок: {row['images']}")
    for heading in row["outline"]:
        print("  " * (int(heading[1]) - 1) + heading)
    if a.text:
        print("\n--- Текст ---\n" + row["text"][: a.text])


def cmd_gap(a):
    own = crawler.latest_crawl(config.own_domain())
    if not own:
        sys.exit("Нет краулинга своего сайта: python -m seo crawl")
    own_rows = crawler.load_crawl(own)
    domains = [a.competitor] if a.competitor else [c["domain"] for c in config.competitors()]
    for domain in domains:
        path = crawler.latest_crawl(domain)
        if not path:
            print(f"! нет краулинга {domain}: python -m seo crawl --domain {domain}")
            continue
        comp_rows = crawler.load_crawl(path)
        rows = gap.find_gaps(own_rows, comp_rows, a.threshold)
        out = gap.write_gaps(rows, domain)
        gaps = [r for r in rows if r["gap"]]
        print(f"\n## {domain}: страниц {len(rows)}, нераскрытых тем {len(gaps)} → {out}")
        sections = Counter(audit.section(r["url"]) for r in comp_rows if r.get("status") == 200)
        print("Разделы:", ", ".join(f"/{s}/ {n}" for s, n in sections.most_common(15)))
        for r in gaps[: a.top]:
            print(f"- [{r['coverage']}] {r['title'][:90]} — {r['url']}")
        print("Частые основы в нераскрытых темах:", ", ".join(f"{t}({n})" for t, n in gap.gap_terms(rows, 40)))


# --- Яндекс.Вебмастер и Метрика ---------------------------------------------------------------

def _webmaster():
    from .webmaster import Webmaster
    wm = Webmaster(config.optional_env("YANDEX_OAUTH_TOKEN"))
    return wm, wm.host_id(config.own_domain())


def cmd_wm_queries(a):
    wm, host = _webmaster()
    date_from, date_to = _days_range(a.days, lag=1)
    rows = wm.popular_queries(host, date_from, date_to, order_by=a.order_by, device=a.device)
    out = DATA_DIR / "positions" / f"webmaster_{TODAY.isoformat()}.csv"
    prev = positions.previous_snapshot(out.parent, out)
    rows = positions.compare(rows, positions.read_csv(prev) if prev else [], "query", "avg_show_position")
    positions.write_csv(out, rows)
    dist = Counter(r["bucket"] for r in rows)
    print(f"Запросов: {len(rows)} ({date_from} — {date_to}) → {out}" + (f"; сравнение с {prev.name}" if prev else ""))
    print(" | ".join(f"{b}: {dist.get(b, 0)}" for b in positions.BUCKETS))


def cmd_wm_diagnostics(a):
    wm, host = _webmaster()
    print(json.dumps({"host_id": host, "summary": wm.summary(host), "diagnostics": wm.diagnostics(host)},
                     ensure_ascii=False, indent=2))


def _metrika():
    from .metrika import Metrika
    return Metrika(config.optional_env("YANDEX_OAUTH_TOKEN"), config.setting("YANDEX_METRIKA_COUNTER", "metrika_counter"))


def cmd_metrika(a):
    mk = _metrika()
    date1, date2 = _days_range(a.days, lag=1)
    if a.report == "pages":
        goals = (config.project().get("yandex") or {}).get("metrika_lead_goals") or {}
        rows = mk.landing_pages(date1, date2, [int(g) for g in goals])
    else:
        rows = mk.search_phrases(date1, date2)
    out = DATA_DIR / "metrika" / f"{a.report}_{TODAY.isoformat()}.csv"
    positions.write_csv(out, rows)
    print(f"{len(rows)} строк ({date1} — {date2}) → {out}")


# --- Wordstat и выдача ------------------------------------------------------------------------

def cmd_wordstat(a):
    from .wordstat import Wordstat, exact
    ws = Wordstat()
    phrases = list(a.phrases)
    if a.file:
        phrases += [r["keyword"] for r in _load_keywords(Path(a.file))]
    if not phrases:
        sys.exit("Укажите фразы аргументами или через --file")
    regions = a.regions or config.project()["regions"]["wordstat"]
    rows = []
    for phrase in phrases:
        if a.mode == "exact":
            res = ws.top_requests(exact(phrase), regions, limit=1)
            rows.append({"keyword": phrase, "exact_freq": res["total"]})
        else:
            res = ws.top_requests(phrase, regions, limit=a.limit)
            rows.append({"seed": phrase, "kind": "total", "phrase": phrase, "count": res["total"]})
            rows += [{"seed": phrase, "kind": "top", "phrase": p, "count": c} for p, c in res["top"]]
            rows += [{"seed": phrase, "kind": "association", "phrase": p, "count": c} for p, c in res["associations"]]
        time.sleep(0.2)
    name = _slug(phrases[0]) if len(phrases) == 1 else f"{a.mode}-{len(phrases)}"
    out = DATA_DIR / "wordstat" / f"{name}_{TODAY.isoformat()}.csv"
    positions.write_csv(out, rows)
    print(f"{len(rows)} строк → {out}")


def cmd_serp(a):
    from .serp import YandexSerp, position_of
    serp = YandexSerp()
    region = a.region or config.project()["regions"]["serp"]
    own = config.own_domain()
    comps = [c["domain"] for c in config.competitors()]
    keywords = _load_keywords(Path(a.file))
    if a.limit:
        keywords = keywords[: a.limit]

    raw_path = DATA_DIR / "serp" / f"{TODAY.isoformat()}_{region}.jsonl"
    raw_path.parent.mkdir(parents=True, exist_ok=True)
    rows, top10_domains = [], Counter()
    with raw_path.open("w", encoding="utf-8") as raw:
        for kw in keywords:
            results = serp.search(kw["keyword"], region, a.depth)
            raw.write(json.dumps({"keyword": kw["keyword"], "region": region, "results": results}, ensure_ascii=False) + "\n")
            top10_domains.update({r["domain"] for r in results[:10]})
            pos, url = position_of(results, own)
            row = {"keyword": kw["keyword"], "cluster": kw.get("cluster", ""), "position": pos or "", "url": url,
                   "target_url": kw.get("target_url", "")}
            if row["target_url"].startswith("http") and url and url.rstrip("/") != row["target_url"].rstrip("/"):
                row["note"] = "ранжируется не целевая страница"
            for c in comps:
                row[c] = position_of(results, c)[0] or ""
            rows.append(row)
            time.sleep(0.3)

    out = DATA_DIR / "positions" / f"serp_{TODAY.isoformat()}.csv"
    prev = positions.previous_snapshot(out.parent, out)
    rows = positions.compare(rows, positions.read_csv(prev) if prev else [], "keyword", "position")
    positions.write_csv(out, rows)
    dist = Counter(r["bucket"] for r in rows)
    print(f"Регион {region}, ключей {len(rows)} → {out}")
    print(" | ".join(f"{b}: {dist.get(b, 0)}" for b in positions.BUCKETS))
    print("\nЧаще всего в ТОП-10 (кандидаты в конкуренты):")
    for d, n in top10_domains.most_common(a.domains):
        mark = " (мы)" if d == own else (" (в списке конкурентов)" if d in comps else "")
        print(f"  {n:>4}  {d}{mark}")


def cmd_top(a):
    from .serp import YandexSerp
    region = a.region or config.project()["regions"]["serp"]
    for r in YandexSerp().search(a.query, region, a.depth):
        print(f"{r['pos']:>3}. {r['domain']:<30} {r['url']}\n     {r['title']}")


def cmd_terms(a):
    sess = http.session()
    texts = {}
    for url in a.urls:
        row = crawler.fetch_page(sess, url)
        if row.get("text"):
            texts[url] = row["text"]
            print(f"  {url}: {row['words']} слов", file=sys.stderr)
        time.sleep(0.5)
    own_text = crawler.fetch_page(sess, a.own).get("text", "") if a.own else ""
    rows = gap.term_profile(texts, own_text, a.min_share)
    out = REPORTS_DIR / f"terms-{_slug(a.name or a.urls[0])}-{TODAY.isoformat()}.csv"
    REPORTS_DIR.mkdir(exist_ok=True)
    positions.write_csv(out, rows)
    print(f"Страниц ТОПа: {len(texts)}; терминов с долей ≥ {a.min_share}: {len(rows)} → {out}")
    missing = [r["term"] for r in rows if a.own and not r["own_uses"]]
    if missing:
        print("Нет на нашей странице:", ", ".join(missing[:80]))


def cmd_cluster(a):
    from .cluster import cluster, load_serp
    path = Path(a.serp) if a.serp else max((DATA_DIR / "serp").glob("*.jsonl"), default=None)
    if not path:
        sys.exit("Нет данных выдачи: сначала python -m seo serp")
    freq = {}
    if a.freq:
        for r in positions.read_csv(Path(a.freq)):
            value = r.get("exact_freq") or r.get("count") or 0
            freq[r.get("keyword") or r.get("phrase")] = int(float(value or 0))
    groups = cluster(load_serp(path), a.min_common, freq)
    rows = [{"cluster": g["marker"], "keyword": k, "freq": freq.get(k, ""), "cluster_freq": g["total_freq"]}
            for g in groups for k in g["keywords"]]
    out = DATA_DIR / "semantic_core" / f"clusters_{TODAY.isoformat()}.csv"
    positions.write_csv(out, rows)
    print(f"{len(groups)} кластеров из {len(rows)} запросов ({path.name}) → {out}")


def main(argv=None):
    config.load_env()
    p = argparse.ArgumentParser(prog="python -m seo", description="SEO-агент am-tech.ru")
    sub = p.add_subparsers(dest="cmd", required=True)

    s = sub.add_parser("crawl", help="краулинг сайта по sitemap")
    s.add_argument("--domain", help="домен (по умолчанию свой сайт)")
    s.add_argument("--competitors", action="store_true", help="краулить всех из config/competitors.yaml")
    s.add_argument("--limit", type=int)
    s.add_argument("--delay", type=float, default=1.0)
    s.set_defaults(func=cmd_crawl)

    s = sub.add_parser("audit", help="аудит по последнему краулингу")
    s.add_argument("--domain")
    s.add_argument("--check-links", type=int, default=0, metavar="N", help="проверить N внутренних ссылок вне sitemap")
    s.set_defaults(func=cmd_audit)

    s = sub.add_parser("page", help="структура одной страницы (для анализа ТОПа)")
    s.add_argument("url")
    s.add_argument("--text", type=int, default=0, metavar="N", help="вывести первые N символов текста")
    s.set_defaults(func=cmd_page)

    s = sub.add_parser("gap", help="темы конкурентов, не раскрытые у нас")
    s.add_argument("--competitor")
    s.add_argument("--threshold", type=float, default=0.5)
    s.add_argument("--top", type=int, default=40)
    s.set_defaults(func=cmd_gap)

    s = sub.add_parser("wm-queries", help="запросы и позиции из Яндекс.Вебмастера")
    s.add_argument("--days", type=int, default=28)
    s.add_argument("--order-by", default="TOTAL_SHOWS", choices=["TOTAL_SHOWS", "TOTAL_CLICKS"])
    s.add_argument("--device", default="ALL", choices=["ALL", "DESKTOP", "MOBILE_AND_TABLET", "MOBILE", "TABLET"])
    s.set_defaults(func=cmd_wm_queries)

    s = sub.add_parser("wm-diagnostics", help="сводка и диагностика сайта в Вебмастере")
    s.set_defaults(func=cmd_wm_diagnostics)

    s = sub.add_parser("metrika", help="органика из Яндекс.Метрики")
    s.add_argument("report", choices=["pages", "phrases"])
    s.add_argument("--days", type=int, default=28)
    s.set_defaults(func=cmd_metrika)

    s = sub.add_parser("wordstat", help="частотность и расширение семантики")
    s.add_argument("phrases", nargs="*")
    s.add_argument("--file", help="txt (фраза в строке) или csv с колонкой keyword")
    s.add_argument("--mode", choices=["expand", "exact"], default="expand",
                   help="expand — топ запросов и ассоциации; exact — точная частота \"!фраза\"")
    s.add_argument("--regions", type=int, nargs="*")
    s.add_argument("--limit", type=int, default=200)
    s.set_defaults(func=cmd_wordstat)

    s = sub.add_parser("serp", help="позиции свои и конкурентов в выдаче Яндекса")
    s.add_argument("--file", default=str(DATA_DIR / "semantic_core" / "core.csv"))
    s.add_argument("--region", type=int)
    s.add_argument("--depth", type=int, default=100)
    s.add_argument("--limit", type=int)
    s.add_argument("--domains", type=int, default=25, help="сколько доменов показать в сводке ТОП-10")
    s.set_defaults(func=cmd_serp)

    s = sub.add_parser("top", help="ТОП выдачи Яндекса по одному запросу")
    s.add_argument("query")
    s.add_argument("--region", type=int)
    s.add_argument("--depth", type=int, default=10)
    s.set_defaults(func=cmd_top)

    s = sub.add_parser("terms", help="термины ТОПа и их наличие на нашей странице")
    s.add_argument("urls", nargs="+", help="URL страниц из ТОПа")
    s.add_argument("--own", help="наша страница для сравнения")
    s.add_argument("--name", help="имя для файла отчёта (обычно запрос)")
    s.add_argument("--min-share", type=float, default=0.5)
    s.set_defaults(func=cmd_terms)

    s = sub.add_parser("cluster", help="кластеризация запросов по ТОП-10")
    s.add_argument("--serp", help="jsonl из data/serp (по умолчанию последний)")
    s.add_argument("--freq", help="csv с частотами (keyword + exact_freq или phrase + count)")
    s.add_argument("--min-common", type=int, default=3)
    s.set_defaults(func=cmd_cluster)

    a = p.parse_args(argv)
    try:
        a.func(a)
    except http.ApiError as exc:
        sys.exit(f"Ошибка API — {exc}")


if __name__ == "__main__":
    main()
