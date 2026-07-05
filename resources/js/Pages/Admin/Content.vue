<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { reactive, ref, watch } from 'vue';
import draggable from 'vuedraggable';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t as tr } from '@/lib/i18n';

type Tag = { id: number; name: string; slug: string; color: string; icon: string | null; topics: number; is_sandbox?: boolean; children?: Tag[] };

const props = defineProps<{
  categories: { id: number; name: string; slug: string; description: string | null; icon: string | null; color: string; position: number; topics: number; slow_mode: number }[];
  tags: Tag[];
  tagRules: { minPrimary: number; maxPrimary: number };
}>();

// Flarum-style min/max primary tags required when starting a topic (0 = off).
const rules = reactive({ min: props.tagRules.minPrimary, max: props.tagRules.maxPrimary });
watch(() => props.tagRules, (v) => { rules.min = v.minPrimary; rules.max = v.maxPrimary; });
function saveRules() {
  router.post('/admin/tags/settings', { min_primary: rules.min, max_primary: rules.max }, { preserveScroll: true, preserveState: true });
}

const opts = { preserveScroll: true };

// --- Categories (legacy) ---
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
function saveCat() { router.put(`/admin/categories/${editCatId.value}`, { ...catBuf }, { ...opts, onSuccess: () => (editCatId.value = null) }); }
function delCat(c: any) { if (confirm(tr('Delete category “{name}”?', { name: c.name }))) router.delete(`/admin/categories/${c.id}`, opts); }

// --- Tags (Flarum-style: drag to reorder, drop onto a tag to nest as a sub-tag) ---
const primaries = ref<Tag[]>(clone(props.tags));
const dragging = ref(false);
watch(() => props.tags, (v) => { primaries.value = clone(v); });
function clone(v: Tag[]): Tag[] { return JSON.parse(JSON.stringify(v || [])).map((t: Tag) => ({ ...t, children: t.children || [] })); }

// Keep it two levels: a tag that already has sub-tags can't itself be nested.
function checkMove(evt: any) {
  const dragged = evt.draggedContext?.element;
  const intoChildren = !!evt.to?.classList?.contains('children-zone');
  if (intoChildren && dragged?.children && dragged.children.length) return false;
  return true;
}

let treeTimer: any = null;
function persistTree() {
  clearTimeout(treeTimer);
  treeTimer = setTimeout(() => {
    const tree = primaries.value.map((p) => ({ id: p.id, children: (p.children || []).map((c) => c.id) }));
    router.post('/admin/tags/tree', { tree }, { preserveScroll: true, preserveState: true });
  }, 140);
}

const newTag = reactive({ name: '', color: '#6366f1', icon: '' });
const editTagId = ref<number | null>(null);
const tagBuf = reactive({ name: '', color: '#6366f1', icon: '', is_sandbox: false });
function addTag() {
  if (!newTag.name.trim()) return;
  router.post('/admin/tags', { ...newTag }, { ...opts, onSuccess: () => Object.assign(newTag, { name: '', color: '#6366f1', icon: '' }) });
}
function startTag(t: Tag) { editTagId.value = t.id; Object.assign(tagBuf, { name: t.name, color: t.color, icon: t.icon ?? '', is_sandbox: !!t.is_sandbox }); }
function saveTag() { router.put(`/admin/tags/${editTagId.value}`, { ...tagBuf }, { ...opts, onSuccess: () => (editTagId.value = null) }); }

// Reliable, non-drag nesting (drag is fiddly for empty zones). Mutate the tree
// locally then persist via the same saveTagTree endpoint the drag uses.
function nestUnder(child: any, parentId: number) {
  const parent = primaries.value.find((t: any) => t.id === parentId);
  if (!parent || parent.id === child.id) return;
  primaries.value = primaries.value.filter((t: any) => t.id !== child.id);
  if (!parent.children) parent.children = [];
  parent.children.push({ ...child, children: [] });
  persistTree();
}
function onNestSelect(child: any, e: Event) {
  const sel = e.target as HTMLSelectElement;
  const id = Number(sel.value);
  sel.value = '';
  if (id) nestUnder(child, id);
}
function promote(child: any, parent: any) {
  parent.children = (parent.children || []).filter((c: any) => c.id !== child.id);
  primaries.value.push({ ...child, children: [] });
  persistTree();
}
function delTag(t: Tag) { if (confirm(tr('Delete tag “{name}”?', { name: t.name }))) router.delete(`/admin/tags/${t.id}`, opts); }

const inp = 'rounded-lg border-line bg-appbg text-sm text-ink focus:border-indigo-500 focus:ring-indigo-500';
</script>

<template>
  <Head :title="tr('Admin · Tags')" />
  <AdminLayout>
    <template #title>{{ tr('Tags') }}</template>
    <template #subtitle>{{ tr('Organize your community’s discussions') }}</template>

    <div class="space-y-6">
      <!-- Tags — Flarum-style drag-and-drop tree -->
      <section class="rounded-2xl border border-line bg-surface p-5">
        <h3 class="mb-1 text-sm font-bold text-ink">{{ tr('Tags') }}</h3>
        <p class="mb-4 text-xs text-ink-muted">{{ tr('Drag the handle to reorder, or drop a tag into another’s sub-tag area to nest it. You can also use the “Nest under…” menu on any tag, and “Promote” to send a sub-tag back to the top level.') }}</p>

        <div class="mb-3 flex flex-wrap items-center gap-4 rounded-xl border border-line bg-appbg p-3">
          <span class="text-xs font-bold uppercase tracking-wide text-ink-muted">{{ tr('Primary tags per topic') }}</span>
          <label class="flex items-center gap-2 text-sm text-ink-2">{{ tr('Min') }}
            <input v-model.number="rules.min" type="number" min="0" max="10" :class="inp" class="w-16" @change="saveRules" />
          </label>
          <label class="flex items-center gap-2 text-sm text-ink-2">{{ tr('Max') }}
            <input v-model.number="rules.max" type="number" min="0" max="10" :class="inp" class="w-16" @change="saveRules" />
          </label>
          <span class="text-xs text-ink-muted">{{ tr('0 = no limit. Members must pick this many primary tags when posting (Flarum-style).') }}</span>
        </div>

        <div class="mb-4 flex flex-wrap items-end gap-2 rounded-xl border border-line bg-appbg p-3">
          <span class="grid h-9 w-9 place-items-center rounded-lg" :style="{ color: newTag.color, background: newTag.color + '22' }"><i v-if="newTag.icon && newTag.icon.startsWith('fa')" :class="newTag.icon"></i><span v-else>#</span></span>
          <input v-model="newTag.name" :class="inp" class="min-w-[140px] flex-1" :placeholder="tr('New tag name')" @keyup.enter="addTag" />
          <input v-model="newTag.icon" :class="inp" class="min-w-[180px] flex-1 font-mono text-xs" :placeholder="tr('Font Awesome class (optional)')" />
          <input v-model="newTag.color" type="color" class="h-9 w-10 rounded border-line bg-transparent" />
          <button class="rounded-lg bg-primary px-3 py-2 text-sm font-semibold text-white hover:bg-primary-600" @click="addTag">{{ tr('Create tag') }}</button>
        </div>

        <draggable :list="primaries" item-key="id" handle=".thandle" :group="{ name: 'tags' }" :move="checkMove" :swap-threshold="0.55" :fallback-on-body="true" :empty-insert-threshold="10" class="space-y-2" @change="persistTree" @start="dragging = true" @end="dragging = false">
          <template #item="{ element: p }">
            <div class="rounded-xl border border-line bg-appbg">
              <!-- primary tag row -->
              <div class="flex items-center gap-3 p-3">
                <span class="thandle cursor-grab select-none px-0.5 text-ink-muted hover:text-ink active:cursor-grabbing" :title="tr('Drag to reorder or nest')">⠿</span>
                <span class="h-8 w-1.5 rounded-full" :style="{ background: p.color }"></span>
                <template v-if="editTagId === p.id">
                  <input v-model="tagBuf.name" :class="inp" class="w-40" />
                  <input v-model="tagBuf.icon" :class="inp" class="w-44 font-mono text-xs" :placeholder="tr('FA class')" />
                  <input v-model="tagBuf.color" type="color" class="h-8 w-9 rounded border-line bg-transparent" />
                  <label class="flex items-center gap-1.5 text-xs text-ink-2" :title="tr('Sandbox tags are practice areas — their topics never trend and don’t count toward community or member stats.')">
                    <input v-model="tagBuf.is_sandbox" type="checkbox" class="rounded border-line bg-appbg text-indigo-500 focus:ring-indigo-500" />
                    {{ tr('Sandbox') }}
                  </label>
                  <button class="rounded-lg bg-emerald-500 px-3 py-1.5 text-xs font-semibold text-white" @click="saveTag">{{ tr('Save') }}</button>
                  <button class="text-xs text-ink-2 hover:text-ink" @click="editTagId = null">{{ tr('Cancel') }}</button>
                </template>
                <template v-else>
                  <i v-if="p.icon && p.icon.startsWith('fa')" :class="p.icon" :style="{ color: p.color }"></i>
                  <span class="font-semibold text-ink">{{ p.name }}</span>
                  <span v-if="p.is_sandbox" class="rounded bg-amber-400/15 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-amber-400" :title="tr('Practice area — excluded from Trending and stats')">{{ tr('Sandbox') }}</span>
                  <span class="text-xs text-ink-muted">{{ tr('{n} topics', { n: p.topics }) }}</span>
                  <span class="ml-auto"></span>
                  <select v-if="!p.children || !p.children.length" :class="inp" class="!py-1 text-[11px]" :title="tr('Make this a sub-tag of…')" @change="onNestSelect(p, $event)">
                    <option value="">{{ tr('Nest under…') }}</option>
                    <option v-for="q in primaries.filter((t: any) => t.id !== p.id)" :key="q.id" :value="q.id">{{ q.name }}</option>
                  </select>
                  <button class="text-xs text-ink-2 hover:text-ink" @click="startTag(p)">{{ tr('Edit') }}</button>
                  <button class="text-xs text-ink-2 hover:text-red-400" @click="delTag(p)">{{ tr('Delete') }}</button>
                </template>
              </div>
              <!-- sub-tags drop zone -->
              <div class="px-3 pb-3 pl-10">
                <p class="mb-1 text-[10px] font-bold uppercase tracking-wide" :class="dragging ? 'text-indigo-400' : 'text-ink-muted/70'">{{ dragging ? tr('Drop here to make a sub-tag') : tr('Sub-tags') }}</p>
                <draggable :list="p.children" item-key="id" handle=".thandle" :group="{ name: 'tags' }" :move="checkMove" :swap-threshold="0.65" :fallback-on-body="true" :empty-insert-threshold="40" :class="['children-zone min-h-[40px] space-y-1.5 rounded-lg border border-dashed p-1.5 transition-colors', dragging ? 'border-indigo-400/70 bg-indigo-400/10' : 'border-line']" @change="persistTree" @start="dragging = true" @end="dragging = false">
                  <template #item="{ element: c }">
                    <div class="flex items-center gap-2 rounded-lg bg-surface px-2.5 py-1.5">
                      <span class="thandle cursor-grab select-none text-xs text-ink-muted hover:text-ink active:cursor-grabbing">⠿</span>
                      <span class="h-4 w-1 rounded-full" :style="{ background: c.color }"></span>
                      <template v-if="editTagId === c.id">
                        <input v-model="tagBuf.name" :class="inp" class="w-32 !py-1 text-xs" />
                        <input v-model="tagBuf.color" type="color" class="h-7 w-8 rounded border-line bg-transparent" />
                        <label class="flex items-center gap-1 text-[11px] text-ink-2" :title="tr('Sandbox tags are practice areas — their topics never trend and don’t count toward community or member stats.')">
                          <input v-model="tagBuf.is_sandbox" type="checkbox" class="rounded border-line bg-appbg text-indigo-500 focus:ring-indigo-500" />
                          {{ tr('Sandbox') }}
                        </label>
                        <button class="rounded bg-emerald-500 px-2 py-1 text-[11px] font-semibold text-white" @click="saveTag">{{ tr('Save') }}</button>
                        <button class="text-[11px] text-ink-2 hover:text-ink" @click="editTagId = null">{{ tr('Cancel') }}</button>
                      </template>
                      <template v-else>
                        <span class="text-sm text-ink">{{ c.name }}</span>
                        <span v-if="c.is_sandbox" class="rounded bg-amber-400/15 px-1 py-0.5 text-[9px] font-bold uppercase tracking-wide text-amber-400" :title="tr('Practice area — excluded from Trending and stats')">{{ tr('Sandbox') }}</span>
                        <span class="text-[11px] text-ink-muted">{{ c.topics }}</span>
                        <span class="ml-auto"></span>
                        <button class="text-[11px] text-indigo-400 hover:text-indigo-300" :title="tr('Move back to top level')" @click="promote(c, p)">↑ {{ tr('Promote') }}</button>
                        <button class="text-[11px] text-ink-2 hover:text-ink" @click="startTag(c)">{{ tr('Edit') }}</button>
                        <button class="text-[11px] text-ink-2 hover:text-red-400" @click="delTag(c)">{{ tr('Delete') }}</button>
                      </template>
                    </div>
                  </template>
                </draggable>
              </div>
            </div>
          </template>
        </draggable>
        <p v-if="!primaries.length" class="rounded-xl border border-dashed border-line p-6 text-center text-sm text-ink-muted">{{ tr('No tags yet — create one above.') }}</p>
      </section>
    </div>
  </AdminLayout>
</template>
