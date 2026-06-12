<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import Editor from '@/Components/Editor.vue';
import { uploadImage } from '@/lib/upload';
import UploadButton from '@/Components/UploadButton.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref, reactive, watch, computed } from 'vue';
import { t as tr } from '@/lib/i18n';
import { toast } from '@/lib/toast';

const props = defineProps<{
  categories: { id: number; name: string; icon: string | null; color: string }[];
  tags: { id: number; name: string; color: string }[];
}>();

const editor = ref<any>(null);
const uploadingCover = ref(false);

// Optional attached poll.
const poll = reactive({ enabled: false, question: '', options: ['', ''] as string[], multiple: false, closes_days: null as number | null });
function addOption() { if (poll.options.length < 10) poll.options.push(''); }
function removeOption(i: number) { if (poll.options.length > 2) poll.options.splice(i, 1); }
const pollField = 'w-full rounded-lg border-line bg-appbg text-sm text-ink placeholder:text-ink-muted focus:border-primary focus:ring-primary';

const form = useForm({
  title: '',
  category_id: props.categories[0]?.id ?? null,
  tags: [] as number[],
  cover: '' as string,
  body_html: '',
  body_json: '',
});

// "Asked before?" — surface existing discussions as the member types a title.
const related = ref<{ title: string; url: string; snippet: string }[]>([]);
let relTimer: number | null = null;
function csrf() {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
}
watch(() => form.title, (title) => {
  if (relTimer) clearTimeout(relTimer);
  const q = title.trim();
  if (q.length < 5) { related.value = []; return; }
  relTimer = window.setTimeout(async () => {
    try {
      const r = await fetch('/api/related', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
        body: JSON.stringify({ q }),
      });
      const d = await r.json();
      related.value = Array.isArray(d.related) ? d.related : [];
    } catch { /* ignore */ }
  }, 450);
});

function toggleTag(id: number) {
  const i = form.tags.indexOf(id);
  if (i === -1) form.tags.push(id); else form.tags.splice(i, 1);
}

// ---- AI compose helpers (title + tag suggestions) ----
const aiEnabled = computed(() => !!(usePage().props as any).ask?.enabled);
const draftText = () => (editor.value?.getHTML() ?? '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
const titleSuggestions = ref<string[]>([]);
const suggestingTitle = ref(false);
async function suggestTitle() {
  if (suggestingTitle.value) return;
  const text = draftText();
  if (text.length < 10) { toast(tr('Write a bit of your post first — I’ll suggest a title from it.'), 'info'); return; }
  suggestingTitle.value = true; titleSuggestions.value = [];
  try {
    const r = await fetch('/api/ai/suggest-title', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
      body: JSON.stringify({ text }),
    });
    titleSuggestions.value = (await r.json()).titles ?? [];
  } catch { /* ignore */ } finally { suggestingTitle.value = false; }
}
const suggestingTags = ref(false);
async function suggestTags() {
  if (suggestingTags.value) return;
  const text = draftText();
  if (text.length < 10) { toast(tr('Write a bit of your post first — I’ll suggest tags from it.'), 'info'); return; }
  suggestingTags.value = true;
  try {
    const r = await fetch('/api/ai/suggest-tags', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
      body: JSON.stringify({ text, title: form.title }),
    });
    const names: string[] = (await r.json()).tags ?? [];
    for (const name of names) {
      const tag = props.tags.find((t) => t.name.toLowerCase() === name.toLowerCase());
      if (tag && !form.tags.includes(tag.id)) form.tags.push(tag.id);
    }
  } catch { /* ignore */ } finally { suggestingTags.value = false; }
}

async function pickCover(file: File) {
  uploadingCover.value = true;
  try { const { url } = await uploadImage(file); form.cover = url; } catch { /* ignore */ } finally { uploadingCover.value = false; }
}

function submit() {
  if (!editor.value || editor.value.isEmpty()) { form.setError('body_html', tr('Write something first.')); return; }
  form.body_html = editor.value.getHTML();
  form.body_json = editor.value.getJSON();
  const opts = poll.options.map((o) => o.trim()).filter(Boolean);
  form.transform((data) => ({
    ...data,
    poll: poll.enabled && poll.question.trim() && opts.length >= 2
      ? { question: poll.question.trim(), options: opts, multiple: poll.multiple, closes_days: poll.closes_days || null }
      : null,
  }));
  form.post('/topics', { onFinish: () => form.transform((d) => d) });
}
</script>

<template>
  <Head :title="tr('Start a topic')" />
  <AppLayout>
    <div class="mx-auto max-w-[760px]">
      <h1 class="mb-5 text-2xl font-extrabold tracking-tight">{{ tr('Start a topic') }}</h1>

      <form class="space-y-5" @submit.prevent="submit">
        <div class="rounded-c border border-line bg-surface p-5">
          <div class="flex items-center justify-between">
            <label class="block text-sm font-semibold text-ink-2">{{ tr('Title') }}</label>
            <button v-if="aiEnabled" type="button" :disabled="suggestingTitle" @click="suggestTitle"
              class="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline disabled:opacity-50">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3"/></svg>
              {{ suggestingTitle ? tr('Thinking…') : tr('Suggest a title') }}
            </button>
          </div>
          <input v-model="form.title" type="text" maxlength="160" :placeholder="tr('What do you want to discuss?')"
            class="mt-1.5 w-full rounded-lg border-line bg-surface-2 text-ink placeholder:text-ink-muted focus:border-primary focus:ring-primary" />
          <p v-if="form.errors.title" class="mt-1 text-sm text-red-500">{{ form.errors.title }}</p>
          <div v-if="titleSuggestions.length" class="mt-2 flex flex-wrap gap-2">
            <button v-for="(s, i) in titleSuggestions" :key="i" type="button" @click="form.title = s; titleSuggestions = []"
              class="rounded-lg border border-primary/30 bg-primary/[0.06] px-2.5 py-1 text-left text-xs text-ink hover:bg-primary/10">{{ s }}</button>
          </div>

          <!-- Asked before? — related existing discussions -->
          <div v-if="related.length" class="mt-3 rounded-lg border border-primary/25 bg-primary/[0.04] p-3">
            <div class="mb-1.5 flex items-center gap-1.5 text-xs font-bold text-primary">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
              {{ tr('Already asked? These discussions might help:') }}
            </div>
            <ul class="space-y-1">
              <li v-for="r in related" :key="r.url">
                <a :href="r.url" target="_blank" class="text-sm font-semibold text-primary hover:underline">{{ r.title }}</a>
                <span class="block truncate text-xs text-ink-muted">{{ r.snippet }}</span>
              </li>
            </ul>
            <p class="mt-1.5 text-[11px] text-ink-muted">{{ tr('Still different? Carry on — your question is welcome.') }}</p>
          </div>

          <label class="mt-4 block text-sm font-semibold text-ink-2">{{ tr('Category') }}</label>
          <select v-model="form.category_id" class="mt-1.5 w-full rounded-lg border-line bg-surface-2 text-ink focus:border-primary focus:ring-primary">
            <option :value="null">{{ tr('— none —') }}</option>
            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>

          <div class="mt-4 flex items-center justify-between">
            <label class="block text-sm font-semibold text-ink-2">{{ tr('Tags') }}</label>
            <button v-if="aiEnabled && tags.length" type="button" :disabled="suggestingTags" @click="suggestTags"
              class="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline disabled:opacity-50">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3"/></svg>
              {{ suggestingTags ? tr('Thinking…') : tr('Suggest tags') }}
            </button>
          </div>
          <div class="mt-2 flex flex-wrap gap-2">
            <button v-for="t in tags" :key="t.id" type="button"
              class="rounded-full border px-3 py-1 text-xs font-semibold"
              :class="form.tags.includes(t.id) ? 'border-primary bg-primary/15 text-primary' : 'border-line bg-surface-2 text-ink-2 hover:bg-surface'"
              @click="toggleTag(t.id)">#{{ t.name }}</button>
            <span v-if="!tags.length" class="text-sm text-ink-muted">{{ tr('No tags yet.') }}</span>
          </div>

          <label class="mt-4 block text-sm font-semibold text-ink-2">{{ tr('Cover image') }} <span class="font-normal text-ink-muted">{{ tr('(optional, for grid view)') }}</span></label>
          <div class="mt-2 flex items-center gap-3">
            <img v-if="form.cover" :src="form.cover" alt="" class="h-14 w-24 rounded-lg object-cover" />
            <UploadButton :uploading="uploadingCover" :label="tr('Choose cover')" accept="image/*" @file="pickCover" />
          </div>

          <label class="mt-4 flex items-center gap-2 text-sm font-semibold text-ink-2">
            <input v-model="poll.enabled" type="checkbox" class="rounded border-line text-primary focus:ring-primary" />
            {{ tr('Add a poll') }}
          </label>
          <div v-if="poll.enabled" class="mt-2 space-y-2.5 rounded-xl border border-line bg-surface-2/40 p-4">
            <input v-model="poll.question" type="text" :placeholder="tr('Ask a question…')" :class="pollField" maxlength="200" />
            <div v-for="(o, i) in poll.options" :key="i" class="flex items-center gap-2">
              <input v-model="poll.options[i]" type="text" :placeholder="tr('Option {n}', { n: i + 1 })" :class="pollField" maxlength="120" />
              <button v-if="poll.options.length > 2" type="button" class="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-ink-muted hover:bg-surface hover:text-red-500" :aria-label="tr('Remove')" @click="removeOption(i)">✕</button>
            </div>
            <button v-if="poll.options.length < 10" type="button" class="text-sm font-semibold text-primary hover:text-primary-600" @click="addOption">+ {{ tr('Add option') }}</button>
            <div class="flex flex-wrap items-center gap-x-5 gap-y-2 border-t border-line pt-3">
              <label class="flex items-center gap-2 text-sm text-ink-2"><input v-model="poll.multiple" type="checkbox" class="rounded border-line text-primary focus:ring-primary" /> {{ tr('Allow multiple choices') }}</label>
              <label class="flex items-center gap-2 text-sm text-ink-2">{{ tr('Close after') }} <input v-model.number="poll.closes_days" type="number" min="1" max="365" class="w-16 rounded-lg border-line bg-appbg text-sm text-ink focus:border-primary focus:ring-primary" /> {{ tr('days') }}</label>
            </div>
          </div>
        </div>

        <div>
          <label class="mb-1.5 block text-sm font-semibold text-ink-2">{{ tr('Post') }}</label>
          <Editor ref="editor" :placeholder="tr('Write your post… (rich text, drag images in)')" />
          <p v-if="form.errors.body_html" class="mt-1 text-sm text-red-500">{{ form.errors.body_html }}</p>
        </div>

        <div class="flex items-center gap-3">
          <button type="submit" :disabled="form.processing" class="rounded-c bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-primary/30 hover:bg-primary-600 disabled:opacity-60">
            {{ form.processing ? tr('Posting…') : tr('Post topic') }}
          </button>
          <Link href="/" class="text-sm font-semibold text-ink-2 hover:text-ink">{{ tr('Cancel') }}</Link>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
