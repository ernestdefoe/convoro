// Convoro widget: Members Online (forum sidebar).
const c = window.Convoro;
const T = {
  surface: 'rgb(var(--c-surface,255 255 255))', surface2: 'rgb(var(--c-surface-2,243 244 249))',
  ink: 'rgb(var(--c-ink,17 24 39))', muted: 'rgb(var(--c-ink-muted,122 129 148))',
  line: 'rgb(var(--c-line,230 232 240))', primary: 'rgb(var(--c-primary,91 91 214))',
};
const PALETTE = ['#6366f1', '#16a34a', '#e8830c', '#dc2626', '#0ea5e9', '#a855f7'];

function avatarEl(a, size) {
  const s = size + 'px';
  if (a.avatar) {
    const img = document.createElement('img');
    img.src = a.avatar; img.alt = a.name;
    img.style.cssText = `width:${s};height:${s};border-radius:50%;object-fit:cover`;
    return img;
  }
  const d = document.createElement('span');
  d.textContent = a.initials || '?';
  d.title = a.name;
  d.style.cssText = `display:grid;place-items:center;width:${s};height:${s};border-radius:50%;color:#fff;font-size:${Math.round(size * 0.4)}px;font-weight:700;background:${PALETTE[((a.color || 1) - 1) % 6]}`;
  return d;
}

if (c && typeof c.registerSlot === 'function') {
  c.registerSlot('forum:sidebar', {
    ext: 'convoro-members-online',
    order: 20,
    mount(el) {
      const box = document.createElement('div');
      box.style.cssText = `margin-top:14px;border:1px solid ${T.line};background:${T.surface};border-radius:var(--c-radius,12px);padding:16px`;
      el.appendChild(box);

      fetch('/api/ext/members', { headers: { Accept: 'application/json' } })
        .then((r) => (r.ok ? r.json() : null))
        .then((d) => {
          if (!d) { box.remove(); return; }
          box.innerHTML = `<div style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:${T.muted}">Members</div>
            <div style="display:flex;align-items:baseline;gap:8px;margin-top:8px">
              <b style="font-size:26px;color:${T.primary}">${d.online}</b>
              <span style="font-size:13px;color:${T.muted}">online now</span>
            </div>
            <div style="font-size:13px;color:${T.ink};margin-top:2px">${d.total.toLocaleString()} member${d.total === 1 ? '' : 's'} total</div>`;
          if (d.newest && d.newest.length) {
            const lbl = document.createElement('div');
            lbl.textContent = 'Newest';
            lbl.style.cssText = `margin-top:12px;font-size:11px;color:${T.muted}`;
            const row = document.createElement('div');
            row.style.cssText = 'display:flex;gap:6px;margin-top:6px;flex-wrap:wrap';
            d.newest.forEach((a) => {
              const link = document.createElement('a');
              link.href = a.url;
              link.appendChild(avatarEl(a, 30));
              row.appendChild(link);
            });
            box.append(lbl, row);
          }
        })
        .catch(() => box.remove());
    },
  });
}
