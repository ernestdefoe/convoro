// Convoro extension: GDPR & Privacy — cookie consent banner (forum surface).
// Shipped prebuilt — no build step. Renders a granular, accessible consent
// banner into the `forum:footer` slot, persists the choice, logs proof of
// consent server-side, and emits `gdpr:consent` so other extensions can gate
// their analytics/marketing scripts.

const c = window.Convoro;
const KEY = 'convoro_consent';

function readConsent() {
  try { return JSON.parse(localStorage.getItem(KEY) || 'null'); } catch { return null; }
}
function csrf() {
  const m = document.querySelector('meta[name="csrf-token"]');
  return m ? m.getAttribute('content') : '';
}
function persist(choice) {
  try { localStorage.setItem(KEY, JSON.stringify({ ...choice, at: Date.now() })); } catch { /* ignore */ }
  if (c && typeof c.emit === 'function') c.emit('gdpr:consent', choice);
  fetch('/privacy/consent', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
    credentials: 'same-origin',
    body: JSON.stringify(choice),
  }).catch(() => { /* silent */ });
}

if (c && typeof c.registerSlot === 'function' && !readConsent()) {
  c.registerSlot('forum:footer', {
    ext: 'convoro-gdpr',
    order: 100,
    mount(el) {
      if (readConsent()) return;

      fetch('/api/ext/gdpr/config', { headers: { Accept: 'application/json' } })
        .then((r) => (r.ok ? r.json() : null))
        .then((cfg) => {
          if (!cfg) return;
          render(el, cfg);
        })
        .catch(() => { /* silent */ });
    },
  });
}

function render(el, cfg) {
  const card = 'rgb(var(--c-surface, 255 255 255))';
  const text = 'rgb(var(--c-text, 27 32 48))';
  const muted = 'rgb(var(--c-muted, 138 144 166))';
  const line = 'rgb(var(--c-border, 230 232 240))';

  const wrap = document.createElement('div');
  wrap.setAttribute('role', 'dialog');
  wrap.setAttribute('aria-label', 'Cookie consent');
  wrap.style.cssText = [
    'position:fixed', 'left:16px', 'right:16px', 'bottom:16px', 'z-index:90',
    'max-width:560px', 'margin:0 auto', 'padding:18px 20px',
    `background:${card}`, `color:${text}`, `border:1px solid ${line}`,
    'border-radius:var(--c-radius,12px)', 'box-shadow:0 12px 40px rgba(0,0,0,.25)',
    'font-size:14px', 'line-height:1.5',
  ].join(';');

  const h = document.createElement('div');
  h.textContent = cfg.heading;
  h.style.cssText = 'font-weight:800;font-size:15px;margin-bottom:4px';

  const p = document.createElement('div');
  p.textContent = cfg.message;
  p.style.cssText = `color:${muted};margin-bottom:14px`;

  // Optional granular toggles.
  const prefs = document.createElement('div');
  prefs.style.cssText = 'display:none;flex-direction:column;gap:8px;margin-bottom:14px';
  const toggles = {};
  const addToggle = (key, label, on, disabled) => {
    const row = document.createElement('label');
    row.style.cssText = `display:flex;align-items:center;gap:10px;color:${text}`;
    const cb = document.createElement('input');
    cb.type = 'checkbox'; cb.checked = on; cb.disabled = !!disabled;
    const span = document.createElement('span');
    span.textContent = label;
    row.appendChild(cb); row.appendChild(span);
    prefs.appendChild(row);
    toggles[key] = cb;
  };
  addToggle('necessary', 'Strictly necessary (always on)', true, true);
  if (cfg.analytics) addToggle('analytics', 'Analytics — help us understand usage', false, false);
  if (cfg.marketing) addToggle('marketing', 'Marketing — personalized content', false, false);

  const btns = document.createElement('div');
  btns.style.cssText = 'display:flex;flex-wrap:wrap;gap:10px;align-items:center';

  const mkBtn = (label, primary) => {
    const b = document.createElement('button');
    b.type = 'button'; b.textContent = label;
    b.style.cssText = primary
      ? 'border:0;border-radius:9px;padding:9px 16px;font-weight:700;cursor:pointer;background:rgb(var(--c-primary,91 91 214));color:#fff'
      : `border:1px solid ${line};border-radius:9px;padding:9px 16px;font-weight:700;cursor:pointer;background:transparent;color:${text}`;
    return b;
  };

  const finish = (choice) => { persist(choice); wrap.remove(); };

  const acceptAll = mkBtn('Accept all', true);
  acceptAll.addEventListener('click', () => finish({ analytics: !!cfg.analytics, marketing: !!cfg.marketing }));

  const rejectAll = mkBtn('Reject non-essential', false);
  rejectAll.addEventListener('click', () => finish({ analytics: false, marketing: false }));

  const hasCategories = cfg.analytics || cfg.marketing;
  const prefsBtn = mkBtn('Preferences', false);
  let saveBtn = null;
  if (hasCategories) {
    prefsBtn.addEventListener('click', () => {
      const showing = prefs.style.display === 'flex';
      prefs.style.display = showing ? 'none' : 'flex';
      if (saveBtn) saveBtn.style.display = showing ? 'none' : 'inline-block';
    });
    saveBtn = mkBtn('Save choices', true);
    saveBtn.style.display = 'none';
    saveBtn.addEventListener('click', () => finish({
      analytics: !!(toggles.analytics && toggles.analytics.checked),
      marketing: !!(toggles.marketing && toggles.marketing.checked),
    }));
  }

  const link = document.createElement('a');
  link.href = cfg.privacyUrl; link.textContent = 'Privacy';
  link.style.cssText = `margin-left:auto;color:${muted};text-decoration:underline;font-size:13px`;

  btns.appendChild(acceptAll);
  btns.appendChild(rejectAll);
  if (hasCategories) { btns.appendChild(prefsBtn); btns.appendChild(saveBtn); }
  btns.appendChild(link);

  wrap.appendChild(h);
  wrap.appendChild(p);
  if (hasCategories) wrap.appendChild(prefs);
  wrap.appendChild(btns);
  document.body.appendChild(wrap);
}
