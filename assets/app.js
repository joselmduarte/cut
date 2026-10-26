// Modais
document.addEventListener('click', function (e) {
  var open = e.target.closest('[data-open-modal]');
  if (open) {
    var m = document.getElementById(open.getAttribute('data-open-modal'));
    if (m) m.classList.remove('hidden');
    return;
  }
  var close = e.target.closest('[data-close-modal]');
  if (close) {
    var c = document.getElementById(close.getAttribute('data-close-modal'));
    if (c) c.classList.add('hidden');
    return;
  }
  if (e.target.classList && e.target.classList.contains('modal')) e.target.classList.add('hidden');
});
document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape') document.querySelectorAll('.modal').forEach(function (m) { m.classList.add('hidden'); });
});

// YouTube IFrame API (carrega uma só vez)
function loadYT(cb) {
  if (window.YT && YT.Player) { cb(); return; }
  var prev = window.onYouTubeIframeAPIReady;
  window.onYouTubeIframeAPIReady = function () { if (prev) prev(); cb(); };
  if (!document.getElementById('yt-api')) {
    var t = document.createElement('script');
    t.id = 'yt-api'; t.src = 'https://www.youtube.com/iframe_api';
    document.head.appendChild(t);
  }
}
function ic(name, cls) {
  return '<svg class="ic' + (cls ? ' ' + cls : '') + '" aria-hidden="true" focusable="false"><use href="#i-' + name + '"/></svg>';
}
function toast(msg) {
  var el = document.getElementById('toast');
  if (!el) { el = document.createElement('div'); el.id = 'toast'; el.className = 'toast'; document.body.appendChild(el); }
  var ok = /^\u2714 /.test(msg);
  var host = document.fullscreenElement || document.webkitFullscreenElement || document.body;
  if (el.parentNode !== host) host.appendChild(el);
  el.innerHTML = (ok ? ic('check') + ' ' : '') + escHtml(ok ? msg.slice(2) : msg); el.classList.add('show');
  clearTimeout(el._t); el._t = setTimeout(function () { el.classList.remove('show'); }, 2500);
}

// Página do jogo: marcar rápido, marcar para formulário, editar
(function () {
  var frame = document.getElementById('gamePlayer');
  if (!frame) return;
  var player = null, marks = 0;
  loadYT(function () { player = new YT.Player('gamePlayer'); });
  function now() { return (player && player.getCurrentTime) ? Math.floor(player.getCurrentTime()) : null; }
  function val(id, d) { var e = document.getElementById(id); return e ? parseInt(e.value, 10) : d; }

  function quick() {
    if (document.body.classList.contains('role-viewer')) return;
    var t = now();
    if (t === null) { toast('O player ainda está a carregar…'); return; }
    var fd = new FormData();
    fd.append('csrf', document.querySelector('input[name="csrf"]').value);
    fd.append('quick_moment', '1'); fd.append('time', t);
    fd.append('before', val('qBefore', 5)); fd.append('after', val('qAfter', 5));
    fetch(location.pathname + location.search, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        marks++;
        toast('\u2714 Marcado ' + j.time);
        var s = document.getElementById('quickStatus');
        if (s) s.textContent = marks + ' marcado(s) nesta sessão. Completa-os depois em "Editar".';
      })
      .catch(function () { toast('Erro ao guardar. Sessão expirada?'); });
  }
  document.querySelectorAll('[data-quick-mark]').forEach(function (b) { b.addEventListener('click', quick); });
  document.addEventListener('keydown', function (e) {
    var tag = (e.target.tagName || '').toLowerCase();
    if (e.key.toLowerCase() === 'm' && !e.ctrlKey && !e.metaKey && tag !== 'input' && tag !== 'textarea' && tag !== 'select') quick();
  });

  var btn = document.querySelector('[data-mark-now]');
  if (btn) btn.addEventListener('click', function () {
    var t = now(); if (t === null) { toast('O player ainda está a carregar…'); return; }
    var form = document.querySelector('form input[name="new_moment"]').form;
    form.elements['start'].value = Math.max(0, t - val('qBefore', 5));
    form.elements['end'].value = t + val('qAfter', 5);
    form.elements['title'].focus();
  });

  document.querySelectorAll('[data-edit-moment]').forEach(function (b) {
    b.addEventListener('click', function () {
      var d = JSON.parse(b.getAttribute('data-edit-moment')), f = document.getElementById('editMomentForm');
      f.elements['moment_id'].value = d.id;
      ['title', 'athlete', 'category', 'importance', 'start', 'end', 'tags', 'notes'].forEach(function (k) { f.elements[k].value = d[k]; });
      document.getElementById('editMomentModal').classList.remove('hidden');
    });
  });
})();

// ---------- Quadro tático (campo de futsal 5 vs 5, estilo coaching board) ----------
// Campo 400 x 220 (unidades); banco por baixo do campo (y = 242).
var BOARD_DEFAULT_COLORS = { a: '#131316', b: '#d42a42', ga: '#e0a100', gb: '#e0650a' };
var _boardSeq = 0;
function shadeHex(hex, amt) {
  var n = parseInt(String(hex).slice(1), 16), r = n >> 16, g = (n >> 8) & 255, b = n & 255;
  function f(c) { c = Math.round(amt < 0 ? c * (1 + amt) : c + (255 - c) * amt); return ('0' + Math.max(0, Math.min(255, c)).toString(16)).slice(-2); }
  return '#' + f(r) + f(g) + f(b);
}
function defaultBoard() {
  var pl = [{ t: 'a', l: '', g: 1, x: 12, y: 110 }, { t: 'b', l: '', g: 1, x: 388, y: 110 }];
  [22, 66, 154, 198].forEach(function (y) {
    pl.push({ t: 'a', l: '', x: 96, y: y });
    pl.push({ t: 'b', l: '', x: 304, y: y });
  });
  [10, 32, 54].forEach(function (x) {
    pl.push({ t: 'a', l: '', x: x, y: 242 });
    pl.push({ t: 'b', l: '', x: 400 - x, y: 242 });
  });
  return { players: pl, ball: { x: 200, y: 110 } };
}
// Devolve { get, snapshot, setState, svg }. A cor das equipas muda-se tocando nas bolinhas «Casa» / «Fora» do campo.
function renderBoard(host, board, opts) {
  opts = opts || {};
  var NS = 'http://www.w3.org/2000/svg', uid = 'b' + (++_boardSeq);
  board = normalizeBoard((board && board.players) ? JSON.parse(JSON.stringify(board)) : defaultBoard());
  var colors = Object.assign({}, BOARD_DEFAULT_COLORS, board.colors || {}, opts.colors || {});
  board.colors = colors;
  var canColor = !opts.readonly;
  var bench = [10, 32, 54, 346, 368, 390].map(function (x) { return '<circle cx="' + x + '" cy="242" r="11"/>'; }).join('');
  function grad(id, c1, c2) {
    return '<radialGradient id="' + uid + id + '" cx=".35" cy=".3" r=".85"><stop offset="0" stop-color="' + c1 + '"/><stop offset="1" stop-color="' + c2 + '"/></radialGradient>';
  }
  host.innerHTML =
    '<svg class="board-svg" viewBox="-22 -12 444 276" xmlns="' + NS + '" role="img" aria-label="Quadro tático">' +
    '<defs>' + grad('a', shadeHex(colors.a, .3), colors.a) + grad('b', shadeHex(colors.b, .3), colors.b) +
    grad('ga', shadeHex(colors.ga, .3), colors.ga) + grad('gb', shadeHex(colors.gb, .3), colors.gb) +
    '<filter id="' + uid + 's" x="-40%" y="-40%" width="180%" height="190%"><feDropShadow dx="0" dy="1.4" stdDeviation="1.4" flood-color="#0a1a4a" flood-opacity=".45"/></filter>' +
    '</defs>' +
    '<rect x="-22" y="-12" width="444" height="276" rx="16" fill="#3375e9"/>' +
    '<g class="line">' +
    '<rect x="0" y="0" width="400" height="220"/><line x1="200" y1="0" x2="200" y2="220"/><circle cx="200" cy="110" r="29"/>' +
    '<path d="M0,5 A5,5 0 0 0 5,0 M395,0 A5,5 0 0 0 400,5 M0,215 A5,5 0 0 1 5,220 M395,220 A5,5 0 0 1 400,215"/>' +
    '<path d="M0,35.5 A58,58 0 0 1 58,93.5 L58,126.5 A58,58 0 0 1 0,184.5"/>' +
    '<path d="M400,35.5 A58,58 0 0 0 342,93.5 L342,126.5 A58,58 0 0 0 400,184.5"/>' +
    '<rect x="-12" y="93.5" width="12" height="33" rx="2"/><rect x="400" y="93.5" width="12" height="33" rx="2"/>' +
    '<path d="M-4,47 H4 M-4,173 H4 M396,47 H404 M396,173 H404 M104,216 V226 M152,216 V226 M248,216 V226 M296,216 V226"/>' +
    '</g>' +
    '<g class="spot"><circle cx="95" cy="110" r="1.7"/><circle cx="305" cy="110" r="1.7"/></g>' +
    '<g class="bench">' + bench + '</g>' +
    '<g class="legend' + (canColor ? ' can-pick' : '') + '"><rect class="pill" x="104" y="229" width="192" height="26" rx="13"/>' +
    '<circle class="dot dot-a" cx="120" cy="242" r="6.6" fill="' + colors.a + '"/><text x="131" y="242">Casa</text>' +
    '<text class="gr" x="167" y="242">GR</text><circle class="dot dot-ga" cx="184" cy="242" r="5" fill="' + colors.ga + '"/>' +
    '<circle class="dot dot-gb" cx="216" cy="242" r="5" fill="' + colors.gb + '"/><text class="gr" x="225" y="242">GR</text>' +
    '<text x="269" y="242" text-anchor="end">Fora</text><circle class="dot dot-b" cx="280" cy="242" r="6.6" fill="' + colors.b + '"/>' +
    (canColor ? '<rect class="lg-hit" data-team="a" x="104" y="229" width="60" height="26" rx="13"><title>Cor da equipa «Casa»</title></rect>' +
      '<rect class="lg-hit" data-team="ga" x="164" y="229" width="36" height="26"><title>Cor do guarda-redes da «Casa»</title></rect>' +
      '<rect class="lg-hit" data-team="gb" x="200" y="229" width="36" height="26"><title>Cor do guarda-redes da «Fora»</title></rect>' +
      '<rect class="lg-hit" data-team="b" x="236" y="229" width="60" height="26" rx="13"><title>Cor da equipa «Fora»</title></rect>' : '') +
    '</g></svg>';
  var svg = host.firstChild, tokens = [], ballTok = null, picker = null;
  function mk(tag, attrs) {
    var e = document.createElementNS(NS, tag);
    for (var k in attrs) e.setAttribute(k, attrs[k]);
    return e;
  }
  function refreshColors() {
    ['a', 'b', 'ga', 'gb'].forEach(function (t) {
      var stops = svg.querySelectorAll('#' + uid + t + ' stop');
      stops[0].setAttribute('stop-color', shadeHex(colors[t], .3)); stops[1].setAttribute('stop-color', colors[t]);
      svg.querySelector('.dot-' + t).setAttribute('fill', colors[t]);
    });
    tokens.forEach(function (tk) { tk.paint(); });
  }
  function pickColor(team) {
    if (!picker) {
      picker = document.createElement('input'); picker.type = 'color'; picker.className = 'board-color-input'; picker.tabIndex = -1;
      host.appendChild(picker);
    }
    picker.value = colors[team];
    picker.oninput = function () {
      colors[team] = picker.value; board.colors = colors; refreshColors();
      if (opts.onChange) opts.onChange(board);
    };
    picker.click();
  }
  if (canColor) svg.querySelectorAll('.lg-hit').forEach(function (r) {
    r.addEventListener('click', function () { pickColor(r.getAttribute('data-team')); });
  });
  function token(o, cls) {
    var g = mk('g', { 'class': 'tok ' + cls }), self = {};
    self.move = function () { g.setAttribute('transform', 'translate(' + o.x + ',' + o.y + ')'); };
    self.paint = function () {
      while (g.firstChild) g.removeChild(g.firstChild);
      g.appendChild(mk('circle', { r: 13, fill: 'transparent' })); // área de toque maior
      if (cls === 'ball') {
        g.appendChild(mk('circle', { r: 5.2, fill: '#fff', stroke: '#1b1b1f', 'stroke-width': .7 }));
        g.appendChild(mk('polygon', { points: '0,-2.5 2.4,-0.8 1.5,2.1 -1.5,2.1 -2.4,-0.8', fill: '#1b1b1f' }));
      } else {
        var fill = 'url(#' + uid + (o.g ? 'g' : '') + (cls === 'b' ? 'b' : 'a') + ')';
        g.appendChild(mk('circle', { r: 8.6, fill: fill, filter: 'url(#' + uid + 's)' }));
        var t = mk('text', {});
        if (o.l) { t.textContent = o.l; g.appendChild(t); }
        else if (o.g) { t.setAttribute('class', 'glove'); t.textContent = '\uD83E\uDDE4'; g.appendChild(t); }
        else g.appendChild(mk('circle', { r: 1.7, fill: shadeHex(colors[cls === 'b' ? 'b' : 'a'], -.55) }));
      }
      self.move();
    };
    self.paint();
    if (!opts.readonly) {
      var on = false;
      g.addEventListener('pointerdown', function (e) { e.preventDefault(); on = true; g.setPointerCapture(e.pointerId); });
      g.addEventListener('pointermove', function (e) {
        if (!on) return;
        var pt = svg.createSVGPoint(); pt.x = e.clientX; pt.y = e.clientY;
        var q = pt.matrixTransform(svg.getScreenCTM().inverse());
        o.x = Math.round(Math.max(-14, Math.min(414, q.x)));
        o.y = Math.round(Math.max(-4, Math.min(258, q.y)));
        self.move();
      });
      g.addEventListener('pointerup', function () { if (on) { on = false; if (opts.onChange) opts.onChange(board); } });
      g.addEventListener('pointercancel', function () { on = false; });
      if (opts.editLabels && cls !== 'ball') g.addEventListener('dblclick', function () {
        var v = prompt('Número ou nome (até 3 caracteres; vazio para limpar):', o.l || ''); if (v === null) return;
        o.l = v.slice(0, 3); self.paint(); if (opts.onChange) opts.onChange(board);
      });
    }
    svg.appendChild(g);
    return self;
  }
  board.players.forEach(function (o) { tokens.push(token(o, o.t === 'b' ? 'b' : 'a')); });
  ballTok = token(board.ball, 'ball');
  return {
    svg: svg,
    get: function () { return board; },
    snapshot: function () {
      return { players: board.players.map(function (p) { return { t: p.t, l: p.l, g: p.g ? 1 : 0, x: p.x, y: p.y }; }), ball: { x: board.ball.x, y: board.ball.y } };
    },
    setState: function (s) {
      s.players.forEach(function (sp, i) { var o = board.players[i]; if (o) { o.x = sp.x; o.y = sp.y; tokens[i].move(); } });
      if (s.ball) { board.ball.x = s.ball.x; board.ball.y = s.ball.y; ballTok.move(); }
    }
  };
}
// Reproduz os fotogramas com movimento suave. Devolve uma função para cancelar.
function playFrames(api, frames, o) {
  o = o || {};
  var dur = o.duration || 900, i = 0, t0 = null, raf = null, stop = false;
  if (!frames || frames.length < 2) { if (o.onDone) o.onDone(false); return function () {}; }
  api.setState(frames[0]);
  function ease(p) { return p < .5 ? 2 * p * p : 1 - Math.pow(-2 * p + 2, 2) / 2; }
  function lerp(a, b, t) { return a + (b - a) * t; }
  function step(ts) {
    if (stop) return;
    if (t0 === null) t0 = ts;
    var p = Math.min(1, (ts - t0) / dur), e = ease(p), a = frames[i], b = frames[i + 1];
    api.setState({
      players: a.players.map(function (fp, k) { var tp = b.players[k] || fp; return { x: lerp(fp.x, tp.x, e), y: lerp(fp.y, tp.y, e) }; }),
      ball: (a.ball && b.ball) ? { x: lerp(a.ball.x, b.ball.x, e), y: lerp(a.ball.y, b.ball.y, e) } : null
    });
    if (p < 1) { raf = requestAnimationFrame(step); return; }
    i++; t0 = null;
    if (i < frames.length - 1) raf = requestAnimationFrame(step);
    else if (o.onDone) o.onDone(true);
  }
  raf = requestAnimationFrame(step);
  return function () { stop = true; if (raf) cancelAnimationFrame(raf); };
}

// ---------- Utilitários partilhados: normalizar prancheta, capas, PNG ----------
function normalizeBoard(b) {
  // 1) guarda-redes: pranchetas antigas não tinham a marca; o jogador mais recuado de cada equipa passa a GR
  ['a', 'b'].forEach(function (t) {
    var team = b.players.filter(function (p) { return (p.t === 'b' ? 'b' : 'a') === t; });
    if (!team.length || team.some(function (p) { return p.g; })) return;
    var gk = team.reduce(function (m, p) { return (t === 'a' ? p.x < m.x : p.x > m.x) ? p : m; }, team[0]);
    if ((t === 'a' && gk.x <= 40) || (t === 'b' && gk.x >= 360)) gk.g = 1;
  });
  // 2) banco: três lugares por equipa ocupados com jogadores extra
  var slots = { a: [10, 32, 54], b: [390, 368, 346] };
  ['a', 'b'].forEach(function (t) {
    var team = b.players.filter(function (p) { return (p.t === 'b' ? 'b' : 'a') === t; });
    slots[t].forEach(function (sx) {
      if (team.length >= 8) return;
      var taken = team.some(function (p) { return Math.abs(p.x - sx) < 11 && Math.abs(p.y - 242) < 11; });
      if (!taken) { var np = { t: t, l: '', x: sx, y: 242 }; b.players.push(np); team.push(np); }
    });
  });
  return b;
}
function escHtml(t) { return String(t == null ? '' : t).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
function coverHtml(c, m) {
  c = c || {}; m = m || {};
  var hex = function (v, d) { return /^#[0-9a-fA-F]{6}$/.test(v || '') ? v : d; };
  var em = [c.emblem, c.emblem2].filter(Boolean).map(function (s) { return '<img class="cover-emblem" src="' + escHtml(s) + '" alt="">'; }).join('');
  return '<div class="cover-inner" style="background:' + hex(c.bg, '#0b1026') + ';color:' + hex(c.fg, '#ffffff') + '">' +
    (em ? '<div class="cover-emblems">' + em + '</div>' : '') +
    (m.category ? '<p class="cover-eyebrow">' + escHtml(m.category) + '</p>' : '') +
    '<h1 class="cover-title">' + escHtml(m.title || '') + '</h1>' +
    (m.notes ? '<p class="cover-sub">' + escHtml(m.notes).replace(/\n/g, '<br>') + '</p>' : '') + '</div>';
}
var BOARD_CSS = '.line{fill:none;stroke:#fff;stroke-width:1.1;stroke-linecap:round}.spot circle{fill:#fff}.bench circle{fill:rgba(255,255,255,.07);stroke:rgba(255,255,255,.55);stroke-width:.9}.legend rect{fill:rgba(18,48,128,.55)}.legend text{fill:#fff;font:700 9px sans-serif;dominant-baseline:central}.legend .gr{font-size:7px}.tok text{fill:#fff;font:700 8px sans-serif;text-anchor:middle;dominant-baseline:central}.tok text.glove{font-size:9px}';
function boardToPng(svg, name) {
  var c = svg.cloneNode(true);
  c.setAttribute('width', 1776); c.setAttribute('height', 1104);
  var st = document.createElementNS('http://www.w3.org/2000/svg', 'style'); st.textContent = BOARD_CSS;
  c.insertBefore(st, c.firstChild);
  var img = new Image();
  img.onload = function () {
    var cv = document.createElement('canvas'); cv.width = 1776; cv.height = 1104;
    cv.getContext('2d').drawImage(img, 0, 0);
    cv.toBlob(function (b) {
      var a = document.createElement('a'); a.href = URL.createObjectURL(b); a.download = name; a.click();
      setTimeout(function () { URL.revokeObjectURL(a.href); }, 2000);
    }, 'image/png');
  };
  img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(new XMLSerializer().serializeToString(c));
}

// ---------- Painéis (página do jogo): texto, índice, quadro (com animação), capa ----------
(function () {
  var f = document.getElementById('panelForm');
  if (!f) return;
  var host = document.getElementById('boardEditor'), cur = null, coverOld = null, frames = [], cancelAnim = null;
  function info() {
    var el = document.getElementById('pbInfo');
    if (el) el.textContent = 'Fotogramas: ' + frames.length + (frames.length === 1 ? ' (precisa de pelo menos 2 para animar)' : '');
  }
  function save() {
    if (!cur) return;
    var b = cur.get();
    if (frames.length) b.frames = frames; else delete b.frames;
    f.elements['board_json'].value = JSON.stringify(b);
  }
  function drawPreview() {
    var pv = document.getElementById('coverPreview'); if (!pv) return;
    var c = {
      bg: f.elements['cover_bg'].value, fg: f.elements['cover_fg'].value,
      emblem: (coverOld && !f.elements['remove_emblem'].checked) ? coverOld.emblem : '',
      emblem2: (coverOld && !f.elements['remove_emblem2'].checked) ? coverOld.emblem2 : ''
    };
    [['cover_emblem', 'emblem'], ['cover_emblem2', 'emblem2']].forEach(function (p) {
      var inp = f.elements[p[0]];
      if (inp.files && inp.files[0]) c[p[1]] = URL.createObjectURL(inp.files[0]);
    });
    pv.innerHTML = coverHtml(c, { category: f.elements['category'].value, title: f.elements['title'].value, notes: f.elements['notes'].value });
  }
  function startBoard() {
    var existing = f.elements['board_json'].value ? JSON.parse(f.elements['board_json'].value) : null;
    frames = (existing && existing.frames) ? existing.frames.slice() : [];
    cur = renderBoard(host, existing, { editLabels: true, onChange: save });
    save(); info();
  }
  function sync() {
    var t = f.elements['ptype'].value;
    document.getElementById('boardField').hidden = t !== 'board';
    document.getElementById('coverField').hidden = t !== 'cover';
    document.getElementById('notesField').hidden = t === 'index';
    document.getElementById('tagsField').hidden = t === 'index';
    if (t === 'board' && !cur) startBoard();
    if (t === 'cover') drawPreview();
  }
  function open(d) {
    if (cancelAnim) { cancelAnim(); cancelAnim = null; }
    f.elements['moment_id'].value = d.id || '';
    f.elements['title'].value = d.title || '';
    f.elements['category'].value = d.category || '';
    f.elements['notes'].value = d.notes || '';
    f.elements['tags'].value = (d.tags || []).join(', ');
    f.elements['after'].value = '';
    f.elements['ptype'].value = d.type || 'text';
    f.elements['ptype'].disabled = !!d.id;
    f.elements['board_json'].value = d.board ? JSON.stringify(d.board) : '';
    coverOld = d.cover || null;
    f.elements['cover_bg'].value = (d.cover && d.cover.bg) || '#0b1026';
    f.elements['cover_fg'].value = (d.cover && d.cover.fg) || '#ffffff';
    f.elements['cover_emblem'].value = ''; f.elements['cover_emblem2'].value = '';
    f.elements['remove_emblem'].checked = false; f.elements['remove_emblem2'].checked = false;
    cur = null; frames = []; host.innerHTML = ''; info();
    sync();
    document.getElementById('panelModal').classList.remove('hidden');
    f.elements['title'].focus();
  }
  function bind(id, fn) { var el = document.getElementById(id); if (el) el.addEventListener('click', fn); }
  bind('pbFrame', function () { if (!cur) return; frames.push(cur.snapshot()); save(); info(); toast('Fotograma ' + frames.length + ' guardado'); });
  bind('pbUndoFrame', function () { frames.pop(); save(); info(); });
  bind('pbClear', function () { frames = []; save(); info(); });
  bind('pbPlay', function () {
    if (!cur) return;
    if (frames.length < 2) { toast('Grava pelo menos 2 fotogramas'); return; }
    if (cancelAnim) cancelAnim();
    var keep = cur.snapshot();
    cancelAnim = playFrames(cur, frames, { onDone: function () { cancelAnim = null; } });
  });
  f.addEventListener('submit', save);
  f.elements['ptype'].addEventListener('change', sync);
  ['cover_bg', 'cover_fg', 'cover_emblem', 'cover_emblem2', 'remove_emblem', 'remove_emblem2', 'title', 'category', 'notes'].forEach(function (n) {
    ['input', 'change'].forEach(function (ev) {
      f.elements[n].addEventListener(ev, function () { if (f.elements['ptype'].value === 'cover') drawPreview(); });
    });
  });
  document.querySelectorAll('[data-new-panel]').forEach(function (b) { b.addEventListener('click', function () { open({}); }); });
  document.querySelectorAll('[data-edit-panel]').forEach(function (b) {
    b.addEventListener('click', function () { open(JSON.parse(b.getAttribute('data-edit-panel'))); });
  });
})();

// ---------- Ordem dos slides: botões ▲ ▼ e arrastar (página do jogo) ----------
(function () {
  var first = document.querySelector('.moment-item[data-id]');
  if (!first) return;
  var list = first.parentElement, csrf = document.querySelector('input[name="csrf"]');
  function items() { return [].slice.call(list.querySelectorAll('.moment-item[data-id]')); }
  function ids() { return items().map(function (x) { return x.getAttribute('data-id'); }); }
  var lastSaved = ids().join('|');
  items().forEach(function (a) { a.setAttribute('draggable', 'false'); });
  list.addEventListener('dragstart', function (e) { e.preventDefault(); });

  function save() {
    var now = ids();
    if (now.join('|') === lastSaved) return;
    var fd = new FormData(); fd.append('csrf', csrf.value); fd.append('reorder', JSON.stringify(now));
    fetch(location.pathname + location.search, { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { if (!r.ok) throw new Error('http'); return r.json(); })
      .then(function () {
        lastSaved = now.join('|');
        var cards = now.map(function (id) { return document.getElementById('moment-' + id); }).filter(Boolean);
        if (cards.length) { var par = cards[0].parentElement; cards.forEach(function (c) { par.appendChild(c); }); }
        toast('\u2714 Ordem guardada');
      })
      .catch(function () { toast('Erro ao guardar a ordem'); });
  }

  // botões ▲ ▼
  list.addEventListener('click', function (e) {
    var b = e.target.closest('[data-move]');
    if (!b) { if (e.target.closest('.drag-handle')) { e.preventDefault(); e.stopPropagation(); } return; }
    e.preventDefault(); e.stopPropagation();
    var it = b.closest('.moment-item'), dir = parseInt(b.getAttribute('data-move'), 10), all = items(), i = all.indexOf(it), j = i + dir;
    if (j < 0 || j >= all.length) return;
    if (dir < 0) list.insertBefore(it, all[j]); else list.insertBefore(all[j], it);
    save();
  });

  // arrastar pelo ícone ⠿ (rato e toque)
  list.addEventListener('pointerdown', function (e) {
    var h = e.target.closest('.drag-handle'); if (!h) return;
    e.preventDefault();
    var it = h.closest('.moment-item'), pid = e.pointerId;
    it.classList.add('dragging');
    function move(ev) {
      if (ev.pointerId !== pid) return;
      var after = null, others = items().filter(function (x) { return x !== it; });
      for (var i = 0; i < others.length; i++) {
        var r = others[i].getBoundingClientRect();
        if (ev.clientY < r.top + r.height / 2) { after = others[i]; break; }
      }
      list.insertBefore(it, after);
    }
    function up() {
      document.removeEventListener('pointermove', move);
      document.removeEventListener('pointerup', up);
      document.removeEventListener('pointercancel', up);
      it.classList.remove('dragging');
      save();
    }
    document.addEventListener('pointermove', move);
    document.addEventListener('pointerup', up);
    document.addEventListener('pointercancel', up);
  });
})();

// ---------- Quadro livre (cores na legenda + animação tipo coaching board) ----------
(function () {
  var host = document.getElementById('freeBoard');
  if (!host) return;
  var KEY = 'futsal_free_board', KEYC = 'futsal_free_board_colors', KEYF = 'futsal_free_board_frames';
  var cur = null, frames = [], cancel = null;
  function $(id) { return document.getElementById(id); }
  function load() {
    var b = null;
    try { b = JSON.parse(localStorage.getItem(KEY) || 'null'); } catch (e) {}
    if (b && !b.colors) { try { var c = JSON.parse(localStorage.getItem(KEYC) || 'null'); if (c) b.colors = { a: c.a, b: c.b }; } catch (e) {} }
    return b;
  }
  function persist(b) { try { localStorage.setItem(KEY, JSON.stringify(b)); } catch (e) {} }
  function loadFrames() { try { frames = JSON.parse(localStorage.getItem(KEYF) || '[]') || []; } catch (e) { frames = []; } info(); }
  function saveFrames() { try { localStorage.setItem(KEYF, JSON.stringify(frames)); } catch (e) {} info(); }
  function info() {
    var el = $('fbFrameInfo');
    if (el) el.textContent = 'Fotogramas: ' + frames.length;
  }
  function stop() { if (cancel) { cancel(); cancel = null; } }
  function draw(b) { cur = renderBoard(host, b, { editLabels: true, onChange: persist }); }
  loadFrames(); draw(load());
  function on(id, fn) { var el = $(id); if (el) el.addEventListener('click', fn); }
  on('fbReset', function () {
    stop();
    var b = defaultBoard(); b.colors = cur.get().colors; persist(b); draw(b);
  });
  on('fbFlip', function () {
    stop();
    var b = cur.get();
    b.players.forEach(function (p) { p.x = 400 - p.x; }); b.ball.x = 400 - b.ball.x;
    persist(b); draw(b);
  });
  on('fbFrame', function () { stop(); frames.push(cur.snapshot()); saveFrames(); toast('Fotograma ' + frames.length + ' guardado'); });
  on('fbPlay', function () {
    if (frames.length < 2) { toast('Grava pelo menos 2 fotogramas'); return; }
    stop(); cancel = playFrames(cur, frames, { onDone: function () { cancel = null; persist(cur.get()); } });
  });
  on('fbClearFrames', function () { stop(); frames = []; saveFrames(); });
  on('fbPng', function () { boardToPng(host.querySelector('svg'), 'quadro-tatico.png'); });
  on('fbFs', function () { (host.requestFullscreen || host.webkitRequestFullscreen).call(host); });

  // Guardar o quadro (e a animação) como momento num jogo
  var saveBtn = $('fbSaveMoment'), saveForm = $('saveBoardForm');
  function setModal(open) { var m = $('saveBoardModal'); if (m) m.classList[open ? 'remove' : 'add']('hidden'); }
  if (saveBtn) saveBtn.addEventListener('click', function () { setModal(true); });
  if (saveForm) saveForm.addEventListener('submit', function (e) {
    e.preventDefault();
    var gameId = $('fbGameId').value;
    if (!gameId) { toast('Escolhe um jogo'); return; }
    var board = JSON.parse(JSON.stringify(cur.get()));
    if (frames.length) board.frames = frames;
    var csrf = {};
    try { csrf = JSON.parse($('fbCsrf').textContent); } catch (err) {}
    var fd = new FormData();
    fd.append('save_board_moment', '1');
    fd.append('game_id', gameId);
    fd.append('title', $('fbMomentTitle').value || 'Quadro tático');
    fd.append('notes', $('fbMomentNotes').value || '');
    fd.append('tags', ($('fbMomentTags') || { value: '' }).value);
    fd.append('board_json', JSON.stringify(board));
    if (csrf.csrf) fd.append('csrf', csrf.csrf);
    fetch('board.php', { method: 'POST', body: fd, credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j.ok) throw new Error(j.error || 'Erro');
        setModal(false);
        if (confirm('Guardado. Abrir o jogo?')) location.href = 'game.php?id=' + encodeURIComponent(j.game) + '#moment-' + encodeURIComponent(j.id);
      })
      .catch(function (err) { toast(err.message || 'Não foi possível guardar'); });
  });
})();

// ---------- Modo apresentação (sem recarregar: mantém o ecrã inteiro) ----------
(function () {
  var dataEl = document.getElementById('pData');
  if (!dataEl) return;
  var D = JSON.parse(dataEl.textContent), M = D.moments, vid = D.video;
  var idx = D.sel || 0, p = null, ready = false, start = 0, end = 0, stopped = false, boardApi = null, cancelBoard = null;
  function stopBoard() { if (cancelBoard) { cancelBoard(); cancelBoard = null; } }
  function playBoard() {
    var fr = (M[idx].board && M[idx].board.frames) || [];
    stopBoard(); if (boardApi && fr.length > 1) cancelBoard = playFrames(boardApi, fr, { onDone: function () { cancelBoard = null; } });
  }
  function $(i) { return document.getElementById(i); }
  function fmt(s) { s = Math.max(0, s | 0); return ('0' + Math.floor(s / 60)).slice(-2) + ':' + ('0' + (s % 60)).slice(-2); }
  function esc(t) { var d = document.createElement('div'); d.textContent = (t == null ? '' : t); return d.innerHTML; }
  function nl(t) { return esc(t).replace(/\n/g, '<br>'); }
  function kind(m) { return (m.type === 'text' || m.type === 'index' || m.type === 'board' || m.type === 'cover') ? m.type : 'video'; }
  function icon(m) { var k = kind(m); return k === 'video' ? fmt(m.start) : ic(k === 'cover' ? 'image' : k === 'board' ? 'pitch' : (k === 'index' ? 'list' : 'file-text')); }
  function sub(m) {
    var k = kind(m);
    var base = k === 'cover' ? 'Capa' : k === 'board' ? (m.category || 'Quadro tático') : (k === 'index' ? 'Índice' : (m.category || (k === 'text' ? 'Painel de texto' : 'Geral')));
    return m.gameTitle ? (base + ' · ' + m.gameTitle) : base;
  }

  function list() {
    $('pList').innerHTML = M.map(function (m, i) {
      return '<a href="#" class="moment-item' + (i === idx ? ' active' : '') + '" data-i="' + i + '"><span class="moment-time">' + icon(m) +
        '</span><span><strong>' + esc(m.title || ('Slide ' + (i + 1))) + '</strong><small>' + esc(sub(m)) + '</small></span></a>';
    }).join('');
  }
  function menuHtml() {
    return M.map(function (m, i) {
      if (kind(m) === 'index') return '';
      return '<a href="#" class="idx-card' + (i === idx ? ' active' : '') + '" data-i="' + i + '"><span class="idx-n">' + (i + 1) + '</span><span class="idx-t"><strong>' +
        esc(m.title || ('Slide ' + (i + 1))) + '</strong><small>' + icon(m) + ' ' + esc(sub(m)) + '</small></span></a>';
    }).join('');
  }

  function show(i) {
    idx = Math.min(Math.max(i, 0), M.length - 1);
    var m = M[idx], k = kind(m);
    stopBoard();
    list();
    $('pVideoShell').hidden = k !== 'video' || !(vid || m.video);
    $('pText').hidden = !(k === 'text' || k === 'index');
    $('pBoard').hidden = k !== 'board';
    $('pCover').hidden = k !== 'cover';
    $('pNote').hidden = k !== 'video';
    $('pReplay').hidden = k !== 'video'; $('pLoopLbl').hidden = k !== 'video';
    $('pBarTitle').textContent = (idx + 1) + '/' + M.length + ' · ' + (m.title || '');
    stopped = false;
    if (k === 'text' || k === 'index') {
      $('pTextEyebrow').textContent = m.category || '';
      $('pTextTitle').textContent = m.title || '';
      $('pTextBody').innerHTML = k === 'index' ? '<div class="idx-grid">' + menuHtml() + '</div>' : nl(m.notes);
      end = 0; if (ready && p.pauseVideo) p.pauseVideo();
    } else if (k === 'cover') {
      $('pCover').innerHTML = coverHtml(m.cover, m);
      end = 0; if (ready && p.pauseVideo) p.pauseVideo();
    } else if (k === 'board') {
      $('pBoardTitle').textContent = m.title || '';
      $('pBoardNotes').innerHTML = nl(m.notes);
      boardApi = renderBoard($('pBoardHost'), m.board, {});
      var nfr = (m.board && m.board.frames) ? m.board.frames.length : 0;
      $('pBoardPlay').hidden = nfr < 2;
      if (nfr > 1) playBoard();
      end = 0; if (ready && p.pauseVideo) p.pauseVideo();
    } else {
      $('pTitle').textContent = m.title || 'Momento';
      $('pCat').textContent = m.category || 'Geral';
      $('pAthlete').textContent = 'Jogador: ' + (m.athlete || 'N/D');
      $('pTime').textContent = 'Tempo: ' + fmt(m.start) + ' - ' + fmt(m.end);
      $('pTags').innerHTML = (m.tags || []).map(function (t) { return '<span class="tag">' + esc(t) + '</span>'; }).join('');
      $('pNotes').innerHTML = nl(m.notes);
      var gt = $('pGameTitle');
      if (gt) {
        if (m.gameTitle) { gt.hidden = false; gt.textContent = 'Jogo: ' + m.gameTitle; }
        else { gt.hidden = true; gt.textContent = ''; }
      }
      start = m.start; end = m.end;
      var wantVid = m.video || vid;
      if (ready && p) {
        try {
          var curId = p.getVideoData && p.getVideoData().video_id;
          if (wantVid && curId && curId !== wantVid && p.loadVideoById) {
            p.loadVideoById({ videoId: wantVid, startSeconds: start });
          } else if (wantVid) {
            p.seekTo(start, true); p.playVideo();
          }
        } catch (e) { if (wantVid) { try { p.seekTo(start, true); p.playVideo(); } catch (e2) {} } }
      }
    }
    $('pPrev').disabled = idx === 0;
    $('pNext').disabled = idx === M.length - 1;
    try { var qs = new URLSearchParams(location.search); qs.set('moment', m.id); history.replaceState(null, '', '?' + qs.toString()); } catch (e) {}
  }

  var anyVid = vid || (M.some(function (m) { return m.video; }));
  if (anyVid) {
    var firstM = M[idx] || {};
    var firstVid = firstM.video || vid || (M.filter(function (m) { return m.video; })[0] || {}).video || '';
    var first = kind(firstM) === 'video' ? (firstM.start || 0) : 0;
    loadYT(function () {
      p = new YT.Player('presentationPlayer', {
        width: '100%', height: '100%', videoId: firstVid,
        playerVars: { rel: 0, modestbranding: 1, playsinline: 1, start: first },
        events: { onReady: function () { ready = true; show(idx); } }
      });
      setInterval(function () {
        if (!ready || !end || !p.getCurrentTime) return;
        var t = p.getCurrentTime();
        if (t >= end && !stopped) {
          stopped = true;
          if ($('pLoop').checked) { p.seekTo(start, true); } else { p.pauseVideo(); }
        } else if (t < end - 0.5) { stopped = false; }
      }, 250);
    });
  }

  function go(e) {
    var a = e.target.closest('[data-i]'); if (!a) return;
    e.preventDefault(); closeMenu(); show(parseInt(a.getAttribute('data-i'), 10));
  }
  function openMenu() { $('pMenuList').innerHTML = menuHtml(); $('pMenu').hidden = false; }
  function closeMenu() { $('pMenu').hidden = true; }
  $('pList').addEventListener('click', go);
  $('pMenuList').addEventListener('click', go);
  $('pText').addEventListener('click', go);
  $('pMenuBtn').addEventListener('click', function () { $('pMenu').hidden ? openMenu() : closeMenu(); });
  $('pMenuClose').addEventListener('click', closeMenu);
  $('pMenu').addEventListener('click', function (e) { if (e.target === $('pMenu')) closeMenu(); });
  function goPrev() { show(idx - 1); }
  function goNext() { show(idx + 1); }
  $('pPrev').addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); goPrev(); });
  $('pNext').addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); goNext(); });
  // Zonas de toque em ecrã inteiro (esquerda = anterior, direita = seguinte)
  var main = document.querySelector('.presentation-main');
  if (main) {
    main.addEventListener('click', function (e) {
      if (!document.body.classList.contains('is-fs')) return;
      if (e.target.closest('button,a,input,label,.idx-card,.presentation-actions,#pNav,#pBoard')) return;
      var rect = main.getBoundingClientRect();
      var x = e.clientX - rect.left;
      if (x < rect.width * 0.28) goPrev();
      else if (x > rect.width * 0.72) goNext();
    });
    var sx = 0, sy = 0, skipSwipe = false;
    main.addEventListener('touchstart', function (e) {
      if (!e.changedTouches || !e.changedTouches[0]) return;
      skipSwipe = !!(e.target.closest && e.target.closest('#pBoard,.idx-grid'));
      sx = e.changedTouches[0].clientX; sy = e.changedTouches[0].clientY;
    }, { passive: true });
    main.addEventListener('touchend', function (e) {
      if (!document.body.classList.contains('is-fs') || skipSwipe) return;
      if (!e.changedTouches || !e.changedTouches[0]) return;
      var dx = e.changedTouches[0].clientX - sx, dy = e.changedTouches[0].clientY - sy;
      if (Math.abs(dx) < 50 || Math.abs(dx) < Math.abs(dy)) return;
      if (dx < 0) goNext(); else goPrev();
    }, { passive: true });
  }
  $('pReplay').addEventListener('click', function () { if (ready) { stopped = false; p.seekTo(start, true); p.playVideo(); } });
  $('pBoardReset').addEventListener('click', function () { stopBoard(); boardApi = renderBoard($('pBoardHost'), M[idx].board, {}); });
  $('pBoardPlay').addEventListener('click', function () { boardApi = renderBoard($('pBoardHost'), M[idx].board, {}); playBoard(); });

  function toggleFs() {
    var el = document.documentElement;
    if (document.fullscreenElement || document.webkitFullscreenElement) { (document.exitFullscreen || document.webkitExitFullscreen).call(document); }
    else { (el.requestFullscreen || el.webkitRequestFullscreen).call(el); }
  }
  $('pFs').addEventListener('click', toggleFs);
  function fsChange() { document.body.classList.toggle('is-fs', !!(document.fullscreenElement || document.webkitFullscreenElement)); }
  document.addEventListener('fullscreenchange', fsChange);
  document.addEventListener('webkitfullscreenchange', fsChange);

  document.addEventListener('keydown', function (e) {
    var tag = (e.target.tagName || '').toLowerCase();
    if (tag === 'input' || tag === 'textarea') return;
    var k = e.key.toLowerCase();
    if (e.key === 'ArrowRight') show(idx + 1);
    else if (e.key === 'ArrowLeft') show(idx - 1);
    else if (k === 'f') toggleFs();
    else if (k === 'n') document.body.classList.toggle('show-notes');
    else if (k === 'i' || k === 'm') { $('pMenu').hidden ? openMenu() : closeMenu(); }
    else if (e.key === 'Escape') closeMenu();
  });

  if (M.length) show(idx);
})();


// ---------- Menu hambúrguer, dropdown utilizador, modais, olho password ----------
(function () {
  var bar = document.getElementById('topbar'), t = document.getElementById('navToggle');
  if (bar && t) {
    var set = function (o) { bar.classList.toggle('open', o); t.setAttribute('aria-expanded', o ? 'true' : 'false'); };
    t.addEventListener('click', function (e) { e.stopPropagation(); set(!bar.classList.contains('open')); });
    document.addEventListener('click', function (e) { if (!bar.contains(e.target)) set(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') set(false); });
    bar.querySelectorAll('.nav a').forEach(function (a) { a.addEventListener('click', function () { set(false); }); });
  }
  var ub = document.getElementById('navUserBtn'), um = document.getElementById('navUserMenu'), uw = document.getElementById('navUser');
  if (ub && um) {
    ub.addEventListener('click', function (e) {
      e.stopPropagation();
      var open = um.hasAttribute('hidden');
      if (open) um.removeAttribute('hidden'); else um.setAttribute('hidden', '');
      ub.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.addEventListener('click', function (e) {
      if (uw && !uw.contains(e.target)) { um.setAttribute('hidden', ''); ub.setAttribute('aria-expanded', 'false'); }
    });
  }
  document.querySelectorAll('[data-open-modal]').forEach(function (b) {
    b.addEventListener('click', function () {
      var id = b.getAttribute('data-open-modal');
      var m = document.getElementById(id);
      if (m) m.classList.remove('hidden');
    });
  });
  document.querySelectorAll('[data-close-modal]').forEach(function (b) {
    b.addEventListener('click', function () {
      var id = b.getAttribute('data-close-modal');
      var m = document.getElementById(id);
      if (m) m.classList.add('hidden');
    });
  });
  document.querySelectorAll('.modal').forEach(function (m) {
    m.addEventListener('click', function (e) { if (e.target === m) m.classList.add('hidden'); });
  });
  var eye = document.querySelector('.pw-eye'), pw = document.querySelector('.pw input');
  if (eye && pw) eye.addEventListener('click', function () { pw.type = pw.type === 'password' ? 'text' : 'password'; });
})();

// ---------- PWA: botão «Instalar app» e ajuda para iPad ----------
(function () {
  var btn = document.getElementById('installBtn'), btn2 = document.getElementById('installBtn2'), hint = document.getElementById('installHint'), ev = null;
  var standalone = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) || window.navigator.standalone;
  if (standalone) return;
  window.addEventListener('beforeinstallprompt', function (e) { e.preventDefault(); ev = e; if (btn) btn.hidden = false; if (btn2) btn2.hidden = false; });
  [btn, btn2].forEach(function (b) {
    if (b) b.addEventListener('click', function () {
      if (!ev) return;
      ev.prompt();
      ev.userChoice.then(function () { ev = null; if (btn) btn.hidden = true; if (btn2) btn2.hidden = true; });
    });
  });
  window.addEventListener('appinstalled', function () { if (btn) btn.hidden = true; if (btn2) btn2.hidden = true; toast('\u2714 Aplicação instalada'); });
  var ua = navigator.userAgent || '';
  var ios = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  if (ios && hint) hint.hidden = false;
})();

// ---------- Miniaturas de quadros/animações (lista de momentos) ----------
(function () {
  document.querySelectorAll('[data-board-thumb]').forEach(function (el) {
    var b = null;
    try { b = JSON.parse(el.getAttribute('data-board-thumb')); } catch (e) {}
    if (!b) return;
    var first = (b.frames && b.frames.length) ? b.frames[0] : null;
    var api = renderBoard(el, b, { readonly: true });
    if (first) api.setState(first);
    var fr = b.frames || [], cancel = null;
    if (fr.length > 1) {
      el.addEventListener('mouseenter', function () { if (cancel) cancel(); cancel = playFrames(api, fr, { duration: 700, onDone: function () { cancel = null; } }); });
      el.addEventListener('click', function () { if (cancel) cancel(); cancel = playFrames(api, fr, { duration: 700, onDone: function () { cancel = null; } }); });
    }
  });
})();

// ---------- «Carregar mais» (cartões de jogos) ----------
(function () {
  var btn = document.getElementById('loadMore');
  if (!btn) return;
  btn.addEventListener('click', function () {
    var hidden = [].slice.call(document.querySelectorAll('.game-card.more[hidden]')).slice(0, 6);
    hidden.forEach(function (c) { c.hidden = false; });
    var left = document.querySelectorAll('.game-card.more[hidden]').length;
    if (!left) btn.hidden = true; else btn.querySelector('.count').textContent = left;
  });
})();


// ---------- Vídeo do jogo: tamanho maior e ecrã inteiro com «marcar rápido» ----------
(function () {
  var layout = document.querySelector('.game-layout'), stage = document.getElementById('videoStage');
  if (!layout || !stage) return;
  var KEY = 'futsal_video_size', order = ['normal', 'large', 'xl'];
  var labels = { normal: 'Vídeo: normal', large: 'Vídeo: grande', xl: 'Vídeo: máximo' };
  var sizeBtn = document.getElementById('videoSize'), fsBtn = document.getElementById('videoFs'), lab = document.getElementById('videoSizeLabel');
  var cur = 'normal';
  try { cur = localStorage.getItem(KEY) || 'normal'; } catch (e) {}
  if (order.indexOf(cur) < 0) cur = 'normal';
  function apply() {
    layout.classList.toggle('vid-large', cur === 'large');
    layout.classList.toggle('vid-xl', cur === 'xl');
    if (lab) lab.textContent = labels[cur];
    try { localStorage.setItem(KEY, cur); } catch (e) {}
  }
  function cycle() { cur = order[(order.indexOf(cur) + 1) % order.length]; apply(); }
  function fs() {
    var req = stage.requestFullscreen || stage.webkitRequestFullscreen;
    var isFs = document.fullscreenElement || document.webkitFullscreenElement;
    if (isFs) { (document.exitFullscreen || document.webkitExitFullscreen).call(document); return; }
    if (req) req.call(stage); else stage.classList.toggle('fake-fs');
  }
  if (sizeBtn) sizeBtn.addEventListener('click', cycle);
  if (fsBtn) fsBtn.addEventListener('click', fs);
  document.addEventListener('keydown', function (e) {
    var tag = (e.target.tagName || '').toLowerCase();
    if (tag === 'input' || tag === 'textarea' || tag === 'select' || e.ctrlKey || e.metaKey) return;
    if (e.key.toLowerCase() === 'v') cycle();
  });
  apply();
})();
