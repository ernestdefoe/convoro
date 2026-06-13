<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { reactive, ref, watch } from 'vue';
import { t as tr } from '@/lib/i18n';
import { toast } from '@/lib/toast';

const props = defineProps<{
  users: { data: any[]; links: { url: string | null; label: string; active: boolean }[]; prev_page_url: string | null; next_page_url: string | null; total: number };
  q: string;
  groups: { id: number; key: string | null; name: string; color: string; is_staff: boolean; priority: number; permissions: string[] | null }[];
  permissionCatalog: { key: string; label: string; category: string; baseline: boolean }[];
  trustLevels: Record<number, string> | null;
}>();

// Trust ladder choices for the editor: Auto (let it self-manage) + each level.
const trustChoices = props.trustLevels
  ? [{ value: 'auto', label: tr('Auto') }, ...Object.entries(props.trustLevels).map(([v, l]) => ({ value: v, label: l as string }))]
  : [];

const assignablePerms = props.permissionCatalog.filter((p) => !p.baseline);
// The Admin badge follows the (editable) Admin group's color.
const adminColor = props.groups.find((g) => g.key === 'admin')?.color || '#6366f1';

const opts = { preserveScroll: true, preserveState: true };
const search = ref(props.q ?? '');
let t: any = null;
watch(search, (v) => {
  clearTimeout(t);
  t = setTimeout(() => router.get('/admin/members', { q: v }, { preserveState: true, replace: true }), 250);
});

// --- edit member ---
const editing = ref<any>(null);
const buf = reactive({ name: '', email: '', is_admin: false, groups: [] as number[], trust: 'auto' });
function edit(u: any) {
  editing.value = u;
  Object.assign(buf, {
    name: u.name, email: u.email, is_admin: u.is_admin, groups: u.groups.map((g: any) => g.id),
    trust: u.trust_locked ? String(u.trust_level) : 'auto',
  });
}
function toggleGroup(id: number) {
  const i = buf.groups.indexOf(id);
  if (i === -1) buf.groups.push(id); else buf.groups.splice(i, 1);
}
function saveMember() {
  router.put(`/admin/members/${editing.value.id}`, { ...buf }, { ...opts, onSuccess: () => (editing.value = null) });
}

// --- IP tools (view / look up / ban) ---
const ipInfo = reactive<Record<string, any>>({});
const ipBusy = ref('');
async function lookupIp(ip: string) {
  if (!ip || ipBusy.value) return;
  ipBusy.value = ip;
  try {
    const r = await fetch('/admin/ip-lookup?ip=' + encodeURIComponent(ip), { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
    const d = await r.json();
    ipInfo[ip] = d.info || { error: true };
  } catch { ipInfo[ip] = { error: true }; }
  finally { ipBusy.value = ''; }
}
function banIp(ip: string) {
  if (!ip) return;
  const reason = window.prompt(tr('Ban IP {ip}? Optional reason:', { ip }), tr('Abuse'));
  if (reason === null) return;
  router.post('/admin/moderation/ip-bans', { ip_address: ip, reason }, { ...opts, onSuccess: () => toast(tr('IP banned.')) });
}
function anonymizeMember() {
  if (!confirm(tr('Anonymize {name}? This permanently erases their name, email, avatar, bio and stored IP addresses, but keeps their posts and topics (shown as “Deleted user”). This cannot be undone.', { name: editing.value.name }))) return;
  router.post(`/admin/members/${editing.value.id}/anonymize`, {}, { ...opts, onSuccess: () => (editing.value = null) });
}
function deleteMember() {
  if (!confirm(tr('Permanently delete {name} AND all of their content? Their topics — including every reply inside them — plus their posts, reactions and messages will be removed. This cannot be undone. To keep discussions intact, use Anonymize instead.', { name: editing.value.name }))) return;
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
function delGroup(g: any) { if (confirm(tr('Delete group “{name}”?', { name: g.name }))) router.delete(`/admin/groups/${g.id}`, opts); }

const inp = 'rounded-lg border-line bg-appbg text-sm text-ink focus:border-indigo-500 focus:ring-indigo-500';
</script>

<template>
  <Head :title="tr('Admin · Members')" />
  <AdminLayout>
    <template #title>{{ tr('Members') }}</template>
    <template #subtitle>{{ tr('{n} members', { n: users.total }) }}</template>

    <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
      <!-- Members list -->
      <section class="rounded-2xl border border-line bg-surface p-5">
        <input v-model="search" type="text" :placeholder="tr('Search name or email…')" :class="inp" class="mb-4 w-full" />
        <ul class="divide-y divide-line">
          <li v-for="u in users.data" :key="u.id" class="flex items-center gap-3 py-3">
            <img v-if="u.avatar" :src="u.avatar" class="h-9 w-9 rounded-full object-cover" alt="" />
            <span v-else class="grid h-9 w-9 place-items-center rounded-full bg-indigo-500/30 text-xs font-bold text-indigo-200">{{ u.initials }}</span>
            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-2">
                <span class="truncate font-semibold text-ink">{{ u.name }}</span>
                <span v-if="u.is_admin" class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase text-white shadow-sm" :style="{ background: adminColor }">{{ tr('ADMIN') }}</span>
                <span v-for="g in u.groups" :key="g.id" class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase text-white shadow-sm" :style="{ background: g.color }">{{ g.name }}</span>
                <span v-if="trustLevels && !u.is_admin" class="rounded-full px-1.5 py-0.5 text-[10px] font-semibold"
                  :class="u.trust_level >= 4 ? 'bg-amber-400/20 text-amber-500' : u.trust_level >= 1 ? 'bg-primary/15 text-primary' : 'bg-ink/10 text-ink-muted'"
                  :title="u.trust_locked ? tr('Trust level pinned by an admin') : tr('Trust level (auto)')">
                  {{ trustLevels[u.trust_level] || ('L' + u.trust_level) }}<span v-if="u.trust_locked"> 📌</span>
                </span>
              </div>
              <div class="truncate text-xs text-ink-muted">{{ u.email }} · {{ tr('joined {when}', { when: u.joined }) }}</div>
            </div>
            <button class="text-sm font-semibold text-indigo-300 hover:text-indigo-200" @click="edit(u)">{{ tr('Edit') }}</button>
          </li>
          <li v-if="!users.data.length" class="py-6 text-center text-sm text-ink-muted">{{ tr('No members found.') }}</li>
        </ul>
        <div class="mt-4 flex justify-between text-sm">
          <button :disabled="!users.prev_page_url" class="text-ink-2 disabled:opacity-30" @click="users.prev_page_url && router.get(users.prev_page_url, {}, opts)">{{ tr('← Prev') }}</button>
          <button :disabled="!users.next_page_url" class="text-ink-2 disabled:opacity-30" @click="users.next_page_url && router.get(users.next_page_url, {}, opts)">{{ tr('Next →') }}</button>
        </div>
      </section>

      <!-- Groups manager -->
      <section class="rounded-2xl border border-line bg-surface p-5">
        <h3 class="mb-3 text-sm font-bold text-ink">{{ tr('Groups') }}</h3>
        <div class="mb-4 space-y-2 rounded-xl border border-line bg-appbg p-3">
          <input v-model="newGroup.name" :class="inp" class="w-full" :placeholder="tr('New group name')" @keyup.enter="addGroup" />
          <div class="flex items-center gap-2">
            <input v-model="newGroup.color" type="color" class="h-9 w-10 rounded border-line bg-transparent" />
            <label class="flex items-center gap-1.5 text-xs text-ink-2"><input v-model="newGroup.is_staff" type="checkbox" class="rounded border-line bg-appbg text-indigo-500" /> {{ tr('Staff') }}</label>
            <button class="ml-auto rounded-lg bg-indigo-500 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-600" @click="addGroup">{{ tr('Add') }}</button>
          </div>
          <div class="space-y-1 pt-1">
            <div class="text-[11px] uppercase tracking-wide text-ink-muted">{{ tr('Permissions') }}</div>
            <label v-for="p in assignablePerms" :key="p.key" class="flex items-center gap-2 text-xs text-ink-2">
              <input v-model="newGroup.permissions" type="checkbox" :value="p.key" class="rounded border-line bg-appbg text-indigo-500" /> {{ p.label }}
            </label>
            <p class="text-[11px] text-ink-muted">{{ tr('All members can post, react & edit their own content by default.') }}</p>
          </div>
        </div>
        <ul class="space-y-2">
          <li v-for="g in groups" :key="g.id" class="rounded-xl border border-line p-2.5">
            <template v-if="editGroupId === g.id">
              <input v-model="gbuf.name" :class="inp" class="w-full" />
              <div class="mt-2 flex items-center gap-2">
                <input v-model="gbuf.color" type="color" class="h-8 w-9 rounded border-line bg-transparent" />
                <label class="flex items-center gap-1.5 text-xs text-ink-2"><input v-model="gbuf.is_staff" type="checkbox" class="rounded border-line bg-appbg text-indigo-500" /> {{ tr('Staff') }}</label>
                <button class="ml-auto rounded-lg bg-emerald-500 px-2.5 py-1 text-sm font-semibold text-white" @click="saveGroup">{{ tr('Save') }}</button>
                <button class="text-sm text-ink-2" @click="editGroupId = null">{{ tr('Cancel') }}</button>
              </div>
              <div class="mt-2 space-y-1">
                <label v-for="p in assignablePerms" :key="p.key" class="flex items-center gap-2 text-xs text-ink-2">
                  <input v-model="gbuf.permissions" type="checkbox" :value="p.key" class="rounded border-line bg-appbg text-indigo-500" /> {{ p.label }}
                </label>
              </div>
            </template>
            <div v-else class="flex items-center gap-2">
              <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :style="{ color: g.color, background: g.color + '22' }">{{ g.name }}</span>
              <span v-if="g.is_staff" class="text-[10px] font-bold text-ink-muted">{{ tr('STAFF') }}</span>
              <span v-if="g.key" class="text-[10px] font-bold text-ink-muted">{{ tr('SYSTEM') }}</span>
              <button class="ml-auto text-ink-2 hover:text-ink" @click="startGroup(g)">{{ tr('Edit') }}</button>
              <button v-if="!g.key" class="text-ink-2 hover:text-red-400" @click="delGroup(g)">{{ tr('Delete') }}</button>
            </div>
          </li>
          <li v-if="!groups.length" class="text-sm text-ink-muted">{{ tr('No groups yet.') }}</li>
        </ul>
      </section>
    </div>

    <!-- Edit member modal -->
    <div v-if="editing" class="fixed inset-0 z-[80] flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-black/60" @click="editing = null"></div>
      <div class="relative w-full max-w-md rounded-2xl border border-line bg-surface p-6">
        <h3 class="mb-4 text-lg font-bold text-ink">{{ tr('Edit {name}', { name: editing.name }) }}</h3>
        <label class="block text-sm text-ink-2">{{ tr('Name') }}</label>
        <input v-model="buf.name" :class="inp" class="mt-1 w-full" />
        <label class="mt-3 block text-sm text-ink-2">{{ tr('Email') }}</label>
        <input v-model="buf.email" type="email" :class="inp" class="mt-1 w-full" />
        <label class="mt-3 flex items-center gap-2 text-sm text-ink-2">
          <input v-model="buf.is_admin" type="checkbox" class="rounded border-line bg-appbg text-indigo-500" /> {{ tr('Administrator') }}
        </label>
        <div class="mt-3">
          <div class="text-sm text-ink-2">{{ tr('Groups') }}</div>
          <div class="mt-2 flex flex-wrap gap-2">
            <button v-for="g in groups" :key="g.id" type="button" class="rounded-full border px-2.5 py-1 text-xs font-semibold"
              :class="buf.groups.includes(g.id) ? 'border-indigo-500 bg-indigo-500/20 text-ink' : 'border-line text-ink-2'"
              @click="toggleGroup(g.id)">{{ g.name }}</button>
            <span v-if="!groups.length" class="text-xs text-ink-muted">{{ tr('No groups — create one first.') }}</span>
          </div>
        </div>
        <div v-if="trustChoices.length" class="mt-3">
          <label class="block text-sm text-ink-2">{{ tr('Trust level') }}</label>
          <select v-model="buf.trust" :class="inp" class="mt-1 w-full">
            <option v-for="c in trustChoices" :key="c.value" :value="c.value">{{ c.label }}</option>
          </select>
          <p class="mt-1 text-xs text-ink-muted">{{ tr('“Auto” lets the member earn their level by participating. Choosing a level pins it (e.g. grant Leader, or reset a problem account to New).') }}</p>
        </div>

        <!-- IP addresses: view, look up, ban -->
        <div class="mt-4 rounded-xl border border-line bg-appbg/60 p-3">
          <div class="text-sm font-semibold text-ink-2">{{ tr('IP addresses') }}</div>
          <div v-for="row in [{ label: tr('Last seen'), ip: editing.last_ip }, { label: tr('Registered'), ip: editing.reg_ip }]" :key="row.label" class="mt-2">
            <template v-if="row.ip">
              <div class="flex flex-wrap items-center gap-2 text-sm">
                <span class="text-xs text-ink-muted">{{ row.label }}:</span>
                <span class="font-mono text-ink">{{ row.ip }}</span>
                <button type="button" class="rounded-md border border-line px-2 py-0.5 text-xs font-semibold text-ink-2 hover:bg-surface-2 disabled:opacity-50" :disabled="ipBusy === row.ip" @click="lookupIp(row.ip)">{{ ipBusy === row.ip ? tr('…') : tr('Look up') }}</button>
                <button type="button" class="rounded-md border border-red-500/40 px-2 py-0.5 text-xs font-semibold text-red-500 hover:bg-red-500/10" @click="banIp(row.ip)">{{ tr('Ban IP') }}</button>
              </div>
              <div v-if="ipInfo[row.ip]" class="mt-1 ml-1 text-xs text-ink-muted">
                <template v-if="ipInfo[row.ip].error">{{ tr('Lookup unavailable.') }}</template>
                <template v-else>
                  📍 {{ ipInfo[row.ip].location || tr('Unknown') }} · {{ ipInfo[row.ip].isp }}
                  <span v-if="ipInfo[row.ip].flags?.length" class="font-semibold text-amber-600"> · ⚠ {{ ipInfo[row.ip].flags.join(', ') }}</span>
                </template>
              </div>
            </template>
            <div v-else class="text-xs text-ink-muted">{{ row.label }}: {{ tr('not recorded') }}</div>
          </div>
        </div>

        <div class="mt-6 flex items-center gap-2">
          <button class="rounded-lg bg-indigo-500 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-600" @click="saveMember">{{ tr('Save') }}</button>
          <button class="rounded-lg px-3 py-2 text-sm text-ink-2 hover:text-ink" @click="editing = null">{{ tr('Cancel') }}</button>
        </div>

        <!-- GDPR / account deletion -->
        <div class="mt-6 rounded-xl border border-line bg-appbg/60 p-4">
          <div class="flex items-center gap-2 text-sm font-bold text-ink">
            <svg viewBox="0 0 20 20" class="h-4 w-4 text-ink-2" fill="currentColor"><path d="M10 1l7 3v5c0 4.4-3 8.3-7 10-4-1.7-7-5.6-7-10V4l7-3z"/></svg>
            {{ tr('Data & privacy (GDPR)') }}
          </div>
          <p class="mt-1.5 text-xs text-ink-muted">{{ tr('Honour a “delete my account” request. Anonymizing erases personal data but keeps the community’s discussions readable — the recommended option.') }}</p>
          <div class="mt-3 flex flex-wrap gap-2">
            <button class="rounded-lg border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-sm font-semibold text-amber-600 hover:bg-amber-500/20 dark:text-amber-400" @click="anonymizeMember">{{ tr('Anonymize account') }}</button>
            <button class="rounded-lg border border-red-500/40 bg-red-500/10 px-3 py-2 text-sm font-semibold text-red-500 hover:bg-red-500/20 dark:text-red-400" @click="deleteMember">{{ tr('Delete account & content') }}</button>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
