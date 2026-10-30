/* Калькулятор-заявка на страницах услуг: STL → 3D-просмотр, объём, габариты, подбор установки.
   Без внешних библиотек: 3D-просмотр на canvas 2D. Файл собирается в одну строку (build.py),
   поэтому только блочные комментарии и точки с запятой в конце операторов. Без символа «амперсанд»: WordPress
   превращает его в сущность HTML и внутри <script>, код ломается (проверено 28.10.2026). */
(function () {
  var root = document.getElementById('amt-calc');
  if (!root) return;
  var MACHINES = JSON.parse(root.getAttribute('data-machines') || '[]');
  var $ = function (s) { return root.querySelector(s); };
  var state = { volume: null, dims: null, area: null, name: '' };
  var view = $('.amc-view'), hint = $('.amc-view__hint');
  var canvas = null, ctx = null, model = null, rotX = -0.5, rotY = 0.6, zoom = 1, frame = 0;
  var MAX_TRI = 250000;

  function parseSTL(buf) {
    var dv = new DataView(buf);
    var isBinary = false;
    if (buf.byteLength >= 84) {
      var n = dv.getUint32(80, true);
      if (84 + n * 50 === buf.byteLength) isBinary = true;
    }
    var pos = [];
    if (isBinary) {
      var count = dv.getUint32(80, true);
      for (var i = 0; i < count; i++) {
        var o = 84 + i * 50 + 12;
        for (var k = 0; k < 9; k++) pos.push(dv.getFloat32(o + k * 4, true));
      }
    } else {
      var text = new TextDecoder().decode(new Uint8Array(buf));
      var re = /vertex\s+([-+\deE.]+)\s+([-+\deE.]+)\s+([-+\deE.]+)/g, m;
      while ((m = re.exec(text)) !== null) pos.push(+m[1], +m[2], +m[3]);
    }
    return new Float32Array(pos);
  }

  function measure(p) {
    var v = 0, a = 0, min = [Infinity, Infinity, Infinity], max = [-Infinity, -Infinity, -Infinity];
    for (var i = 0; i < p.length; i += 9) {
      var ax = p[i], ay = p[i + 1], az = p[i + 2], bx = p[i + 3], by = p[i + 4], bz = p[i + 5], cx = p[i + 6], cy = p[i + 7], cz = p[i + 8];
      v += (ax * (by * cz - bz * cy) - ay * (bx * cz - bz * cx) + az * (bx * cy - by * cx)) / 6;
      var ux = bx - ax, uy = by - ay, uz = bz - az, wx = cx - ax, wy = cy - ay, wz = cz - az;
      var nx = uy * wz - uz * wy, ny = uz * wx - ux * wz, nz = ux * wy - uy * wx;
      a += Math.sqrt(nx * nx + ny * ny + nz * nz) / 2;
    }
    for (var j = 0; j < p.length; j += 3) {
      for (var k = 0; k < 3; k++) { if (p[j + k] < min[k]) min[k] = p[j + k]; if (p[j + k] > max[k]) max[k] = p[j + k]; }
    }
    return { volume: Math.abs(v) / 1000, area: a / 100, dims: [max[0] - min[0], max[1] - min[1], max[2] - min[2]] };
  }

  /* Помещается ли габаритный параллелепипед детали в камеру при любой из шести ориентаций по осям. */
  function fits(d, m) {
    var o = [[0, 1, 2], [0, 2, 1], [1, 0, 2], [1, 2, 0], [2, 0, 1], [2, 1, 0]];
    for (var i = 0; i < o.length; i++) {
      var x = d[o[i][0]], y = d[o[i][1]], z = d[o[i][2]];
      var inPlane = m.d ? Math.sqrt(x * x + y * y) <= m.d : Math.max(x - m.x, y - m.y) <= 0;
      if (inPlane ? z <= m.z : false) return true;
    }
    return false;
  }

  function fmt(n, dg) { return n.toLocaleString('ru-RU', { maximumFractionDigits: dg, minimumFractionDigits: dg }); }

  function update() {
    var qty = Math.max(1, parseInt($('[name=amc-qty]').value, 10) || 1);
    var manual = parseFloat(($('[name=amc-volume]').value || '').replace(',', '.'));
    var vol = state.volume || (manual > 0 ? manual : null);
    var mat = $('[name=amc-material]');
    var tech = mat.options[mat.selectedIndex].getAttribute('data-tech');
    $('.amc-res__vol').textContent = vol ? fmt(vol, 1) + ' см³' + (qty > 1 ? ' × ' + qty + ' = ' + fmt(vol * qty, 1) + ' см³' : '') : '—';
    $('.amc-res__dims').textContent = state.dims ? state.dims.map(function (x) { return fmt(x, 1); }).join(' × ') + ' мм' : '—';
    $('.amc-res__area').textContent = state.area ? fmt(state.area, 1) + ' см²' : '—';
    $('.amc-res__tech').textContent = tech || '—';
    var fit = '—';
    if (state.dims) {
      var ok = MACHINES.filter(function (m) { return m.tech.indexOf(tech) > -1 ? fits(state.dims, m) : false; });
      fit = ok.length ? ok.map(function (m) { return m.name; }).join(', ') : 'по габаритам не помещается — напечатаем частями или подберём решение';
    }
    $('.amc-res__fit').textContent = fit;
    $('.amc-res__lead').textContent = vol ? 'Предварительные данные готовы — отправьте заявку, инженер рассчитает стоимость и срок' : 'Загрузите STL или укажите объём детали';
  }

  function summary() {
    var mat = $('[name=amc-material]');
    var extra = Array.prototype.map.call(root.querySelectorAll('input[type=checkbox]:checked'), function (c) { return c.value; });
    return ['Услуга: ' + root.getAttribute('data-service'),
      'Файл: ' + (state.name || 'не загружен'),
      'Объём: ' + $('.amc-res__vol').textContent,
      'Габариты: ' + $('.amc-res__dims').textContent,
      'Материал: ' + mat.value,
      'Количество: ' + ($('[name=amc-qty]').value || 1),
      'Установка: ' + $('.amc-res__fit').textContent,
      'Дополнительно: ' + (extra.length ? extra.join(', ') : 'нет')].join('\n');
  }

  /* Свой просмотр на canvas 2D: без three.js и WebGL, внешние CDN из России открываются не у всех.
     Плоская заливка с освещением по нормали, сортировка треугольников по глубине. */
  function show(p) {
    var n = p.length / 9, step = Math.max(1, Math.ceil(n / MAX_TRI)), m = Math.ceil(n / step);
    var c = [0, 0, 0], min = [Infinity, Infinity, Infinity], max = [-Infinity, -Infinity, -Infinity];
    for (var j = 0; j < p.length; j += 3) {
      for (var k = 0; k < 3; k++) { if (p[j + k] < min[k]) min[k] = p[j + k]; if (p[j + k] > max[k]) max[k] = p[j + k]; }
    }
    for (k = 0; k < 3; k++) c[k] = (min[k] + max[k]) / 2;
    var size = Math.max(max[0] - min[0], max[1] - min[1], max[2] - min[2]) || 1;
    var v = new Float32Array(m * 9), nrm = new Float32Array(m * 3), t = 0;
    for (var i = 0; i < n; i += step) {
      for (k = 0; k < 9; k++) v[t * 9 + k] = (p[i * 9 + k] - c[k % 3]) / size;
      var o = t * 9;
      var ux = v[o + 3] - v[o], uy = v[o + 4] - v[o + 1], uz = v[o + 5] - v[o + 2];
      var wx = v[o + 6] - v[o], wy = v[o + 7] - v[o + 1], wz = v[o + 8] - v[o + 2];
      var nx = uy * wz - uz * wy, ny = uz * wx - ux * wz, nz = ux * wy - uy * wx, l = Math.sqrt(nx * nx + ny * ny + nz * nz) || 1;
      nrm[t * 3] = nx / l; nrm[t * 3 + 1] = ny / l; nrm[t * 3 + 2] = nz / l; t++;
    }
    model = { v: v, n: nrm, count: t, depth: new Float32Array(t), order: new Uint32Array(t) };
    if (!canvas) {
      canvas = document.createElement('canvas'); ctx = canvas.getContext('2d');
      view.insertBefore(canvas, hint); bindControls(); window.addEventListener('resize', resize);
    }
    rotX = -0.5; rotY = 0.6; zoom = 1;
    hint.textContent = 'вращайте мышью или пальцем · колесо — масштаб';
    resize();
  }

  function resize() {
    if (!canvas) return;
    var r = Math.min(window.devicePixelRatio || 1, 2);
    canvas.width = Math.round(view.clientWidth * r); canvas.height = Math.round(view.clientHeight * r);
    draw();
  }

  function draw() {
    if (!model) return;
    var W = canvas.width, H = canvas.height, s = Math.min(W, H) * 0.62 * zoom;
    var cx = Math.cos(rotX), sx = Math.sin(rotX), cy = Math.cos(rotY), sy = Math.sin(rotY);
    var v = model.v, n = model.n, cnt = model.count, P = new Float32Array(cnt * 9), D = model.depth, O = model.order, L = new Float32Array(cnt);
    var vis = 0;
    for (var t = 0; t < cnt; t++) {
      var nx0 = n[t * 3], ny0 = n[t * 3 + 1], nz0 = n[t * 3 + 2];
      var nx1 = nx0 * cy + nz0 * sy, nz1 = -nx0 * sy + nz0 * cy;
      var ny2 = ny0 * cx - nz1 * sx, nz2 = ny0 * sx + nz1 * cx;
      var z = 0;
      for (var k = 0; k < 3; k++) {
        var o = t * 9 + k * 3, x0 = v[o], y0 = v[o + 1], z0 = v[o + 2];
        var x1 = x0 * cy + z0 * sy, z1 = -x0 * sy + z0 * cy;
        var y2 = y0 * cx - z1 * sx, z2 = y0 * sx + z1 * cx;
        P[o] = W / 2 + x1 * s; P[o + 1] = H / 2 - y2 * s; z += z2;
      }
      D[t] = z; L[t] = Math.abs(0.35 * nx1 + 0.45 * ny2 + 0.82 * nz2);
      O[vis++] = t;
    }
    var idx = Array.prototype.slice.call(O.subarray(0, vis));
    idx.sort(function (a, b) { return D[a] - D[b]; });
    ctx.clearRect(0, 0, W, H);
    for (var i = 0; i < idx.length; i++) {
      var q = idx[i], b = q * 9, g = Math.round(60 + 175 * L[q]);
      ctx.fillStyle = 'rgb(' + g + ',' + Math.round(g * 0.99) + ',' + Math.round(g * 0.97) + ')';
      ctx.strokeStyle = ctx.fillStyle;
      ctx.beginPath(); ctx.moveTo(P[b], P[b + 1]); ctx.lineTo(P[b + 3], P[b + 4]); ctx.lineTo(P[b + 6], P[b + 7]); ctx.closePath();
      ctx.fill(); ctx.stroke();
    }
  }

  function redraw() { if (!frame) frame = requestAnimationFrame(function () { frame = 0; draw(); }); }

  function bindControls() {
    var drag = null;
    canvas.addEventListener('pointerdown', function (e) { drag = [e.clientX, e.clientY]; canvas.setPointerCapture(e.pointerId); });
    canvas.addEventListener('pointermove', function (e) {
      if (!drag) return;
      rotY += (e.clientX - drag[0]) * 0.01; rotX += (e.clientY - drag[1]) * 0.01; drag = [e.clientX, e.clientY]; redraw();
    });
    canvas.addEventListener('pointerup', function () { drag = null; });
    canvas.addEventListener('wheel', function (e) { e.preventDefault(); zoom = Math.min(4, Math.max(0.4, zoom * (e.deltaY > 0 ? 0.9 : 1.1))); redraw(); }, { passive: false });
    canvas.style.touchAction = 'none';
  }

  function onFile(f) {
    if (!f) return;
    if (!/\.stl$/i.test(f.name)) { hint.textContent = 'Нужен файл STL. STEP и чертежи прикрепите к заявке — инженер посчитает по ним'; return; }
    if (f.size > 60 * 1024 * 1024) { hint.textContent = 'Файл больше 60 МБ — прикрепите его к заявке, посчитаем вручную'; return; }
    state.name = f.name; state.file = f;
    $('.amc-drop__name').textContent = f.name;
    var r = new FileReader();
    r.onload = function () {
      var p = parseSTL(r.result);
      if (!p.length) { hint.textContent = 'Не удалось прочитать STL'; return; }
      var m = measure(p);
      state.volume = m.volume; state.dims = m.dims; state.area = m.area;
      $('[name=amc-volume]').value = fmt(m.volume, 1);
      update(); show(p);
    };
    r.readAsArrayBuffer(f);
  }

  var input = $('[name=amc-file]'), drop = $('.amc-drop');
  input.addEventListener('change', function () { onFile(input.files[0]); });
  ['dragenter', 'dragover'].forEach(function (t) { drop.addEventListener(t, function (e) { e.preventDefault(); drop.classList.add('is-over'); }); });
  ['dragleave', 'drop'].forEach(function (t) { drop.addEventListener(t, function (e) { e.preventDefault(); drop.classList.remove('is-over'); }); });
  drop.addEventListener('drop', function (e) { onFile(e.dataTransfer.files[0]); });
  root.addEventListener('input', function (e) { if (e.target.name === 'amc-volume') { state.volume = null; } update(); });
  root.addEventListener('change', update);

  /* «Далее»: сводка и параметры уходят в окно заявки (modal.js), сводка копируется в буфер. Файл STL окно
     прикрепляет в форму само (DataTransfer), если браузер это позволяет. */
  $('.amc-next').addEventListener('click', function () {
    var mat = $('[name=amc-material]');
    var extra = Array.prototype.map.call(root.querySelectorAll('input[type=checkbox]:checked'), function (c) { return c.value; });
    var props = { service: root.getAttribute('data-service'), file: state.name || '', volume: $('.amc-res__vol').textContent,
      dims: $('.amc-res__dims').textContent, material: mat.value, qty: String($('[name=amc-qty]').value || 1),
      machine: $('.amc-res__fit').textContent, extra: extra.join(', ') };
    if (window.amtOpenForm) window.amtOpenForm(summary(), props, state.file || null);
  });
  update();
})();
