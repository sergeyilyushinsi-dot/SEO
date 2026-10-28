/* Окно заявки с формой Битрикс24 «Сайт — калькулятор услуг» (inline/212). Открывается кнопками
   [data-amt-form] и калькулятором (window.amtOpenForm(сводка, параметры)). Параметры передаются в форму
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
  var box = modal.querySelector('.amc-modal__summary'), note = modal.querySelector('.amc-modal__note');
  function open(text, props) {
    box.textContent = text || '';
    box.style.display = text ? '' : 'none';
    note.style.display = text ? '' : 'none';
    if (text) {
      if (navigator.clipboard) navigator.clipboard.writeText(text).catch(function () {});
      apply(props || {});
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
