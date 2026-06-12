<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import MarketingLayout from '@/Layouts/MarketingLayout.vue';
import { t } from '@/lib/i18n';

const stats = [
  { n: '100%', l: t('Open to AI-built add-ons') },
  { n: '$3/mo', l: t('Runs on shared hosting') },
  { n: '1-click', l: t('Browser installer') },
];
</script>

<template>
  <Head title="About Convoro" />
  <MarketingLayout>
    <section class="mx-auto max-w-3xl px-6 py-16">
      <span class="text-sm font-bold uppercase tracking-wide text-primary">{{ t('About') }}</span>
      <h1 class="mt-2 text-4xl font-black tracking-tight">{{ t('Community software, modernized') }}</h1>
      <div class="prose prose-slate mt-6 max-w-none text-lg text-ink-2">
        <p>{{ t('Convoro takes the proven ideas behind the great forum platforms and rebuilds them into one cohesive, modern product: a live theme editor, true WYSIWYG posting, realtime threads, a built-in marketplace, and PWA push — all installable in a browser, even on inexpensive shared hosting.') }}</p>
        <p>{{ t("We're built for the AI era on purpose. Extensions and themes are designed to be scaffolded with AI assistants, every marketplace add-on gets an unbiased AI security review, and we publish a machine-readable build spec so models can get it right the first time.") }}</p>
        <p>{{ t("The goal is simple: give community builders software that feels like a team has polished it for years — because it's continuously shipped, transparent about what's changing, and honest about what's next.") }}</p>
      </div>

      <div class="mt-10 grid gap-4 sm:grid-cols-3">
        <div v-for="s in stats" :key="s.l" class="rounded-2xl border border-line bg-surface p-6 text-center">
          <div class="text-3xl font-black text-primary">{{ s.n }}</div>
          <div class="mt-1 text-sm text-ink-2">{{ s.l }}</div>
        </div>
      </div>

      <div class="mt-10 flex flex-wrap gap-3">
        <Link href="/roadmap" class="rounded-xl bg-primary px-5 py-3 font-bold text-white hover:bg-primary-600">{{ t('See the roadmap') }}</Link>
        <Link href="/changelog" class="rounded-xl border border-line bg-surface px-5 py-3 font-bold text-ink-2 hover:bg-surface-2">{{ t("What's new") }}</Link>
        <Link href="/security" class="rounded-xl border border-line bg-surface px-5 py-3 font-bold text-ink-2 hover:bg-surface-2">{{ t('Security') }}</Link>
      </div>
    </section>
  </MarketingLayout>
</template>
