"""Снятие позиций в выдаче Яндекса через Yandex Cloud Search API v2 (бывший Яндекс.XML).

POST https://searchapi.api.cloud.yandex.net/v2/web/search — синхронный режим, ответ XML в base64.
Ключ: YANDEX_CLOUD_API_KEY или сетевой секрет окружения (заголовок Authorization, префикс Api-Key).
Каталог: YANDEX_CLOUD_FOLDER_ID или project.yaml → yandex.cloud_folder_id. Каждый запрос платный.
"""
import base64
from xml.etree import ElementTree as ET

from . import http
from .config import optional_env, setting
from .crawler import host_of

URL = "https://searchapi.api.cloud.yandex.net/v2/web/search"


class YandexSerp:
    def __init__(self):
        self.folder = setting("YANDEX_CLOUD_FOLDER_ID", "cloud_folder_id")
        self.sess = http.session()
        key = optional_env("YANDEX_CLOUD_API_KEY")
        if key:
            self.sess.headers["Authorization"] = f"Api-Key {key}"

    def search(self, query: str, region: int, depth: int = 100) -> list[dict]:
        """Топ выдачи с группировкой по доменам, как в обычной выдаче: [{pos, url, domain, title}]."""
        body = {
            "query": {"searchType": "SEARCH_TYPE_RU", "queryText": query, "page": "0",
                      "familyMode": "FAMILY_MODE_MODERATE", "fixTypoMode": "FIX_TYPO_MODE_OFF"},
            "sortSpec": {"sortMode": "SORT_MODE_BY_RELEVANCE"},
            "groupSpec": {"groupMode": "GROUP_MODE_DEEP", "groupsOnPage": str(depth), "docsInGroup": "1"},
            "maxPassages": "1",
            "region": str(region),
            "l10n": "LOCALIZATION_RU",
            "folderId": self.folder,
            "responseFormat": "FORMAT_XML",
        }
        data = http.check(self.sess.post(URL, json=body, timeout=60), "Search API")
        return parse_xml(base64.b64decode(data["rawData"]))


def parse_xml(raw: bytes) -> list[dict]:
    root = ET.fromstring(raw)
    error = root.find(".//error")
    if error is not None:
        raise http.ApiError(f"Search API: {error.get('code')} {''.join(error.itertext())}")
    results = []
    for pos, doc in enumerate(root.iterfind(".//grouping/group/doc"), 1):
        url = doc.findtext("url", "")
        title = doc.find("title")
        results.append({"pos": pos, "url": url, "domain": host_of(url),
                        "title": "".join(title.itertext()) if title is not None else ""})
    return results


def position_of(results: list[dict], domain: str) -> tuple[int | None, str]:
    for r in results:
        if r["domain"] == domain or r["domain"].endswith("." + domain):
            return r["pos"], r["url"]
    return None, ""
