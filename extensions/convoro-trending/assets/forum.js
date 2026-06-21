// Convoro core widget — Trending Topics (forum sidebar).
(function () {
  var c = window.Convoro;
  if (!c || typeof c.registerSlot !== 'function') return;
  var V = function (n, f) { return 'rgb(var(--c-' + n + ',' + f + '))'; };
  var T = {
    surface: V('surface', '255 255 255'), surface2: V('surface-2', '248 249 252'),
    ink: V('text', '27 32 48'), muted: V('muted', '138 144 166'),
    line: V('border', '230 232 240'), primary: V('primary', '91 91 214'),
  };
  function tr(k) { return c.t ? c.t(k) : k; }
  function card(title) {
    var box = document.createElement('div');
    box.style.cssText = 'border:1px solid ' + T.line + ';background:' + T.surface + ';border-radius:var(--c-radius,12px);box-shadow:0 1px 2px rgba(0,0,0,.05);overflow:hidden';
    var h = document.createElement('div');
    h.style.cssText = 'display:flex;align-items:center;gap:8px;padding:12px 16px;background:rgb(var(--c-primary,91 91 214) / .10);border-bottom:1px solid ' + T.line;
    var hicon = document.createElement('span');
    hicon.textContent = '🔥';
    hicon.style.cssText = 'font-size:14px;line-height:1';
    var hlabel = document.createElement('b');
    hlabel.textContent = title;
    hlabel.style.cssText = 'font-size:13px;text-transform:uppercase;letter-spacing:.04em;color:rgb(var(--c-text-2,74 81 104))';
    h.appendChild(hicon); h.appendChild(hlabel);
    box.appendChild(h);
    return box;
  }

  c.registerSlot('forum:sidebar', {
    ext: 'convoro-trending', label: 'Trending', order: 15,
    mount: function (el) {
      function render() {
        el.innerHTML = '';
        var items = ((window.Convoro && window.Convoro.data) || {}).trending || [];
        if (!items.length) return;
        var box = card(tr('Trending'));
        var list = document.createElement('div'); list.style.cssText = 'padding:6px';
        items.forEach(function (it, i) {
          var a = document.createElement('a');
          a.href = '/t/' + it.slug;
          a.style.cssText = 'display:flex;gap:10px;align-items:flex-start;padding:9px 10px;border-radius:9px;text-decoration:none;color:' + T.ink;
          a.onmouseenter = function () { a.style.background = T.surface2; };
          a.onmouseleave = function () { a.style.background = 'transparent'; };
          var rank = document.createElement('span'); rank.textContent = i + 1;
          rank.style.cssText = 'flex:0 0 18px;font-weight:800;color:' + T.primary + ';font-size:13px';
          var wrap = document.createElement('span'); wrap.style.cssText = 'min-width:0';
          var ttl = document.createElement('span'); ttl.textContent = it.title;
          ttl.style.cssText = 'display:block;font-size:13px;font-weight:600;line-height:1.3;overflow:hidden;text-overflow:ellipsis;white-space:nowrap';
          var rc = it.replies || 0;
          var meta = document.createElement('span');
          meta.textContent = rc + ' ' + tr(rc === 1 ? 'reply' : 'replies') + (it.cat ? ' · ' + it.cat : '');
          meta.style.cssText = 'font-size:11px;color:' + T.muted;
          wrap.appendChild(ttl); wrap.appendChild(meta);
          a.appendChild(rank); a.appendChild(wrap); list.appendChild(a);
        });
        box.appendChild(list); el.appendChild(box);
      }
      render();
      var off = c.on('convoro:data', render);
      return function () { off && off(); };
    },
  });
})();
