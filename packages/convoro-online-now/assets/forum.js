// Convoro widget: Online Now (forum sidebar) — avatars of currently-online members.
const c = window.Convoro;
const T = {
  surface: 'rgb(var(--c-surface,255 255 255))', ink: 'rgb(var(--c-ink,17 24 39))',
  muted: 'rgb(var(--c-ink-muted,122 129 148))', line: 'rgb(var(--c-line,230 232 240))',
  ok: '#16a34a',
};
const PALETTE = ['#6366f1', '#16a34a', '#e8830c', '#dc2626', '#0ea5e9', '#a855f7'];

function avatarEl(a, size) {
  const s = size + 'px';
  if (a.avatar) {
    const img = document.createElement('img');
    img.src = a.avatar; img.alt = a.name; img.title = a.name;
    img.style.cssText = `width:${s};height:${s};border-radius:50%;object-fit:cover`;
    return img;
  }
  const d = document.createElement('span');
  d.textContent = a.initials || '?'; d.title = a.name;
  d.style.cssText = `display:grid;place-items:center;width:${s};height:${s};border-radius:50%;color:#fff;font-size:${Math.round(size * 0.4)}px;font-weight:700;background:${PALETTE[((a.color || 1) - 1) % 6]}`;
  return d;
}

if (c && typeof c.registerSlot === 'function') {
  c.registerSlot('forum:sidebar', {
    ext: 'convoro-online-now',
    order: 15,
    mount(el) {
      const box = document.createElement('div');
      box.style.cssText = `margin-top:14px;border:1px solid ${T.line};background:${T.surface};border-radius:var(--c-radius,12px);padding:16px`;
      el.appendChild(box);

      const load = () => fetch('/api/ext/online', { headers: { Accept: 'application/json' } })
        .then((r) => (r.ok ? r.json() : null))
        .then((d) => {
          if (!d) { box.remove(); return; }
          box.innerHTML = '';
          const head = document.createElement('div');
          head.style.cssText = `display:flex;align-items:center;gap:7px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:${T.muted}`;
          head.innerHTML = `<span style="width:8px;height:8px;border-radius:50%;background:${T.ok};display:inline-block"></span> Online now · ${d.count}`;
          box.appendChild(head);
          if (!d.users.length) {
            const p = document.createElement('p');
            p.textContent = 'Nobody online right now.';
            p.style.cssText = `margin:10px 0 0;font-size:13px;color:${T.muted}`;
            box.appendChild(p);
            return;
          }
          const grid = document.createElement('div');
          grid.style.cssText = 'display:flex;flex-wrap:wrap;gap:7px;margin-top:12px';
          d.users.forEach((a) => {
            const link = document.createElement('a');
            link.href = a.url;
            link.appendChild(avatarEl(a, 34));
            grid.appendChild(link);
          });
          box.appendChild(grid);
        })
        .catch(() => box.remove());

      load();
      // Light refresh while the page is open.
      const timer = setInterval(load, 60000);
      window.addEventListener('beforeunload', () => clearInterval(timer));
    },
  });
}
