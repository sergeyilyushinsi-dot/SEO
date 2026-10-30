"""Поиск тем, которые есть у конкурентов, но не раскрыты на нашем сайте."""
import csv
import re
from collections import Counter, defaultdict
from datetime import date
from pathlib import Path
from statistics import median

import snowballstemmer

from .config import REPORTS_DIR
from .crawler import WORD_RE, host_of

_stemmer = snowballstemmer.stemmer("russian")

STOP = set("""
и в во не что он на я с со как а то все она так его но да ты к у же вы за бы по только ее мне было вот от меня еще нет о из ему
теперь когда даже ну вдруг ли если уже или ни быть был него до вас нибудь опять уж вам ведь там потом себя ничего ей может они тут
где есть надо ней для мы тебя их чем была сам чтоб без будто чего раз тоже себе под будет ж тогда кто этот того потому этого какой
совсем ним здесь этом один почти мой тем чтобы нее сейчас были куда зачем всех никогда можно при наконец два об другой хоть после
над больше тот через эти нас про всего них какая много разве три эту моя впрочем хорошо свою этой перед иногда лучше чуть том нельзя
такой им более всегда конечно всю между это наш наши ваш
купить цена цены стоимость заказать каталог главная страница новости новость интернет магазин москва москве россии россия рф
доставка недорого официальный сайт компания компании ооо
the and of for to in a on with by
""".split())


def words(text: str, brand_words: set[str] = frozenset()) -> list[str]:
    tokens = [w.lower() for w in WORD_RE.findall(text)]
    return [w for w in tokens if w not in STOP and w not in brand_words and len(w) > 1 and not w.isdigit()]


def stems(text: str, brand_words: set[str] = frozenset()) -> set[str]:
    return set(_stemmer.stemWords(words(text, brand_words)))


def page_topic(row: dict, brand_words: set[str]) -> set[str]:
    return stems(" ".join([row.get("title", ""), *row.get("h1", [])]), brand_words)


def _brand_words(domain: str) -> set[str]:
    return {p for p in re.split(r"[.\-]", domain.lower()) if p not in ("ru", "com", "рф", "www")}


def find_gaps(own_rows: list[dict], comp_rows: list[dict], threshold: float = 0.5) -> list[dict]:
    own = [r for r in own_rows if r.get("status") == 200 and "title" in r]
    own_domain = host_of(own[0]["url"]) if own else ""
    own_brand = _brand_words(own_domain)
    own_topics = [(r["url"], page_topic(r, own_brand)) for r in own]

    result = []
    for r in comp_rows:
        if r.get("status") != 200 or "title" not in r or "noindex" in (r.get("meta_robots") or "").lower():
            continue
        topic = page_topic(r, _brand_words(host_of(r["url"])))
        if len(topic) < 2:
            continue
        best_url, best = "", 0.0
        for url, ours in own_topics:
            if not ours:
                continue
            score = len(topic & ours) / len(topic)
            if score > best:
                best_url, best = url, score
        result.append({
            "competitor": host_of(r["url"]),
            "url": r["url"],
            "title": r["title"],
            "h1": " | ".join(r.get("h1", [])),
            "words": r.get("words", 0),
            "coverage": round(best, 2),
            "best_own_url": best_url,
            "gap": best < threshold,
            "topic_stems": " ".join(sorted(topic)),
        })
    result.sort(key=lambda x: (not x["gap"], x["coverage"], -x["words"]))
    return result


def gap_terms(rows: list[dict], top: int = 60) -> list[tuple[str, int]]:
    """Самые частые основы слов среди нераскрытых страниц — кандидаты в новые направления."""
    counter = Counter()
    for r in rows:
        if r["gap"]:
            counter.update(r["topic_stems"].split())
    return counter.most_common(top)


def write_gaps(rows: list[dict], competitor: str) -> Path:
    REPORTS_DIR.mkdir(exist_ok=True)
    path = REPORTS_DIR / f"gap-{competitor}-{date.today().isoformat()}.csv"
    with path.open("w", encoding="utf-8-sig", newline="") as fh:
        w = csv.DictWriter(fh, fieldnames=list(rows[0].keys()) if rows else ["url"])
        w.writeheader()
        w.writerows(rows)
    return path


def term_profile(texts: dict[str, str], own_text: str = "", min_share: float = 0.5) -> list[dict]:
    """Слова, которые встречаются у большинства страниц ТОПа, и сколько раз они есть в нашем тексте.
    Помогает оценить полноту раскрытия темы (не для переспама — смотрим на охват, а не на плотность)."""
    surface: dict[str, Counter] = defaultdict(Counter)
    docs = []
    for text in texts.values():
        tokens = words(text)
        stemmed = _stemmer.stemWords(tokens)
        for stem, word in zip(stemmed, tokens):
            surface[stem][word] += 1
        docs.append(Counter(stemmed))
    own = Counter(_stemmer.stemWords(words(own_text)))
    df = Counter()
    for doc in docs:
        df.update(doc.keys())
    n = len(docs) or 1
    rows = []
    for stem, pages in df.items():
        if pages / n < min_share:
            continue
        rows.append({
            "term": surface[stem].most_common(1)[0][0],
            "pages": pages,
            "share": round(pages / n, 2),
            "median_uses": median(doc[stem] for doc in docs),
            "own_uses": own[stem],
        })
    rows.sort(key=lambda r: (-r["share"], -r["median_uses"]))
    return rows
