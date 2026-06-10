<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import MarketingLayout from '@/Layouts/MarketingLayout.vue';

defineProps<{ license: { key: string; product: string } | null }>();
</script>

<template>
  <Head title="Thank you" />
  <MarketingLayout>
    <section class="mx-auto max-w-xl px-6 py-24 text-center">
      <div class="text-5xl">🎉</div>
      <h1 class="mt-4 text-3xl font-black tracking-tight">Thank you!</h1>

      <template v-if="license">
        <p class="mt-3 text-slate-600">Your purchase of <strong>{{ license.product }}</strong> is complete. Here’s your license key:</p>
        <div class="mt-5 select-all rounded-xl border border-slate-200 bg-slate-50 px-5 py-4 font-mono text-lg font-bold tracking-wide text-indigo-700">{{ license.key }}</div>
        <p class="mt-3 text-sm text-slate-500">Enter this key in your site’s <strong>Admin → Marketplace</strong> to install it. It’s also saved to your account.</p>
      </template>
      <template v-else>
        <p class="mt-3 text-slate-600">Your payment is being confirmed. Your license key will appear in your account momentarily — and we’ve emailed it to you.</p>
      </template>

      <div class="mt-8 flex justify-center gap-3">
        <Link href="/account/licenses" class="rounded-xl bg-indigo-600 px-5 py-3 font-bold text-white hover:bg-indigo-700">View my licenses</Link>
        <Link href="/store" class="rounded-xl border border-slate-200 px-5 py-3 font-bold text-slate-700 hover:bg-slate-50">Back to store</Link>
      </div>
    </section>
  </MarketingLayout>
</template>
