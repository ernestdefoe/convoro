// Convoro extension: GDPR & Privacy — granular cookie consent (forum surface).
// Worldwide-aware: honors Global Privacy Control / Do-Not-Track (CCPA), keeps a
// persistent "Privacy choices / Do Not Sell or Share" link so consent can be
// changed anytime, logs proof of consent server-side, and emits `gdpr:consent`
// so other extensions can gate their analytics/marketing scripts.

const c = window.Convoro;
const KEY = 'convoro_consent';
let CFG = null;

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
// CCPA / ePrivacy: browser opt-out signals are legally binding — honor them.
function gpcSignal() {
  try {
    return navigator.globalPrivacyControl === true
      || navigator.doNotTrack === '1' || window.doNotTrack === '1';
  } catch { return false; }
}
function getConfig() {
  if (CFG) return Promise.resolve(CFG);
  return fetch('/api/ext/gdpr/config', { headers: { Accept: 'application/json' } })
    .then((r) => (r.ok ? r.json() : null))
    .then((cfg) => { CFG = cfg; return cfg; })
    .catch(() => null);
}
function openChoices() { getConfig().then((cfg) => { if (cfg) render(cfg, true); }); }
// Exposed so a privacy policy page / "Do Not Sell" link anywhere can re-open it.
window.convoroPrivacyChoices = openChoices;

if (c && typeof c.registerSlot === 'function') {
  c.registerSlot('forum:footer', {
    ext: 'convoro-gdpr',
    order: 100,
    mount(el) {
      const a = document.createElement('a');
      a.href = '#';
      a.textContent = 'Privacy choices';
      a.title = 'Manage cookies · Do Not Sell or Share My Personal Information';
      a.style.cssText = 'color:rgb(var(--c-muted,138 144 166));text-decoration:none;font-size:13px';
      a.addEventListener('click', (e) => { e.preventDefault(); openChoices(); });
      el.appendChild(a);

      if (!readConsent()) {
        if (gpcSignal()) {
          persist({ analytics: false, marketing: false, gpc: true });
        } else {
          getConfig().then((cfg) => { if (cfg) render(cfg, false); });
        }
      }
    },
  });
}

function render(cfg, forced) {
  if (!forced && readConsent()) return;
  const old = document.getElementById('convoro-consent');
  if (old) old.remove();
  const prior = readConsent() || {};

  const card = 'rgb(var(--c-surface, 255 255 255))';
  const text = 'rgb(var(--c-text, 27 32 48))';
  const muted = 'rgb(var(--c-muted, 138 144 166))';
  const line = 'rgb(var(--c-border, 230 232 240))';

  const wrap = document.createElement('div');
  wrap.id = 'convoro-consent';
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
  if (cfg.analytics) addToggle('analytics', 'Analytics — help us understand usage', !!prior.analytics, false);
  if (cfg.marketing) addToggle('marketing', 'Marketing — personalized content', !!prior.marketing, false);

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
    saveBtn.style.display = forced ? 'inline-block' : 'none';
    if (forced) prefs.style.display = 'flex';
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
  // CCPA notice when a "sale/share" category (marketing) is offered.
  if (cfg.marketing) {
    const ccpa = document.createElement('div');
    ccpa.textContent = 'California residents: choose “Reject non-essential” to opt out of the sale or sharing of your personal information.';
    ccpa.style.cssText = `color:${muted};font-size:12px;margin-bottom:12px`;
    wrap.appendChild(ccpa);
  }
  if (hasCategories) wrap.appendChild(prefs);
  wrap.appendChild(btns);
  document.body.appendChild(wrap);
}
