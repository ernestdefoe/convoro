<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps<{
  values: { name: string; tagline: string; default_view: string; realtime: boolean };
}>();

const form = useForm({
  name: props.values.name,
  tagline: props.values.tagline,
  default_view: props.values.default_view,
  realtime: props.values.realtime,
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

      <div class="flex items-center gap-3">
        <button type="submit" :disabled="form.processing" class="rounded-lg bg-indigo-500 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-600 disabled:opacity-60">Save changes</button>
        <span v-if="form.recentlySuccessful" class="text-sm text-emerald-400">Saved.</span>
      </div>
    </form>
  </AdminLayout>
</template>
