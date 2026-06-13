<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, reactive, ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';

type Backup = { name: string; size: number; created_at: string; kind: string; offsite: boolean };
type LastResult = { ok: boolean; mode: string; message: string; name?: string; at: string } | null;

const props = defineProps<{
  available: boolean;
  reason: string | null;
  driver: string;
  offsiteConfigured: boolean;
  running: boolean;
  status: string;
  last: LastResult;
  backups: Backup[];
  settings: { enabled: boolean; frequency: string; retention: number; offsite: boolean };
}>();

const page = usePage();
const flash = computed(() => (page.props as any).flash?.status as string | undefined);

const live = reactive({
  running: props.running,
  status: props.status,
  last: props.last as LastResult,
  backups: props.backups,
});

const settings = reactive({ ...props.settings });
const uploadFile = ref<File | null>(null);

let timer: number | null = null;
async function poll() {
  if (timer) return;
  const tick = async () => {
    try {
      const r = await fetch('/admin/backups/status', { headers: { Accept: 'application/json' } });
      const d = await r.json();
      live.running = d.running;
      live.status = d.status;
      live.last = d.last;
      live.backups = d.backups;
      if (!d.running) stop();
    } catch { /* ignore */ }
  };
  await tick();
  timer = window.setInterval(tick, 2500);
}
function stop() { if (timer) { clearInterval(timer); timer = null; } }
onBeforeUnmount(stop);
if (props.running) poll();

function backupNow() {
  router.post('/admin/backups/run', {}, { preserveScroll: true, onSuccess: () => { live.running = true; poll(); } });
}

function confirmRestore(): string | null {
  const typed = window.prompt(t('Restoring REPLACES your entire database with this backup. A safety snapshot of the current database is taken first. Type RESTORE to continue.'));
  return typed === 'RESTORE' ? 'RESTORE' : null;
}

function restoreNamed(name: string) {
  const confirm = confirmRestore();
  if (!confirm) return;
  router.post('/admin/backups/restore', { name, confirm }, { preserveScroll: true, onSuccess: () => { live.running = true; poll(); } });
}

function restoreUpload() {
  if (!uploadFile.value) return;
  const confirm = confirmRestore();
  if (!confirm) return;
  router.post('/admin/backups/restore', { file: uploadFile.value, confirm }, {
    forceFormData: true,
    preserveScroll: true,
    onSuccess: () => { live.running = true; uploadFile.value = null; poll(); },
  });
}

function destroy(name: string) {
  if (!window.confirm(t('Delete this backup? This cannot be undone.'))) return;
  router.delete(`/admin/backups/${encodeURIComponent(name)}`, { preserveScroll: true });
}

function saveSettings() {
  router.post('/admin/backups/settings', { ...settings }, { preserveScroll: true });
}

function fmtSize(b: number): string {
  if (b < 1024) return `${b} B`;
  if (b < 1024 * 1024) return `${(b / 1024).toFixed(1)} KB`;
  return `${(b / 1024 / 1024).toFixed(1)} MB`;
}
function fmtDate(iso: string): string {
  try { return new Date(iso).toLocaleString(); } catch { return iso; }
}

const onFile = (e: Event) => { uploadFile.value = (e.target as HTMLInputElement).files?.[0] ?? null; };
const field = 'mt-1 w-full rounded-lg border-line bg-appbg text-ink focus:border-primary focus:ring-primary text-sm';
</script>

<template>
  <Head :title="t('Admin · Backups')" />
  <AdminLayout>
    <template #title>{{ t('Backups') }}</template>
    <template #subtitle>{{ t('Download, restore, and schedule database backups') }}</template>

    <div v-if="flash" class="mb-5 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">{{ flash }}</div>

    <div v-if="!available" class="mb-5 rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-300">
      {{ t('Backups are not available on this host.') }}<span v-if="reason"> {{ reason }}</span>
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
      <!-- Create + list -->
      <section class="rounded-2xl border border-line bg-surface p-5">
        <div class="flex items-center justify-between gap-3">
          <div>
            <h3 class="text-sm font-bold text-ink">{{ t('Database backups') }}</h3>
            <p class="mt-0.5 text-xs text-ink-muted">{{ t('Engine: {driver}', { driver }) }}</p>
          </div>
          <button type="button" :disabled="!available || live.running" @click="backupNow"
            class="rounded-lg bg-primary px-3 py-2 text-sm font-bold text-white disabled:opacity-50">
            {{ live.running ? t('Working…') : t('Back up now') }}
          </button>
        </div>

        <div v-if="live.running" class="mt-3 flex items-center gap-2 text-sm text-ink-2">
          <span class="h-2 w-2 animate-pulse rounded-full bg-primary"></span>{{ live.status || t('Working…') }}
        </div>
        <div v-else-if="live.last" class="mt-3 text-sm" :class="live.last.ok ? 'text-emerald-400' : 'text-rose-400'">
          {{ live.last.message }}
        </div>

        <ul class="mt-4 divide-y divide-line">
          <li v-for="b in live.backups" :key="b.name" class="flex items-center gap-3 py-2.5">
            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-2">
                <span class="truncate text-sm font-medium text-ink">{{ b.name }}</span>
                <span v-if="b.kind === 'safety'" class="rounded-full bg-amber-500/15 px-1.5 py-0.5 text-[10px] font-bold uppercase text-amber-400">{{ t('safety') }}</span>
                <span v-if="b.offsite" class="rounded-full bg-sky-500/15 px-1.5 py-0.5 text-[10px] font-bold uppercase text-sky-400" :title="t('Mirrored offsite')">{{ t('offsite') }}</span>
              </div>
              <div class="text-xs text-ink-muted">{{ fmtSize(b.size) }} · {{ fmtDate(b.created_at) }}</div>
            </div>
            <a :href="`/admin/backups/${encodeURIComponent(b.name)}/download`"
              class="rounded-lg border border-line px-2.5 py-1.5 text-xs font-bold text-ink-2 hover:border-primary/50">{{ t('Download') }}</a>
            <button type="button" :disabled="!available || live.running" @click="restoreNamed(b.name)"
              class="rounded-lg border border-line px-2.5 py-1.5 text-xs font-bold text-ink-2 hover:border-primary/50 disabled:opacity-50">{{ t('Restore') }}</button>
            <button type="button" @click="destroy(b.name)"
              class="rounded-lg border border-rose-500/30 px-2.5 py-1.5 text-xs font-bold text-rose-400 hover:bg-rose-500/10">{{ t('Delete') }}</button>
          </li>
          <li v-if="!live.backups.length" class="py-6 text-center text-sm text-ink-muted">{{ t('No backups yet.') }}</li>
        </ul>
      </section>

      <div class="flex flex-col gap-6">
        <!-- Restore from file -->
        <section class="rounded-2xl border border-line bg-surface p-5">
          <h3 class="text-sm font-bold text-ink">{{ t('Restore from a file') }}</h3>
          <p class="mt-1 text-xs text-ink-muted">{{ t('Upload a .gz backup created by Convoro. This replaces your database — a safety snapshot is taken first.') }}</p>
          <input type="file" accept=".gz" @change="onFile" :class="field" />
          <button type="button" :disabled="!available || live.running || !uploadFile" @click="restoreUpload"
            class="mt-3 rounded-lg border border-rose-500/40 px-3 py-2 text-sm font-bold text-rose-300 hover:bg-rose-500/10 disabled:opacity-50">
            {{ t('Restore from upload') }}
          </button>
        </section>

        <!-- Schedule + offsite -->
        <section class="rounded-2xl border border-line bg-surface p-5">
          <h3 class="text-sm font-bold text-ink">{{ t('Scheduled backups') }}</h3>

          <label class="mt-3 flex items-center gap-2 text-sm text-ink-2">
            <input type="checkbox" v-model="settings.enabled" class="rounded border-line text-primary focus:ring-primary" />
            {{ t('Automatically back up on a schedule') }}
          </label>

          <div class="mt-3 grid grid-cols-2 gap-3">
            <label class="text-sm text-ink-2">{{ t('Frequency') }}
              <select v-model="settings.frequency" :class="field">
                <option value="daily">{{ t('Daily') }}</option>
                <option value="weekly">{{ t('Weekly') }}</option>
              </select>
            </label>
            <label class="text-sm text-ink-2">{{ t('Keep last') }}
              <input type="number" min="1" max="365" v-model.number="settings.retention" :class="field" />
            </label>
          </div>

          <label class="mt-3 flex items-center gap-2 text-sm" :class="offsiteConfigured ? 'text-ink-2' : 'text-ink-muted'">
            <input type="checkbox" v-model="settings.offsite" :disabled="!offsiteConfigured" class="rounded border-line text-primary focus:ring-primary disabled:opacity-50" />
            {{ t('Also store backups offsite (S3)') }}
          </label>
          <p v-if="!offsiteConfigured" class="mt-1 text-xs text-ink-muted">{{ t('Configure an S3-compatible bucket (AWS_* in your environment) to enable offsite backups.') }}</p>

          <button type="button" @click="saveSettings" class="mt-4 rounded-lg bg-primary px-3 py-2 text-sm font-bold text-white">{{ t('Save') }}</button>
        </section>
      </div>
    </div>
  </AdminLayout>
</template>
