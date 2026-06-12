// Light/dark persistence. Stored in a cookie scoped to the registrable domain
// (e.g. `.convoro.co`) so the choice carries across the marketing site and the
// forum subdomain — localStorage is per-origin and would NOT share between them.

/** `.convoro.co` for community.convoro.co / convoro.co; '' for localhost/IP. */
export function rootCookieDomain(): string {
  const h = location.hostname;
  if (h === 'localhost' || /^[\d.]+$/.test(h)) return '';
  const parts = h.split('.');
  return '.' + (parts.length > 2 ? parts.slice(-2).join('.') : h);
}

export function getStoredTheme(): 'light' | 'dark' | null {
  const m = document.cookie.split('; ').find((c) => c.startsWith('convoro_theme='));
  const v = m ? decodeURIComponent(m.split('=')[1]) : (() => { try { return localStorage.getItem('convoro_theme'); } catch { return null; } })();
  return v === 'dark' || v === 'light' ? v : null;
}

export function setTheme(mode: 'light' | 'dark'): void {
  document.documentElement.dataset.theme = mode;
  const dom = rootCookieDomain();
  document.cookie =
    `convoro_theme=${mode};path=/;max-age=31536000;samesite=lax` +
    (dom ? `;domain=${dom}` : '') +
    (location.protocol === 'https:' ? ';secure' : '');
  // Keep localStorage in sync for backward compatibility / same-origin reads.
  try { localStorage.setItem('convoro_theme', mode); } catch { /* ignore */ }
}
