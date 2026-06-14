<script setup lang="ts">
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { t as tr } from '@/lib/i18n';

const props = defineProps<{
  logs: { data: any[]; prev_page_url: string | null; next_page_url: string | null; total: number };
  q: string;
  action: string;
  actions: string[];
}>();

const search = ref(props.q ?? '');
const action = ref(props.action ?? '');
const opts = { preserveState: true, replace: true, preserveScroll: true };

function apply() {
  router.get('/admin/audit-log', { q: search.value || undefined, action: action.value || undefined }, opts);
}
let t: any;
watch(search, () => { clearTimeout(t); t = setTimeout(apply, 250); });
watch(action, apply);

const AV = ['', '#6366f1', '#0ea5e9', '#10b981', '#f59e0b', '#ef4444', '#ec4899'];

// Friendly verb + colour tone per action key.
const LABELS: Record<string, string> = {
  'post.delete': 'Deleted post', 'post.approve': 'Approved post', 'post.move': 'Moved post',
  'topic.move': 'Moved topic', 'topic.pin': 'Pinned topic', 'topic.unpin': 'Unpinned topic',
  'topic.lock': 'Locked topic', 'topic.unlock': 'Unlocked topic',
  'user.ban': 'Banned member', 'user.unban': 'Reinstated member',
  'ip.ban': 'Banned IP', 'ip.unban': 'Unbanned IP',
  'report.resolve': 'Resolved report', 'report.dismiss': 'Dismissed report',
  'member.update': 'Edited member', 'member.anonymize': 'Anonymized member', 'member.delete': 'Deleted member',
  'extension.install': 'Installed extension', 'extension.enable': 'Enabled extension',
  'extension.disable': 'Disabled extension', 'extension.uninstall': 'Removed extension',
  'group.create': 'Created group', 'group.update': 'Edited group', 'group.delete': 'Deleted group',
};
const label = (a: string) => tr(LABELS[a] ?? a);

const DESTRUCTIVE = ['post.delete', 'user.ban', 'ip.ban', 'member.delete', 'member.anonymize', 'topic.lock', 'extension.uninstall', 'extension.disable', 'group.delete'];
const POSITIVE = ['post.approve', 'user.unban', 'ip.unban', 'topic.unlock', 'report.resolve', 'extension.enable', 'extension.install'];
function tone(a: string) {
  if (DESTRUCTIVE.includes(a)) return 'bg-rose-500/15 text-rose-600 dark:text-rose-400';
  if (POSITIVE.includes(a)) return 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400';
  return 'bg-indigo-500/15 text-indigo-600 dark:text-indigo-400';
}

// Render the few meta keys worth surfacing as small chips.
function metaChips(meta: any): string[] {
  if (!meta || typeof meta !== 'object') return [];
  const out: string[] = [];
  if (meta.admin) out.push(meta.admin === 'promoted' ? tr('→ admin') : tr('→ not admin'));
  if (meta.ban_ip) out.push(tr('+ IP ban'));
  if (meta.deleted_posts) out.push(tr('+ wiped posts'));
  if (meta.trust && meta.trust !== 'auto') out.push(tr('trust {n}', { n: meta.trust }));
  if (meta.posts != null) out.push(tr('{n} posts', { n: meta.posts }));
  if (meta.topics != null) out.push(tr('{n} topics', { n: meta.topics }));
  if (meta.groups_changed) out.push(tr('groups changed'));
  return out;
}
const fullTime = (iso: string) => (iso ? new Date(iso).toLocaleString() : '');
</script>

<template>
  <Head :title="tr('Audit log')" />
  <AdminLayout>
    <div class="mx-auto max-w-5xl">
      <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 class="text-2xl font-black tracking-tight">{{ tr('Audit log') }}</h1>
          <p class="mt-1 text-sm text-ink-2">{{ tr('Every moderation and admin action, newest first.') }}</p>
        </div>
        <span class="text-xs text-ink-muted">{{ tr('{n} entries', { n: logs.total }) }}</span>
      </div>

      <!-- Filters -->
      <div class="mt-5 flex flex-wrap gap-2">
        <input v-model="search" type="search" :placeholder="tr('Search staff, target or reason…')"
          class="min-w-[220px] flex-1 rounded-lg border border-line bg-surface px-3 py-2 text-sm" />
        <select v-model="action" class="rounded-lg border border-line bg-surface px-3 py-2 text-sm">
          <option value="">{{ tr('All actions') }}</option>
          <option v-for="a in actions" :key="a" :value="a">{{ label(a) }}</option>
        </select>
      </div>

      <!-- Entries -->
      <div class="mt-4 overflow-hidden rounded-xl border border-line bg-surface">
        <ul class="divide-y divide-line">
          <li v-for="l in logs.data" :key="l.id" class="flex items-start gap-3 px-4 py-3">
            <!-- actor -->
            <a :href="l.actor.id ? `/u/${l.actor.id}` : undefined" class="mt-0.5 shrink-0">
              <img v-if="l.actor.avatar" :src="l.actor.avatar" :alt="l.actor.name" class="h-8 w-8 rounded-full object-cover" />
              <span v-else class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold text-white"
                :style="{ background: AV[l.actor.color] || AV[1] }">{{ l.actor.initials }}</span>
            </a>

            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                <span class="font-semibold text-ink">{{ l.actor.name }}</span>
                <span class="rounded px-1.5 py-0.5 text-[10px] font-bold uppercase" :class="tone(l.action)">{{ label(l.action) }}</span>
                <template v-if="l.target">
                  <span class="truncate text-ink-2">{{ l.target.label || ('#' + l.target.id) }}</span>
                  <span class="rounded bg-surface-2 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-ink-muted">{{ l.target.type }}</span>
                </template>
              </div>

              <p v-if="l.reason" class="mt-1 text-sm text-ink-2">“{{ l.reason }}”</p>

              <div v-if="metaChips(l.meta).length" class="mt-1 flex flex-wrap gap-1.5">
                <span v-for="(m, i) in metaChips(l.meta)" :key="i" class="rounded bg-surface-2 px-1.5 py-0.5 text-[10px] font-medium text-ink-2">{{ m }}</span>
              </div>

              <div class="mt-1 flex flex-wrap items-center gap-x-3 text-xs text-ink-muted">
                <span :title="fullTime(l.at)">{{ l.ago }}</span>
                <span v-if="l.ip" class="font-mono">{{ l.ip }}</span>
              </div>
            </div>
          </li>

          <li v-if="!logs.data.length" class="px-4 py-10 text-center text-sm text-ink-muted">
            {{ tr('No actions logged yet.') }}
          </li>
        </ul>
      </div>

      <!-- Pagination -->
      <div v-if="logs.data.length" class="mt-4 flex items-center justify-between text-sm">
        <button :disabled="!logs.prev_page_url" class="text-ink-2 disabled:opacity-30"
          @click="logs.prev_page_url && router.get(logs.prev_page_url, {}, opts)">{{ tr('← Newer') }}</button>
        <button :disabled="!logs.next_page_url" class="text-ink-2 disabled:opacity-30"
          @click="logs.next_page_url && router.get(logs.next_page_url, {}, opts)">{{ tr('Older →') }}</button>
      </div>
    </div>
  </AdminLayout>
</template>
