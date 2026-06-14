<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { t } from '@/lib/i18n';

interface Item { id: string; name: string; version: string; description: string; author: string; type: string; premium: boolean; price: number | string; active: boolean; icon?: string | null }
const props = defineProps<{ items: Item[] }>();

const filter = ref<'all' | 'extension' | 'theme'>('all');
const query = ref('');
const counts = computed(() => ({
  all: props.items.length,
  extension: props.items.filter((e) => e.type === 'extension').length,
  theme: props.items.filter((e) => e.type === 'theme').length,
}));
const pills = [
  { key: 'all' as const, label: t('All') },
  { key: 'extension' as const, label: t('Extensions') },
  { key: 'theme' as const, label: t('Themes') },
];
const visible = computed(() => {
  const q = query.value.trim().toLowerCase();
  return props.items.filter((e) => {
    if (filter.value !== 'all' && e.type !== filter.value) return false;
    if (!q) return true;
    return [e.name, e.description, e.author, e.id].some((v) => (v || '').toLowerCase().includes(q));
  });
});

function priceLabel(item: Item): string {
  if (!item.premium) return t('Free');
  const n = Number(item.price);
  return n > 0 ? `$${n}` : t('Premium');
}

// Give each card icon-tile its own colour so the grid feels varied while the
// layout stays identical. Deterministic per item so a card keeps its colour.
const TILE_ACCENTS = [
  'text-rose-500',
  'text-sky-500',
  'text-emerald-500',
  'text-amber-500',
  'text-violet-500',
  'text-fuchsia-500',
  'text-cyan-500',
  'text-indigo-500',
];
function tileAccent(item: Item): string {
  const key = String(item.id || item.name || '');
  let h = 0;
  for (let i = 0; i < key.length; i++) h = (h * 31 + key.charCodeAt(i)) >>> 0;
  return TILE_ACCENTS[h % TILE_ACCENTS.length];
}
</script>

<template>
  <Head :title="t('Extensions & Themes')" />
  <AppLayout>
    <div class="mx-auto max-w-[var(--c-container)]">
      <!-- Hero -->
      <header class="mb-6 rounded-c border border-line bg-surface p-7 sm:p-9">
        <h1 class="text-3xl font-extrabold tracking-tight sm:text-4xl">{{ t('Extensions & Themes') }}</h1>
        <p class="mt-2 max-w-2xl text-ink-2">{{ t('Extend your community — add features and reskin it with one click. Free and premium add-ons, all built for Convoro.') }}</p>
      </header>

      <!-- Controls -->
      <div class="mb-5 flex flex-wrap items-center gap-3">
        <div class="flex items-center gap-1 rounded-c border border-line bg-surface p-1">
          <button v-for="p in pills" :key="p.key" type="button" @click="filter = p.key"
            class="rounded-lg px-3 py-1.5 text-sm font-semibold transition"
            :class="filter === p.key ? 'bg-primary text-white' : 'text-ink-2 hover:bg-surface-2'">
            {{ p.label }} <span class="opacity-70">{{ counts[p.key] }}</span>
          </button>
        </div>
        <div class="ml-auto flex items-center gap-2 rounded-full border border-line bg-surface px-4 py-2">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-ink-muted"><circle cx="11" cy="11" r="7" /><path d="m21 21-4.3-4.3" /></svg>
          <input v-model="query" type="search" :placeholder="t('Search add-ons…')" class="w-48 border-0 bg-transparent p-0 text-sm text-ink placeholder:text-ink-muted focus:ring-0" />
        </div>
      </div>

      <!-- Grid -->
      <div v-if="!visible.length" class="rounded-c border border-dashed border-line bg-surface p-12 text-center text-ink-muted">
        {{ t('Nothing here yet — check back soon.') }}
      </div>
      <div v-else class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        <article v-for="item in visible" :key="item.id"
          class="flex flex-col rounded-c border border-line bg-surface p-6 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
          <div class="flex items-start gap-3">
            <div class="grid h-12 w-12 shrink-0 place-items-center text-xl font-extrabold [&_svg]:h-12 [&_svg]:w-12 [&_svg]:rounded-xl" :class="tileAccent(item)">
              <span v-if="item.icon" class="grid h-12 w-12 place-items-center" v-html="item.icon"></span>
              <span v-else>{{ (item.name || '?').charAt(0).toUpperCase() }}</span>
            </div>
            <div class="min-w-0 flex-1">
              <h3 class="truncate text-lg font-bold leading-tight">{{ item.name }}</h3>
              <p class="text-xs text-ink-muted">{{ item.author || 'Convoro' }} · {{ t('v{version}', { version: item.version }) }}</p>
            </div>
          </div>

          <p class="mt-3 line-clamp-3 flex-1 text-sm text-ink-2">{{ item.description }}</p>

          <div class="mt-4 flex items-center gap-2 border-t border-line pt-4">
            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
              :class="item.type === 'theme' ? 'bg-fuchsia-500/15 text-fuchsia-600' : 'bg-primary/15 text-primary'">{{ item.type }}</span>
            <span v-if="item.active" class="rounded-full bg-emerald-500/15 px-2.5 py-0.5 text-xs font-semibold text-emerald-600">{{ t('Active') }}</span>
            <span class="ml-auto text-sm font-extrabold" :class="item.premium ? 'text-amber-600' : 'text-ink-muted'">{{ priceLabel(item) }}</span>
          </div>
        </article>
      </div>

      <p class="mt-8 text-center text-sm text-ink-muted">
        {{ t('More free & premium add-ons are coming to the Convoro store. Built something? Developer docs are on the way.') }}
      </p>
    </div>
  </AppLayout>
</template>
