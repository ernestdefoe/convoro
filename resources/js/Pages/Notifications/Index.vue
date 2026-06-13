<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Avatar from '@/Components/forum/Avatar.vue';
import { t } from '@/lib/i18n';

type Note = {
  id: string; read: boolean; time: string; type: string;
  actor: { name: string; initials: string; color: number };
  topic: { title: string; slug: string };
  emoji?: string; excerpt?: string; text?: string; url: string;
};

const props = defineProps<{ items: Note[]; unread: number }>();

function label(n: Note): string {
  if (n.type === 'badge') return n.text || t('You earned a badge');
  if (n.type === 'mention') return t('{name} mentioned you in {topic}', { name: n.actor.name, topic: n.topic.title });
  if (n.type === 'reaction') return t('{name} reacted {emoji} to your post', { name: n.actor.name, emoji: n.emoji ?? '' });
  return t('{name} replied in {topic}', { name: n.actor.name, topic: n.topic.title });
}

function go(n: Note) {
  if (!n.read) router.post(`/notifications/${n.id}/read`, {}, { preserveScroll: true });
  router.visit(n.url);
}

function markAll() {
  router.post('/notifications/read', {}, { preserveScroll: true });
}

function clearAll() {
  if (!window.confirm(t('Clear all notifications? This cannot be undone.'))) return;
  router.delete('/notifications', { preserveScroll: true });
}

function clearOne(n: Note) {
  router.delete(`/notifications/${n.id}`, { preserveScroll: true });
}
</script>

<template>
  <Head :title="t('Notifications')" />
  <AppLayout>
    <div class="mx-auto max-w-[760px]">
      <div class="mb-5 flex items-center justify-between">
        <h1 class="text-2xl font-extrabold tracking-tight">{{ t('Notifications') }}</h1>
        <div class="flex items-center gap-2">
          <button
            v-if="props.unread > 0"
            type="button"
            class="rounded-lg border border-line bg-surface px-3 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2"
            @click="markAll"
          >{{ t('Mark all read') }}</button>
          <button
            v-if="props.items.length"
            type="button"
            class="rounded-lg border border-line bg-surface px-3 py-2 text-sm font-semibold text-ink-2 hover:border-red-500/40 hover:text-red-500"
            @click="clearAll"
          >{{ t('Clear all') }}</button>
        </div>
      </div>

      <div class="overflow-hidden rounded-c border border-line bg-surface">
        <div
          v-for="n in props.items"
          :key="n.id"
          class="group flex w-full cursor-pointer items-start gap-3 border-b border-line/60 px-5 py-4 text-left last:border-0 hover:bg-surface-2"
          :class="!n.read ? 'bg-primary/10' : ''"
          @click="go(n)"
        >
          <Avatar :avatar="{ initials: n.actor.initials, color: n.actor.color }" :size="40" />
          <div class="min-w-0 flex-1">
            <div class="text-sm leading-snug text-ink">{{ label(n) }}</div>
            <div v-if="n.excerpt" class="mt-1 truncate text-sm text-ink-muted">{{ n.excerpt }}</div>
            <div class="mt-1 text-xs text-ink-muted">{{ n.time }}</div>
          </div>
          <span v-if="!n.read" class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full bg-primary"></span>
          <button
            type="button"
            :title="t('Dismiss')"
            class="-mr-1 shrink-0 rounded-md p-1 text-ink-muted opacity-0 transition hover:bg-red-500/10 hover:text-red-500 focus:opacity-100 group-hover:opacity-100"
            @click.stop="clearOne(n)"
          >
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12" /></svg>
          </button>
        </div>

        <div v-if="!props.items.length" class="px-5 py-16 text-center text-ink-muted">
          <div class="text-3xl">🎉</div>
          <p class="mt-2 text-sm">{{ t("No notifications yet — you're all caught up.") }}</p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
