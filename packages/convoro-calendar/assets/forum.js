/*
 * Convoro Events — forum bundle (vanilla JS, shipped prebuilt).
 * Adds an "Events" link to the header nav and an "Upcoming events" widget to the
 * forum sidebar. The pages themselves are server-rendered by the provider.
 */
(function () {
  var c = window.Convoro;
  if (!c || typeof c.registerSlot !== 'function') return;

  // The "Events" header nav link is declared in extension.json ("nav") and
  // rendered server-side, so it appears instantly (no async-bundle flash).

  // ── "Upcoming events" sidebar widget ──
  c.registerSlot('forum:sidebar', {
    ext: 'convoro-calendar',
    order: 30,
    mount: function (el) {
      var card = document.createElement('div');
      card.className = 'overflow-hidden rounded-c border border-line bg-surface shadow-sm';
      card.innerHTML = '<h4 class="border-b border-line px-4 py-3 text-xs font-semibold uppercase tracking-wide text-ink-muted">' +
        ((c.trans && c.trans('Upcoming events')) || 'Upcoming events') + '</h4><div class="p-2" data-list></div>';
      var list = card.querySelector('[data-list]');

      function fmt(iso) {
        try {
          var d = new Date(iso.replace(' ', 'T'));
          return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' }) + ' · ' +
            d.toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit' });
        } catch (e) { return ''; }
      }

      fetch('/api/ext/events/upcoming', { headers: { Accept: 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (d) {
          if (!d || !d.events || !d.events.length) { card.remove(); return; }
          list.innerHTML = d.events.map(function (ev) {
            var place = ev.is_online ? '🌐 Online' : (ev.location ? esc(ev.location) : '');
            var meta = esc(ev.time || fmt(ev.starts_at)) + (place ? ' · ' + place : '');
            return '<a href="/events/' + ev.id + '" class="flex items-center gap-2.5 rounded-lg px-2.5 py-2 hover:bg-surface-2">' +
              '<span class="flex-none w-9 overflow-hidden rounded-md border border-line bg-surface-2 text-center">' +
                '<span class="block bg-primary py-px text-[9px] font-bold uppercase leading-tight text-white">' + esc(ev.mon || '') + '</span>' +
                '<span class="block py-0.5 text-sm font-extrabold leading-tight text-ink">' + esc(ev.day || '') + '</span>' +
              '</span>' +
              '<span class="min-w-0 flex-1">' +
                '<span class="block text-sm font-semibold text-ink line-clamp-1">' + esc(ev.title) + '</span>' +
                '<span class="block text-xs text-ink-muted line-clamp-1">' + meta + '</span>' +
              '</span></a>';
          }).join('');
          el.appendChild(card);
        })
        .catch(function () { /* silent */ });

      function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"]/g, function (m) {
          return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[m];
        });
      }
    },
  });
})();
