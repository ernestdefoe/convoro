<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, reactive, ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';

type Source = { id: string; name: string; db: string; prefix: string; tested: boolean };

const props = defineProps<{
  state: { running: boolean; percent: number; status: string | null; summary: Record<string, number>; lastStatus: string | null };
  sources: Source[];
}>();

const page = usePage();
const flash = computed(() => (page.props as any).flash?.status as string | undefined);

const form = reactive({
  source: 'flarum',
  host: '127.0.0.1',
  port: 3306,
  database: '',
  username: '',
  password: '',
  prefix: '',
  source_url: '',
  import_tags: true,
});

const current = computed<Source>(() => props.sources.find((s) => s.id === form.source) || props.sources[0]);

function selectSource(id: string) {
  const s = props.sources.find((x) => x.id === id);
  if (!s) return;
  form.source = id;
  form.prefix = s.prefix;
  form.port = s.db === 'PostgreSQL' ? 5432 : 3306;
  counts.value = null;
  testError.value = null;
}

const testing = ref(false);
const testError = ref<string | null>(null);
const counts = ref<Record<string, number> | null>(null);

// Connect to a live DB, or upload a database file (managed hosts that only give
// you the DB). File mode loads a SQLite file directly or a MySQL dump into one.
const mode = ref<'connect' | 'file'>('connect');
const fileEl = ref<HTMLInputElement | null>(null);
const fileName = ref('');
const uploading = ref(false);
function setMode(m: 'connect' | 'file') { mode.value = m; counts.value = null; testError.value = null; }
function onFile() { fileName.value = fileEl.value?.files?.[0]?.name ?? ''; counts.value = null; testError.value = null; }

async function uploadFile() {
  const f = fileEl.value?.files?.[0];
  if (!f) { testError.value = t('Choose a database file first.'); return; }
  uploading.value = true; testError.value = null; counts.value = null;
  try {
    const fd = new FormData();
    fd.append('file', f);
    fd.append('source', form.source);
    fd.append('prefix', form.prefix);
    fd.append('source_url', form.source_url);
    const r = await fetch('/admin/import/upload', { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' }, body: fd });
    const d = await r.json();
    if (!r.ok || !d.ok) testError.value = d.message || t('Could not read the file.');
    else counts.value = d.counts;
  } catch { testError.value = t('Upload failed.'); } finally { uploading.value = false; }
}

const live = reactive({ ...props.state });
let timer: number | null = null;

function csrf() {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
}

async function testConnection() {
  testing.value = true;
  testError.value = null;
  counts.value = null;
  try {
    const r = await fetch('/admin/import/test', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
      body: JSON.stringify(form),
    });
    const d = await r.json();
    if (!r.ok || !d.ok) testError.value = d.message || t('Could not connect.');
    else counts.value = d.counts;
  } catch (e: any) {
    testError.value = t('Could not reach the server.');
  } finally {
    testing.value = false;
  }
}

function startImport() {
  if (!confirm(t('Start importing from {name} into Convoro? This adds content to your community. Back up your database first.', { name: current.value.name }))) return;
  router.post('/admin/import/start', { ...form, mode: mode.value }, {
    preserveScroll: true,
    onSuccess: () => { live.running = true; poll(); },
  });
}

async function poll() {
  if (timer) return;
  const tick = async () => {
    try {
      const r = await fetch('/admin/import/progress', { headers: { Accept: 'application/json' } });
      const d = await r.json();
      Object.assign(live, d);
      if (!d.running) stop();
    } catch { /* ignore */ }
  };
  await tick();
  timer = window.setInterval(tick, 2000);
}
function stop() { if (timer) { clearInterval(timer); timer = null; } }
onBeforeUnmount(stop);
if (props.state.running) poll();

const summaryRows = computed(() => Object.entries(live.summary || {}));
const countRows = computed(() => Object.entries(counts.value || {}));
const field = 'mt-1 w-full rounded-lg border-line bg-appbg text-ink placeholder:text-ink-muted focus:border-primary focus:ring-primary text-sm';
</script>

<template>
  <Head :title="t('Admin · Import')" />
  <AdminLayout>
    <template #title>{{ t('Import') }}</template>
    <template #subtitle>{{ t('Migrate a community from other forum software') }}</template>

    <div v-if="flash" class="mb-5 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">{{ flash }}</div>

    <div class="grid gap-6 lg:grid-cols-2">
      <!-- Source -->
      <section class="rounded-2xl border border-line bg-surface p-5">
        <h3 class="text-sm font-bold text-ink">{{ t('Choose your current platform') }}</h3>
        <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
          <button v-for="s in sources" :key="s.id" type="button" @click="selectSource(s.id)"
            class="rounded-xl border px-3 py-2.5 text-left transition"
            :class="form.source === s.id ? 'border-primary bg-primary/10 text-primary' : 'border-line text-ink-2 hover:border-primary/50'">
            <div class="text-sm font-bold">{{ s.name }}</div>
            <div class="text-[11px] opacity-70">{{ s.db }}<template v-if="!s.tested"> · beta</template></div>
          </button>
        </div>

        <p v-if="!current.tested" class="mt-3 rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-xs text-amber-300">
          {{ t('{name} import is newer — its mapping is automatically tested against the {name} schema, but real forums vary. Back up your Convoro database first and review the result.', { name: current.name }) }}
        </p>

        <!-- connect to a live DB, or upload a database file -->
        <div class="mt-4 flex w-max rounded-lg border border-line p-0.5">
          <button type="button" class="rounded-md px-3 py-1.5 text-xs font-bold" :class="mode === 'connect' ? 'bg-primary text-white' : 'text-ink-muted hover:text-ink'" @click="setMode('connect')">{{ t('Connect to database') }}</button>
          <button type="button" class="rounded-md px-3 py-1.5 text-xs font-bold" :class="mode === 'file' ? 'bg-primary text-white' : 'text-ink-muted hover:text-ink'" @click="setMode('file')">{{ t('Upload database file') }}</button>
        </div>

        <!-- connect mode -->
        <div v-show="mode === 'connect'" class="mt-4 grid grid-cols-2 gap-3">
          <div class="col-span-2 sm:col-span-1"><label class="text-xs font-semibold text-ink-2">{{ t('DB host') }}</label><input v-model="form.host" :class="field" /></div>
          <div class="col-span-2 sm:col-span-1"><label class="text-xs font-semibold text-ink-2">{{ t('Port') }}</label><input v-model.number="form.port" type="number" :class="field" /></div>
          <div class="col-span-2"><label class="text-xs font-semibold text-ink-2">{{ t('Database name') }}</label><input v-model="form.database" :class="field" /></div>
          <div class="col-span-2 sm:col-span-1"><label class="text-xs font-semibold text-ink-2">{{ t('DB username') }}</label><input v-model="form.username" :class="field" /></div>
          <div class="col-span-2 sm:col-span-1"><label class="text-xs font-semibold text-ink-2">{{ t('DB password') }}</label><input v-model="form.password" type="password" :class="field" /></div>
          <div class="col-span-2 sm:col-span-1"><label class="text-xs font-semibold text-ink-2">{{ t('Table prefix') }} <span class="font-normal text-ink-muted">{{ t('(optional)') }}</span></label><input v-model="form.prefix" :class="field" /></div>
          <div class="col-span-2 sm:col-span-1"><label class="text-xs font-semibold text-ink-2">{{ t('Old forum URL') }} <span class="font-normal text-ink-muted">{{ t('(avatars & images)') }}</span></label><input v-model="form.source_url" :class="field" placeholder="https://old.forum" /></div>
        </div>

        <!-- file mode -->
        <div v-show="mode === 'file'" class="mt-4 space-y-3">
          <p class="rounded-lg border border-line bg-appbg px-3 py-2 text-xs text-ink-muted">
            {{ t('Only have your database file? Upload a SQLite file, or a MySQL dump (.sql or .sql.gz) — common when a managed host only gives you the database. We load it and read it the same way as a live connection.') }}
          </p>
          <label class="flex cursor-pointer items-center justify-between gap-3 rounded-lg border border-dashed border-line bg-appbg px-4 py-3 hover:border-primary/50">
            <span class="truncate text-sm" :class="fileName ? 'text-ink' : 'text-ink-muted'">{{ fileName || t('Choose a .sql, .sql.gz or .sqlite file…') }}</span>
            <span class="shrink-0 rounded-md bg-surface-2 px-3 py-1.5 text-xs font-bold text-ink">{{ t('Browse') }}</span>
            <input ref="fileEl" type="file" accept=".sql,.gz,.sqlite,.sqlite3,.db" class="hidden" @change="onFile" />
          </label>
          <div class="grid grid-cols-2 gap-3">
            <div class="col-span-2 sm:col-span-1"><label class="text-xs font-semibold text-ink-2">{{ t('Table prefix') }} <span class="font-normal text-ink-muted">{{ t('(optional)') }}</span></label><input v-model="form.prefix" :class="field" /></div>
            <div class="col-span-2 sm:col-span-1"><label class="text-xs font-semibold text-ink-2">{{ t('Old forum URL') }} <span class="font-normal text-ink-muted">{{ t('(avatars & images)') }}</span></label><input v-model="form.source_url" :class="field" placeholder="https://old.forum" /></div>
          </div>
        </div>

        <label v-if="form.source === 'flarum'" class="mt-4 flex items-center gap-2 text-sm text-ink-2">
          <input v-model="form.import_tags" type="checkbox" class="rounded border-line text-primary focus:ring-primary" />
          {{ t('Import tags as categories') }}
        </label>

        <div class="mt-4 flex flex-wrap items-center gap-3">
          <button v-if="mode === 'connect'" :disabled="testing || !form.database || !form.username" class="rounded-lg bg-surface-2 px-3 py-2 text-sm font-semibold text-ink hover:bg-appbg disabled:opacity-50" @click="testConnection">
            {{ testing ? t('Testing…') : t('Test connection') }}
          </button>
          <button v-else :disabled="uploading || !fileName" class="rounded-lg bg-surface-2 px-3 py-2 text-sm font-semibold text-ink hover:bg-appbg disabled:opacity-50" @click="uploadFile">
            {{ uploading ? t('Reading file…') : t('Upload &amp; scan') }}
          </button>
          <button v-if="counts" :disabled="live.running" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-600 disabled:opacity-50" @click="startImport">
            {{ t('Start import') }}
          </button>
        </div>

        <p v-if="testError" class="mt-3 rounded-lg border border-red-500/30 bg-red-500/10 px-3 py-2 text-sm text-red-400">{{ testError }}</p>
        <div v-if="counts" class="mt-3 rounded-xl border border-emerald-500/30 bg-emerald-500/5 p-3 text-sm">
          <div class="mb-1 font-bold text-emerald-300">{{ t('Connected ✓ Ready to import:') }}</div>
          <div class="flex flex-wrap gap-x-5 gap-y-1 text-ink-2">
            <span v-for="[k, v] in countRows" :key="k"><b class="text-ink">{{ v }}</b> {{ k }}</span>
          </div>
        </div>
      </section>

      <!-- Progress -->
      <section class="rounded-2xl border border-line bg-surface p-5">
        <h3 class="mb-3 text-sm font-bold text-ink">{{ t('Progress') }}</h3>

        <div v-if="live.running || live.percent > 0">
          <div class="mb-2 flex items-center justify-between text-sm">
            <span class="text-ink-2">{{ live.status || t('Working…') }}</span>
            <span class="font-bold text-ink">{{ live.percent }}%</span>
          </div>
          <div class="h-2.5 w-full overflow-hidden rounded-full bg-appbg">
            <div class="h-full rounded-full bg-primary transition-all duration-500" :style="{ width: live.percent + '%' }"></div>
          </div>

          <div v-if="summaryRows.length" class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3">
            <div v-for="[k, v] in summaryRows" :key="k" class="rounded-lg bg-appbg px-3 py-2">
              <div class="text-lg font-bold text-ink">{{ v }}</div>
              <div class="text-xs capitalize text-ink-muted">{{ k }}</div>
            </div>
          </div>
        </div>

        <p v-else-if="live.lastStatus" class="rounded-lg border border-line bg-appbg px-3 py-2 text-sm text-ink-2">{{ live.lastStatus }}</p>
        <p v-else class="text-sm text-ink-muted">{{ t('Test your connection, then start the import. It runs in the background — you can leave this page and come back.') }}</p>

        <p v-if="live.running" class="mt-3 inline-flex items-center gap-2 text-xs font-semibold text-amber-400">
          <span class="h-2 w-2 animate-pulse rounded-full bg-amber-400"></span> {{ t('Running in the background…') }}
        </p>
      </section>
    </div>

    <p class="mt-6 max-w-2xl text-xs text-ink-muted">
      {{ t("The importer reads your old forum's database directly (read-only) and maps forums/tags → categories, users → members, threads/discussions → topics and posts → posts, converting the old formatting to Convoro. Members keep their passwords where the old hashes are portable (Flarum, XenForo, phpBB 3.1+) — otherwise they reset on first login. Set the old forum URL to bring across avatars and embedded images. It's safe to re-run: existing members, categories and topics are skipped rather than duplicated.") }}
    </p>
  </AdminLayout>
</template>
