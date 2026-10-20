"""Частотность запросов из Wordstat через один из двух официальных API.

1. Yandex Cloud Search API (рекомендуется — тот же ключ подходит для проверки выдачи):
   POST https://searchapi.api.cloud.yandex.net/v2/wordstat/topRequests
   Каталог: YANDEX_CLOUD_FOLDER_ID или project.yaml → yandex.cloud_folder_id.
   Ключ: YANDEX_CLOUD_API_KEY или сетевой секрет окружения (Authorization, префикс Api-Key).
2. API Wordstat (доступ выдаётся через поддержку Яндекс.Директа):
   POST https://api.wordstat.yandex.net/v1/topRequests
   Переменная: WORDSTAT_OAUTH_TOKEN.
"""
import os
import re

from . import config, http

CLOUD_URL = "https://searchapi.api.cloud.yandex.net/v2/wordstat/topRequests"
DIRECT_URL = "https://api.wordstat.yandex.net/v1/topRequests"


def exact(phrase: str) -> str:
    """Точная частота: "!слово !слово" — фиксирует состав и формы слов."""
    words = re.findall(r"[^\s\"!]+", phrase)
    return '"' + " ".join("!" + w for w in words) + '"'


class Wordstat:
    def __init__(self):
        self.sess = http.session()
        folder = config.optional_env("YANDEX_CLOUD_FOLDER_ID") or (config.project().get("yandex") or {}).get("cloud_folder_id")
        if folder:
            self.backend = "cloud"
            self.folder = str(folder)
            key = config.optional_env("YANDEX_CLOUD_API_KEY")
            if key:
                self.sess.headers["Authorization"] = f"Api-Key {key}"
        elif os.environ.get("WORDSTAT_OAUTH_TOKEN"):
            self.backend = "direct"
            self.sess.headers["Authorization"] = f"Bearer {os.environ['WORDSTAT_OAUTH_TOKEN']}"
        else:
            raise SystemExit("Нет доступа к Wordstat: укажите ID каталога Yandex Cloud (yandex.cloud_folder_id в "
                             "config/project.yaml) и ключ, либо WORDSTAT_OAUTH_TOKEN (см. README)")

    def top_requests(self, phrase: str, regions: list[int] | None = None, limit: int = 200) -> dict:
        """Возвращает {total, top: [(фраза, показы)], associations: [(фраза, показы)]} за последние 30 дней."""
        if self.backend == "cloud":
            body = {"phrase": phrase, "numPhrases": str(limit), "devices": ["DEVICE_ALL"], "folderId": self.folder}
            if regions:
                body["regions"] = [str(r) for r in regions]
            data = http.check(self.sess.post(CLOUD_URL, json=body, timeout=60), "Wordstat (Cloud)")
            top = data.get("results", [])
        else:
            body = {"phrase": phrase, "numPhrases": limit, "devices": ["all"]}
            if regions:
                body["regions"] = regions
            data = http.check(self.sess.post(DIRECT_URL, json=body, timeout=60), "Wordstat")
            top = data.get("topRequests", [])

        def pairs(items):
            return [(i.get("phrase", ""), int(i.get("count") or 0)) for i in items]

        return {"total": int(data.get("totalCount") or 0), "top": pairs(top),
                "associations": pairs(data.get("associations", []))}
