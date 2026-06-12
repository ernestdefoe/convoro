<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t as tr } from '@/lib/i18n';

interface Subject {
  kind: string; id: number; excerpt?: string; author?: string; authorId?: number;
  ip?: string | null; registrationIp?: string | null; topic?: string; topicSlug?: string; banned?: boolean;
}
interface Report { id: number; type: string; reason?: string; reporter?: string; createdAt?: string; subject: Subject | null; source?: string; ai?: { risk?: number; spam?: number; toxicity?: number; pii?: boolean; labels?: string[]; reason?: string } | null }
interface BannedUser { id: number; name: string; email: string; reason?: string; lastIp?: string; registrationIp?: string; bannedAt?: string }
interface IpBan { id: number; ip: string; reason?: string; createdAt?: string }

defineProps<{
  reports: Report[];
  bannedUsers: BannedUser[];
  ipBans: IpBan[];
  categories: { id: number; name: string }[];
  counts: { reports: number; bannedUsers: number; ipBans: number };
}>();

const tab = ref<'reports' | 'users' | 'ips'>('reports');
const opt = { preserveScroll: true };

function resolve(r: Report) { router.post(`/admin/moderation/reports/${r.id}/resolve`, {}, opt); }
function dismiss(r: Report) { router.post(`/admin/moderation/reports/${r.id}/dismiss`, {}, opt); }
function approve(r: Report) { if (r.subject) router.post(`/admin/moderation/posts/${r.subject.id}/approve`, {}, opt); }

// Generic confirm modal (destructive yes/no).
const confirmModal = ref<{ title: string; body: string; danger?: boolean; action: () => void } | null>(null);
function askConfirm(title: string, body: string, action: () => void, danger = true) {
  confirmModal.value = { title, body, danger, action };
}
function runConfirm() { const m = confirmModal.value; confirmModal.value = null; m?.action(); }

function deletePost(id: number) {
  askConfirm(tr('Delete post'), tr('This permanently removes the post. This cannot be undone.'), () =>
    router.delete(`/admin/moderation/posts/${id}`, opt));
}

// Ban-user modal (reason + options).
const banModal = ref<{ id: number; name: string } | null>(null);
const banForm = reactive({ reason: tr('Spam'), ban_ip: true, delete_posts: false });
function openBan(id: number, name?: string) {
  banForm.reason = tr('Spam'); banForm.ban_ip = true; banForm.delete_posts = false;
  banModal.value = { id, name: name || tr('this member') };
}
function confirmBan() {
  const m = banModal.value; if (!m) return;
  router.post(`/admin/moderation/users/${m.id}/ban`, { ...banForm }, { ...opt, onSuccess: () => (banModal.value = null) });
}
function unbanUser(id: number) { router.post(`/admin/moderation/users/${id}/unban`, {}, opt); }

// Ban-IP modal (reason; ip prefilled).
const ipModal = ref<{ ip: string } | null>(null);
const ipModalReason = ref('');
function openBanIp(ip?: string | null) { if (!ip) return; ipModalReason.value = ''; ipModal.value = { ip }; }
function confirmBanIp() {
  const m = ipModal.value; if (!m) return;
  router.post('/admin/moderation/ip-bans', { ip_address: m.ip, reason: ipModalReason.value }, { ...opt, onSuccess: () => (ipModal.value = null) });
}

const newIp = ref(''); const newIpReason = ref('');
function addIpBan() {
  if (!newIp.value.trim()) return;
  router.post('/admin/moderation/ip-bans', { ip_address: newIp.value.trim(), reason: newIpReason.value }, {
    ...opt, onSuccess: () => { newIp.value = ''; newIpReason.value = ''; },
  });
}
function unbanIp(id: number) {
  askConfirm(tr('Unban IP'), tr('Allow this IP address to access the community again?'), () =>
    router.delete(`/admin/moderation/ip-bans/${id}`, opt), false);
}
</script>

<template>
  <Head :title="tr('Admin · Moderation')" />
  <AdminLayout>
    <template #title>{{ tr('Moderation') }}</template>
    <template #subtitle>{{ tr('Reports, spam control, and IP bans') }}</template>

    <!-- Tabs -->
    <div class="mb-5 flex gap-2">
      <button v-for="t in (['reports','users','ips'] as const)" :key="t" type="button" @click="tab = t"
        class="rounded-lg px-3.5 py-2 text-sm font-semibold capitalize transition"
        :class="tab === t ? 'bg-indigo-500 text-white' : 'bg-surface-2 text-white-2 hover:bg-surface-2'">
        {{ t === 'ips' ? tr('IP bans') : t === 'users' ? tr('Banned users') : tr('Reports') }}
        <span class="ml-1 rounded-full bg-black/20 px-1.5 text-[11px]">{{ t === 'reports' ? counts.reports : t === 'users' ? counts.bannedUsers : counts.ipBans }}</span>
      </button>
    </div>

    <!-- REPORTS -->
    <div v-if="tab === 'reports'" class="space-y-3">
      <div v-if="!reports.length" class="rounded-2xl border border-dashed border-line p-10 text-center text-sm text-ink-2">
        {{ tr('Nothing in the queue. 🎉') }}
      </div>
      <div v-for="r in reports" :key="r.id" class="rounded-2xl border border-line bg-surface p-5">
        <div class="flex items-start gap-3">
          <span class="rounded bg-amber-500/15 px-2 py-0.5 text-[11px] font-semibold uppercase text-amber-300">{{ r.type }}</span>
          <span v-if="r.source === 'ai'" class="inline-flex items-center gap-1 rounded bg-primary/15 px-2 py-0.5 text-[11px] font-semibold uppercase text-primary">
            <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 3v3M12 18v3M3 12h3M18 12h3"/></svg>{{ tr('AI') }}
          </span>
          <div class="min-w-0 flex-1">
            <div v-if="r.ai" class="mb-1 flex flex-wrap gap-1.5">
              <span v-if="(r.ai.risk ?? 0) >= 0.85" class="rounded bg-red-500/15 px-1.5 py-0.5 text-[11px] font-semibold text-red-300">{{ tr('Held') }} · {{ tr('risk {n}%', { n: Math.round((r.ai.risk ?? 0) * 100) }) }}</span>
              <span v-else class="rounded bg-amber-500/15 px-1.5 py-0.5 text-[11px] font-semibold text-amber-300">{{ tr('risk {n}%', { n: Math.round((r.ai.risk ?? 0) * 100) }) }}</span>
              <span v-for="l in (r.ai.labels || [])" :key="l" class="rounded bg-surface-2 px-1.5 py-0.5 text-[11px] font-semibold text-ink-2">{{ l }}</span>
            </div>
            <p v-if="r.subject?.excerpt" class="text-sm text-ink">“{{ r.subject.excerpt }}”</p>
            <p class="mt-1 text-xs text-ink-2">
              {{ tr('by') }} <strong class="text-ink-2">{{ r.subject?.author || tr('unknown') }}</strong>
              <template v-if="r.subject?.topic"> {{ tr('in') }} <a :href="`/t/${r.subject.topicSlug}`" target="_blank" class="text-indigo-300 hover:underline">{{ r.subject.topic }}</a></template>
              <span v-if="r.subject?.ip" class="ml-2 rounded bg-surface-2 px-1.5 py-0.5 font-mono text-[11px] text-ink-2">{{ tr('IP {ip}', { ip: r.subject.ip }) }}</span>
            </p>
            <p class="mt-1 text-xs text-ink-muted">{{ tr('Reported by {who}', { who: r.reporter || tr('a member') }) }} · {{ r.createdAt }}<span v-if="r.reason"> · “{{ r.reason }}”</span></p>
          </div>
        </div>
        <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-line pt-3">
          <button v-if="r.type === 'post' && r.subject" @click="deletePost(r.subject.id)" class="rounded-lg bg-red-500/15 px-3 py-1.5 text-xs font-semibold text-red-300 hover:bg-red-500/25">{{ tr('Delete post') }}</button>
          <button v-if="r.subject?.authorId" @click="openBan(r.subject.authorId, r.subject.author)" class="rounded-lg bg-red-500/15 px-3 py-1.5 text-xs font-semibold text-red-300 hover:bg-red-500/25">{{ tr('Ban author') }}</button>
          <button v-if="r.subject?.ip" @click="openBanIp(r.subject.ip)" class="rounded-lg bg-surface-2 px-3 py-1.5 text-xs font-semibold text-ink-2 hover:bg-surface-2">{{ tr('Ban IP') }}</button>
          <button v-if="r.source === 'ai' && r.type === 'post' && r.subject" @click="approve(r)" class="rounded-lg bg-emerald-500/15 px-3 py-1.5 text-xs font-semibold text-emerald-300 hover:bg-emerald-500/25">{{ tr('Approve & restore') }}</button>
          <span class="ml-auto"></span>
          <button @click="resolve(r)" class="rounded-lg bg-emerald-500/15 px-3 py-1.5 text-xs font-semibold text-emerald-300 hover:bg-emerald-500/25">{{ tr('Resolve') }}</button>
          <button @click="dismiss(r)" class="rounded-lg px-3 py-1.5 text-xs font-semibold text-ink-2 hover:bg-surface-2">{{ tr('Dismiss') }}</button>
        </div>
      </div>
    </div>

    <!-- BANNED USERS -->
    <div v-else-if="tab === 'users'" class="space-y-2">
      <div v-if="!bannedUsers.length" class="rounded-2xl border border-dashed border-line p-10 text-center text-sm text-ink-2">
        {{ tr('No banned users.') }}
      </div>
      <div v-for="u in bannedUsers" :key="u.id" class="flex items-center gap-3 rounded-xl border border-line bg-surface p-4">
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-2">
            <span class="font-semibold text-ink">{{ u.name }}</span>
            <span class="truncate text-xs text-ink-muted">{{ u.email }}</span>
          </div>
          <div class="mt-0.5 text-xs text-ink-muted">
            {{ tr('Banned {when}', { when: u.bannedAt ?? '' }) }}<span v-if="u.reason"> · {{ u.reason }}</span>
            <span v-if="u.lastIp" class="ml-2 font-mono">{{ tr('IP {ip}', { ip: u.lastIp }) }}</span>
          </div>
        </div>
        <button v-if="u.lastIp" @click="openBanIp(u.lastIp)" class="rounded-lg bg-surface-2 px-2.5 py-1.5 text-xs font-semibold text-ink-2 hover:bg-surface-2">{{ tr('Ban IP') }}</button>
        <button @click="unbanUser(u.id)" class="rounded-lg bg-emerald-500/15 px-2.5 py-1.5 text-xs font-semibold text-emerald-300 hover:bg-emerald-500/25">{{ tr('Reinstate') }}</button>
      </div>
    </div>

    <!-- IP BANS -->
    <div v-else class="space-y-4">
      <div class="rounded-2xl border border-line bg-surface p-5">
        <h2 class="mb-3 text-sm font-bold text-ink">{{ tr('Ban an IP address') }}</h2>
        <div class="flex flex-wrap items-end gap-3">
          <div><label class="block text-xs text-ink-2">{{ tr('IP address') }}</label><input v-model="newIp" placeholder="203.0.113.5" class="mt-1 w-44 rounded-lg border-line bg-appbg font-mono text-sm text-ink focus:border-indigo-500 focus:ring-indigo-500" /></div>
          <div class="flex-1"><label class="block text-xs text-ink-2">{{ tr('Reason (optional)') }}</label><input v-model="newIpReason" class="mt-1 w-full rounded-lg border-line bg-appbg text-sm text-ink focus:border-indigo-500 focus:ring-indigo-500" /></div>
          <button @click="addIpBan" :disabled="!newIp.trim()" class="rounded-lg bg-indigo-500 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-600 disabled:opacity-60">{{ tr('Ban IP') }}</button>
        </div>
      </div>
      <div v-if="!ipBans.length" class="rounded-2xl border border-dashed border-line p-10 text-center text-sm text-ink-2">{{ tr('No banned IPs.') }}</div>
      <div v-for="b in ipBans" :key="b.id" class="flex items-center gap-3 rounded-xl border border-line bg-surface p-4">
        <span class="font-mono text-sm text-ink">{{ b.ip }}</span>
        <span v-if="b.reason" class="text-xs text-ink-muted">{{ b.reason }}</span>
        <span class="text-xs text-ink-muted">· {{ b.createdAt }}</span>
        <button @click="unbanIp(b.id)" class="ml-auto rounded-lg px-2.5 py-1.5 text-xs font-semibold text-ink-2 hover:bg-emerald-500/10 hover:text-emerald-300">{{ tr('Unban') }}</button>
      </div>
    </div>

    <!-- Ban-user modal -->
    <div v-if="banModal" class="fixed inset-0 z-[80] flex items-center justify-center p-4" @keydown.esc="banModal = null">
      <div class="absolute inset-0 bg-black/60" @click="banModal = null" />
      <div class="relative w-full max-w-md rounded-2xl border border-line bg-surface p-6 shadow-2xl">
        <h3 class="text-lg font-bold text-ink">{{ tr('Ban {name}', { name: banModal.name }) }}</h3>
        <p class="mt-1 text-sm text-ink-2">{{ tr("They'll be signed out and blocked from posting or registering.") }}</p>
        <label class="mt-4 block text-xs font-semibold uppercase tracking-wide text-ink-2">{{ tr('Reason') }}</label>
        <input v-model="banForm.reason" class="mt-1.5 w-full rounded-lg border-line bg-appbg text-sm text-ink focus:border-indigo-500 focus:ring-indigo-500" />
        <label class="mt-3 flex items-center gap-2.5 text-sm text-ink">
          <input type="checkbox" v-model="banForm.ban_ip" class="rounded border-line bg-appbg text-indigo-500 focus:ring-indigo-500" />
          {{ tr('Also ban their last known IP address') }}
        </label>
        <label class="mt-2 flex items-center gap-2.5 text-sm text-ink">
          <input type="checkbox" v-model="banForm.delete_posts" class="rounded border-line bg-appbg text-indigo-500 focus:ring-indigo-500" />
          {{ tr('Also delete all their replies') }}
        </label>
        <div class="mt-5 flex justify-end gap-2">
          <button @click="banModal = null" class="rounded-lg px-4 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2">{{ tr('Cancel') }}</button>
          <button @click="confirmBan" class="rounded-lg bg-red-500 px-4 py-2 text-sm font-semibold text-white hover:bg-red-600">{{ tr('Ban member') }}</button>
        </div>
      </div>
    </div>

    <!-- Ban-IP modal -->
    <div v-if="ipModal" class="fixed inset-0 z-[80] flex items-center justify-center p-4" @keydown.esc="ipModal = null">
      <div class="absolute inset-0 bg-black/60" @click="ipModal = null" />
      <div class="relative w-full max-w-md rounded-2xl border border-line bg-surface p-6 shadow-2xl">
        <h3 class="text-lg font-bold text-ink">{{ tr('Ban IP') }} <span class="font-mono text-indigo-300">{{ ipModal.ip }}</span></h3>
        <p class="mt-1 text-sm text-ink-2">{{ tr('Requests from this address will be blocked.') }}</p>
        <label class="mt-4 block text-xs font-semibold uppercase tracking-wide text-ink-2">{{ tr('Reason (optional)') }}</label>
        <input v-model="ipModalReason" class="mt-1.5 w-full rounded-lg border-line bg-appbg text-sm text-ink focus:border-indigo-500 focus:ring-indigo-500" />
        <div class="mt-5 flex justify-end gap-2">
          <button @click="ipModal = null" class="rounded-lg px-4 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2">{{ tr('Cancel') }}</button>
          <button @click="confirmBanIp" class="rounded-lg bg-red-500 px-4 py-2 text-sm font-semibold text-white hover:bg-red-600">{{ tr('Ban IP') }}</button>
        </div>
      </div>
    </div>

    <!-- Generic confirm modal -->
    <div v-if="confirmModal" class="fixed inset-0 z-[80] flex items-center justify-center p-4" @keydown.esc="confirmModal = null">
      <div class="absolute inset-0 bg-black/60" @click="confirmModal = null" />
      <div class="relative w-full max-w-sm rounded-2xl border border-line bg-surface p-6 shadow-2xl">
        <h3 class="text-lg font-bold text-ink">{{ confirmModal.title }}</h3>
        <p class="mt-1.5 text-sm text-ink-2">{{ confirmModal.body }}</p>
        <div class="mt-5 flex justify-end gap-2">
          <button @click="confirmModal = null" class="rounded-lg px-4 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2">{{ tr('Cancel') }}</button>
          <button @click="runConfirm" class="rounded-lg px-4 py-2 text-sm font-semibold text-white" :class="confirmModal.danger ? 'bg-red-500 hover:bg-red-600' : 'bg-indigo-500 hover:bg-indigo-600'">{{ tr('Confirm') }}</button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
