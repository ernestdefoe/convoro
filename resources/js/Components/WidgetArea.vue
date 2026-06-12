<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Avatar from '@/Components/forum/Avatar.vue';
import { t } from '@/lib/i18n';

export interface Widget { id: string; type: string; title?: string; body?: string }
interface WidgetData {
  onlineNow: number;
  newestMembers: { name: string; initials: string; color: number; avatar?: string | null; url?: string }[];
  topPosters: { name: string; count: number }[];
}
interface Category { name: string; slug: string; count: number }

const props = defineProps<{
  widgets: Widget[];
  data?: WidgetData;
  stats?: Record<string, number>;
  categories?: Category[];
}>();

// Fall back to a sensible default rail when nothing is configured yet.
const list = computed<Widget[]>(() =>
  props.widgets?.length ? props.widgets : [{ id: 'default-stats', type: 'stats', title: t('Community stats') }],
);

const fmt = (n = 0) => (n >= 1000 ? (n / 1000).toFixed(1).replace('.0', '') + 'k' : String(n));
</script>

<template>
  <div class="flex flex-col gap-4">
    <div v-for="w in list" :key="w.id" class="overflow-hidden rounded-c border border-line bg-surface shadow-sm">
      <h4 v-if="w.title" class="border-b border-line px-4 py-3 text-xs font-semibold uppercase tracking-wide text-ink-muted">{{ w.title }}</h4>

      <!-- Custom text / HTML -->
      <div v-if="w.type === 'text'" class="prose-sm px-4 py-3 text-sm text-ink-2" v-html="w.body || ''" />

      <!-- Community stats -->
      <div v-else-if="w.type === 'stats'" class="grid grid-cols-2 gap-2.5 p-4">
        <div class="rounded-[10px] border border-line bg-surface-2 p-3"><b class="block text-lg tracking-tight">{{ fmt(stats?.members) }}</b><span class="text-[11px] text-ink-muted">{{ t('Members') }}</span></div>
        <div class="rounded-[10px] border border-line bg-surface-2 p-3"><b class="block text-lg tracking-tight">{{ fmt(stats?.topics) }}</b><span class="text-[11px] text-ink-muted">{{ t('Topics') }}</span></div>
        <div class="rounded-[10px] border border-line bg-surface-2 p-3"><b class="block text-lg tracking-tight">{{ fmt(stats?.posts) }}</b><span class="text-[11px] text-ink-muted">{{ t('Posts') }}</span></div>
        <div class="rounded-[10px] border border-line bg-surface-2 p-3"><b class="block text-lg tracking-tight">{{ fmt(stats?.reactions) }}</b><span class="text-[11px] text-ink-muted">{{ t('Reactions') }}</span></div>
      </div>

      <!-- Online now -->
      <div v-else-if="w.type === 'online_now'" class="flex items-center gap-3 p-4">
        <span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
        <span class="text-2xl font-extrabold tracking-tight">{{ fmt(data?.onlineNow) }}</span>
        <span class="text-sm text-ink-muted">{{ t('online now') }}</span>
      </div>

      <!-- Newest members -->
      <div v-else-if="w.type === 'newest_members'" class="flex flex-wrap gap-2 p-4">
        <Link v-for="(m, i) in data?.newestMembers || []" :key="i" :href="m.url || '#'" :title="m.name">
          <Avatar :avatar="m" :size="36" />
        </Link>
        <p v-if="!(data?.newestMembers || []).length" class="text-sm text-ink-muted">{{ t('No members yet.') }}</p>
      </div>

      <!-- Top posters -->
      <ul v-else-if="w.type === 'top_posters'" class="p-2">
        <li v-for="(p, i) in data?.topPosters || []" :key="i" class="flex items-center justify-between rounded-lg px-2 py-1.5 text-sm">
          <span class="text-ink-2">{{ p.name }}</span><span class="font-semibold text-ink-muted">{{ p.count }}</span>
        </li>
        <li v-if="!(data?.topPosters || []).length" class="px-2 py-1.5 text-sm text-ink-muted">{{ t('No posts yet.') }}</li>
      </ul>

      <!-- Categories -->
      <nav v-else-if="w.type === 'categories'" class="flex flex-col gap-0.5 p-2">
        <Link v-for="c in categories || []" :key="c.slug" :href="`/?category=${c.slug}`"
          class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2">
          {{ c.name }}<span class="ml-auto rounded-full bg-surface-2 px-2 py-0.5 text-xs text-ink-muted">{{ c.count }}</span>
        </Link>
      </nav>
    </div>
  </div>
</template>
