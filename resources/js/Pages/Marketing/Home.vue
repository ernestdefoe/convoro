<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import MarketingLayout from '@/Layouts/MarketingLayout.vue';

defineProps<{ featured: any[] }>();

const features = [
  { icon: '🎨', title: 'Live theme editor', body: 'Recolor and reskin your whole community from the admin, with a real-time preview. No CSS required.' },
  { icon: '✍️', title: 'True WYSIWYG', body: 'Rich text via TipTap with drag-and-drop images auto-converted to WebP. Never see Markdown.' },
  { icon: '⚡', title: 'Live threads', body: 'Realtime posts, presence, and typing indicators — with a graceful polling fallback.' },
  { icon: '🧩', title: 'Built-in marketplace', body: 'Browse, install, and configure extensions and themes from the admin. No terminal needed.' },
  { icon: '📱', title: 'PWA & push', body: 'Installs to the home screen with push notifications. One image becomes every icon size.' },
  { icon: '👤', title: 'Profiles & DMs', body: 'Full profiles with cover images and a wall, plus private messaging — no add-on required.' },
  { icon: '♿', title: 'Accessibility first', body: 'ADA-minded throughout — keyboard nav, skip links, focus states, and a compliance dashboard.' },
  { icon: '🔑', title: 'Tokens & permissions', body: 'Core API access tokens, user groups, and a granular permission system built in.' },
  { icon: '🔔', title: 'Notifications & digests', body: 'In-app, push, and scheduled email digests keep members coming back.' },
];
</script>

<template>
  <Head title="Convoro — modern community forum software" />
  <MarketingLayout>
    <!-- Hero -->
    <section class="relative overflow-hidden">
      <div class="pointer-events-none absolute inset-x-0 -top-40 h-[420px] bg-gradient-to-b from-primary/10 to-transparent"></div>
      <div class="relative mx-auto max-w-4xl px-6 pt-24 pb-16 text-center">
        <span class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-4 py-1.5 text-sm font-bold text-primary">✨ Where AI meets community innovation</span>
        <h1 class="mt-6 text-5xl font-black leading-[1.05] tracking-tight sm:text-6xl">
          Build a community that feels
          <span class="bg-gradient-to-r from-indigo-500 to-fuchsia-500 bg-clip-text text-transparent">premium</span>.
        </h1>
        <p class="mx-auto mt-6 max-w-2xl text-xl text-ink-2">
          Convoro is batteries-included forum software — live theme editor, true WYSIWYG posting, reactions, profiles, DMs, a built-in marketplace, and PWA push. Install it in a browser, even on cheap shared hosting.
        </p>
        <div class="mt-9 flex flex-wrap justify-center gap-3.5">
          <a href="/docs/install.html" class="rounded-xl bg-primary px-6 py-3.5 font-bold text-white shadow-xl shadow-primary/30 hover:bg-primary-600">Install in minutes →</a>
          <a href="https://community.convoro.co/demo" class="rounded-xl border border-line bg-surface px-6 py-3.5 font-bold text-ink-2 hover:bg-surface-2">Try the live demo</a>
        </div>
      </div>
      <div class="mx-auto max-w-5xl px-6 pb-8">
        <div class="overflow-hidden rounded-2xl border border-line shadow-2xl shadow-black/10">
          <img src="/site-assets/hero.png" alt="The Convoro community interface" class="w-full" loading="lazy" />
        </div>
      </div>
    </section>

    <!-- Features -->
    <section class="mx-auto max-w-6xl px-6 py-20">
      <div class="mx-auto mb-12 max-w-2xl text-center">
        <h2 class="text-3xl font-extrabold tracking-tight sm:text-4xl">Everything a community needs — in the box</h2>
        <p class="mt-3 text-lg text-ink-2">The proven features of leading forum software, reimagined into one cohesive, modern platform.</p>
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
        <h2 class="text-3xl font-extrabold tracking-tight sm:text-4xl">Runs anywhere — even $3 shared hosting</h2>
        <p class="mx-auto mt-3 max-w-2xl text-lg text-slate-300">No SSH? No problem. Upload, unzip, and finish setup in your browser. Scale up to a VPS with Redis and realtime when you grow.</p>
        <div class="mt-10 grid gap-5 text-left sm:grid-cols-3">
          <div class="rounded-2xl border border-white/10 bg-white/5 p-6"><div class="text-2xl">📦</div><h3 class="mt-3 font-bold">One-click installer</h3><p class="mt-1 text-sm text-slate-300">A guided wizard writes config, migrates, and creates your admin — no command line.</p></div>
          <div class="rounded-2xl border border-white/10 bg-white/5 p-6"><div class="text-2xl">🗄️</div><h3 class="mt-3 font-bold">MySQL · MariaDB · Postgres</h3><p class="mt-1 text-sm text-slate-300">Bring whatever database your host gives you. No Redis required.</p></div>
          <div class="rounded-2xl border border-white/10 bg-white/5 p-6"><div class="text-2xl">🚀</div><h3 class="mt-3 font-bold">Scales to a VPS</h3><p class="mt-1 text-sm text-slate-300">Add Redis, Reverb websockets, and Horizon queues when you’re ready.</p></div>
        </div>
      </div>
    </section>

    <!-- Built for the AI era -->
    <section class="mx-auto max-w-6xl px-6 py-20">
      <div class="overflow-hidden rounded-3xl border border-primary/20 bg-primary/[0.04] p-8 sm:p-12">
        <div class="grid items-center gap-10 lg:grid-cols-2">
          <div>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 text-xs font-bold text-primary">🤖 AI-native</span>
            <h2 class="mt-4 text-3xl font-extrabold tracking-tight sm:text-4xl">Built for the AI era — and proud of it</h2>
            <p class="mt-3 text-lg text-ink-2">Convoro doesn't just tolerate AI — it embraces it. Build themes and extensions <em>with</em> your favorite AI assistant. We publish a machine-readable build spec, so models like Claude can scaffold a correct, installable Convoro extension or theme on the first try.</p>
            <div class="mt-6 flex flex-wrap gap-3">
              <a href="/docs/extensions.html" class="rounded-xl bg-primary px-5 py-3 font-bold text-white shadow-lg shadow-primary/25 hover:bg-primary-600">Build with AI →</a>
              <a href="/llms.txt" class="rounded-xl border border-line bg-surface px-5 py-3 font-bold text-ink-2 hover:bg-surface-2">View the AI spec</a>
            </div>
          </div>
          <div class="rounded-2xl border border-line bg-ink p-5 font-mono text-sm text-slate-200 shadow-xl">
            <div class="mb-3 flex gap-1.5"><span class="h-3 w-3 rounded-full bg-red-400"></span><span class="h-3 w-3 rounded-full bg-amber-400"></span><span class="h-3 w-3 rounded-full bg-emerald-400"></span></div>
            <p class="text-emerald-300">$ curl convoro.co/llms.txt</p>
            <p class="mt-1 text-slate-400"># Everything an AI needs to build a</p>
            <p class="text-slate-400"># Convoro theme or extension —</p>
            <p class="text-slate-400"># manifest, slots, tokens, publish flow.</p>
            <p class="mt-2 text-slate-200">→ paste it to your model. Ship.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- Featured products -->
    <section v-if="featured.length" class="mx-auto max-w-6xl px-6 py-20">
      <div class="mb-10 flex items-end justify-between">
        <div><h2 class="text-3xl font-extrabold tracking-tight">Extensions &amp; themes</h2><p class="mt-2 text-ink-2">Extend further with first-party and community add-ons.</p></div>
        <Link href="/extensions" class="hidden font-bold text-primary hover:text-primary-600 sm:block">Browse extensions →</Link>
      </div>
      <div class="grid gap-5 sm:grid-cols-3">
        <Link v-for="p in featured" :key="p.slug" :href="`/extensions/${p.slug}`" class="group rounded-2xl border border-line bg-surface p-6 transition hover:shadow-lg">
          <div class="grid h-12 w-12 place-items-center rounded-xl bg-primary/10 text-2xl">{{ p.type === 'theme' ? '🎨' : '🧩' }}</div>
          <h3 class="mt-4 font-bold group-hover:text-primary">{{ p.name }}</h3>
          <p class="mt-1 line-clamp-2 text-sm text-ink-2">{{ p.tagline }}</p>
          <div class="mt-4 font-extrabold text-primary">{{ p.price }}</div>
        </Link>
      </div>
    </section>

    <!-- CTA -->
    <section class="mx-auto max-w-6xl px-6 pb-24 pt-4">
      <div class="rounded-3xl bg-gradient-to-br from-indigo-600 to-fuchsia-500 px-8 py-16 text-center text-white">
        <h2 class="text-3xl font-extrabold tracking-tight sm:text-4xl">Ready to launch your community?</h2>
        <p class="mt-3 text-lg text-white/90">Download Convoro and be online in minutes.</p>
        <div class="mt-8 flex flex-wrap justify-center gap-3.5">
          <a href="/docs/install.html" class="rounded-xl bg-white px-6 py-3.5 font-bold text-indigo-600 hover:bg-indigo-50">Read the install guide</a>
          <a href="https://community.convoro.co/demo" class="rounded-xl border border-white/40 px-6 py-3.5 font-bold text-white hover:bg-white/10">Try it live</a>
        </div>
      </div>
    </section>
  </MarketingLayout>
</template>
