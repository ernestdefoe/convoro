<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps<{
  update: { current: string; latest: string; available: boolean; url: string | null; checkedAt: string | null; enabled: boolean };
}>();

function checkUpdates() {
  router.post('/admin/system/check-updates', {}, { preserveScroll: true });
}
</script>

<template>
  <Head title="Admin · Marketplace" />
  <AdminLayout>
    <template #title>Marketplace</template>
    <template #subtitle>Extensions, themes &amp; software updates</template>

    <!-- Update status -->
    <section class="mb-6 rounded-2xl border border-white/5 bg-[#14172a] p-5">
      <div class="flex flex-wrap items-center gap-4">
        <div>
          <div class="text-sm text-slate-400">Convoro</div>
          <div class="text-xl font-extrabold text-white">{{ update.current }}</div>
        </div>
        <div v-if="update.available" class="rounded-xl border border-indigo-500/40 bg-indigo-500/10 px-4 py-2 text-sm text-indigo-200">
          Version <strong>{{ update.latest }}</strong> is available.
          <a v-if="update.url" :href="update.url" target="_blank" class="ml-1 font-semibold underline">Details</a>
        </div>
        <div v-else class="rounded-xl bg-emerald-500/10 px-4 py-2 text-sm text-emerald-300">You’re on the latest version.</div>
        <button class="ml-auto rounded-lg bg-indigo-500 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-600" @click="checkUpdates">Check for updates</button>
      </div>
    </section>

    <!-- Extensions / themes (coming with the extension system) -->
    <section class="rounded-2xl border border-dashed border-white/10 bg-[#14172a] p-10 text-center">
      <div class="text-3xl">🧩</div>
      <h3 class="mt-2 text-lg font-bold text-white">Extensions &amp; themes</h3>
      <p class="mx-auto mt-1 max-w-md text-sm text-slate-400">
        Browse and click-to-install free and premium extensions and themes. Paid items unlock with a license key from your account.
        The marketplace lights up when the extension system ships.
      </p>
      <span class="mt-4 inline-block rounded-full bg-white/5 px-3 py-1 text-xs font-semibold text-slate-400">Coming in the extension system phase</span>
    </section>
  </AdminLayout>
</template>
