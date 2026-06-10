<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import MarketingLayout from '@/Layouts/MarketingLayout.vue';

interface Item {
  slug: string; name: string; type: string; tagline?: string; description?: string;
  free: boolean; price: number; published: boolean; status?: string;
}
const props = defineProps<{ products: Item[]; checkoutEnabled: boolean }>();

const status = computed(() => (usePage().props as any).flash?.status as string | undefined);
const error = computed(() => (usePage().props as any).flash?.storeError as string | undefined);

// Editable working copy per product.
const forms = reactive<Record<string, { pricing: 'free' | 'premium'; price: number; tagline: string; description: string; saving: boolean }>>(
  Object.fromEntries(props.products.map((p) => [p.slug, {
    pricing: p.free ? 'free' : 'premium',
    price: p.free ? 9 : p.price,
    tagline: p.tagline ?? '',
    description: p.description ?? '',
    saving: false,
  }])),
);

function save(p: Item) {
  const f = forms[p.slug];
  f.saving = true;
  router.post(`/extensions/${p.slug}/manage`, {
    pricing: f.pricing, price: f.price, tagline: f.tagline, description: f.description,
  }, { preserveScroll: true, onFinish: () => (f.saving = false) });
}
</script>

<template>
  <Head title="Manage your extensions — Convoro" />
  <MarketingLayout>
    <section class="mx-auto max-w-3xl px-6 py-16">
      <Link href="/extensions" class="mb-6 inline-flex items-center gap-1.5 text-sm font-semibold text-ink-muted hover:text-ink">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7" /></svg>
        Back to the directory
      </Link>

      <h1 class="text-4xl font-black tracking-tight">Your extensions</h1>
      <p class="mt-3 text-lg text-ink-2">Edit pricing and details for the listings you publish. Changes go live immediately.</p>

      <div v-if="status" class="mt-6 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm font-medium text-emerald-700 dark:text-emerald-300">{{ status }}</div>
      <div v-if="error" class="mt-6 rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm font-medium text-red-600 dark:text-red-300">{{ error }}</div>

      <div v-if="!products.length" class="mt-10 rounded-2xl border border-dashed border-line p-16 text-center text-ink-muted">
        You haven't published any extensions yet.
        <Link href="/extensions/submit" class="font-semibold text-primary hover:underline">Submit one →</Link>
      </div>

      <div v-else class="mt-8 space-y-5">
        <div v-for="p in products" :key="p.slug" class="rounded-2xl border border-line bg-surface p-6">
          <div class="flex items-center gap-3">
            <div class="grid h-11 w-11 place-items-center rounded-xl bg-primary/10 text-xl">{{ p.type === 'theme' ? '🎨' : '🧩' }}</div>
            <div class="min-w-0 flex-1">
              <h3 class="truncate font-bold">{{ p.name }}</h3>
              <span class="text-xs font-semibold uppercase tracking-wide" :class="p.published ? 'text-emerald-500' : 'text-amber-500'">
                {{ p.status === 'pending' ? 'Pending review' : (p.published ? 'Live' : 'Draft') }}
              </span>
            </div>
            <a :href="`/extensions/${p.slug}`" class="text-sm font-semibold text-primary hover:underline">View →</a>
          </div>

          <div class="mt-5 grid gap-4">
            <div>
              <label class="block text-sm font-bold">Tagline</label>
              <input v-model="forms[p.slug].tagline" maxlength="200" class="mt-1.5 w-full rounded-lg border-line bg-surface-2 text-sm focus:border-primary focus:ring-primary" />
            </div>
            <div>
              <label class="block text-sm font-bold">Description</label>
              <textarea v-model="forms[p.slug].description" rows="3" class="mt-1.5 w-full rounded-lg border-line bg-surface-2 text-sm focus:border-primary focus:ring-primary"></textarea>
            </div>
            <div>
              <label class="block text-sm font-bold">Pricing</label>
              <div class="mt-2 flex flex-wrap items-center gap-3">
                <div class="flex rounded-xl border border-line p-0.5">
                  <button type="button" @click="forms[p.slug].pricing = 'free'"
                    class="rounded-lg px-4 py-1.5 text-sm font-semibold" :class="forms[p.slug].pricing === 'free' ? 'bg-primary text-white' : 'text-ink-2'">Free</button>
                  <button type="button" @click="forms[p.slug].pricing = 'premium'"
                    class="rounded-lg px-4 py-1.5 text-sm font-semibold" :class="forms[p.slug].pricing === 'premium' ? 'bg-primary text-white' : 'text-ink-2'">Premium</button>
                </div>
                <div v-if="forms[p.slug].pricing === 'premium'" class="flex items-center gap-1.5">
                  <span class="text-ink-muted">$</span>
                  <input v-model.number="forms[p.slug].price" type="number" min="1" max="9999" step="1"
                    class="w-28 rounded-lg border-line bg-surface-2 text-sm focus:border-primary focus:ring-primary" />
                  <span class="text-sm text-ink-muted">one-time</span>
                </div>
              </div>
              <p v-if="forms[p.slug].pricing === 'premium' && !checkoutEnabled" class="mt-2 rounded-lg bg-amber-500/10 px-3 py-2 text-xs text-amber-600 dark:text-amber-300">
                Payments aren't live on this store yet — buyers can't check out until the store owner connects Stripe.
              </p>
            </div>
          </div>

          <div class="mt-4 border-t border-line pt-4">
            <button type="button" :disabled="forms[p.slug].saving" @click="save(p)"
              class="rounded-c bg-primary px-5 py-2.5 text-sm font-bold text-white hover:bg-primary-600 disabled:opacity-60">
              {{ forms[p.slug].saving ? 'Saving…' : 'Save changes' }}
            </button>
          </div>
        </div>
      </div>
    </section>
  </MarketingLayout>
</template>
