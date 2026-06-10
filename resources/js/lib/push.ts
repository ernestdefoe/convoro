/** Web Push helpers shared by the profile toggle and the PWA banner. */

export function pushSupported(): boolean {
  return 'serviceWorker' in navigator && 'PushManager' in window && (window.isSecureContext ?? false);
}

export function isStandalone(): boolean {
  return window.matchMedia('(display-mode: standalone)').matches || (navigator as any).standalone === true;
}

function urlBase64ToUint8Array(base64: string): Uint8Array {
  const padding = '='.repeat((4 - (base64.length % 4)) % 4);
  const b64 = (base64 + padding).replace(/-/g, '+').replace(/_/g, '/');
  const raw = atob(b64);
  return Uint8Array.from([...raw].map((c) => c.charCodeAt(0)));
}

function xsrf(): string {
  const m = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
  return m ? decodeURIComponent(m[1]) : '';
}

async function post(url: string, body: unknown): Promise<boolean> {
  const res = await fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrf(), Accept: 'application/json' },
    body: JSON.stringify(body),
  });
  return res.ok;
}

export async function currentSubscription(): Promise<PushSubscription | null> {
  const reg = await navigator.serviceWorker.ready.catch(() => null);
  return reg ? reg.pushManager.getSubscription() : null;
}

/** Request permission, subscribe via PushManager, and persist on the server. */
export async function subscribeToPush(vapidKey: string): Promise<boolean> {
  if (!pushSupported() || !vapidKey) return false;
  const perm = await Notification.requestPermission();
  if (perm !== 'granted') return false;

  const reg = await navigator.serviceWorker.ready;
  const sub = await reg.pushManager.subscribe({
    userVisibleOnly: true,
    applicationServerKey: urlBase64ToUint8Array(vapidKey) as BufferSource,
  });
  return post('/push/subscribe', sub.toJSON());
}

export async function unsubscribeFromPush(): Promise<void> {
  const sub = await currentSubscription();
  if (!sub) return;
  await post('/push/unsubscribe', { endpoint: sub.endpoint });
  await sub.unsubscribe();
}
