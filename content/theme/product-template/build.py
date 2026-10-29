"""Собирает woocommerce/single-product.php: product.src.php + стили блога + стили статей + product.css."""
from pathlib import Path

HERE = Path(__file__).parent
THEME = HERE.parent
css = (THEME / "case-template" / "blog-reference.css").read_text()
css = "\n".join(l for l in css.splitlines() if "page-id-1733" not in l)
css += "\n" + (THEME / "single-template" / "single.css").read_text()
css += "\n" + (HERE / "product.css").read_text()
src = (HERE / "product.src.php").read_text()
assert "/*AMT_CSS*/" in src
(HERE / "single-product.php").write_text(src.replace("/*AMT_CSS*/", css.strip()))
print("ok")
