"""Клиент Reporting API Яндекс.Метрики: органический трафик по страницам входа и поисковым фразам.

Документация: https://yandex.ru/dev/metrika/ru/stat/
Авторизация: OAuth-токен с правом metrika:read (YANDEX_OAUTH_TOKEN), номер счётчика — YANDEX_METRIKA_COUNTER.
"""
from . import http

API = "https://api-metrika.yandex.net/stat/v1/data"
ORGANIC = "ym:s:trafficSource=='organic'"
METRICS = ("ym:s:visits", "ym:s:users", "ym:s:bounceRate", "ym:s:pageDepth",
           "ym:s:avgVisitDurationSeconds", "ym:s:sumGoalReachesAny")


class Metrika:
    def __init__(self, token: str, counter: str):
        self.counter = counter
        self.sess = http.session()
        self.sess.headers["Authorization"] = f"OAuth {token}"

    def report(self, dimensions: list[str], date1: str, date2: str, metrics=METRICS,
               filters: str | None = ORGANIC, sort: str = "-ym:s:visits", max_rows: int = 10000) -> list[dict]:
        rows, offset = [], 1
        while len(rows) < max_rows:
            params = {"ids": self.counter, "dimensions": ",".join(dimensions), "metrics": ",".join(metrics),
                      "date1": date1, "date2": date2, "sort": sort, "limit": 1000, "offset": offset,
                      "accuracy": "full"}
            if filters:
                params["filters"] = filters
            data = http.check(self.sess.get(API, params=params, timeout=120), "Метрика")
            for item in data.get("data", []):
                row = {d.split(":")[-1]: (v.get("name") or v.get("id") or "") for d, v in zip(dimensions, item["dimensions"])}
                row.update({m.split(":")[-1]: round(x, 2) if isinstance(x, float) else x
                            for m, x in zip(metrics, item["metrics"])})
                rows.append(row)
            offset += 1000
            if offset > data.get("total_rows", 0):
                break
        return rows

    def landing_pages(self, date1: str, date2: str) -> list[dict]:
        """Страницы входа из органики с разбивкой по поисковой системе."""
        return self.report(["ym:s:startURL", "ym:s:searchEngineRoot"], date1, date2)

    def search_phrases(self, date1: str, date2: str, dimension: str = "ym:s:lastsignSearchPhrase") -> list[dict]:
        """Поисковые фразы, по которым приходили из органики (Яндекс скрывает большую часть фраз)."""
        return self.report([dimension, "ym:s:startURL"], date1, date2)
