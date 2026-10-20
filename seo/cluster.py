"""Кластеризация запросов по выдаче (hard): запросы в одном кластере, если их ТОП-10 делят ≥ N URL."""
import json
from pathlib import Path


def load_serp(path: Path) -> dict[str, list[str]]:
    tops = {}
    with path.open(encoding="utf-8") as fh:
        for line in fh:
            item = json.loads(line)
            tops[item["keyword"]] = [r["url"].rstrip("/") for r in item["results"][:10]]
    return tops


def cluster(tops: dict[str, list[str]], min_common: int = 3, freq: dict[str, int] | None = None) -> list[dict]:
    """Жадная hard-кластеризация: маркер — самый частотный запрос, к нему присоединяются запросы,
    у которых с маркером и со всеми уже вошедшими запросами ≥ min_common общих URL в ТОП-10."""
    freq = freq or {}
    order = sorted(tops, key=lambda k: -freq.get(k, 0))
    used: set[str] = set()
    clusters = []
    for marker in order:
        if marker in used:
            continue
        members = [marker]
        used.add(marker)
        for kw in order:
            if kw in used:
                continue
            urls = set(tops[kw])
            if all(len(urls & set(tops[m])) >= min_common for m in members):
                members.append(kw)
                used.add(kw)
        clusters.append({"marker": marker, "keywords": members,
                         "total_freq": sum(freq.get(k, 0) for k in members)})
    clusters.sort(key=lambda c: -c["total_freq"])
    return clusters
