<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>Install Convoro</title>
  <style>
    :root { --c: #5b5bd6; --ink: #1f2430; --muted: #6b7280; --line: #e6e8f0; --bg: #f3f4f9; --card: #fff; }
    * { box-sizing: border-box; }
    body { margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Inter, sans-serif; background: var(--bg); color: var(--ink); }
    .wrap { max-width: 640px; margin: 0 auto; padding: 48px 20px 80px; }
    .brand { display: flex; align-items: center; gap: 12px; margin-bottom: 8px; }
    .logo { width: 40px; height: 40px; border-radius: 11px; background: linear-gradient(135deg,#6366f1,#8b5cf6,#a855f7); position: relative; }
    .logo::before, .logo::after, .logo i { content:""; position:absolute; width:7px; height:7px; border-radius:50%; background:#fff; }
    .logo::before { top:11px; left:16px; } .logo::after { bottom:12px; left:10px; } .logo i { bottom:12px; right:10px; }
    h1 { font-size: 26px; margin: 0; font-weight: 800; letter-spacing: -.02em; }
    .sub { color: var(--muted); margin: 4px 0 28px; }
    .card { background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 28px; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
    .steps { display: flex; gap: 8px; margin-bottom: 22px; }
    .dot { flex: 1; height: 4px; border-radius: 99px; background: var(--line); }
    .dot.on { background: var(--c); }
    h2 { font-size: 18px; margin: 0 0 4px; font-weight: 700; }
    .step-sub { color: var(--muted); font-size: 14px; margin: 0 0 18px; }
    label { display: block; font-size: 13px; font-weight: 600; margin: 14px 0 6px; }
    input, select { width: 100%; padding: 10px 12px; border: 1px solid var(--line); border-radius: 10px; font-size: 14px; background: #fff; color: var(--ink); }
    input:focus, select:focus { outline: none; border-color: var(--c); box-shadow: 0 0 0 3px rgba(91,91,214,.15); }
    .row { display: flex; gap: 12px; } .row > * { flex: 1; }
    .check { display: flex; align-items: center; gap: 10px; padding: 8px 0; font-size: 14px; border-bottom: 1px solid var(--line); }
    .check:last-child { border-bottom: 0; }
    .badge { width: 20px; height: 20px; border-radius: 50%; display: grid; place-items: center; color: #fff; font-size: 12px; flex-shrink: 0; }
    .ok { background: #16a34a; } .bad { background: #dc2626; }
    .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 11px 18px; border-radius: 10px; border: 0; font-size: 14px; font-weight: 700; cursor: pointer; background: var(--c); color: #fff; }
    .btn:disabled { opacity: .55; cursor: not-allowed; }
    .btn.ghost { background: transparent; color: var(--muted); }
    .actions { display: flex; align-items: center; gap: 10px; margin-top: 24px; }
    .actions .spacer { flex: 1; }
    .msg { margin-top: 14px; padding: 10px 12px; border-radius: 10px; font-size: 13px; display: none; }
    .msg.err { background: #fef2f2; color: #b91c1c; display: block; }
    .msg.good { background: #ecfdf5; color: #047857; display: block; }
    .hidden { display: none; }
    .done { text-align: center; padding: 20px 0; }
    .done .big { font-size: 44px; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="brand"><span class="logo"><i></i></span><h1>Convoro</h1></div>
    <p class="sub">Let's get your community online. This takes about a minute.</p>

    <div class="card">
      <div class="steps">
        <div class="dot on" data-dot="0"></div>
        <div class="dot" data-dot="1"></div>
        <div class="dot" data-dot="2"></div>
      </div>

      {{-- Step 1: Requirements --}}
      <section data-step="0">
        <h2>Server requirements</h2>
        <p class="step-sub">Convoro needs a few things to run.</p>
        <div>
          @foreach ($requirements['checks'] as $c)
            <div class="check">
              <span class="badge {{ $c['pass'] ? 'ok' : 'bad' }}">{{ $c['pass'] ? '✓' : '✕' }}</span>
              <span>{{ $c['label'] }}</span>
            </div>
          @endforeach
        </div>
        <div class="actions">
          <div class="spacer"></div>
          <button class="btn" id="toDb" {{ $requirements['ok'] ? '' : 'disabled' }}>Continue</button>
        </div>
        @unless ($requirements['ok'])
          <div class="msg err">Please resolve the items marked ✕, then reload this page.</div>
        @endunless
      </section>

      {{-- Step 2: Database --}}
      <section data-step="1" class="hidden">
        <h2>Database</h2>
        <p class="step-sub">Create an empty database, then enter its details.</p>
        <label>Engine</label>
        <select id="db_driver">
          @foreach ($drivers as $d)
            <option value="{{ $d['value'] }}" data-port="{{ $d['port'] }}">{{ $d['label'] }}</option>
          @endforeach
        </select>
        <div class="row">
          <div><label>Host</label><input id="db_host" value="127.0.0.1"></div>
          <div style="max-width:120px"><label>Port</label><input id="db_port" value="3306"></div>
        </div>
        <label>Database name</label><input id="db_database" placeholder="convoro">
        <div class="row">
          <div><label>Username</label><input id="db_username"></div>
          <div><label>Password</label><input id="db_password" type="password"></div>
        </div>
        <div class="msg" id="dbMsg"></div>
        <div class="actions">
          <button class="btn ghost" data-back="0">Back</button>
          <div class="spacer"></div>
          <button class="btn" id="testDb">Test connection</button>
          <button class="btn hidden" id="toSite">Continue</button>
        </div>
      </section>

      {{-- Step 3: Site + admin --}}
      <section data-step="2" class="hidden">
        <h2>Your community &amp; admin account</h2>
        <p class="step-sub">Final step — name your site and create the owner account.</p>
        <label>Community name</label><input id="site_name" placeholder="My Community">
        <label>Site URL</label><input id="site_url" value="{{ $appUrl }}">
        <div class="row">
          <div><label>Admin name</label><input id="admin_name"></div>
          <div><label>Admin email</label><input id="admin_email" type="email"></div>
        </div>
        <label>Admin password <span style="color:var(--muted);font-weight:400">(min 8 characters)</span></label>
        <input id="admin_password" type="password">
        <div class="msg" id="runMsg"></div>
        <div class="actions">
          <button class="btn ghost" data-back="1">Back</button>
          <div class="spacer"></div>
          <button class="btn" id="run">Install Convoro</button>
        </div>
      </section>

      {{-- Done --}}
      <section data-step="3" class="hidden">
        <div class="done">
          <div class="big">🎉</div>
          <h2>You're all set!</h2>
          <p class="step-sub">Convoro is installed. Redirecting you to your new community…</p>
        </div>
      </section>
    </div>
  </div>

  <script>
    const csrf = document.querySelector('meta[name=csrf-token]').content;
    const $ = (s) => document.querySelector(s);
    const sections = [...document.querySelectorAll('[data-step]')];
    const dots = [...document.querySelectorAll('[data-dot]')];

    function go(step) {
      sections.forEach((s) => s.classList.toggle('hidden', +s.dataset.step !== step));
      dots.forEach((d) => d.classList.toggle('on', +d.dataset.dot <= step));
      window.scrollTo(0, 0);
    }
    document.querySelectorAll('[data-back]').forEach((b) => b.addEventListener('click', () => go(+b.dataset.back)));
    $('#toDb').addEventListener('click', () => go(1));

    // keep port in sync with engine
    $('#db_driver').addEventListener('change', (e) => {
      $('#db_port').value = e.target.selectedOptions[0].dataset.port;
    });

    function dbPayload() {
      return {
        driver: $('#db_driver').value, host: $('#db_host').value.trim(),
        port: $('#db_port').value.trim(), database: $('#db_database').value.trim(),
        username: $('#db_username').value, password: $('#db_password').value,
      };
    }
    async function post(url, body) {
      const r = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        body: JSON.stringify(body),
      });
      return { ok: r.ok, data: await r.json().catch(() => ({})) };
    }
    function showMsg(el, text, good) {
      el.textContent = text; el.className = 'msg ' + (good ? 'good' : 'err');
    }

    $('#testDb').addEventListener('click', async () => {
      const btn = $('#testDb'); btn.disabled = true; btn.textContent = 'Testing…';
      const { ok, data } = await post('{{ route('install.testdb') }}', dbPayload());
      btn.disabled = false; btn.textContent = 'Test connection';
      showMsg($('#dbMsg'), data.message || (ok ? 'OK' : 'Failed'), ok);
      $('#toSite').classList.toggle('hidden', !ok);
    });
    $('#toSite').addEventListener('click', () => go(2));

    $('#run').addEventListener('click', async () => {
      const btn = $('#run'); btn.disabled = true; btn.textContent = 'Installing…';
      const payload = {
        site: { name: $('#site_name').value.trim(), url: $('#site_url').value.trim() },
        db: dbPayload(),
        admin: { name: $('#admin_name').value.trim(), email: $('#admin_email').value.trim(), password: $('#admin_password').value },
      };
      const { ok, data } = await post('{{ route('install.run') }}', payload);
      if (ok && data.ok) {
        go(3);
        setTimeout(() => (window.location = data.redirect || '/'), 1500);
      } else {
        btn.disabled = false; btn.textContent = 'Install Convoro';
        showMsg($('#runMsg'), data.message || 'Installation failed. Please check your details.', false);
      }
    });
  </script>
</body>
</html>
