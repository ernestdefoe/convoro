<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import Avatar from '@/Components/forum/Avatar.vue';

const page = usePage();
const user = computed(() => (page.props as any).auth?.user ?? null);

type Note = {
  id: string; read: boolean; time: string; type: string;
  actor: { name: string; initials: string; color: number };
  topic: { title: string; slug: string };
  emoji?: string; excerpt?: string; url: string;
};

const open = ref(false);
const items = ref<Note[]>([]);
const unread = ref(0);

function syncFromProps() {
  const n = (page.props as any).notifications ?? { items: [], unread: 0 };
  items.value = n.items ?? [];
  unread.value = n.unread ?? 0;
}
syncFromProps();
watch(() => (page.props as any).notifications, syncFromProps, { deep: true });

function label(n: Note): string {
  if (n.type === 'mention') return `${n.actor.name} mentioned you in ${n.topic.title}`;
  if (n.type === 'reaction') return `${n.actor.name} reacted ${n.emoji ?? ''} to your post`;
  return `${n.actor.name} replied in ${n.topic.title}`;
}

function toggle() {
  open.value = !open.value;
}

function go(n: Note) {
  open.value = false;
  if (!n.read) {
    router.post(`/notifications/${n.id}/read`, {}, { preserveScroll: true, preserveState: true, only: ['notifications'] });
  }
  router.visit(n.url);
}

function markAll() {
  unread.value = 0;
  items.value = items.value.map((n) => ({ ...n, read: true }));
  router.post('/notifications/read', {}, { preserveScroll: true, preserveState: true, only: ['notifications'] });
}

// ---- Live delivery over the user's private channel ----
const Echo = () => (window as any).Echo;
let bound = false;

onMounted(() => {
  document.addEventListener('click', onDocClick);
  if (user.value && Echo()) {
    Echo().private(`App.Models.User.${user.value.id}`).listen('.NotificationCreated', (e: any) => {
      if (e?.notification && !items.value.some((n) => n.id === e.notification.id)) {
        items.value.unshift(e.notification);
        items.value = items.value.slice(0, 15);
      }
      unread.value = e?.unread ?? unread.value + 1;
    });
    bound = true;
  }
});

onBeforeUnmount(() => {
  document.removeEventListener('click', onDocClick);
  if (bound && user.value && Echo()) Echo().leave(`App.Models.User.${user.value.id}`);
});

const root = ref<HTMLElement | null>(null);
function onDocClick(e: MouseEvent) {
  if (open.value && root.value && !root.value.contains(e.target as Node)) open.value = false;
}
</script>

<template>
  <div ref="root" class="relative">
    <button
      type="button"
      class="relative flex h-[34px] w-[34px] items-center justify-center rounded-full border border-line bg-surface-2 text-ink-2 hover:text-ink"
      aria-label="Notifications"
      @click="toggle"
    >
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" />
        <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />
      </svg>
      <span
        v-if="unread > 0"
        class="absolute -right-1 -top-1 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-primary px-1 text-[10px] font-bold text-white"
      >{{ unread > 99 ? '99+' : unread }}</span>
    </button>

    <div
      v-if="open"
      class="absolute right-0 top-[44px] z-50 w-[360px] overflow-hidden rounded-c border border-line bg-surface shadow-2xl shadow-black/10"
    >
      <div class="flex items-center justify-between border-b border-line px-4 py-3">
        <span class="text-sm font-bold">Notifications</span>
        <button v-if="unread > 0" type="button" class="text-xs font-semibold text-primary-700 hover:underline" @click="markAll">Mark all read</button>
      </div>

      <div class="max-h-[420px] overflow-y-auto">
        <button
          v-for="n in items"
          :key="n.id"
          type="button"
          class="flex w-full gap-3 border-b border-line/60 px-4 py-3 text-left hover:bg-surface-2"
          :class="!n.read ? 'bg-primary/10' : ''"
          @click="go(n)"
        >
          <Avatar :avatar="{ initials: n.actor.initials, color: n.actor.color }" :size="34" />
          <span class="min-w-0 flex-1">
            <span class="block text-sm leading-snug text-ink">{{ label(n) }}</span>
            <span v-if="n.excerpt" class="mt-0.5 block truncate text-xs text-ink-muted">{{ n.excerpt }}</span>
            <span class="mt-0.5 block text-[11px] text-ink-muted">{{ n.time }}</span>
          </span>
          <span v-if="!n.read" class="mt-1 h-2 w-2 shrink-0 rounded-full bg-primary"></span>
        </button>

        <div v-if="!items.length" class="px-4 py-10 text-center text-sm text-ink-muted">
          You're all caught up. 🎉
        </div>
      </div>

      <Link
        href="/notifications"
        class="block border-t border-line px-4 py-3 text-center text-sm font-semibold text-primary-700 hover:bg-surface-2"
        @click="open = false"
      >View all notifications</Link>
    </div>
  </div>
</template>
