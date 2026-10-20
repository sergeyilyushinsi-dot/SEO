"""Загрузка конфигурации проекта и переменных окружения."""
import os
from pathlib import Path

import yaml

ROOT = Path(__file__).resolve().parent.parent
CONFIG_DIR = ROOT / "config"
DATA_DIR = ROOT / "data"
REPORTS_DIR = ROOT / "reports"


def load_env(path: Path = ROOT / ".env") -> None:
    """Подхватывает .env, не перезаписывая уже заданные переменные окружения."""
    if not path.exists():
        return
    for line in path.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, value = line.split("=", 1)
        os.environ.setdefault(key.strip(), value.strip().strip('"').strip("'"))


def optional_env(name: str) -> str | None:
    """Ключ из окружения. Может отсутствовать: при «сетевом секрете» его подставляет прокси окружения."""
    return os.environ.get(name, "").strip() or None


def setting(env_name: str, yaml_key: str) -> str:
    """Несекретная настройка: переменная окружения, иначе config/project.yaml → yandex.<ключ>."""
    value = optional_env(env_name) or (project().get("yandex") or {}).get(yaml_key)
    if not value:
        raise SystemExit(f"Не задано {env_name} (или yandex.{yaml_key} в config/project.yaml)")
    return str(value)


def _load_yaml(name: str) -> dict:
    path = CONFIG_DIR / name
    if not path.exists():
        return {}
    return yaml.safe_load(path.read_text(encoding="utf-8")) or {}


def project() -> dict:
    return _load_yaml("project.yaml")


def competitors() -> list[dict]:
    return _load_yaml("competitors.yaml").get("competitors") or []


def own_domain() -> str:
    return project()["site"]["domain"]
