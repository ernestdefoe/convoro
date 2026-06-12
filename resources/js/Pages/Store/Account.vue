<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import MarketingLayout from '@/Layouts/MarketingLayout.vue';
import { t } from '@/lib/i18n';

defineProps<{ licenses: any[] }>();
</script>

<template>
  <Head :title="t('My licenses')" />
  <MarketingLayout>
    <section class="mx-auto max-w-3xl px-6 py-16">
      <h1 class="text-3xl font-black tracking-tight">{{ t('My licenses') }}</h1>
      <p class="mt-2 text-ink-2">{{ t('Your purchased extensions and themes. Use a key in any Convoro site’s Marketplace to install it.') }}</p>

      <div v-if="!licenses.length" class="mt-8 rounded-2xl border border-dashed border-line p-16 text-center text-ink-muted">
        {{ t('No licenses yet.') }} <Link href="/extensions" class="font-semibold text-primary">{{ t('Browse extensions →') }}</Link>
      </div>

      <div v-else class="mt-8 space-y-3">
        <div v-for="l in licenses" :key="l.key" class="rounded-2xl border border-line bg-surface p-5">
          <div class="flex flex-wrap items-center gap-3">
            <div class="font-bold">{{ l.product }}</div>
            <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="l.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-surface-2 text-ink-muted'">{{ l.status }}</span>
            <span class="ml-auto text-xs text-ink-muted">{{ t('Purchased {date}', { date: l.purchased }) }}</span>
          </div>
          <div class="mt-3 flex flex-wrap items-center gap-3">
            <code class="select-all rounded-lg bg-surface-2 px-3 py-2 font-mono text-sm font-bold tracking-wide text-primary">{{ l.key }}</code>
            <a v-if="l.download" :href="l.download" class="rounded-lg border border-line px-3 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2">{{ t('Download') }}</a>
          </div>
        </div>
      </div>
    </section>
  </MarketingLayout>
</template>
