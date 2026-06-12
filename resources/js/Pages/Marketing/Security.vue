<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import MarketingLayout from '@/Layouts/MarketingLayout.vue';
import { t } from '@/lib/i18n';

const practices = [
  { icon: '🔍', title: t('AI security review of every add-on'), body: t('Each marketplace extension is audited from its real source code and gets an unbiased safety verdict before you install — visible right on its listing.') },
  { icon: '🔒', title: t('Hardened by default'), body: t('CSRF protection, signed webhooks, hashed passwords, scoped API tokens, and a granular permission system are built in.') },
  { icon: '🧾', title: t('Transparent updates'), body: t('A public changelog and version on every page mean you always know exactly what you are running and what changed.') },
  { icon: '🛡️', title: t('Safe extension model'), body: t('Extensions are reviewed, version-pinnable, and pulled from public source you can inspect — no opaque binary blobs.') },
];
</script>

<template>
  <Head title="Security — Convoro" />
  <MarketingLayout>
    <section class="mx-auto max-w-3xl px-6 py-16">
      <span class="text-sm font-bold uppercase tracking-wide text-primary">{{ t('Security') }}</span>
      <h1 class="mt-2 text-4xl font-black tracking-tight">{{ t('Security & trust') }}</h1>
      <p class="mt-3 text-lg text-ink-2">{{ t("Communities run on trust. Here's how Convoro helps keep yours — and its members — safe.") }}</p>

      <div class="mt-10 grid gap-5 sm:grid-cols-2">
        <div v-for="p in practices" :key="p.title" class="rounded-2xl border border-line bg-surface p-6">
          <div class="grid h-11 w-11 place-items-center rounded-xl bg-primary/10 text-xl">{{ p.icon }}</div>
          <h2 class="mt-4 font-bold">{{ p.title }}</h2>
          <p class="mt-1.5 text-sm text-ink-2">{{ p.body }}</p>
        </div>
      </div>

      <div class="mt-10 rounded-2xl border border-line bg-surface-2 p-6">
        <h2 class="font-bold">{{ t('Responsible disclosure') }}</h2>
        <p class="mt-2 text-sm text-ink-2">{{ t('Found a vulnerability? Please report it privately to') }} <a href="mailto:security@convoro.co" class="font-semibold text-primary hover:underline">security@convoro.co</a> {{ t('before disclosing publicly. We investigate every report and will credit you once a fix ships.') }}</p>
      </div>
    </section>
  </MarketingLayout>
</template>
