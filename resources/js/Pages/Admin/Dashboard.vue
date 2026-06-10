<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface Analytics {
  online: number;
  signups: { date: string; day: string; count: number }[];
  topPosters: { name: string; count: number }[];
  activeTopics: { title: string; slug: string; replies: number }[];
}

const props = defineProps<{
  stats: { users: number; topics: number; posts: number; reactions: number };
  newUsers: { name: string; joined: string }[];
  queue: { connection: string; pending: number | null; failed: number; horizon: boolean };
  analytics: Analytics | null;
}>();

const cards = computed(() => [
  { label: 'Members', value: props.stats.users },
  { label: 'Topics', value: props.stats.topics },
  { label: 'Posts', value: props.stats.posts },
  { label: 'Reactions', value: props.stats.reactions },
]);

const maxSignup = computed(() => Math.max(1, ...(props.analytics?.signups.map((s) => s.count) ?? [1])));
</script>

<template>
  <Head title="Admin · Dashboard" />
  <AdminLayout>
    <template #title>Dashboard</template>
    <template #subtitle>An overview of your community</template>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
      <div v-for="c in cards" :key="c.label" class="rounded-2xl border border-white/5 bg-[#14172a] p-5">
        <div class="text-3xl font-extrabold text-white">{{ c.value.toLocaleString() }}</div>
        <div class="mt-1 text-sm text-slate-400">{{ c.label }}</div>
      </div>
    </div>

    <!-- Queue / jobs overview -->
    <div class="mt-6 rounded-2xl border border-white/5 bg-[#14172a] p-5">
      <div class="mb-3 flex items-center gap-2">
        <h2 class="text-sm font-bold text-white">Queue &amp; jobs</h2>
        <span class="rounded bg-white/5 px-1.5 py-0.5 text-[11px] uppercase tracking-wide text-slate-400">{{ queue.connection }}</span>
        <a v-if="queue.horizon" href="/horizon" target="_blank" rel="noopener"
          class="ml-auto text-xs font-semibold text-indigo-300 hover:text-indigo-200">Open Horizon →</a>
      </div>
      <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-white/5 bg-[#0f1120] p-4">
          <div class="text-2xl font-extrabold text-white">{{ queue.pending === null ? '—' : queue.pending.toLocaleString() }}</div>
          <div class="mt-0.5 text-xs text-slate-400">Pending jobs</div>
        </div>
        <div class="rounded-xl border p-4" :class="queue.failed > 0 ? 'border-red-500/30 bg-red-500/5' : 'border-white/5 bg-[#0f1120]'">
          <div class="text-2xl font-extrabold" :class="queue.failed > 0 ? 'text-red-300' : 'text-white'">{{ queue.failed.toLocaleString() }}</div>
          <div class="mt-0.5 text-xs text-slate-400">Failed jobs</div>
        </div>
        <div class="rounded-xl border border-white/5 bg-[#0f1120] p-4">
          <div class="text-2xl font-extrabold" :class="queue.failed > 0 ? 'text-amber-300' : 'text-emerald-300'">{{ queue.failed > 0 ? 'Attention' : 'Healthy' }}</div>
          <div class="mt-0.5 text-xs text-slate-400">Status</div>
        </div>
      </div>
      <p v-if="queue.pending === null" class="mt-3 text-xs text-slate-500">Pending count unavailable for this queue driver.</p>
    </div>

    <!-- Analytics (when the analytics extension is enabled) -->
    <div v-if="analytics" class="mt-6 space-y-6">
      <div class="rounded-2xl border border-white/5 bg-[#14172a] p-5">
        <div class="mb-4 flex items-center gap-2">
          <h2 class="text-sm font-bold text-white">New members · last 14 days</h2>
          <span class="ml-auto rounded bg-emerald-500/15 px-2 py-0.5 text-[11px] font-semibold text-emerald-300">{{ analytics.online }} online now</span>
        </div>
        <div class="flex h-32 items-end gap-1.5">
          <div v-for="(s, i) in analytics.signups" :key="i" class="flex flex-1 flex-col items-center justify-end gap-1">
            <div class="w-full max-w-[26px] rounded-t bg-gradient-to-b from-indigo-500 to-violet-500"
              :style="{ height: Math.round((s.count / maxSignup) * 92) + 4 + 'px' }" :title="`${s.date}: ${s.count}`" />
            <span class="text-[10px] text-slate-500">{{ s.day }}</span>
          </div>
        </div>
      </div>

      <div class="grid gap-6 md:grid-cols-2">
        <div class="rounded-2xl border border-white/5 bg-[#14172a] p-5">
          <h2 class="mb-3 text-sm font-bold text-white">Top posters</h2>
          <ul class="divide-y divide-white/5">
            <li v-for="(p, i) in analytics.topPosters" :key="i" class="flex items-center justify-between py-2.5 text-sm">
              <span class="text-slate-200">{{ p.name }}</span>
              <span class="font-semibold text-slate-400">{{ p.count }}</span>
            </li>
            <li v-if="!analytics.topPosters.length" class="py-2.5 text-sm text-slate-500">No posts yet.</li>
          </ul>
        </div>
        <div class="rounded-2xl border border-white/5 bg-[#14172a] p-5">
          <h2 class="mb-3 text-sm font-bold text-white">Most active topics</h2>
          <ul class="divide-y divide-white/5">
            <li v-for="(t, i) in analytics.activeTopics" :key="i" class="flex items-center justify-between gap-3 py-2.5 text-sm">
              <a :href="`/t/${t.slug}`" class="truncate text-slate-200 hover:text-white">{{ t.title }}</a>
              <span class="shrink-0 font-semibold text-slate-400">{{ t.replies }} replies</span>
            </li>
            <li v-if="!analytics.activeTopics.length" class="py-2.5 text-sm text-slate-500">No topics yet.</li>
          </ul>
        </div>
      </div>
    </div>

    <div class="mt-6 rounded-2xl border border-white/5 bg-[#14172a] p-5">
      <h2 class="mb-3 text-sm font-bold text-white">Newest members</h2>
      <ul class="divide-y divide-white/5">
        <li v-for="(u, i) in newUsers" :key="i" class="flex items-center justify-between py-2.5 text-sm">
          <span class="text-slate-200">{{ u.name }}</span>
          <span class="text-slate-500">{{ u.joined }}</span>
        </li>
        <li v-if="!newUsers.length" class="py-2.5 text-sm text-slate-500">No members yet.</li>
      </ul>
    </div>
  </AdminLayout>
</template>
