"""Прототип новой главной am-tech.ru в дизайне блога (#amt-blog).

Данные — с сайта через REST (кейсы, новости, видео, категории, товары), факты о компании — со страницы
/o-kompanii/. Результат: content/home/home-prototype.html (тело страницы со стилями) — основа для шаблона
главной (main-page.php) после согласования.

Запуск: python3 content/home/build_home.py DATA_DIR
где DATA_DIR — папка с выгрузками cases.json, news.json, videos.json, catdata.json, pcat.json.
"""
import html
import json
import re
import sys
from pathlib import Path

HERE = Path(__file__).parent
DATA = Path(sys.argv[1])


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


def excerpt(item, words=22):
    raw = item.get("excerpt", {}).get("rendered", "")
    text = re.sub(r"<[^>]+>", " ", raw)
    text = re.sub(r"^\s*Коротко\s*[:.]\s*", "", html.unescape(text).strip())
    w = text.split()
    return " ".join(w[:words]) + ("…" if len(w) > words else "")


def date(item):
    y, m, d = item["date"][:10].split("-")
    return f"{d}.{m}.{y}"


cat = {c["slug"]: c["link"] for c in load("pcat.json")}
reps = load("catdata.json")["reps"]
cases, news, videos = load("cases.json"), load("news.json"), load("videos.json")

# Направления каталога: ссылка на раздел, фото модели раздела, 2–3 модели для быстрых ссылок.
DIRECTIONS = [
    ("SLM 3D-принтеры", "Металл", "slm-3d-printeri", "3d-printer-amt-32",
     "Собственная линейка AMT: печать металлических деталей сложной геометрии.", ["amt-16", "3d-printer-amt-32"]),
    ("EBM 3D-принтеры", "Металл", "ebm-3d-print", "ebm-200",
     "Электронно-лучевое плавление в вакууме — для титановых и тугоплавких сплавов.", ["ebm-200"]),
    ("MBJ 3D-принтеры", "Металл, серия", "mbj-3d-print", "mbj-550-pro",
     "Печать металлическим связующим для многодетальных построений и серий.", ["mbj-550-pro"]),
    ("SLA 3D-принтеры", "Фотополимеры", "sla-3d-printery", "sla-600",
     "Точные прототипы, мастер-модели и выжигаемые модели для литья.", ["sla-600"]),
    ("Керамические 3D-принтеры", "Керамика", "3d-print-keramika", "c-136",
     "Техническая и биосовместимая керамика сложной формы.", ["c-136"]),
    ("3D-сканеры", "Метрология", "3d-scan", "3d-skaner-kscan-magic",
     "Ручные, трекинговые и беспроводные сканеры для контроля геометрии и реверс-инжиниринга.",
     ["3d-skaner-kscan-magic", "3d-skaner-trackscan-sharp-e", "3d-skaner-simscan-s"]),
    ("Геодезические 3D-сканеры", "Геодезия", "geodesicheskie-3d-scaneri", "lazernyj-geodezicheskij-skaner-mls10",
     "Наземные и SLAM-сканеры для обследования зданий и площадок.", ["lazernyj-geodezicheskij-skaner-mls10"]),
]

MODEL_LABEL = {
    "amt-16": "AMT-16", "3d-printer-amt-32": "AMT-32", "ebm-200": "EBM-200", "mbj-550-pro": "MBJ-550 PRO",
    "sla-600": "SLA-600", "c-136": "C-136", "3d-skaner-kscan-magic": "KSCAN MAGIC",
    "3d-skaner-trackscan-sharp-e": "TrackScan Sharp E", "3d-skaner-simscan-s": "SIMSCAN S",
    "lazernyj-geodezicheskij-skaner-mls10": "MLS10",
}

SERVICES = [
    ("3D-печать металлом", "/uslugi/3d-pechat-metallom/", "SLM и EBM на собственном оборудовании, расчёт по модели, постобработка."),
    ("SLA-печать", "/uslugi/sla-pechat/", "Прототипы, мастер-модели и выжигаемые модели для литья."),
    ("3D-сканирование", "/uslugi/3d-skanirovanie/", "Оцифровка деталей и объектов, выезд по России."),
    ("Реверс-инжиниринг", "/uslugi/revers-inzhiniring/", "CAD-модель по физическому образцу для производства и ремонта."),
    ("Контроль геометрии", "/uslugi/kontrol-geometrii/", "Сравнение детали с CAD-моделью и карта отклонений."),
]


def media(url, label):
    style = f' style="background-image:url({esc(url)});background-size:cover;background-position:center;"' if url else ""
    lab = f'<span class="amt-media__label">{esc(label)}</span>' if label else ""
    return f'<div class="amt-media"{style}>{lab}</div>'


def article_card(item, label=None):
    label = label if label is not None else term_of(item)
    return (f'<a class="amt-article" href="{esc(item["link"])}">{media(img_of(item), label)}'
            f'<div class="amt-article__body"><div class="amt-label">{esc(label)}</div>'
            f'<h3>{esc(item["title"]["rendered"])}</h3><p>{esc(excerpt(item))}</p>'
            f'<div class="amt-meta"><span>{date(item)}</span><span>Читать →</span></div></div></a>')


def directions():
    out = []
    for name, kind, slug, rep, text, models in DIRECTIONS:
        r = reps.get(rep, {})
        chips = "".join(f'<a href="{esc(reps[m]["link"])}">{esc(MODEL_LABEL.get(m, reps[m]["name"]))}</a>'
                        for m in models if m in reps)
        out.append(
            f'<div class="amh-cat"><a class="amh-cat__link" href="{esc(cat[slug])}" aria-label="{esc(name)}"></a>'
            f'<div class="amh-cat__img"><img src="{esc(r.get("img", ""))}" alt="{esc(name)}" loading="lazy"></div>'
            f'<div class="amh-cat__body"><div class="amt-label">{esc(kind)}</div><h3>{esc(name)}</h3><p>{esc(text)}</p>'
            f'<div class="amh-cat__chips">{chips}</div></div></div>')
    # Восьмая плитка — помощь в выборе (сетка 4×N без пустых мест)
    out.append(
        '<a class="amh-cat amh-cat--help" href="#amh-contact"><div class="amh-cat__body">'
        '<div class="amt-label">Подбор</div><h3>Не знаете, какая технология нужна?</h3>'
        '<p>Пришлите чертёж или 3D-модель детали — инженер предложит технологию, модель и тестовую деталь.</p>'
        '<span class="amh-cat__more">Получить консультацию →</span></div></a>')
    return "".join(out)


def services():
    return "".join(
        f'<a class="amt-direction amh-service" href="https://am-tech.ru{href}"><div class="amt-direction__index">'
        f'<span>{i:02d}</span> / УСЛУГА</div><div><h3>{esc(n)}</h3><p>{esc(t)}</p></div></a>'
        for i, (n, href, t) in enumerate(SERVICES, 1))


def videos_html():
    return "".join(
        f'<a class="amh-video" href="{esc(v["link"])}"><span class="amh-video__img" style="background-image:url({esc(img_of(v))})">'
        f'<span class="amh-video__play">▶</span></span><span class="amh-video__title">{esc(v["title"]["rendered"])}</span></a>'
        for v in videos)


PARTNERS = [
    ("https://am-tech.ru/wp-content/uploads/2025/10/gazprom.png", "Газпром"),
    ("https://am-tech.ru/wp-content/uploads/2025/10/sibur_logo-1-1.png", "СИБУР"),
    ("https://am-tech.ru/wp-content/uploads/2025/10/engineering___-1-1.png", "ТДНТ Инжиниринг"),
    ("https://am-tech.ru/wp-content/uploads/2025/10/phptmgwdi-1-1.png", "АВТОТОР"),
    ("https://am-tech.ru/wp-content/uploads/2025/10/gerb_mgtu_imeni_n._je._baumana.svg.png", "МГТУ им. Н. Э. Баумана"),
]

hero_img = reps["3d-printer-amt-32"]["img"]

body = f"""
<div id="amt-blog" class="amt-home">
  <section class="amt-hero amh-hero">
    <div class="amt-container amh-hero__grid">
      <div>
        <div class="amt-eyebrow">Российский производитель · аддитивное производство и 3D-метрология</div>
        <h1 class="amt-title">Промышленные 3D-принтеры и 3D-сканеры AM.TECH</h1>
        <p class="amt-lead">Разрабатываем и производим SLM-системы AMT, поставляем оборудование для печати металлом, фотополимерами и керамикой, 3D-сканеры для контроля геометрии — и внедряем технологию под задачу предприятия: от тестовой детали до обучения и сервиса.</p>
        <div class="amt-hero__actions">
          <a class="amt-button amt-button--primary" href="#amh-catalog">Подобрать оборудование <span>→</span></a>
          <a class="amt-button amt-button--secondary" href="https://am-tech.ru/uslugi/">Услуги и расчёт печати</a>
        </div>
      </div>
      <div class="amh-hero__visual">
        <img src="{esc(hero_img)}" alt="SLM 3D-принтер AMT-32" fetchpriority="high">
        <a class="amh-hero__badge" href="{esc(reps['3d-printer-amt-32']['link'])}"><b class="amt-nowrap">AMT-32</b><span>SLM-система собственной разработки · в реестре ГИСП</span></a>
      </div>
    </div>
    <div class="amt-container">
      <div class="amt-trust amh-stats">
        <div class="amt-trust__item"><b>Собственное производство</b><span>SLM-установки линейки AMT разрабатываются и собираются в России.</span></div>
        <div class="amt-trust__item"><b>6 технологий 3D-печати</b><span>SLM, EBM, MBJ, SLA, керамика и др. + промышленная 3D-метрология.</span></div>
        <div class="amt-trust__item"><b>27+ инженеров</b><span>Тестовая печать, пусконаладка, обучение и сервис в одном проекте.</span></div>
      </div>
    </div>
  </section>

  <nav class="amt-topics amh-nav" aria-label="Разделы">
    <div class="amt-container amt-topics__row">
      <a class="amt-topic is-active" href="#amh-catalog">Оборудование</a>
      <a class="amt-topic" href="#amh-services">Услуги</a>
      <a class="amt-topic" href="#amh-cases">Кейсы</a>
      <a class="amt-topic" href="#amh-why">О компании</a>
      <a class="amt-topic" href="#amh-blog">Блог и видео</a>
      <a class="amt-topic" href="#amh-contact">Контакты</a>
    </div>
  </nav>

  <section class="amt-section" id="amh-catalog">
    <div class="amt-container">
      <div class="amt-section__head">
        <div class="amt-section__copy"><div class="amt-eyebrow">Каталог</div><h2 class="amt-title amt-title--section">Оборудование для аддитивного<br>производства и 3D-метрологии</h2></div>
        <a class="amt-button amt-button--secondary" href="https://am-tech.ru/katalog/">Весь каталог <span>→</span></a>
      </div>
      <div class="amh-cats">{directions()}</div>
    </div>
  </section>

  <section class="amt-section amt-section--soft" id="amh-services">
    <div class="amt-container">
      <div class="amt-section__head">
        <div class="amt-section__copy"><div class="amt-eyebrow">Услуги</div><h2 class="amt-title amt-title--section">Печать, сканирование и контроль<br>на нашем оборудовании</h2>
        <p class="amt-lead">Загрузите STL-модель — калькулятор оценит объём и стоимость печати, инженер подтвердит расчёт.</p></div>
        <a class="amt-button amt-button--primary" href="https://am-tech.ru/uslugi/3d-pechat-metallom/#calc">Рассчитать печать <span>→</span></a>
      </div>
      <div class="amt-directions amh-services">{services()}</div>
    </div>
  </section>

  <section class="amt-section" id="amh-cases">
    <div class="amt-container">
      <div class="amt-section__head">
        <div class="amt-section__copy"><div class="amt-eyebrow">Практика</div><h2 class="amt-title amt-title--section">Кейсы применения</h2></div>
        <a class="amt-button amt-button--secondary" href="https://am-tech.ru/teknologii/">Все кейсы <span>→</span></a>
      </div>
      <div class="amt-articles">{''.join(article_card(c) for c in cases)}</div>
    </div>
  </section>

  <section class="amt-section amt-section--soft" id="amh-why">
    <div class="amt-container">
      <div class="amt-section__head"><div class="amt-section__copy"><div class="amt-eyebrow">О компании</div><h2 class="amt-title amt-title--section">Интегратор с ответственностью<br>производителя</h2></div>
        <a class="amt-button amt-button--secondary" href="https://am-tech.ru/o-kompanii/">О компании <span>→</span></a></div>
      <div class="amh-why">
        <div class="amh-why__item"><b>01</b><h3>Тестовая деталь до покупки</h3><p>Проверяем технологию на вашей детали или её фрагменте и подтверждаем результат до поставки оборудования.</p></div>
        <div class="amh-why__item"><b>02</b><h3>Оборудование в реестре</h3><p>SLM-установки AMT включены в реестр российской промышленной продукции ГИСП — это важно для закупок по ПП 719.</p></div>
        <div class="amh-why__item"><b>03</b><h3>Своё ПО — PrintMate</h3><p>Открытая платформа подготовки и управления печатью: более 90 параметров процесса под ваш материал.</p></div>
        <div class="amh-why__item"><b>04</b><h3>Внедрение под ключ</h3><p>Аудит, подбор технологии, ТЭО, пусконаладка, обучение технологов и сервис — один проект и одна команда.</p></div>
        <div class="amh-why__item"><b>05</b><h3>Лизинг</h3><p>Оборудование AM.TECH можно приобрести в лизинг с партнёром ВЕЛКОР. <a href="https://am-tech.ru/leasing/">Условия →</a></p></div>
        <div class="amh-why__item"><b>06</b><h3>Демонстрация</h3><p>Покажем оборудование в работе и отсканируем или напечатаем вашу деталь.</p></div>
      </div>
      <div class="amh-partners"><span class="amh-partners__label">Нам доверяют</span>{''.join(f'<img src="{esc(u)}" alt="{esc(a)}">' for u, a in PARTNERS)}</div>
    </div>
  </section>

  <section class="amt-section" id="amh-blog">
    <div class="amt-container">
      <div class="amt-section__head">
        <div class="amt-section__copy"><div class="amt-eyebrow">Блог</div><h2 class="amt-title amt-title--section">Новости и инженерные материалы</h2></div>
        <a class="amt-button amt-button--secondary" href="https://am-tech.ru/blog/">Все материалы <span>→</span></a>
      </div>
      <div class="amt-articles">{''.join(article_card(n) for n in news)}</div>
      <div class="amh-videos-head"><h3>Видеообзоры</h3><a href="https://am-tech.ru/video-obzory/">Все видео →</a></div>
      <div class="amh-videos">{videos_html()}</div>
    </div>
  </section>

  <section class="amt-section amt-section--dark amt-cta" id="amh-contact">
    <div class="amt-container amt-cta__grid">
      <div>
        <div class="amt-eyebrow">Инженерная консультация</div>
        <h2 class="amt-title amt-title--section">Начнём с вашей<br>производственной <em>задачи</em></h2>
        <p class="amt-lead">Пришлите чертёж, 3D-модель или описание процесса — подберём оборудование или технологию, подготовим КП и предложим тестовую деталь.</p>
        <div class="amt-contacts"><a href="tel:+74951091191">+7 (495) 109‑11‑91</a><a href="mailto:sales@am-tech.ru">sales@am-tech.ru</a><span>Москва, Дмитровское ш., д. 9, стр. 3</span></div>
      </div>
      <div class="amt-cta__actions">
        <a class="amt-button amt-button--primary" href="#amt-form">Оставить заявку <span>→</span></a>
        <a class="amt-button amt-button--secondary" href="https://am-tech.ru/kontakty/">Контакты</a>
      </div>
    </div>
  </section>
</div>
<style id="amt-home-styles">
{(HERE / 'home.css').read_text()}
</style>
"""

(HERE / "home-prototype.html").write_text(body)
print("ok", len(body))
