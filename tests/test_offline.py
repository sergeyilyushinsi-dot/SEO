"""Офлайн-тесты разбора данных (без обращений к API)."""
from seo import cluster, gap, positions, wp
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
