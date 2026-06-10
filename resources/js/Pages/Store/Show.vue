<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import MarketingLayout from '@/Layouts/MarketingLayout.vue';

const props = defineProps<{ product: any; checkoutEnabled: boolean }>();
const page = usePage();
const error = computed(() => (page.props as any).flash?.storeError ?? null);
const loading = ref(false);

function buy() {
  loading.value = true;
  router.post(`/store/${props.product.slug}/checkout`, {}, { onFinish: () => (loading.value = false) });
}
</script>

<template>
  <Head :title="product.name" />
  <MarketingLayout>
    <section class="mx-auto max-w-4xl px-6 py-16">
      <Link href="/store" class="text-sm font-semibold text-ink-muted hover:text-ink">← Back to store</Link>
      <div class="mt-6 grid gap-10 md:grid-cols-[1fr_320px]">
        <div>
          <div class="flex items-center gap-4">
            <div class="grid h-16 w-16 place-items-center rounded-2xl bg-primary/10 text-3xl">{{ product.type === 'theme' ? '🎨' : '🧩' }}</div>
            <div>
              <h1 class="text-3xl font-black tracking-tight">{{ product.name }}</h1>
              <p class="text-ink-muted">{{ product.tagline }}</p>
            </div>
          </div>
          <img v-if="product.image" :src="product.image" :alt="product.name" class="mt-8 w-full rounded-2xl border border-line" />
          <div class="prose prose-slate mt-8 max-w-none whitespace-pre-line text-ink-2">{{ product.description || product.tagline }}</div>
        </div>

        <aside class="md:pt-2">
          <div class="sticky top-24 rounded-2xl border border-line bg-surface p-6 shadow-sm">
            <div class="text-3xl font-black" :class="product.free ? 'text-ink-2' : 'text-primary'">{{ product.price }}</div>
            <div class="mt-1 text-sm text-ink-muted">v{{ product.version }} · one-time</div>

            <button v-if="!product.free" type="button" :disabled="!checkoutEnabled || loading" @click="buy"
              class="mt-5 w-full rounded-xl bg-primary px-5 py-3 font-bold text-white shadow-lg shadow-primary/25 hover:bg-primary-600 disabled:opacity-60">
              {{ loading ? 'Redirecting…' : 'Buy now' }}
            </button>
            <a v-else href="https://community.convoro.co" class="mt-5 block rounded-xl bg-primary px-5 py-3 text-center font-bold text-white hover:bg-primary-600">Get it free</a>

            <p v-if="!product.free && !checkoutEnabled" class="mt-3 text-center text-xs text-amber-600">Checkout is being set up — purchasing opens soon.</p>
            <p v-if="error" class="mt-3 text-center text-sm text-red-600">{{ error }}</p>
            <ul class="mt-5 space-y-2 text-sm text-ink-2">
              <li>✓ Lifetime license key</li>
              <li>✓ Install on your Convoro sites</li>
              <li>✓ Updates included</li>
            </ul>
          </div>
        </aside>
      </div>
    </section>
  </MarketingLayout>
</template>
