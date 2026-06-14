<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import MarketingLayout from '@/Layouts/MarketingLayout.vue';
import { t } from '@/lib/i18n';

const props = defineProps<{ catalog: any[]; plans?: any[]; getStartedUrl?: string }>();

const plans = computed(() => props.plans ?? []);
const getStartedUrl = computed(() => props.getStartedUrl ?? '/get-started');
function planUrl(id?: string) { return getStartedUrl.value + (id ? `?plan=${id}` : ''); }
function selfHostUrl() { return getStartedUrl.value + '?mode=self'; }

// The CTA always invites a personal, on-demand demo request — the old shared
// one-click demo (demo.convoro.co) was retired in favour of per-request demos.
const demoHref = computed(() => '/request-demo');
const demoLabel = computed(() => t('Request a demo'));

// Duplicate the list so the marquee can scroll seamlessly (only when there's
// enough to scroll); a short list just centers.
const loop = computed(() => (props.catalog.length > 3 ? [...props.catalog, ...props.catalog] : props.catalog));
const marquee = computed(() => props.catalog.length > 3);

const features = [
  { icon: '🎨', title: t('Live theme editor'), body: t('Recolor and reskin your whole community from the admin, with a real-time preview. No CSS required.') },
  { icon: '✍️', title: t('True WYSIWYG'), body: t('Rich text via TipTap with drag-and-drop images auto-converted to WebP. Never see Markdown.') },
  { icon: '⚡', title: t('Live threads'), body: t('Realtime posts, presence, and typing indicators — with a graceful polling fallback.') },
  { icon: '🧩', title: t('Built-in marketplace'), body: t('Browse, install, and configure extensions and themes from the admin. No terminal needed.') },
  { icon: '📱', title: t('PWA & push'), body: t('Installs to the home screen with push notifications. One image becomes every icon size.') },
  { icon: '👤', title: t('Profiles & DMs'), body: t('Full profiles with cover images and a wall, plus private messaging — no add-on required.') },
  { icon: '♿', title: t('Accessibility first'), body: t('ADA-minded throughout — keyboard nav, skip links, focus states, and a compliance dashboard.') },
  { icon: '🔑', title: t('Tokens & permissions'), body: t('Core API access tokens, user groups, and a granular permission system built in.') },
  { icon: '🔔', title: t('Notifications & digests'), body: t('In-app, push, and scheduled email digests keep members coming back.') },
];

// The modern, open-source stack Convoro is built on. Logos are the official
// single-path marks (simple-icons), tinted with each brand's color.
const stack = [
  { name: 'Laravel', hex: '#FF2D20', d: 'M23.642 5.43a.364.364 0 01.014.1v5.149c0 .135-.073.26-.189.326l-4.323 2.49v4.934a.378.378 0 01-.188.326L9.93 23.949a.316.316 0 01-.066.027c-.008.002-.016.008-.024.01a.348.348 0 01-.192 0c-.011-.002-.02-.008-.03-.012-.02-.008-.042-.014-.062-.025L.533 18.755a.376.376 0 01-.189-.326V2.974c0-.033.005-.066.014-.098.003-.012.01-.02.014-.032a.369.369 0 01.023-.058c.004-.013.015-.022.023-.033l.033-.045c.012-.01.025-.018.037-.027.014-.012.027-.024.041-.034H.53L5.043.05a.375.375 0 01.375 0L9.93 2.647h.002c.015.01.027.021.04.033l.038.027c.013.014.02.03.033.045.008.011.02.021.025.033.01.02.017.038.024.058.003.011.01.021.013.032.01.031.014.064.014.098v9.652l3.76-2.164V5.527c0-.033.004-.066.013-.098.003-.01.01-.02.013-.032a.487.487 0 01.024-.059c.007-.012.018-.02.025-.033.012-.015.021-.03.033-.043.012-.012.025-.02.037-.028.014-.01.026-.023.041-.032h.001l4.513-2.598a.375.375 0 01.375 0l4.513 2.598c.016.01.027.021.042.031.012.01.025.018.036.028.013.014.022.03.034.044.008.012.019.021.024.033.011.02.018.04.024.06.006.01.012.021.015.032zm-.74 5.032V6.179l-1.578.908-2.182 1.256v4.283zm-4.51 7.75v-4.287l-2.147 1.225-6.126 3.498v4.325zM1.093 3.624v14.588l8.273 4.761v-4.325l-4.322-2.445-.002-.003H5.04c-.014-.01-.025-.021-.04-.031-.011-.01-.024-.018-.035-.027l-.001-.002c-.013-.012-.021-.025-.031-.04-.01-.011-.021-.022-.028-.036h-.002c-.008-.014-.013-.031-.02-.047-.006-.016-.014-.027-.018-.043a.49.49 0 01-.008-.057c-.002-.014-.006-.027-.006-.041V5.789l-2.18-1.257zM5.23.81L1.47 2.974l3.76 2.164 3.758-2.164zm1.956 13.505l2.182-1.256V3.624l-1.58.91-2.182 1.255v9.435zm11.581-10.95l-3.76 2.163 3.76 2.163 3.759-2.164zm-.376 4.978L16.21 7.087 14.63 6.18v4.283l2.182 1.256 1.58.908zm-8.65 9.654l5.514-3.148 2.756-1.572-3.757-2.163-4.323 2.489-3.941 2.27z' },
  { name: 'Vue.js', hex: '#42B883', d: 'M24,1.61H14.06L12,5.16,9.94,1.61H0L12,22.39ZM12,14.08,5.16,2.23H9.59L12,6.41l2.41-4.18h4.43Z' },
  { name: 'Inertia.js', hex: '#9553E9', d: 'M6.901 5.331H0L6.669 12 0 18.669h6.901L13.571 12 6.9 5.331zm10.43 0H10.43L17.099 12l-6.67 6.669h6.902L24 12l-6.669-6.669z' },
  { name: 'Tailwind CSS', hex: '#38BDF8', d: 'M12.001,4.8c-3.2,0-5.2,1.6-6,4.8c1.2-1.6,2.6-2.2,4.2-1.8c0.913,0.228,1.565,0.89,2.288,1.624 C13.666,10.618,15.027,12,18.001,12c3.2,0,5.2-1.6,6-4.8c-1.2,1.6-2.6,2.2-4.2,1.8c-0.913-0.228-1.565-0.89-2.288-1.624 C16.337,6.182,14.976,4.8,12.001,4.8z M6.001,12c-3.2,0-5.2,1.6-6,4.8c1.2-1.6,2.6-2.2,4.2-1.8c0.913,0.228,1.565,0.89,2.288,1.624 c1.177,1.194,2.538,2.576,5.512,2.576c3.2,0,5.2-1.6,6-4.8c-1.2,1.6-2.6,2.2-4.2,1.8c-0.913-0.228-1.565-0.89-2.288-1.624 C10.337,13.382,8.976,12,6.001,12z' },
  { name: 'TypeScript', hex: '#3178C6', d: 'M1.125 0C.502 0 0 .502 0 1.125v21.75C0 23.498.502 24 1.125 24h21.75c.623 0 1.125-.502 1.125-1.125V1.125C24 .502 23.498 0 22.875 0zm17.363 9.75c.612 0 1.154.037 1.627.111a6.38 6.38 0 0 1 1.306.34v2.458a3.95 3.95 0 0 0-.643-.361 5.093 5.093 0 0 0-.717-.26 5.453 5.453 0 0 0-1.426-.2c-.3 0-.573.028-.819.086a2.1 2.1 0 0 0-.623.242c-.17.104-.3.229-.393.374a.888.888 0 0 0-.14.49c0 .196.053.373.156.529.104.156.252.304.443.444s.423.276.696.41c.273.135.582.274.926.416.47.197.892.407 1.266.628.374.222.695.473.963.753.268.279.472.598.614.957.142.359.214.776.214 1.253 0 .657-.125 1.21-.373 1.656a3.033 3.033 0 0 1-1.012 1.085 4.38 4.38 0 0 1-1.487.596c-.566.12-1.163.18-1.79.18a9.916 9.916 0 0 1-1.84-.164 5.544 5.544 0 0 1-1.512-.493v-2.63a5.033 5.033 0 0 0 3.237 1.2c.333 0 .624-.03.872-.09.249-.06.456-.144.623-.25.166-.108.29-.234.373-.38a1.023 1.023 0 0 0-.074-1.089 2.12 2.12 0 0 0-.537-.5 5.597 5.597 0 0 0-.807-.444 27.72 27.72 0 0 0-1.007-.436c-.918-.383-1.602-.852-2.053-1.405-.45-.553-.676-1.222-.676-2.005 0-.614.123-1.141.369-1.582.246-.441.58-.804 1.004-1.089a4.494 4.494 0 0 1 1.47-.629 7.536 7.536 0 0 1 1.77-.201zm-15.113.188h9.563v2.166H9.506v9.646H6.789v-9.646H3.375z' },
  { name: 'Vite', hex: '#646CFF', d: 'M13.056 23.238a.57.57 0 0 1-1.02-.355v-5.202c0-.63-.512-1.143-1.144-1.143H5.148a.57.57 0 0 1-.464-.903l3.777-5.29c.54-.753 0-1.804-.93-1.804H.57a.574.574 0 0 1-.543-.746.6.6 0 0 1 .08-.157L5.008.78a.57.57 0 0 1 .467-.24h14.589a.57.57 0 0 1 .466.903l-3.778 5.29c-.54.755 0 1.806.93 1.806h5.745c.238 0 .424.138.513.322a.56.56 0 0 1-.063.603z' },
];
</script>

<template>
  <Head title="Convoro — modern community forum software" />
  <MarketingLayout>
    <!-- Hero -->
    <section class="relative overflow-hidden">
      <div class="pointer-events-none absolute inset-x-0 -top-40 h-[420px] bg-gradient-to-b from-primary/10 to-transparent"></div>
      <div class="relative mx-auto max-w-4xl px-6 pt-24 pb-16 text-center">
        <span class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-4 py-1.5 text-sm font-bold text-primary">{{ t('✨ The AI-native community platform') }}</span>
        <h1 class="mt-6 text-5xl font-black leading-[1.05] tracking-tight sm:text-6xl">
          {{ t('The forum that') }}
          <span class="bg-gradient-to-r from-indigo-500 to-fuchsia-500 bg-clip-text text-transparent">{{ t('answers itself') }}</span>.
        </h1>
        <p class="mx-auto mt-6 max-w-2xl text-xl text-ink-2">
          {{ t("Convoro puts AI at the core of community. Members ask a question and get an instant answer drawn from your community's own threads — with citations. Every discussion makes the forum smarter. Batteries-included, beautiful, and runnable on cheap shared hosting.") }}
        </p>
        <div class="mt-9 flex flex-wrap justify-center gap-3.5">
          <a v-if="plans.length" :href="planUrl()" class="rounded-xl bg-primary px-6 py-3.5 font-bold text-white shadow-xl shadow-primary/30 hover:bg-primary-600">{{ t('Start your community →') }}</a>
          <a v-else href="/docs/install" class="rounded-xl bg-primary px-6 py-3.5 font-bold text-white shadow-xl shadow-primary/30 hover:bg-primary-600">{{ t('Install in minutes →') }}</a>
          <a :href="demoHref" class="rounded-xl border border-line bg-surface px-6 py-3.5 font-bold text-ink-2 hover:bg-surface-2">{{ demoLabel }}</a>
        </div>
      </div>
      <div class="mx-auto max-w-5xl px-6 pb-8">
        <div class="overflow-hidden rounded-2xl border border-line shadow-2xl shadow-black/10">
          <img src="/site-assets/hero.png" :alt="t('The Convoro community interface')" class="w-full" loading="lazy" />
        </div>
      </div>
    </section>

    <!-- Ask Convoro — the AI-native headline -->
    <section class="mx-auto max-w-6xl px-6 py-20">
      <div class="overflow-hidden rounded-3xl border border-primary/20 bg-gradient-to-br from-primary/[0.07] via-primary/[0.03] to-transparent p-8 sm:p-12">
        <div class="grid items-center gap-10 lg:grid-cols-2">
          <div>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 text-xs font-bold text-primary">{{ t('Ask Convoro') }}</span>
            <h2 class="mt-4 text-3xl font-extrabold tracking-tight sm:text-4xl">{{ t('Your community’s knowledge, instantly answerable') }}</h2>
            <p class="mt-3 text-lg text-ink-2">{{ t('Every forum slowly buries its best answers. Convoro surfaces them. Ask a question in plain language and get a synthesized answer with citations to the real threads — powered by semantic search over your community’s content.') }}</p>
            <ul class="mt-5 space-y-2.5 text-ink-2">
              <li class="flex gap-2.5"><span class="text-primary">→</span> <span><b class="text-ink">{{ t('Useful on day one.') }}</b> {{ t('Even a brand-new community can answer questions, so it never feels empty.') }}</span></li>
              <li class="flex gap-2.5"><span class="text-primary">→</span> <span><b class="text-ink">{{ t('Gets smarter with every post.') }}</b> {{ t('The more members discuss, the better the answers — your content becomes a compounding moat.') }}</span></li>
              <li class="flex gap-2.5"><span class="text-primary">→</span> <span><b class="text-ink">{{ t('Always cited.') }}</b> {{ t('Answers link back to the threads they came from, so members can dig deeper and trust the source.') }}</span></li>
            </ul>
            <p class="mt-5 text-sm text-ink-muted">{{ t('Bring your own model — any Anthropic or OpenAI-compatible LLM, plus Voyage AI for semantic search.') }}</p>
          </div>
          <div class="rounded-2xl border border-line bg-surface p-5 shadow-xl shadow-black/5">
            <div class="flex items-center gap-2 text-sm font-bold text-ink"><span class="grid h-6 w-6 place-items-center rounded-md bg-primary text-white">✦</span> {{ t('Ask the community') }}</div>
            <div class="mt-3 rounded-lg bg-surface-2 px-3 py-2 text-sm text-ink-2">{{ t('How do I move my forum from Flarum?') }}</div>
            <div class="mt-3 text-sm leading-relaxed text-ink">{{ t('Open') }} <b>{{ t('Admin → Import') }}</b> {{ t('and connect your Flarum database — members, categories, topics and posts come across, and members keep their passwords') }} <span class="text-primary">[1]</span>{{ t('. Embedded images and avatars carry over too') }} <span class="text-primary">[2]</span>.</div>
            <div class="mt-3 border-t border-line pt-3 text-xs text-ink-muted">{{ t('Sources') }} <span class="text-primary">{{ t('[1] Migrating from Flarum') }}</span> · <span class="text-primary">{{ t('[2] Importing media') }}</span></div>
          </div>
        </div>
      </div>
    </section>

    <!-- Features -->
    <section class="mx-auto max-w-6xl px-6 py-20">
      <div class="mx-auto mb-12 max-w-2xl text-center">
        <h2 class="text-3xl font-extrabold tracking-tight sm:text-4xl">{{ t('Everything a community needs — in the box') }}</h2>
        <p class="mt-3 text-lg text-ink-2">{{ t('The proven features of leading forum software, reimagined into one cohesive, modern platform.') }}</p>
      </div>
      <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        <div v-for="f in features" :key="f.title" class="rounded-2xl border border-line bg-surface p-7 transition hover:shadow-lg hover:shadow-black/5">
          <div class="grid h-11 w-11 place-items-center rounded-xl bg-primary/10 text-xl">{{ f.icon }}</div>
          <h3 class="mt-4 text-lg font-bold">{{ f.title }}</h3>
          <p class="mt-1.5 text-ink-2">{{ f.body }}</p>
        </div>
      </div>
    </section>

    <!-- Built on — the modern open-source stack -->
    <section class="border-y border-line bg-surface/40">
      <div class="mx-auto max-w-6xl px-6 py-16">
        <div class="mx-auto mb-10 max-w-2xl text-center">
          <span class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 text-xs font-bold text-primary">{{ t('🛠️ Built on') }}</span>
          <h2 class="mt-4 text-3xl font-extrabold tracking-tight sm:text-4xl">{{ t('A modern, open-source foundation') }}</h2>
          <p class="mt-3 text-lg text-ink-2">{{ t('No bespoke black box — Convoro stands on the same battle-tested tools millions of developers already know and trust.') }}</p>
        </div>
        <div class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
          <div v-for="s in stack" :key="s.name" class="flex flex-col items-center gap-3 rounded-2xl border border-line bg-surface px-4 py-6 transition hover:shadow-lg hover:shadow-black/5" :title="s.name">
            <svg viewBox="0 0 24 24" class="h-9 w-9" :fill="s.hex" aria-hidden="true"><path :d="s.d" /></svg>
            <span class="text-sm font-bold text-ink-2">{{ s.name }}</span>
          </div>
        </div>
        <p class="mt-8 text-center text-sm text-ink-muted">{{ t('Plus Laravel Reverb for websockets, Valkey / Redis for cache & queues, and MySQL · MariaDB · PostgreSQL for storage.') }}</p>
      </div>
    </section>

    <!-- Hosting band (intentionally dark in both themes) -->
    <section class="bg-slate-900 py-20 text-white">
      <div class="mx-auto max-w-4xl px-6 text-center">
        <h2 class="text-3xl font-extrabold tracking-tight sm:text-4xl">{{ t('Runs anywhere — even $3 shared hosting') }}</h2>
        <p class="mx-auto mt-3 max-w-2xl text-lg text-slate-300">{{ t('No SSH? No problem. Upload, unzip, and finish setup in your browser. Scale up to a VPS with Redis and realtime when you grow.') }}</p>
        <div class="mt-10 grid gap-5 text-left sm:grid-cols-3">
          <div class="rounded-2xl border border-white/10 bg-white/5 p-6"><div class="text-2xl">📦</div><h3 class="mt-3 font-bold">{{ t('One-click installer') }}</h3><p class="mt-1 text-sm text-slate-300">{{ t('A guided wizard writes config, migrates, and creates your admin — no command line.') }}</p></div>
          <div class="rounded-2xl border border-white/10 bg-white/5 p-6"><div class="text-2xl">🗄️</div><h3 class="mt-3 font-bold">{{ t('MySQL · MariaDB · Postgres') }}</h3><p class="mt-1 text-sm text-slate-300">{{ t('Bring whatever database your host gives you. No Redis required.') }}</p></div>
          <div class="rounded-2xl border border-white/10 bg-white/5 p-6"><div class="text-2xl">🚀</div><h3 class="mt-3 font-bold">{{ t('Scales to a VPS') }}</h3><p class="mt-1 text-sm text-slate-300">{{ t('Add Redis, Reverb websockets, and Horizon queues when you’re ready.') }}</p></div>
        </div>
      </div>
    </section>

    <!-- Built for the AI era -->
    <section class="mx-auto max-w-6xl px-6 py-20">
      <div class="overflow-hidden rounded-3xl border border-primary/20 bg-primary/[0.04] p-8 sm:p-12">
        <div class="grid items-center gap-10 lg:grid-cols-2">
          <div>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 text-xs font-bold text-primary">{{ t('🤖 AI-native') }}</span>
            <h2 class="mt-4 text-3xl font-extrabold tracking-tight sm:text-4xl">{{ t('Built for the AI era — and proud of it') }}</h2>
            <p class="mt-3 text-lg text-ink-2">{{ t("Convoro doesn't just tolerate AI — it embraces it. Build themes and extensions with your favorite AI assistant. We publish a machine-readable build spec, so models like Claude can scaffold a correct, installable Convoro extension or theme on the first try.") }}</p>
            <div class="mt-6 flex flex-wrap gap-3">
              <a href="/docs/extensions" class="rounded-xl bg-primary px-5 py-3 font-bold text-white shadow-lg shadow-primary/25 hover:bg-primary-600">{{ t('Build with AI →') }}</a>
              <a href="/llms.txt" class="rounded-xl border border-line bg-surface px-5 py-3 font-bold text-ink-2 hover:bg-surface-2">{{ t('View the AI spec') }}</a>
            </div>
          </div>
          <div class="rounded-2xl border border-slate-700 bg-slate-900 p-5 font-mono text-sm text-slate-200 shadow-xl">
            <div class="mb-3 flex gap-1.5"><span class="h-3 w-3 rounded-full bg-red-400"></span><span class="h-3 w-3 rounded-full bg-amber-400"></span><span class="h-3 w-3 rounded-full bg-emerald-400"></span></div>
            <p class="text-emerald-300">$ curl convoro.co/llms.txt</p>
            <p class="mt-1 text-slate-400">{{ t('# Everything an AI needs to build a') }}</p>
            <p class="text-slate-400">{{ t('# Convoro theme or extension —') }}</p>
            <p class="text-slate-400">{{ t('# manifest, slots, tokens, publish flow.') }}</p>
            <p class="mt-2 text-slate-200">{{ t('→ paste it to your model. Ship.') }}</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Free & open -->
    <section class="mx-auto max-w-4xl px-6 py-16">
      <div class="rounded-3xl border border-emerald-500/30 bg-emerald-500/[0.06] p-8 text-center sm:p-12">
        <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/15 px-3 py-1 text-xs font-bold text-emerald-600 dark:text-emerald-400">{{ t('💚 Free & open') }}</span>
        <h2 class="mt-4 text-3xl font-extrabold tracking-tight sm:text-4xl">{{ t('The whole platform is free. Forever.') }}</h2>
        <p class="mx-auto mt-3 max-w-2xl text-lg text-ink-2">{{ t('Convoro is free to download and self-host — no seats, no feature gates, no trials. The only paid part is the optional marketplace, where you can buy premium add-ons built by the community.') }}</p>
        <div class="mt-7 flex flex-wrap justify-center gap-3">
          <a href="/docs/install" class="rounded-xl bg-primary px-6 py-3.5 font-bold text-white shadow-lg shadow-primary/25 hover:bg-primary-600">{{ t('Get started free →') }}</a>
          <Link href="/extensions" class="rounded-xl border border-line bg-surface px-6 py-3.5 font-bold text-ink-2 hover:bg-surface-2">{{ t('Browse the marketplace') }}</Link>
        </div>
      </div>
    </section>

    <!-- Switching? Compare -->
    <section class="mx-auto max-w-6xl px-6 py-12">
      <div class="flex flex-col items-center justify-between gap-5 rounded-3xl border border-line bg-surface p-8 text-center sm:flex-row sm:text-left">
        <div>
          <h2 class="text-2xl font-extrabold tracking-tight">{{ t('Coming from another forum?') }}</h2>
          <p class="mt-1.5 text-ink-2">{{ t('See how Convoro stacks up against Flarum, Discourse, XenForo, phpBB and Invision Community — feature by feature.') }}</p>
        </div>
        <Link href="/compare" class="shrink-0 rounded-xl bg-primary px-6 py-3.5 font-bold text-white shadow-lg shadow-primary/25 hover:bg-primary-600">{{ t('Compare forum software →') }}</Link>
      </div>
    </section>

    <!-- Extensions & themes — auto-scrolling carousel of the whole catalog -->
    <section v-if="catalog.length" class="overflow-hidden py-20">
      <div class="mx-auto mb-10 flex max-w-6xl items-end justify-between px-6">
        <div><h2 class="text-3xl font-extrabold tracking-tight">{{ t('Extensions & themes') }}</h2><p class="mt-2 text-ink-2">{{ t('Extend further with first-party and community add-ons — {n} and counting.', { n: catalog.length }) }}</p></div>
        <Link href="/extensions" class="hidden font-bold text-primary hover:text-primary-600 sm:block">{{ t('Browse all →') }}</Link>
      </div>

      <div class="marquee" :class="{ 'is-static': !marquee }">
        <div class="marquee-track" :class="{ animate: marquee }">
          <Link v-for="(p, i) in loop" :key="p.slug + '-' + i" :href="`/extensions/${p.slug}`"
            class="marquee-card group flex flex-col overflow-hidden rounded-2xl border border-line bg-surface transition hover:shadow-lg hover:shadow-black/5">
            <img v-if="p.image" :src="p.image" :alt="p.name" loading="lazy" class="aspect-[2/1] w-full object-cover" />
            <div v-else class="grid aspect-[2/1] w-full place-items-center bg-primary/10 text-4xl">{{ p.type === 'theme' ? '🎨' : '🧩' }}</div>
            <div class="flex flex-1 flex-col p-5">
              <div class="flex items-center gap-2">
                <h3 class="truncate font-bold group-hover:text-primary">{{ p.name }}</h3>
                <span class="ml-auto shrink-0 text-[11px] font-semibold uppercase tracking-wide text-ink-muted">{{ p.type }}</span>
              </div>
              <p class="mt-1.5 line-clamp-2 h-10 text-sm text-ink-2">{{ p.tagline }}</p>
              <div class="mt-3 flex items-center justify-between">
                <span class="font-extrabold" :class="p.free ? 'text-ink-muted' : 'text-primary'">{{ p.price }}</span>
                <span v-if="p.review?.rating === 'safe'" class="rounded-full bg-emerald-500/15 px-2 py-0.5 text-[10px] font-bold text-emerald-600 dark:text-emerald-400">{{ t('✓ reviewed') }}</span>
              </div>
            </div>
          </Link>
        </div>
      </div>

      <div class="mt-8 text-center sm:hidden">
        <Link href="/extensions" class="font-bold text-primary hover:text-primary-600">{{ t('Browse all extensions →') }}</Link>
      </div>
    </section>

    <!-- Pricing (managed hosting) -->
    <section v-if="plans.length" id="pricing" class="mx-auto max-w-6xl px-6 py-16">
      <div class="text-center">
        <h2 class="text-3xl font-extrabold tracking-tight sm:text-4xl">{{ t('Let us host it for you') }}</h2>
        <p class="mx-auto mt-3 max-w-2xl text-lg text-ink-2">{{ t('Every plan includes unlimited members and unlimited bandwidth — you only pick how much storage you need. Migrate your existing forum in minutes.') }}</p>
      </div>
      <div class="mt-10 grid gap-5 sm:grid-cols-3">
        <div v-for="(p, i) in plans" :key="p.id" class="relative rounded-3xl border bg-surface p-6" :class="i === 1 ? 'border-primary shadow-xl shadow-primary/10' : 'border-line'">
          <div v-if="i === 1" class="absolute -top-3 left-1/2 -translate-x-1/2 rounded-full bg-primary px-3 py-0.5 text-xs font-bold text-white">{{ t('Most popular') }}</div>
          <div class="text-sm font-black uppercase tracking-wide text-ink-2">{{ p.name }}</div>
          <div class="mt-2"><span class="text-4xl font-black text-ink">{{ p.price }}</span><span class="text-ink-muted">/mo</span></div>
          <div class="mt-1 text-sm font-bold text-primary">{{ p.storageGb }} GB storage</div>
          <p class="mt-2 text-sm text-ink-2">{{ p.blurb }}</p>
          <ul class="mt-4 space-y-1.5 text-sm text-ink-2">
            <li class="flex gap-2"><span class="text-primary">✓</span> {{ t('Unlimited members') }}</li>
            <li class="flex gap-2"><span class="text-primary">✓</span> {{ t('Unlimited bandwidth') }}</li>
            <li class="flex gap-2"><span class="text-primary">✓</span> {{ t('Custom domain + HTTPS') }}</li>
            <li class="flex gap-2"><span class="text-primary">✓</span> {{ t('Import from any forum') }}</li>
          </ul>
          <a :href="planUrl(p.id)" class="mt-5 block rounded-xl px-4 py-2.5 text-center font-bold" :class="i === 1 ? 'bg-primary text-white hover:bg-primary-600' : 'border border-line text-ink hover:bg-surface-2'">{{ t('Get started') }}</a>
        </div>
      </div>
      <p class="mt-6 text-center text-sm text-ink-muted">
        {{ t('Prefer to host it yourself?') }}
        <a :href="selfHostUrl()" class="font-semibold text-primary hover:underline">{{ t('Self-host Convoro for free →') }}</a>
      </p>
    </section>

    <!-- CTA -->
    <section class="mx-auto max-w-6xl px-6 pb-24 pt-4">
      <div class="rounded-3xl bg-gradient-to-br from-indigo-600 to-fuchsia-500 px-8 py-16 text-center text-white">
        <h2 class="text-3xl font-extrabold tracking-tight sm:text-4xl">{{ t('Ready to launch your community?') }}</h2>
        <p class="mt-3 text-lg text-white/90">{{ t('Download Convoro and be online in minutes.') }}</p>
        <div class="mt-8 flex flex-wrap justify-center gap-3.5">
          <a href="/docs/install.html" class="rounded-xl bg-white px-6 py-3.5 font-bold text-indigo-600 hover:bg-indigo-50">{{ t('Read the install guide') }}</a>
          <a :href="demoHref" class="rounded-xl border border-white/40 px-6 py-3.5 font-bold text-white hover:bg-white/10">{{ demoLabel }}</a>
        </div>
      </div>
    </section>
  </MarketingLayout>
</template>

<style scoped>
.marquee {
  width: 100%;
  -webkit-mask-image: linear-gradient(to right, transparent, #000 6%, #000 94%, transparent);
  mask-image: linear-gradient(to right, transparent, #000 6%, #000 94%, transparent);
}
.marquee.is-static { display: flex; justify-content: center; }
.marquee-track {
  display: flex;
  gap: 1.25rem;
  width: max-content;
  padding: 0 0.75rem;
}
.marquee-track.animate {
  animation: marquee-scroll 60s linear infinite;
}
.marquee:hover .marquee-track.animate { animation-play-state: paused; }
.marquee-card {
  flex: 0 0 300px;
  width: 300px;
}
@keyframes marquee-scroll {
  from { transform: translateX(0); }
  to { transform: translateX(-50%); }
}
@media (prefers-reduced-motion: reduce) {
  .marquee-track.animate { animation: none; }
  .marquee { overflow-x: auto; }
}
</style>
