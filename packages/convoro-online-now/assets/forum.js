// Convoro widget: Online Now (forum sidebar) — avatars of currently-online members.
const c = window.Convoro;
const T = {
  surface: 'rgb(var(--c-surface,255 255 255))', ink: 'rgb(var(--c-text,27 32 48))',
  muted: 'rgb(var(--c-muted,138 144 166))', line: 'rgb(var(--c-border,230 232 240))',
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

function card(title) {
  const box = document.createElement('div');
  box.style.cssText = `margin-top:20px;border:1px solid ${T.line};background:${T.surface};border-radius:var(--c-radius,12px);box-shadow:0 1px 2px rgba(0,0,0,.05);overflow:hidden`;
  const h = document.createElement('h4');
  h.textContent = title;
  h.style.cssText = `margin:0;padding:12px 16px;border-bottom:1px solid ${T.line};font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.03em;color:${T.muted}`;
  const body = document.createElement('div');
  body.style.cssText = 'padding:16px';
  box.append(h, body);
  return { box, body };
}

if (c && typeof c.registerSlot === 'function') {
  c.registerSlot('forum:sidebar', {
    ext: 'convoro-online-now',
    order: 15,
    mount(el) {
      const { box, body } = card('Online Now');
      el.appendChild(box);

      const load = () => fetch('/api/ext/online', { headers: { Accept: 'application/json' } })
        .then((r) => (r.ok ? r.json() : null))
        .then((d) => {
          if (!d) { box.remove(); return; }
          body.innerHTML = '';
          const count = document.createElement('div');
          count.style.cssText = `display:flex;align-items:center;gap:7px;font-size:13px;color:${T.ink}`;
          const g = d.guests || 0;
          const members = `<b style="color:${T.ok}">${d.count}</b> member${d.count === 1 ? '' : 's'}`;
          const guests = g > 0 ? ` · <b style="color:${T.ink}">${g}</b> guest${g === 1 ? '' : 's'}` : '';
          count.innerHTML = `<span style="width:8px;height:8px;border-radius:50%;background:${T.ok};display:inline-block"></span> ${members}${guests} online`;
          body.appendChild(count);
          if (!d.users.length && g === 0) return;
          if (!d.users.length) return;
          const grid = document.createElement('div');
          grid.style.cssText = 'display:flex;flex-wrap:wrap;gap:7px;margin-top:12px';
          d.users.forEach((a) => {
            const link = document.createElement('a');
            link.href = a.url;
            link.appendChild(avatarEl(a, 34));
            grid.appendChild(link);
          });
          body.appendChild(grid);
        })
        .catch(() => box.remove());

      load();
      const timer = setInterval(load, 60000);
      window.addEventListener('beforeunload', () => clearInterval(timer));
    },
  });
}
