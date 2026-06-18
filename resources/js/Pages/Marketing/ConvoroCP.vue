<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import MarketingLayout from '@/Layouts/MarketingLayout.vue';
import { t } from '@/lib/i18n';

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
