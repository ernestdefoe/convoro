<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import MarketingLayout from '@/Layouts/MarketingLayout.vue';
import { t } from '@/lib/i18n';

defineProps<{ products: any[] }>();

const status = computed(() => (usePage().props as any).flash?.status as string | undefined);
const loggedIn = computed(() => !!(usePage().props as any).auth?.user);

// Give each card its own accent — a matching cover gradient and a faint
// full-card tint — so the directory looks varied instead of a wall of
// identical tiles. Deterministic per product so a card keeps its colour.
const ACCENTS = [
  ['#f472b6', '#db2777'],
  ['#60a5fa', '#2563eb'],
  ['#34d399', '#059669'],
  ['#fbbf24', '#d97706'],
  ['#a78bfa', '#7c3aed'],
  ['#22d3ee', '#0891b2'],
  ['#fb7185', '#e11d48'],
  ['#818cf8', '#4f46e5'],
];
function accentIdx(p: any): number {
  const key = String(p.id ?? p.slug ?? p.name ?? '');
  let h = 0;
  for (let i = 0; i < key.length; i++) h = (h * 31 + key.charCodeAt(i)) >>> 0;
  return h % ACCENTS.length;
}
function coverGradient(p: any): string {
  const [a, b] = ACCENTS[accentIdx(p)];
  return `linear-gradient(135deg,${a},${b})`;
}
// A designed, Aurora-style generated cover: a deep base with soft colour blobs,
// themed per product, so each card looks crafted rather than a flat tile.
function coverBg(p: any): string {
  const [a, b] = ACCENTS[accentIdx(p)];
  return [
    `radial-gradient(60% 85% at 15% 15%, ${a}cc 0%, transparent 60%)`,
    `radial-gradient(55% 80% at 88% 25%, ${b}b3 0%, transparent 60%)`,
    `radial-gradient(70% 90% at 65% 115%, ${a}80 0%, transparent 60%)`,
    `#0a0d1a`,
  ].join(',');
}
function cardStyle(p: any): Record<string, string> {
  const [, b] = ACCENTS[accentIdx(p)];
  // ~5% tint background, ~28% border — subtle but clearly distinct per card.
  return { background: b + '0d', borderColor: b + '47' };
}

// AI security-review badge for a card.
function reviewBadge(r: any): { t: string; c: string } | null {
  if (r?.rating === 'safe') return { t: t('✓ Reviewed safe'), c: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400' };
  if (r?.rating === 'caution') return { t: t('⚠ Review: caution'), c: 'bg-amber-500/15 text-amber-600 dark:text-amber-400' };
  if (r?.rating === 'unsafe') return { t: t('⛔ Review: unsafe'), c: 'bg-red-500/15 text-red-600 dark:text-red-400' };
  return null;
}
</script>

<template>
  <Head :title="t('Extensions & themes — {brand}', { brand: 'Convoro' })" />
  <MarketingLayout>
    <section class="mx-auto max-w-6xl px-6 py-16">
      <div v-if="status" class="mb-8 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm font-medium text-emerald-700 dark:text-emerald-300">
        {{ status }}
      </div>

      <div class="mb-10 flex flex-wrap items-end justify-between gap-4">
        <div class="max-w-2xl">
          <h1 class="text-4xl font-black tracking-tight">{{ t('Extensions & themes') }}</h1>
          <p class="mt-3 text-lg text-ink-2">{{ t('Browse the directory of Convoro extensions and themes. Free add-ons install in one click; premium ones are purchased on their detail page — you get a license key to install on any of your sites.') }}</p>
        </div>
        <div class="flex shrink-0 items-center gap-2">
          <Link v-if="loggedIn" href="/extensions/manage"
            class="inline-flex items-center gap-2 rounded-c border border-line px-4 py-2.5 text-sm font-bold text-ink-2 hover:bg-surface-2">
            {{ t('Manage yours') }}
          </Link>
          <Link href="/extensions/submit"
            class="inline-flex items-center gap-2 rounded-c bg-primary px-5 py-2.5 text-sm font-bold text-white hover:bg-primary-600">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14" /></svg>
            {{ t('Submit your extension') }}
          </Link>
        </div>
      </div>

      <div v-if="!products.length" class="rounded-2xl border border-dashed border-line p-16 text-center text-ink-muted">
        {{ t('New premium add-ons are on the way. Check back soon.') }}
      </div>

      <div v-else class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        <Link v-for="p in products" :key="p.slug" :href="`/extensions/${p.slug}`" :style="cardStyle(p)"
          class="group flex flex-col overflow-hidden rounded-2xl border transition hover:-translate-y-0.5 hover:shadow-lg">
          <div class="relative">
            <img v-if="p.image" :src="p.image" :alt="p.name" loading="lazy" class="aspect-[2/1] w-full object-cover" />
            <div v-else class="relative flex aspect-[2/1] w-full flex-col items-center justify-center overflow-hidden px-4 text-center" :style="{ background: coverBg(p) }">
              <span class="bg-clip-text text-2xl font-black tracking-tight text-transparent drop-shadow-sm sm:text-3xl" :style="{ backgroundImage: coverGradient(p), WebkitBackgroundClip: 'text' }">{{ p.name }}</span>
              <span class="mt-1 text-[11px] font-semibold uppercase tracking-[0.15em] text-white/55">{{ p.type }}</span>
            </div>
            <span v-if="p.icon" v-html="p.icon" class="absolute left-3 top-3 grid h-10 w-10 place-items-center overflow-hidden rounded-xl shadow-md ring-1 ring-white/20 [&>svg]:h-full [&>svg]:w-full"></span>
          </div>
          <div class="flex flex-1 flex-col p-6">
            <div class="flex items-center gap-2">
              <h3 class="truncate font-bold group-hover:text-primary">{{ p.name }}</h3>
              <span class="ml-auto shrink-0 text-xs font-semibold uppercase tracking-wide text-ink-muted">{{ p.type }}</span>
            </div>
            <p class="mt-3 line-clamp-2 text-sm text-ink-2">{{ p.description }}</p>
            <div v-if="reviewBadge(p.review)" class="mt-3">
              <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-bold" :class="reviewBadge(p.review)!.c">{{ reviewBadge(p.review)!.t }}</span>
            </div>
            <div class="mt-4 flex flex-1 items-end justify-between border-t border-line pt-4">
              <span class="font-extrabold" :class="p.free ? 'text-ink-muted' : 'text-primary'">{{ p.price }}</span>
              <span class="text-sm font-semibold text-primary group-hover:underline">{{ t('View →') }}</span>
            </div>
          </div>
        </Link>
      </div>
    </section>
  </MarketingLayout>
</template>
