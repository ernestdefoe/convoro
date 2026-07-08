<script setup lang="ts">
import Editor from '@/Components/Editor.vue';
import Slot from '@/Components/ext/Slot.vue';
import TagChip from '@/Components/forum/TagChip.vue';
import TagPickerModal from '@/Components/forum/TagPickerModal.vue';
import UploadButton from '@/Components/UploadButton.vue';
import { uploadImage } from '@/lib/upload';
import { useComposer } from '@/lib/composer';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, nextTick, reactive, ref, watch } from 'vue';
import { t as tr } from '@/lib/i18n';
import { toast } from '@/lib/toast';

const { state, close } = useComposer();

/* ── data loaded lazily when the sheet opens (mirrors the old Create.vue props) ── */
const loading = ref(false);
const loaded = ref(false);
const tags = ref<{ id: number; name: string; icon: string | null; color: string; children: { id: number; name: string; icon: string | null; color: string }[] }[]>([]);
const tagRules = ref<{ minPrimary: number; maxPrimary: number }>({ minPrimary: 0, maxPrimary: 0 });
const bodyOptionalCategories = ref<number[]>([]);
const autosaveData = ref<any>(null);

const editor = ref<any>(null);
const uploadingCover = ref(false);
const draftId = ref<number | null>(null);
const tagPickerOpen = ref(false);
const tagPickerOpenedOnce = ref(false);

/* ── optional attached poll ── */
const poll = reactive({ enabled: false, question: '', options: ['', ''] as string[], multiple: false, closes_days: null as number | null });
function addOption() { if (poll.options.length < 10) poll.options.push(''); }
function removeOption(i: number) { if (poll.options.length > 2) poll.options.splice(i, 1); }
const pollField = 'w-full rounded-lg border-line bg-appbg text-sm text-ink placeholder:text-ink-muted focus:border-primary focus:ring-primary';

const form = useForm({ title: '', category_id: null as number | null, tags: [] as number[], cover: '', body_html: '', body_json: '' });

/* ── derived ── */
const flatTags = computed(() => tags.value.flatMap((t) => [t, ...(t.children || [])]));
const primaryIds = computed(() => new Set(tags.value.map((t) => t.id)));
const primarySelected = computed(() => form.tags.filter((id: number) => primaryIds.value.has(id)).length);
const bodyOptional = computed(() => bodyOptionalCategories.value.includes(form.category_id as number));
const selectedTags = computed(() => form.tags.map((id: number) => flatTags.value.find((t) => t.id === id)).filter(Boolean) as any[]);
const tagRuleError = computed(() => {
  const min = tagRules.value.minPrimary || 0, max = tagRules.value.maxPrimary || 0, n = primarySelected.value;
  if (min && n < min) return tr('Choose at least {n} primary tag(s).', { n: min });
  if (max && n > max) return tr('Choose at most {n} primary tag(s).', { n: max });
  return '';
});
const ruleHint = computed(() => {
  const min = tagRules.value.minPrimary || 0, max = tagRules.value.maxPrimary || 0;
  if (min && max) return min === max ? tr('Pick exactly {n} primary tag(s).', { n: min }) : tr('Pick {min}–{max} primary tags.', { min, max });
  if (min) return tr('Pick at least {n} primary tag(s).', { n: min });
  if (max) return tr('Pick up to {n} primary tag(s).', { n: max });
  return tr('Pick the space(s) this belongs in.');
});
const aiEnabled = computed(() => !!(usePage().props as any).ask?.enabled);

/* ── open → fetch composer data, reset the form ── */
watch(() => state.value.open, async (open) => {
  if (!open) return;
  draftId.value = state.value.draftId;
  await loadData();
});

function resetForm() {
  form.title = ''; form.category_id = null; form.tags = []; form.cover = ''; form.body_html = ''; form.body_json = '';
  form.clearErrors();
  poll.enabled = false; poll.question = ''; poll.options = ['', '']; poll.multiple = false; poll.closes_days = null;
  lastSig = ''; saveState.value = 'idle'; related.value = []; titleSuggestions.value = [];
}

async function loadData() {
  loading.value = true; loaded.value = false;
  resetForm();
  try {
    const r = await fetch('/new/data' + (draftId.value ? '?draft=' + draftId.value : ''), { credentials: 'include', headers: { Accept: 'application/json' } });
    const d = await r.json();
    tags.value = d.tags || [];
    tagRules.value = d.tagRules || { minPrimary: 0, maxPrimary: 0 };
    bodyOptionalCategories.value = d.bodyOptionalCategories || [];
    autosaveData.value = d.autosave || null;

    const draft = d.draft;
    if (draft) {
      draftId.value = draft.id;
      form.title = draft.title ?? '';
      form.category_id = draft.category_id ?? null;
      form.tags = (draft.tags ?? []).slice();
      form.cover = draft.cover ?? '';
      if (draft.poll?.question) {
        poll.enabled = true; poll.question = draft.poll.question ?? '';
        poll.options = draft.poll.options?.length >= 2 ? [...draft.poll.options] : ['', ''];
        poll.multiple = !!draft.poll.multiple; poll.closes_days = draft.poll.closes_days ?? null;
      }
    }
    showRestore.value = !!(autosaveData.value && !draft);

    loaded.value = true;
    await nextTick();
    editor.value?.setHTML(draft?.body_html ?? '');
    nextTick(() => document.querySelector<HTMLInputElement>('[data-composer-title]')?.focus());
  } catch {
    toast(tr('Could not open the composer. Please try again.'), 'error');
    close();
  } finally {
    loading.value = false;
  }
}

/* ── tags ── */
function toggleTag(id: number) {
  const i = form.tags.indexOf(id);
  if (i === -1) form.tags.push(id); else form.tags.splice(i, 1);
}

/* ── cover ── */
async function pickCover(file: File) {
  uploadingCover.value = true;
  try { const { url } = await uploadImage(file); form.cover = url; } catch { /* ignore */ } finally { uploadingCover.value = false; }
}

/* ── csrf ── */
function csrf() {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
}

/* ── autosave (rolling per-user draft) ── */
const saveState = ref<'idle' | 'saving' | 'saved'>('idle');
let autoTimer: number | null = null;
let lastSig = '';
function pollPayload() {
  const opts = poll.options.map((o) => o.trim()).filter(Boolean);
  return poll.enabled && poll.question.trim() && opts.length >= 2
    ? { question: poll.question.trim(), options: opts, multiple: poll.multiple, closes_days: poll.closes_days || null }
    : null;
}
function autoPayload() {
  return { title: form.title, category_id: form.category_id, tags: form.tags, cover: form.cover, body_html: editor.value?.getHTML() ?? '', body_json: editor.value?.getJSON() ?? '', poll: pollPayload() };
}
function isBlank(p: any) { return !String(p.title || '').trim() && !String(p.body_html || '').replace(/<[^>]+>/g, '').trim(); }
function scheduleAutosave() {
  if (!state.value.open) return;
  if (autoTimer) clearTimeout(autoTimer);
  autoTimer = window.setTimeout(doAutosave, 2500);
}
async function doAutosave() {
  const payload = autoPayload();
  const sig = JSON.stringify(payload);
  if (sig === lastSig || isBlank(payload)) return;
  lastSig = sig; saveState.value = 'saving';
  try {
    const r = await fetch('/drafts/autosave', { method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() }, body: sig });
    const d = await r.json();
    saveState.value = d.saved ? 'saved' : 'idle';
  } catch { saveState.value = 'idle'; }
}
watch(() => [form.title, form.category_id, form.tags.slice(), form.cover, poll.enabled, poll.question, poll.options.slice(), poll.multiple, poll.closes_days], scheduleAutosave, { deep: true });

/* ── restore banner ── */
const showRestore = ref(false);
function restoreAutosave() {
  const a = autosaveData.value; if (!a) return;
  form.title = a.title ?? ''; if (a.category_id != null) form.category_id = a.category_id;
  form.tags = (a.tags ?? []).slice(); form.cover = a.cover ?? '';
  editor.value?.setHTML(a.body_html ?? '');
  if (a.poll?.question) { poll.enabled = true; poll.question = a.poll.question ?? ''; poll.options = a.poll.options?.length >= 2 ? [...a.poll.options] : ['', '']; poll.multiple = !!a.poll.multiple; poll.closes_days = a.poll.closes_days ?? null; }
  showRestore.value = false;
}
async function discardAutosave() {
  showRestore.value = false;
  try { await fetch('/drafts/autosave', { method: 'DELETE', credentials: 'include', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() } }); } catch { /* ignore */ }
}

/* ── "asked before?" related discussions ── */
const related = ref<{ title: string; url: string; snippet: string }[]>([]);
let relTimer: number | null = null;
watch(() => form.title, (title) => {
  if (relTimer) clearTimeout(relTimer);
  const q = title.trim();
  if (q.length < 5) { related.value = []; return; }
  relTimer = window.setTimeout(async () => {
    try {
      const r = await fetch('/api/related', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() }, body: JSON.stringify({ q }) });
      const d = await r.json();
      related.value = Array.isArray(d.related) ? d.related : [];
    } catch { /* ignore */ }
  }, 450);
});

/* ── AI compose helpers ── */
const draftText = () => (editor.value?.getHTML() ?? '').replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
const titleSuggestions = ref<string[]>([]);
const suggestingTitle = ref(false);
async function suggestTitle() {
  if (suggestingTitle.value) return;
  const text = draftText();
  if (text.length < 10) { toast(tr('Write a bit of your post first — I’ll suggest a title from it.'), 'info'); return; }
  suggestingTitle.value = true; titleSuggestions.value = [];
  try {
    const r = await fetch('/api/ai/suggest-title', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() }, body: JSON.stringify({ text }) });
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
    const r = await fetch('/api/ai/suggest-tags', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() }, body: JSON.stringify({ text, title: form.title }) });
    const names: string[] = (await r.json()).tags ?? [];
    for (const name of names) {
      const tag = flatTags.value.find((t) => t.name.toLowerCase() === name.toLowerCase());
      if (tag && !form.tags.includes(tag.id)) form.tags.push(tag.id);
    }
  } catch { /* ignore */ } finally { suggestingTags.value = false; }
}

/* ── drafts + scheduling ── */
const showSchedule = ref(false);
const scheduledAt = ref('');
const draftForm = useForm({ draft_id: null as number | null, title: '', category_id: null as number | null, tags: [] as number[], cover: '', body_html: '', body_json: '', poll: null as any, scheduled_at: null as string | null });
function saveDraft(schedule = false) {
  if (schedule && !scheduledAt.value) { showSchedule.value = true; return; }
  draftForm.draft_id = draftId.value;
  draftForm.title = form.title; draftForm.category_id = form.category_id; draftForm.tags = form.tags; draftForm.cover = form.cover;
  draftForm.body_html = editor.value?.getHTML() ?? ''; draftForm.body_json = editor.value?.getJSON() ?? '';
  draftForm.poll = pollPayload(); draftForm.scheduled_at = schedule ? scheduledAt.value : null;
  draftForm.post('/drafts', { onSuccess: () => close() });
}

/* ── submit ── */
function submit() {
  if (autoTimer) clearTimeout(autoTimer);
  if (!bodyOptional.value && (!editor.value || editor.value.isEmpty())) { form.setError('body_html', tr('Write something first.')); return; }
  if (tagRuleError.value) { form.setError('tags', tagRuleError.value); return; }
  form.body_html = editor.value?.getHTML() ?? '';
  form.body_json = editor.value?.getJSON() ?? '';
  const opts = poll.options.map((o) => o.trim()).filter(Boolean);
  form.transform((data) => ({
    ...data,
    draft_id: draftId.value,
    poll: poll.enabled && poll.question.trim() && opts.length >= 2 ? { question: poll.question.trim(), options: opts, multiple: poll.multiple, closes_days: poll.closes_days || null } : null,
  }));
  form.post('/topics', { onSuccess: () => close(), onFinish: () => form.transform((d) => d) });
}
</script>

<template>
  <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="opacity-0" leave-active-class="transition duration-150 ease-in" leave-to-class="opacity-0">
    <div v-if="state.open" class="fixed inset-0 z-[80] flex items-end justify-center sm:p-4" @keydown.esc="close">
      <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="close"></div>

      <Transition enter-active-class="transition duration-300 ease-out" enter-from-class="translate-y-full sm:translate-y-8 sm:opacity-0" leave-active-class="transition duration-200 ease-in" leave-to-class="translate-y-full sm:translate-y-8 sm:opacity-0" appear>
        <div v-if="state.open" class="relative flex max-h-[94vh] w-full max-w-[760px] flex-col overflow-hidden rounded-t-2xl border border-line bg-surface shadow-2xl sm:max-h-[90vh] sm:rounded-c">
          <!-- header -->
          <div class="flex items-center justify-between border-b border-line px-5 py-3.5">
            <h2 class="text-lg font-extrabold tracking-tight text-ink">{{ tr('Start a topic') }}</h2>
            <div class="flex items-center gap-3">
              <Link href="/drafts" class="text-sm font-semibold text-ink-2 hover:text-ink" @click="close">{{ tr('My drafts') }}</Link>
              <button type="button" class="text-ink-muted hover:text-ink" :aria-label="tr('Close')" @click="close">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12" /></svg>
              </button>
            </div>
          </div>

          <!-- loading -->
          <div v-if="loading && !loaded" class="grid place-items-center py-16 text-ink-muted">
            <svg class="animate-spin" width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 12a9 9 0 1 1-6.2-8.5" stroke-linecap="round" /></svg>
          </div>

          <!-- body -->
          <form v-show="loaded" class="flex min-h-0 flex-1 flex-col" @submit.prevent="submit">
            <div class="min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-4">
              <!-- restore banner -->
              <div v-if="showRestore" class="flex flex-wrap items-center gap-3 rounded-c border border-primary/30 bg-primary/[0.06] px-4 py-3">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" class="shrink-0 text-primary"><path d="M3 12a9 9 0 1 0 9-9 9 9 0 0 0-6.4 2.6L3 8" /><path d="M3 3v5h5" /></svg>
                <div class="min-w-0 flex-1 text-sm"><span class="font-semibold text-ink">{{ tr('You have an unsaved draft') }}</span><span class="text-ink-2"> · {{ tr('autosaved {ago}', { ago: autosaveData?.savedAgo ?? tr('recently') }) }}</span></div>
                <button type="button" class="rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-600" @click="restoreAutosave">{{ tr('Restore') }}</button>
                <button type="button" class="rounded-lg border border-line bg-surface px-3 py-1.5 text-xs font-semibold text-ink-2 hover:bg-surface-2" @click="discardAutosave">{{ tr('Discard') }}</button>
              </div>

              <!-- title -->
              <div>
                <div class="flex items-center justify-between">
                  <label class="block text-sm font-semibold text-ink-2">{{ tr('Title') }}</label>
                  <button v-if="aiEnabled" type="button" :disabled="suggestingTitle" @click="suggestTitle" class="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline disabled:opacity-50">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3" /><path d="M12 3v3M12 18v3M3 12h3M18 12h3" /></svg>
                    {{ suggestingTitle ? tr('Thinking…') : tr('Suggest a title') }}
                  </button>
                </div>
                <input v-model="form.title" type="text" maxlength="160" :placeholder="tr('What do you want to discuss?')" data-composer-title class="mt-1.5 w-full rounded-lg border-line bg-surface-2 text-ink placeholder:text-ink-muted focus:border-primary focus:ring-primary" />
                <p v-if="form.errors.title" class="mt-1 text-sm text-red-500">{{ form.errors.title }}</p>
                <Slot name="composer:title" :ctx="{ categoryId: form.category_id }" />
                <div v-if="titleSuggestions.length" class="mt-2 flex flex-wrap gap-2">
                  <button v-for="(s, i) in titleSuggestions" :key="i" type="button" @click="form.title = s; titleSuggestions = []" class="rounded-lg border border-primary/30 bg-primary/[0.06] px-2.5 py-1 text-left text-xs text-ink hover:bg-primary/10">{{ s }}</button>
                </div>
                <div v-if="related.length" class="mt-3 rounded-lg border border-primary/25 bg-primary/[0.04] p-3">
                  <div class="mb-1.5 flex items-center gap-1.5 text-xs font-bold text-primary">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7" /><path d="m21 21-4.3-4.3" /></svg>
                    {{ tr('Already asked? These discussions might help:') }}
                  </div>
                  <ul class="space-y-1">
                    <li v-for="r in related" :key="r.url"><a :href="r.url" target="_blank" class="text-sm font-semibold text-primary hover:underline">{{ r.title }}</a><span class="block truncate text-xs text-ink-muted">{{ r.snippet }}</span></li>
                  </ul>
                </div>
              </div>

              <!-- tags: chips + picker trigger -->
              <div>
                <div class="flex items-center justify-between">
                  <label class="block text-sm font-semibold text-ink-2">{{ tr('Tags') }}</label>
                  <button v-if="aiEnabled && tags.length" type="button" :disabled="suggestingTags" @click="suggestTags" class="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline disabled:opacity-50">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3" /><path d="M12 3v3M12 18v3M3 12h3M18 12h3" /></svg>
                    {{ suggestingTags ? tr('Thinking…') : tr('Suggest tags') }}
                  </button>
                </div>
                <div class="mt-2 flex flex-wrap items-center gap-1.5">
                  <TagChip v-for="t in selectedTags" :key="t.id" :tag="t" removable @remove="toggleTag(t.id)" />
                  <button v-if="tags.length" type="button" class="inline-flex items-center gap-1.5 rounded-full border border-dashed border-line bg-surface-2 px-3 py-1 text-xs font-semibold text-ink-2 hover:border-primary hover:text-primary" @click="tagPickerOpen = true">
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><path d="M12 5v14M5 12h14" /></svg>
                    {{ selectedTags.length ? tr('Edit tags') : tr('Add tags') }}
                  </button>
                  <span v-else class="text-sm text-ink-muted">{{ tr('No spaces yet.') }}</span>
                </div>
                <p class="mt-1 text-xs" :class="tagRuleError ? 'text-red-400' : 'text-ink-muted'">{{ tagRuleError || ruleHint }}</p>
                <p v-if="form.errors.tags" class="mt-0.5 text-sm text-red-500">{{ form.errors.tags }}</p>
              </div>

              <!-- cover -->
              <div>
                <label class="block text-sm font-semibold text-ink-2">{{ tr('Cover image') }} <span class="font-normal text-ink-muted">{{ tr('(optional, for grid view)') }}</span></label>
                <div class="mt-2 flex items-center gap-3">
                  <div v-if="form.cover" class="relative">
                    <img :src="form.cover" alt="" class="h-14 w-24 rounded-lg object-cover" />
                    <button type="button" class="absolute -right-2 -top-2 grid h-5 w-5 place-items-center rounded-full bg-surface text-ink-muted shadow ring-1 ring-line hover:text-red-500" :aria-label="tr('Remove')" @click="form.cover = ''">
                      <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12" /></svg>
                    </button>
                  </div>
                  <UploadButton :uploading="uploadingCover" :label="form.cover ? tr('Replace cover') : tr('Choose cover')" accept="image/*" @file="pickCover" />
                </div>
              </div>

              <!-- poll -->
              <div>
                <label class="flex items-center gap-2 text-sm font-semibold text-ink-2">
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

              <!-- editor -->
              <div>
                <label class="mb-1.5 block text-sm font-semibold text-ink-2">{{ tr('Post') }}<span v-if="bodyOptional" class="ml-1 font-normal text-ink-muted">· {{ tr('optional') }}</span></label>
                <Editor ref="editor" :placeholder="bodyOptional ? tr('Add a comment (optional)…') : tr('Write your post… (rich text, drag images in)')" :category-id="form.category_id" @typing="scheduleAutosave" />
                <p v-if="form.errors.body_html" class="mt-1 text-sm text-red-500">{{ form.errors.body_html }}</p>
              </div>

              <!-- schedule -->
              <div v-if="showSchedule" class="flex flex-wrap items-center gap-3 rounded-c border border-line bg-surface-2/50 p-4">
                <label class="text-sm font-semibold text-ink-2">{{ tr('Publish at') }}</label>
                <input v-model="scheduledAt" type="datetime-local" class="rounded-lg border-line bg-surface-2 text-sm text-ink focus:border-primary focus:ring-primary" />
                <button type="button" :disabled="!scheduledAt || draftForm.processing" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-600 disabled:opacity-50" @click="saveDraft(true)">{{ tr('Schedule post') }}</button>
                <button type="button" class="text-sm text-ink-2 hover:text-ink" @click="showSchedule = false">{{ tr('Cancel') }}</button>
              </div>
            </div>

            <!-- footer -->
            <div class="flex flex-wrap items-center gap-3 border-t border-line px-5 py-3">
              <button type="submit" :disabled="form.processing" class="rounded-c bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-primary/30 hover:bg-primary-600 disabled:opacity-60">
                {{ form.processing ? tr('Posting…') : tr('Post topic') }}
              </button>
              <button type="button" :disabled="draftForm.processing" class="rounded-c border border-line bg-surface px-4 py-2.5 text-sm font-semibold text-ink-2 hover:bg-surface-2 disabled:opacity-60" @click="saveDraft(false)">
                {{ draftForm.processing ? tr('Saving…') : (draftId ? tr('Update draft') : tr('Save draft')) }}
              </button>
              <button type="button" class="rounded-c border border-line bg-surface px-4 py-2.5 text-sm font-semibold text-ink-2 hover:bg-surface-2" @click="showSchedule = !showSchedule">🕑 {{ tr('Schedule') }}</button>
              <span class="ml-auto inline-flex items-center gap-1.5 text-xs text-ink-muted" aria-live="polite">
                <template v-if="saveState === 'saving'"><svg class="animate-spin" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 12a9 9 0 1 1-6.2-8.5" stroke-linecap="round" /></svg>{{ tr('Saving…') }}</template>
                <template v-else-if="saveState === 'saved'"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="text-emerald-500"><path d="M20 6 9 17l-5-5" stroke-linecap="round" stroke-linejoin="round" /></svg>{{ tr('Draft saved') }}</template>
              </span>
            </div>
          </form>
        </div>
      </Transition>

      <TagPickerModal :open="tagPickerOpen" :tags="tags" v-model="form.tags" :tag-rules="tagRules" @close="tagPickerOpen = false" />
    </div>
  </Transition>
</template>
