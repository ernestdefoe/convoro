import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/**
 * Realtime is OPTIONAL (Laravel Reverb needs a persistent socket process, which
 * shared hosting can't run). When it isn't configured we expose `window.Echo = null`
 * and every consumer guards with `if (!Echo()) ...`, so the app works the same —
 * just without live presence/typing/instant notifications (push + refresh cover it).
 *
 * The connection is read from server-rendered <meta> tags at RUNTIME (not from
 * build-time VITE_* env), because Convoro ships ONE prebuilt bundle to many
 * hosts — each install must connect to ITS OWN domain. The server only emits the
 * tags when realtime is enabled and a Reverb key is configured (see
 * resources/views/app.blade.php). The socket rides the same host/scheme as the
 * page, over the `/app` path the web server proxies to Reverb.
 */
function meta(name: string): string {
  if (typeof document === 'undefined') return '';
  return document.querySelector(`meta[name="${name}"]`)?.getAttribute('content') ?? '';
}

const key = meta('reverb-key');
const host = meta('reverb-host');
const scheme = meta('reverb-scheme') || 'https';
const enabled = typeof window !== 'undefined' && !!key && !!host;

let echo: Echo<any> | null = null;

if (enabled) {
  (window as any).Pusher = Pusher;
  const tls = scheme === 'https';
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

  echo = new Echo({
    broadcaster: 'reverb',
    key,
    wsHost: host,
    // Same port as the page: 443 behind TLS (web server proxies /app → Reverb),
    // 8080 for plain-HTTP local dev hitting Reverb directly.
    wsPort: tls ? 443 : 8080,
    wssPort: 443,
    forceTLS: tls,
    enabledTransports: ['ws', 'wss'],
    auth: { headers: { 'X-CSRF-TOKEN': csrf } },
  });
}

if (typeof window !== 'undefined') {
  (window as any).Echo = echo;
}

export default echo;
