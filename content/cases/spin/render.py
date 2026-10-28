"""HTML кейса для am-tech.ru (тип записи cases) из описания в batchN.py. Картинки вставляются отдельно (images)."""
import html as H


def render(c, images=()):
    rows = "".join(f"<tr><td>{k}</td><td>{v}</td></tr>" for k, v in c["facts"])
    table = ('<div style="overflow:auto;width:0;min-width:100%"><table class="table table-bordered"><tbody>'
             f"{rows}</tbody></table></div>")
    imgs = list(images)
    body = []
    for i, (h, t) in enumerate(c["sections"]):
        body.append(f"<h2>{h}</h2>{t}")
        if i < len(imgs):
            u, w, hh, alt = imgs[i]
            body.append(f'<p><img src="{u}" alt="{H.escape(alt)}" width="{w}" height="{hh}" loading="lazy" style="max-width:100%;height:auto"></p>')
    rest = "".join(f'<p><img src="{u}" alt="{H.escape(alt)}" width="{w}" height="{hh}" loading="lazy" style="max-width:100%;height:auto"></p>'
                   for u, w, hh, alt in imgs[len(c["sections"]):])
    cta = ('<p><strong>Нужна похожая работа?</strong> Пришлите фото, чертёж или модель детали — инженер AM.TECH оценит задачу и срок: '
           '<a href="tel:+74951091191">+7 (495) 109-11-91</a>, <a href="mailto:sales@am-tech.ru">sales@am-tech.ru</a>. '
           'Все услуги — в разделе <a href="/uslugi/">услуги</a>.</p>')
    inner = f'<p><strong>Коротко:</strong> {c["lead"]}</p>{table}{"".join(body)}{rest}{cta}'
    return ('<div class="boldgrid-section"><div class="container"><div class="row">'
            f'<div class="col-lg-12 col-md-12 col-xs-12 col-sm-12">{inner}</div></div></div></div>')
