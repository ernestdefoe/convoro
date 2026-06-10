import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

(window as any).Pusher = Pusher;

const scheme = (import.meta.env.VITE_REVERB_SCHEME as string) ?? 'https';
const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

const echo = new Echo({
  broadcaster: 'reverb',
  key: import.meta.env.VITE_REVERB_APP_KEY,
  wsHost: import.meta.env.VITE_REVERB_HOST,
  wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
  wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
  forceTLS: scheme === 'https',
  enabledTransports: ['ws', 'wss'],
  auth: { headers: { 'X-CSRF-TOKEN': csrf } },
});

(window as any).Echo = echo;

export default echo;
