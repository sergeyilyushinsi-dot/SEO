"""Офлайн-тесты разбора данных (без обращений к API)."""
from seo import cluster, crawler, gap, positions, wp
from seo.serp import parse_xml, position_of
from seo.wordstat import exact

XML = """<?xml version="1.0" encoding="utf-8"?>
<yandexsearch version="1.0"><response><results><grouping>
<group><doc><url>https://www.competitor.ru/slm/</url><domain>www.competitor.ru</domain><title>SLM <hlword>3D-принтеры</hlword></title></doc></group>
<group><doc><url>https://am-tech.ru/katalog/slm/</url><domain>am-tech.ru</domain><title>AM.TECH</title></doc></group>
</grouping></results></response></yandexsearch>""".encode()


def test_parse_xml_and_position():
    res = parse_xml(XML)
    assert [r["domain"] for r in res] == ["competitor.ru", "am-tech.ru"]
    assert res[0]["title"] == "SLM 3D-принтеры"
    assert position_of(res, "am-tech.ru") == (2, "https://am-tech.ru/katalog/slm/")
    assert position_of(res, "other.ru") == (None, "")


def test_buckets_and_compare():
    assert [positions.bucket(p) for p in (1, 7, 15, 25, 40, None)] == ["ТОП-3", "ТОП-10", "ТОП-20", "ТОП-30", "30+", "нет"]
    rows = positions.compare([{"q": "a", "pos": "8"}], [{"q": "a", "pos": "14"}], "q", "pos")
    assert rows[0]["delta"] == 6.0 and rows[0]["next_target"] == "ТОП-3"


def test_cluster_by_serp():
    tops = {"slm принтер": list("abcdefghij"), "slm 3d принтер": list("abcdxyzwvu"), "3d сканер": list("klmnopqrst")}
    groups = cluster.cluster(tops, min_common=3, freq={"slm принтер": 100, "slm 3d принтер": 50, "3d сканер": 70})
    assert groups[0]["keywords"] == ["slm принтер", "slm 3d принтер"]
    assert groups[1]["keywords"] == ["3d сканер"]


def test_exact_phrase():
    assert exact("3d принтер по металлу") == '"!3d !принтер !по !металлу"'


def test_term_profile_and_gaps():
    texts = {"u1": "Принтер печатает металлом. Лазер мощный.", "u2": "Лазерный принтер для металла, лазер 500 Вт"}
    terms = {r["term"]: r for r in gap.term_profile(texts, own_text="принтер", min_share=1.0)}
    assert terms["принтер"]["own_uses"] == 1 and terms["лазер"]["own_uses"] == 0
    own = [{"url": "https://am-tech.ru/a/", "status": 200, "title": "SLM 3D-принтер по металлу", "h1": []}]
    comp = [{"url": "https://c.ru/x/", "status": 200, "title": "Вакуумная печь для спекания", "h1": [], "words": 300},
            {"url": "https://c.ru/y/", "status": 200, "title": "3D-принтеры SLM по металлу", "h1": [], "words": 500}]
    rows = {r["url"]: r for r in gap.find_gaps(own, comp)}
    assert rows["https://c.ru/x/"]["gap"] and not rows["https://c.ru/y/"]["gap"]


PRODUCT = {
    "slug": "amt-43", "name": "AMT-43",
    "short_description": '<p class="x"><strong><span>AMT-45 – SLM-принтер</span></strong></p>\n',
    "meta_data": [{"id": 7, "key": "_yoast_wpseo_title", "value": "AMT-45 | AM-Tech"},
                  {"id": 8, "key": "_custom_advantages", "value": "<h2>Материалы</h2>\r\nв SLM-установке AMT-45.\r\n"}],
}


def test_wp_build_update():
    body = wp.build_update(PRODUCT, [
        {"field": "title", "value": "AMT-43 | AM-Tech"},
        {"field": "description", "value": "Описание"},
        {"field": "short_description", "value": "AMT-43 — SLM-принтер"},
    ])
    assert body["meta_data"] == [{"id": 7, "key": "_yoast_wpseo_title", "value": "AMT-43 | AM-Tech"},
                                 {"key": "_yoast_wpseo_metadesc", "value": "Описание"}]
    assert body["short_description"] == '<p class="x"><strong><span>AMT-43 — SLM-принтер</span></strong></p>\n'


def test_wp_build_update_refuses_unknown_text():
    try:
        wp.build_update(PRODUCT, [{"field": "short_description", "value": "x", "old": "AMT-16"}])
    except ValueError:
        pass
    else:
        raise AssertionError("правка с ненайденным текстом должна отклоняться")


def test_wp_on_page():
    page = {"title": "AMT-43 &amp; | AM-Tech", "description": "", "meta_robots": "noindex, follow",
            "h1": ["AMT-43"], "text": "Цена по запросу AMT-43 — SLM-принтер"}
    assert wp.on_page(page, "title", "AMT-43 & | AM-Tech")
    assert wp.on_page(page, "noindex", "1") and not wp.on_page(page, "noindex", "2")
    assert wp.on_page(page, "short_description", "AMT-43 — SLM-принтер")


def test_wp_meta_fragment():
    body = wp.build_update(PRODUCT, [{"field": "meta:_custom_advantages", "old": "SLM-установке AMT-45",
                                      "value": "SLM-установке AMT-43"}])
    assert body["meta_data"] == [{"id": 8, "key": "_custom_advantages",
                                  "value": "<h2>Материалы</h2>\r\nв SLM-установке AMT-43.\r\n"}]
    # Две правки одного поля: вторая не затирает первую (16.10.2026 так потерялись 4 правки)
    body = wp.build_update(PRODUCT, [{"field": "meta:_custom_advantages", "old": "Материалы", "value": "Порошки"},
                                     {"field": "meta:_custom_advantages", "old": "AMT-45", "value": "AMT-43"}])
    assert body["meta_data"] == [{"id": 8, "key": "_custom_advantages",
                                  "value": "<h2>Порошки</h2>\r\nв SLM-установке AMT-43.\r\n"}]
    try:  # фрагмент с тегом записался бы как &lt;/h2&gt;
        wp.build_update(PRODUCT, [{"field": "meta:_custom_advantages", "old": "Материалы</h2>", "value": "Порошки</h2>"}])
    except ValueError:
        pass
    else:
        raise AssertionError("фрагмент мета-поля с тегами должен отклоняться")


def test_wp_status_on_page():
    assert wp.on_page({"http_status": 404}, "status", "draft")
    assert not wp.on_page({"http_status": 200}, "status", "draft")
    # снятая с публикации страница с 301 на другую (плагин Redirection) — тоже убрана
    assert wp.on_page({"http_status": 200, "redirected": True}, "status", "draft")
    assert not wp.on_page({"http_status": 200, "redirected": True}, "status", "publish")
    assert wp.build_update(PRODUCT, [{"field": "status", "value": "draft"}]) == {"status": "draft"}


def test_wp_body_categories_and_removal():
    product = {**PRODUCT, "description": "<style>body{margin:0}</p>\n<p>.x{a:b}</style><p>Текст</p>",
               "categories": [{"id": 15, "name": "default"}]}
    body = wp.build_update(product, [{"field": "body", "old": "body{margin:0}</p>\n<p>", "value": ""},
                                     {"field": "categories", "value": [24, 1272]}])
    assert body["description"] == "<style>.x{a:b}</style><p>Текст</p>"
    assert body["categories"] == [{"id": 24}, {"id": 1272}]
    assert wp.build_update(product, [{"field": "body", "value": "<p>Новое</p>"}]) == {"description": "<p>Новое</p>"}
    assert wp.get_value(product, "categories") == [15]
    page = {"html": "<style>.x{a:b}</style>", "text": "Новое описание", "h1": []}
    assert wp.on_page(page, "body", "", old="body{margin:0}")
    assert wp.on_page(page, "body", "<p>Новое описание</p>")
    assert wp.on_page(page, "categories", [24]) is None


def test_wp_post_types_links():
    news = {"slug": "n", "content": {"raw": '<a href="/product-category/3d-skanery/">сканеры</a>'},
            "meta": {"_elementor_data": '[{"url":"https:\\/\\/am-tech.ru\\/product-category\\/sla-3d-printery\\/"}]'},
            "title": {"raw": "Новость"}}
    body = wp.build_update(news, [{"field": "content", "old": "/product-category/3d-skanery/", "value": "/katalog/x/"}], "news")
    assert body == {"content": '<a href="/katalog/x/">сканеры</a>'}
    body = wp.build_update(news, [{"field": "elementor", "old": "/product-category/sla-3d-printery/",
                                   "value": "/katalog/sla/"}], "page")
    assert body["meta"]["_elementor_data"] == '[{"url":"https:\\/\\/am-tech.ru\\/katalog\\/sla\\/"}]'
    page = {"html": '<a href="/katalog/x/">сканеры</a>', "text": "сканеры"}
    assert wp.on_page(page, "content", "/katalog/x/", old="/product-category/3d-skanery/")
    assert not wp.on_page({"html": '<a href="/product-category/3d-skanery/">', "text": ""}, "content",
                          "/katalog/x/", old="/product-category/3d-skanery/")
    assert wp.saved_ok('[{"url":"https:\\/\\/am-tech.ru\\/katalog\\/sla\\/"}]',
                       {"field": "elementor", "old": "/product-category/sla-3d-printery/", "value": "/katalog/sla/"})


def test_wp_link_replaces_only_whole_url():
    news = {"slug": "n", "content": {"raw": '<a href="/product/ ">каталог</a> <a href="https://am-tech.ru/product/amt-16/">AMT</a>'},
            "meta": {"_elementor_data": '[{"url":"\\/product-category\\/sla-3d-printery\\/"},{"editor":"<a href=\\"\\/product-category\\/sla-3d-printery\\/\\">"}]'}}
    body = wp.build_update(news, [{"field": "content", "link": True, "old": "/product/ ", "value": "/katalog/"}], "news")
    assert body["content"] == '<a href="/katalog/">каталог</a> <a href="https://am-tech.ru/product/amt-16/">AMT</a>'
    body = wp.build_update(news, [{"field": "elementor", "link": True, "old": "/product-category/sla-3d-printery/",
                                   "value": "/katalog/sla/"}], "page")
    assert "sla-3d-printery" not in body["meta"]["_elementor_data"]
    assert body["meta"]["_elementor_data"].count("\\/katalog\\/sla\\/") == 2
    page = {"html": '<a href="/katalog/">каталог</a> <a href="https://am-tech.ru/product/amt-16/">'}
    assert wp.on_page(page, "content", "/katalog/", old="/product/ ", link=True)


def test_wp_yoast_on_posts_needs_task_41():
    page = {"slug": "o-kompanii", "meta": {}, "content": {"raw": ""}, "title": {"raw": "О компании"}}
    try:
        wp.build_update(page, [{"field": "noindex", "value": "2"}], "page")
    except ValueError as exc:
        assert "задача 41" in str(exc)
    else:
        raise AssertionError("без открытых полей Yoast правка должна отклоняться")
    page["meta"]["_yoast_wpseo_meta-robots-noindex"] = "1"
    assert wp.build_update(page, [{"field": "noindex", "value": "2"}], "page") == {"meta": {"_yoast_wpseo_meta-robots-noindex": "2"}}


def test_counter_pixel_is_not_an_image_without_alt():
    html = ('<html><body><img src="/a.jpg" alt="Принтер"><img src="/b.jpg">'
            '<noscript><div><img src="https://mc.yandex.ru/watch/1" style="position:absolute"></div></noscript></body></html>')
    row = crawler.parse_page("https://am-tech.ru/", html)
    assert row["images"] == 2
    assert row["images_no_alt"] == 1


def test_wp_merge_duplicate_post():
    old = {"slug": "a", "content": {"raw": "<p>Старый текст</p>"}, "title": {"raw": "Старое"}, "featured_media": 1,
           "meta": {"_yoast_wpseo_metadesc": ""}}
    new_html = "<h2>Принципы</h2><p>Новый текст статьи про неявное моделирование для аддитивного производства и DfAM</p>"
    body = wp.build_update(old, [{"field": "content", "whole": True, "value": new_html},
                                 {"field": "featured_media", "value": "4270"},
                                 {"field": "name", "value": "Новое"}], "news")
    assert body == {"content": new_html, "featured_media": 4270, "title": "Новое"}
    # без "whole" пустой фрагмент не заменяет весь текст
    try:
        wp.build_update(old, [{"field": "content", "value": new_html}], "news")
        assert False, "должна быть ошибка"
    except ValueError:
        pass
    page = {"html": "", "text": "Принципы Новый текст статьи про неявное моделирование для аддитивного производства и DfAM"}
    assert wp.on_page(page, "content", new_html)
    assert wp.on_page(page, "featured_media", 4270) is None


def test_wp_category_yoast_fields():
    cat = {"slug": "slm-3d-printeri", "description": "", "yoast_seo": {"title": "", "desc": "Старое"}}
    body = wp.build_update(cat, [{"field": "title", "value": "SLM 3D-принтеры — купить | AM-Tech"},
                                 {"field": "description", "value": "Новое описание"}], "category")
    assert body == {"yoast_seo": {"title": "SLM 3D-принтеры — купить | AM-Tech", "desc": "Новое описание"}}
    assert wp.get_value(cat, "description", "category") == "Старое"
    # Одно поле Yoast — второе передаётся текущим значением, иначе Yoast его сотрёт.
    body = wp.build_update(cat, [{"field": "title", "value": "SLM | AM.TECH"}], "category")
    assert body == {"yoast_seo": {"title": "SLM | AM.TECH", "desc": "Старое"}}
    assert cat["yoast_seo"] == {"title": "", "desc": "Старое"}
    try:  # без сниппета поля в ответе API нет
        wp.build_update({"slug": "x", "description": ""}, [{"field": "title", "value": "T"}], "category")
        assert False, "должна быть ошибка"
    except ValueError as exc:
        assert "сниппет" in str(exc)


def test_wp_markup_guard_for_categories():
    html = "<h2>Как работает</h2><p>Текст <strong>важно</strong></p><ul><li>пункт</li></ul>"
    cat = {"slug": "slm-3d-printeri", "description": html}
    # Без сниппета «Описания категорий: HTML» любая запись категории вырезает заголовки, абзацы и списки.
    assert wp.markup_risk("category", cat, {"name": "SLM 3D-принтеры"}) == ["h2", "li", "p", "ul"]
    assert wp.markup_risk("category", {**cat, "html_description": True}, {"name": "SLM"}) == []
    assert wp.markup_risk("category", {**cat, "html_description": True},
                          {"description": "<style>a{}</style><p>x</p>"}) == ["style"]
    assert wp.markup_risk("category", {"slug": "x", "description": "Текст без разметки"}, {"name": "X"}) == []
    assert wp.markup_risk("product", {"description": "<style>a{}</style>"}, {}) == []
    assert wp.markup_lost({"description": "<style>a{}</style><p>1</p><p>2</p>"},
                          {"description": "a{}<p>1</p>"}) == {"style": 1, "p": 1}
    assert wp.markup_lost({"content": "<p>1</p>"}, {"content": "<p>1</p>"}) == {}


def test_wp_layout_guard_for_table_wrapper():
    """Новая обёртка class="table-responsive" на телефоне обрезает текст — такую правку не пишем."""
    old = '<div class="table-responsive"><table></table></div>'
    fixed = '<div style="overflow:auto;width:0;min-width:100%"><table></table></div>'
    assert wp.layout_risk("category", {"description": "<p>x</p>"}, {"description": old})
    assert wp.layout_risk("news", {"content": {"raw": ""}}, {"content": old})
    assert not wp.layout_risk("category", {"description": old}, {"description": old + "<p>y</p>"})  # уже была
    assert not wp.layout_risk("category", {"description": old}, {"description": fixed})
    assert not wp.layout_risk("product", {"description": old, "short_description": ""}, {"name": "X"})


def test_wp_product_description_goes_through_posts_api(monkeypatch, tmp_path):
    """Описание товара пишется через wp/v2/product: WooCommerce API вырезал бы <style> и <svg>."""
    styled = "<style>.amt-wrapper{max-width:1320px}</style><div class=\"amt-wrapper\"><p>Описание</p></div>"
    product = {"id": 1671, "slug": "am-tech-trackscan-sharp-49", "permalink": "https://am-tech.ru/product/x/",
               "description": "", "short_description": "", "meta_data": []}
    calls = []

    class FakeWP:
        def find(self, kind, slug):
            return dict(product)

        def api(self, method, path, **kw):
            calls.append((method, path, kw.get("json")))
            if method == "POST" and path == "wp/v2/product/1671":
                product["description"] = kw["json"]["content"]
            return dict(product)

        def update(self, kind, item_id, body):
            calls.append(("PUT", "wc/v3/products", body))
            return dict(product)

        def resave(self, kind, item_id):
            pass

    monkeypatch.setattr(wp, "WordPress", FakeWP)
    monkeypatch.setattr(wp, "BACKUP_DIR", tmp_path)
    monkeypatch.setattr(wp, "fetch_page", lambda url, fresh=True: {
        "html": styled, "text": "Описание", "title": "", "description": "", "meta_robots": "", "h1": [],
        "http_status": 200})
    res = wp.apply([{"slug": "am-tech-trackscan-sharp-49", "field": "body", "value": styled}])
    assert ("POST", "wp/v2/product/1671", {"content": styled}) in calls
    assert not any(c[0] == "PUT" for c in calls)  # WooCommerce API описание не получает
    assert res[0]["status"] == "применено" and res[0]["saved"]

    # Если WordPress всё же вырезал разметку — правка не считается сохранённой.
    def stripping_api(method, path, **kw):
        if method == "POST":
            product["description"] = kw["json"]["content"].replace("<style>", "").replace("</style>", "")
        return dict(product)

    monkeypatch.setattr(FakeWP, "api", lambda self, method, path, **kw: stripping_api(method, path, **kw))
    res = wp.apply([{"slug": "am-tech-trackscan-sharp-49", "field": "body", "value": styled}])
    assert res[0]["status"].startswith("РАЗМЕТКА ВЫРЕЗАНА: <style>×1") and not res[0]["saved"]


def test_wp_visible_text_skips_styles():
    html = "<style>.amt-wrapper{max-width:1320px}</style><div class=\"amt-wrapper\"><p>Описание &amp; ТТХ</p></div>"
    assert wp.text(html) == "Описание & ТТХ"
    assert wp.on_page({"text": "Купить Описание & ТТХ Заказать"}, "body", html)


def test_wp_on_page_ignores_wptexturize():
    assert wp.on_page({"text": "Каталог SLM 3D — принтеры применяются «в авиации»…"}, "body",
                      "<p>SLM 3D - принтеры применяются \"в авиации\"...</p>")


def test_wp_attribute_replace_and_empty_short_description():
    product = {"slug": "amt-43", "short_description": "", "meta_data": [], "attributes": [
        {"id": 3542, "name": "Габариты области построения ШхГхВ (мм)", "position": 0, "visible": True,
         "variation": False, "options": ["320х520х370"]},
        {"id": 3543, "name": "Максимальная масса изделия (кг)", "position": 3, "visible": True,
         "variation": False, "options": ["715"]}]}
    new = {"name": "Объём камеры построения, л", "options": ["61,6"]}
    body = wp.build_update(product, [{"field": "attribute", "old": "Максимальная масса изделия (кг)", "value": new}])
    assert body["attributes"][0]["id"] == 3542  # остальные характеристики уходят как были
    assert body["attributes"][1] == {"name": "Объём камеры построения, л", "options": ["61,6"], "visible": True,
                                     "variation": False, "position": 3}
    stored = wp.get_value({"attributes": body["attributes"]}, "attribute")
    assert wp.saved_ok(stored, {"field": "attribute", "old": "Максимальная масса изделия (кг)", "value": new})
    assert wp.on_page({"text": "Объём камеры построения, л 61,6"}, "attribute", new)
    try:
        wp.build_update(product, [{"field": "attribute", "old": "Нет такой", "value": new}])
        assert False, "должна быть ошибка"
    except ValueError:
        pass
    html = "<p>Установка внесена в <strong>Реестр российской промышленной продукции</strong>.</p>"
    assert wp.build_update(product, [{"field": "short_description", "value": html}]) == {"short_description": html}
