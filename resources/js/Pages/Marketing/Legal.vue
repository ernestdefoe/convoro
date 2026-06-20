<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import MarketingLayout from '@/Layouts/MarketingLayout.vue';
import { t } from '@/lib/i18n';

defineProps<{
  doc: {
    title: string;
    updated: string;
    intro?: string;
    sections: { h: string; body?: string[]; list?: string[] }[];
  };
}>();
</script>

<template>
  <Head :title="`${doc.title} — Convoro`" />
  <MarketingLayout>
    <article class="mx-auto max-w-3xl px-6 py-16">
      <h1 class="text-4xl font-black tracking-tight">{{ doc.title }}</h1>
      <p class="mt-2 text-sm text-ink-muted">{{ t('Last updated') }}: {{ doc.updated }}</p>
      <p v-if="doc.intro" class="mt-6 text-lg leading-relaxed text-ink-2">{{ doc.intro }}</p>

      <section v-for="(s, i) in doc.sections" :key="i" class="mt-9">
        <h2 class="text-xl font-extrabold tracking-tight text-ink">{{ s.h }}</h2>
        <p v-for="(p, j) in (s.body || [])" :key="'p' + j" class="mt-3 leading-relaxed text-ink-2">{{ p }}</p>
        <ul v-if="s.list && s.list.length" class="mt-3 space-y-2 pl-1">
          <li v-for="(li, k) in s.list" :key="'l' + k" class="flex gap-2.5 leading-relaxed text-ink-2">
            <span class="mt-2 h-1.5 w-1.5 shrink-0 rounded-full bg-primary"></span><span>{{ li }}</span>
          </li>
        </ul>
      </section>

      <div class="mt-12 flex flex-wrap gap-3 border-t border-line pt-8 text-sm">
        <Link href="/privacy" class="font-semibold text-ink-2 hover:text-primary">{{ t('Privacy Policy') }}</Link>
        <Link href="/terms" class="font-semibold text-ink-2 hover:text-primary">{{ t('Terms of Use') }}</Link>
        <Link href="/guidelines" class="font-semibold text-ink-2 hover:text-primary">{{ t('Community Guidelines') }}</Link>
      </div>
    </article>
  </MarketingLayout>
</template>
