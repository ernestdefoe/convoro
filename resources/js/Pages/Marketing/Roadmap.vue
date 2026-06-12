<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import MarketingLayout from '@/Layouts/MarketingLayout.vue';
import { t } from '@/lib/i18n';

defineProps<{ roadmap: Record<string, string[]> }>();

const columns = [
  { key: 'shipped', label: t('Shipped'), icon: '✓', c: 'border-emerald-500/30', dot: 'bg-emerald-500' },
  { key: 'now', label: t('In progress'), icon: '◐', c: 'border-indigo-500/30', dot: 'bg-indigo-500' },
  { key: 'next', label: t('Up next'), icon: '→', c: 'border-amber-500/30', dot: 'bg-amber-500' },
  { key: 'later', label: t('Later'), icon: '○', c: 'border-line', dot: 'bg-ink-muted' },
];
</script>

<template>
  <Head title="Roadmap — Convoro" />
  <MarketingLayout>
    <section class="mx-auto max-w-6xl px-6 py-16">
      <span class="text-sm font-bold uppercase tracking-wide text-primary">{{ t('Roadmap') }}</span>
      <h1 class="mt-2 text-4xl font-black tracking-tight">{{ t('Where Convoro is headed') }}</h1>
      <p class="mt-3 max-w-2xl text-lg text-ink-2">{{ t("An honest, living view of what's done, what we're building now, and what's planned. Priorities shift with community feedback.") }}</p>

      <div class="mt-12 grid gap-5 md:grid-cols-2 lg:grid-cols-4">
        <div v-for="col in columns" :key="col.key" class="rounded-2xl border bg-surface p-5" :class="col.c">
          <h2 class="flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-ink-2">
            <span class="grid h-5 w-5 place-items-center rounded-full text-white text-[11px]" :class="col.dot">{{ col.icon }}</span>
            {{ col.label }}
          </h2>
          <ul class="mt-4 space-y-2.5">
            <li v-for="(item, i) in (roadmap[col.key] || [])" :key="i" class="rounded-lg bg-surface-2 px-3 py-2 text-sm text-ink-2">{{ item }}</li>
          </ul>
        </div>
      </div>

      <div class="mt-12 text-center text-sm text-ink-muted">
        {{ t('Have a request?') }} <Link href="/changelog" class="font-semibold text-primary hover:text-primary-600">{{ t('See what we just shipped') }}</Link> {{ t('or tell us in the community.') }}
      </div>
    </section>
  </MarketingLayout>
</template>
