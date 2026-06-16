<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { t } from '@/lib/i18n';
import { accentColors } from '@/lib/accent';
import ProductCover from '@/Components/store/ProductCover.vue';

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

// Per-card accent (deterministic) shared with the detail page via @/lib/accent.
const accentKey = computed(() => String(props.item.accentKey ?? props.item.name ?? ''));
const accent = computed(() => accentColors(accentKey.value));
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
    <ProductCover :name="item.name" :type="item.type" :image="item.image" :icon="item.icon" :accent-key="accentKey" />

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
      <div class="mt-4 flex flex-1 items-end justify-between gap-3 border-t border-line pt-4">
        <span v-if="item.priceLabel" class="font-extrabold" :class="item.free ? 'text-ink-muted' : 'text-primary'">{{ item.priceLabel }}</span>
        <span v-else></span>
        <slot name="action">
          <span v-if="item.active" class="rounded-full bg-emerald-500/15 px-2.5 py-0.5 text-xs font-semibold text-emerald-600">{{ t('Active') }}</span>
          <span v-else-if="href" class="text-sm font-semibold text-primary group-hover:underline">{{ t('View →') }}</span>
        </slot>
      </div>
    </div>
  </component>
</template>
