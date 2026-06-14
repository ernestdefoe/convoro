// Convoro extension: Social Login (forum surface).
// Shipped prebuilt — no build step. Renders the configured provider buttons
// into the core `auth:providers` slot inside the sign-in dialog. The buttons
// are plain links to the OAuth redirect routes the extension registers.

const c = window.Convoro;

const LABEL = { github: 'GitHub', google: 'Google', facebook: 'Facebook', x: 'X' };
const GLYPH = {
  github:
    '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 .5A12 12 0 0 0 8.2 23.9c.6.1.8-.3.8-.6v-2c-3.3.7-4-1.6-4-1.6-.6-1.4-1.3-1.8-1.3-1.8-1.1-.7.1-.7.1-.7 1.2.1 1.8 1.2 1.8 1.2 1.1 1.8 2.8 1.3 3.5 1 .1-.8.4-1.3.8-1.6-2.7-.3-5.5-1.3-5.5-5.9 0-1.3.5-2.4 1.2-3.2 0-.3-.5-1.5.2-3.2 0 0 1-.3 3.3 1.2a11.5 11.5 0 0 1 6 0C17 4.7 18 5 18 5c.7 1.7.2 2.9.1 3.2.8.8 1.2 1.9 1.2 3.2 0 4.6-2.8 5.6-5.5 5.9.4.4.8 1.1.8 2.2v3.3c0 .3.2.7.8.6A12 12 0 0 0 12 .5z"/></svg>',
  google: '<span style="font-weight:900;font-size:16px">G</span>',
  facebook: '<span style="font-weight:900;font-size:16px">f</span>',
  x: '<span style="font-weight:900;font-size:16px">𝕏</span>',
};

let cache = null;
function loadProviders() {
  if (cache) return cache;
  cache = fetch('/api/ext/social-login/providers', { headers: { Accept: 'application/json' } })
    .then((r) => (r.ok ? r.json() : { providers: [] }))
    .then((d) => (Array.isArray(d.providers) ? d.providers : []))
    .catch(() => []);
  return cache;
}

function render(el, providers) {
  if (!providers.length) return;
  el.innerHTML = '';

  const wrap = document.createElement('div');
  wrap.style.cssText = 'margin-bottom:20px';

  const grid = document.createElement('div');
  grid.style.cssText =
    'display:grid;gap:8px;grid-template-columns:' + (providers.length > 1 ? 'repeat(2,1fr)' : '1fr');

  providers.forEach((p) => {
    const a = document.createElement('a');
    a.href = `/auth/${p}/redirect`;
    a.setAttribute('aria-label', `Continue with ${LABEL[p] || p}`);
    a.style.cssText = [
      'display:flex', 'align-items:center', 'justify-content:center', 'gap:8px',
      'padding:10px 12px', 'border-radius:10px', 'font-size:14px', 'font-weight:600',
      'text-decoration:none', 'cursor:pointer',
      'border:1px solid rgb(var(--c-border, 230 232 240))',
      'background:rgb(var(--c-surface-2, 247 248 252))',
      'color:rgb(var(--c-text-2, 60 66 88))',
    ].join(';');
    a.innerHTML = `${GLYPH[p] || ''}<span>${LABEL[p] || p}</span>`;
    grid.appendChild(a);
  });

  const divider = document.createElement('div');
  divider.style.cssText =
    'display:flex;align-items:center;gap:12px;margin-top:20px;font-size:12px;color:rgb(var(--c-muted, 138 144 166))';
  divider.innerHTML =
    '<span style="height:1px;flex:1;background:rgb(var(--c-border, 230 232 240))"></span>or with email<span style="height:1px;flex:1;background:rgb(var(--c-border, 230 232 240))"></span>';

  wrap.appendChild(grid);
  wrap.appendChild(divider);
  el.appendChild(wrap);
}

function csrf() {
  const m = document.querySelector('meta[name="csrf-token"]');
  return m ? m.getAttribute('content') : '';
}

// "Connected accounts" panel for the Settings page (signed-in members).
function renderSettings(el, status) {
  const providers = status.providers || [];
  if (!providers.length) return;
  el.innerHTML = '';

  const card = document.createElement('div');
  card.style.cssText =
    'border-radius:var(--c-radius,12px);border:1px solid rgb(var(--c-border,230 232 240));background:rgb(var(--c-surface,255 255 255));padding:24px';

  const h = document.createElement('h2');
  h.textContent = 'Connected accounts';
  h.style.cssText = 'font-size:18px;font-weight:800;margin:0;color:rgb(var(--c-text,27 32 48))';
  const sub = document.createElement('p');
  sub.textContent = 'Link one or more providers to sign in with one click.';
  sub.style.cssText = 'font-size:13px;margin:4px 0 8px;color:rgb(var(--c-muted,138 144 166))';
  card.appendChild(h);
  card.appendChild(sub);

  // Every connected provider — a member may link several at once. Falls back to
  // the legacy single `provider` field if talking to an older server.
  const linkedSet = new Set(status.linked || (status.provider ? [status.provider] : []));

  providers.forEach((p) => {
    const linked = linkedSet.has(p);
    const row = document.createElement('div');
    row.style.cssText =
      'display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 0;border-top:1px solid rgb(var(--c-border,230 232 240))';

    const left = document.createElement('div');
    left.style.cssText =
      'display:flex;align-items:center;gap:10px;font-weight:600;color:rgb(var(--c-text-2,60 66 88))';
    left.innerHTML =
      `<span style="display:inline-flex">${GLYPH[p] || ''}</span><span>${LABEL[p] || p}</span>` +
      (linked ? '<span style="font-size:12px;color:#16a34a;font-weight:700">● Connected</span>' : '');
    row.appendChild(left);

    if (linked) {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.textContent = 'Disconnect';
      btn.style.cssText =
        'border:1px solid rgb(var(--c-border,230 232 240));background:transparent;color:#dc2626;border-radius:8px;padding:7px 14px;font-size:13px;font-weight:600;cursor:pointer';
      btn.addEventListener('click', () => {
        if (!confirm(`Disconnect ${LABEL[p] || p}? Make sure you have a password set so you can still sign in.`)) return;
        fetch(`/auth/${p}/disconnect`, {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
          credentials: 'same-origin',
        }).then(() => location.reload());
      });
      row.appendChild(btn);
    } else {
      const a = document.createElement('a');
      a.href = `/auth/${p}/redirect?link=1`;
      a.textContent = 'Connect';
      a.style.cssText =
        'border:1px solid rgb(var(--c-border,230 232 240));background:rgb(var(--c-surface-2,247 248 252));color:rgb(var(--c-text-2,60 66 88));border-radius:8px;padding:7px 14px;font-size:13px;font-weight:600;text-decoration:none';
      row.appendChild(a);
    }
    card.appendChild(row);
  });

  el.appendChild(card);
}

if (c && typeof c.registerSlot === 'function') {
  c.registerSlot('auth:providers', {
    ext: 'convoro-social-login',
    order: 10,
    mount(el) {
      loadProviders().then((providers) => render(el, providers));
    },
  });

  c.registerSlot('settings:account', {
    ext: 'convoro-social-login',
    order: 20,
    mount(el) {
      fetch('/api/ext/social-login/status', { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
        .then((r) => (r.ok ? r.json() : null))
        .then((s) => { if (s) renderSettings(el, s); })
        .catch(() => { /* silent */ });
    },
  });
}
