<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import MarketingLayout from '@/Layouts/MarketingLayout.vue';
import { t } from '@/lib/i18n';

defineProps<{
  guide: { slug: string; title: string; icon: string; intro: string; sections: { h: string; body: any[] }[] };
  nav: { slug: string; title: string; icon: string }[];
}>();

const isCode = (b: any) => typeof b === 'object' && b !== null && 'code' in b;
</script>

<template>
  <Head :title="`${guide.title} — Convoro docs`" />
  <MarketingLayout>
    <section class="mx-auto max-w-5xl px-6 py-16">
      <Link href="/docs" class="text-sm font-semibold text-ink-muted hover:text-ink">{{ t('← All docs') }}</Link>

      <div class="mt-6 grid gap-10 md:grid-cols-[200px_1fr]">
        <!-- Sidebar -->
        <aside class="md:pt-1">
          <nav class="sticky top-24 space-y-1">
            <Link v-for="n in nav" :key="n.slug" :href="`/docs/${n.slug}`"
              class="flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium"
              :class="n.slug === guide.slug ? 'bg-primary/10 text-primary' : 'text-ink-2 hover:bg-surface-2'">
              <span>{{ n.icon }}</span> {{ n.title }}
            </Link>
          </nav>
        </aside>

        <!-- Content -->
        <article>
          <div class="flex items-center gap-3">
            <span class="grid h-12 w-12 place-items-center rounded-2xl bg-primary/10 text-2xl">{{ guide.icon }}</span>
            <h1 class="text-3xl font-black tracking-tight">{{ guide.title }}</h1>
          </div>
          <p class="mt-3 text-lg text-ink-2">{{ guide.intro }}</p>

          <div class="mt-8 space-y-10">
            <section v-for="(s, i) in guide.sections" :key="i">
              <h2 class="text-xl font-extrabold tracking-tight">{{ s.h }}</h2>
              <div class="mt-3 space-y-3">
                <template v-for="(b, j) in s.body" :key="j">
                  <pre v-if="isCode(b)" class="overflow-x-auto rounded-xl border border-line bg-ink p-4 text-sm text-slate-200"><code>{{ b.code }}</code></pre>
                  <p v-else class="leading-relaxed text-ink-2">{{ b }}</p>
                </template>
              </div>
            </section>
          </div>
        </article>
      </div>
    </section>
  </MarketingLayout>
</template>
