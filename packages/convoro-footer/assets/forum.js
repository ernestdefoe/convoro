/*
 * Convoro Footer Builder — forum surface (vanilla JS, shipped prebuilt).
 *
 * Renders an admin-configured multi-column site footer into the `forum:footer`
 * slot plus an optional back-to-top button. Everything is themed with the live
 * --c-* design tokens, so it tracks the active theme (incl. dark mode) for free.
 */
(function () {
  var c = window.Convoro;
  if (!c || typeof c.registerSlot !== 'function') return;

  // Curated single-path social/brand glyphs (24×24, fill=currentColor).
  var ICONS = {
    website: 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm6.93 6h-2.95a15.6 15.6 0 0 0-1.38-3.56A8.03 8.03 0 0 1 18.93 8ZM12 4c.83 1.2 1.48 2.54 1.91 3.96h-3.82A13.7 13.7 0 0 1 12 4ZM4.26 14a7.96 7.96 0 0 1 0-4h3.38a16.6 16.6 0 0 0 0 4H4.26Zm.81 2h2.95c.34 1.27.81 2.48 1.38 3.56A8.03 8.03 0 0 1 5.07 16Zm2.95-8H5.07a8.03 8.03 0 0 1 4.33-3.56A15.6 15.6 0 0 0 8.02 8ZM12 20c-.83-1.2-1.48-2.54-1.91-3.96h3.82A13.7 13.7 0 0 1 12 20Zm2.34-6H9.66a14.7 14.7 0 0 1 0-4h4.68a14.7 14.7 0 0 1 0 4Zm.26 5.56c.57-1.08 1.04-2.29 1.38-3.56h2.95a8.03 8.03 0 0 1-4.33 3.56ZM16.36 14a16.6 16.6 0 0 0 0-4h3.38a7.96 7.96 0 0 1 0 4h-3.38Z',
    x: 'M18.24 2H21.5l-7.5 8.57L22.5 22h-6.9l-5.4-7.06L4 22H.74l8.02-9.17L1.5 2h7.06l4.88 6.45L18.24 2Zm-1.2 18h1.82L7.05 3.9H5.1L17.03 20Z',
    github: 'M12 2C6.48 2 2 6.58 2 12.25c0 4.53 2.87 8.37 6.84 9.73.5.1.68-.22.68-.49v-1.7c-2.78.62-3.37-1.36-3.37-1.36-.45-1.18-1.11-1.5-1.11-1.5-.91-.63.07-.62.07-.62 1 .07 1.53 1.06 1.53 1.06.9 1.57 2.36 1.12 2.94.86.09-.66.35-1.12.63-1.38-2.22-.26-4.55-1.14-4.55-5.07 0-1.12.39-2.03 1.03-2.75-.1-.26-.45-1.3.1-2.7 0 0 .84-.28 2.75 1.05A9.3 9.3 0 0 1 12 6.84c.85 0 1.71.12 2.51.34 1.91-1.33 2.75-1.05 2.75-1.05.55 1.4.2 2.44.1 2.7.64.72 1.03 1.63 1.03 2.75 0 3.94-2.34 4.8-4.57 5.06.36.32.68.94.68 1.9v2.82c0 .27.18.6.69.49A10.02 10.02 0 0 0 22 12.25C22 6.58 17.52 2 12 2Z',
    discord: 'M20.32 4.37A19.8 19.8 0 0 0 15.45 3a13.6 13.6 0 0 0-.62 1.27 18.3 18.3 0 0 0-5.66 0A13.6 13.6 0 0 0 8.55 3a19.7 19.7 0 0 0-4.88 1.37C.56 8.98-.28 13.48.14 17.91a19.9 19.9 0 0 0 6.07 3.06c.49-.67.93-1.38 1.3-2.13-.71-.27-1.4-.6-2.04-.99.17-.13.34-.26.5-.4a14.2 14.2 0 0 0 12.06 0c.16.14.33.27.5.4-.65.39-1.34.72-2.05.99.37.75.81 1.46 1.3 2.13a19.9 19.9 0 0 0 6.07-3.06c.5-5.18-.84-9.64-3.53-13.54ZM8.02 15.18c-1.18 0-2.15-1.09-2.15-2.43 0-1.34.95-2.43 2.15-2.43 1.2 0 2.17 1.1 2.15 2.43 0 1.34-.95 2.43-2.15 2.43Zm7.96 0c-1.18 0-2.15-1.09-2.15-2.43 0-1.34.95-2.43 2.15-2.43 1.2 0 2.17 1.1 2.15 2.43 0 1.34-.95 2.43-2.15 2.43Z',
    youtube: 'M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.5 12 3.5 12 3.5s-7.5 0-9.4.6A3 3 0 0 0 .5 6.2 31 31 0 0 0 0 12a31 31 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.1c1.9.6 9.4.6 9.4.6s7.5 0 9.4-.6a3 3 0 0 0 2.1-2.1A31 31 0 0 0 24 12a31 31 0 0 0-.5-5.8ZM9.6 15.6V8.4l6.3 3.6-6.3 3.6Z',
    facebook: 'M24 12.07C24 5.4 18.63 0 12 0S0 5.4 0 12.07C0 18.1 4.39 23.1 10.13 24v-8.44H7.08v-3.49h3.05V9.41c0-3.02 1.79-4.69 4.53-4.69 1.31 0 2.68.24 2.68.24v2.97h-1.51c-1.49 0-1.96.93-1.96 1.89v2.25h3.33l-.53 3.49h-2.8V24C19.61 23.1 24 18.1 24 12.07Z',
    instagram: 'M12 2.16c3.2 0 3.58.01 4.85.07 1.17.05 1.8.25 2.23.41.56.22.96.48 1.38.9.42.42.68.82.9 1.38.16.42.36 1.06.41 2.23.06 1.27.07 1.65.07 4.85s-.01 3.58-.07 4.85c-.05 1.17-.25 1.8-.41 2.23-.22.56-.48.96-.9 1.38-.42.42-.82.68-1.38.9-.42.16-1.06.36-2.23.41-1.27.06-1.65.07-4.85.07s-3.58-.01-4.85-.07c-1.17-.05-1.8-.25-2.23-.41a3.7 3.7 0 0 1-1.38-.9 3.7 3.7 0 0 1-.9-1.38c-.16-.42-.36-1.06-.41-2.23C2.17 15.58 2.16 15.2 2.16 12s.01-3.58.07-4.85c.05-1.17.25-1.8.41-2.23.22-.56.48-.96.9-1.38.42-.42.82-.68 1.38-.9.42-.16 1.06-.36 2.23-.41C8.42 2.17 8.8 2.16 12 2.16ZM12 0C8.74 0 8.33.01 7.05.07 5.78.13 4.9.33 4.14.63c-.79.3-1.46.72-2.12 1.38C1.36 2.67.94 3.34.63 4.14.33 4.9.13 5.78.07 7.05.01 8.33 0 8.74 0 12s.01 3.67.07 4.95c.06 1.27.26 2.15.56 2.91.3.8.72 1.47 1.38 2.13.66.66 1.33 1.08 2.12 1.38.76.3 1.64.5 2.91.56 1.28.06 1.69.07 4.95.07s3.67-.01 4.95-.07c1.27-.06 2.15-.26 2.91-.56.8-.3 1.47-.72 2.13-1.38.66-.66 1.08-1.33 1.38-2.13.3-.76.5-1.64.56-2.91.06-1.28.07-1.69.07-4.95s-.01-3.67-.07-4.95c-.06-1.27-.26-2.15-.56-2.91-.3-.8-.72-1.47-1.38-2.13a5.7 5.7 0 0 0-2.13-1.38c-.76-.3-1.64-.5-2.91-.56C15.67.01 15.26 0 12 0Zm0 5.84A6.16 6.16 0 1 0 12 18.16 6.16 6.16 0 0 0 12 5.84Zm0 10.16A4 4 0 1 1 12 8a4 4 0 0 1 0 8Zm7.85-10.4a1.44 1.44 0 1 1-2.88 0 1.44 1.44 0 0 1 2.88 0Z',
    linkedin: 'M20.45 20.45h-3.56v-5.57c0-1.33-.02-3.04-1.85-3.04-1.85 0-2.14 1.45-2.14 2.94v5.67H9.35V9h3.41v1.56h.05c.48-.9 1.63-1.85 3.36-1.85 3.6 0 4.27 2.37 4.27 5.45v6.29ZM5.34 7.43a2.06 2.06 0 1 1 0-4.13 2.06 2.06 0 0 1 0 4.13ZM7.12 20.45H3.56V9h3.56v11.45ZM22.22 0H1.77C.79 0 0 .77 0 1.73v20.54C0 23.23.79 24 1.77 24h20.45c.98 0 1.78-.77 1.78-1.73V1.73C24 .77 23.2 0 22.22 0Z',
    mastodon: 'M23.27 5.33c-.36-2.65-2.7-4.74-5.47-5.15-.47-.07-2.24-.18-6.01-.18h-.09c-3.77 0-4.58.11-5.05.18C3.95.58 1.49 2.46.9 5.14.61 6.46.58 7.93.63 9.27c.08 1.92.1 3.84.27 5.75.12 1.27.34 2.53.65 3.77.59 2.29 2.85 4.2 5.06 4.97 2.37.81 4.92.94 7.36.39.27-.06.53-.13.8-.21.6-.19 1.3-.4 1.81-.78a.06.06 0 0 0 .03-.05v-1.86a.05.05 0 0 0-.07-.05c-1.55.37-3.13.55-4.72.55-2.74 0-3.48-1.3-3.69-1.84a5.7 5.7 0 0 1-.32-1.45.05.05 0 0 1 .06-.06c1.52.37 3.08.55 4.64.55.38 0 .75 0 1.13-.01 1.57-.04 3.22-.12 4.76-.42l.11-.03c2.43-.47 4.75-1.93 4.99-5.64.01-.15.03-1.53.03-1.68.01-.51.17-3.62-.02-5.53ZM19.4 14.5h-2.4V8.6c0-1.24-.52-1.87-1.58-1.87-1.16 0-1.74.75-1.74 2.24v3.24h-2.39V8.97c0-1.49-.58-2.24-1.74-2.24-1.05 0-1.58.63-1.58 1.87v5.9H5.56V8.42c0-1.24.32-2.22.95-2.95.65-.72 1.5-1.1 2.56-1.1 1.22 0 2.15.47 2.76 1.4l.6 1 .6-1c.61-.93 1.54-1.4 2.76-1.4 1.05 0 1.9.38 2.56 1.1.63.73.95 1.71.95 2.95v6.08Z',
    twitch: 'M2.15 0 .55 4.17v17.34h5.92V24h3.32l2.48-2.49h4.8L23.45 16V0H2.15Zm19.07 14.93-3.32 3.32h-5.07l-2.48 2.48v-2.48H5.92V2.21h15.3v12.72ZM17.9 6.34v6.16h-2.21V6.34h2.21Zm-5.53 0v6.16h-2.21V6.34h2.21Z',
    tiktok: 'M16.6 5.82A4.28 4.28 0 0 1 15.54 3h-3.09v12.4a2.59 2.59 0 0 1-2.59 2.5 2.59 2.59 0 0 1-2.59-2.59 2.59 2.59 0 0 1 3.4-2.46V9.7a5.66 5.66 0 0 0-.81-.06A5.69 5.69 0 0 0 4.18 15.3a5.69 5.69 0 0 0 11.38 0V9.01a7.35 7.35 0 0 0 4.29 1.37V7.3a4.28 4.28 0 0 1-3.25-1.48Z',
    rss: 'M6.18 17.82a2.18 2.18 0 1 1-4.36 0 2.18 2.18 0 0 1 4.36 0ZM2 8.36v2.91c5.39 0 9.77 4.38 9.77 9.77h2.91c0-7-5.68-12.68-12.68-12.68Zm0-5.45v2.91c8.39 0 15.23 6.83 15.23 15.23h2.91C20.14 11.45 12.05 3.36 2 2.91Z',
    email: 'M3 5h18a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Zm9 7 8-5H4l8 5Zm0 2L4 9v8h16V9l-8 5Z',
  };

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"]/g, function (m) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[m];
    });
  }
  // Block javascript:/data: hrefs; allow http(s), mailto, anchors and site paths.
  function safeUrl(u) {
    u = String(u == null ? '' : u).trim();
    if (/^\s*(javascript|data|vbscript):/i.test(u)) return '#';
    return u || '#';
  }

  var STYLE_ID = 'cvf-style';
  function injectStyle() {
    if (document.getElementById(STYLE_ID)) return;
    var s = document.createElement('style');
    s.id = STYLE_ID;
    s.textContent =
      '.cvf{width:100%;text-align:left}' +
      // Fallback accent (used only when the footer is not inside a <footer> we
      // can border). Full-bleed via the negative-margin trick.
      '.cvf-accent{height:3px;width:100vw;margin-left:calc(50% - 50vw);margin-top:-26px;margin-bottom:26px;background:linear-gradient(90deg,rgb(var(--c-primary)),rgb(var(--c-accent,var(--c-primary))))}' +
      '.cvf-main{display:flex;flex-wrap:wrap;justify-content:space-between;gap:36px;padding-bottom:26px}' +
      '.cvf-brand{flex:1 1 240px;max-width:360px;min-width:200px}' +
      '.cvf-logo{height:30px;width:auto;display:block}' +
      '.cvf-brand h3{font-size:18px;font-weight:800;margin:0;color:rgb(var(--c-text));letter-spacing:-.01em}' +
      '.cvf-desc{color:rgb(var(--c-muted));font-size:13.5px;line-height:1.55;margin:10px 0 0}' +
      '.cvf-social{display:flex;flex-wrap:wrap;gap:10px;margin-top:16px}' +
      '.cvf-social a{width:34px;height:34px;border-radius:10px;display:grid;place-items:center;background:rgb(var(--c-surface-2));color:rgb(var(--c-text-2));transition:background .15s,color .15s,transform .15s}' +
      '.cvf-social a:hover{background:rgb(var(--c-primary));color:#fff;transform:translateY(-2px)}' +
      '.cvf-social svg{width:17px;height:17px;fill:currentColor}' +
      '.cvf-cols{display:flex;flex-wrap:wrap;gap:44px}' +
      '.cvf-col{min-width:120px}' +
      '.cvf-col h4{font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.07em;color:rgb(var(--c-text-2));margin:0 0 13px}' +
      '.cvf-col ul{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:9px}' +
      '.cvf-col a{color:rgb(var(--c-muted));font-size:13.5px;transition:color .12s}' +
      '.cvf-col a:hover{color:rgb(var(--c-primary))}' +
      '.cvf-bottom{border-top:1px solid rgb(var(--c-border));padding-top:18px;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;color:rgb(var(--c-muted));font-size:13px}' +
      '.cvf-copy{flex:1 1 auto}' +
      '.cvf-utility{display:flex;flex-wrap:wrap;gap:8px;align-items:center}' +
      '.cvf-utility a,.cvf-util-link{color:rgb(var(--c-muted))!important;font-size:12.5px!important;text-decoration:none!important;padding:5px 11px;border:1px solid rgb(var(--c-border));border-radius:999px;transition:color .15s,border-color .15s;line-height:1.2}' +
      '.cvf-utility a:hover,.cvf-util-link:hover{color:rgb(var(--c-primary))!important;border-color:rgb(var(--c-primary))}' +
      '#cvf-top{position:fixed;left:20px;bottom:20px;width:42px;height:42px;border-radius:12px;border:0;cursor:pointer;display:grid;place-items:center;background:rgb(var(--c-primary));color:#fff;box-shadow:0 8px 24px rgb(var(--c-primary)/.4);opacity:0;visibility:hidden;transform:translateY(8px);transition:opacity .2s,visibility .2s,transform .2s;z-index:50}' +
      '#cvf-top.show{opacity:1;visibility:visible;transform:none}' +
      '@media(max-width:640px){.cvf-main{gap:28px}.cvf-cols{gap:32px}.cvf-bottom{justify-content:center;text-align:center}.cvf-copy{flex:1 1 100%}}';
    document.head.appendChild(s);
  }

  function socialHtml(socials) {
    if (!Array.isArray(socials) || !socials.length) return '';
    var out = socials.map(function (s) {
      if (!s || !s.url) return '';
      var icon = ICONS[s.icon] || ICONS.website;
      var label = esc(s.icon || 'link');
      return '<a href="' + esc(safeUrl(s.url)) + '" target="_blank" rel="noopener noreferrer" aria-label="' + label +
        '" title="' + label + '"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="' + icon + '"/></svg></a>';
    }).join('');
    return out ? '<div class="cvf-social">' + out + '</div>' : '';
  }

  function brandHtml(info) {
    info = info || {};
    var head = info.logo
      ? '<img class="cvf-logo" src="' + esc(safeUrl(info.logo)) + '" alt="' + esc(info.title || '') + '">'
      : '<h3>' + esc(info.title || '') + '</h3>';
    var desc = info.text ? '<p class="cvf-desc">' + esc(info.text) + '</p>' : '';
    return '<div class="cvf-brand">' + head + desc + socialHtml(info.socials) + '</div>';
  }

  function columnsHtml(columns) {
    if (!Array.isArray(columns) || !columns.length) return '';
    var cols = columns.map(function (col) {
      if (!col) return '';
      var links = (Array.isArray(col.links) ? col.links : []).map(function (l) {
        if (!l || !l.label) return '';
        return '<li><a href="' + esc(safeUrl(l.url)) + '">' + esc(l.label) + '</a></li>';
      }).join('');
      if (!links) return '';
      return '<div class="cvf-col"><h4>' + esc(col.title || '') + '</h4><ul>' + links + '</ul></div>';
    }).join('');
    return cols ? '<div class="cvf-cols">' + cols + '</div>' : '';
  }

  function render(el, cfg) {
    injectStyle();
    var year = new Date().getFullYear();
    var copy = String(cfg.copyright || '').replace(/\{year\}/g, year);

    // Full-bleed accent: turn the host <footer>'s top border into the gradient
    // so it spans the full page width and clearly separates the footer. Falls
    // back to an in-content bar when there's no <footer> ancestor.
    var footerEl = el.closest ? el.closest('footer') : null;
    var html = '<div class="cvf">';
    if (cfg.accent !== false && footerEl) {
      footerEl.style.borderTopWidth = '3px';
      footerEl.style.borderTopStyle = 'solid';
      footerEl.style.borderImage = 'linear-gradient(90deg,rgb(var(--c-primary)),rgb(var(--c-accent,var(--c-primary)))) 1';
    } else if (cfg.accent !== false) {
      html += '<div class="cvf-accent"></div>';
    }
    html += '<div class="cvf-main">' + brandHtml(cfg.info) + columnsHtml(cfg.columns) + '</div>';
    // Always render the bottom bar — it hosts the copyright AND adopted utility
    // links (RSS, Privacy choices) from sibling footer extensions.
    html += '<div class="cvf-bottom">' + (copy ? '<span class="cvf-copy">' + esc(copy) + '</span>' : '<span class="cvf-copy"></span>') + '<span class="cvf-utility"></span></div>';
    html += '</div>';
    el.innerHTML = html;

    adoptUtilityLinks(el);
    if (cfg.backToTop !== false) addBackToTop();
  }

  /**
   * Pull sibling forum:footer slot links (RSS, Privacy choices, …) into our
   * bottom utility bar so they read as a deliberate footer row instead of loose
   * centered links. Re-runs briefly via an observer for links added after us.
   */
  function adoptUtilityLinks(el) {
    var container = el.parentElement;
    var bar = el.querySelector('.cvf-utility');
    if (!container || !bar) return;

    function adopt() {
      var sibs = container.querySelectorAll('[data-convoro-ext]');
      for (var i = 0; i < sibs.length; i++) {
        var sib = sibs[i];
        if (sib === el || el.contains(sib)) continue;
        var links = sib.querySelectorAll('a');
        for (var j = 0; j < links.length; j++) {
          var a = links[j];
          a.removeAttribute('style');
          a.classList.add('cvf-util-link');
          bar.appendChild(a);
        }
        sib.style.display = 'none';
      }
    }

    adopt();
    // Catch links injected by slower sibling extensions for a few seconds.
    try {
      var obs = new MutationObserver(adopt);
      obs.observe(container, { childList: true, subtree: true });
      setTimeout(function () { obs.disconnect(); }, 6000);
    } catch (e) { /* no observer — initial adopt is enough */ }
  }

  function addBackToTop() {
    if (document.getElementById('cvf-top')) return;
    var btn = document.createElement('button');
    btn.id = 'cvf-top';
    btn.type = 'button';
    btn.setAttribute('aria-label', 'Back to top');
    btn.innerHTML = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M12 19V5M5 12l7-7 7 7"/></svg>';
    btn.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: 'smooth' }); });
    document.body.appendChild(btn);
    var onScroll = function () { btn.classList.toggle('show', window.scrollY > 400); };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  c.registerSlot('forum:footer', {
    ext: 'convoro-footer',
    order: 100,
    mount: function (el) {
      fetch('/api/ext/footer/config', { headers: { Accept: 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (cfg) {
          if (!cfg || cfg.enabled === false) return;
          render(el, cfg);
        })
        .catch(function () { /* silent */ });
    },
  });
})();
