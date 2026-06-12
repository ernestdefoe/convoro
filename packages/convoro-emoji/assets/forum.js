/*
 * Convoro Emoji Picker — forum bundle (prebuilt ESM, vanilla JS).
 * Registers an emoji-picker button into the composer toolbar via the Convoro
 * extension runtime (window.Convoro). Uses the host forum's CSS variables so it
 * matches the active light/dark theme. No build step required.
 */
(function () {
  if (!window.Convoro || typeof window.Convoro.registerSlot !== 'function') return;

  var STYLE_ID = 'convoro-emoji-styles';
  var RECENT_KEY = 'convoro_emoji_recent';

  // Curated, popular emoji set: category -> [[emoji, name], ...].
  var DATA = {
    'Smileys': [
      ['😀','grin'],['😃','smile'],['😄','happy'],['😁','beam'],['😆','laugh'],['😅','sweat'],['🤣','rofl'],['😂','joy'],
      ['🙂','slight'],['🙃','upside'],['😉','wink'],['😊','blush'],['😇','angel'],['🥰','love'],['😍','heart eyes'],['🤩','star'],
      ['😘','kiss'],['😋','yum'],['😜','tongue'],['🤪','zany'],['😎','cool'],['🥳','party'],['🤔','think'],['🤨','raised brow'],
      ['😐','neutral'],['😶','no mouth'],['🙄','roll eyes'],['😏','smirk'],['😴','sleep'],['🤤','drool'],['😪','sleepy'],['😫','tired'],
      ['😢','cry'],['😭','sob'],['😤','triumph'],['😠','angry'],['😡','rage'],['🤯','mind blown'],['😳','flushed'],['🥺','pleading'],
      ['😬','grimace'],['🤥','lying'],['🤒','sick'],['🤧','sneeze'],['🥵','hot'],['🥶','cold'],['😵','dizzy'],['🤐','zipper'],
    ],
    'Gestures': [
      ['👍','thumbs up'],['👎','thumbs down'],['👌','ok'],['🤌','pinch'],['✌️','victory'],['🤞','crossed'],['🤟','love you'],['🤘','rock'],
      ['👏','clap'],['🙌','raise hands'],['👐','open hands'],['🤝','handshake'],['🙏','pray thanks'],['👋','wave'],['🤙','call me'],['💪','muscle'],
      ['👆','point up'],['👇','point down'],['👈','point left'],['👉','point right'],['✋','raised hand'],['🖐️','hand'],['🤚','back hand'],['👊','fist'],
    ],
    'Hearts': [
      ['❤️','red heart'],['🧡','orange heart'],['💛','yellow heart'],['💚','green heart'],['💙','blue heart'],['💜','purple heart'],['🖤','black heart'],['🤍','white heart'],
      ['💔','broken'],['❣️','exclaim heart'],['💕','two hearts'],['💖','sparkle heart'],['💗','growing'],['💘','arrow heart'],['💝','gift heart'],['💯','hundred'],
    ],
    'Animals': [
      ['🐶','dog'],['🐱','cat'],['🐭','mouse'],['🐹','hamster'],['🐰','rabbit'],['🦊','fox'],['🐻','bear'],['🐼','panda'],
      ['🐨','koala'],['🐯','tiger'],['🦁','lion'],['🐮','cow'],['🐷','pig'],['🐸','frog'],['🐵','monkey'],['🐔','chicken'],
      ['🐧','penguin'],['🐦','bird'],['🦅','eagle'],['🦉','owl'],['🐝','bee'],['🦋','butterfly'],['🐢','turtle'],['🐬','dolphin'],
    ],
    'Food': [
      ['🍎','apple'],['🍌','banana'],['🍓','strawberry'],['🍑','peach'],['🍕','pizza'],['🍔','burger'],['🌮','taco'],['🍟','fries'],
      ['🍩','donut'],['🍪','cookie'],['🎂','cake'],['🍫','chocolate'],['🍿','popcorn'],['☕','coffee'],['🍺','beer'],['🍷','wine'],
      ['🥤','soda'],['🧋','boba'],['🍦','ice cream'],['🥑','avocado'],
    ],
    'Activities': [
      ['⚽','soccer'],['🏀','basketball'],['🏈','football'],['⚾','baseball'],['🎾','tennis'],['🎮','game'],['🎲','dice'],['🎯','target'],
      ['🎸','guitar'],['🎧','headphones'],['🎤','mic'],['🏆','trophy'],['🥇','gold'],['🎉','tada'],['🎊','confetti'],['🎈','balloon'],
    ],
    'Travel': [
      ['🚗','car'],['✈️','plane'],['🚀','rocket'],['🚲','bike'],['🏠','house'],['🏖️','beach'],['🌍','earth'],['🗺️','map'],
      ['⛰️','mountain'],['🌋','volcano'],['🏝️','island'],['🌃','night'],
    ],
    'Objects': [
      ['💻','laptop'],['📱','phone'],['⌨️','keyboard'],['🖥️','desktop'],['💡','idea'],['🔧','wrench'],['🔨','hammer'],['🔑','key'],
      ['📌','pin'],['📎','clip'],['✏️','pencil'],['📷','camera'],['🔔','bell'],['🔒','lock'],['💰','money'],['🎁','gift'],
    ],
    'Symbols': [
      ['✅','check'],['❌','cross'],['⭐','star'],['🔥','fire'],['✨','sparkles'],['⚡','zap'],['💥','boom'],['❓','question'],
      ['❗','exclaim'],['⚠️','warning'],['♻️','recycle'],['✔️','tick'],['➕','plus'],['➖','minus'],['💤','zzz'],['🆗','ok'],
    ],
  };
  var CATEGORIES = Object.keys(DATA);

  function injectStyles() {
    if (document.getElementById(STYLE_ID)) return;
    var css =
      '.cv-emoji-pop{position:fixed;z-index:90;width:316px;max-width:92vw;background:rgb(var(--c-surface));' +
      'border:1px solid rgb(var(--c-border));border-radius:14px;box-shadow:0 12px 40px rgba(0,0,0,.22);' +
      'overflow:hidden;font-family:inherit;color:rgb(var(--c-text))}' +
      '.cv-emoji-search{width:100%;box-sizing:border-box;border:0;border-bottom:1px solid rgb(var(--c-border));' +
      'background:rgb(var(--c-surface-2));color:rgb(var(--c-text));padding:9px 12px;font-size:13px;outline:none}' +
      '.cv-emoji-search::placeholder{color:rgb(var(--c-muted))}' +
      '.cv-emoji-tabs{display:flex;gap:2px;padding:6px;overflow-x:auto;border-bottom:1px solid rgb(var(--c-border));scrollbar-width:none}' +
      '.cv-emoji-tabs::-webkit-scrollbar{display:none}' +
      '.cv-emoji-tab{flex:0 0 auto;border:0;background:none;cursor:pointer;font-size:17px;line-height:1;padding:5px 7px;border-radius:8px;opacity:.65}' +
      '.cv-emoji-tab:hover{background:rgb(var(--c-surface-2));opacity:1}' +
      '.cv-emoji-tab.on{background:rgb(var(--c-primary)/.15);opacity:1}' +
      '.cv-emoji-grid{height:232px;overflow-y:auto;padding:6px 8px 10px}' +
      '.cv-emoji-head{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:rgb(var(--c-muted));padding:8px 4px 4px;position:sticky;top:0;background:rgb(var(--c-surface))}' +
      '.cv-emoji-row{display:grid;grid-template-columns:repeat(8,1fr)}' +
      '.cv-emoji-btn{border:0;background:none;cursor:pointer;font-size:20px;line-height:1;padding:5px 0;border-radius:8px}' +
      '.cv-emoji-btn:hover{background:rgb(var(--c-surface-2))}' +
      '.cv-emoji-empty{color:rgb(var(--c-muted));font-size:13px;text-align:center;padding:24px}' +
      '.cv-emoji-toggle{position:relative;display:grid;place-items:center;width:30px;height:30px;border-radius:7px;' +
      'color:rgb(var(--c-text-2));cursor:pointer;border:0;background:none;font-size:17px;line-height:1}' +
      '.cv-emoji-toggle:hover{background:rgb(var(--c-surface));box-shadow:0 0 0 1px rgb(var(--c-border))}';
    var style = document.createElement('style');
    style.id = STYLE_ID;
    style.textContent = css;
    document.head.appendChild(style);
  }

  function getRecents() {
    try { return JSON.parse(localStorage.getItem(RECENT_KEY) || '[]').slice(0, 16); } catch (e) { return []; }
  }
  function pushRecent(emoji) {
    try {
      var r = getRecents().filter(function (e) { return e !== emoji; });
      r.unshift(emoji);
      localStorage.setItem(RECENT_KEY, JSON.stringify(r.slice(0, 16)));
    } catch (e) {}
  }

  function gridButton(emoji, name, onPick) {
    var b = document.createElement('button');
    b.type = 'button';
    b.className = 'cv-emoji-btn';
    b.textContent = emoji;
    b.title = name || '';
    b.addEventListener('mousedown', function (ev) { ev.preventDefault(); }); // keep editor focus
    b.addEventListener('click', function () { onPick(emoji); });
    return b;
  }

  window.Convoro.registerSlot('composer:toolbar', {
    ext: 'convoro-emoji',
    order: 5,
    mount: function (el, ctx) {
      injectStyles();
      var insert = ctx && ctx.props && typeof ctx.props.insertText === 'function' ? ctx.props.insertText : null;

      var toggle = document.createElement('button');
      toggle.type = 'button';
      toggle.className = 'cv-emoji-toggle';
      toggle.title = 'Emoji';
      toggle.setAttribute('aria-label', 'Insert emoji');
      toggle.textContent = '😊';
      el.appendChild(toggle);

      var pop = null;
      var activeCat = CATEGORIES[0];

      function pick(emoji) {
        if (insert) insert(emoji + ' ');
        pushRecent(emoji);
        // keep the popup open for quick multi-insert
        renderGrid(currentQuery());
      }

      var searchEl, gridEl, tabsEl;
      function currentQuery() { return (searchEl && searchEl.value || '').trim().toLowerCase(); }

      function renderGrid(query) {
        gridEl.innerHTML = '';
        if (query) {
          var matches = [];
          CATEGORIES.forEach(function (cat) {
            DATA[cat].forEach(function (pair) {
              if (pair[1].indexOf(query) !== -1) matches.push(pair);
            });
          });
          if (!matches.length) { gridEl.innerHTML = '<div class="cv-emoji-empty">No emoji found</div>'; return; }
          var row = document.createElement('div'); row.className = 'cv-emoji-row';
          matches.forEach(function (p) { row.appendChild(gridButton(p[0], p[1], pick)); });
          gridEl.appendChild(row);
          return;
        }
        var recents = getRecents();
        if (recents.length) {
          var h = document.createElement('div'); h.className = 'cv-emoji-head'; h.textContent = 'Recent'; gridEl.appendChild(h);
          var rr = document.createElement('div'); rr.className = 'cv-emoji-row';
          recents.forEach(function (e) { rr.appendChild(gridButton(e, '', pick)); });
          gridEl.appendChild(rr);
        }
        var list = DATA[activeCat] || [];
        var hd = document.createElement('div'); hd.className = 'cv-emoji-head'; hd.textContent = activeCat; gridEl.appendChild(hd);
        var row2 = document.createElement('div'); row2.className = 'cv-emoji-row';
        list.forEach(function (p) { row2.appendChild(gridButton(p[0], p[1], pick)); });
        gridEl.appendChild(row2);
      }

      function position() {
        if (!pop) return;
        var r = toggle.getBoundingClientRect();
        var w = 316;
        var left = Math.min(Math.max(8, r.left), window.innerWidth - w - 8);
        var top = r.bottom + 6;
        // flip above if it would overflow the viewport bottom
        if (top + 300 > window.innerHeight) top = Math.max(8, r.top - 306);
        pop.style.left = left + 'px';
        pop.style.top = top + 'px';
      }

      function onDocDown(ev) {
        if (pop && !pop.contains(ev.target) && ev.target !== toggle) close();
      }
      function onKey(ev) { if (ev.key === 'Escape') close(); }

      function open() {
        if (pop) return;
        pop = document.createElement('div');
        pop.className = 'cv-emoji-pop';
        searchEl = document.createElement('input');
        searchEl.className = 'cv-emoji-search';
        searchEl.type = 'text';
        searchEl.placeholder = 'Search emoji…';
        searchEl.addEventListener('input', function () { renderGrid(currentQuery()); });
        tabsEl = document.createElement('div'); tabsEl.className = 'cv-emoji-tabs';
        CATEGORIES.forEach(function (cat) {
          var tb = document.createElement('button');
          tb.type = 'button';
          tb.className = 'cv-emoji-tab' + (cat === activeCat ? ' on' : '');
          tb.textContent = DATA[cat][0][0];
          tb.title = cat;
          tb.addEventListener('mousedown', function (ev) { ev.preventDefault(); });
          tb.addEventListener('click', function () {
            activeCat = cat;
            Array.prototype.forEach.call(tabsEl.children, function (c) { c.classList.remove('on'); });
            tb.classList.add('on');
            if (searchEl.value) searchEl.value = '';
            renderGrid('');
          });
          tabsEl.appendChild(tb);
        });
        gridEl = document.createElement('div'); gridEl.className = 'cv-emoji-grid';
        pop.appendChild(searchEl); pop.appendChild(tabsEl); pop.appendChild(gridEl);
        document.body.appendChild(pop);
        renderGrid('');
        position();
        setTimeout(function () {
          document.addEventListener('mousedown', onDocDown);
          document.addEventListener('keydown', onKey);
          window.addEventListener('resize', position);
          window.addEventListener('scroll', position, true);
        }, 0);
      }
      function close() {
        if (!pop) return;
        document.removeEventListener('mousedown', onDocDown);
        document.removeEventListener('keydown', onKey);
        window.removeEventListener('resize', position);
        window.removeEventListener('scroll', position, true);
        pop.remove();
        pop = null;
      }
      toggle.addEventListener('click', function () { pop ? close() : open(); });

      // cleanup when the slot host is torn down
      return function () { close(); if (toggle.parentNode) toggle.parentNode.removeChild(toggle); };
    },
  });
})();
