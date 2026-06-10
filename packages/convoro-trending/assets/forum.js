// Convoro widget: Trending Topics (forum sidebar).
const c = window.Convoro;
const T = {
  surface: 'rgb(var(--c-surface,255 255 255))', surface2: 'rgb(var(--c-surface-2,248 249 252))',
  ink: 'rgb(var(--c-text,27 32 48))', ink2: 'rgb(var(--c-text-2,74 81 104))', muted: 'rgb(var(--c-muted,138 144 166))',
  line: 'rgb(var(--c-border,230 232 240))', primary: 'rgb(var(--c-primary,91 91 214))',
};

function card(title) {
  const box = document.createElement('div');
  box.style.cssText = `margin-top:20px;border:1px solid ${T.line};background:${T.surface};border-radius:var(--c-radius,12px);box-shadow:0 1px 2px rgba(0,0,0,.05);overflow:hidden`;
  const h = document.createElement('h4');
  h.textContent = title;
  h.style.cssText = `margin:0;padding:12px 16px;border-bottom:1px solid ${T.line};font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.03em;color:${T.muted}`;
  box.appendChild(h);
  return box;
}

if (c && typeof c.registerSlot === 'function') {
  c.registerSlot('forum:sidebar', {
    ext: 'convoro-trending',
    order: 10,
    mount(el) {
      const box = card('🔥 Trending');
      const list = document.createElement('div');
      list.style.cssText = 'padding:6px';
      box.appendChild(list);
      el.appendChild(box);

      fetch('/api/ext/trending', { headers: { Accept: 'application/json' } })
        .then((r) => (r.ok ? r.json() : []))
        .then((items) => {
          if (!items.length) { box.remove(); return; }
          items.forEach((t, i) => {
            const a = document.createElement('a');
            a.href = t.url;
            a.style.cssText = `display:flex;gap:10px;align-items:flex-start;padding:9px 10px;border-radius:9px;text-decoration:none;color:${T.ink}`;
            a.onmouseenter = () => (a.style.background = T.surface2);
            a.onmouseleave = () => (a.style.background = 'transparent');
            const rank = document.createElement('span');
            rank.textContent = i + 1;
            rank.style.cssText = `flex:0 0 18px;font-weight:800;color:${T.primary};font-size:13px`;
            const wrap = document.createElement('span');
            wrap.style.cssText = 'min-width:0';
            const ttl = document.createElement('span');
            ttl.textContent = t.title;
            ttl.style.cssText = 'display:block;font-size:13px;font-weight:600;line-height:1.3;overflow:hidden;text-overflow:ellipsis;white-space:nowrap';
            const meta = document.createElement('span');
            meta.textContent = `${t.replies} repl${t.replies === 1 ? 'y' : 'ies'}` + (t.cat ? ` · ${t.cat}` : '');
            meta.style.cssText = `font-size:11px;color:${T.muted}`;
            wrap.append(ttl, meta);
            a.append(rank, wrap);
            list.appendChild(a);
          });
        })
        .catch(() => box.remove());
    },
  });
}
