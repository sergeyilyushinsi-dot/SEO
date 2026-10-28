"""Собирает single-news.php и single-cases.php (один и тот же файл) и single-video_reviews.php."""
from pathlib import Path

HERE = Path(__file__).parent
BLOG_CSS = HERE.parent / "case-template" / "blog-reference.css"

css = BLOG_CSS.read_text()
# Full-bleed блога привязан к page-id-1733 — здесь к страницам записей news и cases.
css = css.replace("body.page-id-1733 #amt-blog", "body.single-news #amt-blog,body.single-cases #amt-blog,body.single-video_reviews #amt-blog,body.post-type-archive-news #amt-blog,body.tax-news_category #amt-blog")
css = css.replace("html:has(body.page-id-1733),body.page-id-1733", "html:has(body.single-news),html:has(body.single-cases),html:has(body.single-video_reviews),html:has(body.post-type-archive-news),html:has(body.tax-news_category),body.single-news,body.single-cases,body.single-video_reviews,body.post-type-archive-news,body.tax-news_category")
css = "\n".join(l for l in css.splitlines() if "page-id-1733" not in l)
css += "\n" + (HERE / "single.css").read_text()

src = (HERE / "single.src.php").read_text()
assert "/*AMT_CSS*/" in src
out = src.replace("/*AMT_CSS*/", css.strip())
for name in ("single-news.php", "single-cases.php"):
    (HERE / name).write_text(out)

# Видеообзор: общие функции берутся из single.src.php, стили — те же + video.css.
start = src.index("if ( ! function_exists( 'amt_single_read_time' ) )")
common = src[start:src.index("get_header();")].rstrip()
vsrc = (HERE / "video.src.php").read_text()
assert "/*AMT_COMMON*/" in vsrc and "/*AMT_CSS*/" in vsrc
vcss = css + "\n" + (HERE / "video.css").read_text()
(HERE / "single-video_reviews.php").write_text(vsrc.replace("/*AMT_COMMON*/", common).replace("/*AMT_CSS*/", vcss.strip()))
# Архив и рубрики новостей
asrc = (HERE / "archive.src.php").read_text()
acss = css + "\n" + (HERE / "archive.css").read_text()
aout = asrc.replace("/*AMT_COMMON*/", common).replace("/*AMT_CSS*/", acss.strip())
for name in ("archive-news.php", "taxonomy-news_category.php"):
    (HERE / name).write_text(aout)
print("ok", len(out))
