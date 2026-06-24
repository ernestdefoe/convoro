/*
 * Convoro Follow & Feed — forum bundle (vanilla JS).
 * Adds a Follow button into the profile header action row (the profile:actions
 * slot) and a "Feed" link to the header nav. Pages/API live in the provider.
 */
(function () {
  if (!window.Convoro || typeof window.Convoro.registerSlot !== 'function') return;

  function csrf() {
    var m = document.querySelector('meta[name=csrf-token]');
    return m ? m.content : '';
  }

  // Compact follower count (1.2K, 3.4M), like the channel-style header.
  function fmt(n) {
    if (n >= 1e6) return (n / 1e6).toFixed(n % 1e6 ? 1 : 0).replace(/\.0$/, '') + 'M';
    if (n >= 1e3) return (n / 1e3).toFixed(n % 1e3 ? 1 : 0).replace(/\.0$/, '') + 'K';
    return '' + n;
  }

  // Fill the follower count into the profile header's stats row (a core
  // placeholder); core never queries our table — we own this number.
  function updateStat(n) {
    var stat = document.querySelector('[data-follow-stat]');
    if (!stat) return;
    stat.innerHTML = '<strong class="font-bold text-white">' + fmt(n) + '</strong> ' + (n === 1 ? 'follower' : 'followers');
    stat.classList.remove('hidden');
  }

  var ICON_FOLLOW =
    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg>';
  var ICON_FOLLOWING =
    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="m17 11 2 2 4-4"/></svg>';

  // ---- Follow button in a member's profile header (over the cover card). ----
  window.Convoro.registerSlot('profile:actions', {
    ext: 'convoro-follow',
    order: 5,
    mount: function (el, ctx) {
      var uid = ctx && ctx.props && ctx.props.userId;
      if (!uid) return;

      var btn = document.createElement('button');
      btn.type = 'button';
      btn.style.display = 'none';

      function paint(state) {
        if (!state.canFollow) {
          btn.style.display = 'none';
          return;
        }
        btn.style.display = '';
        var tip = state.followers + (state.followers === 1 ? ' follower' : ' followers');
        btn.setAttribute('title', tip);
        if (state.following) {
          btn.innerHTML = ICON_FOLLOWING + '<span>Following</span>';
          btn.className =
            'inline-flex items-center gap-1.5 rounded-xl border border-white/30 bg-white/10 px-4 py-2.5 text-sm font-semibold text-white transition hover:border-red-400/60 hover:text-red-200';
        } else {
          btn.innerHTML = ICON_FOLLOW + '<span>Follow</span>';
          btn.className =
            'inline-flex items-center gap-1.5 rounded-xl bg-white/90 px-4 py-2.5 text-sm font-semibold text-slate-900 shadow transition hover:bg-white';
        }
      }

      var current = { canFollow: false, following: false, followers: 0 };
      btn.addEventListener('click', function () {
        btn.disabled = true;
        fetch('/api/ext/follow/user/' + uid, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' } })
          .then(function (r) { return r.ok ? r.json() : null; })
          .then(function (d) { if (d) { current.following = d.following; current.followers = d.followers; paint(current); updateStat(d.followers); } })
          .catch(function () {})
          .then(function () { btn.disabled = false; });
      });

      el.appendChild(btn);

      fetch('/api/ext/follow/state/' + uid, { headers: { Accept: 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (d) { if (d) { current = d; paint(d); updateStat(d.followers); } else { btn.remove(); } })
        .catch(function () { btn.remove(); });
    },
  });

  // The "Feed" header nav link is declared in extension.json ("nav", auth-only)
  // and rendered server-side, so it appears instantly for logged-in members.
})();
