"""Клиент API Яндекс.Вебмастера v4: поисковые запросы с позициями, сводка, диагностика.

Документация: https://yandex.ru/dev/webmaster/doc/ru/
Авторизация: OAuth-токен с правом webmaster:hostinfo (переменная YANDEX_OAUTH_TOKEN).
"""
from urllib.parse import quote, urlparse

from . import http

API = "https://api.webmaster.yandex.net/v4"
INDICATORS = ("TOTAL_SHOWS", "TOTAL_CLICKS", "AVG_SHOW_POSITION", "AVG_CLICK_POSITION")


class Webmaster:
    def __init__(self, token: str):
        self.sess = http.session()
        self.sess.headers["Authorization"] = f"OAuth {token}"
        self._user_id = None

    def _get(self, path: str, params=None) -> dict:
        return http.check(self.sess.get(API + path, params=params, timeout=60), "Вебмастер")

    @property
    def user_id(self) -> int:
        if self._user_id is None:
            self._user_id = self._get("/user")["user_id"]
        return self._user_id

    def hosts(self) -> list[dict]:
        return self._get(f"/user/{self.user_id}/hosts")["hosts"]

    def host_id(self, domain: str) -> str:
        """Находит host_id (вида https:am-tech.ru:443) для домена, предпочитая подтверждённый https."""
        candidates = []
        for h in self.hosts():
            url = h.get("ascii_host_url") or h.get("unicode_host_url") or ""
            host = (urlparse(url).hostname or "").removeprefix("www.")
            if host == domain:
                candidates.append(h)
        if not candidates:
            raise http.ApiError(f"Вебмастер: сайт {domain} не найден среди сайтов аккаунта")
        candidates.sort(key=lambda h: (not h.get("verified"), not h["host_id"].startswith("https")))
        return candidates[0]["host_id"]

    def _host_path(self, host_id: str) -> str:
        return f"/user/{self.user_id}/hosts/{quote(host_id, safe='')}"

    def summary(self, host_id: str) -> dict:
        return self._get(self._host_path(host_id) + "/summary")

    def diagnostics(self, host_id: str) -> dict:
        return self._get(self._host_path(host_id) + "/diagnostics")

    def popular_queries(self, host_id: str, date_from: str, date_to: str, order_by: str = "TOTAL_SHOWS",
                        device: str = "ALL", max_rows: int = 3000) -> list[dict]:
        """Топ запросов (до 3000) с показами, кликами и средней позицией за период (даты ISO 8601)."""
        rows, offset = [], 0
        while offset < max_rows:
            params = [("order_by", order_by), ("device_type_indicator", device),
                      ("date_from", date_from), ("date_to", date_to),
                      ("offset", offset), ("limit", 500)]
            params += [("query_indicator", i) for i in INDICATORS]
            data = self._get(self._host_path(host_id) + "/search-queries/popular", params)
            batch = data.get("queries", [])
            for q in batch:
                ind = q.get("indicators", {})
                shows, clicks = ind.get("TOTAL_SHOWS") or 0, ind.get("TOTAL_CLICKS") or 0
                rows.append({
                    "query": q.get("query_text", ""),
                    "shows": int(shows),
                    "clicks": int(clicks),
                    "ctr": round(clicks / shows * 100, 2) if shows else 0,
                    "avg_show_position": round(ind.get("AVG_SHOW_POSITION") or 0, 1),
                    "avg_click_position": round(ind.get("AVG_CLICK_POSITION") or 0, 1),
                })
            offset += len(batch)
            if len(batch) < 500 or offset >= data.get("count", 0):
                break
        return rows
