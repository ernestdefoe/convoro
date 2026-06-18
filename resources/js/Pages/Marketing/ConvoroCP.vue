<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import MarketingLayout from '@/Layouts/MarketingLayout.vue';
import { t } from '@/lib/i18n';

const sub = useForm({});
function subscribe() {
  sub.post('/convorocp/subscribe');
}

const features = [
  { icon: '🌐', title: t('Sites & SSL'), body: t('Create a site in one click with nginx + PHP-FPM wired up, then issue free Let’s Encrypt certificates automatically.') },
  { icon: '🐘', title: t('Per-site PHP'), body: t('Install multiple PHP versions and pick one per site, with editable memory, upload limits, and disabled functions — or edit the raw php.ini.') },
  { icon: '🗄️', title: t('Databases'), body: t('Provision MySQL, MariaDB, PostgreSQL, or SQLite databases and users without touching a shell.') },
  { icon: '⏰', title: t('Cron & daemons'), body: t('Schedule cron jobs and supervise long-running daemons as real systemd units — start, stop, and restart from the panel.') },
  { icon: '🐳', title: t('Docker'), body: t('Search Docker Hub and deploy any image, with an optional reverse proxy + SSL on your own domain.') },
  { icon: '✉️', title: t('Webmail'), body: t('Read and send mail straight from the panel against your own mailboxes.') },
  { icon: '💾', title: t('Backups'), body: t('Run on-demand or scheduled backups of sites and databases, with optional off-site storage.') },
  { icon: '🖥️', title: t('Terminal & services'), body: t('A built-in web terminal and service controls for the whole node, all behind operator auth.') },
];
</script>

<template>
  <Head title="ConvoroCP — hosting control panel" />
  <MarketingLayout>
    <!-- Hero -->
    <section class="mx-auto max-w-5xl px-6 py-16 text-center">
      <span class="text-sm font-bold uppercase tracking-wide text-primary">{{ t('ConvoroCP') }}</span>
      <h1 class="mt-2 text-4xl font-black tracking-tight sm:text-5xl">{{ t('A control panel that hosts everything — including Convoro') }}</h1>
      <p class="mx-auto mt-6 max-w-2xl text-xl text-ink-2">{{ t('ConvoroCP is our self-hosted web hosting control panel — a modern, beautiful alternative to cPanel and Plesk. It provisions and manages sites, databases, email, scheduled jobs, daemons, and Docker on your own server, all from one dashboard in the Convoro look.') }}</p>
      <div class="mt-8 flex flex-wrap justify-center gap-3">
        <a href="#pricing" class="rounded-xl bg-primary px-6 py-3 font-bold text-white hover:bg-primary-600">{{ t('Start 30-day free trial') }}</a>
        <a href="#install" class="rounded-xl border border-line bg-surface px-6 py-3 font-bold text-ink-2 hover:bg-surface-2">{{ t('Install docs') }}</a>
      </div>
    </section>

    <!-- Pricing -->
    <section id="pricing" class="mx-auto max-w-md px-6 py-16">
      <div class="rounded-3xl border border-line bg-surface p-8 text-center shadow-sm">
        <h2 class="text-2xl font-black tracking-tight">{{ t('Simple pricing') }}</h2>
        <div class="mt-5 flex items-end justify-center gap-1">
          <span class="text-5xl font-black tracking-tight">$10</span>
          <span class="mb-1 text-ink-2">{{ t('/ month') }}</span>
        </div>
        <p class="mt-2 text-sm text-ink-2">{{ t('Per server. 30-day free trial — no card needed to start.') }}</p>
        <ul class="mt-6 space-y-2 text-left text-ink-2">
          <li class="flex gap-2"><span class="text-primary">✓</span>{{ t('Every feature, unlimited sites & databases') }}</li>
          <li class="flex gap-2"><span class="text-primary">✓</span>{{ t('Cancel anytime; the panel keeps your services running') }}</li>
          <li class="flex gap-2"><span class="text-primary">✓</span>{{ t('A license key you activate right in the panel') }}</li>
        </ul>
        <button type="button" @click="subscribe" :disabled="sub.processing"
          class="mt-7 w-full rounded-xl bg-primary px-6 py-3 font-bold text-white hover:bg-primary-600 disabled:opacity-60">
          {{ sub.processing ? t('Redirecting to checkout…') : t('Subscribe — $10/mo') }}
        </button>
        <p class="mt-3 text-xs text-ink-muted">{{ t('Install free and run a 30-day trial first — subscribe before it ends to keep going.') }}</p>
      </div>
    </section>

    <!-- How it works -->
    <section class="border-y border-line bg-surface/40">
      <div class="mx-auto max-w-4xl px-6 py-14">
        <h2 class="text-center text-2xl font-black tracking-tight">{{ t('Safe by design') }}</h2>
        <p class="mx-auto mt-3 max-w-2xl text-center text-ink-2">{{ t('The dashboard never runs privileged commands itself. It records what you want as desired state, and a small root agent applies it — so every change is allowlisted, auditable, and reversible.') }}</p>
        <div class="mt-8 grid gap-4 sm:grid-cols-3">
          <div class="rounded-2xl border border-line bg-surface p-6 text-center"><div class="text-3xl font-black text-primary">1</div><div class="mt-2 font-bold">{{ t('You ask') }}</div><p class="mt-1 text-sm text-ink-2">{{ t('Click in the panel — create a site, add a cron, deploy a container.') }}</p></div>
          <div class="rounded-2xl border border-line bg-surface p-6 text-center"><div class="text-3xl font-black text-primary">2</div><div class="mt-2 font-bold">{{ t('The agent applies it') }}</div><p class="mt-1 text-sm text-ink-2">{{ t('A privileged agent converges the machine using only allowlisted operations.') }}</p></div>
          <div class="rounded-2xl border border-line bg-surface p-6 text-center"><div class="text-3xl font-black text-primary">3</div><div class="mt-2 font-bold">{{ t('It just works') }}</div><p class="mt-1 text-sm text-ink-2">{{ t('Configs are validated before they go live and rolled back automatically if they fail.') }}</p></div>
        </div>
      </div>
    </section>

    <!-- Features -->
    <section class="mx-auto max-w-6xl px-6 py-16">
      <h2 class="text-center text-3xl font-black tracking-tight">{{ t('Everything your server needs') }}</h2>
      <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <div v-for="f in features" :key="f.title" class="rounded-2xl border border-line bg-surface p-6">
          <div class="text-2xl">{{ f.icon }}</div>
          <h3 class="mt-3 font-bold">{{ f.title }}</h3>
          <p class="mt-1 text-sm text-ink-2">{{ f.body }}</p>
        </div>
      </div>
    </section>

    <!-- Dogfood band -->
    <section class="bg-slate-900 py-16 text-white">
      <div class="mx-auto max-w-3xl px-6 text-center">
        <h2 class="text-2xl font-black tracking-tight sm:text-3xl">{{ t('We run Convoro on it') }}</h2>
        <p class="mx-auto mt-3 max-w-2xl text-lg text-slate-300">{{ t('This very site is hosted and managed by ConvoroCP — its scheduler, queue workers, websockets, and SSR all supervised from the panel. We trust it with our own flagship.') }}</p>
      </div>
    </section>

    <!-- Install -->
    <section id="install" class="mx-auto max-w-4xl px-6 py-16">
      <h2 class="text-center text-3xl font-black tracking-tight">{{ t('Install ConvoroCP') }}</h2>
      <p class="mx-auto mt-3 max-w-2xl text-center text-ink-2">{{ t('ConvoroCP installs on a fresh Linux server that it fully manages — it takes over nginx, PHP, SSL, mail and the firewall, so give it a box of its own, not one already running other sites.') }}</p>

      <!-- Requirements -->
      <div class="mt-10">
        <h3 class="text-sm font-bold uppercase tracking-wide text-primary">{{ t('Requirements') }}</h3>
        <ul class="mt-3 grid gap-2 text-ink-2 sm:grid-cols-2">
          <li class="flex gap-2"><span class="text-primary">✓</span>{{ t('A dedicated Linux server (Ubuntu 24.04+ recommended) with root access') }}</li>
          <li class="flex gap-2"><span class="text-primary">✓</span>{{ t('PHP 8.3+ (8.5 recommended) and Composer') }}</li>
          <li class="flex gap-2"><span class="text-primary">✓</span>{{ t('Node.js 20+ (to build the dashboard)') }}</li>
          <li class="flex gap-2"><span class="text-primary">✓</span>{{ t('A domain pointed at the server, for the panel + free SSL') }}</li>
        </ul>
      </div>

      <!-- Quickstart -->
      <div class="mt-10">
        <h3 class="text-sm font-bold uppercase tracking-wide text-primary">{{ t('Quickstart') }}</h3>
        <p class="mt-2 text-sm text-ink-2">{{ t('Run these as root on a fresh server:') }}</p>
        <pre class="mt-4 overflow-x-auto rounded-2xl border border-line bg-slate-900 p-5 text-sm leading-relaxed text-slate-100"><code># 1 · Get the code
git clone https://github.com/ernestdefoe/convorocp.git /var/www/convorocp
cd /var/www/convorocp

# 2 · Install dependencies + build the dashboard
composer install --no-dev --optimize-autoloader
npm install &amp;&amp; npm run build

# 3 · Configure
cp .env.example .env
php artisan key:generate
#   edit .env — set APP_URL, your database, and (on the real box)
#   CONVOROCP_AGENT_DRY_RUN=false so the agent applies changes for real

# 4 · Create the schema
php artisan migrate

# 5 · Run the privileged agent as root (install as a systemd service)
php artisan agent:run

# 6 · Serve the panel (php-fpm + nginx) over your domain with SSL
#   then open https://your-domain and sign in as the operator</code></pre>
        <p class="mt-4 text-sm text-ink-2">{{ t('The dashboard runs as an unprivileged user; only the small, allowlisted agent runs as root. In production, run both the agent and the panel as systemd services behind nginx.') }}</p>
      </div>

      <div class="mt-8 flex flex-wrap justify-center gap-3">
        <a href="https://github.com/ernestdefoe/convorocp" target="_blank" rel="noopener" class="rounded-xl bg-primary px-5 py-3 font-bold text-white hover:bg-primary-600">{{ t('View on GitHub') }}</a>
      </div>
    </section>

    <!-- CTA -->
    <section class="mx-auto max-w-4xl px-6 py-16 text-center">
      <h2 class="text-3xl font-black tracking-tight">{{ t('Bring your own server') }}</h2>
      <p class="mx-auto mt-3 max-w-2xl text-lg text-ink-2">{{ t('ConvoroCP installs on a fresh Linux box and takes it from there. It’s the easiest way to self-host Convoro and everything around it.') }}</p>
      <div class="mt-8 flex flex-wrap justify-center gap-3">
        <Link href="/" class="rounded-xl border border-line bg-surface px-5 py-3 font-bold text-ink-2 hover:bg-surface-2">{{ t('Back to Convoro') }}</Link>
        <Link href="/roadmap" class="rounded-xl bg-primary px-5 py-3 font-bold text-white hover:bg-primary-600">{{ t('See the roadmap') }}</Link>
      </div>
    </section>
  </MarketingLayout>
</template>
