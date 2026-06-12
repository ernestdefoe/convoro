<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t as tr } from '@/lib/i18n';

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
  onboarding: { steps: { label: string; detail: string; href: string; done: boolean }[]; done: number; total: number };
  update: { current: string; latest: string; available: boolean; url: string | null; title: string | null; notes: string | null; changelog: { type?: string; text: string }[]; checkedAt: string | null; enabled: boolean; running: boolean; lastStatus: string | null };
  health: { name: string; state: string; detail: string }[];
  info: { version: string; php: string; laravel: string; database: string; cache: string; queue: string };
}>();

const page = usePage();
const flash = computed(() => (page.props as any).flash?.status as string | undefined);

const onboardingPct = computed(() => Math.round((props.onboarding.done / props.onboarding.total) * 100));

const updating = ref(false);
function checkUpdates() { router.post('/admin/system/check-updates', {}, { preserveScroll: true }); }
function applyUpdate() {
  if (!confirm(tr('Download and install the latest version now?'))) return;
  updating.value = true;
  router.post('/admin/system/update', {}, { preserveScroll: true, onSuccess: () => pollUntilDone() });
}
// Poll the update status and auto-refresh the page once it finishes, so you
// don't have to manually reload to see whether the update took.
function pollUntilDone() {
  const started = Date.now();
  const tick = async () => {
    try {
      const r = await fetch('/admin/system/update-status', { headers: { Accept: 'application/json' } });
      if (r.ok) {
        const s = await r.json();
        if (!s.running) { window.location.reload(); return; }
      }
    } catch { /* mid-update blips are expected — keep polling */ }
    if (Date.now() - started < 180000) setTimeout(tick, 2500);
    else updating.value = false; // give up after 3 minutes
  };
  setTimeout(tick, 2500);
}
const maintenance: { action: string; label: string; confirm?: string }[] = [
  { action: 'cache', label: tr('Clear caches') },
  { action: 'optimize', label: tr('Optimize') },
  { action: 'migrate', label: tr('Run migrations') },
  { action: 'storage', label: tr('Link storage') },
  { action: 'queue', label: tr('Clear queue'), confirm: tr('Clear all pending and failed jobs and restart the queue workers? In-progress work will be dropped so it can be retried.') },
];
function runTask(action: string, confirmMsg?: string) {
  if (confirmMsg && !window.confirm(confirmMsg)) return;
  router.post('/admin/system/run', { action }, { preserveScroll: true });
}
const stateColor = (s: string) => s === 'ok' ? 'bg-emerald-400' : s === 'degraded' ? 'bg-amber-400' : 'bg-red-400';

const cards = computed(() => [
  { label: tr('Members'), value: props.stats.users },
  { label: tr('Topics'), value: props.stats.topics },
  { label: tr('Posts'), value: props.stats.posts },
  { label: tr('Reactions'), value: props.stats.reactions },
]);

const maxSignup = computed(() => Math.max(1, ...(props.analytics?.signups.map((s) => s.count) ?? [1])));
</script>

<template>
  <Head :title="tr('Admin · Dashboard')" />
  <AdminLayout>
    <template #title>{{ tr('Dashboard') }}</template>
    <template #subtitle>{{ tr('An overview of your community') }}</template>

    <!-- Getting started (hidden once every step is done) -->
    <div v-if="onboarding.done < onboarding.total" class="mb-6 rounded-2xl border border-line bg-surface p-6">
      <div class="flex flex-wrap items-center gap-4">
        <div class="flex-1">
          <h2 class="text-base font-bold text-ink">{{ tr('Get your community ready') }}</h2>
          <p class="text-sm text-ink-2">{{ tr('{done} of {total} steps done — you\'re {pct}% set up.', { done: onboarding.done, total: onboarding.total, pct: onboardingPct }) }}</p>
        </div>
        <div class="h-2 w-40 overflow-hidden rounded-full bg-surface-2">
          <div class="h-full rounded-full bg-primary transition-all" :style="{ width: onboardingPct + '%' }"></div>
        </div>
      </div>
      <div class="mt-5 grid gap-2.5 sm:grid-cols-2">
        <a v-for="s in onboarding.steps" :key="s.label" :href="s.href"
          class="flex items-start gap-3 rounded-xl border border-line p-3.5 transition hover:bg-surface-2"
          :class="s.done ? 'opacity-60' : ''">
          <span class="mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full text-[11px]"
            :class="s.done ? 'bg-emerald-500 text-white' : 'border-2 border-line text-transparent'">✓</span>
          <span class="min-w-0">
            <span class="block text-sm font-semibold text-ink" :class="s.done ? 'line-through' : ''">{{ s.label }}</span>
            <span class="block text-xs text-ink-muted">{{ s.detail }}</span>
          </span>
          <svg v-if="!s.done" class="ml-auto mt-1 shrink-0 text-ink-muted" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6" /></svg>
        </a>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
      <div v-for="c in cards" :key="c.label" class="rounded-2xl border border-line bg-surface p-5">
        <div class="text-3xl font-extrabold text-ink">{{ c.value.toLocaleString() }}</div>
        <div class="mt-1 text-sm text-ink-2">{{ c.label }}</div>
      </div>
    </div>

    <!-- Queue / jobs overview -->
    <div class="mt-6 rounded-2xl border border-line bg-surface p-5">
      <div class="mb-3 flex items-center gap-2">
        <h2 class="text-sm font-bold text-ink">{{ tr('Queue & jobs') }}</h2>
        <span class="rounded bg-surface-2 px-1.5 py-0.5 text-[11px] uppercase tracking-wide text-ink-2">{{ queue.connection }}</span>
        <a v-if="queue.horizon" href="/horizon" target="_blank" rel="noopener"
          class="ml-auto text-xs font-semibold text-indigo-300 hover:text-indigo-200">{{ tr('Open Horizon →') }}</a>
      </div>
      <div class="grid grid-cols-2 gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-line bg-appbg p-4">
          <div class="text-2xl font-extrabold text-ink">{{ queue.pending === null ? '—' : queue.pending.toLocaleString() }}</div>
          <div class="mt-0.5 text-xs text-ink-2">{{ tr('Pending jobs') }}</div>
        </div>
        <div class="rounded-xl border p-4" :class="queue.failed > 0 ? 'border-red-500/30 bg-red-500/5' : 'border-line bg-appbg'">
          <div class="text-2xl font-extrabold" :class="queue.failed > 0 ? 'text-red-300' : 'text-ink'">{{ queue.failed.toLocaleString() }}</div>
          <div class="mt-0.5 text-xs text-ink-2">{{ tr('Failed jobs') }}</div>
        </div>
        <div class="rounded-xl border border-line bg-appbg p-4">
          <div class="text-2xl font-extrabold" :class="queue.failed > 0 ? 'text-amber-300' : 'text-emerald-300'">{{ queue.failed > 0 ? tr('Attention') : tr('Healthy') }}</div>
          <div class="mt-0.5 text-xs text-ink-2">{{ tr('Status') }}</div>
        </div>
      </div>
      <p v-if="queue.pending === null" class="mt-3 text-xs text-ink-muted">{{ tr('Pending count unavailable for this queue driver.') }}</p>
    </div>

    <!-- Analytics (when the analytics extension is enabled) -->
    <div v-if="analytics" class="mt-6 space-y-6">
      <div class="rounded-2xl border border-line bg-surface p-5">
        <div class="mb-4 flex items-center gap-2">
          <h2 class="text-sm font-bold text-ink">{{ tr('New members · last 14 days') }}</h2>
          <span class="ml-auto rounded bg-emerald-500/15 px-2 py-0.5 text-[11px] font-semibold text-emerald-300">{{ tr('{n} online now', { n: analytics.online }) }}</span>
        </div>
        <div class="flex h-32 items-end gap-1.5">
          <div v-for="(s, i) in analytics.signups" :key="i" class="flex flex-1 flex-col items-center justify-end gap-1">
            <div class="w-full max-w-[26px] rounded-t bg-gradient-to-b from-indigo-500 to-violet-500"
              :style="{ height: Math.round((s.count / maxSignup) * 92) + 4 + 'px' }" :title="`${s.date}: ${s.count}`" />
            <span class="text-[10px] text-ink-muted">{{ s.day }}</span>
          </div>
        </div>
      </div>

      <div class="grid gap-6 md:grid-cols-2">
        <div class="rounded-2xl border border-line bg-surface p-5">
          <h2 class="mb-3 text-sm font-bold text-ink">{{ tr('Top posters') }}</h2>
          <ul class="divide-y divide-line">
            <li v-for="(p, i) in analytics.topPosters" :key="i" class="flex items-center justify-between py-2.5 text-sm">
              <span class="text-ink">{{ p.name }}</span>
              <span class="font-semibold text-ink-2">{{ p.count }}</span>
            </li>
            <li v-if="!analytics.topPosters.length" class="py-2.5 text-sm text-ink-muted">{{ tr('No posts yet.') }}</li>
          </ul>
        </div>
        <div class="rounded-2xl border border-line bg-surface p-5">
          <h2 class="mb-3 text-sm font-bold text-ink">{{ tr('Most active topics') }}</h2>
          <ul class="divide-y divide-line">
            <li v-for="(t, i) in analytics.activeTopics" :key="i" class="flex items-center justify-between gap-3 py-2.5 text-sm">
              <a :href="`/t/${t.slug}`" class="truncate text-ink hover:text-ink">{{ t.title }}</a>
              <span class="shrink-0 font-semibold text-ink-2">{{ tr('{n} replies', { n: t.replies }) }}</span>
            </li>
            <li v-if="!analytics.activeTopics.length" class="py-2.5 text-sm text-ink-muted">{{ tr('No topics yet.') }}</li>
          </ul>
        </div>
      </div>
    </div>

    <div class="mt-6 rounded-2xl border border-line bg-surface p-5">
      <h2 class="mb-3 text-sm font-bold text-ink">{{ tr('Newest members') }}</h2>
      <ul class="divide-y divide-line">
        <li v-for="(u, i) in newUsers" :key="i" class="flex items-center justify-between py-2.5 text-sm">
          <span class="text-ink">{{ u.name }}</span>
          <span class="text-ink-muted">{{ u.joined }}</span>
        </li>
        <li v-if="!newUsers.length" class="py-2.5 text-sm text-ink-muted">{{ tr('No members yet.') }}</li>
      </ul>
    </div>

    <!-- System: server status, updates, maintenance, environment -->
    <div v-if="flash" class="mt-6 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">{{ flash }}</div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
      <!-- Server status -->
      <section class="rounded-2xl border border-line bg-surface p-5">
        <h2 class="mb-3 text-sm font-bold text-ink">{{ tr('Server status') }}</h2>
        <ul class="space-y-2">
          <li v-for="c in health" :key="c.name" class="flex items-center gap-2.5 text-sm">
            <span class="h-2.5 w-2.5 shrink-0 rounded-full" :class="stateColor(c.state)"></span>
            <span class="font-semibold text-ink">{{ c.name }}</span>
            <span class="ml-auto text-ink-muted">{{ c.detail }}</span>
          </li>
        </ul>
      </section>

      <!-- Software updates -->
      <section class="rounded-2xl border border-line bg-surface p-5">
        <h2 class="mb-3 text-sm font-bold text-ink">{{ tr('Software updates') }}</h2>
        <div class="flex items-center justify-between rounded-xl bg-appbg px-4 py-3">
          <div><div class="text-xs text-ink-2">{{ tr('Current version') }}</div><div class="text-lg font-bold text-ink">{{ update.current }}</div></div>
          <span v-if="update.available" class="rounded-full bg-indigo-500/20 px-3 py-1 text-xs font-bold text-indigo-300">{{ tr('{version} available', { version: update.latest }) }}</span>
          <span v-else class="rounded-full bg-emerald-500/20 px-3 py-1 text-xs font-bold text-emerald-300">{{ tr('Up to date') }}</span>
        </div>
        <p v-if="!update.enabled" class="mt-2 text-xs text-ink-muted">{{ tr('Set CONVORO_UPDATE_URL in .env to enable update checks.') }}</p>
        <div v-if="update.available && (update.changelog?.length || update.notes)" class="mt-3 rounded-xl border border-indigo-500/30 bg-indigo-500/5 p-4">
          <div class="text-sm font-bold text-ink">{{ tr("What's new in {version}", { version: update.latest }) }}<span v-if="update.title" class="font-medium text-ink-2"> — {{ update.title }}</span></div>
          <ul v-if="update.changelog?.length" class="mt-2 space-y-1">
            <li v-for="(c, i) in update.changelog" :key="i" class="flex gap-2 text-sm text-ink-2">
              <span class="mt-0.5 shrink-0 rounded px-1.5 text-[10px] font-bold uppercase" :class="c.type === 'fixed' ? 'bg-amber-500/20 text-amber-300' : c.type === 'improved' ? 'bg-sky-500/20 text-sky-300' : 'bg-emerald-500/20 text-emerald-300'">{{ c.type || tr('new') }}</span>
              <span>{{ c.text }}</span>
            </li>
          </ul>
          <p v-else-if="update.notes" class="mt-1 text-sm text-ink-2">{{ update.notes }}</p>
        </div>
        <p v-if="update.checkedAt" class="mt-2 text-xs text-ink-muted">{{ tr('Last checked {when}', { when: update.checkedAt }) }}</p>
        <div class="mt-3 flex flex-wrap gap-2">
          <button class="rounded-lg bg-surface-2 px-3 py-2 text-sm font-semibold text-ink hover:bg-appbg" @click="checkUpdates">{{ tr('Check for updates') }}</button>
          <button v-if="update.available && !update.running" :disabled="updating" class="rounded-lg bg-indigo-500 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-600 disabled:opacity-60" @click="applyUpdate">{{ updating ? tr('Starting…') : tr('Update to {version}', { version: update.latest }) }}</button>
          <span v-if="update.running" class="inline-flex items-center gap-2 rounded-lg bg-amber-500/15 px-3 py-2 text-sm font-semibold text-amber-400"><span class="h-2 w-2 animate-pulse rounded-full bg-amber-400"></span> {{ tr('Updating…') }}</span>
        </div>
      </section>

      <!-- Maintenance -->
      <section class="rounded-2xl border border-line bg-surface p-5">
        <h2 class="mb-3 text-sm font-bold text-ink">{{ tr('Maintenance') }}</h2>
        <div class="flex flex-wrap gap-2">
          <button v-for="t in maintenance" :key="t.action" class="rounded-lg bg-surface-2 px-3 py-2 text-sm font-semibold text-ink hover:bg-appbg" @click="runTask(t.action, t.confirm)">{{ t.label }}</button>
        </div>
      </section>

      <!-- Environment -->
      <section class="rounded-2xl border border-line bg-surface p-5">
        <h2 class="mb-3 text-sm font-bold text-ink">{{ tr('Environment') }}</h2>
        <dl class="space-y-1.5 text-sm">
          <div class="flex justify-between"><dt class="text-ink-muted">Convoro</dt><dd class="font-semibold text-ink">{{ info.version }}</dd></div>
          <div class="flex justify-between"><dt class="text-ink-muted">PHP</dt><dd class="font-semibold text-ink">{{ info.php }}</dd></div>
          <div class="flex justify-between"><dt class="text-ink-muted">Laravel</dt><dd class="font-semibold text-ink">{{ info.laravel }}</dd></div>
          <div class="flex justify-between"><dt class="text-ink-muted">{{ tr('Database') }}</dt><dd class="font-semibold text-ink">{{ info.database }}</dd></div>
          <div class="flex justify-between"><dt class="text-ink-muted">{{ tr('Cache') }}</dt><dd class="font-semibold text-ink">{{ info.cache }}</dd></div>
          <div class="flex justify-between"><dt class="text-ink-muted">{{ tr('Queue') }}</dt><dd class="font-semibold text-ink">{{ info.queue }}</dd></div>
        </dl>
      </section>
    </div>
  </AdminLayout>
</template>
