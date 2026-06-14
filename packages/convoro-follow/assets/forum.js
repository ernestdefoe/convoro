/*
 * Convoro Follow & Feed — forum bundle (vanilla JS).
 * Adds a Follow button to member profiles (the profile:below slot) and a "Feed"
 * link to the header nav. Pages/API live in the provider.
 */
(function () {
  if (!window.Convoro || typeof window.Convoro.registerSlot !== 'function') return;

  function csrf() {
    var m = document.querySelector('meta[name=csrf-token]');
    return m ? m.content : '';
  }

  // ---- Follow button on a member's profile. ----
  window.Convoro.registerSlot('profile:below', {
    ext: 'convoro-follow',
    order: 5,
    mount: function (el, ctx) {
      var uid = ctx && ctx.props && ctx.props.userId;
      if (!uid) return;

      var wrap = document.createElement('div');
      wrap.className = 'mb-4 flex items-center gap-3 rounded-c border border-line bg-surface px-4 py-3';
      el.appendChild(wrap);

      var count = document.createElement('span');
      count.className = 'text-sm text-ink-2';
      var btn = document.createElement('button');
      btn.type = 'button';

      function paint(state) {
        var n = state.followers || 0;
        count.textContent = n + (n === 1 ? ' follower' : ' followers');
        if (!state.canFollow) {
          btn.style.display = 'none';
          return;
        }
        btn.style.display = '';
        if (state.following) {
          btn.textContent = 'Following';
          btn.className = 'rounded-lg border border-line bg-surface-2 px-4 py-2 text-sm font-semibold text-ink-2 hover:border-red-500/40 hover:text-red-500';
        } else {
          btn.textContent = 'Follow';
          btn.className = 'rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-600';
        }
      }

      var current = { canFollow: false, following: false, followers: 0 };
      btn.addEventListener('click', function () {
        btn.disabled = true;
        fetch('/api/ext/follow/user/' + uid, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' } })
          .then(function (r) { return r.ok ? r.json() : null; })
          .then(function (d) { if (d) { current.following = d.following; current.followers = d.followers; paint(current); } })
          .catch(function () {})
          .then(function () { btn.disabled = false; });
      });

      wrap.appendChild(count);
      var sp = document.createElement('span'); sp.className = 'flex-1'; wrap.appendChild(sp);
      wrap.appendChild(btn);

      fetch('/api/ext/follow/state/' + uid, { headers: { Accept: 'application/json' } })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (d) { if (d) { current = d; paint(d); } else { wrap.remove(); } })
        .catch(function () { wrap.remove(); });
    },
  });

  // The "Feed" header nav link is declared in extension.json ("nav", auth-only)
  // and rendered server-side, so it appears instantly for logged-in members.
})();
