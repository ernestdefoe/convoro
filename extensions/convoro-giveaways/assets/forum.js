// Convoro extension: Giveaways (forum surface).
// Shipped prebuilt — no build step. Shows the active giveaway in the sidebar
// with a one-click Enter button. Uses live theme tokens (--c-*).

const c = window.Convoro;
function csrf() {
  const m = document.querySelector('meta[name="csrf-token"]');
  return m ? m.getAttribute('content') : '';
}

if (c && typeof c.registerSlot === 'function') {
  c.registerSlot('forum:sidebar', {
    ext: 'convoro-giveaways',
    order: -20,
    mount(el) {
      fetch('/api/ext/giveaways/active', { headers: { Accept: 'application/json' } })
        .then((r) => (r.ok ? r.json() : null))
        .then((d) => { if (d && d.giveaway) render(el, d.giveaway); })
        .catch(() => { /* silent */ });
    },
  });
}

function render(el, g) {
  const card = document.createElement('div');
  card.style.cssText = [
    'overflow:hidden', 'border-radius:var(--c-radius,12px)',
    'border:1px solid rgb(var(--c-border,230 232 240))',
    'background:rgb(var(--c-surface,255 255 255))',
    'box-shadow:0 1px 2px rgba(0,0,0,.04)', 'margin-bottom:16px',
  ].join(';');

  const head = document.createElement('div');
  head.style.cssText = 'display:flex;align-items:center;gap:8px;padding:12px 16px;background:rgb(var(--c-primary,91 91 214) / .10);border-bottom:1px solid rgb(var(--c-border,230 232 240))';
  head.innerHTML = '<span>🎁</span><b style="font-size:13px;text-transform:uppercase;letter-spacing:.04em;color:rgb(var(--c-primary-700,66 66 181))">Giveaway</b>';

  const body = document.createElement('div');
  body.style.cssText = 'padding:16px';

  const title = document.createElement('div');
  title.textContent = g.title;
  title.style.cssText = 'font-weight:800;color:rgb(var(--c-text,27 32 48));margin-bottom:2px';

  const prize = document.createElement('div');
  prize.textContent = '🏆 ' + g.prize;
  prize.style.cssText = 'font-size:14px;color:rgb(var(--c-text-2,74 81 104));margin-bottom:10px';

  const meta = document.createElement('div');
  meta.style.cssText = 'font-size:12px;color:rgb(var(--c-muted,138 144 166));margin-bottom:12px';
  const entryLabel = (n) => `${n} ${n === 1 ? 'entry' : 'entries'}`;
  meta.textContent = entryLabel(g.entries) + (g.endsAt ? ' · ends ' + new Date(g.endsAt).toLocaleDateString() : '');

  const btn = document.createElement('button');
  btn.type = 'button';
  btn.style.cssText = 'width:100%;border:0;border-radius:var(--c-radius-btn,9px);padding:10px;font-weight:700;cursor:pointer;background:rgb(var(--c-primary,91 91 214));color:#fff';

  function setEntered() {
    btn.textContent = '✓ You\'re entered';
    btn.disabled = true;
    btn.style.background = 'rgb(var(--c-border,230 232 240))';
    btn.style.color = 'rgb(var(--c-text-2,74 81 104))';
    btn.style.cursor = 'default';
  }

  if (!g.authed) {
    btn.textContent = 'Log in to enter';
    btn.addEventListener('click', () => { try { window.Convoro?.emit?.('auth:open', 'login'); } catch { /* */ } });
  } else if (g.entered) {
    setEntered();
  } else {
    btn.textContent = 'Enter giveaway';
    btn.addEventListener('click', () => {
      btn.disabled = true; btn.textContent = 'Entering…';
      fetch(`/api/ext/giveaways/${g.id}/enter`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
        credentials: 'same-origin',
      }).then((r) => r.json()).then((d) => {
        if (d.ok) { meta.textContent = entryLabel(d.entries) + (g.endsAt ? ' · ends ' + new Date(g.endsAt).toLocaleDateString() : ''); setEntered(); }
        else { btn.disabled = false; btn.textContent = 'Enter giveaway'; }
      }).catch(() => { btn.disabled = false; btn.textContent = 'Enter giveaway'; });
    });
  }

  body.appendChild(title);
  body.appendChild(prize);
  if (g.description) {
    const desc = document.createElement('div');
    desc.textContent = g.description;
    desc.style.cssText = 'font-size:13px;color:rgb(var(--c-text-2,74 81 104));margin-bottom:12px';
    body.appendChild(desc);
  }
  body.appendChild(meta);
  body.appendChild(btn);
  card.appendChild(head);
  card.appendChild(body);
  el.appendChild(card);
}
