"""Главная am-tech.ru, вариант 2: первый экран (слайдер с видео) остаётся как есть, ниже — новые блоки.

Акценты: 1) отечественный производитель SLM-оборудования (линейка AMT), 2) 3D-сканеры и метрология,
3) интегратор аддитивного оборудования.
Результат — content/home/home-blocks.html: готовый HTML для виджета «HTML» Elementor (стили + блоки + форма
Bitrix24 той же, что в шаблоне 3699). Блок с id="contact-block" — на него уже ведут кнопки слайдера.

Факты: карточки AMT (атрибуты, реестровые записи), страницы «О компании», «Лизинг», услуги.
Кейсы и статьи выводятся из выгрузки, а скрипт на странице подменяет их на последние из REST API.

Запуск: python3 content/home/build_home_v2.py DATA_DIR
"""
import html
import json
import re
import sys
from pathlib import Path

HERE = Path(__file__).parent
DATA = Path(sys.argv[1])
THEME = HERE.parent / "theme"


def load(name):
    return json.loads((DATA / name).read_text())


def esc(s):
    return html.escape(html.unescape(str(s)), quote=True)


def img_of(item, size="medium_large"):
    m = (item.get("_embedded") or {}).get("wp:featuredmedia") or []
    if not m:
        return ""
    sizes = (m[0].get("media_details") or {}).get("sizes") or {}
    return (sizes.get(size) or sizes.get("large") or {}).get("source_url") or m[0].get("source_url", "")


def term_of(item):
    for group in (item.get("_embedded") or {}).get("wp:term") or []:
        for t in group:
            if t.get("taxonomy") in ("case_category", "news_category"):
                return html.unescape(t["name"])
    return ""


def excerpt(item, words=20):
    text = re.sub(r"<[^>]+>", " ", item.get("excerpt", {}).get("rendered", ""))
    text = re.sub(r"^\s*Коротко\s*[:.]\s*", "", html.unescape(text).strip())
    w = text.split()
    return " ".join(w[:words]) + ("…" if len(w) > words else "")


def date(item):
    y, m, d = item["date"][:10].split("-")
    return f"{d}.{m}.{y}"


cat = {c["slug"]: c["link"] for c in load("pcat.json")}
reps = load("catdata.json")["reps"]
slm = {p["slug"]: p for p in load("slm.json")}
scan = {p["slug"]: p for p in load("scan.json")}
cases, news = load("cases.json"), load("news.json")

# Линейка AMT: цифры — из атрибутов карточек (AMT-64 — из описания карточки), реестр — из карточек AMT-16 и AMT-32.
LINE = [
    ("amt-16", "AMT-16", "Ø165 × 225 мм", "1 × 500 Вт", "Компактная установка для НИОКР, прототипов и малых серий", "ГИСП № 10876808"),
    ("3d-printer-amt-32", "AMT-32", "320 × 320 × 375 мм", "2 × 500 Вт", "Два лазера, масса изделия до 300 кг", "ГИСП № 10901161"),
    ("amt-43", "AMT-43", "320 × 520 × 370 мм", "3 × 500 Вт", "Высокая производительность, замкнутый контур работы с порошком", ""),
    ("amt-45", "AMT-45", "450 × 450 × 450 мм", "4 × 500 Вт", "Крупные детали, полная автоматизация процессов", ""),
    ("3d-printer-amt-64", "AMT-64", "600 × 600 × 600 мм", "4 × 500 Вт", "Флагман: высота построения до 1200 мм", ""),
]

TECH = [
    ("EBM 3D-принтеры", "ebm-3d-print", "ebm-200", "Электронно-лучевое плавление в вакууме"),
    ("MBJ 3D-принтеры", "mbj-3d-print", "mbj-550-pro", "Печать металлическим связующим для серий"),
    ("SLA 3D-принтеры", "sla-3d-printery", "sla-600", "Прототипы, мастер-модели, литьё"),
    ("Керамические 3D-принтеры", "3d-print-keramika", "c-136", "Техническая и биосовместимая керамика"),
]

# 3D-сканеры: цифры — из атрибутов карточек; «в реестре СИ» — атрибут «Наличие в реестре средств измерений: Внесен».
SCANNERS = [
    ("3d-skaner-kscan-magic-ii", "KSCAN MAGIC II", "Ручной лазерный", "0,02 мм", "до 4 150 000 т/с", "1,35 кг"),
    ("3d-skaner-simscan-s", "SIMSCAN S", "Беспроводной", "0,015 мм", "до 8 100 000 т/с", "0,56 кг"),
    ("3d-skaner-trackscan-sharp-e", "TrackScan Sharp E", "С оптическим трекером", "0,025 мм", "до 6 000 000 т/с", "сканер 1,5 кг"),
    ("3d-skaner-nimbletrack-c", "NimbleTrack C+", "С оптическим трекером", "0,025 мм", "до 6 300 000 т/с", "сканер 1,3 кг"),
    ("lazernyj-geodezicheskij-skaner-mls10", "MLS10", "SLAM-сканер", "", "", ""),
]
SCAN_TYPES = [
    ("Ручные 3D-сканеры", "ruchnye-3d-skanery"),
    ("Лазерные 3D-сканеры", "lazernye-3d-skanery"),
    ("Беспроводные", "besprovodnoj-skaner"),
    ("Российские 3D-сканеры", "rossijskie-3d-skanery"),
    ("Геодезические", "geodesicheskie-3d-scaneri"),
    ("Все 3D-сканеры", "3d-scan"),
]

ROUTE = [
    ("Аудит производства", "Разбираем задачу, деталь и требования к материалу и производительности."),
    ("Подбор технологии", "Сравниваем SLM, EBM, MBJ, SLA и другие сценарии под вашу деталь."),
    ("Тестовая деталь", "Печатаем деталь или фрагмент и подтверждаем результат до поставки."),
    ("Испытания и ТЭО", "Контроль геометрии, свойства материала, расчёт экономики изготовления."),
    ("Поставка и пусконаладка", "Привозим, монтируем и запускаем оборудование на площадке."),
    ("Обучение и сервис", "Готовим технологов и операторов, обслуживаем и модернизируем."),
]

SERVICES = [
    ("3D-печать металлом", "/uslugi/3d-pechat-metallom/", "SLM и EBM на нашем оборудовании, расчёт по модели"),
    ("SLA-печать", "/uslugi/sla-pechat/", "Прототипы и мастер-модели до 600 мм"),
    ("3D-сканирование", "/uslugi/3d-skanirovanie/", "Оцифровка деталей и объектов, выезд по России"),
    ("Реверс-инжиниринг", "/uslugi/revers-inzhiniring/", "CAD-модель по физическому образцу"),
    ("Контроль геометрии", "/uslugi/kontrol-geometrii/", "Карта отклонений детали от CAD-модели"),
]

PARTNERS = [
    ("https://am-tech.ru/wp-content/uploads/2025/10/gazprom.png", "Газпром"),
    ("https://am-tech.ru/wp-content/uploads/2025/10/sibur_logo-1-1.png", "СИБУР"),
    ("https://am-tech.ru/wp-content/uploads/2025/10/engineering___-1-1.png", "ТДНТ Инжиниринг"),
    ("https://am-tech.ru/wp-content/uploads/2025/10/phptmgwdi-1-1.png", "АВТОТОР"),
    ("https://am-tech.ru/wp-content/uploads/2025/10/gerb_mgtu_imeni_n._je._baumana.svg.png", "МГТУ им. Н. Э. Баумана"),
]


def media(url, label):
    style = f' style="background-image:url({esc(url)});background-size:cover;background-position:center;"' if url else ""
    return f'<div class="amt-media"{style}><span class="amt-media__label">{esc(label)}</span></div>'


def card(item):
    label = term_of(item)
    return (f'<a class="amt-article" href="{esc(item["link"])}">{media(img_of(item), label)}'
            f'<div class="amt-article__body"><div class="amt-label">{esc(label)}</div>'
            f'<h3>{esc(item["title"]["rendered"])}</h3><p>{esc(excerpt(item))}</p>'
            f'<div class="amt-meta"><span>{date(item)}</span><span>Читать →</span></div></div></a>')


def line_cards():
    out = []
    for slug, model, area, laser, text, reg in LINE:
        p = slm[slug]
        badge = f'<span class="amh-badge">{esc(reg)}</span>' if reg else ""
        out.append(
            f'<a class="amh-model" href="{esc(p["link"])}"><span class="amh-model__img"><img src="{esc(p["img"])}" alt="SLM 3D-принтер {model}" loading="lazy">{badge}</span>'
            f'<span class="amh-model__name">{model}</span><span class="amh-model__text">{esc(text)}</span>'
            f'<span class="amh-model__specs"><span><i>Область</i>{esc(area)}</span><span><i>Лазеры</i>{esc(laser)}</span></span></a>')
    return "".join(out)


def scan_cards():
    out = []
    for slug, model, kind, acc, speed, weight in SCANNERS:
        p = scan[slug]
        reg = '<span class="amh-badge">В реестре СИ</span>' if p["attrs"].get("Наличие в реестре средств измерений") == "Внесен" else ""
        specs = "".join(f'<span><i>{k}</i>{esc(v)}</span>' for k, v in (("Точность", acc), ("Скорость", speed), ("Вес", weight)) if v)
        if not specs:
            specs = '<span><i>Тип</i>мобильное сканирование зданий и площадок</span>'
        out.append(
            f'<a class="amh-model" href="{esc(p["link"])}"><span class="amh-model__img"><img src="{esc(p["img"])}" alt="{esc(p["name"])}" loading="lazy">{reg}</span>'
            f'<span class="amh-model__name">{esc(model)}</span><span class="amh-model__text">{esc(kind)}</span>'
            f'<span class="amh-model__specs">{specs}</span></a>')
    return "".join(out)


def scan_types():
    return "".join(f'<a href="{esc(cat[slug])}">{esc(n)}</a>' for n, slug in SCAN_TYPES)


def tech_cards():
    return "".join(
        f'<a class="amh-tech" href="{esc(cat[slug])}"><span class="amh-tech__img"><img src="{esc(reps[rep]["img"])}" alt="{esc(name)}" loading="lazy"></span>'
        f'<span class="amh-tech__body"><b>{esc(name)}</b><span>{esc(text)}</span></span></a>'
        for name, slug, rep, text in TECH)


def route():
    return "".join(f'<li><b>{i:02d}</b><h3>{esc(t)}</h3><p>{esc(d)}</p></li>' for i, (t, d) in enumerate(ROUTE, 1))


def services():
    return "".join(
        f'<a class="amh-svc" href="https://am-tech.ru{href}"><b>{esc(n)}</b><span>{esc(t)}</span><i>→</i></a>'
        for n, href, t in SERVICES)


blog_css = "\n".join(l for l in (THEME / "case-template" / "blog-reference.css").read_text().splitlines() if "page-id-1733" not in l)

blocks = f"""<!-- AM.TECH: блоки главной после слайдера. Вставить в виджет «HTML» Elementor сразу под слайдером. -->
<div id="amt-blog" class="amt-home">

  <section class="amt-section amh-slm" id="amh-slm">
    <div class="amt-container">
      <div class="amh-slm__head">
        <div>
          <div class="amt-eyebrow">Отечественный производитель</div>
          <h2 class="amt-title amt-title--section">SLM 3D-принтеры по металлу<br>российского производства</h2>
        </div>
        <p class="amt-lead">AM.TECH — российский производитель промышленных 3D-принтеров по металлу: разрабатываем и собираем SLM-системы линейки AMT — от компактной AMT-16 до флагманской AMT-64 с высотой построения до 1200 мм. Собственное производство в России, открытое ПО PrintMate, тестовая печать вашей детали до покупки.</p>
      </div>
      <div class="amh-line">{line_cards()}</div>
      <div class="amh-facts">
        <div><b>Собственная разработка</b><span>Установки AMT проектируются и собираются в России, контроль качества на каждом этапе.</span></div>
        <div><b>Реестр ГИСП</b><span>AMT-16 и AMT-32 внесены в реестр российской промышленной продукции — для закупок по ПП 719.</span></div>
        <div><b>PrintMate</b><span>Собственная открытая платформа: более 90 параметров процесса под ваш порошок и задачу.</span></div>
        <div><b>Единая архитектура</b><span>Одна лазерно-оптическая система в серии AMT — режимы переносятся между установками.</span></div>
      </div>
      <div class="amh-actions">
        <a class="amt-button amt-button--primary js-scroll-to-contacts" href="#contact-block">Подобрать установку AMT <span>→</span></a>
        <a class="amt-button amt-button--secondary" href="{esc(cat['slm-3d-printeri'])}">Все SLM 3D-принтеры</a>
        <a class="amt-button amt-button--secondary" href="https://am-tech.ru/uslugi/3d-pechat-metallom/#calc">Тестовая печать детали</a>
      </div>
    </div>
  </section>

  <section class="amt-section amt-section--soft amh-scan" id="amh-scan">
    <div class="amt-container">
      <div class="amh-slm__head">
        <div>
          <div class="amt-eyebrow">3D-сканеры и метрология</div>
          <h2 class="amt-title amt-title--section">Промышленные 3D-сканеры<br>для контроля геометрии и реверс-инжиниринга</h2>
        </div>
        <p class="amt-lead">Ручные, беспроводные и трекинговые 3D-сканеры для контроля геометрии деталей и оснастки, реверс-инжиниринга и оцифровки крупных объектов, геодезические SLAM- и наземные сканеры. Модели внесены в реестр средств измерений. Покажем сканер на вашей детали.</p>
      </div>
      <div class="amh-chips">{scan_types()}</div>
      <div class="amh-line">{scan_cards()}</div>
      <div class="amh-facts">
        <div><b>Реестр средств измерений</b><span>KSCAN MAGIC II, SIMSCAN, TrackScan, NimbleTrack и другие модели внесены в реестр СИ.</span></div>
        <div><b>Демонстрация на вашей детали</b><span>Отсканируем деталь и покажем карту отклонений до покупки.</span></div>
        <div><b>Услуги сканирования</b><span>3D-сканирование, реверс-инжиниринг и контроль геометрии на нашем оборудовании.</span></div>
        <div><b>Выезд по России</b><span>Сканируем на площадке заказчика — крупные объекты и оснастку.</span></div>
      </div>
      <div class="amh-actions">
        <a class="amt-button amt-button--primary js-scroll-to-contacts" href="#contact-block">Подобрать 3D-сканер <span>→</span></a>
        <a class="amt-button amt-button--secondary" href="{esc(cat['3d-scan'])}">Все 3D-сканеры</a>
        <a class="amt-button amt-button--secondary" href="https://am-tech.ru/uslugi/3d-skanirovanie/">Заказать 3D-сканирование</a>
      </div>
    </div>
  </section>

  <section class="amt-section amh-integr" id="amh-integr">
    <div class="amt-container">
      <div class="amt-section__head">
        <div class="amt-section__copy">
          <div class="amt-eyebrow">Интегратор аддитивного оборудования</div>
          <h2 class="amt-title amt-title--section">Оборудование для аддитивного производства:<br>EBM, MBJ, SLA и керамика</h2>
          <p class="amt-lead">Кроме собственных SLM-систем и 3D-сканеров поставляем и внедряем оборудование для печати металлом (EBM, MBJ), фотополимерами и керамикой — и собираем технологическую цепочку под требования к материалу, точности и себестоимости.</p>
        </div>
        <a class="amt-button amt-button--secondary" href="https://am-tech.ru/katalog/">Весь каталог <span>→</span></a>
      </div>
      <div class="amh-techs">{tech_cards()}</div>
      <h3 class="amh-route__title">Внедрение аддитивных технологий под ключ — шесть этапов</h3>
      <ol class="amh-route">{route()}</ol>
    </div>
  </section>

  <section class="amt-section amt-section--soft amh-svcs" id="amh-services">
    <div class="amt-container amh-svcs__grid">
      <div>
        <div class="amt-eyebrow">Услуги на нашем оборудовании</div>
        <h2 class="amt-title amt-title--section">3D-печать металлом<br>и 3D-сканирование на заказ</h2>
        <p class="amt-lead">Загрузите STL-модель — калькулятор оценит объём и стоимость печати, инженер подтвердит расчёт. Срок — от 3 дней.</p>
        <a class="amt-button amt-button--primary" href="https://am-tech.ru/uslugi/3d-pechat-metallom/#calc">Рассчитать печать <span>→</span></a>
      </div>
      <div class="amh-svc-list">{services()}</div>
    </div>
  </section>

  <section class="amt-section" id="amh-cases">
    <div class="amt-container">
      <div class="amt-section__head">
        <div class="amt-section__copy"><div class="amt-eyebrow">Практика</div><h2 class="amt-title amt-title--section">Кейсы применения аддитивных технологий<br>и 3D-сканирования</h2></div>
        <a class="amt-button amt-button--secondary" href="https://am-tech.ru/teknologii/">Все кейсы <span>→</span></a>
      </div>
      <div class="amt-articles" data-amh-feed="cases">{''.join(card(c) for c in cases)}</div>
      <div class="amh-trust">
        <div class="amh-trust__head"><h3>Нам доверяют</h3><p>Промышленные компании и инженерные вузы, с которыми работает AM.TECH</p></div>
        <div class="amh-trust__grid">{''.join(f'<div class="amh-trust__item"><span class="amh-trust__logo"><img src="{esc(u)}" alt="{esc(a)}"></span><span class="amh-trust__name">{esc(a)}</span></div>' for u, a in PARTNERS)}</div>
      </div>
    </div>
  </section>

  <section class="amt-section amt-section--soft" id="amh-blog">
    <div class="amt-container">
      <div class="amt-section__head">
        <div class="amt-section__copy"><div class="amt-eyebrow">Блог</div><h2 class="amt-title amt-title--section">Новости и статьи<br>об аддитивных технологиях</h2></div>
        <div class="amh-head-links"><a class="amt-button amt-button--secondary" href="https://am-tech.ru/video-obzory/">Видеообзоры</a><a class="amt-button amt-button--secondary" href="https://am-tech.ru/blog/">Все материалы <span>→</span></a></div>
      </div>
      <div class="amt-articles" data-amh-feed="news">{''.join(card(n) for n in news)}</div>
    </div>
  </section>

  <section class="amt-section amt-section--dark amt-cta" id="contact-block">
    <div class="amt-container amh-contact">
      <div>
        <div class="amt-eyebrow">Инженерная консультация</div>
        <h2 class="amt-title amt-title--section">Подберём оборудование<br>под вашу <em>задачу</em></h2>
        <p class="amt-lead">Пришлите чертёж, 3D-модель или описание процесса — подберём установку AMT или технологию, подготовим КП и предложим тестовую деталь. Оборудование доступно в <a href="https://am-tech.ru/leasing/">лизинг</a>.</p>
        <div class="amt-contacts"><a href="tel:+74951091191">+7 (495) 109‑11‑91</a><a href="mailto:sales@am-tech.ru">sales@am-tech.ru</a><span>Москва, Дмитровское ш., д. 9, стр. 3</span></div>
      </div>
      <div class="amh-form">
        <script data-b24-form="inline/2/qjtgb9" data-skip-moving="true">(function(w,d,u){{var s=d.createElement('script');s.async=true;s.src=u+'?'+(Date.now()/180000|0);var h=d.getElementsByTagName('script')[0];h.parentNode.insertBefore(s,h);}})(window,document,'https://cdn-ru.bitrix24.ru/b29360544/crm/form/loader_2.js');</script>
      </div>
    </div>
  </section>
</div>
<script>
/* Кейсы и статьи: подставляем последние записи из REST API (в разметке — запасной вариант на момент вставки) */
(function () {{
  function esc(s) {{ var d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }}
  function text(h) {{ var d = document.createElement('div'); d.innerHTML = h || ''; return (d.textContent || '').replace(/^\\s*Коротко\\s*[:.]\\s*/, '').trim(); }}
  function cut(t, n) {{ var w = t.split(/\\s+/); return w.length > n ? w.slice(0, n).join(' ') + '…' : t; }}
  document.querySelectorAll('[data-amh-feed]').forEach(function (box) {{
    var type = box.getAttribute('data-amh-feed');
    fetch('/wp-json/wp/v2/' + type + '?per_page=3&_embed=wp:featuredmedia,wp:term').then(function (r) {{ return r.ok ? r.json() : null; }}).then(function (items) {{
      if (!items || !items.length) return;
      box.innerHTML = items.map(function (it) {{
        var m = it._embedded && it._embedded['wp:featuredmedia'] && it._embedded['wp:featuredmedia'][0];
        var sz = m && m.media_details && m.media_details.sizes;
        var img = m ? ((sz && (sz.medium_large || sz.large) || {{}}).source_url || m.source_url) : '';
        var label = '';
        (it._embedded && it._embedded['wp:term'] || []).forEach(function (g) {{ g.forEach(function (t) {{ if (!label) label = t.name; }}); }});
        var d = it.date.slice(0, 10).split('-').reverse().join('.');
        return '<a class="amt-article" href="' + it.link + '"><div class="amt-media"' + (img ? ' style="background-image:url(' + img + ');background-size:cover;background-position:center;"' : '') + '><span class="amt-media__label">' + label + '</span></div>' +
          '<div class="amt-article__body"><div class="amt-label">' + label + '</div><h3>' + it.title.rendered + '</h3><p>' + esc(cut(text(it.excerpt.rendered), 20)) + '</p>' +
          '<div class="amt-meta"><span>' + d + '</span><span>Читать →</span></div></div></a>';
      }}).join('');
    }}).catch(function () {{}});
  }});
}})();
</script>
<style id="amt-home-styles">
{blog_css}
{(HERE / 'home-v2.css').read_text()}
</style>
"""

(HERE / "home-blocks.html").write_text(blocks)
print("ok", len(blocks))
