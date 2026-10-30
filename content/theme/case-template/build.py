"""Собирает case.php: case.src.php + стили блога (blog-reference.css) + дополнения (extra.css)."""
from pathlib import Path

HERE = Path(__file__).parent

css = (HERE / "blog-reference.css").read_text()
# Full-bleed в блоге привязан к page-id-1733; для шаблона — к классу шаблона, чтобы не зависеть от ID страницы.
css = css.replace("body.page-id-1733", "body.page-template-case")
css += "\n" + (HERE / "extra.css").read_text()

src = (HERE / "case.src.php").read_text()
assert "/*AMT_CSS*/" in src
(HERE / "case.php").write_text(src.replace("/*AMT_CSS*/", css.strip()))
print("case.php", len(src), "+ css", len(css))
