<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import MarketingLayout from '@/Layouts/MarketingLayout.vue';

defineProps<{ licenses: any[] }>();
</script>

<template>
  <Head title="My licenses" />
  <MarketingLayout>
    <section class="mx-auto max-w-3xl px-6 py-16">
      <h1 class="text-3xl font-black tracking-tight">My licenses</h1>
      <p class="mt-2 text-slate-600">Your purchased extensions and themes. Use a key in any Convoro site’s Marketplace to install it.</p>

      <div v-if="!licenses.length" class="mt-8 rounded-2xl border border-dashed border-slate-300 p-16 text-center text-slate-500">
        No licenses yet. <Link href="/store" class="font-semibold text-indigo-600">Browse the store →</Link>
      </div>

      <div v-else class="mt-8 space-y-3">
        <div v-for="l in licenses" :key="l.key" class="rounded-2xl border border-slate-200 bg-white p-5">
          <div class="flex flex-wrap items-center gap-3">
            <div class="font-bold">{{ l.product }}</div>
            <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="l.status === 'active' ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'">{{ l.status }}</span>
            <span class="ml-auto text-xs text-slate-400">Purchased {{ l.purchased }}</span>
          </div>
          <div class="mt-3 flex flex-wrap items-center gap-3">
            <code class="select-all rounded-lg bg-slate-50 px-3 py-2 font-mono text-sm font-bold tracking-wide text-indigo-700">{{ l.key }}</code>
            <a v-if="l.download" :href="l.download" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Download</a>
          </div>
        </div>
      </div>
    </section>
  </MarketingLayout>
</template>
