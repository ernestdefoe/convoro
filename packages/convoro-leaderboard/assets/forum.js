/*
 * Convoro Leaderboard — forum bundle (vanilla JS).
 * Adds a "Leaderboard" link to the primary header nav (the header:nav slot,
 * next to Members). The page itself is served at /leaderboard by the PHP provider.
 */
(function () {
  if (!window.Convoro || typeof window.Convoro.registerSlot !== 'function') return;

  window.Convoro.registerSlot('header:nav', {
    ext: 'convoro-leaderboard',
    order: 5,
    mount: function (el) {
      var a = document.createElement('a');
      a.href = '/leaderboard';
      a.className = 'rounded-lg px-3 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2';
      a.textContent = 'Leaderboard';
      el.appendChild(a);
    },
  });
})();
