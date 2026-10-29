"""Главная am-tech.ru (вариант 2) как шаблон Elementor из стандартных виджетов.

Тексты, картинки, кнопки и ссылки редактируются в Elementor мышкой: заголовки — виджет «Заголовок», абзацы —
«Текстовый редактор», фото — «Изображение», кнопки — «Кнопка», логотипы — «Блок изображения». Новую модель
добавляют копированием карточки («Дублировать»). Код только в двух виджетах «HTML», которые не нужно
трогать: «Стили главной» (оформление) и ленты кейсов/статей (обновляются сами), плюс форма Bitrix24.

Результат:
- content/home/amtech-home-elementor.json — импорт: «Шаблоны» → «Сохранённые шаблоны» → «Импортировать».
- content/home/elementor-preview.html — превью разметки, близкой к Elementor (для проверки).

Запуск: python3 content/home/build_elementor.py DATA_DIR
"""
import hashlib
import html
import json
import runpy
import sys
from pathlib import Path

HERE = Path(__file__).parent
V2 = runpy.run_path(str(HERE / "build_home_v2.py"), run_name="v2")  # данные и карточки лент из варианта 2
esc = V2["esc"]
cat, reps, slm, scan = V2["cat"], V2["reps"], V2["slm"], V2["scan"]

_n = [0]


def eid(seed=""):
    _n[0] += 1
    return hashlib.md5(f"amte{_n[0]}{seed}".encode()).hexdigest()[:7]


# ---------- конструкторы элементов Elementor ----------
def con(children, cls="", title="", row=False, element_id="", inner=True):
    s = {"content_width": "full", "flex_direction": "row" if row else "column"}
    if cls:
        s["_css_classes"] = cls  # для контейнеров Elementor использует css_classes
        s["css_classes"] = cls
    if title:
        s["_title"] = title
    if element_id:
        s["_element_id"] = element_id
    return {"id": eid(cls + title), "elType": "container", "settings": s, "elements": children, "isInner": inner}


def w(kind, settings, cls=""):
    if cls:
        settings["_css_classes"] = cls
    return {"id": eid(kind), "elType": "widget", "widgetType": kind, "settings": settings, "elements": []}


def link(url):
    return {"url": url, "is_external": "", "nofollow": "", "custom_attributes": ""}


def heading(text, tag="h2", cls="", url=""):
    s = {"title": text, "header_size": tag}
    if url:
        s["link"] = link(url)
    return w("heading", s, cls)


def text(htm, cls=""):
    return w("text-editor", {"editor": htm}, cls)


def image(url, alt, cls="", href=""):
    s = {"image": {"url": url, "id": "", "alt": alt, "source": "library"}, "image_size": "full"}
    if href:
        s["link_to"] = "custom"
        s["link"] = link(href)
    return w("image", s, cls)


def button(label, url, primary=False, cls=""):
    return w("button", {"text": label, "link": link(url)},
             ("amte-btn amte-btn--primary " if primary else "amte-btn ") + cls)


def image_box(img, title, desc="", url="", cls=""):
    s = {"image": {"url": img, "id": "", "alt": title}, "title_text": title, "description_text": desc, "title_size": "h3"}
    if url:
        s["link"] = link(url)
    return w("image-box", s, cls)


def html_w(code, title):
    return w("html", {"html": code, "_title": title})


# ---------- блоки ----------
def head(eyebrow, h2, lead):
    return con([con([text(f"<p>{eyebrow}</p>", "amte-eyebrow"), heading(h2, "h2", "amte-h2")], "amte-head__copy"),
                text(f"<p>{lead}</p>", "amte-lead")], "amte-head", row=True)


def facts(items):
    return con([text(f"<p><strong>{t}</strong><br>{d}</p>", "amte-fact") for t, d in items], "amte-facts", row=True)


def spec_html(pairs):
    return "".join(f"<p><span>{k}</span><br><strong>{v}</strong></p>" for k, v in pairs if v)


def model_card(p, model, desc, specs, badge=""):
    kids = []
    if badge:
        kids.append(text(f"<p>{badge}</p>", "amte-badge"))
    kids += [image(p["img"], p["name"], "amte-card__img", p["link"]),
             heading(model, "h3", "amte-card__title", p["link"]),
             text(f"<p>{desc}</p>", "amte-card__text"),
             text(spec_html(specs), "amte-card__specs")]
    return con(kids, "amte-card", title=model)


def section(children, cls, title, element_id=""):
    return con([con(children, "amt-container amte-inner")], f"amt-section {cls}", title=title, element_id=element_id)


slm_cards = [model_card(slm[s], m, d, [("Область", a), ("Лазеры", l)], reg) for s, m, a, l, d, reg in V2["LINE"]]
scan_cards = []
for s, m, kind, acc, speed, weight in V2["SCANNERS"]:
    p = scan[s]
    reg = "В реестре СИ" if p["attrs"].get("Наличие в реестре средств измерений") == "Внесен" else ""
    specs = [("Точность", acc), ("Скорость", speed), ("Вес", weight)] if acc else [("Тип", "мобильное сканирование зданий и площадок")]
    scan_cards.append(model_card(p, m, kind, specs, reg))

SLM = section([
    head("Отечественный производитель", "SLM 3D-принтеры по металлу российского производства",
         "AM.TECH — российский производитель промышленных 3D-принтеров по металлу: разрабатываем и собираем SLM-системы линейки AMT — от компактной AMT-16 до флагманской AMT-64 с высотой построения до 1200 мм. Собственное производство в России, открытое ПО PrintMate, тестовая печать вашей детали до покупки."),
    con(slm_cards, "amte-cards", row=True, title="Модели AMT"),
    facts([("Собственная разработка", "Установки AMT проектируются и собираются в России, контроль качества на каждом этапе."),
           ("Реестр ГИСП", "AMT-16 и AMT-32 внесены в реестр российской промышленной продукции — для закупок по ПП 719."),
           ("PrintMate", "Собственная открытая платформа: более 90 параметров процесса под ваш порошок и задачу."),
           ("Единая архитектура", "Одна лазерно-оптическая система в серии AMT — режимы переносятся между установками.")]),
    con([button("Подобрать установку AMT", "#contact-block", True),
         button("Все SLM 3D-принтеры", cat["slm-3d-printeri"]),
         button("Тестовая печать детали", "https://am-tech.ru/uslugi/3d-pechat-metallom/#calc")], "amte-actions", row=True),
], "", "SLM-принтеры AMT")

SCAN = section([
    head("3D-сканеры и метрология", "Промышленные 3D-сканеры для контроля геометрии и реверс-инжиниринга",
         "Ручные, беспроводные и трекинговые 3D-сканеры для контроля геометрии деталей и оснастки, реверс-инжиниринга и оцифровки крупных объектов, геодезические SLAM- и наземные сканеры. Модели внесены в реестр средств измерений. Покажем сканер на вашей детали."),
    con([button(n, cat[s], cls="amte-chip") for n, s in V2["SCAN_TYPES"]], "amte-chips", row=True, title="Типы сканеров"),
    con(scan_cards, "amte-cards amte-cards--scan", row=True, title="Модели сканеров"),
    facts([("Реестр средств измерений", "KSCAN MAGIC II, SIMSCAN, TrackScan, NimbleTrack и другие модели внесены в реестр СИ."),
           ("Демонстрация на вашей детали", "Отсканируем деталь и покажем карту отклонений до покупки."),
           ("Услуги сканирования", "3D-сканирование, реверс-инжиниринг и контроль геометрии на нашем оборудовании."),
           ("Выезд по России", "Сканируем на площадке заказчика — крупные объекты и оснастку.")]),
    con([button("Подобрать 3D-сканер", "#contact-block", True),
         button("Все 3D-сканеры", cat["3d-scan"]),
         button("Заказать 3D-сканирование", "https://am-tech.ru/uslugi/3d-skanirovanie/")], "amte-actions", row=True),
], "amt-section--soft", "3D-сканеры")

INTEGR = section([
    head("Интегратор аддитивного оборудования", "Оборудование для аддитивного производства: EBM, MBJ, SLA и керамика",
         "Кроме собственных SLM-систем и 3D-сканеров поставляем и внедряем оборудование для печати металлом (EBM, MBJ), фотополимерами и керамикой — и собираем технологическую цепочку под требования к материалу, точности и себестоимости."),
    con([image_box(reps[r]["img"], n, d, cat[s], "amte-tech") for n, s, r, d in V2["TECH"]], "amte-techs", row=True, title="Технологии"),
    heading("Внедрение аддитивных технологий под ключ — шесть этапов", "h3", "amte-h3"),
    con([text(f"<p><span>{i:02d}</span></p><h4>{t}</h4><p>{d}</p>", "amte-step") for i, (t, d) in enumerate(V2["ROUTE"], 1)], "amte-route", row=True, title="Этапы"),
    con([button("Весь каталог", "https://am-tech.ru/katalog/")], "amte-actions", row=True),
], "", "Интегратор")

SERV = section([
    con([con([text("<p>Услуги на нашем оборудовании</p>", "amte-eyebrow"),
              heading("3D-печать металлом и 3D-сканирование на заказ", "h2", "amte-h2"),
              text("<p>Загрузите STL-модель — калькулятор оценит объём и стоимость печати, инженер подтвердит расчёт. Срок — от 3 дней.</p>", "amte-lead"),
              button("Рассчитать печать", "https://am-tech.ru/uslugi/3d-pechat-metallom/#calc", True)], "amte-svcs__intro"),
         con([con([heading(n, "h3", "amte-svc__title", "https://am-tech.ru" + h), text(f"<p>{d}</p>", "amte-svc__text")], "amte-svc", row=True, title=n)
              for n, h, d in V2["SERVICES"]], "amte-svc-list")], "amte-svcs", row=True),
], "amt-section--soft", "Услуги")

feed_cases = '<div class="amt-articles" data-amh-feed="cases">' + "".join(V2["card"](c) for c in V2["cases"]) + "</div>"
feed_news = '<div class="amt-articles" data-amh-feed="news">' + "".join(V2["card"](n) for n in V2["news"]) + "</div>"

CASES = section([
    con([con([text("<p>Практика</p>", "amte-eyebrow"), heading("Кейсы применения аддитивных технологий и 3D-сканирования", "h2", "amte-h2")], "amte-head__copy"),
         button("Все кейсы", "https://am-tech.ru/teknologii/")], "amte-head amte-head--btn", row=True),
    html_w(feed_cases, "Лента кейсов (обновляется сама)"),
    con([con([heading("Нам доверяют", "h3", "amte-h3"),
              text("<p>Промышленные компании и инженерные вузы, с которыми работает AM.TECH</p>", "amte-trust__sub")], "amte-trust__head", row=True),
         con([image_box(u, a, "", "", "amte-logo") for u, a in V2["PARTNERS"]], "amte-logos", row=True, title="Логотипы")], "amte-trust", title="Нам доверяют"),
], "", "Кейсы и партнёры")

BLOG = section([
    con([con([text("<p>Блог</p>", "amte-eyebrow"), heading("Новости и статьи об аддитивных технологиях", "h2", "amte-h2")], "amte-head__copy"),
         con([button("Видеообзоры", "https://am-tech.ru/video-obzory/"), button("Все материалы", "https://am-tech.ru/blog/")], "amte-actions amte-actions--tight", row=True)],
        "amte-head amte-head--btn", row=True),
    html_w(feed_news, "Лента статей (обновляется сама)"),
], "amt-section--soft", "Блог")

FORM = '<script data-b24-form="inline/2/qjtgb9" data-skip-moving="true">(function(w,d,u){var s=d.createElement(\'script\');s.async=true;s.src=u+\'?\'+(Date.now()/180000|0);var h=d.getElementsByTagName(\'script\')[0];h.parentNode.insertBefore(s,h);})(window,document,\'https://cdn-ru.bitrix24.ru/b29360544/crm/form/loader_2.js\');</script>'
CONTACT = section([
    con([con([text("<p>Инженерная консультация</p>", "amte-eyebrow"),
              heading("Подберём оборудование под вашу задачу", "h2", "amte-h2"),
              text('<p>Пришлите чертёж, 3D-модель или описание процесса — подберём установку AMT или технологию, подготовим КП и предложим тестовую деталь. Оборудование доступно в <a href="https://am-tech.ru/leasing/">лизинг</a>.</p>', "amte-lead"),
              text('<p><a href="tel:+74951091191">+7 (495) 109-11-91</a> · <a href="mailto:sales@am-tech.ru">sales@am-tech.ru</a> · Москва, Дмитровское ш., д. 9, стр. 3</p>', "amte-contacts")], "amte-contact__copy"),
         con([html_w(FORM, "Форма Bitrix24")], "amte-form")], "amte-contact", row=True),
], "amt-section--dark", "Заявка", element_id="contact-block")

# Стили: блог (#amt-blog) + оформление стандартных виджетов Elementor (elementor-home.css) + скрипт лент
blog_css = V2["blog_css"]
v2_blocks = (HERE / "home-blocks.html").read_text()
feed_js = v2_blocks[v2_blocks.index("<script>\n/* Кейсы и статьи"):v2_blocks.index("</script>", v2_blocks.index("<script>\n/* Кейсы и статьи")) + 9]
STYLE = f'<style id="amte-styles">\n{blog_css}\n{(HERE / "elementor-home.css").read_text()}\n</style>\n{feed_js}'

ROOT = con([html_w(STYLE, "Стили главной (не удалять)"), SLM, SCAN, INTEGR, SERV, CASES, BLOG, CONTACT],
           "amt-home amte", title="Главная AM.TECH — блоки", element_id="amt-blog", inner=False)

template = {"version": "0.4", "title": "Главная AM.TECH — блоки под слайдером", "type": "container", "content": [ROOT], "page_settings": []}
(HERE / "amtech-home-elementor.json").write_text(json.dumps(template, ensure_ascii=False, indent=1))


# ---------- превью: разметка, близкая к фронтенду Elementor ----------
def render(e, top=False):
    s = e["settings"]
    cls = s.get("_css_classes", "")
    idattr = f' id="{s["_element_id"]}"' if s.get("_element_id") else ""
    if e["elType"] == "container":
        kids = "".join(render(c) for c in e["elements"])
        kind = "e-parent" if not e["isInner"] else "e-child"
        return f'<div class="elementor-element elementor-element-{e["id"]} e-con-full e-flex e-con {kind} {cls}"{idattr} data-element_type="container" style="--flex-direction:{s["flex_direction"]};">{kids}</div>'
    t = e["widgetType"]
    if t == "heading":
        inner = esc(s["title"])
        if s.get("link"):
            inner = f'<a href="{esc(s["link"]["url"])}">{inner}</a>'
        body = f'<{s["header_size"]} class="elementor-heading-title elementor-size-default">{inner}</{s["header_size"]}>'
    elif t == "text-editor":
        body = s["editor"]
    elif t == "image":
        img = f'<img src="{esc(s["image"]["url"])}" alt="{esc(s["image"]["alt"])}">'
        body = f'<a href="{esc(s["link"]["url"])}">{img}</a>' if s.get("link") else img
    elif t == "button":
        body = (f'<div class="elementor-button-wrapper"><a class="elementor-button elementor-button-link elementor-size-sm" href="{esc(s["link"]["url"])}">'
                f'<span class="elementor-button-content-wrapper"><span class="elementor-button-text">{esc(s["text"])}</span></span></a></div>')
    elif t == "image-box":
        title = esc(s["title_text"])
        if s.get("link"):
            title = f'<a href="{esc(s["link"]["url"])}">{title}</a>'
        desc = f'<p class="elementor-image-box-description">{esc(s["description_text"])}</p>' if s.get("description_text") else ""
        body = (f'<div class="elementor-image-box-wrapper"><figure class="elementor-image-box-img"><img src="{esc(s["image"]["url"])}" alt="{esc(s["image"]["alt"])}"></figure>'
                f'<div class="elementor-image-box-content"><h3 class="elementor-image-box-title">{title}</h3>{desc}</div></div>')
    else:
        body = s["html"]
    return (f'<div class="elementor-element elementor-element-{e["id"]} elementor-widget elementor-widget-{t} {cls}" data-element_type="widget" data-widget_type="{t}.default">'
            f'<div class="elementor-widget-container">{body}</div></div>')


(HERE / "elementor-preview.html").write_text(render(ROOT))
print("ok", _n[0], "элементов")
