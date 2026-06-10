// Convoro extension: OnAir (forum surface).
// Shipped prebuilt — no build step. Shows a LIVE player in the sidebar when the
// admin is broadcasting. Builds the embed client-side so Twitch's required
// `parent` can use the live hostname.

const c = window.Convoro;

if (c && typeof c.registerSlot === 'function') {
  c.registerSlot('forum:sidebar', {
    ext: 'convoro-onair',
    order: -30,
    mount(el) {
      fetch('/api/ext/onair/current', { headers: { Accept: 'application/json' } })
        .then((r) => (r.ok ? r.json() : null))
        .then((d) => { if (d && d.live) render(el, d); })
        .catch(() => { /* silent */ });
    },
  });
}

function embedUrl(d) {
  if (d.platform === 'twitch') {
    return `https://player.twitch.tv/?channel=${encodeURIComponent(d.streamId)}&parent=${location.hostname}&muted=true`;
  }
  // YouTube video ID
  return `https://www.youtube.com/embed/${encodeURIComponent(d.streamId)}?autoplay=0&rel=0`;
}

function render(el, d) {
  const card = document.createElement('div');
  card.style.cssText = [
    'overflow:hidden', 'border-radius:var(--c-radius,12px)',
    'border:1px solid rgb(var(--c-border,230 232 240))',
    'background:rgb(var(--c-surface,255 255 255))', 'margin-bottom:16px',
  ].join(';');

  const head = document.createElement('div');
  head.style.cssText = 'display:flex;align-items:center;gap:8px;padding:10px 14px;border-bottom:1px solid rgb(var(--c-border,230 232 240))';
  const badge = document.createElement('span');
  badge.textContent = 'LIVE';
  badge.style.cssText = 'display:inline-flex;align-items:center;gap:6px;background:#e0245e;color:#fff;font-size:11px;font-weight:800;letter-spacing:.05em;padding:3px 8px;border-radius:999px';
  badge.style.animation = 'oa-pulse 1.6s ease-in-out infinite';
  const title = document.createElement('b');
  title.textContent = d.title;
  title.style.cssText = 'font-size:13px;color:rgb(var(--c-text,27 32 48));white-space:nowrap;overflow:hidden;text-overflow:ellipsis';
  head.appendChild(badge);
  head.appendChild(title);

  const frameWrap = document.createElement('div');
  frameWrap.style.cssText = 'position:relative;width:100%;aspect-ratio:16/9;background:#000';
  const iframe = document.createElement('iframe');
  iframe.src = embedUrl(d);
  iframe.allow = 'autoplay; fullscreen; encrypted-media; picture-in-picture';
  iframe.setAttribute('allowfullscreen', '');
  iframe.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;border:0';
  frameWrap.appendChild(iframe);

  if (!document.getElementById('oa-style')) {
    const s = document.createElement('style');
    s.id = 'oa-style';
    s.textContent = '@keyframes oa-pulse{0%,100%{opacity:1}50%{opacity:.55}}';
    document.head.appendChild(s);
  }

  card.appendChild(head);
  card.appendChild(frameWrap);
  el.appendChild(card);
}
