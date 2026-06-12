<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import Avatar from '@/Components/forum/Avatar.vue';
import Editor from '@/Components/Editor.vue';
import ReadingScrubber from '@/Components/forum/ReadingScrubber.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { t } from '@/lib/i18n';

const props = defineProps<{ conversation: any; messages: any[] }>();

const page = usePage();
const meId = computed(() => Number((page.props as any).auth?.user?.id ?? 0));

const editor = ref<any>(null);
const posting = ref(false);
const thread = ref<HTMLElement | null>(null);

const live = ref<any[]>([...props.messages]);
watch(() => props.messages, (v) => { live.value = [...v]; scrollDown(); });

function mine(m: any) {
  return Number(m.author?.id) === meId.value;
}

function scrollDown() {
  nextTick(() => { if (thread.value) thread.value.scrollTop = thread.value.scrollHeight; });
}

function send() {
  if (!editor.value || editor.value.isEmpty()) return;
  posting.value = true;
  router.post(`/messages/${props.conversation.id}`, { body_html: editor.value.getHTML() }, {
    preserveScroll: true,
    onSuccess: () => editor.value?.clear(),
    onFinish: () => (posting.value = false),
  });
}

// Add people (turn a DM into a group / grow a group)
const adding = ref(false);
const addQuery = ref('');
const addResults = ref<any[]>([]);
let at: any = null;
watch(addQuery, (q) => {
  clearTimeout(at);
  if (!q.trim()) { addResults.value = []; return; }
  at = setTimeout(async () => {
    try {
      const res = await fetch(`/users/search?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
      addResults.value = res.ok ? await res.json() : [];
    } catch { addResults.value = []; }
  }, 200);
});
function addPerson(u: any) {
  router.post(`/messages/${props.conversation.id}/participants`, { user_ids: [u.uid] }, {
    preserveScroll: true,
    onSuccess: () => { addQuery.value = ''; addResults.value = []; adding.value = false; },
  });
}

// realtime
const Echo = () => (window as any).Echo;
let channel: any = null;
onMounted(() => {
  scrollDown();
  if (!Echo()) return;
  channel = Echo().private(`conversation.${props.conversation.id}`).listen('.MessageCreated', (e: any) => {
    if (e?.message && !live.value.some((m) => m.id === e.message.id)) {
      live.value.push(e.message);
      scrollDown();
    }
  });
});
onBeforeUnmount(() => { if (Echo()) Echo().leave(`conversation.${props.conversation.id}`); });
</script>

<template>
  <Head :title="conversation.title" />
  <AppLayout>
    <ReadingScrubber :target="thread" />
    <div class="mx-auto flex h-[calc(100vh-140px)] max-w-[760px] flex-col">
      <div class="mb-3 flex items-center gap-3">
        <Link href="/messages" class="text-ink-muted hover:text-ink" :aria-label="t('Back to messages')">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6" /></svg>
        </Link>
        <!-- Group: stacked avatars; 1:1: partner avatar -->
        <div v-if="conversation.isGroup" class="flex -space-x-2">
          <Avatar v-for="p in conversation.participants.slice(0, 3)" :key="p.id" :avatar="p" :size="32" class="ring-2 ring-surface" />
        </div>
        <Avatar v-else :avatar="conversation.partner" :size="38" />
        <div class="min-w-0">
          <h1 class="truncate text-lg font-bold">{{ conversation.title }}</h1>
          <p v-if="conversation.isGroup" class="text-xs text-ink-muted">{{ t('{n} people', { n: conversation.participants.length }) }}</p>
        </div>
        <button type="button" class="ml-auto inline-flex items-center gap-1.5 rounded-c border border-line bg-surface px-3 py-1.5 text-sm font-semibold text-ink-2 hover:bg-surface-2" @click="adding = !adding">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/></svg>
          {{ t('Add people') }}
        </button>
      </div>

      <!-- Add-people search -->
      <div v-if="adding" class="mb-3 rounded-c border border-line bg-surface p-3">
        <input v-model="addQuery" type="text" :placeholder="t('Search people to add…')" class="w-full rounded-lg border-line bg-surface-2 text-ink placeholder:text-ink-muted focus:border-primary focus:ring-primary" />
        <div v-if="addResults.length" class="mt-2 divide-y divide-line/60">
          <button v-for="u in addResults" :key="u.uid" type="button" class="flex w-full items-center gap-2.5 px-1 py-2 text-left hover:bg-surface-2" @click="addPerson(u)">
            <Avatar :avatar="u" :size="28" />
            <span class="text-sm font-semibold text-ink">{{ u.name }}</span>
            <span class="ml-auto text-xs font-semibold text-primary">{{ t('Add') }}</span>
          </button>
        </div>
      </div>

      <div ref="thread" class="flex-1 space-y-3 overflow-y-auto rounded-c border border-line bg-surface p-4">
        <div v-for="m in live" :key="m.id" class="flex gap-2.5" :class="mine(m) ? 'flex-row-reverse' : ''">
          <Avatar :avatar="m.author" :size="32" />
          <div class="max-w-[75%]">
            <div class="rounded-2xl px-3.5 py-2 text-sm" :class="mine(m) ? 'bg-primary text-white' : 'bg-surface-2 text-ink'">
              <div class="prose-q" :class="mine(m) ? 'prose-onprimary' : ''" v-html="m.html"></div>
            </div>
            <div class="mt-0.5 text-[11px] text-ink-muted" :class="mine(m) ? 'text-right' : ''">{{ m.createdAt }}</div>
          </div>
        </div>
        <p v-if="!live.length" class="py-10 text-center text-sm text-ink-muted">{{ t('Say hello 👋') }}</p>
      </div>

      <div class="mt-3">
        <Editor ref="editor" :placeholder="t('Write a message…')" />
        <div class="mt-2 flex justify-end">
          <button type="button" :disabled="posting" class="rounded-c bg-primary px-5 py-2 text-sm font-semibold text-white hover:bg-primary-600 disabled:opacity-60" @click="send">
            {{ posting ? t('Sending…') : t('Send') }}
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.prose-onprimary :deep(a) { color: #fff; text-decoration: underline; }
</style>
