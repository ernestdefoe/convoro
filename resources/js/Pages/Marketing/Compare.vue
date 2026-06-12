<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import MarketingLayout from '@/Layouts/MarketingLayout.vue';
import { t } from '@/lib/i18n';

// Columns after the feature label. Convoro is rendered first and highlighted.
const rivals = ['Flarum', 'Discourse', 'XenForo', 'phpBB', 'Invision'];

// Cell values: true = yes, false = no, 'partial' = limited / via add-on,
// or a short string shown verbatim. Order: [Convoro, ...rivals].
type Cell = boolean | 'partial' | string;
type Row = { label: string; v: Cell[] };

const groups: { title: string; rows: Row[] }[] = [
  {
    title: t('Member experience'),
    rows: [
      { label: t('Modern single-page interface'), v: [true, true, true, 'partial', false, 'partial'] },
      { label: t('True WYSIWYG editor (no Markdown or BBCode)'), v: [true, 'partial', 'partial', true, false, true] },
      { label: t('Realtime threads, presence & typing'), v: [true, 'partial', true, false, false, 'partial'] },
      { label: t('Installable mobile app (PWA) + web push'), v: [true, 'partial', true, false, false, 'partial'] },
      { label: t('Private messages & rich profiles'), v: [true, true, true, true, 'partial', true] },
    ],
  },
  {
    title: t('AI-native — built in, not bolted on'),
    rows: [
      { label: t('Instant answers from your own threads (Ask)'), v: [true, false, false, false, false, false] },
      { label: t('Semantic search'), v: [true, false, 'partial', false, false, false] },
      { label: t('Per-reader post auto-translation'), v: [true, false, false, false, false, false] },
      { label: t('AI writing & moderation assistant'), v: [true, false, false, false, false, false] },
      { label: t('Machine-readable build spec for AI assistants'), v: [true, false, false, false, false, false] },
    ],
  },
  {
    title: t('Customize & extend'),
    rows: [
      { label: t('Live theme editor — no code'), v: [true, false, 'partial', 'partial', false, 'partial'] },
      { label: t('Built-in marketplace'), v: [true, false, false, 'partial', 'partial', true] },
      { label: t('Install add-ons without a terminal'), v: [true, false, false, true, true, true] },
      { label: t('Multi-language UI (45+) with AI translation'), v: [true, 'partial', 'partial', 'partial', 'partial', 'partial'] },
    ],
  },
  {
    title: t('Platform & hosting'),
    rows: [
      { label: t('Runs on cheap shared hosting'), v: [true, true, false, true, true, true] },
      { label: t('Guided in-browser installer'), v: [true, 'partial', false, true, true, true] },
      { label: t('Import from other forum software'), v: [true, 'partial', true, 'partial', 'partial', true] },
      { label: t('Self-host & own your data'), v: [true, true, true, true, true, true] },
      { label: t('Open source'), v: [true, true, true, false, true, false] },
    ],
  },
  {
    title: t('Cost'),
    rows: [
      { label: t('License'), v: [t('Free'), t('Free'), t('Free / paid hosting'), t('Paid'), t('Free'), t('Paid')] },
    ],
  },
];

function isBool(v: Cell): v is boolean { return typeof v === 'boolean'; }
</script>

<template>
  <Head :title="t('Compare Convoro to other forum software')" />
  <MarketingLayout>
    <!-- Hero -->
    <section class="relative overflow-hidden">
      <div class="pointer-events-none absolute inset-x-0 -top-40 h-[380px] bg-gradient-to-b from-primary/10 to-transparent"></div>
      <div class="relative mx-auto max-w-3xl px-6 pt-20 pb-10 text-center">
        <span class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-4 py-1.5 text-sm font-bold text-primary">{{ t('⚖️ Compare') }}</span>
        <h1 class="mt-5 text-4xl font-black leading-tight tracking-tight sm:text-5xl">{{ t('How Convoro compares') }}</h1>
        <p class="mx-auto mt-5 max-w-2xl text-lg text-ink-2">
          {{ t('Every forum platform makes trade-offs. Here’s an honest, feature-by-feature look at how Convoro lines up against the software people most often switch from — Flarum, Discourse, XenForo, phpBB and Invision Community.') }}
        </p>
      </div>
    </section>

    <!-- Matrix -->
    <section class="mx-auto max-w-6xl px-6 pb-8">
      <div class="overflow-x-auto rounded-2xl border border-line">
        <table class="w-full min-w-[760px] border-collapse text-sm">
          <thead>
            <tr class="border-b border-line bg-surface">
              <th class="sticky left-0 z-10 bg-surface px-4 py-4 text-left font-bold text-ink-2">{{ t('Feature') }}</th>
              <th class="bg-primary/10 px-3 py-4 text-center font-extrabold text-primary">Convoro</th>
              <th v-for="r in rivals" :key="r" class="px-3 py-4 text-center font-bold text-ink-2">{{ r }}</th>
            </tr>
          </thead>
          <tbody>
            <template v-for="g in groups" :key="g.title">
              <tr class="bg-surface-2/60">
                <td :colspan="2 + rivals.length" class="px-4 py-2.5 text-xs font-bold uppercase tracking-wide text-ink-muted">{{ g.title }}</td>
              </tr>
              <tr v-for="row in g.rows" :key="row.label" class="border-b border-line last:border-0">
                <th scope="row" class="sticky left-0 z-10 bg-appbg px-4 py-3 text-left font-semibold text-ink">{{ row.label }}</th>
                <td v-for="(cell, i) in row.v" :key="i" class="px-3 py-3 text-center" :class="i === 0 ? 'bg-primary/[0.06]' : ''">
                  <template v-if="isBool(cell)">
                    <span v-if="cell" class="inline-grid h-6 w-6 place-items-center rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400" :aria-label="t('Yes')">
                      <svg viewBox="0 0 20 20" class="h-3.5 w-3.5" fill="currentColor"><path d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 111.4-1.4l3.3 3.3 6.8-6.8a1 1 0 011.9.5z"/></svg>
                    </span>
                    <span v-else class="text-ink-muted" :aria-label="t('No')">—</span>
                  </template>
                  <span v-else-if="cell === 'partial'" class="rounded-full bg-amber-500/15 px-2 py-0.5 text-[11px] font-semibold text-amber-600 dark:text-amber-400">{{ t('Partial') }}</span>
                  <span v-else class="font-semibold text-ink-2">{{ cell }}</span>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>

      <div class="mt-4 flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-ink-muted">
        <span class="inline-flex items-center gap-1.5"><span class="inline-grid h-4 w-4 place-items-center rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400"><svg viewBox="0 0 20 20" class="h-2.5 w-2.5" fill="currentColor"><path d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 111.4-1.4l3.3 3.3 6.8-6.8a1 1 0 011.9.5z"/></svg></span> {{ t('Built in') }}</span>
        <span class="inline-flex items-center gap-1.5"><span class="rounded-full bg-amber-500/15 px-1.5 py-0.5 font-semibold text-amber-600 dark:text-amber-400">{{ t('Partial') }}</span> {{ t('Limited, or via a paid/third-party add-on') }}</span>
        <span class="inline-flex items-center gap-1.5"><span class="text-ink-muted">—</span> {{ t('Not available') }}</span>
      </div>

      <p class="mt-6 max-w-3xl text-xs text-ink-muted">
        {{ t('Comparison reflects typical out-of-the-box capabilities of each platform’s current release as of June 2026. Competitors are excellent products, and several offer some of these features through paid or community add-ons. Convoro can import from Flarum, XenForo, phpBB, Discourse and vBulletin. Spotted something out of date? Let us know on the community.') }}
      </p>
    </section>

    <!-- CTA -->
    <section class="mx-auto max-w-6xl px-6 pb-24 pt-6">
      <div class="rounded-3xl bg-gradient-to-br from-indigo-600 to-fuchsia-500 px-8 py-14 text-center text-white">
        <h2 class="text-3xl font-extrabold tracking-tight sm:text-4xl">{{ t('Ready to make the switch?') }}</h2>
        <p class="mx-auto mt-3 max-w-2xl text-lg text-white/90">{{ t('Convoro is free and open source, and the Import wizard brings your members, categories and topics across in one pass.') }}</p>
        <div class="mt-8 flex flex-wrap justify-center gap-3.5">
          <a href="/docs/install" class="rounded-xl bg-white px-6 py-3.5 font-bold text-indigo-600 hover:bg-indigo-50">{{ t('Get started free →') }}</a>
          <Link href="/" class="rounded-xl border border-white/40 px-6 py-3.5 font-bold text-white hover:bg-white/10">{{ t('Back to home') }}</Link>
        </div>
      </div>
    </section>
  </MarketingLayout>
</template>
