// Convoro core widget — Online Now (forum sidebar): count + who's online.
(function () {
  var c = window.Convoro;
  if (!c || typeof c.registerSlot !== 'function') return;
  var V = function (n, f) { return 'rgb(var(--c-' + n + ',' + f + '))'; };
  var T = {
    surface: V('surface', '255 255 255'), ink: V('text', '27 32 48'),
    muted: V('muted', '138 144 166'), line: V('border', '230 232 240'),
  };
  var AV = ['', '#6366f1', '#0ea5e9', '#10b981', '#f59e0b', '#ef4444', '#ec4899'];
  function tr(k) { return c.t ? c.t(k) : k; }
  function fmt(n) { n = n || 0; return n >= 1000 ? (n / 1000).toFixed(1).replace('.0', '') + 'k' : String(n); }
  function card(title) {
    var box = document.createElement('div');
    box.style.cssText = 'border:1px solid ' + T.line + ';background:' + T.surface + ';border-radius:var(--c-radius,12px);box-shadow:0 1px 2px rgba(0,0,0,.05);overflow:hidden';
    var h = document.createElement('h4');
    h.textContent = title;
    h.style.cssText = 'margin:0;padding:12px 16px;border-bottom:1px solid ' + T.line + ';font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.03em;color:' + T.muted;
    box.appendChild(h);
    return box;
  }
  function avatarEl(m, s) {
    var a = document.createElement('a');
    a.href = m.url || '#'; a.title = m.name || ''; a.style.cssText = 'display:block;text-decoration:none;position:relative';
    var node;
    if (m && m.avatar) {
      node = document.createElement('img');
      node.src = m.avatar; node.alt = m.name || '';
      node.style.cssText = 'width:' + s + 'px;height:' + s + 'px;border-radius:50%;object-fit:cover;display:block';
    } else {
      node = document.createElement('div');
      node.textContent = (m && m.initials) || '?';
      node.style.cssText = 'width:' + s + 'px;height:' + s + 'px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:' + Math.round(s * 0.38) + 'px;font-weight:700;color:#fff;background:' + (AV[(m && m.color) || 1] || AV[1]);
    }
    a.appendChild(node);
    // little green "online" dot
    var dot = document.createElement('span');
    dot.style.cssText = 'position:absolute;right:-1px;bottom:-1px;width:10px;height:10px;border-radius:50%;background:#10b981;border:2px solid ' + T.surface;
    a.appendChild(dot);
    return a;
  }

  c.registerSlot('forum:sidebar', {
    ext: 'convoro-online-now', label: 'Online now', order: 25,
    mount: function (el) {
      function render() {
        el.innerHTML = '';
        var d = (window.Convoro && window.Convoro.data) || {};
        var count = d.online || 0;
        var guests = d.onlineGuests || 0;
        var users = d.onlineUsers || [];
        var box = card(tr('Online now'));

        var head = document.createElement('div');
        head.style.cssText = 'display:flex;align-items:center;gap:10px;padding:14px 16px ' + (users.length ? '6px' : '14px');
        var dot = document.createElement('span');
        dot.style.cssText = 'width:10px;height:10px;border-radius:50%;background:#10b981;flex:0 0 auto';
        var big = document.createElement('span');
        big.textContent = fmt(count); big.style.cssText = 'font-size:22px;font-weight:800;letter-spacing:-.01em;color:' + T.ink;
        var lbl = document.createElement('span');
        lbl.textContent = tr(count === 1 ? 'member online' : 'members online'); lbl.style.cssText = 'font-size:13px;color:' + T.muted;
        head.appendChild(dot); head.appendChild(big); head.appendChild(lbl);
        box.appendChild(head);

        if (guests > 0) {
          var grow = document.createElement('div');
          grow.style.cssText = 'display:flex;align-items:center;gap:9px;padding:0 16px ' + (users.length ? '8px' : '14px') + ';color:' + T.muted;
          var gdot = document.createElement('span');
          gdot.style.cssText = 'width:10px;height:10px;border-radius:50%;background:' + T.muted + ';flex:0 0 auto';
          var gtxt = document.createElement('span');
          gtxt.textContent = fmt(guests) + ' ' + tr(guests === 1 ? 'guest browsing' : 'guests browsing');
          gtxt.style.cssText = 'font-size:13px';
          grow.appendChild(gdot); grow.appendChild(gtxt);
          box.appendChild(grow);
        }

        if (users.length) {
          var wrap = document.createElement('div');
          wrap.style.cssText = 'display:flex;flex-wrap:wrap;gap:8px;padding:6px 16px 16px';
          users.forEach(function (u) { wrap.appendChild(avatarEl(u, 40)); });
          box.appendChild(wrap);
        }
        el.appendChild(box);
      }
      render();
      var off = c.on('convoro:data', render);
      return function () { off && off(); };
    },
  });
})();
