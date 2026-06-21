// Convoro core widget — Categories (forum sidebar).
(function () {
  var c = window.Convoro;
  if (!c || typeof c.registerSlot !== 'function') return;
  var V = function (n, f) { return 'rgb(var(--c-' + n + ',' + f + '))'; };
  var T = {
    surface: V('surface', '255 255 255'), surface2: V('surface-2', '248 249 252'),
    ink2: V('text-2', '74 81 104'), muted: V('muted', '138 144 166'), line: V('border', '230 232 240'),
  };
  function tr(k) { return c.t ? c.t(k) : k; }
  function card(title) {
    var box = document.createElement('div');
    box.style.cssText = 'border:1px solid ' + T.line + ';background:' + T.surface + ';border-radius:var(--c-radius,12px);box-shadow:0 1px 2px rgba(0,0,0,.05);overflow:hidden';
    var h = document.createElement('div');
    h.style.cssText = 'display:flex;align-items:center;gap:8px;padding:12px 16px;background:rgb(var(--c-primary,91 91 214) / .10);border-bottom:1px solid ' + T.line;
    var hicon = document.createElement('span');
    hicon.textContent = '📂';
    hicon.style.cssText = 'font-size:14px;line-height:1';
    var hlabel = document.createElement('b');
    hlabel.textContent = title;
    hlabel.style.cssText = 'font-size:13px;text-transform:uppercase;letter-spacing:.04em;color:rgb(var(--c-text-2,74 81 104))';
    h.appendChild(hicon); h.appendChild(hlabel);
    box.appendChild(h);
    return box;
  }

  c.registerSlot('forum:sidebar', {
    ext: 'convoro-categories', label: 'Categories', order: 20,
    mount: function (el) {
      function render() {
        el.innerHTML = '';
        var cats = ((window.Convoro && window.Convoro.data) || {}).categories || [];
        if (!cats.length) return;
        var box = card(tr('Categories'));
        var nav = document.createElement('nav');
        nav.style.cssText = 'display:flex;flex-direction:column;gap:2px;padding:8px';
        cats.forEach(function (cat) {
          var a = document.createElement('a');
          a.href = '/?category=' + encodeURIComponent(cat.slug);
          a.style.cssText = 'display:flex;align-items:center;gap:10px;padding:8px 12px;border-radius:8px;font-size:14px;font-weight:600;color:' + T.ink2 + ';text-decoration:none';
          a.onmouseenter = function () { a.style.background = T.surface2; };
          a.onmouseleave = function () { a.style.background = 'transparent'; };
          var nm = document.createElement('span'); nm.textContent = cat.name;
          var ct = document.createElement('span'); ct.textContent = cat.count;
          ct.style.cssText = 'margin-left:auto;background:' + T.surface2 + ';border-radius:999px;padding:2px 8px;font-size:12px;color:' + T.muted;
          a.appendChild(nm); a.appendChild(ct); nav.appendChild(a);
        });
        box.appendChild(nav); el.appendChild(box);
      }
      render();
      var off = c.on('convoro:data', render);
      return function () { off && off(); };
    },
  });
})();
