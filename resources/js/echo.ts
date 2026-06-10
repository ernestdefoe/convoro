import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/**
 * Realtime is OPTIONAL (Laravel Reverb needs a persistent socket process, which
 * shared hosting can't run). When it isn't configured we expose `window.Echo = null`
 * and every consumer guards with `if (!Echo()) ...`, so the app works the same —
 * just without live presence/typing/instant notifications (push + refresh cover it).
 */
const key = import.meta.env.VITE_REVERB_APP_KEY as string | undefined;
const host = import.meta.env.VITE_REVERB_HOST as string | undefined;
const realtimeEnabled = (import.meta.env.VITE_REALTIME ?? '1') !== '0' && !!key && !!host;

let echo: Echo<any> | null = null;

if (realtimeEnabled) {
  (window as any).Pusher = Pusher;
  const scheme = (import.meta.env.VITE_REVERB_SCHEME as string) ?? 'https';
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

  echo = new Echo({
    broadcaster: 'reverb',
    key,
    wsHost: host,
    wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
    wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
    forceTLS: scheme === 'https',
    enabledTransports: ['ws', 'wss'],
    auth: { headers: { 'X-CSRF-TOKEN': csrf } },
  });
}

(window as any).Echo = echo;

export default echo;
