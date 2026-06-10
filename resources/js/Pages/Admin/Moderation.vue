<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface Subject {
  kind: string; id: number; excerpt?: string; author?: string; authorId?: number;
  ip?: string | null; registrationIp?: string | null; topic?: string; topicSlug?: string; banned?: boolean;
}
interface Report { id: number; type: string; reason?: string; reporter?: string; createdAt?: string; subject: Subject | null }
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
function deletePost(id: number) { if (confirm('Delete this post?')) router.delete(`/admin/moderation/posts/${id}`, opt); }

function banUser(id: number, name?: string) {
  const reason = prompt(`Ban “${name}”? Optional reason:`, 'Spam');
  if (reason === null) return;
  const banIp = confirm('Also ban their last known IP address?');
  const deletePosts = confirm('Also delete all their replies?');
  router.post(`/admin/moderation/users/${id}/ban`, { reason, ban_ip: banIp, delete_posts: deletePosts }, opt);
}
function unbanUser(id: number) { router.post(`/admin/moderation/users/${id}/unban`, {}, opt); }

function banIpDirect(ip?: string | null) {
  if (!ip) return;
  const reason = prompt(`Ban IP ${ip}? Optional reason:`, '');
  if (reason === null) return;
  router.post('/admin/moderation/ip-bans', { ip_address: ip, reason }, opt);
}
const newIp = ref(''); const newIpReason = ref('');
function addIpBan() {
  if (!newIp.value.trim()) return;
  router.post('/admin/moderation/ip-bans', { ip_address: newIp.value.trim(), reason: newIpReason.value }, {
    ...opt, onSuccess: () => { newIp.value = ''; newIpReason.value = ''; },
  });
}
function unbanIp(id: number) { router.delete(`/admin/moderation/ip-bans/${id}`, opt); }
</script>

<template>
  <Head title="Admin · Moderation" />
  <AdminLayout>
    <template #title>Moderation</template>
    <template #subtitle>Reports, spam control, and IP bans</template>

    <!-- Tabs -->
    <div class="mb-5 flex gap-2">
      <button v-for="t in (['reports','users','ips'] as const)" :key="t" type="button" @click="tab = t"
        class="rounded-lg px-3.5 py-2 text-sm font-semibold capitalize transition"
        :class="tab === t ? 'bg-indigo-500 text-white' : 'bg-white/5 text-slate-300 hover:bg-white/10'">
        {{ t === 'ips' ? 'IP bans' : t === 'users' ? 'Banned users' : 'Reports' }}
        <span class="ml-1 rounded-full bg-black/20 px-1.5 text-[11px]">{{ t === 'reports' ? counts.reports : t === 'users' ? counts.bannedUsers : counts.ipBans }}</span>
      </button>
    </div>

    <!-- REPORTS -->
    <div v-if="tab === 'reports'" class="space-y-3">
      <div v-if="!reports.length" class="rounded-2xl border border-dashed border-white/10 p-10 text-center text-sm text-slate-400">
        Nothing in the queue. 🎉
      </div>
      <div v-for="r in reports" :key="r.id" class="rounded-2xl border border-white/5 bg-[#14172a] p-5">
        <div class="flex items-start gap-3">
          <span class="rounded bg-amber-500/15 px-2 py-0.5 text-[11px] font-semibold uppercase text-amber-300">{{ r.type }}</span>
          <div class="min-w-0 flex-1">
            <p v-if="r.subject?.excerpt" class="text-sm text-slate-200">“{{ r.subject.excerpt }}”</p>
            <p class="mt-1 text-xs text-slate-400">
              by <strong class="text-slate-300">{{ r.subject?.author || 'unknown' }}</strong>
              <template v-if="r.subject?.topic"> in <a :href="`/t/${r.subject.topicSlug}`" target="_blank" class="text-indigo-300 hover:underline">{{ r.subject.topic }}</a></template>
              <span v-if="r.subject?.ip" class="ml-2 rounded bg-white/5 px-1.5 py-0.5 font-mono text-[11px] text-slate-300">IP {{ r.subject.ip }}</span>
            </p>
            <p class="mt-1 text-xs text-slate-500">Reported by {{ r.reporter || 'a member' }} · {{ r.createdAt }}<span v-if="r.reason"> · “{{ r.reason }}”</span></p>
          </div>
        </div>
        <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-white/5 pt-3">
          <button v-if="r.type === 'post' && r.subject" @click="deletePost(r.subject.id)" class="rounded-lg bg-red-500/15 px-3 py-1.5 text-xs font-semibold text-red-300 hover:bg-red-500/25">Delete post</button>
          <button v-if="r.subject?.authorId" @click="banUser(r.subject.authorId, r.subject.author)" class="rounded-lg bg-red-500/15 px-3 py-1.5 text-xs font-semibold text-red-300 hover:bg-red-500/25">Ban author</button>
          <button v-if="r.subject?.ip" @click="banIpDirect(r.subject.ip)" class="rounded-lg bg-white/5 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-white/10">Ban IP</button>
          <span class="ml-auto"></span>
          <button @click="resolve(r)" class="rounded-lg bg-emerald-500/15 px-3 py-1.5 text-xs font-semibold text-emerald-300 hover:bg-emerald-500/25">Resolve</button>
          <button @click="dismiss(r)" class="rounded-lg px-3 py-1.5 text-xs font-semibold text-slate-400 hover:bg-white/5">Dismiss</button>
        </div>
      </div>
    </div>

    <!-- BANNED USERS -->
    <div v-else-if="tab === 'users'" class="space-y-2">
      <div v-if="!bannedUsers.length" class="rounded-2xl border border-dashed border-white/10 p-10 text-center text-sm text-slate-400">
        No banned users.
      </div>
      <div v-for="u in bannedUsers" :key="u.id" class="flex items-center gap-3 rounded-xl border border-white/5 bg-[#14172a] p-4">
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-2">
            <span class="font-semibold text-white">{{ u.name }}</span>
            <span class="truncate text-xs text-slate-500">{{ u.email }}</span>
          </div>
          <div class="mt-0.5 text-xs text-slate-500">
            Banned {{ u.bannedAt }}<span v-if="u.reason"> · {{ u.reason }}</span>
            <span v-if="u.lastIp" class="ml-2 font-mono">IP {{ u.lastIp }}</span>
          </div>
        </div>
        <button v-if="u.lastIp" @click="banIpDirect(u.lastIp)" class="rounded-lg bg-white/5 px-2.5 py-1.5 text-xs font-semibold text-slate-300 hover:bg-white/10">Ban IP</button>
        <button @click="unbanUser(u.id)" class="rounded-lg bg-emerald-500/15 px-2.5 py-1.5 text-xs font-semibold text-emerald-300 hover:bg-emerald-500/25">Reinstate</button>
      </div>
    </div>

    <!-- IP BANS -->
    <div v-else class="space-y-4">
      <div class="rounded-2xl border border-white/5 bg-[#14172a] p-5">
        <h2 class="mb-3 text-sm font-bold text-white">Ban an IP address</h2>
        <div class="flex flex-wrap items-end gap-3">
          <div><label class="block text-xs text-slate-400">IP address</label><input v-model="newIp" placeholder="203.0.113.5" class="mt-1 w-44 rounded-lg border-white/10 bg-[#0f1120] font-mono text-sm text-slate-100 focus:border-indigo-500 focus:ring-indigo-500" /></div>
          <div class="flex-1"><label class="block text-xs text-slate-400">Reason (optional)</label><input v-model="newIpReason" class="mt-1 w-full rounded-lg border-white/10 bg-[#0f1120] text-sm text-slate-100 focus:border-indigo-500 focus:ring-indigo-500" /></div>
          <button @click="addIpBan" :disabled="!newIp.trim()" class="rounded-lg bg-indigo-500 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-600 disabled:opacity-60">Ban IP</button>
        </div>
      </div>
      <div v-if="!ipBans.length" class="rounded-2xl border border-dashed border-white/10 p-10 text-center text-sm text-slate-400">No banned IPs.</div>
      <div v-for="b in ipBans" :key="b.id" class="flex items-center gap-3 rounded-xl border border-white/5 bg-[#14172a] p-4">
        <span class="font-mono text-sm text-slate-200">{{ b.ip }}</span>
        <span v-if="b.reason" class="text-xs text-slate-500">{{ b.reason }}</span>
        <span class="text-xs text-slate-600">· {{ b.createdAt }}</span>
        <button @click="unbanIp(b.id)" class="ml-auto rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-400 hover:bg-emerald-500/10 hover:text-emerald-300">Unban</button>
      </div>
    </div>
  </AdminLayout>
</template>
