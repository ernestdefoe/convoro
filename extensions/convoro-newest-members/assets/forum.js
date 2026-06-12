// Convoro core widget — Newest Members (forum sidebar).
(function () {
  var c = window.Convoro;
  if (!c || typeof c.registerSlot !== 'function') return;
  var V = function (n, f) { return 'rgb(var(--c-' + n + ',' + f + '))'; };
  var T = {
    surface: V('surface', '255 255 255'), muted: V('muted', '138 144 166'),
    line: V('border', '230 232 240'),
  };
  var AV = ['', '#6366f1', '#0ea5e9', '#10b981', '#f59e0b', '#ef4444', '#ec4899'];
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
  function avatarEl(m, s) {
    if (m && m.avatar) {
      var img = document.createElement('img');
      img.src = m.avatar; img.alt = m.name || '';
      img.style.cssText = 'width:' + s + 'px;height:' + s + 'px;border-radius:50%;object-fit:cover;display:block';
      return img;
    }
    var d = document.createElement('div');
    d.textContent = (m && m.initials) || '?';
    d.style.cssText = 'width:' + s + 'px;height:' + s + 'px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:' + Math.round(s * 0.38) + 'px;font-weight:700;color:#fff;background:' + (AV[(m && m.color) || 1] || AV[1]);
    return d;
  }

  c.registerSlot('forum:sidebar', {
    ext: 'convoro-newest-members', label: 'Newest members', order: 30,
    mount: function (el) {
      function render() {
        el.innerHTML = '';
        var members = ((window.Convoro && window.Convoro.data) || {}).newestMembers || [];
        var box = card(tr('Newest members'));
        var wrap = document.createElement('div');
        wrap.style.cssText = 'display:flex;flex-wrap:wrap;gap:8px;padding:16px';
        if (!members.length) {
          var p = document.createElement('p');
          p.textContent = tr('No members yet.'); p.style.cssText = 'font-size:14px;color:' + T.muted + ';margin:0';
          wrap.appendChild(p);
        } else {
          members.forEach(function (m) {
            var a = document.createElement('a');
            a.href = m.url || '#'; a.title = m.name || ''; a.style.cssText = 'display:block;text-decoration:none';
            a.appendChild(avatarEl(m, 44)); wrap.appendChild(a);
          });
        }
        box.appendChild(wrap); el.appendChild(box);
      }
      render();
      var off = c.on('convoro:data', render);
      return function () { off && off(); };
    },
  });
})();
