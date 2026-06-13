/*
 * Convoro Accepted Answers — forum bundle (vanilla JS).
 *
 * Registers a "Mark as answer" control into the core `post:actions` slot. In a
 * Q&A category the asker (or staff) can toggle a reply as the accepted answer;
 * everyone sees the accepted reply flagged with a green check. State comes from
 * /api/ext/solved/topic/{id}; toggling hits the same route's POST.
 */
(function () {
  if (!window.Convoro || typeof window.Convoro.registerSlot !== 'function') return;

  var topics = {}; // topicId -> { state, loaded(Promise), mounts: [{postId, el}] }

  function csrf() {
    var m = document.querySelector('meta[name=csrf-token]');
    return m ? m.content : '';
  }

  function fetchState(topicId) {
    return fetch('/api/ext/solved/topic/' + topicId, { headers: { Accept: 'application/json' } })
      .then(function (r) { return r.ok ? r.json() : null; })
      .catch(function () { return null; })
      .then(function (s) { return s || { isQa: false, solvedPostId: null, canMark: false }; });
  }

  function ctrl(topicId) {
    var c = topics[topicId];
    if (!c) {
      c = topics[topicId] = { state: null, mounts: [] };
      c.loaded = fetchState(topicId).then(function (s) { c.state = s; return s; });
    }
    return c;
  }

  function renderOne(c, m) {
    var s = c.state;
    m.el.innerHTML = '';
    if (!s || !s.isQa) return;
    var isAnswer = s.solvedPostId === m.postId;
    var node;
    if (isAnswer) {
      node = document.createElement(s.canMark ? 'button' : 'span');
      node.className = 'cs-pill cs-on';
      node.innerHTML = '✓ Accepted answer';
      if (s.canMark) { node.title = 'Unmark as answer'; node.addEventListener('click', function () { toggle(c, m); }); }
    } else if (s.canMark) {
      node = document.createElement('button');
      node.className = 'cs-pill';
      node.innerHTML = '✓ Mark as answer';
      node.addEventListener('click', function () { toggle(c, m); });
    }
    if (node) m.el.appendChild(node);
  }

  function renderAll(c) { c.mounts.forEach(function (m) { renderOne(c, m); }); }

  function toggle(c, m) {
    fetch('/api/ext/solved/topic/' + m.topicId + '/post/' + m.postId, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' },
    })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (d) { if (d) { c.state.solvedPostId = d.solvedPostId; renderAll(c); } })
      .catch(function () {});
  }

  var style = document.createElement('style');
  style.textContent =
    '.cs-pill{display:inline-flex;align-items:center;gap:5px;border:1px solid rgb(var(--c-border));background:transparent;' +
    'color:rgb(var(--c-muted));border-radius:9px;padding:6px 10px;font:inherit;font-size:13px;font-weight:700;cursor:pointer}' +
    '.cs-pill:hover{color:rgb(var(--c-text));border-color:rgb(var(--c-primary))}' +
    '.cs-pill.cs-on{background:#16a34a;border-color:#16a34a;color:#fff;cursor:default}' +
    '.cs-pill.cs-on[title]{cursor:pointer}';
  document.head.appendChild(style);

  window.Convoro.registerSlot('post:actions', {
    ext: 'convoro-solved',
    order: -20,
    mount: function (el, ctx) {
      var p = (ctx && ctx.props) || {};
      if (p.isFirst || !p.topicId || !p.postId) return;
      var c = ctrl(p.topicId);
      var m = { postId: p.postId, topicId: p.topicId, el: el };
      c.mounts.push(m);
      c.loaded.then(function () { renderOne(c, m); });
      return function () {
        var i = c.mounts.indexOf(m);
        if (i >= 0) c.mounts.splice(i, 1);
      };
    },
  });
})();
