<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';

type Article = { id: number; title: string; url: string | null; body: string; indexed: boolean; chunks: number; updated: string };

const props = defineProps<{ articles: Article[]; embedReady: boolean; strict: boolean }>();

const page = usePage();
const flash = computed(() => (page.props as any).flash?.status as string | undefined);

const form = reactive<{ id: number | null; title: string; body: string; url: string }>({ id: null, title: '', body: '', url: '' });
const editing = computed(() => form.id !== null);

function reset() {
  form.id = null; form.title = ''; form.body = ''; form.url = '';
}
function edit(a: Article) {
  form.id = a.id; form.title = a.title; form.body = a.body; form.url = a.url || '';
  window.scrollTo({ top: 0, behavior: 'smooth' });
}
function save() {
  if (!form.title.trim() || !form.body.trim()) return;
  const payload = { title: form.title, body: form.body, url: form.url || null };
  const opts = { preserveScroll: true, onSuccess: () => reset() };
  if (form.id !== null) router.put(`/admin/knowledge/${form.id}`, payload, opts);
  else router.post('/admin/knowledge', payload, opts);
}
function remove(a: Article) {
  if (!window.confirm(t('Delete “{title}”?', { title: a.title }))) return;
  router.delete(`/admin/knowledge/${a.id}`, { preserveScroll: true, onSuccess: () => { if (form.id === a.id) reset(); } });
}

const field = 'mt-1 w-full rounded-lg border-line bg-appbg text-ink placeholder:text-ink-muted focus:border-primary focus:ring-primary text-sm';
</script>

<template>
  <Head :title="t('Admin · Knowledge Base')" />
  <AdminLayout>
    <template #title>{{ t('Knowledge Base') }}</template>
    <template #subtitle>{{ t('Support articles the AI grounds answers in, alongside your docs') }}</template>

    <div v-if="flash" class="mb-5 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">{{ flash }}</div>
    <div v-if="!embedReady" class="mb-5 rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-300">
      {{ t('Add a Voyage embeddings key in Admin → AI so the assistant can search these articles.') }}
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
      <!-- Editor -->
      <section class="rounded-2xl border border-line bg-surface p-5">
        <h3 class="text-sm font-bold text-ink">{{ editing ? t('Edit article') : t('Add an article') }}</h3>
        <p class="mt-1 text-xs text-ink-muted">{{ t('Write the answer to a common question or a fix that isn’t in the official docs. The assistant will retrieve and cite it.') }}</p>

        <label class="mt-4 block text-xs font-semibold text-ink-2">{{ t('Title') }}</label>
        <input v-model="form.title" :class="field" :placeholder="t('e.g. Federation won’t connect to Mastodon')" />

        <label class="mt-3 block text-xs font-semibold text-ink-2">{{ t('Answer / body') }}</label>
        <textarea v-model="form.body" rows="9" :class="field" :placeholder="t('Explain the answer or the fix, step by step.')" />
        <p class="mt-1 text-[11px] text-ink-muted">{{ t('Write as much as you need — long articles are automatically split into passages so the assistant retrieves the relevant part.') }}</p>

        <label class="mt-3 block text-xs font-semibold text-ink-2">{{ t('Link (optional)') }}</label>
        <input v-model="form.url" :class="field" placeholder="/docs/federation" />
        <p class="mt-1 text-[11px] text-ink-muted">{{ t('Where the citation links — a doc, a thread, or an external page. Leave blank for none.') }}</p>

        <div class="mt-4 flex items-center gap-2">
          <button type="button" :disabled="!form.title.trim() || !form.body.trim()" class="rounded-lg bg-primary px-4 py-2 text-sm font-bold text-white disabled:opacity-50" @click="save">
            {{ editing ? t('Save changes') : t('Add article') }}
          </button>
          <button v-if="editing" type="button" class="rounded-lg px-3 py-2 text-sm font-semibold text-ink-2 hover:text-ink" @click="reset">{{ t('Cancel') }}</button>
        </div>
      </section>

      <!-- List -->
      <section class="rounded-2xl border border-line bg-surface p-5">
        <h3 class="text-sm font-bold text-ink">{{ t('Articles') }} <span class="font-normal text-ink-muted">({{ props.articles.length }})</span></h3>
        <ul class="mt-3 divide-y divide-line">
          <li v-for="a in props.articles" :key="a.id" class="flex items-start gap-3 py-3">
            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-2">
                <span class="truncate text-sm font-semibold text-ink">{{ a.title }}</span>
                <span v-if="!a.indexed" class="rounded-full bg-amber-500/15 px-1.5 py-0.5 text-[10px] font-bold uppercase text-amber-400" :title="t('Not embedded yet')">{{ t('not indexed') }}</span>
                <span v-else-if="a.chunks > 1" class="rounded-full bg-surface-2 px-1.5 py-0.5 text-[10px] font-bold text-ink-muted" :title="t('Split into passages for retrieval')">{{ t('{n} chunks', { n: a.chunks }) }}</span>
              </div>
              <div class="truncate text-xs text-ink-muted">{{ a.body }}</div>
              <div class="mt-0.5 text-[11px] text-ink-muted">{{ t('Updated {when}', { when: a.updated }) }}</div>
            </div>
            <button type="button" class="rounded-lg border border-line px-2.5 py-1 text-xs font-bold text-ink-2 hover:border-primary/50" @click="edit(a)">{{ t('Edit') }}</button>
            <button type="button" class="rounded-lg border border-rose-500/30 px-2.5 py-1 text-xs font-bold text-rose-400 hover:bg-rose-500/10" @click="remove(a)">{{ t('Delete') }}</button>
          </li>
          <li v-if="!props.articles.length" class="py-10 text-center text-sm text-ink-muted">{{ t('No articles yet. Add your first support answer on the left.') }}</li>
        </ul>
      </section>
    </div>
  </AdminLayout>
</template>
