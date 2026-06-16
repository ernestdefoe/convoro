<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import MarketingLayout from '@/Layouts/MarketingLayout.vue';
import ProductCard from '@/Components/store/ProductCard.vue';
import { t } from '@/lib/i18n';

const props = defineProps<{ products: any[] }>();

const status = computed(() => (usePage().props as any).flash?.status as string | undefined);
const loggedIn = computed(() => !!(usePage().props as any).auth?.user);

// Auto-generated covers (under /storage/covers) just repeat the name + icon, so
// they're dropped here and the card draws its designed cover instead. A genuine
// custom screenshot (any other path) is passed through as the cover image.
function screenshot(p: any): string | null {
  const img = typeof p.image === 'string' ? p.image : '';
  return img && !img.includes('/storage/covers/') ? img : null;
}

const cards = computed(() => props.products.map((p) => ({
  name: p.name,
  type: p.type,
  description: p.description,
  meta: p.version ? `v${p.version}` : null,
  icon: p.icon ?? null,
  image: screenshot(p),
  priceLabel: p.price,
  free: p.free,
  review: p.review ?? null,
  accentKey: p.slug ?? p.id ?? p.name,
  href: `/extensions/${p.slug}`,
})));
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

      <div v-if="!cards.length" class="rounded-2xl border border-dashed border-line p-16 text-center text-ink-muted">
        {{ t('New premium add-ons are on the way. Check back soon.') }}
      </div>

      <div v-else class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        <ProductCard v-for="c in cards" :key="c.href" :item="c" :href="c.href" />
      </div>
    </section>
  </MarketingLayout>
</template>
