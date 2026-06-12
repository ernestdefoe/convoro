<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { reactive, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Avatar from '@/Components/forum/Avatar.vue';

type Note = {
  id: string; read: boolean; time: string; type: string;
  actor: { name: string; initials: string; color: number };
  topic: { title: string; slug: string };
  emoji?: string; excerpt?: string; url: string;
};

type Frequency = 'off' | 'daily' | 'weekly';

const props = defineProps<{
  items: Note[];
  unread: number;
  digestFrequency: Frequency;
  preferences: Record<string, boolean>;
}>();

// Type keys + their human labels (the order shown in the preferences card).
const TYPE_LABELS: { key: string; label: string }[] = [
  { key: 'reply', label: 'Replies in topics you joined' },
  { key: 'mention', label: 'Mentions of you' },
  { key: 'reaction', label: 'Reactions to your posts' },
  { key: 'wall', label: 'Posts on your profile' },
  { key: 'message', label: 'Direct messages' },
];

const prefs = reactive<Record<string, boolean>>({ ...props.preferences });
const digest = ref<Frequency>(props.digestFrequency);

function label(n: Note): string {
  if (n.type === 'mention') return `${n.actor.name} mentioned you in ${n.topic.title}`;
  if (n.type === 'reaction') return `${n.actor.name} reacted ${n.emoji ?? ''} to your post`;
  if (n.type === 'wall') return `${n.actor.name} posted on your profile`;
  if (n.type === 'message') return `${n.actor.name} sent you a message`;
  return `${n.actor.name} replied in ${n.topic.title}`;
}

function go(n: Note) {
  if (!n.read) router.post(`/notifications/${n.id}/read`, {}, { preserveScroll: true });
  router.visit(n.url);
}

function markAll() {
  router.post('/notifications/read', {}, { preserveScroll: true });
}

function toggle(key: string) {
  prefs[key] = !prefs[key];
  router.post('/notifications/preferences', { notifications: { ...prefs } }, {
    preserveScroll: true,
    preserveState: true,
  });
}

function saveDigest() {
  router.post('/notifications/preferences', { digest_frequency: digest.value }, {
    preserveScroll: true,
    preserveState: true,
  });
}
</script>

<template>
  <Head title="Notifications" />
  <AppLayout>
    <div class="mx-auto max-w-[760px]">
      <div class="mb-5 flex items-center justify-between">
        <h1 class="text-2xl font-extrabold tracking-tight">Notifications</h1>
        <button
          v-if="props.unread > 0"
          type="button"
          class="rounded-lg border border-line bg-surface px-3 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2"
          @click="markAll"
        >Mark all read</button>
      </div>

      <div class="overflow-hidden rounded-c border border-line bg-surface">
        <button
          v-for="n in props.items"
          :key="n.id"
          type="button"
          class="flex w-full gap-3 border-b border-line/60 px-5 py-4 text-left last:border-0 hover:bg-surface-2"
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
        </button>

        <div v-if="!props.items.length" class="px-5 py-16 text-center text-ink-muted">
          <div class="text-3xl">🎉</div>
          <p class="mt-2 text-sm">No notifications yet — you're all caught up.</p>
        </div>
      </div>

      <!-- Preferences -->
      <div class="mt-8 rounded-c border border-line bg-surface p-5">
        <h2 class="text-lg font-extrabold tracking-tight">Preferences</h2>

        <div class="mt-4">
          <div class="text-sm font-semibold text-ink">Notify me about</div>
          <div class="mt-2 divide-y divide-line/60">
            <label
              v-for="t in TYPE_LABELS"
              :key="t.key"
              class="flex cursor-pointer items-center justify-between py-3"
            >
              <span class="text-sm text-ink-2">{{ t.label }}</span>
              <button
                type="button"
                role="switch"
                :aria-checked="prefs[t.key]"
                class="relative h-6 w-11 shrink-0 rounded-full transition-colors"
                :class="prefs[t.key] ? 'bg-primary' : 'bg-line'"
                @click="toggle(t.key)"
              >
                <span
                  class="absolute top-0.5 left-0.5 h-5 w-5 rounded-full bg-white transition-transform"
                  :class="prefs[t.key] ? 'translate-x-5' : ''"
                ></span>
              </button>
            </label>
          </div>
        </div>

        <div class="mt-5 border-t border-line/60 pt-5">
          <label class="block text-sm font-semibold text-ink" for="digest-frequency">Digest email</label>
          <p class="mt-1 text-sm text-ink-muted">A periodic summary of new topics and unread notifications.</p>
          <select
            id="digest-frequency"
            v-model="digest"
            class="mt-2 rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink"
            @change="saveDigest"
          >
            <option value="off">Off</option>
            <option value="daily">Daily</option>
            <option value="weekly">Weekly</option>
          </select>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
