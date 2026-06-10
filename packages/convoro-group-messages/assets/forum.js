// Convoro extension: Group Messages (forum surface, prebuilt — no build step).
// Adds a "Groups" button to the header that opens a self-contained group-chat
// panel, talking to the extension's own /api/ext/gm/* endpoints.

const c = window.Convoro;
const csrf = () => document.querySelector('meta[name=csrf-token]')?.content || '';
const headers = () => ({ 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() });
const loggedIn = () => !!document.querySelector('meta[name=csrf-token]'); // present app-wide; gate by 401 instead

async function api(method, path, body) {
  const r = await fetch('/api/ext/gm' + path, { method, headers: headers(), body: body ? JSON.stringify(body) : undefined });
  if (r.status === 401 || r.status === 403) return null;
  return r.ok ? r.json() : null;
}

function el(tag, css, text) {
  const n = document.createElement(tag);
  if (css) n.style.cssText = css;
  if (text != null) n.textContent = text;
  return n;
}

const TOKENS = {
  surface: 'rgb(var(--c-surface, 255 255 255))',
  surface2: 'rgb(var(--c-surface-2, 243 244 249))',
  ink: 'rgb(var(--c-ink, 17 24 39))',
  ink2: 'rgb(var(--c-ink-2, 71 80 105))',
  line: 'rgb(var(--c-line, 230 232 240))',
  primary: 'rgb(var(--c-primary, 91 91 214))',
};

let overlay = null;
let activeGroup = null;

function openPanel() {
  if (overlay) { overlay.remove(); overlay = null; return; }
  overlay = el('div', 'position:fixed;inset:0;z-index:90;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.45)');
  overlay.addEventListener('click', (e) => { if (e.target === overlay) closePanel(); });

  const modal = el('div', `display:flex;width:min(860px,94vw);height:min(620px,88vh);background:${TOKENS.surface};color:${TOKENS.ink};border:1px solid ${TOKENS.line};border-radius:16px;overflow:hidden;box-shadow:0 24px 60px rgba(0,0,0,.3)`);

  // Left: list + new
  const left = el('div', `width:300px;border-right:1px solid ${TOKENS.line};display:flex;flex-direction:column`);
  const lhead = el('div', `padding:16px;border-bottom:1px solid ${TOKENS.line};font-weight:800;font-size:16px`, 'Group messages');
  const list = el('div', 'flex:1;overflow-y:auto;padding:8px');
  const newWrap = el('div', `padding:12px;border-top:1px solid ${TOKENS.line}`);
  const title = el('input'); title.placeholder = 'New group name…';
  title.style.cssText = `width:100%;padding:8px 10px;border:1px solid ${TOKENS.line};border-radius:8px;background:${TOKENS.surface2};color:${TOKENS.ink};font:inherit`;
  const members = el('input'); members.placeholder = 'Members (comma-separated usernames)';
  members.style.cssText = title.style.cssText + ';margin-top:6px';
  const createBtn = el('button', `margin-top:8px;width:100%;padding:8px;border:0;border-radius:8px;background:${TOKENS.primary};color:#fff;font-weight:700;cursor:pointer`, 'Create group');
  createBtn.addEventListener('click', async () => {
    if (!title.value.trim()) return;
    const res = await api('POST', '/groups', { title: title.value.trim(), members: members.value.trim() });
    title.value = ''; members.value = '';
    await loadGroups();
    if (res && res.id) selectGroup(res.id);
  });
  newWrap.append(title, members, createBtn);
  left.append(lhead, list, newWrap);

  // Right: messages
  const right = el('div', 'flex:1;display:flex;flex-direction:column;min-width:0');
  const rhead = el('div', `padding:16px;border-bottom:1px solid ${TOKENS.line};font-weight:700;display:flex;align-items:center`);
  const rtitle = el('span', 'flex:1', 'Select a group');
  const close = el('button', `background:none;border:0;font-size:18px;cursor:pointer;color:${TOKENS.ink2}`, '✕');
  close.addEventListener('click', closePanel);
  rhead.append(rtitle, close);
  const thread = el('div', 'flex:1;overflow-y:auto;padding:16px;display:flex;flex-direction:column;gap:8px');
  const composer = el('div', `padding:12px;border-top:1px solid ${TOKENS.line};display:flex;gap:8px`);
  const input = el('input'); input.placeholder = 'Message…'; input.disabled = true;
  input.style.cssText = `flex:1;padding:9px 12px;border:1px solid ${TOKENS.line};border-radius:9px;background:${TOKENS.surface2};color:${TOKENS.ink};font:inherit`;
  const send = el('button', `padding:9px 16px;border:0;border-radius:9px;background:${TOKENS.primary};color:#fff;font-weight:700;cursor:pointer`, 'Send');
  send.disabled = true;
  const doSend = async () => {
    if (!activeGroup || !input.value.trim()) return;
    const body = input.value.trim(); input.value = '';
    await api('POST', `/groups/${activeGroup}/messages`, { body });
    await loadMessages(activeGroup);
  };
  send.addEventListener('click', doSend);
  input.addEventListener('keydown', (e) => { if (e.key === 'Enter') doSend(); });
  composer.append(input, send);
  right.append(rhead, thread, composer);

  modal.append(left, right);
  overlay.append(modal);
  document.body.appendChild(overlay);

  // helpers bound to this panel
  async function loadGroups() {
    const groups = (await api('GET', '/groups')) || [];
    list.innerHTML = '';
    if (!groups.length) { list.append(el('div', `padding:16px;color:${TOKENS.ink2};font-size:14px`, 'No groups yet. Create one below.')); return; }
    groups.forEach((g) => {
      const item = el('button', `display:block;width:100%;text-align:left;padding:10px 12px;border:0;border-radius:9px;background:${g.id === activeGroup ? 'rgb(var(--c-primary)/0.12)' : 'transparent'};color:${TOKENS.ink};cursor:pointer`);
      item.append(el('div', 'font-weight:700;font-size:14px', g.title));
      item.append(el('div', `font-size:12px;color:${TOKENS.ink2}`, g.members.join(', ')));
      item.addEventListener('click', () => selectGroup(g.id, g.title));
      list.append(item);
    });
  }
  async function loadMessages(id) {
    const msgs = (await api('GET', `/groups/${id}/messages`)) || [];
    thread.innerHTML = '';
    msgs.forEach((m) => {
      const row = el('div', `max-width:75%;${m.mine ? 'align-self:flex-end' : 'align-self:flex-start'}`);
      const bubble = el('div', `padding:8px 12px;border-radius:14px;font-size:14px;${m.mine ? `background:${TOKENS.primary};color:#fff` : `background:${TOKENS.surface2};color:${TOKENS.ink}`}`);
      bubble.textContent = m.body;
      const meta = el('div', `font-size:11px;color:${TOKENS.ink2};margin-top:2px;${m.mine ? 'text-align:right' : ''}`, `${m.mine ? 'You' : m.author} · ${m.at}`);
      row.append(bubble, meta);
      thread.append(row);
    });
    thread.scrollTop = thread.scrollHeight;
  }
  function selectGroup(id, t) {
    activeGroup = id;
    rtitle.textContent = t || rtitle.textContent;
    input.disabled = false; send.disabled = false;
    loadGroups(); loadMessages(id);
  }
  // expose for create flow
  openPanel._loadGroups = loadGroups;
  window.__gmSelect = selectGroup;
  loadGroups();
}
function closePanel() { if (overlay) { overlay.remove(); overlay = null; activeGroup = null; } }
// rebind helpers used outside closure
async function loadGroups() { if (openPanel._loadGroups) return openPanel._loadGroups(); }
function selectGroup(id) { if (window.__gmSelect) window.__gmSelect(id); }

if (c && typeof c.registerSlot === 'function') {
  c.registerSlot('header:end', {
    ext: 'convoro-group-messages',
    order: 5,
    mount(elm) {
      const btn = el('button', `display:inline-flex;align-items:center;gap:6px;height:34px;padding:0 12px;border-radius:99px;border:1px solid ${TOKENS.line};background:${TOKENS.surface2};color:${TOKENS.ink2};font:inherit;font-size:13px;font-weight:600;cursor:pointer`);
      btn.innerHTML = '<span aria-hidden="true">👥</span> Groups';
      btn.title = 'Group messages';
      btn.addEventListener('click', openPanel);
      elm.appendChild(btn);
    },
  });
}
