<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import ProductCard from '@/Components/store/ProductCard.vue';
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

// Map to the shared ProductCard shape so this directory matches the Marketplace.
const cards = computed(() => visible.value.map((e) => ({
  name: e.name,
  type: e.type,
  description: e.description,
  meta: `${e.author || 'Convoro'} · ${t('v{version}', { version: e.version })}`,
  icon: e.icon ?? null,
  image: null,
  priceLabel: priceLabel(e),
  free: !e.premium,
  active: e.active,
  accentKey: e.id,
})));
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
      <div v-if="!cards.length" class="rounded-c border border-dashed border-line bg-surface p-12 text-center text-ink-muted">
        {{ t('Nothing here yet — check back soon.') }}
      </div>
      <div v-else class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        <ProductCard v-for="(c, i) in cards" :key="c.accentKey || i" :item="c" />
      </div>

      <p class="mt-8 text-center text-sm text-ink-muted">
        {{ t('More free & premium add-ons are coming to the Convoro store. Built something? Developer docs are on the way.') }}
      </p>
    </div>
  </AppLayout>
</template>
