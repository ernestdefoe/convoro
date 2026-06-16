// Convoro theme — Aurora.
// Restyles the whole forum by remapping Convoro's --c-* design tokens to a deep
// night-sky palette, layering an animated aurora-borealis backdrop, frosted
// glass surfaces and soft glow, plus a 6-palette switcher in the header.
// Ships as plain JS (no build step) — injected before the app mounts.
(function () {
  'use strict';
  var c = window.Convoro || {};
  var STORAGE = 'convoro-aurora.palette';

  // Each palette: gradient stops c1..c4 (hex), warm accent, and the rgb triplets
  // for the tokens Convoro consumes as `rgb(var(--c-x))` (primary/accent/link),
  // plus the five drifting backdrop blob colours.
  var PALETTES = [
    { key: 'aurora', label: 'Aurora', c1: '#7c3aed', c2: '#4f46e5', c3: '#22d3ee', c4: '#14b8a6', accent: '#f472b6',
      p: '124 58 237', a: '244 114 182', l: '34 211 238',
      blobs: ['rgba(124,58,237,.70)', 'rgba(34,211,238,.60)', 'rgba(217,70,239,.55)', 'rgba(20,184,166,.55)', 'rgba(244,114,182,.50)'] },
    { key: 'sunset', label: 'Sunset', c1: '#f97316', c2: '#f43f5e', c3: '#d946ef', c4: '#7c3aed', accent: '#fbbf24',
      p: '249 115 22', a: '251 191 36', l: '217 70 239',
      blobs: ['rgba(249,115,22,.65)', 'rgba(244,63,94,.55)', 'rgba(217,70,239,.55)', 'rgba(124,58,237,.55)', 'rgba(251,191,36,.45)'] },
    { key: 'ocean', label: 'Ocean', c1: '#1e40af', c2: '#3b82f6', c3: '#06b6d4', c4: '#22d3ee', accent: '#67e8f9',
      p: '37 99 235', a: '103 232 249', l: '6 182 212',
      blobs: ['rgba(30,64,175,.70)', 'rgba(59,130,246,.55)', 'rgba(6,182,212,.55)', 'rgba(34,211,238,.50)', 'rgba(103,232,249,.40)'] },
    { key: 'forest', label: 'Forest', c1: '#15803d', c2: '#10b981', c3: '#22d3ee', c4: '#0ea5e9', accent: '#bef264',
      p: '16 185 129', a: '190 242 100', l: '34 211 238',
      blobs: ['rgba(21,128,61,.65)', 'rgba(16,185,129,.55)', 'rgba(34,211,238,.50)', 'rgba(14,165,233,.55)', 'rgba(190,242,100,.40)'] },
    { key: 'nebula', label: 'Nebula', c1: '#a855f7', c2: '#d946ef', c3: '#6366f1', c4: '#3b82f6', accent: '#f0abfc',
      p: '168 85 247', a: '240 171 252', l: '99 102 241',
      blobs: ['rgba(168,85,247,.70)', 'rgba(217,70,239,.55)', 'rgba(99,102,241,.55)', 'rgba(59,130,246,.55)', 'rgba(240,171,252,.45)'] },
    { key: 'ember', label: 'Ember', c1: '#b91c1c', c2: '#ea580c', c3: '#f59e0b', c4: '#fbbf24', accent: '#fda4af',
      p: '234 88 12', a: '253 164 175', l: '245 158 11',
      blobs: ['rgba(185,28,28,.65)', 'rgba(234,88,12,.55)', 'rgba(245,158,11,.50)', 'rgba(251,191,36,.45)', 'rgba(253,164,175,.40)'] },
  ];

  function paletteByKey(k) {
    for (var i = 0; i < PALETTES.length; i++) if (PALETTES[i].key === k) return PALETTES[i];
    return PALETTES[0];
  }

  function applyPalette(p) {
    var s = document.documentElement.style;
    s.setProperty('--aurora-c1', p.c1);
    s.setProperty('--aurora-c2', p.c2);
    s.setProperty('--aurora-c3', p.c3);
    s.setProperty('--aurora-c4', p.c4);
    s.setProperty('--aurora-accent', p.accent);
    for (var i = 0; i < 5; i++) s.setProperty('--aurora-blob-' + (i + 1), p.blobs[i]);
    // Convoro tokens (space-separated rgb triplets).
    s.setProperty('--c-primary', p.p);
    s.setProperty('--c-accent', p.a);
    s.setProperty('--c-link', p.l);
  }

  function storedKey() {
    try { return localStorage.getItem(STORAGE) || 'aurora'; } catch (e) { return 'aurora'; }
  }

  // Apply the saved palette immediately (before paint) to avoid a flash.
  applyPalette(paletteByKey(storedKey()));

  // ---- The stylesheet ----------------------------------------------------
  var CSS = [
    // Base palette vars + the gradient that follows the active palette.
    ":root{",
    "  --aurora-c1:#7c3aed;--aurora-c2:#4f46e5;--aurora-c3:#22d3ee;--aurora-c4:#14b8a6;--aurora-accent:#f472b6;",
    "  --aurora-blob-1:rgba(124,58,237,.70);--aurora-blob-2:rgba(34,211,238,.60);--aurora-blob-3:rgba(217,70,239,.55);--aurora-blob-4:rgba(20,184,166,.55);--aurora-blob-5:rgba(244,114,182,.50);",
    "  --c-grad:linear-gradient(120deg,var(--aurora-c1) 0%,var(--aurora-c2) 35%,var(--aurora-c3) 70%,var(--aurora-c4) 100%);",
    "  --c-radius:14px;--c-radius-btn:14px;",
    "  --aurora-glow-cool:0 0 24px rgba(34,211,238,.35),0 0 48px rgba(124,58,237,.25);",
    "}",
    // Light = soft lavender "Dawn".
    ":root,:root[data-theme='light']{",
    "  --c-bg:245 243 255;--c-surface:255 255 255;--c-surface-2:248 246 255;--c-border:226 222 245;",
    "  --c-text:30 27 75;--c-text-2:76 29 149;--c-muted:124 90 200;",
    "  --c-header-bg:rgb(255 255 255 / .72);--c-header-text:30 27 75;--aurora-blob-opacity:.4;--aurora-glass:255 255 255;",
    "}",
    // Dark = night sky.
    ":root[data-theme='dark']{",
    "  --c-bg:5 7 15;--c-surface:19 26 58;--c-surface-2:28 35 80;--c-border:64 74 124;",
    "  --c-text:229 231 255;--c-text-2:154 163 199;--c-muted:122 132 175;",
    "  --c-header-bg:rgb(11 16 36 / .6);--c-header-text:229 231 255;--aurora-blob-opacity:1;--aurora-glass:19 26 58;",
    "}",
    // Drifting aurora backdrop.
    "body::before,body::after{content:'';position:fixed;inset:-20%;z-index:-2;pointer-events:none;filter:blur(80px);opacity:var(--aurora-blob-opacity,1);will-change:transform,opacity;}",
    "body::before{background:radial-gradient(40% 50% at 20% 20%,var(--aurora-blob-1) 0%,transparent 60%),radial-gradient(35% 45% at 80% 10%,var(--aurora-blob-2) 0%,transparent 60%),radial-gradient(45% 55% at 60% 80%,var(--aurora-blob-3) 0%,transparent 60%);animation:aurora-drift 18s ease-in-out infinite;}",
    "body::after{background:radial-gradient(40% 45% at 80% 70%,var(--aurora-blob-4) 0%,transparent 60%),radial-gradient(30% 35% at 10% 70%,var(--aurora-blob-5) 0%,transparent 60%);animation:aurora-drift 24s ease-in-out infinite reverse;}",
    "@keyframes aurora-drift{0%{transform:translate3d(0,0,0) rotate(0)}50%{transform:translate3d(-3%,2%,0) rotate(2deg)}100%{transform:translate3d(0,0,0) rotate(0)}}",
    // Starfield (dark only).
    ":root[data-theme='dark'] body{background:rgb(var(--c-bg));}",
    ":root[data-theme='dark']::before{content:'';position:fixed;inset:0;z-index:-1;pointer-events:none;opacity:.5;background-image:radial-gradient(1px 1px at 12% 18%,rgba(255,255,255,.6) 0,transparent 100%),radial-gradient(1px 1px at 78% 32%,rgba(255,255,255,.45) 0,transparent 100%),radial-gradient(1px 1px at 42% 76%,rgba(255,255,255,.5) 0,transparent 100%),radial-gradient(1px 1px at 88% 84%,rgba(255,255,255,.4) 0,transparent 100%),radial-gradient(1px 1px at 22% 60%,rgba(255,255,255,.35) 0,transparent 100%);}",
    // Frosted glass on the token-driven surfaces.
    ".bg-surface{background:rgb(var(--aurora-glass,var(--c-surface)) / .62)!important;backdrop-filter:blur(16px) saturate(160%);-webkit-backdrop-filter:blur(16px) saturate(160%);}",
    ".bg-surface-2{background:rgb(var(--c-surface-2) / .55)!important;}",
    "header{backdrop-filter:blur(22px) saturate(160%);-webkit-backdrop-filter:blur(22px) saturate(160%);}",
    // Gradient + glow on primary buttons.
    ".bg-primary{background-image:var(--c-grad)!important;background-color:transparent!important;box-shadow:0 8px 22px -10px rgba(var(--c-primary) / .7);}",
    ".bg-primary:hover{box-shadow:var(--aurora-glow-cool);}",
    // Avatars get a soft cyan ring that warms on hover.
    ".av,[class*='av-g']{transition:box-shadow .25s,transform .25s;}",
    // Scrollbar + selection.
    "::-webkit-scrollbar{width:10px;height:10px;}::-webkit-scrollbar-track{background:rgb(var(--c-surface));}::-webkit-scrollbar-thumb{background:var(--c-grad);border-radius:999px;border:2px solid rgb(var(--c-bg));}",
    "::selection{background:rgba(124,58,237,.55);color:#fff;}",
    // Aurora palette button.
    ".aurora-pal{position:relative;width:34px;height:34px;border-radius:999px;border:1px solid rgb(var(--c-border));background:transparent;cursor:pointer;display:inline-grid;place-items:center;transition:transform .2s,box-shadow .2s;}",
    ".aurora-pal::before{content:'';width:18px;height:18px;border-radius:999px;background:var(--c-grad);box-shadow:var(--aurora-glow-cool);}",
    ".aurora-pal:hover{transform:translateY(-1px);box-shadow:var(--aurora-glow-cool);}",
    ".aurora-pop{position:absolute;top:calc(100% + 10px);right:0;z-index:1200;width:236px;padding:12px;border-radius:18px;border:1px solid rgb(var(--c-border));background:rgb(var(--aurora-glass,var(--c-surface)) / .92);backdrop-filter:blur(28px) saturate(160%);-webkit-backdrop-filter:blur(28px) saturate(160%);box-shadow:0 24px 64px -20px rgba(0,0,0,.8);}",
    ".aurora-pop h6{margin:0 0 10px 2px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:rgb(var(--c-muted));}",
    ".aurora-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;}",
    ".aurora-sw{border:1px solid rgb(var(--c-border));border-radius:12px;padding:7px 6px;background:transparent;cursor:pointer;display:flex;flex-direction:column;gap:6px;align-items:center;transition:transform .15s,border-color .15s;}",
    ".aurora-sw:hover{transform:translateY(-2px);border-color:rgb(var(--c-text-2));}",
    ".aurora-sw.on{border-color:transparent;background:rgb(var(--c-surface-2));box-shadow:0 0 0 2px rgb(var(--c-primary));}",
    ".aurora-sw i{display:block;width:100%;height:26px;border-radius:7px;box-shadow:inset 0 0 0 1px rgba(255,255,255,.1);}",
    ".aurora-sw span{font-size:12px;font-weight:600;color:rgb(var(--c-text-2));}",
    "@media (prefers-reduced-motion: reduce){body::before,body::after{animation:none!important;}}",
  ].join('\n');

  function injectStyle() {
    if (document.getElementById('convoro-aurora-css')) return;
    var st = document.createElement('style');
    st.id = 'convoro-aurora-css';
    st.textContent = CSS;
    document.head.appendChild(st);
  }
  injectStyle();

  // ---- Palette switcher button in the header -----------------------------
  if (typeof c.registerSlot === 'function') {
    c.registerSlot('header:end', {
      ext: 'convoro-aurora', label: 'Aurora palette', order: 5,
      mount: function (el) {
        var current = storedKey();
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'aurora-pal';
        btn.setAttribute('aria-label', 'Theme palette');
        btn.title = 'Theme palette';
        var pop = null;

        function close() {
          if (pop) { pop.remove(); pop = null; document.removeEventListener('click', onDoc, true); }
        }
        function onDoc(e) { if (pop && !pop.contains(e.target) && e.target !== btn) close(); }
        function open() {
          pop = document.createElement('div');
          pop.className = 'aurora-pop';
          var h = document.createElement('h6');
          h.textContent = (c.t ? c.t('Palette') : 'Palette');
          pop.appendChild(h);
          var grid = document.createElement('div');
          grid.className = 'aurora-grid';
          PALETTES.forEach(function (p) {
            var sw = document.createElement('button');
            sw.type = 'button';
            sw.className = 'aurora-sw' + (p.key === current ? ' on' : '');
            var i = document.createElement('i');
            i.style.background = 'linear-gradient(120deg,' + p.c1 + ' 0%,' + p.c2 + ' 35%,' + p.c3 + ' 70%,' + p.c4 + ' 100%)';
            var sp = document.createElement('span');
            sp.textContent = p.label;
            sw.appendChild(i); sw.appendChild(sp);
            sw.addEventListener('click', function (ev) {
              ev.stopPropagation();
              current = p.key;
              applyPalette(p);
              try { localStorage.setItem(STORAGE, p.key); } catch (e) {}
              close();
            });
            grid.appendChild(sw);
          });
          pop.appendChild(grid);
          btn.parentNode.style.position = 'relative';
          btn.parentNode.appendChild(pop);
          setTimeout(function () { document.addEventListener('click', onDoc, true); }, 0);
        }

        btn.addEventListener('click', function (e) {
          e.stopPropagation();
          if (pop) close(); else open();
        });
        el.appendChild(btn);
        return function () { close(); btn.remove(); };
      },
    });
  }
})();
