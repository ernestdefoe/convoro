<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import Editor from '@/Components/Editor.vue';
import Slot from '@/Components/ext/Slot.vue';
import { uploadImage } from '@/lib/upload';
import UploadButton from '@/Components/UploadButton.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref, reactive, watch, computed } from 'vue';
import { t as tr } from '@/lib/i18n';
import { toast } from '@/lib/toast';

const props = defineProps<{
  categories: { id: number; name: string; icon: string | null; color: string }[];
  tags: { id: number; name: string; color: string }[];
  draft?: any;
  autosave?: any;
}>();

const editor = ref<any>(null);
const uploadingCover = ref(false);
const draftId = ref<number | null>(props.draft?.id ?? null);
const draftBody = props.draft?.body_html ?? '';

// Optional attached poll (prefilled when resuming a draft that had one).
const dp = props.draft?.poll ?? null;
const poll = reactive({
  enabled: !!dp?.question,
  question: dp?.question ?? '',
  options: (dp?.options && dp.options.length >= 2 ? [...dp.options] : ['', '']) as string[],
  multiple: !!dp?.multiple,
  closes_days: (dp?.closes_days ?? null) as number | null,
});
function addOption() { if (poll.options.length < 10) poll.options.push(''); }
function removeOption(i: number) { if (poll.options.length > 2) poll.options.splice(i, 1); }
const pollField = 'w-full rounded-lg border-line bg-appbg text-sm text-ink placeholder:text-ink-muted focus:border-primary focus:ring-primary';

const form = useForm({
  title: props.draft?.title ?? '',
  category_id: props.draft?.category_id ?? (props.categories[0]?.id ?? null),
  tags: (props.draft?.tags ?? []) as number[],
  cover: (props.draft?.cover ?? '') as string,
  body_html: '',
  body_json: '',
});

// Scheduling.
const showSchedule = ref(false);
const scheduledAt = ref('');
const pollPayload = () => {
  const opts = poll.options.map((o) => o.trim()).filter(Boolean);
  return poll.enabled && poll.question.trim() && opts.length >= 2
    ? { question: poll.question.trim(), options: opts, multiple: poll.multiple, closes_days: poll.closes_days || null }
    : null;
};
const draftForm = useForm({ draft_id: null as number | null, title: '', category_id: null as number | null, tags: [] as number[], cover: '', body_html: '', body_json: '', poll: null as any, scheduled_at: null as string | null });
function saveDraft(schedule = false) {
  if (schedule && !scheduledAt.value) { showSchedule.value = true; return; }
  draftForm.draft_id = draftId.value;
  draftForm.title = form.title;
  draftForm.category_id = form.category_id;
  draftForm.tags = form.tags;
  draftForm.cover = form.cover;
  draftForm.body_html = editor.value?.getHTML() ?? '';
  draftForm.body_json = editor.value?.getJSON() ?? '';
  draftForm.poll = pollPayload();
  draftForm.scheduled_at = schedule ? scheduledAt.value : null;
  draftForm.post('/drafts');
}

// ---- Auto draft save -------------------------------------------------------
// Quietly persists the in-progress topic to a rolling per-user autosave draft
// so a crash, refresh or accidental navigation never loses work. Cleared
// server-side once the topic is published.
const saveState = ref<'idle' | 'saving' | 'saved'>('idle');
const savedAt = ref<string | null>(props.autosave?.savedAt ?? null);
const savedAgo = ref<string>(props.autosave?.savedAgo ?? '');
let autoTimer: number | null = null;
let lastSig = '';

function autoPayload() {
  return {
    title: form.title,
    category_id: form.category_id,
    tags: form.tags,
    cover: form.cover,
    body_html: editor.value?.getHTML() ?? '',
    body_json: editor.value?.getJSON() ?? '',
    poll: pollPayload(),
  };
}
function isBlank(p: any) {
  return !String(p.title || '').trim() && !String(p.body_html || '').replace(/<[^>]+>/g, '').trim();
}
function scheduleAutosave() {
  if (autoTimer) clearTimeout(autoTimer);
  autoTimer = window.setTimeout(doAutosave, 2500);
}
async function doAutosave() {
  const payload = autoPayload();
  const sig = JSON.stringify(payload);
  if (sig === lastSig || isBlank(payload)) return;
  lastSig = sig;
  saveState.value = 'saving';
  try {
    const r = await fetch('/drafts/autosave', {
      method: 'POST', credentials: 'include',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
      body: sig,
    });
    const d = await r.json();
    if (d.saved) { savedAt.value = d.at; savedAgo.value = tr('just now'); saveState.value = 'saved'; }
    else saveState.value = 'idle';
  } catch { saveState.value = 'idle'; }
}
watch(() => [form.title, form.category_id, form.tags.slice(), form.cover,
  poll.enabled, poll.question, poll.options.slice(), poll.multiple, poll.closes_days],
  scheduleAutosave, { deep: true });

// Restore banner (offered when returning to a fresh composer).
const showRestore = ref(!!props.autosave);
function restoreAutosave() {
  const a = props.autosave;
  form.title = a.title ?? '';
  if (a.category_id != null) form.category_id = a.category_id;
  form.tags = (a.tags ?? []).slice();
  form.cover = a.cover ?? '';
  editor.value?.setHTML(a.body_html ?? '');
  if (a.poll?.question) {
    poll.enabled = true;
    poll.question = a.poll.question ?? '';
    poll.options = a.poll.options?.length >= 2 ? [...a.poll.options] : ['', ''];
    poll.multiple = !!a.poll.multiple;
    poll.closes_days = a.poll.closes_days ?? null;
  }
  showRestore.value = false;
}
async function discardAutosave() {
  showRestore.value = false;
  try {
    await fetch('/drafts/autosave', { method: 'DELETE', credentials: 'include', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() } });
  } catch { /* ignore */ }
}

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
  if (autoTimer) clearTimeout(autoTimer); // don't autosave after we navigate away
  if (!editor.value || editor.value.isEmpty()) { form.setError('body_html', tr('Write something first.')); return; }
  form.body_html = editor.value.getHTML();
  form.body_json = editor.value.getJSON();
  const opts = poll.options.map((o) => o.trim()).filter(Boolean);
  form.transform((data) => ({
    ...data,
    draft_id: draftId.value,
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

      <!-- Restore an unsaved draft recovered from a previous session -->
      <div v-if="showRestore" class="mb-5 flex flex-wrap items-center gap-3 rounded-c border border-primary/30 bg-primary/[0.06] px-4 py-3">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="shrink-0 text-primary"><path d="M3 12a9 9 0 1 0 9-9 9 9 0 0 0-6.4 2.6L3 8"/><path d="M3 3v5h5"/></svg>
        <div class="min-w-0 flex-1 text-sm">
          <span class="font-semibold text-ink">{{ tr('You have an unsaved draft') }}</span>
          <span class="text-ink-2"> · {{ tr('autosaved {ago}', { ago: props.autosave?.savedAgo ?? tr('recently') }) }}</span>
        </div>
        <button type="button" class="rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-600" @click="restoreAutosave">{{ tr('Restore') }}</button>
        <button type="button" class="rounded-lg border border-line bg-surface px-3 py-1.5 text-xs font-semibold text-ink-2 hover:bg-surface-2" @click="discardAutosave">{{ tr('Discard') }}</button>
      </div>

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
          <input v-model="form.title" type="text" maxlength="160" :placeholder="tr('What do you want to discuss?')" data-composer-title
            class="mt-1.5 w-full rounded-lg border-line bg-surface-2 text-ink placeholder:text-ink-muted focus:border-primary focus:ring-primary" />
          <p v-if="form.errors.title" class="mt-1 text-sm text-red-500">{{ form.errors.title }}</p>
          <!-- Extensions can preview a title match here as you type (e.g. TMDB movie/TV). -->
          <Slot name="composer:title" :ctx="{ categoryId: form.category_id }" />
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
          <select v-model="form.category_id" data-composer-category class="mt-1.5 w-full rounded-lg border-line bg-surface-2 text-ink focus:border-primary focus:ring-primary">
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
          <Editor ref="editor" :content="draftBody" :placeholder="tr('Write your post… (rich text, drag images in)')" :category-id="form.category_id" @typing="scheduleAutosave" />
          <p v-if="form.errors.body_html" class="mt-1 text-sm text-red-500">{{ form.errors.body_html }}</p>
        </div>

        <!-- Schedule picker -->
        <div v-if="showSchedule" class="flex flex-wrap items-center gap-3 rounded-c border border-line bg-surface p-4">
          <label class="text-sm font-semibold text-ink-2">{{ tr('Publish at') }}</label>
          <input v-model="scheduledAt" type="datetime-local" class="rounded-lg border-line bg-surface-2 text-sm text-ink focus:border-primary focus:ring-primary" />
          <button type="button" :disabled="!scheduledAt || draftForm.processing" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-600 disabled:opacity-50" @click="saveDraft(true)">{{ tr('Schedule post') }}</button>
          <button type="button" class="text-sm text-ink-2 hover:text-ink" @click="showSchedule = false">{{ tr('Cancel') }}</button>
        </div>

        <div class="flex flex-wrap items-center gap-3">
          <button type="submit" :disabled="form.processing" class="rounded-c bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-primary/30 hover:bg-primary-600 disabled:opacity-60">
            {{ form.processing ? tr('Posting…') : tr('Post topic') }}
          </button>
          <button type="button" :disabled="draftForm.processing" class="rounded-c border border-line bg-surface px-4 py-2.5 text-sm font-semibold text-ink-2 hover:bg-surface-2 disabled:opacity-60" @click="saveDraft(false)">
            {{ draftForm.processing ? tr('Saving…') : (draftId ? tr('Update draft') : tr('Save draft')) }}
          </button>
          <button type="button" class="rounded-c border border-line bg-surface px-4 py-2.5 text-sm font-semibold text-ink-2 hover:bg-surface-2" @click="showSchedule = !showSchedule">
            🕑 {{ tr('Schedule') }}
          </button>
          <span class="ml-auto inline-flex items-center gap-1.5 text-xs text-ink-muted" aria-live="polite">
            <template v-if="saveState === 'saving'">
              <svg class="animate-spin" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 12a9 9 0 1 1-6.2-8.5" stroke-linecap="round"/></svg>
              {{ tr('Saving…') }}
            </template>
            <template v-else-if="saveState === 'saved'">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="text-emerald-500"><path d="M20 6 9 17l-5-5" stroke-linecap="round" stroke-linejoin="round"/></svg>
              {{ tr('Draft saved') }}
            </template>
          </span>
          <Link href="/drafts" class="text-sm font-semibold text-ink-2 hover:text-ink">{{ tr('My drafts') }}</Link>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
