"""Группировка позиций по целевым зонам ТОП-3/10/20/30 и сравнение снимков во времени."""
import csv
from collections import Counter
from pathlib import Path

BUCKETS = ("ТОП-3", "ТОП-10", "ТОП-20", "ТОП-30", "30+", "нет")


def bucket(pos: float | int | None) -> str:
    if not pos:
        return "нет"
    if pos <= 3:
        return "ТОП-3"
    if pos <= 10:
        return "ТОП-10"
    if pos <= 20:
        return "ТОП-20"
    if pos <= 30:
        return "ТОП-30"
    return "30+"


def next_target(pos: float | int | None) -> str:
    """Ближайшая реалистичная цель: из 4–10 → ТОП-3, из 11–30 → ТОП-10, из 31–50 → ТОП-30."""
    if not pos or pos > 50:
        return "вход в ТОП-50 (нужна страница/контент)"
    if pos <= 3:
        return "удержание ТОП-3"
    if pos <= 10:
        return "ТОП-3"
    if pos <= 30:
        return "ТОП-10"
    return "ТОП-30"


def distribution(rows: list[dict], pos_key: str) -> Counter:
    return Counter(bucket(_num(r.get(pos_key))) for r in rows)


def _num(value) -> float | None:
    try:
        v = float(value)
    except (TypeError, ValueError):
        return None
    return v or None


def read_csv(path: Path) -> list[dict]:
    with path.open(encoding="utf-8-sig") as fh:
        return list(csv.DictReader(fh))


def write_csv(path: Path, rows: list[dict]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    fields: list[str] = []
    for r in rows:
        fields += [k for k in r if k not in fields]
    with path.open("w", encoding="utf-8-sig", newline="") as fh:
        w = csv.DictWriter(fh, fieldnames=fields or ["empty"])
        w.writeheader()
        w.writerows(rows)


def compare(current: list[dict], previous: list[dict], key: str, pos_key: str) -> list[dict]:
    """Добавляет к текущим строкам прошлую позицию и изменение (плюс — рост)."""
    prev = {r[key]: _num(r.get(pos_key)) for r in previous}
    out = []
    for r in current:
        now, before = _num(r.get(pos_key)), prev.get(r[key])
        delta = round(before - now, 1) if now and before else ""
        out.append({**r, "prev_position": before or "", "delta": delta, "bucket": bucket(now),
                    "next_target": next_target(now)})
    return out


def previous_snapshot(directory: Path, current: Path) -> Path | None:
    files = sorted(p for p in directory.glob(current.stem.rsplit("_", 1)[0] + "_*.csv") if p != current)
    older = [p for p in files if p.name < current.name]
    return older[-1] if older else None
