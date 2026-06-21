<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { reactive, ref, watch } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t as tr } from '@/lib/i18n';

const props = defineProps<{
  categories: { id: number; name: string; slug: string; description: string | null; icon: string | null; color: string; position: number; topics: number; slow_mode: number }[];
  tags: { id: number; name: string; slug: string; color: string; topics: number }[];
}>();

const opts = { preserveScroll: true };

// --- Categories ---
// Local working copy so a drag reorders instantly; re-synced whenever the
// server sends a fresh list (after add/edit/delete/reorder).
const cats = ref<any[]>([...props.categories]);
watch(() => props.categories, (v) => { cats.value = [...v]; });

const dragIndex = ref<number | null>(null);
function onDragStart(i: number) { dragIndex.value = i; }
function onDrop(i: number) {
  const from = dragIndex.value;
  dragIndex.value = null;
  if (from === null || from === i) return;
  const arr = [...cats.value];
  const [moved] = arr.splice(from, 1);
  arr.splice(i, 0, moved);
  cats.value = arr;
  router.post('/admin/categories/reorder', { order: arr.map((c) => c.id) }, opts);
}

const newCat = reactive({ name: '', icon: '', color: '#5b5bd6', description: '', slow_mode: 0 });
const editCatId = ref<number | null>(null);
const catBuf = reactive({ name: '', icon: '', color: '#5b5bd6', description: '', slow_mode: 0 });

function addCategory() {
  if (!newCat.name.trim()) return;
  router.post('/admin/categories', { ...newCat }, { ...opts, onSuccess: () => Object.assign(newCat, { name: '', icon: '', color: '#5b5bd6', description: '', slow_mode: 0 }) });
}
function startCat(c: any) {
  editCatId.value = c.id;
  Object.assign(catBuf, { name: c.name, icon: c.icon ?? '', color: c.color, description: c.description ?? '', slow_mode: c.slow_mode ?? 0 });
}
function saveCat() {
  router.put(`/admin/categories/${editCatId.value}`, { ...catBuf }, { ...opts, onSuccess: () => (editCatId.value = null) });
}
function delCat(c: any) {
  if (confirm(tr('Delete category “{name}”?', { name: c.name }))) router.delete(`/admin/categories/${c.id}`, opts);
}

// --- Tags ---
const newTag = reactive({ name: '', color: '#6366f1' });
const editTagId = ref<number | null>(null);
const tagBuf = reactive({ name: '', color: '#6366f1' });

function addTag() {
  if (!newTag.name.trim()) return;
  router.post('/admin/tags', { ...newTag }, { ...opts, onSuccess: () => Object.assign(newTag, { name: '', color: '#6366f1' }) });
}
function startTag(t: any) {
  editTagId.value = t.id;
  Object.assign(tagBuf, { name: t.name, color: t.color });
}
function saveTag() {
  router.put(`/admin/tags/${editTagId.value}`, { ...tagBuf }, { ...opts, onSuccess: () => (editTagId.value = null) });
}
function delTag(t: any) {
  if (confirm(tr('Delete tag “{name}”?', { name: t.name }))) router.delete(`/admin/tags/${t.id}`, opts);
}

const inp = 'rounded-lg border-line bg-appbg text-sm text-ink focus:border-indigo-500 focus:ring-indigo-500';
</script>

<template>
  <Head :title="tr('Admin · Categories & Tags')" />
  <AdminLayout>
    <template #title>{{ tr('Categories & Tags') }}</template>
    <template #subtitle>{{ tr('Organize your community’s discussions') }}</template>

    <div class="grid gap-6 lg:grid-cols-2">
      <!-- Categories -->
      <section class="rounded-2xl border border-line bg-surface p-5">
        <h3 class="mb-3 text-sm font-bold text-ink">{{ tr('Categories') }}</h3>

        <div class="mb-4 rounded-xl border border-line bg-appbg p-3">
          <div class="flex items-center gap-2">
            <span class="grid h-9 w-9 place-items-center rounded-lg bg-surface-2 text-ink-2"><i v-if="newCat.icon" :class="newCat.icon"></i><span v-else>#</span></span>
            <input v-model="newCat.name" :class="inp" class="flex-1" :placeholder="tr('New category name')" @keyup.enter="addCategory" />
            <input v-model="newCat.color" type="color" class="h-9 w-10 rounded border-line bg-transparent" />
            <button class="rounded-lg bg-indigo-500 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-600" @click="addCategory">{{ tr('Add') }}</button>
          </div>
          <input v-model="newCat.icon" :class="inp" class="mt-2 w-full font-mono" :placeholder="tr('Font Awesome class — e.g. fa-solid fa-rocket')" />
        </div>

        <ul class="space-y-2">
          <li
            v-for="(c, i) in cats"
            :key="c.id"
            class="rounded-xl border border-line p-3 transition"
            :class="dragIndex === i ? 'opacity-50' : ''"
            @dragover.prevent
            @drop="onDrop(i)"
          >
            <template v-if="editCatId === c.id">
              <div class="flex flex-wrap items-center gap-2">
                <span class="grid h-9 w-9 place-items-center rounded-lg bg-surface-2 text-ink-2"><i v-if="catBuf.icon" :class="catBuf.icon"></i><span v-else>#</span></span>
                <input v-model="catBuf.name" :class="inp" class="flex-1" />
                <input v-model="catBuf.color" type="color" class="h-9 w-10 rounded border-line bg-transparent" />
                <button class="rounded-lg bg-emerald-500 px-3 py-1.5 text-sm font-semibold text-white" @click="saveCat">{{ tr('Save') }}</button>
                <button class="rounded-lg px-3 py-1.5 text-sm text-ink-2 hover:text-ink" @click="editCatId = null">{{ tr('Cancel') }}</button>
              </div>
              <input v-model="catBuf.icon" :class="inp" class="mt-2 w-full font-mono" :placeholder="tr('Font Awesome class — e.g. fa-solid fa-rocket')" />
              <input v-model="catBuf.description" :class="inp" class="mt-2 w-full" :placeholder="tr('Description (optional)')" />
              <label class="mt-2 flex items-center gap-2 text-xs text-ink-muted">
                <span>{{ tr('Slow mode — seconds between posts in this category (0 = off)') }}</span>
                <input v-model.number="catBuf.slow_mode" type="number" min="0" max="86400" :class="inp" class="w-24" />
              </label>
            </template>
            <div v-else class="flex items-center gap-3">
              <span
                class="-ml-1 cursor-grab select-none px-1 text-ink-muted hover:text-ink active:cursor-grabbing"
                draggable="true"
                :title="tr('Drag to reorder')"
                :aria-label="tr('Drag to reorder')"
                @dragstart="onDragStart(i)"
                @dragend="dragIndex = null"
              >⠿</span>
              <span class="flex h-8 w-8 items-center justify-center rounded-lg text-base" :style="{ color: c.color, background: c.color + '22' }">
                <i v-if="c.icon && c.icon.startsWith('fa')" :class="c.icon"></i><span v-else>{{ c.icon || '#' }}</span>
              </span>
              <div class="min-w-0 flex-1">
                <div class="font-semibold text-ink">{{ c.name }}</div>
                <div class="text-xs text-ink-muted">{{ tr('{n} topics', { n: c.topics }) }} · /{{ c.slug }}</div>
              </div>
              <button class="text-ink-2 hover:text-ink" @click="startCat(c)">{{ tr('Edit') }}</button>
              <button class="text-ink-2 hover:text-red-400" @click="delCat(c)">{{ tr('Delete') }}</button>
            </div>
          </li>
        </ul>
      </section>

      <!-- Tags -->
      <section class="rounded-2xl border border-line bg-surface p-5">
        <h3 class="mb-3 text-sm font-bold text-ink">{{ tr('Tags') }}</h3>

        <div class="mb-4 flex flex-wrap items-end gap-2 rounded-xl border border-line bg-appbg p-3">
          <input v-model="newTag.name" :class="inp" class="flex-1" :placeholder="tr('New tag name')" @keyup.enter="addTag" />
          <input v-model="newTag.color" type="color" class="h-9 w-10 rounded border-line bg-transparent" />
          <button class="rounded-lg bg-indigo-500 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-600" @click="addTag">{{ tr('Add') }}</button>
        </div>

        <ul class="space-y-2">
          <li v-for="t in tags" :key="t.id" class="rounded-xl border border-line p-3">
            <template v-if="editTagId === t.id">
              <div class="flex items-center gap-2">
                <input v-model="tagBuf.name" :class="inp" class="flex-1" />
                <input v-model="tagBuf.color" type="color" class="h-9 w-10 rounded border-line bg-transparent" />
                <button class="rounded-lg bg-emerald-500 px-3 py-1.5 text-sm font-semibold text-white" @click="saveTag">{{ tr('Save') }}</button>
                <button class="rounded-lg px-3 py-1.5 text-sm text-ink-2 hover:text-ink" @click="editTagId = null">{{ tr('Cancel') }}</button>
              </div>
            </template>
            <div v-else class="flex items-center gap-3">
              <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold" :style="{ color: t.color, background: t.color + '22' }">#{{ t.name }}</span>
              <span class="text-xs text-ink-muted">{{ tr('{n} topics', { n: t.topics }) }}</span>
              <span class="ml-auto"></span>
              <button class="text-ink-2 hover:text-ink" @click="startTag(t)">{{ tr('Edit') }}</button>
              <button class="text-ink-2 hover:text-red-400" @click="delTag(t)">{{ tr('Delete') }}</button>
            </div>
          </li>
        </ul>
      </section>
    </div>
  </AdminLayout>
</template>
