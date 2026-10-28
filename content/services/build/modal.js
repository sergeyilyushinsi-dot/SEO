/* Окно заявки с формой Битрикс24 «Сайт — калькулятор услуг» (inline/212). Открывается кнопками
   [data-amt-form] и калькулятором (window.amtOpenForm(сводка, параметры, файл)). Параметры передаются в форму
   через form.setProperty — их видно в карточке лида, если в форме включены свойства с такими кодами.
   Без символа «амперсанд»: WordPress превращает его в сущность HTML внутри script. */
(function () {
  var modal = document.getElementById('amt-form-modal');
  if (!modal) return;
  var b24 = null, pending = null;
  function apply(props) {
    if (!b24) { pending = props; return; }
    Object.keys(props).forEach(function (k) { try { b24.setProperty(k, props[k]); } catch (e) {} });
  }
  window.addEventListener('b24:form:init', function (event) {
    var f = event.detail ? event.detail.object : null;
    if (!f) return;
    b24 = f;
    if (pending) { apply(pending); pending = null; }
  });
  /* Заполнение полей самой формы Битрикс24: она рисуется прямо в странице (не во фрейме), поэтому поле
     «Параметры детали» и поле файла доступны. Значение ставится через нативный сеттер и событие input, иначе
     форма его не увидит. Файл передаётся через DataTransfer. Форма грузится не сразу — повторяем попытки. */
  function setValue(el, text) {
    var proto = el.tagName === 'TEXTAREA' ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype;
    Object.getOwnPropertyDescriptor(proto, 'value').set.call(el, text);
    el.dispatchEvent(new Event('input', { bubbles: true })); el.dispatchEvent(new Event('change', { bubbles: true }));
  }
  function findField(form, words) {
    var fields = form.querySelectorAll('.b24-form-field');
    for (var i = 0; i < fields.length; i++) {
      var label = (fields[i].textContent || '').toLowerCase();
      for (var w = 0; w < words.length; w++) {
        if (label.indexOf(words[w]) > -1) { var el = fields[i].querySelector('textarea, input[type=text]'); if (el) return el; }
      }
    }
    return null;
  }
  function fill(text, file, tries) {
    var form = modal.querySelector('.b24-form');
    var field = form ? findField(form, ['параметры детали', 'параметры', 'комментар']) : null;
    if (!field) { if (tries < 30) setTimeout(function () { fill(text, file, tries + 1); }, 500); return; }
    if (!field.value) setValue(field, text);
    var inp = form.querySelector('input[type=file]');
    if (file ? inp : false) {
      try { var dt = new DataTransfer(); dt.items.add(file); inp.files = dt.files; inp.dispatchEvent(new Event('change', { bubbles: true })); }
      catch (e) {}
    }
  }
  var box = modal.querySelector('.amc-modal__summary'), note = modal.querySelector('.amc-modal__note');
  function open(text, props, file) {
    box.textContent = text || '';
    box.style.display = text ? '' : 'none';
    note.style.display = text ? '' : 'none';
    if (text) {
      if (navigator.clipboard) navigator.clipboard.writeText(text).catch(function () {});
      apply(props || {});
      fill(text, file || null, 0);
    }
    modal.classList.add('is-open'); document.documentElement.style.overflow = 'hidden';
  }
  window.amtOpenForm = open;
  document.addEventListener('click', function (e) {
    var t = e.target.closest ? e.target.closest('[data-amt-form]') : null;
    if (t) { e.preventDefault(); open('', {}); return; }
    if (e.target === modal || e.target.classList.contains('amc-modal__close')) {
      modal.classList.remove('is-open'); document.documentElement.style.overflow = '';
    }
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { modal.classList.remove('is-open'); document.documentElement.style.overflow = ''; }
  });
})();
