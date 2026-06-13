/*
 * Convoro Bookmarks — forum bundle (vanilla JS).
 * Adds a bookmark toggle to each post (the post:actions slot) and a "Bookmarks"
 * link to the header nav for logged-in members. Pages/API live in the provider.
 */
(function () {
  if (!window.Convoro || typeof window.Convoro.registerSlot !== 'function') return;

  function csrf() {
    var m = document.querySelector('meta[name=csrf-token]');
    return m ? m.content : '';
  }

  var ICON_OFF = '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>';
  var ICON_ON = '<svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>';

  // ---- Per-topic bookmark state (which posts I've saved). ----
  var topics = {}; // topicId -> { state, loaded(Promise), mounts: [{postId, el}] }

  function fetchState(topicId) {
    return fetch('/api/ext/bookmarks/topic/' + topicId, { headers: { Accept: 'application/json' } })
      .then(function (r) { return r.ok ? r.json() : null; })
      .catch(function () { return null; })
      .then(function (s) { return s || { canBookmark: false, ids: [] }; });
  }

  function ctrl(topicId) {
    var c = topics[topicId];
    if (!c) {
      c = topics[topicId] = { state: null, mounts: [] };
      c.loaded = fetchState(topicId).then(function (s) { c.state = { canBookmark: s.canBookmark, ids: (s.ids || []).map(Number) }; return c.state; });
    }
    return c;
  }

  function render(c, m) {
    m.el.innerHTML = '';
    if (!c.state || !c.state.canBookmark) return;
    var on = c.state.ids.indexOf(m.postId) !== -1;
    var b = document.createElement('button');
    b.type = 'button';
    b.title = on ? 'Remove bookmark' : 'Bookmark this post';
    b.className = 'inline-flex items-center rounded-lg px-2.5 py-1 text-[13px] font-semibold hover:bg-surface-2 ' + (on ? 'text-primary' : 'text-ink-2');
    b.innerHTML = on ? ICON_ON : ICON_OFF;
    b.addEventListener('click', function () { toggle(c, m); });
    m.el.appendChild(b);
  }

  function toggle(c, m) {
    fetch('/api/ext/bookmarks/post/' + m.postId, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' } })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) {
        if (!d) return;
        var i = c.state.ids.indexOf(m.postId);
        if (d.bookmarked && i === -1) c.state.ids.push(m.postId);
        else if (!d.bookmarked && i !== -1) c.state.ids.splice(i, 1);
        render(c, m);
      })
      .catch(function () {});
  }

  window.Convoro.registerSlot('post:actions', {
    ext: 'convoro-bookmarks',
    order: 10,
    mount: function (el, ctx) {
      var p = (ctx && ctx.props) || {};
      if (!p.topicId || !p.postId) return;
      var c = ctrl(p.topicId);
      var m = { postId: Number(p.postId), el: el };
      c.mounts.push(m);
      c.loaded.then(function () { render(c, m); });
      return function () { var i = c.mounts.indexOf(m); if (i >= 0) c.mounts.splice(i, 1); };
    },
  });

  // ---- User-menu link. The dropdown only renders for logged-in members, so
  // no auth probe is needed — just match the other menu items' styling. ----
  window.Convoro.registerSlot('user:menu', {
    ext: 'convoro-bookmarks',
    order: 6,
    mount: function (el) {
      var a = document.createElement('a');
      a.href = '/bookmarks';
      a.className = 'block px-4 py-2.5 text-sm font-medium text-ink-2 hover:bg-surface-2 hover:text-ink';
      a.textContent = 'Bookmarks';
      el.appendChild(a);
    },
  });
})();
