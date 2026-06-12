<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import MarketingLayout from '@/Layouts/MarketingLayout.vue';
import { t as tr } from '@/lib/i18n';

defineProps<{ releases: any[]; version: string }>();

const tag = (t: string) => ({
  new: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
  improved: 'bg-indigo-500/15 text-indigo-600 dark:text-indigo-400',
  fixed: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
}[t] || 'bg-surface-2 text-ink-2');
</script>

<template>
  <Head title="Changelog — Convoro" />
  <MarketingLayout>
    <section class="mx-auto max-w-3xl px-6 py-16">
      <span class="text-sm font-bold uppercase tracking-wide text-primary">{{ tr('Changelog') }}</span>
      <h1 class="mt-2 text-4xl font-black tracking-tight">{{ tr("What's new in Convoro") }}</h1>
      <p class="mt-3 text-lg text-ink-2">{{ tr("We ship continuously. Here's everything that's changed — currently on v{v}.", { v: version }) }}</p>

      <div class="mt-12 space-y-12">
        <article v-for="r in releases" :key="r.tag" class="relative border-l-2 border-line pl-6">
          <div class="absolute -left-[7px] top-1.5 h-3 w-3 rounded-full bg-primary"></div>
          <div class="flex flex-wrap items-baseline gap-3">
            <h2 class="text-xl font-extrabold">v{{ r.tag }}</h2>
            <span v-if="r.available" class="rounded-full bg-primary/15 px-2.5 py-0.5 text-xs font-bold text-primary">{{ tr('Available now') }}</span>
            <span class="text-sm text-ink-muted">{{ r.date }}</span>
          </div>
          <p class="mt-1 font-semibold text-ink-2">{{ r.title }}</p>
          <ul class="mt-4 space-y-2.5">
            <li v-for="(it, i) in r.items" :key="i" class="flex items-start gap-2.5 text-sm">
              <span class="mt-0.5 shrink-0 rounded px-1.5 py-0.5 text-[10px] font-bold uppercase" :class="tag(it.type)">{{ it.type }}</span>
              <span class="text-ink-2">{{ it.text }}</span>
            </li>
          </ul>
        </article>
      </div>

      <div class="mt-14 rounded-2xl border border-line bg-surface p-6 text-center">
        <p class="text-ink-2">{{ tr("Want to know what's coming next?") }}</p>
        <Link href="/roadmap" class="mt-2 inline-block font-bold text-primary hover:text-primary-600">{{ tr('See the roadmap →') }}</Link>
      </div>
    </section>
  </MarketingLayout>
</template>
