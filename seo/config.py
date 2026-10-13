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


def require_env(name: str) -> str:
    value = os.environ.get(name, "").strip()
    if not value:
        raise SystemExit(
            f"Не задана переменная окружения {name}. "
            "Добавьте её в настройки окружения или в .env (см. README, раздел «Доступы»)."
        )
    return value


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
