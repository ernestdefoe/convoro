<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import MarketingLayout from '@/Layouts/MarketingLayout.vue';
import { t } from '@/lib/i18n';

defineProps<{
  checkoutEnabled: boolean;
  github: { configured: boolean; connected: boolean; login: string | null };
}>();

const error = computed(() => (usePage().props as any).flash?.storeError as string | undefined);
const status = computed(() => (usePage().props as any).flash?.status as string | undefined);

function disconnectGithub() {
  router.post('/extensions/disconnect/github', {}, { preserveScroll: true });
}

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
      <div v-if="status" class="mt-6 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm font-medium text-emerald-600 dark:text-emerald-300">
        {{ status }}
      </div>

      <!-- Connect GitHub: required to list a PRIVATE repo. -->
      <div v-if="github.configured" class="mt-6 flex items-center gap-3 rounded-2xl border border-line bg-surface p-4">
        <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" class="shrink-0 text-ink"><path d="M12 .5a12 12 0 0 0-3.8 23.4c.6.1.8-.3.8-.6v-2c-3.3.7-4-1.6-4-1.6-.5-1.4-1.3-1.8-1.3-1.8-1.1-.7 0-.7 0-.7 1.2 0 1.8 1.2 1.8 1.2 1 1.8 2.8 1.3 3.5 1 0-.8.4-1.3.7-1.6-2.7-.3-5.5-1.3-5.5-5.9 0-1.3.5-2.4 1.2-3.2 0-.3-.5-1.5.2-3.2 0 0 1-.3 3.3 1.2a11.5 11.5 0 0 1 6 0C17.3 4.7 18.3 5 18.3 5c.7 1.7.2 2.9.1 3.2.8.8 1.2 1.9 1.2 3.2 0 4.6-2.8 5.6-5.5 5.9.4.4.8 1.1.8 2.2v3.3c0 .3.2.7.8.6A12 12 0 0 0 12 .5Z" /></svg>
        <div class="min-w-0 flex-1">
          <div class="text-sm font-bold">{{ github.connected ? t('GitHub connected') : t('Connect GitHub') }}</div>
          <div class="truncate text-xs text-ink-muted">
            {{ github.connected ? t('Listing as @{login} — private repos are available.', { login: github.login || '' }) : t('Connect to list extensions from your private repositories.') }}
          </div>
        </div>
        <a v-if="!github.connected" href="/extensions/connect/github"
          class="shrink-0 rounded-c bg-ink px-4 py-2 text-sm font-bold text-surface hover:opacity-90">{{ t('Connect') }}</a>
        <button v-else type="button" @click="disconnectGithub"
          class="shrink-0 rounded-c border border-line px-4 py-2 text-sm font-semibold text-ink-muted hover:bg-surface-2">{{ t('Disconnect') }}</button>
      </div>

      <form class="mt-8 space-y-6 rounded-2xl border border-line bg-surface p-6" @submit.prevent="submit">
        <div>
          <label class="block text-sm font-bold">{{ t('GitHub repository') }}</label>
          <input v-model="form.repo" type="text" :placeholder="t('your-name/your-extension or https://github.com/…')"
            class="mt-1.5 w-full rounded-lg border-line bg-surface-2 text-sm focus:border-primary focus:ring-primary" />
          <p v-if="form.errors.repo" class="mt-1 text-xs text-red-500">{{ form.errors.repo }}</p>
          <p class="mt-1 text-xs text-ink-muted">
            {{ github.connected ? t('Public or your own private repo, with') : t('Must be public (or connect GitHub above for private), with') }}
            <code>extension.json</code> {{ t('at the root. We pull the latest release tag.') }}
          </p>
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
