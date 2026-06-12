<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import MarketingLayout from '@/Layouts/MarketingLayout.vue';
import { t } from '@/lib/i18n';

const props = defineProps<{ catalog: any[] }>();

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
          <a href="/docs/install" class="rounded-xl bg-primary px-6 py-3.5 font-bold text-white shadow-xl shadow-primary/30 hover:bg-primary-600">{{ t('Install in minutes →') }}</a>
          <a href="https://demo.convoro.co/demo" class="rounded-xl border border-line bg-surface px-6 py-3.5 font-bold text-ink-2 hover:bg-surface-2">{{ t('Try the live demo') }}</a>
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
          <div class="rounded-2xl border border-line bg-ink p-5 font-mono text-sm text-slate-200 shadow-xl">
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

    <!-- CTA -->
    <section class="mx-auto max-w-6xl px-6 pb-24 pt-4">
      <div class="rounded-3xl bg-gradient-to-br from-indigo-600 to-fuchsia-500 px-8 py-16 text-center text-white">
        <h2 class="text-3xl font-extrabold tracking-tight sm:text-4xl">{{ t('Ready to launch your community?') }}</h2>
        <p class="mt-3 text-lg text-white/90">{{ t('Download Convoro and be online in minutes.') }}</p>
        <div class="mt-8 flex flex-wrap justify-center gap-3.5">
          <a href="/docs/install.html" class="rounded-xl bg-white px-6 py-3.5 font-bold text-indigo-600 hover:bg-indigo-50">{{ t('Read the install guide') }}</a>
          <a href="https://demo.convoro.co/demo" class="rounded-xl border border-white/40 px-6 py-3.5 font-bold text-white hover:bg-white/10">{{ t('Try it live') }}</a>
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
