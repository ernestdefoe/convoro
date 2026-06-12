<script setup lang="ts">
import { onBeforeUnmount, computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import Link from '@tiptap/extension-link';
import Image from '@tiptap/extension-image';
import Placeholder from '@tiptap/extension-placeholder';
import { uploadImage, isImageFile } from '@/lib/upload';
import { MentionText } from '@/lib/mentionSuggestion';
import { t } from '@/lib/i18n';

const props = withDefaults(defineProps<{ placeholder?: string; content?: string }>(), { placeholder: 'Write a reply…', content: '' });
const emit = defineEmits<{ typing: [] }>();

const uploading = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);

async function doUpload(file: File) {
  if (!isImageFile(file)) return;
  uploading.value = true;
  try {
    const { url } = await uploadImage(file);
    editor.value?.chain().focus().setImage({ src: url }).run();
  } catch (e) {
    window.alert(t('Image upload failed.'));
  } finally {
    uploading.value = false;
  }
}

const editor = useEditor({
  extensions: [
    StarterKit,
    Underline,
    Link.configure({ openOnClick: false, autolink: true, HTMLAttributes: { rel: 'noopener noreferrer nofollow', target: '_blank' } }),
    Image,
    Placeholder.configure({ placeholder: t(props.placeholder) }),
    MentionText,
  ],
  content: props.content,
  onUpdate: () => emit('typing'),
  editorProps: {
    attributes: { class: 'prose-q focus:outline-none' },
    handlePaste: (_view, event) => {
      const files = Array.from(event.clipboardData?.files ?? []).filter(isImageFile);
      if (!files.length) return false;
      files.forEach(doUpload);
      return true;
    },
    handleDrop: (_view, event) => {
      const files = Array.from((event as DragEvent).dataTransfer?.files ?? []).filter(isImageFile);
      if (!files.length) return false;
      event.preventDefault();
      files.forEach(doUpload);
      return true;
    },
  },
});

onBeforeUnmount(() => editor.value?.destroy());

defineExpose({
  getHTML: () => editor.value?.getHTML() ?? '',
  getJSON: () => JSON.stringify(editor.value?.getJSON() ?? {}),
  isEmpty: () => editor.value?.isEmpty ?? true,
  clear: () => editor.value?.commands.clearContent(true),
  focus: () => editor.value?.commands.focus('end'),
  insertContent: (html: string) => editor.value?.chain().focus().insertContent(html).run(),
});

// ---- AI writing assistant ----
const page = usePage();
const aiEnabled = computed(() => !!(page.props as any).ask?.enabled && !!(page.props as any).auth?.user);
const aiMenuOpen = ref(false);
const aiBusy = ref(false);
const AI_ACTIONS: { action: string; label: string }[] = [
  { action: 'improve', label: t('Improve writing') },
  { action: 'grammar', label: t('Fix spelling & grammar') },
  { action: 'shorten', label: t('Make shorter') },
  { action: 'expand', label: t('Make longer') },
  { action: 'simplify', label: t('Simplify') },
  { action: 'professional', label: t('Professional tone') },
  { action: 'friendly', label: t('Friendly tone') },
];

const escapeHtml = (s: string) => s.replace(/[&<>]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c] as string));
const toParagraphs = (text: string) => text.split(/\n{2,}/).map((p) => `<p>${escapeHtml(p.trim()).replace(/\n/g, '<br>')}</p>`).join('');

async function runAi(action: string) {
  if (!editor.value || aiBusy.value) return;
  aiMenuOpen.value = false;
  const sel = editor.value.state.selection;
  const hasSelection = sel.from !== sel.to;
  const from = hasSelection ? sel.from : 0;
  const to = hasSelection ? sel.to : editor.value.state.doc.content.size;
  const text = editor.value.state.doc.textBetween(from, to, '\n').trim();
  if (!text) return;

  aiBusy.value = true;
  try {
    const r = await fetch('/api/ai/assist', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
      body: JSON.stringify({ text, action }),
    });
    const data = await r.json();
    if (data.result) {
      editor.value.chain().focus().insertContentAt({ from, to }, toParagraphs(data.result)).run();
    } else {
      window.alert(data.error || t('AI request failed.'));
    }
  } catch {
    window.alert(t('AI request failed.'));
  } finally {
    aiBusy.value = false;
  }
}

const isActive = (name: string, attrs?: Record<string, unknown>) => editor.value?.isActive(name, attrs) ?? false;
function setLink() {
  const url = window.prompt(t('Link URL'));
  if (url === null) return;
  if (url === '') editor.value?.chain().focus().unsetLink().run();
  else editor.value?.chain().focus().toggleLink({ href: url }).run();
}
function pickImage() {
  fileInput.value?.click();
}
function onFile(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0];
  if (file) doUpload(file);
  (e.target as HTMLInputElement).value = '';
}
</script>

<template>
  <div class="relative overflow-hidden rounded-c border border-line bg-surface">
    <div v-if="editor" class="flex flex-wrap items-center gap-0.5 border-b border-line bg-surface-2 px-2.5 py-2">
      <button type="button" class="tb" :class="{ on: isActive('bold') }" :title="t('Bold')" :aria-label="t('Bold')" :data-tip="t('Bold (Ctrl+B)')" @click="editor.chain().focus().toggleBold().run()"><b>B</b></button>
      <button type="button" class="tb italic" :class="{ on: isActive('italic') }" :title="t('Italic')" :aria-label="t('Italic')" :data-tip="t('Italic (Ctrl+I)')" @click="editor.chain().focus().toggleItalic().run()">I</button>
      <button type="button" class="tb underline" :class="{ on: isActive('underline') }" :title="t('Underline')" :aria-label="t('Underline')" :data-tip="t('Underline (Ctrl+U)')" @click="editor.chain().focus().toggleUnderline().run()">U</button>
      <button type="button" class="tb line-through" :class="{ on: isActive('strike') }" :title="t('Strikethrough')" :aria-label="t('Strikethrough')" :data-tip="t('Strikethrough')" @click="editor.chain().focus().toggleStrike().run()">S</button>
      <span class="mx-1.5 h-5 w-px bg-line"></span>
      <button type="button" class="tb" :class="{ on: isActive('heading', { level: 2 }) }" :title="t('Heading')" :aria-label="t('Heading')" :data-tip="t('Heading')" @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"><b>H</b></button>
      <button type="button" class="tb" :class="{ on: isActive('blockquote') }" :title="t('Quote')" :aria-label="t('Quote')" :data-tip="t('Quote')" @click="editor.chain().focus().toggleBlockquote().run()">”</button>
      <button type="button" class="tb font-mono text-xs" :class="{ on: isActive('codeBlock') }" :title="t('Code block')" :aria-label="t('Code block')" :data-tip="t('Code block')" @click="editor.chain().focus().toggleCodeBlock().run()">{}</button>
      <button type="button" class="tb" :class="{ on: isActive('bulletList') }" :title="t('Bullet list')" :aria-label="t('Bullet list')" :data-tip="t('Bullet list')" @click="editor.chain().focus().toggleBulletList().run()">•</button>
      <button type="button" class="tb" :class="{ on: isActive('orderedList') }" :title="t('Numbered list')" :aria-label="t('Numbered list')" :data-tip="t('Numbered list')" @click="editor.chain().focus().toggleOrderedList().run()">1.</button>
      <span class="mx-1.5 h-5 w-px bg-line"></span>
      <button type="button" class="tb" :class="{ on: isActive('link') }" :title="t('Insert link')" :aria-label="t('Insert link')" :data-tip="t('Insert link')" @click="setLink">🔗</button>
      <button type="button" class="tb" :title="t('Insert image')" :aria-label="t('Insert image')" :data-tip="t('Insert image')" @click="pickImage">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" /><circle cx="9" cy="9" r="2" /><path d="m21 15-5-5L5 21" /></svg>
      </button>
      <template v-if="aiEnabled">
        <span class="mx-1.5 h-5 w-px bg-line"></span>
        <div class="relative">
          <button type="button" class="tb w-auto gap-1 px-2 font-semibold text-primary" :class="{ on: aiMenuOpen }" :title="t('AI writing assistant')" :data-tip="t('AI writing assistant')" :disabled="aiBusy" @click="aiMenuOpen = !aiMenuOpen">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v3M12 18v3M5.6 5.6l2.1 2.1M16.3 16.3l2.1 2.1M3 12h3M18 12h3M5.6 18.4l2.1-2.1M16.3 7.7l2.1-2.1" /><circle cx="12" cy="12" r="3" /></svg>
            <span class="text-xs">{{ aiBusy ? t('Thinking…') : 'AI' }}</span>
          </button>
          <div v-if="aiMenuOpen" class="absolute left-0 top-full z-30 mt-1 w-52 overflow-hidden rounded-xl border border-line bg-surface py-1 shadow-xl">
            <button v-for="a in AI_ACTIONS" :key="a.action" type="button" class="block w-full px-3 py-1.5 text-left text-sm text-ink hover:bg-surface-2" @click="runAi(a.action)">{{ a.label }}</button>
          </div>
        </div>
      </template>
      <span class="mx-1.5 h-5 w-px bg-line"></span>
      <button type="button" class="tb" :title="t('Undo')" :aria-label="t('Undo')" :data-tip="t('Undo (Ctrl+Z)')" @click="editor.chain().focus().undo().run()">↶</button>
      <button type="button" class="tb" :title="t('Redo')" :aria-label="t('Redo')" :data-tip="t('Redo (Ctrl+Y)')" @click="editor.chain().focus().redo().run()">↷</button>
      <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="onFile" />
    </div>
    <EditorContent :editor="editor" class="min-h-[110px] px-4 py-3 text-ink" />
    <div v-if="uploading" class="pointer-events-none absolute bottom-2 left-3 inline-flex items-center gap-2 rounded-full bg-ink/80 px-3 py-1 text-xs font-semibold text-white">
      <span class="h-2 w-2 animate-pulse rounded-full bg-white"></span> {{ t('Uploading & converting to WebP…') }}
    </div>
  </div>
</template>

<style scoped>
.tb { position: relative; display: grid; place-items: center; width: 30px; height: 30px; border-radius: 7px; color: rgb(var(--c-text-2)); cursor: pointer; border: 0; background: none; font-size: 14px; }
.tb:hover { background: rgb(var(--c-surface)); box-shadow: 0 0 0 1px rgb(var(--c-border)); }
.tb[data-tip]:hover::after {
  content: attr(data-tip);
  position: absolute; bottom: calc(100% + 6px); left: 50%; transform: translateX(-50%);
  background: rgb(var(--c-text)); color: rgb(var(--c-surface));
  font-size: 11px; font-weight: 600; line-height: 1; white-space: nowrap;
  padding: 5px 7px; border-radius: 6px; pointer-events: none; z-index: 30;
}
.tb.on { background: rgb(var(--c-primary) / 0.15); color: rgb(var(--c-primary)); }
:deep(.ProseMirror) { min-height: 90px; }
:deep(.ProseMirror p.is-editor-empty:first-child::before) { content: attr(data-placeholder); color: rgb(var(--c-muted)); float: left; height: 0; pointer-events: none; }
:deep(.prose-q) { line-height: 1.65; }
:deep(.prose-q p) { margin: 0 0 10px; }
:deep(.prose-q blockquote) { border-left: 3px solid rgb(var(--c-primary)); background: rgb(var(--c-surface-2)); padding: 10px 14px; border-radius: 0 10px 10px 0; color: rgb(var(--c-text-2)); margin: 0 0 10px; }
:deep(.prose-q blockquote > p:first-child) { font-size: 0.82em; font-weight: 600; color: rgb(var(--c-muted)); margin-bottom: 6px; }
:deep(.prose-q blockquote a) { color: rgb(var(--c-primary)); font-weight: 700; text-decoration: none; }
:deep(.prose-q ul) { list-style: disc; padding-left: 22px; }
:deep(.prose-q ol) { list-style: decimal; padding-left: 22px; }
:deep(.prose-q h2) { font-size: 1.3em; font-weight: 700; margin: 4px 0 8px; }
:deep(.prose-q pre) { background: rgb(var(--c-surface-2)); padding: 10px 12px; border-radius: 8px; font-family: monospace; font-size: 13px; overflow: auto; }
:deep(.prose-q a) { color: rgb(var(--c-primary)); text-decoration: underline; }
:deep(.prose-q img) { max-width: 100%; height: auto; border-radius: 10px; border: 1px solid rgb(var(--c-border)); margin: 4px 0; }
</style>
