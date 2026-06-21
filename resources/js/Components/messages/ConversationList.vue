<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Avatar from '@/Components/forum/Avatar.vue';
import { t as tr } from '@/lib/i18n';

const props = defineProps<{ conversations: any[]; activeId?: number | null }>();
const emit = defineEmits<{ (e: 'new'): void }>();

const q = ref('');
const filtered = computed(() => {
  const s = q.value.trim().toLowerCase();
  if (!s) return props.conversations;
  return props.conversations.filter((c) => (c.title || '').toLowerCase().includes(s));
});
</script>

<template>
  <div class="flex h-full flex-col">
    <!-- ALL CHATS / Messages (N) -->
    <div class="mb-3 shrink-0">
      <p class="text-[11px] font-bold uppercase tracking-wider text-ink-muted">{{ tr('All chats') }}</p>
      <div class="mt-0.5 flex items-center justify-between gap-2">
        <h1 class="text-2xl font-extrabold tracking-tight text-ink">
          {{ tr('Messages') }} <span class="text-primary">({{ conversations.length }})</span>
        </h1>
        <button type="button" class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-primary text-white hover:bg-primary-600" :title="tr('New message')" :aria-label="tr('New message')" @click="emit('new')">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14" /></svg>
        </button>
      </div>
    </div>

    <!-- Search -->
    <div class="relative mb-2 shrink-0">
      <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7" /><path d="m21 21-4.3-4.3" /></svg>
      <input v-model="q" type="text" :placeholder="tr('Search…')" class="w-full rounded-full border-line bg-surface-2 pl-9 text-sm text-ink placeholder:text-ink-muted focus:border-primary focus:ring-primary" />
    </div>

    <!-- Rows -->
    <div class="-mx-1 min-h-0 flex-1 overflow-y-auto px-1">
      <Link
        v-for="c in filtered"
        :key="c.id"
        :href="`/messages/${c.id}`"
        class="flex items-center gap-3 rounded-xl px-3 py-2.5 transition"
        :class="c.id === activeId ? 'bg-primary' : 'hover:bg-surface-2'"
      >
        <Avatar :avatar="c.avatar" :size="44" class="shrink-0" />
        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-2">
            <span class="truncate font-bold" :class="c.id === activeId ? 'text-white' : 'text-ink'">{{ c.title }}</span>
            <span class="ml-auto shrink-0 text-[11px]" :class="c.id === activeId ? 'text-white/80' : 'text-ink-muted'">{{ c.time }}</span>
          </div>
          <div class="mt-0.5 flex items-center gap-2">
            <span class="truncate text-[13px]" :class="c.id === activeId ? 'text-white/90' : (c.unread ? 'font-semibold text-ink' : 'text-ink-muted')">{{ c.excerpt }}</span>
            <span v-if="c.unread && c.id !== activeId" class="ml-auto h-2.5 w-2.5 shrink-0 rounded-full bg-primary"></span>
          </div>
        </div>
      </Link>
      <p v-if="!filtered.length" class="px-3 py-10 text-center text-sm text-ink-muted">
        {{ q ? tr('No matches.') : tr('No conversations yet.') }}
      </p>
    </div>
  </div>
</template>
