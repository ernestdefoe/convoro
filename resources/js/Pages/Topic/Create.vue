<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import Editor from '@/Components/Editor.vue';
import { uploadImage } from '@/lib/upload';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{
  categories: { id: number; name: string; icon: string | null; color: string }[];
  tags: { id: number; name: string; color: string }[];
}>();

const editor = ref<any>(null);
const uploadingCover = ref(false);

const form = useForm({
  title: '',
  category_id: props.categories[0]?.id ?? null,
  tags: [] as number[],
  cover: '' as string,
  body_html: '',
  body_json: '',
});

function toggleTag(id: number) {
  const i = form.tags.indexOf(id);
  if (i === -1) form.tags.push(id); else form.tags.splice(i, 1);
}

async function pickCover(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0];
  if (!file) return;
  uploadingCover.value = true;
  try { const { url } = await uploadImage(file); form.cover = url; } catch { /* ignore */ } finally { uploadingCover.value = false; }
}

function submit() {
  if (!editor.value || editor.value.isEmpty()) { form.setError('body_html', 'Write something first.'); return; }
  form.body_html = editor.value.getHTML();
  form.body_json = editor.value.getJSON();
  form.post('/topics');
}
</script>

<template>
  <Head title="Start a topic" />
  <AppLayout>
    <div class="mx-auto max-w-[760px]">
      <h1 class="mb-5 text-2xl font-extrabold tracking-tight">Start a topic</h1>

      <form class="space-y-5" @submit.prevent="submit">
        <div class="rounded-c border border-line bg-surface p-5">
          <label class="block text-sm font-semibold text-ink-2">Title</label>
          <input v-model="form.title" type="text" maxlength="160" placeholder="What do you want to discuss?"
            class="mt-1.5 w-full rounded-lg border-line bg-surface-2 text-ink placeholder:text-ink-muted focus:border-primary focus:ring-primary" />
          <p v-if="form.errors.title" class="mt-1 text-sm text-red-500">{{ form.errors.title }}</p>

          <label class="mt-4 block text-sm font-semibold text-ink-2">Category</label>
          <select v-model="form.category_id" class="mt-1.5 w-full rounded-lg border-line bg-surface-2 text-ink focus:border-primary focus:ring-primary">
            <option :value="null">— none —</option>
            <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
          </select>

          <label class="mt-4 block text-sm font-semibold text-ink-2">Tags</label>
          <div class="mt-2 flex flex-wrap gap-2">
            <button v-for="t in tags" :key="t.id" type="button"
              class="rounded-full border px-3 py-1 text-xs font-semibold"
              :class="form.tags.includes(t.id) ? 'border-primary bg-primary/15 text-primary' : 'border-line bg-surface-2 text-ink-2 hover:bg-surface'"
              @click="toggleTag(t.id)">#{{ t.name }}</button>
            <span v-if="!tags.length" class="text-sm text-ink-muted">No tags yet.</span>
          </div>

          <label class="mt-4 block text-sm font-semibold text-ink-2">Cover image <span class="font-normal text-ink-muted">(optional, for grid view)</span></label>
          <div class="mt-2 flex items-center gap-3">
            <img v-if="form.cover" :src="form.cover" alt="" class="h-14 w-24 rounded-lg object-cover" />
            <input type="file" accept="image/*" class="text-sm text-ink-2" @change="pickCover" />
            <span v-if="uploadingCover" class="text-sm text-ink-muted">Uploading…</span>
          </div>
        </div>

        <div>
          <label class="mb-1.5 block text-sm font-semibold text-ink-2">Post</label>
          <Editor ref="editor" placeholder="Write your post… (rich text, drag images in)" />
          <p v-if="form.errors.body_html" class="mt-1 text-sm text-red-500">{{ form.errors.body_html }}</p>
        </div>

        <div class="flex items-center gap-3">
          <button type="submit" :disabled="form.processing" class="rounded-c bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-primary/30 hover:bg-primary-600 disabled:opacity-60">
            {{ form.processing ? 'Posting…' : 'Post topic' }}
          </button>
          <Link href="/" class="text-sm font-semibold text-ink-2 hover:text-ink">Cancel</Link>
        </div>
      </form>
    </div>
  </AppLayout>
</template>
