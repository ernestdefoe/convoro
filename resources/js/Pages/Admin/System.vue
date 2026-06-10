<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps<{
  info: { version: string; php: string; laravel: string; database: string; cache: string; queue: string };
  update: { current: string; latest: string; available: boolean; url: string | null; checkedAt: string | null; enabled: boolean };
}>();

const page = usePage();
const status = computed(() => (page.props as any).flash?.status as string | undefined);

const tasks = [
  { action: 'cache', label: 'Clear caches', desc: 'Flush config, route, view & app caches' },
  { action: 'optimize', label: 'Optimize', desc: 'Cache config, routes & views for speed' },
  { action: 'migrate', label: 'Run migrations', desc: 'Apply pending database migrations' },
  { action: 'storage', label: 'Link storage', desc: 'Create the public storage symlink' },
  { action: 'icons', label: 'Rebuild icons', desc: 'Regenerate the PWA/favicon icon set' },
];

function run(action: string) {
  router.post('/admin/system/run', { action }, { preserveScroll: true });
}
function checkUpdates() {
  router.post('/admin/system/check-updates', {}, { preserveScroll: true });
}
const updating = ref(false);
function applyUpdate() {
  if (!confirm('Download and install the latest version now? The site will update its files and run migrations.')) return;
  updating.value = true;
  router.post('/admin/system/update', {}, { preserveScroll: true, onFinish: () => (updating.value = false) });
}
</script>

<template>
  <Head title="Admin · System" />
  <AdminLayout>
    <template #title>System</template>
    <template #subtitle>Maintenance, updates &amp; environment</template>

    <div v-if="status" class="mb-5 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">{{ status }}</div>

    <div class="grid gap-6 lg:grid-cols-2">
      <!-- Updates -->
      <section class="rounded-2xl border border-white/5 bg-[#14172a] p-5">
        <h3 class="mb-3 text-sm font-bold text-white">Software updates</h3>
        <div class="flex items-center justify-between rounded-xl bg-[#0f1120] px-4 py-3">
          <div>
            <div class="text-sm text-slate-400">Current version</div>
            <div class="text-lg font-bold text-white">{{ update.current }}</div>
          </div>
          <span v-if="update.available" class="rounded-full bg-indigo-500/20 px-3 py-1 text-xs font-bold text-indigo-300">{{ update.latest }} available</span>
          <span v-else class="rounded-full bg-emerald-500/20 px-3 py-1 text-xs font-bold text-emerald-300">Up to date</span>
        </div>
        <p v-if="!update.enabled" class="mt-2 text-xs text-slate-500">Set <code>CONVORO_UPDATE_URL</code> in .env to enable update checks.</p>
        <p v-if="update.checkedAt" class="mt-2 text-xs text-slate-500">Last checked {{ update.checkedAt }}</p>
        <div class="mt-3 flex flex-wrap gap-2">
          <button class="rounded-lg bg-white/5 px-3 py-2 text-sm font-semibold text-slate-200 hover:bg-white/10" @click="checkUpdates">Check for updates</button>
          <button v-if="update.available" :disabled="updating" class="rounded-lg bg-indigo-500 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-600 disabled:opacity-60" @click="applyUpdate">
            {{ updating ? 'Updating…' : `Update to ${update.latest}` }}
          </button>
          <a v-if="update.available && update.url" :href="update.url" target="_blank" class="rounded-lg border border-white/10 px-3 py-2 text-sm font-semibold text-slate-200 hover:bg-white/5">Release notes</a>
        </div>
      </section>

      <!-- Environment -->
      <section class="rounded-2xl border border-white/5 bg-[#14172a] p-5">
        <h3 class="mb-3 text-sm font-bold text-white">Environment</h3>
        <dl class="space-y-1.5 text-sm">
          <div class="flex justify-between"><dt class="text-slate-400">Convoro</dt><dd class="text-slate-200">{{ info.version }}</dd></div>
          <div class="flex justify-between"><dt class="text-slate-400">PHP</dt><dd class="text-slate-200">{{ info.php }}</dd></div>
          <div class="flex justify-between"><dt class="text-slate-400">Laravel</dt><dd class="text-slate-200">{{ info.laravel }}</dd></div>
          <div class="flex justify-between"><dt class="text-slate-400">Database</dt><dd class="text-slate-200">{{ info.database }}</dd></div>
          <div class="flex justify-between"><dt class="text-slate-400">Cache</dt><dd class="text-slate-200">{{ info.cache }}</dd></div>
          <div class="flex justify-between"><dt class="text-slate-400">Queue</dt><dd class="text-slate-200">{{ info.queue }}</dd></div>
        </dl>
      </section>

      <!-- Maintenance -->
      <section class="rounded-2xl border border-white/5 bg-[#14172a] p-5 lg:col-span-2">
        <h3 class="mb-1 text-sm font-bold text-white">Maintenance</h3>
        <p class="mb-3 text-xs text-slate-500">Run these from the browser — no SSH required (ideal on shared hosting).</p>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          <div v-for="t in tasks" :key="t.action" class="rounded-xl border border-white/5 bg-[#0f1120] p-4">
            <div class="font-semibold text-slate-100">{{ t.label }}</div>
            <div class="mt-0.5 text-xs text-slate-500">{{ t.desc }}</div>
            <button class="mt-3 rounded-lg bg-white/5 px-3 py-1.5 text-sm font-semibold text-slate-200 hover:bg-white/10" @click="run(t.action)">Run</button>
          </div>
        </div>
      </section>
    </div>
  </AdminLayout>
</template>
