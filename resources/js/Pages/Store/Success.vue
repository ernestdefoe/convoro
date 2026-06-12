<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import MarketingLayout from '@/Layouts/MarketingLayout.vue';
import { t } from '@/lib/i18n';

defineProps<{ license: { key: string; product: string } | null }>();
</script>

<template>
  <Head :title="t('Thank you')" />
  <MarketingLayout>
    <section class="mx-auto max-w-xl px-6 py-24 text-center">
      <div class="text-5xl">🎉</div>
      <h1 class="mt-4 text-3xl font-black tracking-tight">{{ t('Thank you!') }}</h1>

      <template v-if="license">
        <p class="mt-3 text-ink-2">{{ t('Your purchase of') }} <strong>{{ license.product }}</strong> {{ t('is complete. Here’s your license key:') }}</p>
        <div class="mt-5 select-all rounded-xl border border-line bg-surface-2 px-5 py-4 font-mono text-lg font-bold tracking-wide text-primary">{{ license.key }}</div>
        <p class="mt-3 text-sm text-ink-muted">{{ t('Enter this key in your site’s') }} <strong>{{ t('Admin → Marketplace') }}</strong> {{ t('to install it. It’s also saved to your account.') }}</p>
      </template>
      <template v-else>
        <p class="mt-3 text-ink-2">{{ t('Your payment is being confirmed. Your license key will appear in your account momentarily — and we’ve emailed it to you.') }}</p>
      </template>

      <div class="mt-8 flex justify-center gap-3">
        <Link href="/account/licenses" class="rounded-xl bg-primary px-5 py-3 font-bold text-white hover:bg-primary-600">{{ t('View my licenses') }}</Link>
        <Link href="/extensions" class="rounded-xl border border-line px-5 py-3 font-bold text-ink-2 hover:bg-surface-2">{{ t('Back to extensions') }}</Link>
      </div>
    </section>
  </MarketingLayout>
</template>
