<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps<{
  update: { current: string; latest: string; available: boolean; url: string | null; checkedAt: string | null; enabled: boolean };
  extensions: { id: string; name: string; version: string; description: string; author: string; premium: boolean; enabled: boolean; removable: boolean }[];
}>();

const page = usePage();
const result = computed(() => (page.props as any).flash?.extResult ?? null);

function checkUpdates() {
  router.post('/admin/system/check-updates', {}, { preserveScroll: true });
}

const uploading = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);
function pickPackage(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0];
  if (!file) return;
  uploading.value = true;
  router.post('/admin/marketplace/install', { package: file }, {
    forceFormData: true,
    preserveScroll: true,
    onFinish: () => { uploading.value = false; if (fileInput.value) fileInput.value.value = ''; },
  });
}

function toggle(ext: { id: string; enabled: boolean }) {
  const url = ext.enabled ? '/admin/marketplace/disable' : '/admin/marketplace/enable';
  router.post(url, { id: ext.id }, { preserveScroll: true });
}

function uninstall(ext: { id: string; name: string }) {
  if (!confirm(`Remove “${ext.name}”? This deletes the extension and rolls back its database tables.`)) return;
  router.post('/admin/marketplace/uninstall', { id: ext.id }, { preserveScroll: true });
}
</script>

<template>
  <Head title="Admin · Marketplace" />
  <AdminLayout>
    <template #title>Marketplace</template>
    <template #subtitle>Extensions, themes &amp; software updates</template>

    <!-- Software update status -->
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

    <!-- Result flash -->
    <p v-if="result" class="mb-4 rounded-lg px-4 py-2 text-sm" :class="result.ok ? 'bg-emerald-500/10 text-emerald-300' : 'bg-red-500/10 text-red-300'">{{ result.message }}</p>

    <!-- Install from zip -->
    <section class="mb-6 rounded-2xl border border-white/5 bg-[#14172a] p-5">
      <h2 class="text-sm font-bold uppercase tracking-wide text-slate-400">Install an extension</h2>
      <p class="mt-1 text-xs text-slate-500">Upload a Convoro extension package (<code>.zip</code>). No Composer or shell needed — works on shared hosting.</p>
      <div class="mt-3 flex flex-wrap items-center gap-3">
        <input ref="fileInput" type="file" accept=".zip,application/zip" class="text-sm text-slate-300" @change="pickPackage" />
        <span v-if="uploading" class="text-sm text-slate-400">Installing…</span>
      </div>
    </section>

    <!-- Installed extensions -->
    <section class="rounded-2xl border border-white/5 bg-[#14172a] p-5">
      <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-slate-400">Installed extensions</h2>

      <div v-if="!extensions.length" class="rounded-xl border border-dashed border-white/10 p-8 text-center text-sm text-slate-400">
        No extensions installed yet. Upload one above to get started.
      </div>

      <div v-else class="space-y-3">
        <div v-for="ext in extensions" :key="ext.id" class="flex flex-wrap items-center gap-3 rounded-xl border border-white/5 bg-[#0f1120] p-4">
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
              <span class="font-semibold text-white">{{ ext.name }}</span>
              <span class="rounded bg-white/5 px-1.5 py-0.5 text-[11px] text-slate-400">v{{ ext.version }}</span>
              <span v-if="ext.premium" class="rounded bg-amber-500/15 px-1.5 py-0.5 text-[11px] font-semibold text-amber-300">Premium</span>
              <span v-if="ext.enabled" class="rounded bg-emerald-500/15 px-1.5 py-0.5 text-[11px] font-semibold text-emerald-300">Enabled</span>
            </div>
            <p class="mt-0.5 truncate text-sm text-slate-400">{{ ext.description }}</p>
            <p v-if="ext.author" class="mt-0.5 text-[11px] text-slate-500">by {{ ext.author }} · {{ ext.id }}</p>
          </div>

          <!-- Enable/disable toggle -->
          <button type="button" @click="toggle(ext)"
            class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition"
            :class="ext.enabled ? 'bg-indigo-500' : 'bg-white/15'"
            :aria-pressed="ext.enabled" :aria-label="(ext.enabled ? 'Disable ' : 'Enable ') + ext.name">
            <span class="inline-block h-4 w-4 transform rounded-full bg-white transition" :class="ext.enabled ? 'translate-x-6' : 'translate-x-1'" />
          </button>

          <button v-if="ext.removable" type="button" @click="uninstall(ext)"
            class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-400 hover:bg-red-500/10 hover:text-red-400">Remove</button>
          <span v-else class="px-2.5 py-1.5 text-[11px] text-slate-600" title="Bundled with Convoro">bundled</span>
        </div>
      </div>
    </section>

    <!-- Catalog placeholder (remote storefront comes with the convoro.co store) -->
    <section class="mt-6 rounded-2xl border border-dashed border-white/10 bg-[#14172a] p-8 text-center">
      <div class="text-2xl">🛍️</div>
      <h3 class="mt-2 text-base font-bold text-white">Browse the catalog</h3>
      <p class="mx-auto mt-1 max-w-md text-sm text-slate-400">
        One-click install of free &amp; premium extensions/themes from the Convoro store (license-key unlock) lands when the storefront goes live.
      </p>
    </section>
  </AdminLayout>
</template>
