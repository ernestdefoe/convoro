// Convoro core widget — About / Custom HTML (forum sidebar).
// Content + heading come from window.Convoro.data.aboutHtml / aboutTitle,
// which the live theme editor injects from the saved settings.
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
    if (title) {
      var h = document.createElement('h4');
      h.textContent = title;
      h.style.cssText = 'margin:0;padding:12px 16px;border-bottom:1px solid ' + T.line + ';font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.03em;color:' + T.muted;
      box.appendChild(h);
    }
    return box;
  }

  c.registerSlot('forum:sidebar', {
    ext: 'convoro-about', label: 'About / Custom HTML', order: 5,
    mount: function (el) {
      function render() {
        el.innerHTML = '';
        var d = (window.Convoro && window.Convoro.data) || {};
        var html = (d.aboutHtml || '').trim();
        if (!html) return; // nothing configured yet → render nothing
        var box = card((d.aboutTitle || '').trim() || tr('About'));
        var body = document.createElement('div');
        body.style.cssText = 'padding:12px 16px;font-size:14px;color:' + T.ink2 + ';line-height:1.55';
        body.innerHTML = html;
        box.appendChild(body); el.appendChild(box);
      }
      render();
      var off = c.on('convoro:data', render);
      return function () { off && off(); };
    },
  });
})();
