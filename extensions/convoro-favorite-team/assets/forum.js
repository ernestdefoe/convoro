// Convoro extension: Favorite Team (forum surface).
// Shipped prebuilt — no build step. Loads the FBS team catalog, registers each
// team's logo as an avatar badge (rendered next to usernames by core's
// UserBadges), and adds a searchable team picker to the account settings page.
// Talks to the routes in src/Extension.php.

const c = window.Convoro;
if (c && typeof c.registerSlot === 'function') {
  const csrf = () => {
    const m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
  };
  const getJson = (url) =>
    fetch(url, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
      .then((r) => (r.ok ? r.json() : null))
      .catch(() => null);
  const postJson = (url, body) =>
    fetch(url, {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json', 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify(body || {}),
    })
      .then((r) => (r.ok ? r.json() : null))
      .catch(() => null);

  // Team catalog: array + id→team map, loaded once and shared across mounts.
  let TEAMS = [];
  const BY_ID = {};
  let loading = null;
  const loadTeams = () => {
    if (TEAMS.length) return Promise.resolve(TEAMS);
    if (loading) return loading;
    loading = getJson('/api/ext/favorite-team/teams').then((d) => {
      TEAMS = (d && d.teams) || [];
      const badges = {};
      TEAMS.forEach((t) => {
        BY_ID[t.id] = t;
        badges[t.id] = { logo: t.logo, label: t.name };
      });
      // Hand the logos to core so avatars/usernames can show the badge.
      if (typeof c.setAvatarBadges === 'function') c.setAvatarBadges(badges);
      return TEAMS;
    });
    return loading;
  };
  // Load immediately so badges resolve on the first page too.
  loadTeams();

  const heading = (text) => {
    const h = document.createElement('h3');
    h.textContent = text;
    h.style.cssText = 'margin:0 0 14px;font-size:15px;font-weight:700;color:rgb(var(--c-text,30 32 44))';
    return h;
  };

  // ── settings:account — pick your favorite team ─────────────────────────────
  function mountSettings(el) {
    let current = null; // team id

    const card = document.createElement('div');
    card.style.cssText =
      'border-radius:var(--c-radius,12px);border:1px solid rgb(var(--c-border,230 232 240));background:rgb(var(--c-surface,255 255 255));padding:24px';
    card.appendChild(heading('Favorite Team'));

    const currentRow = document.createElement('div');
    currentRow.style.cssText = 'display:flex;align-items:center;gap:10px;margin-bottom:14px;min-height:32px';
    card.appendChild(currentRow);

    const search = document.createElement('input');
    search.type = 'search';
    search.placeholder = 'Search teams…';
    search.style.cssText =
      'width:100%;box-sizing:border-box;margin-bottom:12px;border:1px solid rgb(var(--c-border,230 232 240));background:rgb(var(--c-surface-2,247 248 252));color:rgb(var(--c-text,30 32 44));border-radius:9px;padding:9px 12px;font-size:13.5px';
    card.appendChild(search);

    const grid = document.createElement('div');
    grid.style.cssText =
      'display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:6px;max-height:340px;overflow:auto';
    card.appendChild(grid);
    el.appendChild(card);

    const renderCurrent = () => {
      currentRow.innerHTML = '';
      const t = current ? BY_ID[current] : null;
      if (t) {
        const img = document.createElement('img');
        img.src = t.logo;
        img.alt = t.name;
        img.style.cssText = 'width:28px;height:28px;object-fit:contain';
        const label = document.createElement('span');
        label.innerHTML = 'Your team: <strong style="color:rgb(var(--c-text,30 32 44))">' + t.name + '</strong>';
        label.style.cssText = 'font-size:13.5px;color:rgb(var(--c-muted,138 144 166))';
        const clear = document.createElement('button');
        clear.type = 'button';
        clear.textContent = 'Clear';
        clear.style.cssText =
          'margin-left:auto;border:1px solid rgb(var(--c-border,230 232 240));background:transparent;color:#dc2626;border-radius:7px;padding:5px 11px;font-size:12.5px;cursor:pointer';
        clear.addEventListener('click', () => choose(''));
        currentRow.append(img, label, clear);
      } else {
        const label = document.createElement('span');
        label.textContent = 'No team chosen yet — pick one below.';
        label.style.cssText = 'font-size:13.5px;color:rgb(var(--c-muted,138 144 166))';
        currentRow.appendChild(label);
      }
    };

    const choose = (id) => {
      postJson('/api/ext/favorite-team/set', { teamId: id }).then((res) => {
        if (res && res.ok) {
          current = res.teamId || null;
          renderCurrent();
          paintGrid(search.value);
        }
      });
    };

    const paintGrid = (q) => {
      const term = (q || '').trim().toLowerCase();
      grid.innerHTML = '';
      TEAMS.filter((t) => !term || t.name.toLowerCase().includes(term) || (t.abbreviation || '').toLowerCase().includes(term))
        .slice(0, 400)
        .forEach((t) => {
          const btn = document.createElement('button');
          btn.type = 'button';
          const active = t.id === current;
          btn.style.cssText =
            'display:flex;align-items:center;gap:8px;text-align:left;border:1px solid ' +
            (active ? 'rgb(var(--c-primary,91 91 214))' : 'rgb(var(--c-border,230 232 240))') +
            ';background:' +
            (active ? 'rgba(var(--c-primary,91 91 214),0.08)' : 'transparent') +
            ';border-radius:9px;padding:7px 9px;font-size:12.5px;color:rgb(var(--c-text-2,60 66 88));cursor:pointer;min-width:0';
          const img = document.createElement('img');
          img.src = t.logo;
          img.alt = '';
          img.loading = 'lazy';
          img.style.cssText = 'width:22px;height:22px;object-fit:contain;flex-shrink:0';
          const name = document.createElement('span');
          name.textContent = t.shortName || t.name;
          name.style.cssText = 'overflow:hidden;text-overflow:ellipsis;white-space:nowrap';
          btn.append(img, name);
          btn.addEventListener('click', () => choose(t.id));
          grid.appendChild(btn);
        });
    };

    search.addEventListener('input', () => paintGrid(search.value));

    Promise.all([loadTeams(), getJson('/api/ext/favorite-team/me')]).then(([, me]) => {
      current = (me && me.teamId) || null;
      renderCurrent();
      paintGrid('');
    });
  }

  c.registerSlot('settings:account', { ext: 'convoro-favorite-team', order: 40, mount: mountSettings });
}
