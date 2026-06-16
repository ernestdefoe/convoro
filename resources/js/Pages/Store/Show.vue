<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import MarketingLayout from '@/Layouts/MarketingLayout.vue';
import { t } from '@/lib/i18n';
import { coverGradient, stripIconBg } from '@/lib/accent';

const props = defineProps<{ product: any; checkoutEnabled: boolean }>();

// Match the card: a white silhouette on the product's accent tile.
const iconGlyph = computed(() => stripIconBg(props.product.icon));
const iconTile = computed(() => coverGradient(props.product.slug ?? props.product.name));
const page = usePage();
const error = computed(() => (page.props as any).flash?.storeError ?? null);
const loading = ref(false);

// Only show a banner for a genuine custom screenshot — the auto-generated
// /storage/covers art just repeats the name + icon already shown in the header.
const bannerImage = computed(() => {
  const img = typeof props.product.image === 'string' ? props.product.image : '';
  return img && !img.includes('/storage/covers/') ? img : null;
});

const review = computed(() => props.product.review ?? null);
const reviewMeta = computed(() => {
  const r = review.value?.rating;
  if (r === 'safe') return { label: t('Reviewed safe'), icon: '✓', c: 'border-emerald-500/40 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' };
  if (r === 'caution') return { label: t('Reviewed — caution'), icon: '⚠', c: 'border-amber-500/40 bg-amber-500/10 text-amber-600 dark:text-amber-400' };
  if (r === 'unsafe') return { label: t('Reviewed — unsafe'), icon: '⛔', c: 'border-red-500/40 bg-red-500/10 text-red-600 dark:text-red-400' };
  return null;
});
const sevClass = (s: string) => ({
  critical: 'text-red-600 dark:text-red-400', high: 'text-red-600 dark:text-red-400',
  medium: 'text-amber-600 dark:text-amber-400', low: 'text-ink-muted',
}[s] || 'text-ink-muted');

function buy() {
  loading.value = true;
  router.post(`/extensions/${props.product.slug}/checkout`, {}, { onFinish: () => (loading.value = false) });
}
</script>

<template>
  <Head :title="product.name" />
  <MarketingLayout>
    <section class="mx-auto max-w-4xl px-6 py-16">
      <Link href="/extensions" class="text-sm font-semibold text-ink-muted hover:text-ink">{{ t('← Back to extensions') }}</Link>
      <div class="mt-6 grid gap-10 md:grid-cols-[1fr_320px]">
        <div>
          <div class="flex items-center gap-4">
            <div class="grid h-16 w-16 shrink-0 place-items-center overflow-hidden rounded-2xl shadow-md ring-1 ring-black/10" :style="{ backgroundImage: iconTile }">
              <span v-if="iconGlyph" v-html="iconGlyph" class="grid h-9 w-9 place-items-center [&>svg]:h-full [&>svg]:w-full [&>svg]:[filter:brightness(0)_invert(1)]"></span>
              <span v-else class="text-3xl font-black text-white">{{ (product.name || '?').charAt(0).toUpperCase() }}</span>
            </div>
            <div>
              <h1 class="text-3xl font-black tracking-tight">{{ product.name }}</h1>
              <p class="text-ink-muted">{{ product.tagline }}</p>
            </div>
          </div>
          <img v-if="bannerImage" :src="bannerImage" :alt="product.name" class="mt-8 w-full rounded-2xl border border-line" />
          <div class="prose prose-slate mt-8 max-w-none whitespace-pre-line text-ink-2">{{ product.description || product.tagline }}</div>

          <!-- AI security review -->
          <div v-if="reviewMeta || review?.status === 'queued' || review?.status === 'reviewing'" class="mt-10">
            <h2 class="text-sm font-bold uppercase tracking-wide text-ink-2">{{ t('AI security review') }}</h2>

            <div v-if="reviewMeta" class="mt-3 rounded-2xl border p-5" :class="reviewMeta.c">
              <div class="flex flex-wrap items-center gap-3">
                <span class="text-2xl">{{ reviewMeta.icon }}</span>
                <div class="flex-1">
                  <div class="font-bold">{{ reviewMeta.label }}<span v-if="review.score !== null"> · {{ review.score }}/100</span></div>
                  <div class="text-sm opacity-90">{{ review.summary }}</div>
                </div>
              </div>
              <ul v-if="review.findings?.length" class="mt-4 space-y-2 border-t border-current/20 pt-4">
                <li v-for="(f, i) in review.findings" :key="i" class="text-sm text-ink-2">
                  <span class="font-bold uppercase" :class="sevClass(f.severity)">{{ f.severity }}</span>
                  · <span class="font-semibold text-ink">{{ f.title }}</span>
                  <span class="text-ink-muted"> — {{ f.detail }}</span>
                </li>
              </ul>
              <p class="mt-4 text-[11px] text-ink-muted">
                {{ t('Automated review of v{version} by', { version: review.reviewedVersion }) }} <code>{{ review.model }}</code> {{ review.reviewedAt }}.
                <span v-if="review.stale">{{ t('⚠ A newer version has shipped since this review.') }}</span>
                {{ t('This is an automated signal to aid your judgment — not a guarantee.') }}
              </p>
            </div>

            <div v-else class="mt-3 rounded-2xl border border-line bg-surface-2 p-5 text-sm text-ink-2">
              {{ t('⏳ A security review of this extension is in progress — check back shortly.') }}
            </div>
          </div>
        </div>

        <aside class="md:pt-2">
          <div class="sticky top-24 rounded-2xl border border-line bg-surface p-6 shadow-sm">
            <div class="text-3xl font-black" :class="product.free ? 'text-ink-2' : 'text-primary'">{{ product.price }}</div>
            <div class="mt-1 text-sm text-ink-muted">v{{ product.version }} · {{ t('one-time') }}</div>

            <button v-if="!product.free" type="button" :disabled="!checkoutEnabled || loading" @click="buy"
              class="mt-5 w-full rounded-xl bg-primary px-5 py-3 font-bold text-white shadow-lg shadow-primary/25 hover:bg-primary-600 disabled:opacity-60">
              {{ loading ? t('Redirecting…') : t('Buy now') }}
            </button>
            <a v-else href="https://community.convoro.co" class="mt-5 block rounded-xl bg-primary px-5 py-3 text-center font-bold text-white hover:bg-primary-600">{{ t('Get it free') }}</a>

            <p v-if="!product.free && !checkoutEnabled" class="mt-3 text-center text-xs text-amber-600">{{ t('Checkout is being set up — purchasing opens soon.') }}</p>
            <p v-if="error" class="mt-3 text-center text-sm text-red-600">{{ error }}</p>
            <ul class="mt-5 space-y-2 text-sm text-ink-2">
              <li>{{ t('✓ Lifetime license key') }}</li>
              <li>{{ t('✓ Install on your Convoro sites') }}</li>
              <li>{{ t('✓ Updates included') }}</li>
            </ul>
          </div>
        </aside>
      </div>
    </section>
  </MarketingLayout>
</template>
