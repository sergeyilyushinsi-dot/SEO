/* Калькулятор-заявка на страницах услуг: STL → 3D-просмотр, объём, габариты, подбор установки.
   Без внешних зависимостей, кроме three.js (cdnjs). Файл собирается в одну строку (build.py),
   поэтому только блочные комментарии и точки с запятой в конце операторов. Без символа «амперсанд»: WordPress
   превращает его в сущность HTML и внутри <script>, код ломается (проверено 28.10.2026). */
(function () {
  var root = document.getElementById('amt-calc');
  if (!root) return;
  var MACHINES = JSON.parse(root.getAttribute('data-machines') || '[]');
  var $ = function (s) { return root.querySelector(s); };
  var state = { volume: null, dims: null, area: null, name: '' };
  var view = $('.amc-view'), hint = $('.amc-view__hint');
  var THREE_URL = 'https://cdnjs.cloudflare.com/ajax/libs/three.js/r128/three.min.js';
  var renderer, scene, camera, mesh, rotX = -0.6, rotY = 0.7, dist = 3.8;

  function loadThree(cb) {
    if (window.THREE) { cb(); return; }
    var s = document.createElement('script');
    s.src = THREE_URL; s.onload = cb;
    s.onerror = function () { hint.textContent = 'Не удалось загрузить 3D-просмотр, объём и габариты считаются без него'; };
    document.head.appendChild(s);
  }

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

  function show(p) {
    loadThree(function () {
      var T = window.THREE;
      if (!renderer) {
        renderer = new T.WebGLRenderer({ antialias: true, alpha: true });
        renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
        view.insertBefore(renderer.domElement, hint);
        scene = new T.Scene();
        camera = new T.PerspectiveCamera(40, 1, 0.01, 100);
        scene.add(new T.AmbientLight(0xffffff, 0.55));
        var l = new T.DirectionalLight(0xffffff, 0.8); l.position.set(2, 3, 4); scene.add(l);
        var l2 = new T.DirectionalLight(0xffb27a, 0.35); l2.position.set(-3, -1, -2); scene.add(l2);
        bindControls();
        window.addEventListener('resize', resize);
      }
      if (mesh) { scene.remove(mesh); mesh.geometry.dispose(); }
      var g = new T.BufferGeometry();
      g.setAttribute('position', new T.BufferAttribute(p, 3));
      g.computeVertexNormals(); g.computeBoundingBox(); g.center();
      var s = g.boundingBox.getSize(new T.Vector3()); var k = 1.6 / Math.max(s.x, s.y, s.z);
      g.scale(k, k, k);
      mesh = new T.Mesh(g, new T.MeshStandardMaterial({ color: 0xb9bcc0, metalness: 0.35, roughness: 0.45 }));
      scene.add(mesh); rotX = -0.6; rotY = 0.7; dist = 3.8;
      hint.textContent = 'вращайте мышью или пальцем · колесо — масштаб';
      resize(); draw();
    });
  }

  function resize() {
    if (!renderer) return;
    var w = view.clientWidth, h = view.clientHeight;
    renderer.setSize(w, h); camera.aspect = w / h; camera.updateProjectionMatrix(); draw();
  }

  function draw() {
    if (!mesh) return;
    mesh.rotation.set(rotX, rotY, 0);
    camera.position.set(0, 0, dist); camera.lookAt(0, 0, 0);
    renderer.render(scene, camera);
  }

  function bindControls() {
    var c = renderer.domElement, drag = null;
    c.addEventListener('pointerdown', function (e) { drag = [e.clientX, e.clientY]; c.setPointerCapture(e.pointerId); });
    c.addEventListener('pointermove', function (e) {
      if (!drag) return;
      rotY += (e.clientX - drag[0]) * 0.01; rotX += (e.clientY - drag[1]) * 0.01; drag = [e.clientX, e.clientY]; draw();
    });
    c.addEventListener('pointerup', function () { drag = null; });
    c.addEventListener('wheel', function (e) { e.preventDefault(); dist = Math.min(8, Math.max(1.4, dist * (e.deltaY > 0 ? 1.1 : 0.9))); draw(); }, { passive: false });
    c.style.touchAction = 'none';
  }

  function onFile(f) {
    if (!f) return;
    if (!/\.stl$/i.test(f.name)) { hint.textContent = 'Нужен файл STL. STEP и чертежи прикрепите к заявке — инженер посчитает по ним'; return; }
    if (f.size > 60 * 1024 * 1024) { hint.textContent = 'Файл больше 60 МБ — прикрепите его к заявке, посчитаем вручную'; return; }
    state.name = f.name;
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

  /* «Далее»: сводка и параметры уходят в окно заявки (modal.js), сводка копируется в буфер. Файл STL посетитель
     прикрепляет в форме Битрикс24: браузер не даёт передать выбранный файл в чужую форму скриптом. */
  $('.amc-next').addEventListener('click', function () {
    var mat = $('[name=amc-material]');
    var extra = Array.prototype.map.call(root.querySelectorAll('input[type=checkbox]:checked'), function (c) { return c.value; });
    var props = { service: root.getAttribute('data-service'), file: state.name || '', volume: $('.amc-res__vol').textContent,
      dims: $('.amc-res__dims').textContent, material: mat.value, qty: String($('[name=amc-qty]').value || 1),
      machine: $('.amc-res__fit').textContent, extra: extra.join(', ') };
    if (window.amtOpenForm) window.amtOpenForm(summary(), props);
  });
  update();
})();
