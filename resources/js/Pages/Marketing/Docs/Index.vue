<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import MarketingLayout from '@/Layouts/MarketingLayout.vue';
import { t } from '@/lib/i18n';

defineProps<{ guides: { slug: string; title: string; icon: string; intro: string }[] }>();
</script>

<template>
  <Head title="Documentation — Convoro" />
  <MarketingLayout>
    <section class="mx-auto max-w-5xl px-6 py-16">
      <span class="text-sm font-bold uppercase tracking-wide text-primary">{{ t('Documentation') }}</span>
      <h1 class="mt-2 text-4xl font-black tracking-tight">{{ t('Everything you need to run Convoro') }}</h1>
      <p class="mt-3 max-w-2xl text-lg text-ink-2">{{ t('Practical guides for installing, configuring, theming, extending, and deploying your community.') }}</p>

      <div class="mt-10 grid gap-5 sm:grid-cols-2">
        <Link v-for="g in guides" :key="g.slug" :href="`/docs/${g.slug}`"
          class="group rounded-2xl border border-line bg-surface p-6 transition hover:-translate-y-0.5 hover:shadow-lg">
          <div class="grid h-12 w-12 place-items-center rounded-xl bg-primary/10 text-2xl">{{ g.icon }}</div>
          <h2 class="mt-4 text-lg font-bold group-hover:text-primary">{{ g.title }}</h2>
          <p class="mt-1.5 text-sm text-ink-2">{{ g.intro }}</p>
          <span class="mt-3 inline-block text-sm font-semibold text-primary">{{ t('Read →') }}</span>
        </Link>
      </div>

      <div class="mt-10 rounded-2xl border border-line bg-surface-2 p-6 text-sm text-ink-2">
        {{ t('Building an extension? Hand your AI assistant') }} <a href="/llms.txt" class="font-semibold text-primary hover:underline">/llms.txt</a> {{ t('— the machine-readable build spec.') }}
      </div>
    </section>
  </MarketingLayout>
</template>
