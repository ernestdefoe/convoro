<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, reactive, ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';

interface Row {
  code: string; label: string; translated: number; total: number;
  percent: number; missing: number; rtl: boolean; source?: boolean;
}

const props = defineProps<{
  coverage: Row[];
  catalogSize: number;
  addable: { code: string; label: string }[];
  aiConfigured: boolean;
  translating: Record<string, boolean>;
}>();

const page = usePage();
const flash = computed(() => (page.props as any).flash?.message as string | undefined);
const siteLocale = computed(() => ((page.props as any).site?.locale ?? 'en') as string);

const rows = reactive<Row[]>([...props.coverage]);
const busy = reactive<Record<string, boolean>>({ ...props.translating });

// Poll while any locale is being translated in the background.
let timer: number | null = null;
function anyBusy() { return Object.values(busy).some(Boolean); }
async function tick() {
  try {
    const r = await fetch('/admin/languages/status', { headers: { Accept: 'application/json' } });
    const data = await r.json();
    rows.splice(0, rows.length, ...data.coverage);
    Object.assign(busy, data.translating);
    if (!anyBusy()) stop();
  } catch { /* ignore */ }
}
function startPolling() { if (!timer) timer = window.setInterval(tick, 3000); }
function stop() { if (timer) { clearInterval(timer); timer = null; } }
onBeforeUnmount(stop);
if (anyBusy()) startPolling();

function translate(code: string) {
  router.post('/admin/languages/translate', { locale: code }, {
    preserveScroll: true,
    onSuccess: () => { busy[code] = true; startPolling(); },
  });
}

function removeLocale(code: string, label: string) {
  if (!confirm(t('Remove {language} and all its translations?', { language: label }))) return;
  router.delete('/admin/languages', { data: { locale: code }, preserveScroll: true });
}

function setDefault(code: string) {
  router.post('/admin/languages/default', { locale: code }, { preserveScroll: true });
}

// Add a language
const addCode = ref('');
const addAndTranslate = ref(true);
function addLanguage() {
  if (!addCode.value) return;
  router.post('/admin/languages/add', { locale: addCode.value, translate: addAndTranslate.value }, {
    preserveScroll: true,
    onSuccess: () => { if (addAndTranslate.value) { busy[addCode.value] = true; startPolling(); } addCode.value = ''; },
  });
}

// Inline string editor
const editing = ref<string | null>(null);
const editLabel = ref('');
const strings = ref<{ key: string; value: string }[]>([]);
const filter = ref('');
const loadingStrings = ref(false);
const filtered = computed(() => {
  const q = filter.value.trim().toLowerCase();
  if (!q) return strings.value;
  return strings.value.filter((s) => s.key.toLowerCase().includes(q) || s.value.toLowerCase().includes(q));
});
async function openEditor(code: string, label: string) {
  editing.value = code; editLabel.value = label; loadingStrings.value = true; filter.value = '';
  try {
    const r = await fetch(`/admin/languages/strings?locale=${encodeURIComponent(code)}`, { headers: { Accept: 'application/json' } });
    strings.value = (await r.json()).strings;
  } finally { loadingStrings.value = false; }
}
function saveString(row: { key: string; value: string }) {
  router.post('/admin/languages/string', { locale: editing.value, key: row.key, value: row.value }, {
    preserveScroll: true, preserveState: true,
  });
}
function closeEditor() { editing.value = null; strings.value = []; }

const field = 'rounded-lg border-line bg-appbg text-ink placeholder:text-ink-muted focus:border-primary focus:ring-primary text-sm';
</script>

<template>
  <Head :title="t('Admin · Languages')" />
  <AdminLayout>
    <template #title>{{ t('Languages') }}</template>
    <template #subtitle>{{ t('Translate the interface into any language — powered by AI') }}</template>

    <div v-if="flash" class="mb-5 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">{{ flash }}</div>

    <div v-if="!aiConfigured" class="mb-5 rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-300">
      {{ t('Connect an AI provider in Admin → AI to enable one-click auto-translation. You can still add languages and edit strings by hand.') }}
    </div>

    <section class="rounded-2xl border border-line bg-surface p-5">
      <div class="mb-4 flex items-center justify-between">
        <div>
          <h3 class="text-sm font-bold text-ink">{{ t('Interface languages') }}</h3>
          <p class="text-xs text-ink-muted">{{ t('{count} translatable strings in the interface.', { count: catalogSize }) }}</p>
        </div>
      </div>

      <div class="overflow-hidden rounded-xl border border-line">
        <table class="w-full text-sm">
          <thead class="bg-appbg text-left text-xs text-ink-muted">
            <tr>
              <th class="px-4 py-2 font-semibold">{{ t('Language') }}</th>
              <th class="px-4 py-2 font-semibold">{{ t('Coverage') }}</th>
              <th class="px-4 py-2 font-semibold">{{ t('Default') }}</th>
              <th class="px-4 py-2 text-right font-semibold">{{ t('Actions') }}</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-line">
            <tr v-for="row in rows" :key="row.code">
              <td class="px-4 py-3">
                <div class="font-semibold text-ink">{{ row.label }}</div>
                <div class="text-xs text-ink-muted">{{ row.code }}<span v-if="row.rtl" class="ml-1">· RTL</span><span v-if="row.source" class="ml-1">· {{ t('source') }}</span></div>
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <div class="h-1.5 w-32 overflow-hidden rounded-full bg-surface-2">
                    <div class="h-full transition-all" :class="row.percent === 100 ? 'bg-emerald-500' : 'bg-primary'" :style="{ width: row.percent + '%' }"></div>
                  </div>
                  <span class="text-xs text-ink-2">{{ row.percent }}%</span>
                  <span v-if="row.missing > 0" class="text-xs text-ink-muted">· {{ t('{n} missing', { n: row.missing }) }}</span>
                </div>
              </td>
              <td class="px-4 py-3">
                <input type="radio" name="default" :checked="siteLocale === row.code" class="text-primary focus:ring-primary" @change="setDefault(row.code)" />
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center justify-end gap-2">
                  <template v-if="!row.source">
                    <button v-if="aiConfigured && row.missing > 0" :disabled="busy[row.code]"
                      class="rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-600 disabled:opacity-50"
                      @click="translate(row.code)">
                      {{ busy[row.code] ? t('Translating…') : t('Translate {n}', { n: row.missing }) }}
                    </button>
                    <button class="rounded-lg bg-surface-2 px-3 py-1.5 text-xs font-semibold text-ink hover:bg-appbg" @click="openEditor(row.code, row.label)">{{ t('Edit') }}</button>
                    <button class="rounded-lg px-2 py-1.5 text-xs font-semibold text-rose-400 hover:bg-rose-500/10" @click="removeLocale(row.code, row.label)">{{ t('Remove') }}</button>
                  </template>
                  <span v-else class="text-xs text-ink-muted">{{ t('Built-in') }}</span>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Add a language -->
    <section class="mt-6 rounded-2xl border border-line bg-surface p-5">
      <h3 class="mb-1 text-sm font-bold text-ink">{{ t('Add a language') }}</h3>
      <p class="mb-4 text-xs text-ink-muted">{{ t('Pick a language and Convoro will create it — translate it instantly with AI, or fill it in yourself.') }}</p>
      <div class="flex flex-wrap items-center gap-3">
        <select v-model="addCode" :class="field" class="min-w-48 px-3 py-2">
          <option value="">{{ t('Choose a language…') }}</option>
          <option v-for="a in addable" :key="a.code" :value="a.code">{{ a.label }} ({{ a.code }})</option>
        </select>
        <label class="flex items-center gap-2 text-sm text-ink-2">
          <input v-model="addAndTranslate" type="checkbox" :disabled="!aiConfigured" class="rounded border-line text-primary focus:ring-primary" />
          {{ t('Auto-translate now') }}
        </label>
        <button :disabled="!addCode" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-600 disabled:opacity-50" @click="addLanguage">{{ t('Add language') }}</button>
      </div>
    </section>

    <!-- Inline string editor -->
    <div v-if="editing" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="closeEditor">
      <div class="flex max-h-[85vh] w-full max-w-3xl flex-col rounded-2xl border border-line bg-surface shadow-2xl">
        <div class="flex items-center justify-between border-b border-line px-5 py-4">
          <h3 class="text-sm font-bold text-ink">{{ t('Edit {language}', { language: editLabel }) }}</h3>
          <button class="text-ink-muted hover:text-ink" @click="closeEditor">✕</button>
        </div>
        <div class="border-b border-line px-5 py-3">
          <input v-model="filter" :class="field" class="w-full px-3 py-2" :placeholder="t('Filter strings…')" />
        </div>
        <div class="flex-1 overflow-y-auto px-5 py-3">
          <p v-if="loadingStrings" class="py-8 text-center text-sm text-ink-muted">{{ t('Loading…') }}</p>
          <div v-for="row in filtered" :key="row.key" class="border-b border-line py-2 last:border-0">
            <div class="mb-1 text-xs text-ink-muted">{{ row.key }}</div>
            <input v-model="row.value" :class="field" class="w-full px-3 py-1.5" :placeholder="row.key" @blur="saveString(row)" @keyup.enter="saveString(row)" />
          </div>
          <p v-if="!loadingStrings && filtered.length === 0" class="py-8 text-center text-sm text-ink-muted">{{ t('No strings match.') }}</p>
        </div>
        <div class="border-t border-line px-5 py-3 text-right">
          <button class="rounded-lg bg-surface-2 px-4 py-2 text-sm font-semibold text-ink hover:bg-appbg" @click="closeEditor">{{ t('Done') }}</button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
