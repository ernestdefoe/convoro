<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import Avatar from '@/Components/forum/Avatar.vue';
import Editor from '@/Components/Editor.vue';
import ConversationList from '@/Components/messages/ConversationList.vue';
import ReadingScrubber from '@/Components/forum/ReadingScrubber.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { t } from '@/lib/i18n';

const props = defineProps<{ conversation: any; messages: any[]; conversations?: any[]; activeId?: number | null }>();

const page = usePage();
const meId = computed(() => Number((page.props as any).auth?.user?.id ?? 0));

const editor = ref<any>(null);
const posting = ref(false);
const thread = ref<HTMLElement | null>(null);

const live = ref<any[]>([...props.messages]);
watch(() => props.messages, (v) => { live.value = [...v]; scrollDown(); autoTranslateAll(); });

function mine(m: any) {
  return Number(m.author?.id) === meId.value;
}

// ---- Per-reader DM translation (mirrors Topic post translation) ----
const user = computed(() => (page.props as any).auth?.user ?? null);
const baseLang = (code: string | null | undefined) => (code ? String(code).split('-')[0].toLowerCase() : '');
const viewerLocale = computed(() => user.value?.locale || (page.props as any).i18n?.locale || 'en');
const autoTranslate = computed(() => !!user.value?.auto_translate);
const translateEnabled = computed(() => !!(page.props as any).ask?.enabled); // same LLM gate as Ask
const tx = ref<Record<number, { html?: string; shown: boolean; loading: boolean; failed?: boolean; source?: string | null }>>({});

function xsrf(): string {
  return decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
}
function needsTranslation(m: any): boolean {
  const src = baseLang(m?.detectedLocale);
  return !!src && src !== baseLang(viewerLocale.value);
}
async function translateMessage(m: any) {
  const id = m.id;
  const cur = tx.value[id];
  if (cur?.loading) return;
  if (cur?.html) { tx.value[id] = { ...cur, shown: true }; return; }
  tx.value[id] = { shown: false, loading: true };
  try {
    const r = await fetch(`/api/messages/${id}/translate`, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrf(), Accept: 'application/json' },
      body: JSON.stringify({ locale: viewerLocale.value }),
    });
    const data = await r.json();
    if (data.translated && data.html) tx.value[id] = { html: data.html, shown: true, loading: false, source: data.source };
    else tx.value[id] = { shown: false, loading: false, failed: !data.translated };
  } catch { tx.value[id] = { shown: false, loading: false, failed: true }; }
}
function toggleTranslation(m: any) {
  const cur = tx.value[m.id];
  if (cur?.html) tx.value[m.id] = { ...cur, shown: !cur.shown };
  else translateMessage(m);
}
function displayHtml(m: any): string {
  const cur = tx.value[m.id];
  return cur?.shown && cur.html ? cur.html : m.html;
}
function autoTranslateAll() {
  if (!autoTranslate.value || !translateEnabled.value) return;
  for (const m of live.value) {
    if (needsTranslation(m) && !tx.value[m.id]) translateMessage(m);
  }
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

// ---- Reply + quoting (client-side blockquote, mirrors the forum) ----
function escapeHtml(s: string): string {
  return s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
function stripQuotes(html: string): string {
  const d = document.createElement('div');
  d.innerHTML = html;
  d.querySelectorAll('blockquote').forEach((b) => b.remove());
  return d.innerHTML.trim();
}
function insertQuote(author: string, innerHtml: string) {
  if (!editor.value) return;
  const inner = stripQuotes(innerHtml);
  const html = `<blockquote><p><strong>${escapeHtml(author)}</strong> ${t('said:')}</p>${inner}</blockquote><p></p>`;
  editor.value.insertContent(html);
  editor.value.focus();
}
function replyTo(m: any) {
  insertQuote(m.author?.name ?? '', displayHtml(m));
}

// Floating "Quote" button when text inside a message is selected.
const quoteTip = ref<{ x: number; y: number; author: string; html: string } | null>(null);
function onSelectionChange() {
  const sel = window.getSelection();
  if (!sel || sel.isCollapsed || !sel.toString().trim()) { quoteTip.value = null; return; }
  const anchor = sel.anchorNode;
  const el = anchor && anchor.nodeType === 3 ? anchor.parentElement : (anchor as HTMLElement | null);
  const bubble = el?.closest('[data-msg-id]') as HTMLElement | null;
  if (!bubble) { quoteTip.value = null; return; }
  const rect = sel.getRangeAt(0).getBoundingClientRect();
  const div = document.createElement('div');
  div.appendChild(sel.getRangeAt(0).cloneContents());
  quoteTip.value = {
    x: rect.left + rect.width / 2,
    y: rect.top - 6,
    author: bubble.getAttribute('data-msg-author') || '',
    html: div.innerHTML || escapeHtml(sel.toString()),
  };
}
function quoteSelection() {
  if (!quoteTip.value) return;
  insertQuote(quoteTip.value.author, quoteTip.value.html);
  quoteTip.value = null;
  window.getSelection()?.removeAllRanges();
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

// Typing indicator (client-event whisper on the conversation channel).
const typingName = ref<string | null>(null);
let typingTimer: any = null;
let whisperThrottle = 0;
function onTyping() {
  const now = Date.now();
  if (channel && user.value && now - whisperThrottle > 1200) {
    whisperThrottle = now;
    channel.whisper('typing', { name: user.value.name });
  }
}

onMounted(() => {
  scrollDown();
  autoTranslateAll();
  document.addEventListener('selectionchange', onSelectionChange);
  if (!Echo()) return;
  channel = Echo().private(`conversation.${props.conversation.id}`)
    .listen('.MessageCreated', (e: any) => {
      if (e?.message && !live.value.some((m) => m.id === e.message.id)) {
        live.value.push(e.message);
        scrollDown();
        if (needsTranslation(e.message)) translateMessage(e.message);
      }
    })
    .listenForWhisper('typing', (e: any) => {
      if (user.value && e.name === user.value.name) return;
      typingName.value = e.name;
      clearTimeout(typingTimer);
      typingTimer = setTimeout(() => (typingName.value = null), 2800);
    });
});
onBeforeUnmount(() => {
  document.removeEventListener('selectionchange', onSelectionChange);
  if (Echo()) Echo().leave(`conversation.${props.conversation.id}`);
});
</script>

<template>
  <Head :title="conversation.title" />
  <AppLayout>
    <ReadingScrubber :target="thread" />
    <!-- dvh + a taller mobile offset (header + bottom tab bar) so only the
         message list scrolls, never the page, on phones. -->
    <div class="mx-auto h-[calc(100dvh-13rem)] max-w-[1100px] overflow-hidden rounded-c border border-line bg-surface md:grid md:h-[calc(100vh-150px)] md:grid-cols-[340px_1fr]">
      <!-- Rail (desktop only — on mobile the open thread takes the full width) -->
      <div class="hidden h-full flex-col border-r border-line p-4 md:flex">
        <ConversationList :conversations="conversations || []" :active-id="activeId" @new="router.visit('/messages')" />
      </div>

      <!-- Open conversation -->
      <div class="flex h-full min-h-0 flex-col p-4">
      <div class="mb-3 flex items-center gap-3">
        <Link href="/messages" class="text-ink-muted hover:text-ink md:hidden" :aria-label="t('Back to messages')">
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

      <div ref="thread" class="min-h-0 flex-1 space-y-3 overflow-y-auto rounded-c border border-line bg-surface p-4">
        <div v-for="m in live" :key="m.id" class="flex gap-2.5" :class="mine(m) ? 'flex-row-reverse' : ''">
          <Avatar :avatar="m.author" :size="32" />
          <div class="max-w-[75%]">
            <div class="rounded-2xl px-3.5 py-2 text-sm" :class="mine(m) ? 'bg-primary text-white' : 'bg-surface-2 text-ink'">
              <div class="prose-q" :class="mine(m) ? 'prose-onprimary' : ''" :data-msg-id="m.id" :data-msg-author="m.author?.name" v-html="displayHtml(m)"></div>
            </div>
            <div class="mt-0.5 flex items-center gap-2 text-[11px] text-ink-muted" :class="mine(m) ? 'flex-row-reverse text-right' : ''">
              <span>{{ m.createdAt }}</span>
              <button type="button" class="font-semibold text-ink-muted hover:text-primary" @click="replyTo(m)">{{ t('Reply') }}</button>
              <button
                v-if="translateEnabled && (needsTranslation(m) || tx[m.id]?.html)"
                type="button"
                class="font-semibold text-primary hover:underline"
                @click="toggleTranslation(m)"
              >
                {{ tx[m.id]?.loading ? t('Translating…') : tx[m.id]?.shown ? t('Show original') : t('Translate') }}
              </button>
              <span v-if="tx[m.id]?.failed" class="text-ink-muted">{{ t('Translation unavailable') }}</span>
            </div>
          </div>
        </div>
        <p v-if="!live.length" class="py-10 text-center text-sm text-ink-muted">{{ t('Say hello 👋') }}</p>
      </div>

      <p v-if="typingName" class="h-4 shrink-0 px-1 text-xs italic text-ink-muted">{{ t('{name} is typing…', { name: typingName }) }}</p>

      <div class="mt-1 shrink-0">
        <Editor ref="editor" :placeholder="t('Write a message…')" @typing="onTyping" />
        <div class="mt-2 flex justify-end">
          <button type="button" :disabled="posting" class="rounded-c bg-primary px-5 py-2 text-sm font-semibold text-white hover:bg-primary-600 disabled:opacity-60" @click="send">
            {{ posting ? t('Sending…') : t('Send') }}
          </button>
        </div>
      </div>
      </div>
    </div>

    <!-- Floating "Quote" button shown over a text selection inside a message.
         Uses the same theme tokens as the forum tooltip (text-surface inverts
         correctly in light/dark) — not a hard-coded colour. -->
    <button
      v-if="quoteTip"
      type="button"
      class="inline-flex -translate-x-1/2 -translate-y-full items-center gap-1.5 rounded-lg bg-ink px-3 py-1.5 text-xs font-bold text-surface shadow-xl hover:opacity-90"
      :style="{ position: 'fixed', left: quoteTip.x + 'px', top: quoteTip.y + 'px', zIndex: 60 }"
      @mousedown.prevent="quoteSelection"
    >
      <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M7 7H4v6h3l-2 4h3l2-4V7H7zm10 0h-3v6h3l-2 4h3l2-4V7h-3z" /></svg>
      {{ t('Quote') }}
    </button>
  </AppLayout>
</template>

<style scoped>
.prose-onprimary :deep(a) { color: #fff; text-decoration: underline; }
</style>
