<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps<{
  categories: { id: number; name: string; slug: string; description: string | null; icon: string | null; color: string; position: number; topics: number }[];
  tags: { id: number; name: string; slug: string; color: string; topics: number }[];
}>();

const opts = { preserveScroll: true };

// --- Categories ---
const newCat = reactive({ name: '', icon: '', color: '#5b5bd6', description: '' });
const editCatId = ref<number | null>(null);
const catBuf = reactive({ name: '', icon: '', color: '#5b5bd6', description: '' });

function addCategory() {
  if (!newCat.name.trim()) return;
  router.post('/admin/categories', { ...newCat }, { ...opts, onSuccess: () => Object.assign(newCat, { name: '', icon: '', color: '#5b5bd6', description: '' }) });
}
function startCat(c: any) {
  editCatId.value = c.id;
  Object.assign(catBuf, { name: c.name, icon: c.icon ?? '', color: c.color, description: c.description ?? '' });
}
function saveCat() {
  router.put(`/admin/categories/${editCatId.value}`, { ...catBuf }, { ...opts, onSuccess: () => (editCatId.value = null) });
}
function delCat(c: any) {
  if (confirm(`Delete category “${c.name}”?`)) router.delete(`/admin/categories/${c.id}`, opts);
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
  if (confirm(`Delete tag “${t.name}”?`)) router.delete(`/admin/tags/${t.id}`, opts);
}

const inp = 'rounded-lg border-white/10 bg-[#0f1120] text-sm text-slate-100 focus:border-indigo-500 focus:ring-indigo-500';
</script>

<template>
  <Head title="Admin · Categories & Tags" />
  <AdminLayout>
    <template #title>Categories &amp; Tags</template>
    <template #subtitle>Organize your community’s discussions</template>

    <div class="grid gap-6 lg:grid-cols-2">
      <!-- Categories -->
      <section class="rounded-2xl border border-white/5 bg-[#14172a] p-5">
        <h3 class="mb-3 text-sm font-bold text-white">Categories</h3>

        <div class="mb-4 flex flex-wrap items-end gap-2 rounded-xl border border-white/5 bg-[#0f1120] p-3">
          <input v-model="newCat.icon" :class="inp" class="w-14 text-center" placeholder="🙂" maxlength="2" />
          <input v-model="newCat.name" :class="inp" class="flex-1" placeholder="New category name" @keyup.enter="addCategory" />
          <input v-model="newCat.color" type="color" class="h-9 w-10 rounded border-white/10 bg-transparent" />
          <button class="rounded-lg bg-indigo-500 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-600" @click="addCategory">Add</button>
        </div>

        <ul class="space-y-2">
          <li v-for="c in categories" :key="c.id" class="rounded-xl border border-white/5 p-3">
            <template v-if="editCatId === c.id">
              <div class="flex flex-wrap items-center gap-2">
                <input v-model="catBuf.icon" :class="inp" class="w-12 text-center" maxlength="2" />
                <input v-model="catBuf.name" :class="inp" class="flex-1" />
                <input v-model="catBuf.color" type="color" class="h-9 w-10 rounded border-white/10 bg-transparent" />
                <button class="rounded-lg bg-emerald-500 px-3 py-1.5 text-sm font-semibold text-white" @click="saveCat">Save</button>
                <button class="rounded-lg px-3 py-1.5 text-sm text-slate-400 hover:text-white" @click="editCatId = null">Cancel</button>
              </div>
              <input v-model="catBuf.description" :class="inp" class="mt-2 w-full" placeholder="Description (optional)" />
            </template>
            <div v-else class="flex items-center gap-3">
              <span class="flex h-8 w-8 items-center justify-center rounded-lg text-base" :style="{ background: c.color + '22' }">{{ c.icon || '#' }}</span>
              <div class="min-w-0 flex-1">
                <div class="font-semibold text-slate-100">{{ c.name }}</div>
                <div class="text-xs text-slate-500">{{ c.topics }} topics · /{{ c.slug }}</div>
              </div>
              <button class="text-slate-400 hover:text-white" @click="startCat(c)">Edit</button>
              <button class="text-slate-400 hover:text-red-400" @click="delCat(c)">Delete</button>
            </div>
          </li>
        </ul>
      </section>

      <!-- Tags -->
      <section class="rounded-2xl border border-white/5 bg-[#14172a] p-5">
        <h3 class="mb-3 text-sm font-bold text-white">Tags</h3>

        <div class="mb-4 flex flex-wrap items-end gap-2 rounded-xl border border-white/5 bg-[#0f1120] p-3">
          <input v-model="newTag.name" :class="inp" class="flex-1" placeholder="New tag name" @keyup.enter="addTag" />
          <input v-model="newTag.color" type="color" class="h-9 w-10 rounded border-white/10 bg-transparent" />
          <button class="rounded-lg bg-indigo-500 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-600" @click="addTag">Add</button>
        </div>

        <ul class="space-y-2">
          <li v-for="t in tags" :key="t.id" class="rounded-xl border border-white/5 p-3">
            <template v-if="editTagId === t.id">
              <div class="flex items-center gap-2">
                <input v-model="tagBuf.name" :class="inp" class="flex-1" />
                <input v-model="tagBuf.color" type="color" class="h-9 w-10 rounded border-white/10 bg-transparent" />
                <button class="rounded-lg bg-emerald-500 px-3 py-1.5 text-sm font-semibold text-white" @click="saveTag">Save</button>
                <button class="rounded-lg px-3 py-1.5 text-sm text-slate-400 hover:text-white" @click="editTagId = null">Cancel</button>
              </div>
            </template>
            <div v-else class="flex items-center gap-3">
              <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold" :style="{ color: t.color, background: t.color + '22' }">#{{ t.name }}</span>
              <span class="text-xs text-slate-500">{{ t.topics }} topics</span>
              <span class="ml-auto"></span>
              <button class="text-slate-400 hover:text-white" @click="startTag(t)">Edit</button>
              <button class="text-slate-400 hover:text-red-400" @click="delTag(t)">Delete</button>
            </div>
          </li>
        </ul>
      </section>
    </div>
  </AdminLayout>
</template>
