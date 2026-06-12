<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import Avatar from '@/Components/forum/Avatar.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import { t as tr } from '@/lib/i18n';

defineProps<{ conversations: any[] }>();

const composing = ref(false);
const query = ref('');
const results = ref<any[]>([]);
const selected = ref<any[]>([]);
const groupTitle = ref('');
let t: any = null;

watch(query, (q) => {
  clearTimeout(t);
  if (!q.trim()) { results.value = []; return; }
  t = setTimeout(async () => {
    try {
      const res = await fetch(`/users/search?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      results.value = res.ok ? await res.json() : [];
    } catch { results.value = []; }
  }, 200);
});

function addPerson(u: any) {
  if (!selected.value.some((s) => s.uid === u.uid)) selected.value.push(u);
  query.value = '';
  results.value = [];
}
function removePerson(uid: number) {
  selected.value = selected.value.filter((s) => s.uid !== uid);
}
function start() {
  if (!selected.value.length) return;
  router.post('/messages', {
    user_ids: selected.value.map((s) => s.uid),
    title: selected.value.length > 1 ? (groupTitle.value.trim() || null) : null,
  }, { preserveScroll: true });
}
</script>

<template>
  <Head :title="tr('Messages')" />
  <AppLayout>
    <div class="mx-auto max-w-[760px]">
      <div class="mb-5 flex items-center justify-between">
        <h1 class="text-2xl font-extrabold tracking-tight">{{ tr('Messages') }}</h1>
        <button type="button" class="rounded-c bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-600" @click="composing = !composing">{{ tr('New message') }}</button>
      </div>

      <div v-if="composing" class="mb-4 rounded-c border border-line bg-surface p-3">
        <!-- Selected people as chips -->
        <div v-if="selected.length" class="mb-2 flex flex-wrap gap-2">
          <span v-for="s in selected" :key="s.uid" class="inline-flex items-center gap-1.5 rounded-full bg-primary/15 py-1 pl-1 pr-2 text-sm font-semibold text-primary">
            <Avatar :avatar="s" :size="22" /> {{ s.name }}
            <button type="button" class="ml-0.5 text-primary/70 hover:text-primary" @click="removePerson(s.uid)" :aria-label="tr('Remove')">✕</button>
          </span>
        </div>

        <input v-model="query" type="text" :placeholder="tr('Add people by name…')" class="w-full rounded-lg border-line bg-surface-2 text-ink placeholder:text-ink-muted focus:border-primary focus:ring-primary" />
        <div v-if="results.length" class="mt-2 divide-y divide-line/60">
          <button v-for="u in results" :key="u.uid" type="button" class="flex w-full items-center gap-2.5 px-1 py-2 text-left hover:bg-surface-2" @click="addPerson(u)">
            <Avatar :avatar="u" :size="30" />
            <span class="text-sm font-semibold text-ink">{{ u.name }}</span>
            <span class="ml-auto text-xs font-semibold text-primary">{{ tr('Add') }}</span>
          </button>
        </div>

        <!-- Optional group name once it's more than one person -->
        <input v-if="selected.length > 1" v-model="groupTitle" type="text" :placeholder="tr('Group name (optional)')" class="mt-2 w-full rounded-lg border-line bg-surface-2 text-ink placeholder:text-ink-muted focus:border-primary focus:ring-primary" />

        <div class="mt-3 flex items-center gap-2">
          <button type="button" :disabled="!selected.length" @click="start" class="rounded-c bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-600 disabled:opacity-50">
            {{ selected.length > 1 ? tr('Start group ({n})', { n: selected.length }) : tr('Start conversation') }}
          </button>
          <span class="text-xs text-ink-muted">{{ tr('Add more than one person to start a group.') }}</span>
        </div>
      </div>

      <div class="overflow-hidden rounded-c border border-line bg-surface">
        <Link v-for="c in conversations" :key="c.id" :href="`/messages/${c.id}`" class="flex items-center gap-3 border-b border-line/60 px-4 py-3 last:border-0 hover:bg-surface-2">
          <Avatar :avatar="c.avatar" :size="44" />
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
              <span class="truncate font-bold text-ink">{{ c.title }}</span>
              <span class="ml-auto shrink-0 text-xs text-ink-muted">{{ c.time }}</span>
            </div>
            <div class="truncate text-sm" :class="c.unread ? 'font-semibold text-ink' : 'text-ink-muted'">{{ c.excerpt }}</div>
          </div>
          <span v-if="c.unread" class="h-2.5 w-2.5 shrink-0 rounded-full bg-primary"></span>
        </Link>
        <p v-if="!conversations.length" class="px-4 py-16 text-center text-sm text-ink-muted">{{ tr('No conversations yet. Start one with “New message”.') }}</p>
      </div>
    </div>
  </AppLayout>
</template>
