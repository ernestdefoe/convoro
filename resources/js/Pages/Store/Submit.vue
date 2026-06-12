<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import MarketingLayout from '@/Layouts/MarketingLayout.vue';
import { t } from '@/lib/i18n';

defineProps<{ checkoutEnabled: boolean }>();

const error = computed(() => (usePage().props as any).flash?.storeError as string | undefined);

const form = useForm({
  repo: '',
  pricing: 'free' as 'free' | 'premium',
  price: 9,
  email: '',
});

function submit() {
  form.post('/extensions/submit', { preserveScroll: true });
}
</script>

<template>
  <Head :title="t('Submit your extension — {brand}', { brand: 'Convoro' })" />
  <MarketingLayout>
    <section class="mx-auto max-w-2xl px-6 py-16">
      <Link href="/extensions" class="mb-6 inline-flex items-center gap-1.5 text-sm font-semibold text-ink-muted hover:text-ink">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7" /></svg>
        {{ t('Back to the directory') }}
      </Link>

      <h1 class="text-4xl font-black tracking-tight">{{ t('Submit your extension or theme') }}</h1>
      <p class="mt-3 text-lg text-ink-2">
        {{ t('Convoro has a built-in, Packagist-style registry. Link a') }} <strong>{{ t('public GitHub repo') }}</strong> {{ t('with an') }}
        <code class="rounded bg-surface-2 px-1.5 py-0.5 text-sm">extension.json</code> {{ t('at its root and a tagged release. We read your manifest, list it in the directory, and one-click installs pull straight from your GitHub releases.') }}
      </p>

      <div v-if="error" class="mt-6 rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm font-medium text-red-600 dark:text-red-300">
        {{ error }}
      </div>

      <form class="mt-8 space-y-6 rounded-2xl border border-line bg-surface p-6" @submit.prevent="submit">
        <div>
          <label class="block text-sm font-bold">{{ t('GitHub repository') }}</label>
          <input v-model="form.repo" type="text" :placeholder="t('your-name/your-extension or https://github.com/…')"
            class="mt-1.5 w-full rounded-lg border-line bg-surface-2 text-sm focus:border-primary focus:ring-primary" />
          <p v-if="form.errors.repo" class="mt-1 text-xs text-red-500">{{ form.errors.repo }}</p>
          <p class="mt-1 text-xs text-ink-muted">{{ t('Must be public, with') }} <code>extension.json</code> {{ t('at the root. We pull the latest release tag.') }}</p>
        </div>

        <div>
          <label class="block text-sm font-bold">{{ t('Listing type') }}</label>
          <div class="mt-2 grid grid-cols-2 gap-3">
            <button type="button" @click="form.pricing = 'free'"
              class="rounded-xl border p-4 text-left transition"
              :class="form.pricing === 'free' ? 'border-primary bg-primary/5' : 'border-line hover:bg-surface-2'">
              <div class="font-bold">{{ t('Free') }}</div>
              <div class="text-xs text-ink-muted">{{ t('Listed instantly after review. Installs in one click.') }}</div>
            </button>
            <button type="button" @click="form.pricing = 'premium'"
              class="rounded-xl border p-4 text-left transition"
              :class="form.pricing === 'premium' ? 'border-primary bg-primary/5' : 'border-line hover:bg-surface-2'">
              <div class="font-bold">{{ t('Premium') }}</div>
              <div class="text-xs text-ink-muted">{{ t('Sells through the Convoro store with a license key.') }}</div>
            </button>
          </div>
        </div>

        <div v-if="form.pricing === 'premium'">
          <label class="block text-sm font-bold">{{ t('Price (USD)') }}</label>
          <div class="mt-1.5 flex items-center gap-2">
            <span class="text-ink-muted">$</span>
            <input v-model.number="form.price" type="number" min="1" max="9999" step="1"
              class="w-32 rounded-lg border-line bg-surface-2 text-sm focus:border-primary focus:ring-primary" />
            <span class="text-sm text-ink-muted">{{ t('one-time') }}</span>
          </div>
          <p v-if="form.errors.price" class="mt-1 text-xs text-red-500">{{ form.errors.price }}</p>
          <p v-if="!checkoutEnabled" class="mt-2 rounded-lg bg-amber-500/10 px-3 py-2 text-xs text-amber-600 dark:text-amber-300">
            {{ t('Payments aren\'t live on this store yet — your premium listing will be held until checkout is enabled.') }}
          </p>
        </div>

        <div>
          <label class="block text-sm font-bold">{{ t('Your email') }}</label>
          <input v-model="form.email" type="email" :placeholder="t('you@example.com')"
            class="mt-1.5 w-full rounded-lg border-line bg-surface-2 text-sm focus:border-primary focus:ring-primary" />
          <p v-if="form.errors.email" class="mt-1 text-xs text-red-500">{{ form.errors.email }}</p>
          <p class="mt-1 text-xs text-ink-muted">{{ t('We\'ll use this to reach you about your listing and (for premium) payouts.') }}</p>
        </div>

        <div class="flex items-center gap-4 border-t border-line pt-5">
          <button type="submit" :disabled="form.processing"
            class="rounded-c bg-primary px-6 py-2.5 text-sm font-bold text-white hover:bg-primary-600 disabled:opacity-60">
            {{ form.processing ? t('Submitting…') : t('Submit for review') }}
          </button>
          <a href="/docs/extensions.html" class="text-sm font-semibold text-primary hover:underline">{{ t('Read the build guide →') }}</a>
        </div>
      </form>
    </section>
  </MarketingLayout>
</template>
