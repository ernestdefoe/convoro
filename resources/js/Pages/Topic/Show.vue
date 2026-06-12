<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Avatar from '@/Components/forum/Avatar.vue';
import Poll from '@/Components/forum/Poll.vue';
import CategoryIcon from '@/Components/forum/CategoryIcon.vue';
import ReaderMode from '@/Components/forum/ReaderMode.vue';
import ReadingScrubber from '@/Components/forum/ReadingScrubber.vue';
import Editor from '@/Components/Editor.vue';
import Slot from '@/Components/ext/Slot.vue';
import { uploadImage } from '@/lib/upload';
import UploadButton from '@/Components/UploadButton.vue';
import { useAuthModal } from '@/lib/authModal';
import { toast } from '@/lib/toast';
import { t as tr } from '@/lib/i18n';

const auth = useAuthModal();

// Share a post: native share sheet where available, else copy the permalink.
function sharePost(post: any) {
  const url = `${window.location.origin}/t/${props.topic.slug}#post-${post.id}`;
  if (navigator.share) {
    navigator.share({ title: props.topic.title, url }).catch(() => {});
    return;
  }
  if (navigator.clipboard) {
    navigator.clipboard.writeText(url).then(() => toast(tr('Link copied to clipboard'))).catch(() => toast(tr("Couldn't copy link"), 'error'));
  } else {
    toast(url, 'info', 6000);
  }
}

const props = defineProps<{ topic: any; posts: any[]; canReply: boolean; categories?: any[]; allTags?: any[]; references?: { title: string; slug: string }[] }>();
const page = usePage();
const aiEnabled = computed(() => !!(page.props as any).ask?.enabled);
const summary = ref('');
const summarizing = ref(false);
async function summarize() {
  if (summarizing.value) return;
  summarizing.value = true;
  try {
    const r = await fetch(`/api/topics/${props.topic.slug}/summary`, { headers: { Accept: 'application/json' } });
    const d = await r.json();
    summary.value = d.summary || tr('Could not summarize this thread right now.');
  } catch {
    summary.value = tr('Could not summarize this thread right now.');
  } finally {
    summarizing.value = false;
  }
}
const user = computed(() => (page.props as any).auth?.user ?? null);
const loggedIn = computed(() => !!user.value);

const reportModal = ref<{ id: number } | null>(null);
const reportReason = ref('');
const reportSent = ref(false);
function report(post: any) {
  if (!loggedIn.value) { auth.open('login'); return; }
  reportReason.value = ''; reportSent.value = false; reportModal.value = { id: post.id };
}
function submitReport() {
  const m = reportModal.value; if (!m) return;
  router.post('/report', { type: 'post', id: m.id, reason: reportReason.value }, {
    preserveScroll: true,
    onSuccess: () => { reportSent.value = true; setTimeout(() => (reportModal.value = null), 1200); },
  });
}
function canReport(post: any) {
  return loggedIn.value && post.author?.id && post.author.id !== (user.value as any)?.id;
}

const EMOJIS = ['👍', '❤️', '😂', '😮', '😢', '🎉', '🔥'];
const pickerFor = ref<number | null>(null);
const editor = ref<any>(null);
const posting = ref(false);

// edit / delete posts
const editing = ref<any>(null);
const editEditor = ref<any>(null);
const editTitle = ref('');
const editCover = ref<string | null>(null);
const editCategory = ref<number | null>(null);
const editTags = ref<number[]>([]);
const uploadingCover = ref(false);
function openEdit(post: any) {
  editing.value = post;
  if (post.isFirst) {
    editTitle.value = props.topic.title;
    editCover.value = props.topic.cover ?? null;
    editCategory.value = props.topic.categoryId ?? null;
    editTags.value = [...(props.topic.tagIds ?? [])];
  }
}
function toggleEditTag(id: number) {
  const i = editTags.value.indexOf(id);
  if (i === -1) editTags.value.push(id); else editTags.value.splice(i, 1);
}

// Move a post to another topic (admins).
const moving = ref<any>(null);
const moveQuery = ref('');
const moveResults = ref<{ id: number; title: string; slug: string }[]>([]);
let moveTimer: number | null = null;
function openMove(post: any) {
  moving.value = post;
  moveQuery.value = '';
  moveResults.value = [];
  searchTopics();
}
function searchTopics() {
  if (moveTimer) clearTimeout(moveTimer);
  moveTimer = window.setTimeout(async () => {
    try {
      const r = await fetch(`/admin/moderation/topics/search?q=${encodeURIComponent(moveQuery.value.trim())}`, { headers: { Accept: 'application/json' } });
      const d = await r.json();
      moveResults.value = (d.topics || []).filter((t: any) => t.id !== props.topic.id);
    } catch { /* ignore */ }
  }, 300);
}
function moveTo(topicId: number) {
  router.post(`/admin/moderation/posts/${moving.value.id}/move`, { topic_id: topicId }, {
    preserveScroll: true,
    onSuccess: () => { moving.value = null; },
  });
}
async function pickCover(file: File) {
  uploadingCover.value = true;
  try { const { url } = await uploadImage(file); editCover.value = url; } catch { /* ignore */ } finally { uploadingCover.value = false; }
}
function saveEdit() {
  if (!editEditor.value || editEditor.value.isEmpty()) return;
  const payload: Record<string, any> = { body_html: editEditor.value.getHTML() };
  if (editing.value.isFirst) {
    payload.title = editTitle.value;
    payload.cover = editCover.value ?? '';
    payload.category_id = editCategory.value;
    payload.tags = editTags.value;
  }
  router.put(`/posts/${editing.value.id}`, payload, { preserveScroll: true, onSuccess: () => (editing.value = null) });
}
function removePost(post: any) {
  if (confirm(post.isFirst ? tr('Delete this entire topic and all its replies?') : tr('Delete this post?'))) {
    router.delete(`/posts/${post.id}`, { preserveScroll: true });
  }
}

function togglePin() {
  menuFor.value = null;
  router.post(`/t/${props.topic.slug}/pin`, {}, { preserveScroll: true });
}

// Local copy so live-broadcast posts can be appended; resync when the server
// sends fresh props (after our own post / a reaction toggle reload).
const livePosts = ref<any[]>([...props.posts]);
watch(() => props.posts, (val) => { livePosts.value = [...val]; });

// The opening post gets a prominent blog-style treatment; the rest are replies.
const firstPost = computed(() => livePosts.value.find((p) => p.isFirst) ?? livePosts.value[0] ?? null);
const replies = computed(() => livePosts.value.filter((p) => p.id !== firstPost.value?.id));

// Sequential post number within the topic (#1 = opening post). Doubles as a permalink.
function postNumber(post: any): number | null {
  const i = livePosts.value.findIndex((p) => p.id === post?.id);
  return i >= 0 ? i + 1 : null;
}

// ---- Reply & quote a specific post ----
const escapeHtml = (s: string) => String(s).replace(/[&<>]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;' }[c] as string));
// Drop any nested quotes so quoting doesn't pyramid.
const stripQuotes = (html: string) => String(html).replace(/<blockquote[\s\S]*?<\/blockquote>/gi, '').trim();

function focusComposer() {
  if (typeof document === 'undefined') return;
  document.getElementById('reply-composer')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
  setTimeout(() => editor.value?.focus(), 350);
}

// Per-post overflow (Edit/Move/Delete/Report) menu — one open at a time.
const menuFor = ref<number | null>(null);

function replyTo(post: any) {
  menuFor.value = null;
  if (!props.canReply) { if (!loggedIn.value) auth.open('login'); return; }
  // Mention the author so they get a reply notification.
  const handle = post.author?.handle;
  if (handle && post.author?.id !== user.value?.id && editor.value && !editor.value.getHTML().includes('@' + handle)) {
    editor.value.insertContent(`@${handle} `);
  }
  focusComposer();
}

// Select text inside a post → a floating "Quote" tooltip appears.
const quoteTip = ref<{ x: number; y: number; postId: number; author: string } | null>(null);

function onSelectionChange() {
  if (!props.canReply || typeof window === 'undefined') { quoteTip.value = null; return; }
  const sel = window.getSelection();
  if (!sel || sel.isCollapsed || !sel.toString().trim()) { quoteTip.value = null; return; }
  const range = sel.getRangeAt(0);
  const node = range.commonAncestorContainer;
  const el = (node.nodeType === 3 ? node.parentElement : node as HTMLElement);
  const body = el?.closest('[data-post-id]') as HTMLElement | null;
  if (!body) { quoteTip.value = null; return; }
  const rect = range.getBoundingClientRect();
  quoteTip.value = {
    x: rect.left + rect.width / 2,
    y: rect.top,
    postId: Number(body.dataset.postId),
    author: body.dataset.postAuthor || '',
  };
}

function quoteSelection() {
  const sel = window.getSelection();
  const tip = quoteTip.value;
  if (!sel || !tip || sel.isCollapsed) return;
  const div = document.createElement('div');
  div.appendChild(sel.getRangeAt(0).cloneContents());
  const inner = stripQuotes(div.innerHTML) || escapeHtml(sel.toString());
  const link = `${window.location.origin}/t/${props.topic.slug}#post-${tip.postId}`;
  const html = `<blockquote><p><a href="${link}"><strong>${escapeHtml(tip.author)}</strong></a> ${tr('wrote:')}</p>${inner}</blockquote><p></p>`;
  editor.value?.insertContent(html);
  sel.removeAllRanges();
  quoteTip.value = null;
  focusComposer();
}

// ---- Per-reader post translation ----
// A member who reads in, say, English can have Arabic posts auto-translated
// (and vice-versa). Each translation is fetched on demand and cached server-side.
const baseLang = (code: string | null | undefined) => (code ? String(code).split('-')[0].toLowerCase() : '');
const viewerLocale = computed(() => user.value?.locale || (page.props as any).i18n?.locale || 'en');
const autoTranslate = computed(() => !!user.value?.auto_translate);
const translateEnabled = computed(() => !!(page.props as any).ask?.enabled); // same LLM gate as Ask
// postId -> { html, shown, loading, failed, source }
const tx = ref<Record<number, { html?: string; shown: boolean; loading: boolean; failed?: boolean; source?: string | null }>>({});

function needsTranslation(post: any): boolean {
  const src = baseLang(post?.detectedLocale);
  return !!src && src !== baseLang(viewerLocale.value);
}

async function translatePost(post: any) {
  const id = post.id;
  const cur = tx.value[id];
  if (cur?.loading) return;
  // Already have it → just toggle on.
  if (cur?.html) { tx.value[id] = { ...cur, shown: true }; return; }
  tx.value[id] = { shown: false, loading: true };
  try {
    const r = await fetch(`/api/posts/${id}/translate`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
      body: JSON.stringify({ locale: viewerLocale.value }),
    });
    const data = await r.json();
    if (data.translated && data.html) {
      tx.value[id] = { html: data.html, shown: true, loading: false, source: data.source };
    } else {
      tx.value[id] = { shown: false, loading: false, failed: !data.translated };
    }
  } catch {
    tx.value[id] = { shown: false, loading: false, failed: true };
  }
}

function toggleTranslation(post: any) {
  const cur = tx.value[post.id];
  if (cur?.html) { tx.value[post.id] = { ...cur, shown: !cur.shown }; }
  else { translatePost(post); }
}

function displayHtml(post: any): string {
  const cur = tx.value[post.id];
  return cur?.shown && cur.html ? cur.html : post.html;
}

// Auto-translate posts that aren't in the reader's language, when the member opted in.
function autoTranslateAll() {
  if (!autoTranslate.value || !translateEnabled.value) return;
  for (const p of livePosts.value) {
    if (needsTranslation(p) && !tx.value[p.id]) translatePost(p);
  }
}
onMounted(autoTranslateAll);
watch(livePosts, autoTranslateAll, { deep: false });

// ---- Realtime (Reverb presence channel) ----
const hereCount = ref(0);
const typingName = ref<string | null>(null);
let typingTimer: any = null;
let channel: any = null;
const Echo = () => (window as any).Echo;

onMounted(() => {
  if (!Echo()) return;
  channel = Echo().join(`topic.${props.topic.id}`)
    .here((users: any[]) => (hereCount.value = users.length))
    .joining(() => (hereCount.value++))
    .leaving(() => (hereCount.value = Math.max(0, hereCount.value - 1)))
    .listen('.PostCreated', (e: any) => {
      if (e.post && !livePosts.value.some((p) => p.id === e.post.id)) {
        livePosts.value.push(e.post);
      }
    })
    .listenForWhisper('typing', (e: any) => {
      if (user.value && e.name === user.value.name) return;
      typingName.value = e.name;
      clearTimeout(typingTimer);
      typingTimer = setTimeout(() => (typingName.value = null), 2800);
    });
});

// Heartbeat so this discussion can show a "LIVE" badge while members are reading it.
let heartbeatTimer: any = null;
function sendHeartbeat() {
  if (!loggedIn.value) return;
  const xsrf = decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [])[1] || '');
  fetch(`/t/${props.topic.slug}/heartbeat`, { method: 'POST', headers: { 'X-XSRF-TOKEN': xsrf, Accept: 'application/json' }, credentials: 'same-origin' }).catch(() => {});
}
onMounted(() => {
  if (!loggedIn.value) return;
  sendHeartbeat();
  heartbeatTimer = setInterval(sendHeartbeat, 25000);
});
onBeforeUnmount(() => { if (heartbeatTimer) clearInterval(heartbeatTimer); });

// Selection-to-quote: update the tooltip on selection change, hide it on scroll.
const hideQuoteTip = () => (quoteTip.value = null);
onMounted(() => {
  document.addEventListener('selectionchange', onSelectionChange);
  window.addEventListener('scroll', hideQuoteTip, true);
});

onBeforeUnmount(() => {
  if (Echo()) Echo().leave(`topic.${props.topic.id}`);
  document.removeEventListener('selectionchange', onSelectionChange);
  window.removeEventListener('scroll', hideQuoteTip, true);
});

let whisperThrottle = 0;
function onTyping() {
  const now = Date.now();
  if (channel && user.value && now - whisperThrottle > 1200) {
    whisperThrottle = now;
    channel.whisper('typing', { name: user.value.name });
  }
}

function react(postId: number, emoji: string) {
  pickerFor.value = null;
  if (!loggedIn.value) { router.visit('/login'); return; }
  router.post(`/posts/${postId}/react`, { emoji }, { preserveScroll: true, preserveState: false });
}

function submitReply() {
  if (!editor.value || editor.value.isEmpty()) return;
  posting.value = true;
  router.post(`/t/${props.topic.slug}/posts`,
    { body_html: editor.value.getHTML(), body_json: editor.value.getJSON() },
    {
      preserveScroll: true,
      onSuccess: () => editor.value?.clear(),
      onFinish: () => (posting.value = false),
    });
}
</script>

<template>
  <Head :title="topic.title" />
  <AppLayout>
    <ReadingScrubber />
    <div class="mx-auto max-w-3xl">
      <Link href="/" class="mb-3 inline-flex items-center gap-1.5 text-sm font-semibold text-ink-muted hover:text-ink-2">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6" /></svg> {{ tr('Back to Community') }}
      </Link>

      <!-- Blog-style opening post -->
      <article :id="firstPost ? 'post-' + firstPost.id : undefined" class="q-post scroll-mt-24 rounded-c border border-line bg-surface shadow-sm" :class="menuFor === (firstPost?.id ?? -1) ? 'relative z-30' : 'overflow-hidden'">
        <img v-if="topic.cover" :src="topic.cover" alt="" class="h-56 w-full object-cover sm:h-72" />
        <div class="p-6 sm:p-9">
          <div class="flex flex-wrap items-center gap-2">
            <span v-if="topic.category" class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold"
              :style="{ color: topic.category.color, background: topic.category.color + '22' }"><CategoryIcon :icon="topic.category.icon" /> {{ topic.category.name }}</span>
            <span v-if="hereCount > 0" class="inline-flex items-center gap-1.5 rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-bold text-rose-600">
              <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-rose-500"></span>{{ tr('LIVE · {n} here', { n: hereCount }) }}
            </span>
            <ReaderMode v-if="firstPost" :title="topic.title" :html="firstPost.html" :byline="firstPost.author.name" :cover="topic.cover" class="ml-auto" />
          </div>

          <h1 class="mt-3 text-3xl font-extrabold leading-tight tracking-tight sm:text-4xl">
            <span v-if="topic.isPinned" class="mr-2 inline-flex items-center gap-1 rounded-full bg-amber-500/15 px-2.5 py-1 align-middle text-xs font-bold text-amber-600 dark:text-amber-400">
              <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor"><path d="M16 3l5 5-4 1-3 3 1 5-2 2-4-4-5 5-1-1 5-5-4-4 2-2 5 1 3-3z" /></svg>{{ tr('Pinned') }}
            </span>{{ topic.title }}
          </h1>

          <div v-if="firstPost" class="mt-5 flex items-center gap-3">
            <Link :href="firstPost.author.url"><Avatar :avatar="firstPost.author" :size="56" badge /></Link>
            <div>
              <Link :href="firstPost.author.url" class="font-bold hover:underline">{{ firstPost.author.name }}</Link>
              <div class="text-sm text-ink-muted">
                <a :href="'#post-' + firstPost.id" class="font-semibold text-ink-muted hover:text-primary" :title="tr('Permalink to this post')">#{{ postNumber(firstPost) }}</a>
                · {{ firstPost.createdAt }}<span v-if="firstPost.editedAt"> · {{ tr('edited {date}', { date: firstPost.editedAt }) }}</span>
              </div>
            </div>
          </div>

          <div v-if="topic.tags.length" class="mt-4 flex flex-wrap gap-2">
            <span v-for="t in topic.tags" :key="t.slug" class="rounded-full bg-surface-2 px-2.5 py-0.5 text-xs font-semibold text-ink-2">#{{ t.name }}</span>
          </div>

          <div v-if="firstPost?.held" class="mt-5 rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-xs font-semibold text-amber-500">{{ tr('Held for review by the moderation copilot — only you and moderators can see this until it’s approved.') }}</div>
          <div v-if="firstPost" class="prose-q mt-7 max-w-none text-[1.075rem] leading-relaxed text-ink" :data-post-id="firstPost.id" :data-post-author="firstPost.author?.name" v-html="displayHtml(firstPost)"></div>
          <div v-if="firstPost && translateEnabled && (needsTranslation(firstPost) || tx[firstPost.id]?.html)" class="mt-2 flex items-center gap-2 text-xs">
            <button @click="toggleTranslation(firstPost)" class="inline-flex items-center gap-1.5 font-semibold text-primary hover:underline">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 8 6 6M4 14l6-6 2-3M2 5h12M7 2h1M22 22l-5-10-5 10M14 18h6" /></svg>
              <span v-if="tx[firstPost.id]?.loading">{{ tr('Translating…') }}</span>
              <span v-else-if="tx[firstPost.id]?.shown">{{ tr('Show original') }}</span>
              <span v-else-if="tx[firstPost.id]?.failed">{{ tr('Translation unavailable') }}</span>
              <span v-else>{{ tr('Translate') }}</span>
            </button>
            <span v-if="tx[firstPost.id]?.shown" class="text-ink-muted">· {{ tr('Translated by AI') }}</span>
          </div>

          <div v-if="firstPost" class="relative mt-7 flex flex-wrap items-center gap-2 border-t border-line pt-5">
            <button v-for="r in firstPost.reactions" :key="r.emoji" @click="react(firstPost.id, r.emoji)"
              class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[13px] font-semibold"
              :class="r.mine ? 'border-primary/40 bg-primary/15 text-primary' : 'border-line bg-surface-2 text-ink-2 hover:bg-surface'">
              {{ r.emoji }} {{ r.count }}
            </button>
            <button @click="pickerFor = pickerFor === firstPost.id ? null : firstPost.id"
              class="grid h-8 w-8 place-items-center rounded-full border border-line bg-surface-2 text-ink-muted hover:bg-surface" :title="tr('React')">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9" /><path d="M8 14s1.5 2 4 2 4-2 4-2M9 9h.01M15 9h.01" /></svg>
            </button>
            <div v-if="pickerFor === firstPost.id" class="absolute bottom-9 left-0 z-10 flex gap-1 rounded-full border border-line bg-surface px-2.5 py-1.5 shadow-xl">
              <button v-for="e in EMOJIS" :key="e" @click="react(firstPost.id, e)" class="text-xl transition hover:scale-125">{{ e }}</button>
            </div>
            <div class="ml-auto flex items-center gap-1">
              <button v-if="canReply" @click="replyTo(firstPost)" :title="tr('Reply')" class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-[13px] font-semibold text-ink-2 hover:bg-surface-2">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 17 4 12 9 7"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/></svg>
                {{ tr('Reply') }}
              </button>
              <button @click="sharePost(firstPost)" :title="tr('Share')" class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-[13px] font-semibold text-ink-2 hover:bg-surface-2">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4M15.4 6.5 8.6 10.5"/></svg>
                {{ tr('Share') }}
              </button>
              <div v-if="firstPost.canEdit || firstPost.canDelete || canReport(firstPost) || topic.canPin" class="relative">
                <button @click="menuFor = menuFor === firstPost.id ? null : firstPost.id" :title="tr('More')" class="grid h-8 w-8 place-items-center rounded-lg text-ink-muted hover:bg-surface-2">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/></svg>
                </button>
                <div v-if="menuFor === firstPost.id" class="absolute right-0 top-9 z-50 w-44 overflow-hidden rounded-xl border border-line bg-surface py-1 shadow-xl">
                  <button v-if="topic.canPin" @click="togglePin" class="block w-full px-3 py-1.5 text-left text-sm text-ink hover:bg-surface-2">📌 {{ topic.isPinned ? tr('Unpin topic') : tr('Pin topic') }}</button>
                  <button v-if="firstPost.canEdit" @click="menuFor = null; openEdit(firstPost)" class="block w-full px-3 py-1.5 text-left text-sm text-ink hover:bg-surface-2">{{ tr('Edit') }}</button>
                  <button v-if="canReport(firstPost)" @click="menuFor = null; report(firstPost)" class="block w-full px-3 py-1.5 text-left text-sm text-ink hover:bg-surface-2">⚑ {{ tr('Report') }}</button>
                  <button v-if="firstPost.canDelete" @click="menuFor = null; removePost(firstPost)" class="block w-full px-3 py-1.5 text-left text-sm text-red-500 hover:bg-red-500/10">{{ tr('Delete') }}</button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </article>

      <!-- Poll -->
      <Poll v-if="topic.poll" :poll="topic.poll" class="mt-5" />

      <div v-if="references && references.length" class="mt-5 rounded-c border border-line bg-surface p-4">
        <div class="mb-2 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wide text-ink-muted">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1" /></svg>
          {{ tr('Mentioned in') }}
        </div>
        <ul class="space-y-1">
          <li v-for="r in references" :key="r.slug">
            <Link :href="`/t/${r.slug}`" class="text-sm font-semibold text-primary hover:underline">{{ r.title }}</Link>
          </li>
        </ul>
      </div>

      <Slot name="topic:below" :ctx="{ topicId: topic.id, slug: topic.slug }" />

      <!-- Replies -->
      <section v-if="replies.length" class="mt-6">
        <div class="mb-3 flex items-center gap-3">
          <h2 class="text-sm font-bold uppercase tracking-wide text-ink-muted">{{ replies.length === 1 ? tr('{n} reply', { n: replies.length }) : tr('{n} replies', { n: replies.length }) }}</h2>
          <button v-if="aiEnabled && replies.length >= 4" @click="summarize" :disabled="summarizing"
            class="ml-auto inline-flex items-center gap-1.5 rounded-full border border-primary/30 bg-primary/5 px-3 py-1 text-xs font-semibold text-primary hover:bg-primary/10 disabled:opacity-50">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 6h16M4 12h10M4 18h7" /></svg>
            {{ summarizing ? tr('Summarizing…') : tr('Catch me up') }}
          </button>
        </div>
        <div v-if="summary" class="mb-4 rounded-c border border-primary/25 bg-primary/[0.04] p-4">
          <div class="mb-1.5 text-xs font-bold uppercase tracking-wide text-primary">✦ {{ tr('AI summary') }}</div>
          <div class="whitespace-pre-wrap text-sm leading-relaxed text-ink-2">{{ summary }}</div>
        </div>
        <div class="space-y-3">
          <article v-for="post in replies" :key="post.id" :id="'post-' + post.id" class="q-post flex scroll-mt-24 gap-4 rounded-c border border-line bg-surface p-6 shadow-sm" :class="menuFor === post.id ? 'relative z-30' : ''">
            <div class="w-24 shrink-0 text-center">
              <Link :href="post.author.url"><Avatar :avatar="post.author" :size="52" class="mx-auto" badge /></Link>
              <div class="mt-2 text-sm font-bold">{{ post.author.name }}</div>
              <span v-if="post.isAi" class="mt-1 inline-flex items-center gap-1 rounded-full bg-primary/15 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-primary">✦ AI</span>
            </div>
            <div class="min-w-0 flex-1">
              <div class="mb-2.5 text-xs text-ink-muted">
                <a :href="'#post-' + post.id" class="font-semibold text-ink-muted hover:text-primary" :title="tr('Permalink to this post')">#{{ postNumber(post) }}</a>
                · {{ post.createdAt }}<span v-if="post.editedAt"> · {{ tr('edited {date}', { date: post.editedAt }) }}</span>
              </div>
              <div v-if="post.held" class="mb-2 rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-xs font-semibold text-amber-500">{{ tr('Held for review — only you and moderators can see this until it’s approved.') }}</div>
              <div class="prose-q text-ink" :data-post-id="post.id" :data-post-author="post.author?.name" v-html="displayHtml(post)"></div>
              <div v-if="translateEnabled && (needsTranslation(post) || tx[post.id]?.html)" class="mt-1.5 flex items-center gap-2 text-xs">
                <button @click="toggleTranslation(post)" class="inline-flex items-center gap-1.5 font-semibold text-primary hover:underline">
                  <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 8 6 6M4 14l6-6 2-3M2 5h12M7 2h1M22 22l-5-10-5 10M14 18h6" /></svg>
                  <span v-if="tx[post.id]?.loading">{{ tr('Translating…') }}</span>
                  <span v-else-if="tx[post.id]?.shown">{{ tr('Show original') }}</span>
                  <span v-else-if="tx[post.id]?.failed">{{ tr('Translation unavailable') }}</span>
                  <span v-else>{{ tr('Translate') }}</span>
                </button>
                <span v-if="tx[post.id]?.shown" class="text-ink-muted">· {{ tr('Translated by AI') }}</span>
              </div>

              <div class="relative mt-3.5 flex flex-wrap items-center gap-2">
                <button v-for="r in post.reactions" :key="r.emoji" @click="react(post.id, r.emoji)"
                  class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[13px] font-semibold"
                  :class="r.mine ? 'border-primary/40 bg-primary/15 text-primary' : 'border-line bg-surface-2 text-ink-2 hover:bg-surface'">
                  {{ r.emoji }} {{ r.count }}
                </button>
                <button @click="pickerFor = pickerFor === post.id ? null : post.id"
                  class="grid h-8 w-8 place-items-center rounded-full border border-line bg-surface-2 text-ink-muted hover:bg-surface" :title="tr('React')">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9" /><path d="M8 14s1.5 2 4 2 4-2 4-2M9 9h.01M15 9h.01" /></svg>
                </button>
                <div v-if="pickerFor === post.id" class="absolute bottom-9 left-0 z-10 flex gap-1 rounded-full border border-line bg-surface px-2.5 py-1.5 shadow-xl">
                  <button v-for="e in EMOJIS" :key="e" @click="react(post.id, e)" class="text-xl transition hover:scale-125">{{ e }}</button>
                </div>
                <div class="ml-auto flex items-center gap-1">
                  <button v-if="canReply" @click="replyTo(post)" :title="tr('Reply')" class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-[13px] font-semibold text-ink-2 hover:bg-surface-2">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 17 4 12 9 7"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/></svg>
                    {{ tr('Reply') }}
                  </button>
                  <button @click="sharePost(post)" :title="tr('Share')" class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-[13px] font-semibold text-ink-2 hover:bg-surface-2">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4M15.4 6.5 8.6 10.5"/></svg>
                    {{ tr('Share') }}
                  </button>
                  <div v-if="post.canEdit || post.canDelete || post.canMove || canReport(post)" class="relative">
                    <button @click="menuFor = menuFor === post.id ? null : post.id" :title="tr('More')" class="grid h-8 w-8 place-items-center rounded-lg text-ink-muted hover:bg-surface-2">
                      <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><circle cx="5" cy="12" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="19" cy="12" r="1.8"/></svg>
                    </button>
                    <div v-if="menuFor === post.id" class="absolute right-0 top-9 z-50 w-40 overflow-hidden rounded-xl border border-line bg-surface py-1 shadow-xl">
                      <button v-if="post.canEdit" @click="menuFor = null; openEdit(post)" class="block w-full px-3 py-1.5 text-left text-sm text-ink hover:bg-surface-2">{{ tr('Edit') }}</button>
                      <button v-if="post.canMove" @click="menuFor = null; openMove(post)" class="block w-full px-3 py-1.5 text-left text-sm text-ink hover:bg-surface-2">{{ tr('Move') }}</button>
                      <button v-if="canReport(post)" @click="menuFor = null; report(post)" class="block w-full px-3 py-1.5 text-left text-sm text-ink hover:bg-surface-2">⚑ {{ tr('Report') }}</button>
                      <button v-if="post.canDelete" @click="menuFor = null; removePost(post)" class="block w-full px-3 py-1.5 text-left text-sm text-red-500 hover:bg-red-500/10">{{ tr('Delete') }}</button>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </article>
        </div>
      </section>

      <div v-if="typingName" class="mt-3 text-sm italic text-ink-muted">{{ tr('{name} is typing…', { name: typingName }) }}</div>

      <div v-if="canReply" id="reply-composer" class="mt-5 scroll-mt-24">
        <div class="mb-2.5 text-sm font-bold">{{ tr('Reply') }}</div>
        <Editor ref="editor" :placeholder="tr('Share your thoughts… (rich text — no markdown needed)')" @typing="onTyping" />
        <div class="mt-3 flex items-center">
          <span class="text-xs text-ink-muted">{{ tr('Rich text · drag, drop or paste images — auto-converted to WebP') }}</span>
          <button @click="submitReply" :disabled="posting"
            class="ml-auto rounded-c bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-primary/30 hover:bg-primary-600 disabled:opacity-60">
            {{ posting ? tr('Posting…') : tr('Post reply') }}
          </button>
        </div>
      </div>
      <div v-else-if="!loggedIn" class="mt-5 rounded-c border border-line bg-surface p-5 text-center text-sm text-ink-2">
        <button type="button" class="font-semibold text-primary hover:underline" @click="auth.open('login')">{{ tr('Log in') }}</button> {{ tr('to join the conversation.') }}
      </div>
    </div>

    <!-- Edit post modal -->
    <div v-if="editing" class="fixed inset-0 z-[80] flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-black/50" @click="editing = null"></div>
      <div class="relative max-h-[88vh] w-full max-w-2xl overflow-y-auto rounded-c border border-line bg-surface p-5 shadow-2xl">
        <div class="mb-2.5 text-sm font-bold text-ink">{{ editing.isFirst ? tr('Edit topic') : tr('Edit post') }}</div>

        <template v-if="editing.isFirst">
          <label class="block text-xs font-semibold uppercase tracking-wide text-ink-muted">{{ tr('Title') }}</label>
          <input v-model="editTitle" type="text" maxlength="160"
            class="mb-3 mt-1 w-full rounded-c border-line bg-surface-2 text-ink focus:border-primary focus:ring-primary" />

          <label class="block text-xs font-semibold uppercase tracking-wide text-ink-muted">{{ tr('Cover image') }}</label>
          <div class="mb-3 mt-1.5 flex items-center gap-3">
            <div class="grid h-16 w-28 shrink-0 place-items-center overflow-hidden rounded-lg border border-line bg-surface-2">
              <img v-if="editCover" :src="editCover" :alt="tr('cover')" class="h-full w-full object-cover" />
              <span v-else class="text-[11px] text-ink-muted">{{ tr('No cover') }}</span>
            </div>
            <UploadButton :uploading="uploadingCover" :label="tr('Choose cover')" accept="image/png,image/jpeg,image/webp" @file="pickCover" />
            <button v-if="editCover" type="button" class="text-sm text-ink-muted hover:text-red-500" @click="editCover = null">{{ tr('Remove') }}</button>
          </div>

          <template v-if="categories && categories.length">
            <label class="block text-xs font-semibold uppercase tracking-wide text-ink-muted">{{ tr('Category') }}</label>
            <select v-model="editCategory" class="mb-3 mt-1 w-full rounded-c border-line bg-surface-2 text-ink focus:border-primary focus:ring-primary">
              <option :value="null">{{ tr('No category') }}</option>
              <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
            </select>
          </template>

          <template v-if="allTags && allTags.length">
            <label class="block text-xs font-semibold uppercase tracking-wide text-ink-muted">{{ tr('Tags') }}</label>
            <div class="mb-3 mt-1.5 flex flex-wrap gap-2">
              <button v-for="t in allTags" :key="t.id" type="button" @click="toggleEditTag(t.id)"
                class="rounded-full border px-2.5 py-1 text-xs font-semibold"
                :class="editTags.includes(t.id) ? 'border-primary bg-primary/15 text-primary' : 'border-line bg-surface-2 text-ink-2 hover:bg-surface'">
                #{{ t.name }}
              </button>
            </div>
          </template>
        </template>

        <Editor ref="editEditor" :content="editing.html" />
        <div class="mt-3 flex items-center gap-2">
          <button @click="saveEdit" class="rounded-c bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-600">{{ tr('Save') }}</button>
          <button @click="editing = null" class="rounded-lg px-3 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2">{{ tr('Cancel') }}</button>
        </div>
      </div>
    </div>

    <!-- Move post modal -->
    <div v-if="moving" class="fixed inset-0 z-[80] flex items-center justify-center p-4" @keydown.esc="moving = null">
      <div class="absolute inset-0 bg-black/50" @click="moving = null"></div>
      <div class="relative w-full max-w-md rounded-c border border-line bg-surface p-5 shadow-2xl">
        <div class="mb-2.5 text-sm font-bold text-ink">{{ tr('Move this reply to another topic') }}</div>
        <input v-model="moveQuery" type="search" :placeholder="tr('Search topics by title…')" @input="searchTopics"
          class="w-full rounded-c border-line bg-surface-2 text-ink placeholder:text-ink-muted focus:border-primary focus:ring-primary" />
        <ul class="mt-3 max-h-72 space-y-1 overflow-y-auto">
          <li v-for="t in moveResults" :key="t.id">
            <button @click="moveTo(t.id)" class="w-full truncate rounded-lg px-3 py-2 text-left text-sm text-ink-2 hover:bg-surface-2 hover:text-ink">{{ t.title }}</button>
          </li>
          <li v-if="!moveResults.length" class="px-3 py-2 text-sm text-ink-muted">{{ tr('No matching topics.') }}</li>
        </ul>
        <div class="mt-3 text-right">
          <button @click="moving = null" class="rounded-lg px-3 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2">{{ tr('Cancel') }}</button>
        </div>
      </div>
    </div>

    <!-- Report post modal -->
    <div v-if="reportModal" class="fixed inset-0 z-[80] flex items-center justify-center p-4" @keydown.esc="reportModal = null">
      <div class="absolute inset-0 bg-black/50" @click="reportModal = null"></div>
      <div class="relative w-full max-w-md rounded-c border border-line bg-surface p-6 shadow-2xl">
        <template v-if="reportSent">
          <div class="py-4 text-center">
            <div class="text-3xl">✅</div>
            <p class="mt-2 font-semibold text-ink">{{ tr('Thanks — our moderators will take a look.') }}</p>
          </div>
        </template>
        <template v-else>
          <h3 class="text-lg font-bold text-ink">{{ tr('Report this post') }}</h3>
          <p class="mt-1 text-sm text-ink-muted">{{ tr("Flag this for the moderators. Tell us what's wrong (optional).") }}</p>
          <textarea v-model="reportReason" rows="3" :placeholder="tr('Reason (optional)…')"
            class="mt-3 w-full rounded-c border-line bg-surface-2 text-sm text-ink focus:border-primary focus:ring-primary"></textarea>
          <div class="mt-4 flex justify-end gap-2">
            <button @click="reportModal = null" class="rounded-lg px-4 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2">{{ tr('Cancel') }}</button>
            <button @click="submitReport" class="rounded-c bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-600">{{ tr('Submit report') }}</button>
          </div>
        </template>
      </div>
    </div>

    <!-- Click-away catcher for the open post menu -->
    <div v-if="menuFor !== null" class="fixed inset-0 z-10" @click="menuFor = null"></div>

    <!-- Floating "Quote selection" tooltip -->
    <button v-if="quoteTip" @mousedown.prevent @click="quoteSelection"
      :style="{ position: 'fixed', left: quoteTip.x + 'px', top: (quoteTip.y - 44) + 'px', transform: 'translateX(-50%)', zIndex: 60 }"
      class="inline-flex items-center gap-1.5 rounded-lg bg-ink px-3 py-1.5 text-xs font-bold text-surface shadow-xl hover:opacity-90">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M7 7H4v6h3l-2 4h3l2-4V7H7zm10 0h-3v6h3l-2 4h3l2-4V7h-3z"/></svg>
      {{ tr('Quote') }}
    </button>
  </AppLayout>
</template>
