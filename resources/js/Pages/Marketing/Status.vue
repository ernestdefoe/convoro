<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import MarketingLayout from '@/Layouts/MarketingLayout.vue';
import { t as tr } from '@/lib/i18n';

const props = defineProps<{ checks: any[]; version: string }>();

const allOk = computed(() => props.checks.every((c) => c.state === 'ok'));
const anyDown = computed(() => props.checks.some((c) => c.state === 'down'));
const overall = computed(() => anyDown.value
  ? { t: tr('Partial outage'), c: 'bg-red-500/10 border-red-500/40 text-red-600 dark:text-red-400', dot: 'bg-red-500' }
  : allOk.value
    ? { t: tr('All systems operational'), c: 'bg-emerald-500/10 border-emerald-500/40 text-emerald-600 dark:text-emerald-400', dot: 'bg-emerald-500' }
    : { t: tr('Degraded performance'), c: 'bg-amber-500/10 border-amber-500/40 text-amber-600 dark:text-amber-400', dot: 'bg-amber-500' });

const dot = (s: string) => ({ ok: 'bg-emerald-500', degraded: 'bg-amber-500', down: 'bg-red-500' }[s] || 'bg-ink-muted');
const stateLabel = (s: string) => ({ ok: tr('Operational'), degraded: tr('Degraded'), down: tr('Down') }[s] || s);
</script>

<template>
  <Head title="System status — Convoro" />
  <MarketingLayout>
    <section class="mx-auto max-w-3xl px-6 py-16">
      <span class="text-sm font-bold uppercase tracking-wide text-primary">{{ tr('Status') }}</span>
      <h1 class="mt-2 text-4xl font-black tracking-tight">{{ tr('System status') }}</h1>

      <div class="mt-8 flex items-center gap-3 rounded-2xl border p-5" :class="overall.c">
        <span class="relative flex h-3 w-3">
          <span class="absolute inline-flex h-full w-full animate-ping rounded-full opacity-60" :class="overall.dot"></span>
          <span class="relative inline-flex h-3 w-3 rounded-full" :class="overall.dot"></span>
        </span>
        <span class="text-lg font-bold">{{ overall.t }}</span>
      </div>

      <ul class="mt-6 divide-y divide-line overflow-hidden rounded-2xl border border-line">
        <li v-for="c in checks" :key="c.name" class="flex items-center gap-3 bg-surface px-5 py-4">
          <span class="h-2.5 w-2.5 shrink-0 rounded-full" :class="dot(c.state)"></span>
          <div class="min-w-0 flex-1">
            <div class="font-semibold text-ink">{{ c.name }}</div>
            <div class="text-sm text-ink-muted">{{ c.detail }}</div>
          </div>
          <span class="shrink-0 text-sm font-semibold" :class="c.state === 'ok' ? 'text-emerald-500' : c.state === 'down' ? 'text-red-500' : 'text-amber-500'">{{ stateLabel(c.state) }}</span>
        </li>
      </ul>

      <p class="mt-6 text-center text-xs text-ink-muted">{{ tr('Checked live on each load') }} · {{ tr('Convoro v{v}', { v: version }) }}</p>
    </section>
  </MarketingLayout>
</template>
