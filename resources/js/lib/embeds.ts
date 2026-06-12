// Hydrates social embeds that Embeds.php rendered as provider markup. Runs on
// first paint and after every Inertia navigation. iframe embeds (YouTube/Vimeo/
// Spotify) need nothing here — they render on their own.
import { router } from '@inertiajs/vue3';

function loadScript(src: string): void {
  const base = src.split('#')[0];
  if (document.querySelector(`script[src^="${base}"]`)) return;
  const s = document.createElement('script');
  s.src = src;
  s.async = true;
  s.setAttribute('charset', 'utf-8');
  document.body.appendChild(s);
}

function hydrate(): void {
  const w = window as any;
  if (document.querySelector('.twitter-tweet')) {
    if (w.twttr?.widgets) w.twttr.widgets.load();
    else loadScript('https://platform.twitter.com/widgets.js');
  }
  if (document.querySelector('.instagram-media')) {
    if (w.instgrm?.Embeds) w.instgrm.Embeds.process();
    else loadScript('https://www.instagram.com/embed.js');
  }
  if (document.querySelector('.fb-post, .fb-video')) {
    if (w.FB?.XFBML) w.FB.XFBML.parse();
    else {
      if (!document.getElementById('fb-root')) {
        const r = document.createElement('div');
        r.id = 'fb-root';
        document.body.prepend(r);
      }
      loadScript('https://connect.facebook.net/en_US/sdk.js#xfbml=1&version=v19.0');
    }
  }
}

export function initEmbeds(): void {
  const run = () => setTimeout(hydrate, 80);
  router.on('success', run);
  if (document.readyState !== 'loading') run();
  else document.addEventListener('DOMContentLoaded', run);
}
