/* ExamCell graphical seat map. Renders server-scoped data (SeatMapService) as an SVG floor plan.
   No framework; one instance per container. Server re-validates every change. */
(function () {
  'use strict';
  var NS = 'http://www.w3.org/2000/svg';
  var DW = 60, DH = 44, GX = 10, GY = 18, AISLE = 34, LEFT = 50, RIGHT = 50, TOP = 150, BOTTOM = 64;

  function el(name, attrs, parent) {
    var n = document.createElementNS(NS, name);
    for (var k in attrs || {}) n.setAttribute(k, attrs[k]);
    if (parent) parent.appendChild(n);
    return n;
  }
  function h(tag, cls, parent, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text != null) n.textContent = text;
    if (parent) parent.appendChild(n);
    return n;
  }
  function textColor(hex) {
    var c = parseInt(hex.slice(1), 16), r = c >> 16 & 255, g = c >> 8 & 255, b = c & 255;
    return (0.299 * r + 0.587 * g + 0.114 * b) > 150 ? '#111' : '#fff';
  }
  function esc(s) { return String(s == null ? '' : s); }

  /** Seat positions for a rows×cols grid (row-major, skipping disabled labels). Mirrors App\Support\RoomLayout. */
  function label(r, c) {
    var s = '';
    for (var i = r; i >= 0; i = Math.floor(i / 26) - 1) s = String.fromCharCode(65 + i % 26) + s;
    return s + (c + 1);
  }
  function positions(rows, cols, disabled) {
    var off = {}, out = [], n = 0;
    (disabled || []).forEach(function (l) { off[String(l).toUpperCase()] = 1; });
    for (var r = 0; r < rows; r++) for (var c = 0; c < cols; c++) {
      var l = label(r, c);
      if (off[l]) continue;
      out.push({ n: ++n, r: r, c: c, label: l });
    }
    return out;
  }

  function Geometry(room) {
    var aisles = (room.aisles || []).slice().sort(function (a, b) { return a - b; });
    this.room = room;
    this.x = function (c) { var k = 0; aisles.forEach(function (a) { if (a <= c) k++; }); return LEFT + c * (DW + GX) + k * AISLE; };
    this.y = function (r) { return TOP + r * (DH + GY); };
    this.W = this.x(room.cols - 1) + DW + RIGHT;
    this.H = this.y(room.rows - 1) + DH + BOTTOM;
    var at = {}, byN = {};
    room.positions.forEach(function (p) { at[p.r + ':' + p.c] = p.n; byN[p.n] = p; });
    this.byN = byN;
    this.at = function (r, c) { return at[r + ':' + c] || null; };
    this.right = function (n) { var p = byN[n]; return p && aisles.indexOf(p.c + 1) < 0 ? this.at(p.r, p.c + 1) : null; };
    this.left = function (n) { var p = byN[n]; return p && aisles.indexOf(p.c) < 0 ? this.at(p.r, p.c - 1) : null; };
    this.center = function (n) { var p = byN[n]; return { x: this.x(p.c) + DW / 2, y: this.y(p.r) + DH / 2 }; };
    this.hit = function (pt) {
      for (var i = 0; i < room.positions.length; i++) {
        var p = room.positions[i], x = this.x(p.c), y = this.y(p.r);
        if (pt.x >= x && pt.x <= x + DW && pt.y >= y && pt.y <= y + DH) return p.n;
      }
      return null;
    };
  }

  function patternDefs(svg) {
    var defs = el('defs', {}, svg), mk = function (id, draw) {
      var p = el('pattern', { id: id, width: 8, height: 8, patternUnits: 'userSpaceOnUse' }, defs);
      draw(p);
    }, st = { stroke: 'rgba(0,0,0,.38)', 'stroke-width': 1.4 };
    mk('sm-diag', function (p) { el('path', Object.assign({ d: 'M-2,2 l4,-4 M0,8 l8,-8 M6,10 l4,-4' }, st), p); });
    mk('sm-rdiag', function (p) { el('path', Object.assign({ d: 'M-2,6 l4,4 M0,0 l8,8 M6,-2 l4,4' }, st), p); });
    mk('sm-dots', function (p) { el('circle', { cx: 4, cy: 4, r: 1.6, fill: 'rgba(0,0,0,.4)' }, p); });
    mk('sm-horiz', function (p) { el('path', Object.assign({ d: 'M0,4 h8' }, st), p); });
    mk('sm-vert', function (p) { el('path', Object.assign({ d: 'M4,0 v8' }, st), p); });
    mk('sm-cross', function (p) { el('path', Object.assign({ d: 'M0,0 l8,8 M8,0 l-8,8' }, st), p); });
    mk('sm-grid', function (p) { el('path', Object.assign({ d: 'M0,4 h8 M4,0 v8' }, st), p); });
  }

  function mount(root, data, opts) {
    opts = opts || {};
    var S = {
      data: data, room: null, view: 'room', active: null, overlay: false, vb: null, geo: null,
      static: !!opts.static, editable: !!data.editable && !opts.static
    };
    var sectionByKey = {};
    (data.sections || []).forEach(function (s) { sectionByKey[s.key] = s; });
    var papersById = {};
    (data.papers || []).forEach(function (p) { papersById[p.id] = p; });

    root.classList.add('sm2');
    root.classList.toggle('sm2-editable', S.editable);
    root.innerHTML = '';
    var top = h('div', 'sm2-toolbar', root), legend = h('div', 'sm2-legend', root);
    var summary = h('div', 'sm2-summary', root), thumbs = h('div', 'sm2-thumbs', root);
    var classPanel = h('div', 'sm2-classview', root);
    var stage = h('div', 'sm2-stage', root), msg = h('p', 'sm2-msg', root);
    msg.setAttribute('role', 'status'); msg.setAttribute('aria-live', 'polite');
    var card = h('div', 'sm2-card', stage); card.hidden = true;
    var svg = el('svg', { role: 'img', class: 'sm2-svg' }, stage);

    function seatsIn(roomId) { return S.data.seats.filter(function (s) { return s.room === roomId; }); }
    function seatAt(roomId, n) { for (var i = 0; i < S.data.seats.length; i++) { var s = S.data.seats[i]; if (s.room === roomId && s.seat === n) return s; } return null; }
    function roomById(id) { for (var i = 0; i < S.data.rooms.length; i++) if (S.data.rooms[i].id === id) return S.data.rooms[i]; return null; }

    /* ---------- toolbar ---------- */
    function button(text, title, onClick, parent) {
      var b = h('button', 'btn btn-secondary btn-small', parent || top, text);
      b.type = 'button'; if (title) { b.title = title; b.setAttribute('aria-label', title); }
      b.addEventListener('click', onClick);
      return b;
    }
    var staff = S.data.mode !== 'student';
    if (!S.static) {
      if (staff) {
        var vr = button('Room-wise', 'Room-wise view', function () { setView('room'); });
        var vc = button('Class-wise', 'Class-wise view', function () { setView('class'); });
      }
      var search = h('input', 'sm2-search', top);
      search.type = 'search';
      search.placeholder = staff ? 'Find roll number or name' : 'Find my seat';
      search.setAttribute('aria-label', 'Find a seat by roll number or name');
      search.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); find(search.value); } });
      search.addEventListener('change', function () { find(search.value); });
      if (staff) {
        var ov = h('label', 'sm2-toggle', top);
        var cb = h('input', null, ov); cb.type = 'checkbox';
        ov.appendChild(document.createTextNode(' Adjacency check'));
        cb.addEventListener('change', function () { S.overlay = cb.checked; draw(); });
      }
    } else top.hidden = true;

    function setView(v) {
      S.view = v;
      if (vr) vr.setAttribute('aria-pressed', v === 'room');
      if (vc) vc.setAttribute('aria-pressed', v === 'class');
      renderClassPanel();
    }

    /* ---------- legend + summary + thumbnails ---------- */
    function renderLegend() {
      legend.innerHTML = '';
      if (!S.data.sections || !S.data.sections.length || opts.print) { legend.hidden = true; return; }
      S.data.sections.forEach(function (s) {
        var chip = h('button', 'sm2-chip', legend);
        chip.type = 'button';
        chip.setAttribute('aria-pressed', S.active === s.key);
        var sw = el('svg', { width: 18, height: 18, 'aria-hidden': 'true' }, chip);
        el('rect', { x: 1, y: 1, width: 16, height: 16, rx: 3, fill: s.color }, sw);
        if (s.pattern !== 'solid') el('rect', { x: 1, y: 1, width: 16, height: 16, rx: 3, fill: 'url(#sm-' + s.pattern + ')' }, sw);
        h('strong', null, chip, s.abbr);
        h('span', null, chip, s.name + ' · ' + s.count);
        chip.addEventListener('click', function () { S.active = S.active === s.key ? null : s.key; renderLegend(); draw(); renderThumbs(); renderClassPanel(); });
      });
    }
    function renderSummary() {
      var m = S.data.summary;
      if (!m || opts.print) { summary.hidden = true; return; }
      summary.innerHTML = '';
      [['Students', m.students], ['Seats used', m.seats_used + ' / ' + m.seats_total], ['Rooms used', m.rooms_used], ['Classes', m.classes + (m.mixed_rooms ? ' · mixed in ' + m.mixed_rooms + ' room' + (m.mixed_rooms > 1 ? 's' : '') : '')], ['Side-by-side clashes', m.clashes], ['Unseated', m.unseated]].concat(m.not_eligible ? [['Seated but no longer eligible', m.not_eligible]] : []).forEach(function (k) {
        var d = h('div', 'sm2-stat' + (k[0] !== 'Students' && k[0] !== 'Seats used' && k[0] !== 'Rooms used' && k[0] !== 'Classes' && k[1] ? ' is-bad' : ''), summary);
        h('span', null, d, k[0]); h('strong', null, d, String(k[1]));
      });
    }
    function renderThumbs() {
      thumbs.innerHTML = '';
      if (opts.print || S.data.rooms.length < 2 && (S.data.mode === 'student' || S.static)) { thumbs.hidden = true; return; }
      S.data.rooms.forEach(function (room) {
        var g = new Geometry(room), b = h('button', 'sm2-thumb' + (S.room === room.id ? ' is-active' : ''), thumbs);
        b.type = 'button';
        b.setAttribute('aria-label', 'Open ' + room.name + ', ' + room.used + ' of ' + room.capacity + ' seats used');
        var t = el('svg', { viewBox: '0 0 ' + g.W + ' ' + g.H, 'aria-hidden': 'true' }, b);
        el('rect', { x: 4, y: 4, width: g.W - 8, height: g.H - 8, rx: 8, class: 'sm2-floor' }, t);
        room.positions.forEach(function (p) {
          var s = seatAt(room.id, p.n), sec = s && sectionByKey[s.section];
          var dim = S.active && (!s || s.section !== S.active);
          el('rect', { x: g.x(p.c), y: g.y(p.r), width: DW, height: DH, rx: 6, fill: sec ? sec.color : (s ? (s.mine ? '#0072B2' : 'var(--sm-occupied)') : 'var(--sm-empty)'), opacity: dim ? 0.2 : 1 }, t);
        });
        var cap = h('div', 'sm2-thumb-cap', b);
        h('strong', null, cap, room.name);
        h('span', null, cap, room.used + '/' + room.capacity);
        var bar = h('div', 'sm2-bar', b);
        (S.data.sections || []).forEach(function (s) {
          var n = room.by_section ? room.by_section[s.key] || 0 : 0;
          if (n) { var seg = h('i', null, bar); seg.style.width = (100 * n / room.capacity) + '%'; seg.style.background = s.color; seg.title = s.abbr + ': ' + n; }
        });
        b.addEventListener('click', function () { openRoom(room.id); setView('room'); });
      });
    }
    function renderClassPanel() {
      classPanel.innerHTML = '';
      classPanel.hidden = S.view !== 'class';
      if (S.view !== 'class') return;
      var sel = S.active ? [sectionByKey[S.active]] : S.data.sections;
      if (!S.active) h('p', 'muted text-xs', classPanel, 'Pick a class in the legend to highlight it everywhere.');
      sel.forEach(function (s) {
        var box = h('section', 'sm2-class', classPanel);
        h('h3', null, box, s.name + ' (' + s.abbr + ') · ' + s.count + ' students');
        var tbl = h('table', 'data-table', box), hd = h('tr', null, h('thead', null, tbl));
        ['Room', 'Seats', 'Students'].forEach(function (t) { h('th', null, hd, t); });
        var tb = h('tbody', null, tbl);
        S.data.rooms.forEach(function (room) {
          var mine = seatsIn(room.id).filter(function (x) { return x.section === s.key; });
          if (!mine.length) return;
          var tr = h('tr', null, tb), a = h('button', 'card-link', h('td', null, tr), room.name);
          a.type = 'button';
          a.addEventListener('click', function () { S.active = s.key; openRoom(room.id); renderLegend(); });
          h('td', null, tr, mine.map(function (x) { return x.label; }).join(', '));
          h('td', null, tr, String(mine.length));
        });
      });
    }

    /* ---------- the room plan ---------- */
    function openRoom(id) {
      S.room = id; S.geo = new Geometry(roomById(id)); fit(); renderThumbs();
    }
    /** Largest size that shows the whole room: fill the available width, unless that makes it taller than the screen allows. The frame then hugs the map. */
    function size() {
      if (!S.geo || opts.print) return;
      var avail = Math.max(240, (root.clientWidth || 800) - 2);
      var maxH = Math.max(320, window.innerHeight * (S.static ? 0.55 : 0.82));
      var ratio = S.geo.H / S.geo.W, w = avail, hgt = avail * ratio;
      if (hgt > maxH) { hgt = maxH; w = hgt / ratio; }
      svg.style.width = Math.round(w) + 'px';
      svg.style.height = Math.round(hgt) + 'px';
    }
    function fit() { if (!S.geo) return; size(); S.vb = { x: 0, y: 0, w: S.geo.W, h: S.geo.H }; applyVb(); draw(); }
    window.addEventListener('resize', function () { size(); });
    function applyVb() { svg.setAttribute('viewBox', [S.vb.x, S.vb.y, S.vb.w, S.vb.h].join(' ')); }
    function toSvg(e) {
      var pt = svg.createSVGPoint(); pt.x = e.clientX; pt.y = e.clientY;
      return pt.matrixTransform(svg.getScreenCTM().inverse());
    }

    function draw() {
      svg.innerHTML = '';
      patternDefs(svg);
      var room = roomById(S.room), g = S.geo;
      if (!room) return;
      svg.setAttribute('aria-label', room.name + ' seating plan, front of room at the top');
      // Walls, floor, windows, door
      el('rect', { x: 6, y: 6, width: g.W - 12, height: g.H - 12, rx: 4, class: 'sm2-floor sm2-wall' }, svg);
      var winSide = room.door === 'left' ? g.W - 6 : 6;
      for (var wy = TOP + 20; wy < g.H - 60; wy += 130) el('line', { x1: winSide, y1: wy, x2: winSide, y2: wy + 60, class: 'sm2-window' }, svg);
      var doorX = room.door === 'left' ? 6 : g.W - 6, dy = 64;
      el('line', { x1: doorX, y1: dy, x2: doorX, y2: dy + 52, class: 'sm2-doorgap' }, svg);
      var sw = room.door === 'left' ? 1 : -1;
      el('path', { d: 'M' + doorX + ',' + dy + ' l' + (sw * 46) + ',0 A46,46 0 0,' + (room.door === 'left' ? 1 : 0) + ' ' + doorX + ',' + (dy + 46), class: 'sm2-door' }, svg);
      var dl = el('text', { x: doorX + sw * 26, y: dy + 70, 'text-anchor': 'middle', class: 'sm2-small' }, svg); dl.textContent = 'DOOR';
      // Front: board, FRONT, invigilator desk
      var bw = Math.min(g.W * 0.55, 380);
      el('rect', { x: (g.W - bw) / 2, y: 18, width: bw, height: 12, rx: 3, class: 'sm2-board' }, svg);
      var bt = el('text', { x: g.W / 2, y: 46, 'text-anchor': 'middle', class: 'sm2-small' }, svg); bt.textContent = 'WHITEBOARD';
      var ft = el('text', { x: g.W / 2, y: 76, 'text-anchor': 'middle', class: 'sm2-front' }, svg); ft.textContent = 'FRONT';
      el('rect', { x: g.W / 2 - 60, y: 88, width: 120, height: 26, rx: 5, class: 'sm2-invig' }, svg);
      var it = el('text', { x: g.W / 2, y: 105, 'text-anchor': 'middle', class: 'sm2-small' }, svg); it.textContent = 'Invigilator desk';
      // Column numbers and row letters
      for (var c = 0; c < room.cols; c++) { var cn = el('text', { x: g.x(c) + DW / 2, y: TOP - 12, 'text-anchor': 'middle', class: 'sm2-axis' }, svg); cn.textContent = c + 1; }
      for (var r = 0; r < room.rows; r++) { var rl = el('text', { x: LEFT / 2 + 2, y: g.y(r) + DH / 2 + 4, 'text-anchor': 'middle', class: 'sm2-axis' }, svg); rl.textContent = label(r, 0).replace(/\d+$/, ''); }
      // Disabled cells
      (room.disabled || []).forEach(function (l) {
        var m = /^([A-Z]+)(\d+)$/.exec(l); if (!m) return;
        var rr = 0; for (var i = 0; i < m[1].length; i++) rr = rr * 26 + (m[1].charCodeAt(i) - 64); rr -= 1;
        var x = g.x(+m[2] - 1), y = g.y(rr);
        el('rect', { x: x, y: y, width: DW, height: DH, rx: 6, class: 'sm2-disabled' }, svg);
        el('path', { d: 'M' + (x + 8) + ',' + (y + 8) + ' L' + (x + DW - 8) + ',' + (y + DH - 8) + ' M' + (x + DW - 8) + ',' + (y + 8) + ' L' + (x + 8) + ',' + (y + DH - 8), class: 'sm2-x' }, svg);
      });
      // Desks
      room.positions.forEach(function (p) {
        var s = seatAt(room.id, p.n), sec = s && sectionByKey[s.section], x = g.x(p.c), y = g.y(p.r);
        var gg = el('g', { class: 'sm2-desk' + (s ? ' is-occupied' : ' is-empty') + (s && s.mine ? ' is-mine' : ''), 'data-n': p.n, tabindex: S.static ? -1 : 0, role: 'button', 'aria-label': deskLabel(room, p, s) }, svg);
        if (S.active && (!s || s.section !== S.active)) gg.setAttribute('opacity', 0.18);
        var fill = sec ? sec.color : (s ? (s.mine ? '#0072B2' : 'var(--sm-occupied)') : 'var(--sm-empty)');
        el('rect', { x: x, y: y, width: DW, height: DH, rx: 6, fill: fill, class: 'sm2-deskrect' }, gg);
        if (sec && sec.pattern !== 'solid') el('rect', { x: x, y: y, width: DW, height: DH, rx: 6, fill: 'url(#sm-' + sec.pattern + ')', 'pointer-events': 'none' }, gg);
        var tc = sec ? textColor(sec.color) : (s && s.mine ? '#fff' : 'var(--sm-text)');
        var lt = el('text', { x: x + 5, y: y + 12, fill: tc, class: 'sm2-seatno' }, gg); lt.textContent = p.label;
        if ((room.accessible || []).indexOf(p.n) >= 0) { var ac = el('text', { x: x + DW - 5, y: y + 12, 'text-anchor': 'end', fill: tc, class: 'sm2-seatno' }, gg); ac.textContent = '♿'; }
        if (s && s.mine && S.data.mode === 'student') {
          var you = el('text', { x: x + DW / 2, y: y + 31, 'text-anchor': 'middle', fill: '#fff', class: 'sm2-you' }, gg); you.textContent = 'You';
        } else if (s && s.roll) {
          var rt = el('text', { x: x + DW / 2, y: y + 27, 'text-anchor': 'middle', fill: tc, class: 'sm2-roll' }, gg);
          rt.textContent = s.roll; if (s.roll.length > 9) { rt.setAttribute('textLength', DW - 8); rt.setAttribute('lengthAdjust', 'spacingAndGlyphs'); }
        }
        if (sec) { var ab = el('text', { x: x + DW / 2, y: y + DH - 5, 'text-anchor': 'middle', fill: tc, class: 'sm2-abbr' }, gg); ab.textContent = sec.abbr; }
        if (s && s.mine) el('rect', { x: x - 4, y: y - 4, width: DW + 8, height: DH + 8, rx: 9, class: 'sm2-ring' }, gg);
        if (!S.static) {
          gg.addEventListener('pointerenter', function (e) { if (e.pointerType === 'mouse' && !S.drag) showCard(room, p, s, x, y); });
          gg.addEventListener('pointerleave', function (e) { if (e.pointerType === 'mouse') card.hidden = true; });
          gg.addEventListener('focus', function () { showCard(room, p, s, x, y); });
          gg.addEventListener('blur', function () { card.hidden = true; });
        }
      });
      if (S.overlay) (S.data.pairs || []).forEach(function (pr) {
        if (pr.room !== room.id || !g.byN[pr.a] || !g.byN[pr.b]) return;
        var a = g.center(pr.a), b = g.center(pr.b);
        el('line', { x1: a.x, y1: a.y, x2: b.x, y2: b.y, class: 'sm2-adj' + (pr.kind === 'side' ? '' : ' is-fb') }, svg);
      });
      if (S.overlay) msg.textContent = (S.data.pairs || []).filter(function (p) { return p.room === room.id; }).length ? 'Red lines join same-class neighbours: solid = side by side (must be fixed), dashed = front/back (advisory).' : 'No same-class neighbours in this room.';
      var bl = el('text', { x: g.W / 2, y: g.H - 22, 'text-anchor': 'middle', class: 'sm2-small' }, svg); bl.textContent = room.name + ' · ' + room.used + '/' + room.capacity + ' seats · BACK';
    }

    function deskLabel(room, p, s) {
      if (!s) return 'Seat ' + p.label + ', empty';
      if (s.mine && S.data.mode === 'student') return 'Your seat ' + p.label;
      if (!s.name) return 'Seat ' + p.label + ', occupied';
      var sec = sectionByKey[s.section];
      return 'Seat ' + p.label + ', ' + s.name + ', ' + s.roll + (sec ? ', ' + sec.name : '');
    }
    function showCard(room, p, s, x, y) {
      card.innerHTML = '';
      h('strong', null, card, (s && s.mine && S.data.mode === 'student' ? 'Your seat ' : 'Seat ') + p.label + ' · ' + room.name);
      if (s && s.name) {
        var sec = sectionByKey[s.section];
        [s.name, 'Roll ' + s.roll, sec ? sec.name : null, s.course, s.special ? 'Needs an accessible seat' : null, s.ineligible ? '⚠ No longer eligible for this paper' : null].forEach(function (t) { if (t) h('span', null, card, t); });
      } else h('span', null, card, s ? 'Occupied' : 'Empty seat');
      var box = svg.getBoundingClientRect(), sb = stage.getBoundingClientRect(), k = box.width / S.vb.w;
      card.style.left = Math.max(4, Math.min(sb.width - 230, (x - S.vb.x) * k + box.left - sb.left)) + 'px';
      card.style.top = Math.max(4, (y - S.vb.y + DH + 8) * k + box.top - sb.top) + 'px';
      card.hidden = false;
    }

    function find(q) {
      q = String(q || '').trim().toLowerCase();
      if (!q) return;
      var hit = null;
      S.data.seats.forEach(function (s) {
        if (hit) return;
        if ((s.roll && s.roll.toLowerCase() === q) || (s.roll && s.roll.toLowerCase().indexOf(q) >= 0) || (s.name && s.name.toLowerCase().indexOf(q) >= 0) || (s.mine && /^(me|my|you|mine|my seat)$/.test(q))) hit = s;
      });
      if (!hit && S.data.mode === 'student') S.data.seats.forEach(function (s) { if (s.mine) hit = s; });
      if (!hit) { msg.textContent = 'No seat found for “' + q + '”.'; return; }
      if (S.room !== hit.room) openRoom(hit.room);
      setView('room');
      draw();
      stage.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
      var node = svg.querySelector('.sm2-desk[data-n="' + hit.seat + '"]');
      if (node) { node.classList.add('sm2-pulse'); setTimeout(function () { node.classList.remove('sm2-pulse'); }, 3200); }
      msg.textContent = 'Seat ' + hit.label + ' in ' + roomById(hit.room).name + (hit.name ? ' — ' + hit.name : '') + '.';
    }

    /* ---------- drag-to-swap preview validation (server re-checks) ---------- */
    function checkMove(from, toN) {
      var room = roomById(S.room), g = S.geo, cur = {};
      seatsIn(room.id).forEach(function (s) { cur[s.seat] = s; });
      var moving = cur[from.seat], other = cur[toN] || null, next = Object.assign({}, cur);
      next[toN] = moving; if (other) next[from.seat] = other; else delete next[from.seat];
      var reasons = [];
      function need(st, n) {
        if (!st) return;
        var paper = papersById[st.paper];
        if (paper && paper.room_ids && paper.room_ids.indexOf(room.id) < 0) reasons.push(st.roll + ' cannot sit in this room (not allocated to their paper).');
        if (st.special && room.accessible.length && room.accessible.indexOf(n) < 0) reasons.push(st.roll + ' needs an accessible seat.');
        [g.left(n), g.right(n)].forEach(function (m) {
          var nb = m && next[m];
          if (nb && nb !== st && nb.section === st.section) reasons.push('Same class (' + (sectionByKey[st.section] || {}).abbr + ') would sit next to seat ' + g.byN[m].label + '.');
        });
      }
      need(moving, toN); if (other) need(other, from.seat);
      return reasons;
    }

    var pointers = {}, gesture = null;
    if (!S.static) {
      svg.addEventListener('pointerdown', function (e) {
        pointers[e.pointerId] = { x: e.clientX, y: e.clientY };
        var ids = Object.keys(pointers);
        if (ids.length > 1) { gesture = null; return; }
        var desk = e.target.closest && e.target.closest('.sm2-desk'), n = desk ? +desk.getAttribute('data-n') : null, s = n ? seatAt(S.room, n) : null;
        if (S.editable && s && s.student) {
          gesture = { type: 'drag', from: s, sx: e.clientX, sy: e.clientY, moved: false, target: null, reasons: [] };
          svg.setPointerCapture(e.pointerId);
          S.drag = true; card.hidden = true;
        } else gesture = { type: 'tap', sx: e.clientX, sy: e.clientY, moved: false, n: n };
      });
      svg.addEventListener('pointermove', function (e) {
        if (!gesture || !pointers[e.pointerId]) return;
        pointers[e.pointerId] = { x: e.clientX, y: e.clientY };
        var k = S.vb.w / svg.getBoundingClientRect().width, dx = e.clientX - gesture.sx, dy = e.clientY - gesture.sy;
        if (Math.abs(dx) + Math.abs(dy) > 5) gesture.moved = true;
        if (gesture.type === 'drag' && gesture.moved) {
          var p = toSvg(e), t = S.geo.hit(p);
          var ghost = svg.querySelector('.sm2-ghost') || el('rect', { width: DW, height: DH, rx: 6, class: 'sm2-ghost' }, svg);
          ghost.setAttribute('x', p.x - DW / 2); ghost.setAttribute('y', p.y - DH / 2);
          svg.querySelectorAll('.sm2-desk.is-target').forEach(function (n) { n.classList.remove('is-target', 'is-valid', 'is-invalid'); });
          gesture.target = t && t !== gesture.from.seat ? t : null;
          if (gesture.target) {
            gesture.reasons = checkMove(gesture.from, gesture.target);
            var node = svg.querySelector('.sm2-desk[data-n="' + t + '"]');
            if (node) node.classList.add('is-target', gesture.reasons.length ? 'is-invalid' : 'is-valid');
            msg.textContent = gesture.reasons.length ? '✕ ' + gesture.reasons.join(' ') : '✓ Drop to move ' + gesture.from.roll + ' to ' + S.geo.byN[t].label + '.';
            msg.className = 'sm2-msg ' + (gesture.reasons.length ? 'is-bad' : 'is-good');
          }
        }
      });
      function end(e) {
        delete pointers[e.pointerId];
        if (!gesture) return;
        var g = gesture; gesture = Object.keys(pointers).length ? gesture : null;
        if (g.type === 'tap' && !g.moved && g.n) { var s = seatAt(S.room, g.n), p = S.geo.byN[g.n]; showCard(roomById(S.room), p, s, S.geo.x(p.c), S.geo.y(p.r)); }
        if (g.type === 'drag') {
          S.drag = false;
          var ghost = svg.querySelector('.sm2-ghost'); if (ghost) ghost.remove();
          if (!g.moved) { var p2 = S.geo.byN[g.from.seat]; showCard(roomById(S.room), p2, g.from, S.geo.x(p2.c), S.geo.y(p2.r)); }
          else if (g.target && !g.reasons.length) save(g.from, g.target);
          else { draw(); if (g.target) msg.textContent = 'Not moved: ' + g.reasons.join(' '); }
          gesture = null;
        }
      }
      svg.addEventListener('pointerup', end);
      svg.addEventListener('pointercancel', end);
      svg.addEventListener('keydown', function (e) { if (e.key === 'Escape') card.hidden = true; });
    }

    function save(from, toN) {
      msg.textContent = 'Saving…';
      var token = document.querySelector('meta[name=csrf-token]');
      fetch('/workflow/seat-map/' + S.data.entry + '/swap', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token ? token.content : '' }, body: JSON.stringify({ student_id: from.student, room_id: S.room, seat: toN }) })
        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
        .then(function (res) {
          if (res.ok) { var keep = S.vb; S.data = res.j.data; S.vb = keep; renderAll(true); msg.className = 'sm2-msg is-good'; msg.textContent = 'Moved ' + from.roll + ' to ' + S.geo.byN[toN].label + '. Plan re-validated.'; }
          else { draw(); msg.className = 'sm2-msg is-bad'; msg.textContent = 'Rejected: ' + ((res.j.errors || []).join(' ') || res.j.message || 'invalid move'); }
        })
        .catch(function () { draw(); msg.textContent = 'The move could not be saved.'; });
    }

    function renderAll(keepView) {
      sectionByKey = {}; (S.data.sections || []).forEach(function (s) { sectionByKey[s.key] = s; });
      papersById = {}; (S.data.papers || []).forEach(function (p) { papersById[p.id] = p; });
      renderLegend(); renderSummary();
      if (!S.data.rooms.length) { stage.innerHTML = '<p class="muted">No rooms to show.</p>'; return; }
      var start = S.room && roomById(S.room) ? S.room : (opts.roomId || (S.data.seats.filter(function (s) { return s.mine; })[0] || {}).room || S.data.rooms[0].id);
      S.room = start; S.geo = new Geometry(roomById(start));
      size();
      if (!keepView || !S.vb) S.vb = { x: 0, y: 0, w: S.geo.W, h: S.geo.H };
      applyVb(); draw(); renderThumbs(); renderClassPanel();
    }
    renderAll(false);
    return { find: find, fit: fit, openRoom: openRoom, state: S };
  }

  window.SeatMap = { mount: mount, positions: positions, label: label };
})();
