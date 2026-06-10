<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import MarketingLayout from '@/Layouts/MarketingLayout.vue';

defineProps<{ products: any[] }>();

const status = computed(() => (usePage().props as any).flash?.status as string | undefined);
const loggedIn = computed(() => !!(usePage().props as any).auth?.user);
</script>

<template>
  <Head title="Extensions & themes — Convoro" />
  <MarketingLayout>
    <section class="mx-auto max-w-6xl px-6 py-16">
      <div v-if="status" class="mb-8 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm font-medium text-emerald-700 dark:text-emerald-300">
        {{ status }}
      </div>

      <div class="mb-10 flex flex-wrap items-end justify-between gap-4">
        <div class="max-w-2xl">
          <h1 class="text-4xl font-black tracking-tight">Extensions &amp; themes</h1>
          <p class="mt-3 text-lg text-ink-2">Browse the directory of Convoro extensions and themes. Free add-ons install in one click; premium ones are purchased on their detail page — you get a license key to install on any of your sites.</p>
        </div>
        <div class="flex shrink-0 items-center gap-2">
          <Link v-if="loggedIn" href="/extensions/manage"
            class="inline-flex items-center gap-2 rounded-c border border-line px-4 py-2.5 text-sm font-bold text-ink-2 hover:bg-surface-2">
            Manage yours
          </Link>
          <Link href="/extensions/submit"
            class="inline-flex items-center gap-2 rounded-c bg-primary px-5 py-2.5 text-sm font-bold text-white hover:bg-primary-600">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M5 12h14" /></svg>
            Submit your extension
          </Link>
        </div>
      </div>

      <div v-if="!products.length" class="rounded-2xl border border-dashed border-line p-16 text-center text-ink-muted">
        New premium add-ons are on the way. Check back soon.
      </div>

      <div v-else class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        <Link v-for="p in products" :key="p.slug" :href="`/extensions/${p.slug}`"
          class="group flex flex-col overflow-hidden rounded-2xl border border-line bg-surface transition hover:-translate-y-0.5 hover:shadow-lg">
          <img v-if="p.image" :src="p.image" :alt="p.name" loading="lazy" class="aspect-[2/1] w-full object-cover" />
          <div class="flex flex-1 flex-col p-6">
            <div class="flex items-start gap-3">
              <div v-if="!p.image" class="grid h-12 w-12 place-items-center rounded-xl bg-primary/10 text-2xl">{{ p.type === 'theme' ? '🎨' : '🧩' }}</div>
              <div class="min-w-0">
                <h3 class="truncate font-bold group-hover:text-primary">{{ p.name }}</h3>
                <span class="text-xs font-semibold uppercase tracking-wide text-ink-muted">{{ p.type }}</span>
              </div>
            </div>
            <p class="mt-3 line-clamp-2 flex-1 text-sm text-ink-2">{{ p.description }}</p>
            <div class="mt-4 flex items-center justify-between border-t border-line pt-4">
              <span class="font-extrabold" :class="p.free ? 'text-ink-muted' : 'text-primary'">{{ p.price }}</span>
              <span class="text-sm font-semibold text-primary group-hover:underline">View →</span>
            </div>
          </div>
        </Link>
      </div>
    </section>
  </MarketingLayout>
</template>
