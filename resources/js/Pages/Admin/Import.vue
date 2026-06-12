<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, reactive, ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';

const props = defineProps<{
  state: { running: boolean; percent: number; status: string | null; summary: Record<string, number>; lastStatus: string | null };
}>();

const page = usePage();
const flash = computed(() => (page.props as any).flash?.status as string | undefined);

const form = reactive({
  host: '127.0.0.1',
  port: 3306,
  database: '',
  username: '',
  password: '',
  prefix: '',
  flarum_url: '',
  import_tags: true,
});

const testing = ref(false);
const testError = ref<string | null>(null);
const counts = ref<Record<string, number> | null>(null);

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
    if (!r.ok || !d.ok) {
      testError.value = d.message || t('Could not connect.');
    } else {
      counts.value = d.counts;
    }
  } catch (e: any) {
    testError.value = t('Could not reach the server.');
  } finally {
    testing.value = false;
  }
}

function startImport() {
  if (!confirm(t('Start importing from this Flarum database into Convoro? This adds content to your community.'))) return;
  router.post('/admin/import/start', { ...form }, {
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
      if (!d.running) { stop(); }
    } catch { /* ignore */ }
  };
  await tick();
  timer = window.setInterval(tick, 2000);
}
function stop() { if (timer) { clearInterval(timer); timer = null; } }
onBeforeUnmount(stop);

if (props.state.running) poll();

const summaryRows = computed(() => Object.entries(live.summary || {}));
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
        <div class="flex items-center gap-2">
          <span class="grid h-8 w-8 place-items-center rounded-lg bg-primary/15 text-primary font-bold">F</span>
          <div>
            <h3 class="text-sm font-bold text-ink">{{ t('Import from Flarum') }}</h3>
            <p class="text-xs text-ink-muted">{{ t('Connect to your Flarum database (read-only). More platforms coming soon.') }}</p>
          </div>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-3">
          <div class="col-span-2 sm:col-span-1"><label class="text-xs font-semibold text-ink-2">{{ t('DB host') }}</label><input v-model="form.host" :class="field" /></div>
          <div class="col-span-2 sm:col-span-1"><label class="text-xs font-semibold text-ink-2">{{ t('Port') }}</label><input v-model.number="form.port" type="number" :class="field" /></div>
          <div class="col-span-2"><label class="text-xs font-semibold text-ink-2">{{ t('Database name') }}</label><input v-model="form.database" :class="field" placeholder="flarum" /></div>
          <div class="col-span-2 sm:col-span-1"><label class="text-xs font-semibold text-ink-2">{{ t('DB username') }}</label><input v-model="form.username" :class="field" /></div>
          <div class="col-span-2 sm:col-span-1"><label class="text-xs font-semibold text-ink-2">{{ t('DB password') }}</label><input v-model="form.password" type="password" :class="field" /></div>
          <div class="col-span-2 sm:col-span-1"><label class="text-xs font-semibold text-ink-2">{{ t('Table prefix') }} <span class="font-normal text-ink-muted">{{ t('(optional)') }}</span></label><input v-model="form.prefix" :class="field" placeholder="" /></div>
          <div class="col-span-2 sm:col-span-1"><label class="text-xs font-semibold text-ink-2">{{ t('Flarum URL') }} <span class="font-normal text-ink-muted">{{ t('(avatars & images)') }}</span></label><input v-model="form.flarum_url" :class="field" placeholder="https://old.forum" /></div>
        </div>

        <label class="mt-4 flex items-center gap-2 text-sm text-ink-2">
          <input v-model="form.import_tags" type="checkbox" class="rounded border-line text-primary focus:ring-primary" />
          {{ t('Import tags as categories') }}
        </label>

        <div class="mt-4 flex flex-wrap items-center gap-3">
          <button :disabled="testing || !form.database || !form.username" class="rounded-lg bg-surface-2 px-3 py-2 text-sm font-semibold text-ink hover:bg-appbg disabled:opacity-50" @click="testConnection">
            {{ testing ? t('Testing…') : t('Test connection') }}
          </button>
          <button v-if="counts" :disabled="live.running" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-600 disabled:opacity-50" @click="startImport">
            {{ t('Start import') }}
          </button>
        </div>

        <p v-if="testError" class="mt-3 rounded-lg border border-red-500/30 bg-red-500/10 px-3 py-2 text-sm text-red-400">{{ testError }}</p>
        <div v-if="counts" class="mt-3 rounded-xl border border-emerald-500/30 bg-emerald-500/5 p-3 text-sm">
          <div class="mb-1 font-bold text-emerald-300">{{ t('Connected ✓ Ready to import:') }}</div>
          <div class="flex flex-wrap gap-x-5 gap-y-1 text-ink-2">
            <span><b class="text-ink">{{ counts.users }}</b> {{ t('members') }}</span>
            <span><b class="text-ink">{{ counts.tags }}</b> {{ t('tags') }}</span>
            <span><b class="text-ink">{{ counts.discussions }}</b> {{ t('discussions') }}</span>
            <span><b class="text-ink">{{ counts.posts }}</b> {{ t('posts') }}</span>
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
      {{ t("The importer reads your Flarum database directly and maps primary tags → categories, secondary tags → tags, discussions → topics, posts → posts (converting Flarum's formatting to Convoro), and likes → reactions. Members keep their passwords; set the Flarum URL to bring across avatars and embedded images. It's safe to re-run — existing members, categories and topics are skipped rather than duplicated.") }}
    </p>
  </AdminLayout>
</template>
