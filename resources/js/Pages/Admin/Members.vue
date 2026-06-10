<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { reactive, ref, watch } from 'vue';

const props = defineProps<{
  users: { data: any[]; links: { url: string | null; label: string; active: boolean }[]; prev_page_url: string | null; next_page_url: string | null; total: number };
  q: string;
  groups: { id: number; name: string; color: string; is_staff: boolean; permissions: string[] | null }[];
  permissionCatalog: { key: string; label: string; category: string; baseline: boolean }[];
}>();

const assignablePerms = props.permissionCatalog.filter((p) => !p.baseline);

const opts = { preserveScroll: true, preserveState: true };
const search = ref(props.q ?? '');
let t: any = null;
watch(search, (v) => {
  clearTimeout(t);
  t = setTimeout(() => router.get('/admin/members', { q: v }, { preserveState: true, replace: true }), 250);
});

// --- edit member ---
const editing = ref<any>(null);
const buf = reactive({ name: '', email: '', is_admin: false, groups: [] as number[] });
function edit(u: any) {
  editing.value = u;
  Object.assign(buf, { name: u.name, email: u.email, is_admin: u.is_admin, groups: u.groups.map((g: any) => g.id) });
}
function toggleGroup(id: number) {
  const i = buf.groups.indexOf(id);
  if (i === -1) buf.groups.push(id); else buf.groups.splice(i, 1);
}
function saveMember() {
  router.put(`/admin/members/${editing.value.id}`, { ...buf }, { ...opts, onSuccess: () => (editing.value = null) });
}
function deleteMember() {
  if (!confirm(`Delete ${editing.value.name}? This removes their account and content.`)) return;
  router.delete(`/admin/members/${editing.value.id}`, { ...opts, onSuccess: () => (editing.value = null) });
}

// --- groups manager ---
const newGroup = reactive({ name: '', color: '#6366f1', is_staff: false, permissions: [] as string[] });
const editGroupId = ref<number | null>(null);
const gbuf = reactive({ name: '', color: '#6366f1', is_staff: false, permissions: [] as string[] });
function addGroup() {
  if (!newGroup.name.trim()) return;
  router.post('/admin/groups', { ...newGroup }, { ...opts, onSuccess: () => Object.assign(newGroup, { name: '', color: '#6366f1', is_staff: false, permissions: [] }) });
}
function startGroup(g: any) { editGroupId.value = g.id; Object.assign(gbuf, { name: g.name, color: g.color, is_staff: g.is_staff, permissions: [...(g.permissions ?? [])] }); }
function saveGroup() { router.put(`/admin/groups/${editGroupId.value}`, { ...gbuf }, { ...opts, onSuccess: () => (editGroupId.value = null) }); }
function delGroup(g: any) { if (confirm(`Delete group “${g.name}”?`)) router.delete(`/admin/groups/${g.id}`, opts); }

const inp = 'rounded-lg border-white/10 bg-[#0f1120] text-sm text-slate-100 focus:border-indigo-500 focus:ring-indigo-500';
</script>

<template>
  <Head title="Admin · Members" />
  <AdminLayout>
    <template #title>Members</template>
    <template #subtitle>{{ users.total }} members</template>

    <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
      <!-- Members list -->
      <section class="rounded-2xl border border-white/5 bg-[#14172a] p-5">
        <input v-model="search" type="text" placeholder="Search name or email…" :class="inp" class="mb-4 w-full" />
        <ul class="divide-y divide-white/5">
          <li v-for="u in users.data" :key="u.id" class="flex items-center gap-3 py-3">
            <img v-if="u.avatar" :src="u.avatar" class="h-9 w-9 rounded-full object-cover" alt="" />
            <span v-else class="grid h-9 w-9 place-items-center rounded-full bg-indigo-500/30 text-xs font-bold text-indigo-200">{{ u.initials }}</span>
            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-2">
                <span class="truncate font-semibold text-slate-100">{{ u.name }}</span>
                <span v-if="u.is_admin" class="rounded-full bg-indigo-500/20 px-1.5 py-0.5 text-[10px] font-bold text-indigo-300">ADMIN</span>
                <span v-for="g in u.groups" :key="g.id" class="rounded-full px-1.5 py-0.5 text-[10px] font-semibold" :style="{ color: g.color, background: g.color + '22' }">{{ g.name }}</span>
              </div>
              <div class="truncate text-xs text-slate-500">{{ u.email }} · joined {{ u.joined }}</div>
            </div>
            <button class="text-sm font-semibold text-indigo-300 hover:text-indigo-200" @click="edit(u)">Edit</button>
          </li>
          <li v-if="!users.data.length" class="py-6 text-center text-sm text-slate-500">No members found.</li>
        </ul>
        <div class="mt-4 flex justify-between text-sm">
          <button :disabled="!users.prev_page_url" class="text-slate-300 disabled:opacity-30" @click="users.prev_page_url && router.get(users.prev_page_url, {}, opts)">← Prev</button>
          <button :disabled="!users.next_page_url" class="text-slate-300 disabled:opacity-30" @click="users.next_page_url && router.get(users.next_page_url, {}, opts)">Next →</button>
        </div>
      </section>

      <!-- Groups manager -->
      <section class="rounded-2xl border border-white/5 bg-[#14172a] p-5">
        <h3 class="mb-3 text-sm font-bold text-white">Groups</h3>
        <div class="mb-4 space-y-2 rounded-xl border border-white/5 bg-[#0f1120] p-3">
          <input v-model="newGroup.name" :class="inp" class="w-full" placeholder="New group name" @keyup.enter="addGroup" />
          <div class="flex items-center gap-2">
            <input v-model="newGroup.color" type="color" class="h-9 w-10 rounded border-white/10 bg-transparent" />
            <label class="flex items-center gap-1.5 text-xs text-slate-400"><input v-model="newGroup.is_staff" type="checkbox" class="rounded border-white/10 bg-[#0f1120] text-indigo-500" /> Staff</label>
            <button class="ml-auto rounded-lg bg-indigo-500 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-600" @click="addGroup">Add</button>
          </div>
          <div class="space-y-1 pt-1">
            <div class="text-[11px] uppercase tracking-wide text-slate-500">Permissions</div>
            <label v-for="p in assignablePerms" :key="p.key" class="flex items-center gap-2 text-xs text-slate-300">
              <input v-model="newGroup.permissions" type="checkbox" :value="p.key" class="rounded border-white/10 bg-[#0f1120] text-indigo-500" /> {{ p.label }}
            </label>
            <p class="text-[11px] text-slate-500">All members can post, react &amp; edit their own content by default.</p>
          </div>
        </div>
        <ul class="space-y-2">
          <li v-for="g in groups" :key="g.id" class="rounded-xl border border-white/5 p-2.5">
            <template v-if="editGroupId === g.id">
              <input v-model="gbuf.name" :class="inp" class="w-full" />
              <div class="mt-2 flex items-center gap-2">
                <input v-model="gbuf.color" type="color" class="h-8 w-9 rounded border-white/10 bg-transparent" />
                <label class="flex items-center gap-1.5 text-xs text-slate-400"><input v-model="gbuf.is_staff" type="checkbox" class="rounded border-white/10 bg-[#0f1120] text-indigo-500" /> Staff</label>
                <button class="ml-auto rounded-lg bg-emerald-500 px-2.5 py-1 text-sm font-semibold text-white" @click="saveGroup">Save</button>
                <button class="text-sm text-slate-400" @click="editGroupId = null">Cancel</button>
              </div>
              <div class="mt-2 space-y-1">
                <label v-for="p in assignablePerms" :key="p.key" class="flex items-center gap-2 text-xs text-slate-300">
                  <input v-model="gbuf.permissions" type="checkbox" :value="p.key" class="rounded border-white/10 bg-[#0f1120] text-indigo-500" /> {{ p.label }}
                </label>
              </div>
            </template>
            <div v-else class="flex items-center gap-2">
              <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :style="{ color: g.color, background: g.color + '22' }">{{ g.name }}</span>
              <span v-if="g.is_staff" class="text-[10px] font-bold text-slate-500">STAFF</span>
              <button class="ml-auto text-slate-400 hover:text-white" @click="startGroup(g)">Edit</button>
              <button class="text-slate-400 hover:text-red-400" @click="delGroup(g)">Delete</button>
            </div>
          </li>
          <li v-if="!groups.length" class="text-sm text-slate-500">No groups yet.</li>
        </ul>
      </section>
    </div>

    <!-- Edit member modal -->
    <div v-if="editing" class="fixed inset-0 z-[80] flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-black/60" @click="editing = null"></div>
      <div class="relative w-full max-w-md rounded-2xl border border-white/10 bg-[#14172a] p-6">
        <h3 class="mb-4 text-lg font-bold text-white">Edit {{ editing.name }}</h3>
        <label class="block text-sm text-slate-300">Name</label>
        <input v-model="buf.name" :class="inp" class="mt-1 w-full" />
        <label class="mt-3 block text-sm text-slate-300">Email</label>
        <input v-model="buf.email" type="email" :class="inp" class="mt-1 w-full" />
        <label class="mt-3 flex items-center gap-2 text-sm text-slate-300">
          <input v-model="buf.is_admin" type="checkbox" class="rounded border-white/10 bg-[#0f1120] text-indigo-500" /> Administrator
        </label>
        <div class="mt-3">
          <div class="text-sm text-slate-300">Groups</div>
          <div class="mt-2 flex flex-wrap gap-2">
            <button v-for="g in groups" :key="g.id" type="button" class="rounded-full border px-2.5 py-1 text-xs font-semibold"
              :class="buf.groups.includes(g.id) ? 'border-indigo-500 bg-indigo-500/20 text-white' : 'border-white/10 text-slate-400'"
              @click="toggleGroup(g.id)">{{ g.name }}</button>
            <span v-if="!groups.length" class="text-xs text-slate-500">No groups — create one first.</span>
          </div>
        </div>
        <div class="mt-6 flex items-center gap-2">
          <button class="rounded-lg bg-indigo-500 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-600" @click="saveMember">Save</button>
          <button class="rounded-lg px-3 py-2 text-sm text-slate-400 hover:text-white" @click="editing = null">Cancel</button>
          <button class="ml-auto rounded-lg px-3 py-2 text-sm font-semibold text-red-400 hover:text-red-300" @click="deleteMember">Delete</button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
