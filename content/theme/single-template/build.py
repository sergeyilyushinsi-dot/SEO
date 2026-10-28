"""Собирает single-news.php и single-cases.php (один и тот же файл): single.src.php + стили блога + single.css."""
from pathlib import Path

HERE = Path(__file__).parent
BLOG_CSS = HERE.parent / "case-template" / "blog-reference.css"

css = BLOG_CSS.read_text()
# Full-bleed блога привязан к page-id-1733 — здесь к страницам записей news и cases.
css = css.replace("body.page-id-1733 #amt-blog", "body.single-news #amt-blog,body.single-cases #amt-blog")
css = css.replace("html:has(body.page-id-1733),body.page-id-1733", "html:has(body.single-news),html:has(body.single-cases),body.single-news,body.single-cases")
css = "\n".join(l for l in css.splitlines() if "page-id-1733" not in l)
css += "\n" + (HERE / "single.css").read_text()

src = (HERE / "single.src.php").read_text()
assert "/*AMT_CSS*/" in src
out = src.replace("/*AMT_CSS*/", css.strip())
for name in ("single-news.php", "single-cases.php"):
    (HERE / name).write_text(out)
print("ok", len(out))
