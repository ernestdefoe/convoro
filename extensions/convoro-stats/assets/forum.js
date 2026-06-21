// Convoro core widget — Community Stats (forum sidebar).
// Renders from window.Convoro.data (no fetch); re-renders on 'convoro:data'.
(function () {
  var c = window.Convoro;
  if (!c || typeof c.registerSlot !== 'function') return;
  var V = function (n, f) { return 'rgb(var(--c-' + n + ',' + f + '))'; };
  var T = {
    surface: V('surface', '255 255 255'), surface2: V('surface-2', '248 249 252'),
    ink: V('text', '27 32 48'), ink2: V('text-2', '74 81 104'),
    muted: V('muted', '138 144 166'), line: V('border', '230 232 240'),
  };
  function tr(k) { return c.t ? c.t(k) : k; }
  function fmt(n) { n = n || 0; return n >= 1000 ? (n / 1000).toFixed(1).replace('.0', '') + 'k' : String(n); }
  function card(title) {
    var box = document.createElement('div');
    box.style.cssText = 'border:1px solid ' + T.line + ';background:' + T.surface + ';border-radius:var(--c-radius,12px);box-shadow:0 1px 2px rgba(0,0,0,.05);overflow:hidden';
    var h = document.createElement('div');
    h.style.cssText = 'display:flex;align-items:center;gap:8px;padding:12px 16px;background:rgb(var(--c-primary,91 91 214) / .10);border-bottom:1px solid ' + T.line;
    var hicon = document.createElement('span');
    hicon.textContent = '📊';
    hicon.style.cssText = 'font-size:14px;line-height:1';
    var hlabel = document.createElement('b');
    hlabel.textContent = title;
    hlabel.style.cssText = 'font-size:13px;text-transform:uppercase;letter-spacing:.04em;color:rgb(var(--c-text-2,74 81 104))';
    h.appendChild(hicon); h.appendChild(hlabel);
    box.appendChild(h);
    return box;
  }

  c.registerSlot('forum:sidebar', {
    ext: 'convoro-stats', label: 'Community stats', order: 10,
    mount: function (el) {
      function render() {
        el.innerHTML = '';
        var s = ((window.Convoro && window.Convoro.data) || {}).stats || {};
        var box = card(tr('Community stats'));
        var grid = document.createElement('div');
        grid.style.cssText = 'display:grid;grid-template-columns:1fr 1fr;gap:10px;padding:16px';
        [[tr('Members'), s.members], [tr('Topics'), s.topics], [tr('Posts'), s.posts], [tr('Reactions'), s.reactions]].forEach(function (p) {
          var cell = document.createElement('div');
          cell.style.cssText = 'border:1px solid ' + T.line + ';background:' + T.surface2 + ';border-radius:10px;padding:12px';
          var b = document.createElement('b'); b.textContent = fmt(p[1]);
          b.style.cssText = 'display:block;font-size:18px;letter-spacing:-.01em;color:' + T.ink;
          var sp = document.createElement('span'); sp.textContent = p[0];
          sp.style.cssText = 'font-size:11px;color:' + T.muted;
          cell.appendChild(b); cell.appendChild(sp); grid.appendChild(cell);
        });
        box.appendChild(grid);
        el.appendChild(box);
      }
      render();
      var off = c.on('convoro:data', render);
      return function () { off && off(); };
    },
  });
})();
