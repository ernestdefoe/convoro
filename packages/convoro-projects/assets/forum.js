// Convoro extension: Projects (forum surface).
// Shipped prebuilt — no build step. Shows the latest member projects in the
// sidebar with a link to the full /projects page. Uses live theme tokens.

const c = window.Convoro;

if (c && typeof c.registerSlot === 'function') {
  c.registerSlot('forum:sidebar', {
    ext: 'convoro-projects',
    order: -10,
    mount(el) {
      fetch('/api/ext/projects/latest', { headers: { Accept: 'application/json' } })
        .then((r) => (r.ok ? r.json() : null))
        .then((d) => { if (d && d.projects && d.projects.length) render(el, d.projects); })
        .catch(() => { /* silent */ });
    },
  });
}

function render(el, projects) {
  const card = document.createElement('div');
  card.style.cssText = [
    'overflow:hidden', 'border-radius:var(--c-radius,12px)',
    'border:1px solid rgb(var(--c-border,230 232 240))',
    'background:rgb(var(--c-surface,255 255 255))', 'margin-bottom:16px',
  ].join(';');

  const head = document.createElement('a');
  head.href = '/projects';
  head.style.cssText = 'display:flex;align-items:center;gap:8px;padding:12px 16px;border-bottom:1px solid rgb(var(--c-border,230 232 240));text-decoration:none;color:rgb(var(--c-text,27 32 48))';
  head.innerHTML = '<span>🚀</span><b style="font-size:13px;text-transform:uppercase;letter-spacing:.04em;flex:1">Projects</b><span style="font-size:12px;color:rgb(var(--c-primary,91 91 214))">All →</span>';
  card.appendChild(head);

  projects.forEach((p) => {
    const row = document.createElement('a');
    row.href = '/projects';
    row.style.cssText = 'display:flex;align-items:center;gap:10px;padding:10px 16px;text-decoration:none;color:rgb(var(--c-text-2,74 81 104));border-bottom:1px solid rgb(var(--c-border,230 232 240) / .5)';
    const thumb = document.createElement('span');
    thumb.style.cssText = 'width:34px;height:34px;flex:none;border-radius:8px;background:rgb(var(--c-surface-2,248 249 252)) center/cover;display:flex;align-items:center;justify-content:center;font-size:15px';
    if (p.image) thumb.style.backgroundImage = `url('${p.image}')`; else thumb.textContent = '🚀';
    const txt = document.createElement('span');
    txt.style.cssText = 'min-width:0;font-size:13px';
    txt.innerHTML = `<span style="display:block;font-weight:700;color:rgb(var(--c-text,27 32 48));white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${escapeHtml(p.title)}</span><span style="color:rgb(var(--c-muted,138 144 166))">by ${escapeHtml(p.author)}</span>`;
    row.appendChild(thumb);
    row.appendChild(txt);
    card.appendChild(row);
  });

  el.appendChild(card);
}

function escapeHtml(s) {
  return String(s || '').replace(/[&<>"]/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ch]));
}
