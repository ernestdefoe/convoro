<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { t } from '@/lib/i18n';

// One card used by BOTH the Marketplace store (Store/Index) and the in-app
// extensions directory (Extensions/Index) so they always look identical.
// Cover = a designed gradient (or a genuine custom screenshot) with the
// extension's icon image overlaid — exactly one icon, never a baked-in dupe.
const props = defineProps<{
  item: {
    name: string;
    type: string;
    description?: string | null;
    meta?: string | null;          // e.g. "Convoro · v1.0.0"
    icon?: string | null;          // inline SVG markup
    image?: string | null;         // a real custom screenshot (auto-covers excluded by caller)
    priceLabel?: string | null;
    free?: boolean;
    active?: boolean;              // installed/enabled on this host
    review?: { rating?: string | null; score?: number | null } | null;
    accentKey?: string | null;     // deterministic accent seed
  };
  href?: string | null;
}>();

// Per-card accent so the directory looks varied, deterministic so a card keeps
// its colour. Mirrors the palette used elsewhere in the store.
const ACCENTS = [
  ['#f472b6', '#db2777'], ['#60a5fa', '#2563eb'], ['#34d399', '#059669'], ['#fbbf24', '#d97706'],
  ['#a78bfa', '#7c3aed'], ['#22d3ee', '#0891b2'], ['#fb7185', '#e11d48'], ['#818cf8', '#4f46e5'],
];
const accent = computed(() => {
  const key = String(props.item.accentKey ?? props.item.name ?? '');
  let h = 0;
  for (let i = 0; i < key.length; i++) h = (h * 31 + key.charCodeAt(i)) >>> 0;
  return ACCENTS[h % ACCENTS.length];
});
const coverGradient = computed(() => `linear-gradient(135deg,${accent.value[0]},${accent.value[1]})`);
const coverBg = computed(() => [
  `radial-gradient(60% 85% at 15% 15%, ${accent.value[0]}cc 0%, transparent 60%)`,
  `radial-gradient(55% 80% at 88% 25%, ${accent.value[1]}b3 0%, transparent 60%)`,
  `radial-gradient(70% 90% at 65% 115%, ${accent.value[0]}80 0%, transparent 60%)`,
  `#0a0d1a`,
].join(','));
const cardStyle = computed(() => ({ background: accent.value[1] + '0d', borderColor: accent.value[1] + '47' }));

const reviewBadge = computed(() => {
  const r = props.item.review?.rating;
  if (r === 'safe') return { t: t('✓ Reviewed safe'), c: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400' };
  if (r === 'caution') return { t: t('⚠ Review: caution'), c: 'bg-amber-500/15 text-amber-600 dark:text-amber-400' };
  if (r === 'unsafe') return { t: t('⛔ Review: unsafe'), c: 'bg-red-500/15 text-red-600 dark:text-red-400' };
  return null;
});
</script>

<template>
  <component :is="href ? Link : 'div'" v-bind="href ? { href } : {}" :style="cardStyle"
    class="group flex flex-col overflow-hidden rounded-2xl border transition hover:-translate-y-0.5 hover:shadow-lg">
    <div class="relative">
      <img v-if="item.image" :src="item.image" :alt="item.name" loading="lazy" class="aspect-[2/1] w-full object-cover" />
      <div v-else class="relative flex aspect-[2/1] w-full flex-col items-center justify-center overflow-hidden px-4 text-center" :style="{ background: coverBg }">
        <span class="bg-clip-text text-2xl font-black tracking-tight text-transparent drop-shadow-sm sm:text-3xl" :style="{ backgroundImage: coverGradient, WebkitBackgroundClip: 'text' }">{{ item.name }}</span>
        <span class="mt-1 text-[11px] font-semibold uppercase tracking-[0.15em] text-white/55">{{ item.type }}</span>
      </div>
      <!-- The one icon, on the cover. Image (inline SVG) when present, else a monogram tile. -->
      <span v-if="item.icon" v-html="item.icon"
        class="absolute left-3 top-3 grid h-11 w-11 place-items-center overflow-hidden rounded-xl bg-white/95 shadow-md ring-1 ring-black/5 [&>svg]:h-7 [&>svg]:w-7"
        :style="{ color: accent[1] }"></span>
      <span v-else class="absolute left-3 top-3 grid h-11 w-11 place-items-center rounded-xl bg-white/95 text-lg font-black shadow-md ring-1 ring-black/5"
        :style="{ color: accent[1] }">{{ (item.name || '?').charAt(0).toUpperCase() }}</span>
    </div>

    <div class="flex flex-1 flex-col p-6">
      <div class="flex items-center gap-2">
        <h3 class="truncate font-bold group-hover:text-primary">{{ item.name }}</h3>
        <span class="ml-auto shrink-0 text-xs font-semibold uppercase tracking-wide text-ink-muted">{{ item.type }}</span>
      </div>
      <p v-if="item.meta" class="mt-0.5 truncate text-xs text-ink-muted">{{ item.meta }}</p>
      <p class="mt-3 line-clamp-2 text-sm text-ink-2">{{ item.description }}</p>
      <div v-if="reviewBadge" class="mt-3">
        <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-bold" :class="reviewBadge.c">{{ reviewBadge.t }}</span>
      </div>
      <div class="mt-4 flex flex-1 items-end justify-between border-t border-line pt-4">
        <span v-if="item.priceLabel" class="font-extrabold" :class="item.free ? 'text-ink-muted' : 'text-primary'">{{ item.priceLabel }}</span>
        <span v-else></span>
        <span v-if="item.active" class="rounded-full bg-emerald-500/15 px-2.5 py-0.5 text-xs font-semibold text-emerald-600">{{ t('Active') }}</span>
        <span v-else-if="href" class="text-sm font-semibold text-primary group-hover:underline">{{ t('View →') }}</span>
      </div>
    </div>
  </component>
</template>
