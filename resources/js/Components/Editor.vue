<script setup lang="ts">
import { onBeforeUnmount, ref } from 'vue';
import { useEditor, EditorContent } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import Underline from '@tiptap/extension-underline';
import Link from '@tiptap/extension-link';
import Image from '@tiptap/extension-image';
import Placeholder from '@tiptap/extension-placeholder';
import { uploadImage, isImageFile } from '@/lib/upload';

const props = withDefaults(defineProps<{ placeholder?: string }>(), { placeholder: 'Write a reply…' });

const uploading = ref(false);
const fileInput = ref<HTMLInputElement | null>(null);

async function doUpload(file: File) {
  if (!isImageFile(file)) return;
  uploading.value = true;
  try {
    const { url } = await uploadImage(file);
    editor.value?.chain().focus().setImage({ src: url }).run();
  } catch (e) {
    window.alert('Image upload failed.');
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
    Placeholder.configure({ placeholder: props.placeholder }),
  ],
  content: '',
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
});

const isActive = (name: string, attrs?: Record<string, unknown>) => editor.value?.isActive(name, attrs) ?? false;
function setLink() {
  const url = window.prompt('Link URL');
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
  <div class="relative overflow-hidden rounded-q border border-line bg-surface">
    <div v-if="editor" class="flex flex-wrap items-center gap-0.5 border-b border-line bg-surface-2 px-2.5 py-2">
      <button type="button" class="tb" :class="{ on: isActive('bold') }" title="Bold" @click="editor.chain().focus().toggleBold().run()"><b>B</b></button>
      <button type="button" class="tb italic" :class="{ on: isActive('italic') }" title="Italic" @click="editor.chain().focus().toggleItalic().run()">I</button>
      <button type="button" class="tb underline" :class="{ on: isActive('underline') }" title="Underline" @click="editor.chain().focus().toggleUnderline().run()">U</button>
      <button type="button" class="tb line-through" :class="{ on: isActive('strike') }" title="Strikethrough" @click="editor.chain().focus().toggleStrike().run()">S</button>
      <span class="mx-1.5 h-5 w-px bg-line"></span>
      <button type="button" class="tb" :class="{ on: isActive('heading', { level: 2 }) }" title="Heading" @click="editor.chain().focus().toggleHeading({ level: 2 }).run()"><b>H</b></button>
      <button type="button" class="tb" :class="{ on: isActive('blockquote') }" title="Quote" @click="editor.chain().focus().toggleBlockquote().run()">”</button>
      <button type="button" class="tb font-mono text-xs" :class="{ on: isActive('codeBlock') }" title="Code" @click="editor.chain().focus().toggleCodeBlock().run()">{}</button>
      <button type="button" class="tb" :class="{ on: isActive('bulletList') }" title="Bullet list" @click="editor.chain().focus().toggleBulletList().run()">•</button>
      <button type="button" class="tb" :class="{ on: isActive('orderedList') }" title="Numbered list" @click="editor.chain().focus().toggleOrderedList().run()">1.</button>
      <span class="mx-1.5 h-5 w-px bg-line"></span>
      <button type="button" class="tb" :class="{ on: isActive('link') }" title="Link" @click="setLink">🔗</button>
      <button type="button" class="tb" title="Image" @click="pickImage">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" /><circle cx="9" cy="9" r="2" /><path d="m21 15-5-5L5 21" /></svg>
      </button>
      <span class="mx-1.5 h-5 w-px bg-line"></span>
      <button type="button" class="tb" title="Undo" @click="editor.chain().focus().undo().run()">↶</button>
      <button type="button" class="tb" title="Redo" @click="editor.chain().focus().redo().run()">↷</button>
      <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="onFile" />
    </div>
    <EditorContent :editor="editor" class="min-h-[110px] px-4 py-3 text-ink" />
    <div v-if="uploading" class="pointer-events-none absolute bottom-2 left-3 inline-flex items-center gap-2 rounded-full bg-ink/80 px-3 py-1 text-xs font-semibold text-white">
      <span class="h-2 w-2 animate-pulse rounded-full bg-white"></span> Uploading &amp; converting to WebP…
    </div>
  </div>
</template>

<style scoped>
.tb { display: grid; place-items: center; width: 30px; height: 30px; border-radius: 7px; color: rgb(var(--q-text-2)); cursor: pointer; border: 0; background: none; font-size: 14px; }
.tb:hover { background: rgb(var(--q-surface)); box-shadow: 0 0 0 1px rgb(var(--q-border)); }
.tb.on { background: rgb(var(--q-primary-soft)); color: rgb(var(--q-primary-700)); }
:deep(.ProseMirror) { min-height: 90px; }
:deep(.ProseMirror p.is-editor-empty:first-child::before) { content: attr(data-placeholder); color: rgb(var(--q-muted)); float: left; height: 0; pointer-events: none; }
:deep(.prose-q) { line-height: 1.65; }
:deep(.prose-q p) { margin: 0 0 10px; }
:deep(.prose-q blockquote) { border-left: 3px solid rgb(var(--q-primary)); padding-left: 12px; color: rgb(var(--q-text-2)); margin: 0 0 10px; }
:deep(.prose-q ul) { list-style: disc; padding-left: 22px; }
:deep(.prose-q ol) { list-style: decimal; padding-left: 22px; }
:deep(.prose-q h2) { font-size: 1.3em; font-weight: 700; margin: 4px 0 8px; }
:deep(.prose-q pre) { background: rgb(var(--q-surface-2)); padding: 10px 12px; border-radius: 8px; font-family: monospace; font-size: 13px; overflow: auto; }
:deep(.prose-q a) { color: rgb(var(--q-primary)); text-decoration: underline; }
:deep(.prose-q img) { max-width: 100%; height: auto; border-radius: 10px; border: 1px solid rgb(var(--q-border)); margin: 4px 0; }
</style>
