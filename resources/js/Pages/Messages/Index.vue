<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import Avatar from '@/Components/forum/Avatar.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

defineProps<{ conversations: any[] }>();

const composing = ref(false);
const query = ref('');
const results = ref<any[]>([]);
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

function startWith(u: any) {
  // user search returns id = slug; we need the numeric id — store both? search returns slug only,
  // so resolve by posting the slug is wrong. We post the numeric user id via a dedicated field.
  router.post('/messages', { user_id: u.uid }, { preserveScroll: true });
}
</script>

<template>
  <Head title="Messages" />
  <AppLayout>
    <div class="mx-auto max-w-[760px]">
      <div class="mb-5 flex items-center justify-between">
        <h1 class="text-2xl font-extrabold tracking-tight">Messages</h1>
        <button type="button" class="rounded-c bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-600" @click="composing = !composing">New message</button>
      </div>

      <div v-if="composing" class="mb-4 rounded-c border border-line bg-surface p-3">
        <input v-model="query" type="text" placeholder="Search people…" class="w-full rounded-lg border-line bg-surface-2 text-ink placeholder:text-ink-muted focus:border-primary focus:ring-primary" />
        <div v-if="results.length" class="mt-2 divide-y divide-line/60">
          <button v-for="u in results" :key="u.uid" type="button" class="flex w-full items-center gap-2.5 px-1 py-2 text-left hover:bg-surface-2" @click="startWith(u)">
            <Avatar :avatar="u" :size="30" />
            <span class="text-sm font-semibold text-ink">{{ u.name }}</span>
          </button>
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
        <p v-if="!conversations.length" class="px-4 py-16 text-center text-sm text-ink-muted">No conversations yet. Start one with “New message”.</p>
      </div>
    </div>
  </AppLayout>
</template>
