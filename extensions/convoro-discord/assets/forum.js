// Discord — registers a "Continue with Discord" button into the core
// `auth:providers` slot (rendered in the login/register modal). Clicking it
// starts the OAuth flow at /auth/discord. Loaded only when the extension is
// enabled, so the button appears exactly when Discord login is available.
(function () {
  var c = window.Convoro;
  if (!c || typeof c.registerSlot !== 'function') return;

  function tr(k) { return c.t ? c.t(k) : k; }

  c.registerSlot('auth:providers', {
    ext: 'convoro-discord',
    label: 'Discord login',
    order: 5,
    mount: function (el) {
      var a = document.createElement('a');
      a.href = '/auth/discord';
      a.setAttribute('style', [
        'display:flex', 'width:100%', 'align-items:center', 'justify-content:center',
        'gap:8px', 'margin-top:8px', 'border-radius:var(--c-radius,10px)', 'border:0',
        'background:#5865F2', 'color:#fff', 'padding:10px 16px',
        'font-size:14px', 'font-weight:600', 'text-decoration:none', 'cursor:pointer',
      ].join(';'));
      a.onmouseenter = function () { a.style.filter = 'brightness(1.08)'; };
      a.onmouseleave = function () { a.style.filter = ''; };

      a.innerHTML =
        '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">' +
        '<path d="M20.317 4.369A19.79 19.79 0 0 0 15.885 3a13.78 13.78 0 0 0-.617 1.27 18.27 18.27 0 0 0-5.535 0A13.7 13.7 0 0 0 9.11 3a19.74 19.74 0 0 0-4.435 1.37C1.86 8.59 1.094 12.7 1.476 16.756A19.9 19.9 0 0 0 7.51 19.82a14.6 14.6 0 0 0 1.29-2.1 12.9 12.9 0 0 1-2.032-.978c.17-.125.337-.255.498-.388a14.2 14.2 0 0 0 12.07 0c.163.135.33.265.498.388a12.9 12.9 0 0 1-2.035.978 14.4 14.4 0 0 0 1.29 2.1 19.85 19.85 0 0 0 6.038-3.064c.448-4.7-.766-8.776-3.21-12.387ZM8.02 14.262c-1.182 0-2.153-1.085-2.153-2.418 0-1.333.952-2.42 2.153-2.42 1.21 0 2.173 1.096 2.153 2.42 0 1.333-.952 2.418-2.153 2.418Zm7.96 0c-1.182 0-2.152-1.085-2.152-2.418 0-1.333.95-2.42 2.152-2.42 1.21 0 2.173 1.096 2.153 2.42 0 1.333-.943 2.418-2.153 2.418Z"/></svg>' +
        '<span></span>';
      a.querySelector('span').textContent = tr('Continue with Discord');
      el.appendChild(a);
    },
  });
})();
