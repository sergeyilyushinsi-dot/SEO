"""Общая HTTP-сессия с повторами для всех сборщиков."""
import requests
from requests.adapters import HTTPAdapter
from urllib3.util.retry import Retry

# Токен, по которому краулер ищет себя в robots.txt; полный UA честно представляется.
BOT_TOKEN = "AmTechSeoAgent"
USER_AGENT = f"Mozilla/5.0 (compatible; {BOT_TOKEN}/1.0; +https://am-tech.ru/)"


class ApiError(RuntimeError):
    pass


def session() -> requests.Session:
    retry = Retry(
        total=3,
        backoff_factor=2,
        status_forcelist=(429, 500, 502, 503, 504),
        allowed_methods=frozenset({"GET", "HEAD", "POST"}),
        respect_retry_after_header=True,
    )
    sess = requests.Session()
    adapter = HTTPAdapter(max_retries=retry)
    sess.mount("https://", adapter)
    sess.mount("http://", adapter)
    sess.headers["User-Agent"] = USER_AGENT
    return sess


def check(resp: requests.Response, service: str) -> dict:
    """Возвращает JSON ответа или бросает ApiError с текстом ошибки сервиса."""
    if resp.status_code >= 400:
        raise ApiError(f"{service}: HTTP {resp.status_code}: {resp.text[:500]}")
    return resp.json()
