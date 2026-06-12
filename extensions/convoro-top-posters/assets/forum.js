// Convoro core widget — Top Posters (forum sidebar).
(function () {
  var c = window.Convoro;
  if (!c || typeof c.registerSlot !== 'function') return;
  var V = function (n, f) { return 'rgb(var(--c-' + n + ',' + f + '))'; };
  var T = {
    surface: V('surface', '255 255 255'), ink2: V('text-2', '74 81 104'),
    muted: V('muted', '138 144 166'), line: V('border', '230 232 240'),
  };
  function tr(k) { return c.t ? c.t(k) : k; }
  function card(title) {
    var box = document.createElement('div');
    box.style.cssText = 'border:1px solid ' + T.line + ';background:' + T.surface + ';border-radius:var(--c-radius,12px);box-shadow:0 1px 2px rgba(0,0,0,.05);overflow:hidden';
    var h = document.createElement('h4');
    h.textContent = title;
    h.style.cssText = 'margin:0;padding:12px 16px;border-bottom:1px solid ' + T.line + ';font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.03em;color:' + T.muted;
    box.appendChild(h);
    return box;
  }

  c.registerSlot('forum:sidebar', {
    ext: 'convoro-top-posters', label: 'Top posters', order: 35,
    mount: function (el) {
      function render() {
        el.innerHTML = '';
        var list = ((window.Convoro && window.Convoro.data) || {}).topPosters || [];
        var box = card(tr('Top posters'));
        var ul = document.createElement('div'); ul.style.cssText = 'padding:8px';
        if (!list.length) {
          var p = document.createElement('div');
          p.textContent = tr('No posts yet.'); p.style.cssText = 'padding:6px 8px;font-size:14px;color:' + T.muted;
          ul.appendChild(p);
        } else {
          list.forEach(function (it) {
            var li = document.createElement('div');
            li.style.cssText = 'display:flex;align-items:center;justify-content:space-between;padding:6px 8px;border-radius:8px;font-size:14px';
            var n = document.createElement('span'); n.textContent = it.name; n.style.cssText = 'color:' + T.ink2;
            var ct = document.createElement('span'); ct.textContent = it.count; ct.style.cssText = 'font-weight:600;color:' + T.muted;
            li.appendChild(n); li.appendChild(ct); ul.appendChild(li);
          });
        }
        box.appendChild(ul); el.appendChild(box);
      }
      render();
      var off = c.on('convoro:data', render);
      return function () { off && off(); };
    },
  });
})();
