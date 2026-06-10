<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps<{
  values: { name: string; tagline: string; default_view: string; realtime: boolean; digests: boolean; pwa_banner: boolean; pwa_short_name: string; fa_kit_url: string };
}>();

const form = useForm({
  name: props.values.name,
  tagline: props.values.tagline,
  default_view: props.values.default_view,
  realtime: props.values.realtime,
  digests: props.values.digests,
  pwa_banner: props.values.pwa_banner,
  pwa_short_name: props.values.pwa_short_name,
  fa_kit_url: props.values.fa_kit_url ?? '',
});

function save() {
  form.post('/admin/settings', { preserveScroll: true });
}
</script>

<template>
  <Head title="Admin · Settings" />
  <AdminLayout>
    <template #title>Settings</template>
    <template #subtitle>General community configuration</template>

    <form class="max-w-2xl space-y-6" @submit.prevent="save">
      <div class="rounded-2xl border border-white/5 bg-[#14172a] p-6 space-y-5">
        <div>
          <label class="block text-sm font-medium text-slate-300">Community name</label>
          <input v-model="form.name" type="text" class="mt-1.5 w-full rounded-lg border-white/10 bg-[#0f1120] text-slate-100 focus:border-indigo-500 focus:ring-indigo-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-300">Tagline</label>
          <input v-model="form.tagline" type="text" class="mt-1.5 w-full rounded-lg border-white/10 bg-[#0f1120] text-slate-100 focus:border-indigo-500 focus:ring-indigo-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-300">Default forum view</label>
          <select v-model="form.default_view" class="mt-1.5 w-full rounded-lg border-white/10 bg-[#0f1120] text-slate-100 focus:border-indigo-500 focus:ring-indigo-500">
            <option value="feed">Feed</option>
            <option value="grid">Grid</option>
          </select>
        </div>
        <label class="flex items-center gap-3">
          <input v-model="form.realtime" type="checkbox" class="rounded border-white/10 bg-[#0f1120] text-indigo-500 focus:ring-indigo-500" />
          <span class="text-sm text-slate-300">Enable live threads (realtime) — requires a WebSocket server; leave off on shared hosting</span>
        </label>
      </div>

      <div class="rounded-2xl border border-white/5 bg-[#14172a] p-6 space-y-5">
        <h2 class="text-sm font-bold uppercase tracking-wide text-slate-400">Email digests</h2>
        <label class="flex items-center gap-3">
          <input v-model="form.digests" type="checkbox" class="rounded border-white/10 bg-[#0f1120] text-indigo-500 focus:ring-indigo-500" />
          <span class="text-sm text-slate-300">Send digest emails (members choose their frequency in their profile)</span>
        </label>
      </div>

      <div class="rounded-2xl border border-white/5 bg-[#14172a] p-6 space-y-5">
        <h2 class="text-sm font-bold uppercase tracking-wide text-slate-400">Progressive Web App</h2>
        <div>
          <label class="block text-sm font-medium text-slate-300">App short name (home-screen label)</label>
          <input v-model="form.pwa_short_name" type="text" maxlength="30" class="mt-1.5 w-full rounded-lg border-white/10 bg-[#0f1120] text-slate-100 focus:border-indigo-500 focus:ring-indigo-500" />
        </div>
        <label class="flex items-center gap-3">
          <input v-model="form.pwa_banner" type="checkbox" class="rounded border-white/10 bg-[#0f1120] text-indigo-500 focus:ring-indigo-500" />
          <span class="text-sm text-slate-300">Show the “install app” banner to visitors on supported devices</span>
        </label>
      </div>

      <div class="rounded-2xl border border-white/5 bg-[#14172a] p-6 space-y-3">
        <h2 class="text-sm font-bold uppercase tracking-wide text-slate-400">Icons</h2>
        <p class="text-sm text-slate-500">Convoro bundles Font Awesome Free. To use a Pro or custom <a href="https://fontawesome.com/kits" target="_blank" class="text-indigo-400 underline">Font Awesome Kit</a>, paste its script URL here.</p>
        <input v-model="form.fa_kit_url" type="url" placeholder="https://kit.fontawesome.com/xxxxxxxx.js"
          class="w-full rounded-lg border-white/10 bg-[#0f1120] font-mono text-sm text-slate-100 focus:border-indigo-500 focus:ring-indigo-500" />
      </div>

      <div class="flex items-center gap-3">
        <button type="submit" :disabled="form.processing" class="rounded-lg bg-indigo-500 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-600 disabled:opacity-60">Save changes</button>
        <span v-if="form.recentlySuccessful" class="text-sm text-emerald-400">Saved.</span>
      </div>
    </form>
  </AdminLayout>
</template>
