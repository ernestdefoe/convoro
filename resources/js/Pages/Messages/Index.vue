<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import Avatar from '@/Components/forum/Avatar.vue';
import ConversationList from '@/Components/messages/ConversationList.vue';
import { Head, router } from '@inertiajs/vue3';
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
function cancelCompose() {
  composing.value = false;
  selected.value = [];
  query.value = '';
  results.value = [];
  groupTitle.value = '';
}
</script>

<template>
  <Head :title="tr('Messages')" />
  <AppLayout>
    <div class="mx-auto h-[calc(100dvh-13rem)] max-w-[1100px] overflow-hidden rounded-c border border-line bg-surface md:grid md:h-[calc(100vh-150px)] md:grid-cols-[340px_1fr]">
      <!-- Rail -->
      <div class="h-full flex-col border-line p-4 md:border-r" :class="composing ? 'hidden md:flex' : 'flex'">
        <ConversationList :conversations="conversations" :active-id="null" @new="composing = true" />
      </div>

      <!-- Right pane: compose, or an empty prompt -->
      <div class="h-full overflow-y-auto p-5" :class="composing ? 'block' : 'hidden md:block'">
        <template v-if="composing">
          <div class="mb-4 flex items-center gap-3">
            <button type="button" class="text-ink-muted hover:text-ink md:hidden" :aria-label="tr('Back')" @click="cancelCompose">
              <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6" /></svg>
            </button>
            <h2 class="text-lg font-bold text-ink">{{ tr('New message') }}</h2>
            <button type="button" class="ml-auto text-sm font-semibold text-ink-muted hover:text-ink" @click="cancelCompose">{{ tr('Cancel') }}</button>
          </div>

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

          <input v-if="selected.length > 1" v-model="groupTitle" type="text" :placeholder="tr('Group name (optional)')" class="mt-2 w-full rounded-lg border-line bg-surface-2 text-ink placeholder:text-ink-muted focus:border-primary focus:ring-primary" />

          <div class="mt-3 flex items-center gap-2">
            <button type="button" :disabled="!selected.length" @click="start" class="rounded-c bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-600 disabled:opacity-50">
              {{ selected.length > 1 ? tr('Start group ({n})', { n: selected.length }) : tr('Start conversation') }}
            </button>
            <span class="text-xs text-ink-muted">{{ tr('Add more than one person to start a group.') }}</span>
          </div>
        </template>

        <div v-else class="flex h-full flex-col items-center justify-center text-center text-ink-muted">
          <svg width="44" height="44" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="mb-3 opacity-60"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" /></svg>
          <p class="text-sm font-semibold">{{ tr('Select a conversation') }}</p>
          <p class="mt-1 text-xs">{{ tr('Or start a new one with the + button.') }}</p>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
