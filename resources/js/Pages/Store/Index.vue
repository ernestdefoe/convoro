<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import MarketingLayout from '@/Layouts/MarketingLayout.vue';

defineProps<{ products: any[] }>();
</script>

<template>
  <Head title="Store — premium extensions & themes" />
  <MarketingLayout>
    <section class="mx-auto max-w-6xl px-6 py-16">
      <div class="mb-10 max-w-2xl">
        <h1 class="text-4xl font-black tracking-tight">Store</h1>
        <p class="mt-3 text-lg text-slate-600">Premium extensions and themes for Convoro. Buy once — get a license key and download, and install on any of your sites.</p>
      </div>

      <div v-if="!products.length" class="rounded-2xl border border-dashed border-slate-300 p-16 text-center text-slate-500">
        New premium add-ons are on the way. Check back soon.
      </div>

      <div v-else class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        <Link v-for="p in products" :key="p.slug" :href="`/store/${p.slug}`"
          class="group flex flex-col rounded-2xl border border-slate-200 bg-white p-6 transition hover:-translate-y-0.5 hover:shadow-lg">
          <div class="flex items-start gap-3">
            <div class="grid h-12 w-12 place-items-center rounded-xl bg-indigo-50 text-2xl">{{ p.type === 'theme' ? '🎨' : '🧩' }}</div>
            <div class="min-w-0">
              <h3 class="truncate font-bold group-hover:text-indigo-600">{{ p.name }}</h3>
              <span class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ p.type }}</span>
            </div>
          </div>
          <p class="mt-3 line-clamp-2 flex-1 text-sm text-slate-600">{{ p.description }}</p>
          <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-4">
            <span class="font-extrabold" :class="p.free ? 'text-slate-500' : 'text-indigo-600'">{{ p.price }}</span>
            <span class="text-sm font-semibold text-indigo-600 group-hover:underline">View →</span>
          </div>
        </Link>
      </div>
    </section>
  </MarketingLayout>
</template>
