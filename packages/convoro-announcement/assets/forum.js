// Convoro extension: Announcement Bar (forum surface).
// Shipped prebuilt — no build step. Registers a dismissible banner into the
// `topic:below` slot, fed by the extension's own /api/ext/announcement route.
// Uses the live theme tokens (--c-*) so it matches whatever theme is active.

const c = window.Convoro;
if (c && typeof c.registerSlot === 'function') {
  c.registerSlot('topic:below', {
    ext: 'convoro-announcement',
    order: -10,
    mount(el) {
      const dismissed = (() => { try { return localStorage.getItem('convoro-announcement-dismissed'); } catch { return null; } })();

      fetch('/api/ext/announcement', { headers: { Accept: 'application/json' } })
        .then((r) => (r.ok ? r.json() : null))
        .then((d) => {
          if (!d || !d.body) return;
          if (dismissed === d.body) return; // already dismissed this exact message

          const bar = document.createElement('div');
          bar.setAttribute('role', 'status');
          bar.style.cssText = [
            'display:flex', 'align-items:center', 'gap:10px',
            'margin:16px 0', 'padding:12px 16px',
            'border-radius:var(--c-radius,12px)',
            'background:rgb(var(--c-primary) / 0.10)',
            'border:1px solid rgb(var(--c-primary) / 0.30)',
            'color:rgb(var(--c-text, 27 32 48))',
            'font-size:14px', 'line-height:1.4',
          ].join(';');

          const icon = document.createElement('span');
          icon.textContent = '📣';
          icon.setAttribute('aria-hidden', 'true');

          const text = document.createElement('span');
          text.style.flex = '1';
          text.textContent = d.body;

          const close = document.createElement('button');
          close.type = 'button';
          close.setAttribute('aria-label', 'Dismiss announcement');
          close.textContent = '✕';
          close.style.cssText = 'background:none;border:0;cursor:pointer;font-size:14px;color:inherit;opacity:.6';
          close.addEventListener('click', () => {
            try { localStorage.setItem('convoro-announcement-dismissed', d.body); } catch { /* ignore */ }
            bar.remove();
          });

          bar.appendChild(icon);
          bar.appendChild(text);
          bar.appendChild(close);
          el.appendChild(bar);
        })
        .catch(() => { /* silent */ });
    },
  });
}
