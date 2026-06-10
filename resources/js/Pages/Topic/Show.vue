<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Avatar from '@/Components/forum/Avatar.vue';
import CategoryIcon from '@/Components/forum/CategoryIcon.vue';
import ReaderMode from '@/Components/forum/ReaderMode.vue';
import ReadingScrubber from '@/Components/forum/ReadingScrubber.vue';
import Editor from '@/Components/Editor.vue';
import Slot from '@/Components/ext/Slot.vue';
import { uploadImage } from '@/lib/upload';
import { useAuthModal } from '@/lib/authModal';

const auth = useAuthModal();

const props = defineProps<{ topic: any; posts: any[]; canReply: boolean }>();
const page = usePage();
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
const uploadingCover = ref(false);
function openEdit(post: any) {
  editing.value = post;
  if (post.isFirst) {
    editTitle.value = props.topic.title;
    editCover.value = props.topic.cover ?? null;
  }
}
async function pickCover(e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0];
  if (!file) return;
  uploadingCover.value = true;
  try { const { url } = await uploadImage(file); editCover.value = url; } catch { /* ignore */ } finally { uploadingCover.value = false; }
}
function saveEdit() {
  if (!editEditor.value || editEditor.value.isEmpty()) return;
  const payload: Record<string, any> = { body_html: editEditor.value.getHTML() };
  if (editing.value.isFirst) {
    payload.title = editTitle.value;
    payload.cover = editCover.value ?? '';
  }
  router.put(`/posts/${editing.value.id}`, payload, { preserveScroll: true, onSuccess: () => (editing.value = null) });
}
function removePost(post: any) {
  if (confirm(post.isFirst ? 'Delete this entire topic and all its replies?' : 'Delete this post?')) {
    router.delete(`/posts/${post.id}`, { preserveScroll: true });
  }
}

// Local copy so live-broadcast posts can be appended; resync when the server
// sends fresh props (after our own post / a reaction toggle reload).
const livePosts = ref<any[]>([...props.posts]);
watch(() => props.posts, (val) => { livePosts.value = [...val]; });

// The opening post gets a prominent blog-style treatment; the rest are replies.
const firstPost = computed(() => livePosts.value.find((p) => p.isFirst) ?? livePosts.value[0] ?? null);
const replies = computed(() => livePosts.value.filter((p) => p.id !== firstPost.value?.id));

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

onBeforeUnmount(() => {
  if (Echo()) Echo().leave(`topic.${props.topic.id}`);
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
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6" /></svg> Back to Community
      </Link>

      <!-- Blog-style opening post -->
      <article class="q-post overflow-hidden rounded-c border border-line bg-surface shadow-sm">
        <img v-if="topic.cover" :src="topic.cover" alt="" class="h-56 w-full object-cover sm:h-72" />
        <div class="p-6 sm:p-9">
          <div class="flex flex-wrap items-center gap-2">
            <span v-if="topic.category" class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold"
              :style="{ color: topic.category.color, background: topic.category.color + '22' }"><CategoryIcon :icon="topic.category.icon" /> {{ topic.category.name }}</span>
            <span v-if="hereCount > 0" class="inline-flex items-center gap-1.5 rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-bold text-rose-600">
              <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-rose-500"></span>LIVE · {{ hereCount }} here
            </span>
            <ReaderMode v-if="firstPost" :title="topic.title" :html="firstPost.html" :byline="firstPost.author.name" :cover="topic.cover" class="ml-auto" />
          </div>

          <h1 class="mt-3 text-3xl font-extrabold leading-tight tracking-tight sm:text-4xl">{{ topic.title }}</h1>

          <div v-if="firstPost" class="mt-5 flex items-center gap-3">
            <Link :href="firstPost.author.url"><Avatar :avatar="firstPost.author" :size="46" /></Link>
            <div>
              <Link :href="firstPost.author.url" class="font-bold hover:underline">{{ firstPost.author.name }}</Link>
              <div class="text-sm text-ink-muted">{{ firstPost.createdAt }}<span v-if="firstPost.editedAt"> · edited {{ firstPost.editedAt }}</span></div>
            </div>
          </div>

          <div v-if="topic.tags.length" class="mt-4 flex flex-wrap gap-2">
            <span v-for="t in topic.tags" :key="t.slug" class="rounded-full bg-surface-2 px-2.5 py-0.5 text-xs font-semibold text-ink-2">#{{ t.name }}</span>
          </div>

          <div v-if="firstPost" class="prose-q mt-7 max-w-none text-[1.075rem] leading-relaxed text-ink" v-html="firstPost.html"></div>

          <div v-if="firstPost" class="relative mt-7 flex flex-wrap items-center gap-2 border-t border-line pt-5">
            <button v-for="r in firstPost.reactions" :key="r.emoji" @click="react(firstPost.id, r.emoji)"
              class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[13px] font-semibold"
              :class="r.mine ? 'border-primary/40 bg-primary/15 text-primary' : 'border-line bg-surface-2 text-ink-2 hover:bg-surface'">
              {{ r.emoji }} {{ r.count }}
            </button>
            <button @click="pickerFor = pickerFor === firstPost.id ? null : firstPost.id"
              class="grid h-8 w-8 place-items-center rounded-full border border-line bg-surface-2 text-ink-muted hover:bg-surface" title="React">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9" /><path d="M8 14s1.5 2 4 2 4-2 4-2M9 9h.01M15 9h.01" /></svg>
            </button>
            <div v-if="pickerFor === firstPost.id" class="absolute bottom-9 left-0 z-10 flex gap-1 rounded-full border border-line bg-surface px-2.5 py-1.5 shadow-xl">
              <button v-for="e in EMOJIS" :key="e" @click="react(firstPost.id, e)" class="text-xl transition hover:scale-125">{{ e }}</button>
            </div>
            <div v-if="firstPost.canEdit || firstPost.canDelete || canReport(firstPost)" class="ml-auto flex gap-1">
              <button v-if="firstPost.canEdit" @click="openEdit(firstPost)" class="rounded-lg px-2.5 py-1 text-[13px] font-semibold text-ink-2 hover:bg-surface-2">Edit</button>
              <button v-if="firstPost.canDelete" @click="removePost(firstPost)" class="rounded-lg px-2.5 py-1 text-[13px] font-semibold text-red-500 hover:bg-red-500/10">Delete</button>
              <button v-if="canReport(firstPost)" @click="report(firstPost)" title="Report to moderators" class="rounded-lg px-2.5 py-1 text-[13px] font-semibold text-ink-muted hover:bg-surface-2 hover:text-ink-2">⚑ Report</button>
            </div>
          </div>
        </div>
      </article>

      <Slot name="topic:below" :ctx="{ topicId: topic.id, slug: topic.slug }" />

      <!-- Replies -->
      <section v-if="replies.length" class="mt-6">
        <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-ink-muted">{{ replies.length }} {{ replies.length === 1 ? 'reply' : 'replies' }}</h2>
        <div class="space-y-3">
          <article v-for="post in replies" :key="post.id" class="q-post flex gap-4 rounded-c border border-line bg-surface p-6 shadow-sm">
            <div class="w-24 shrink-0 text-center">
              <Link :href="post.author.url"><Avatar :avatar="post.author" :size="44" class="mx-auto" /></Link>
              <div class="mt-2 text-sm font-bold">{{ post.author.name }}</div>
            </div>
            <div class="min-w-0 flex-1">
              <div class="mb-2.5 text-xs text-ink-muted">{{ post.createdAt }}<span v-if="post.editedAt"> · edited {{ post.editedAt }}</span></div>
              <div class="prose-q text-ink" v-html="post.html"></div>

              <div class="relative mt-3.5 flex flex-wrap items-center gap-2">
                <button v-for="r in post.reactions" :key="r.emoji" @click="react(post.id, r.emoji)"
                  class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[13px] font-semibold"
                  :class="r.mine ? 'border-primary/40 bg-primary/15 text-primary' : 'border-line bg-surface-2 text-ink-2 hover:bg-surface'">
                  {{ r.emoji }} {{ r.count }}
                </button>
                <button @click="pickerFor = pickerFor === post.id ? null : post.id"
                  class="grid h-8 w-8 place-items-center rounded-full border border-line bg-surface-2 text-ink-muted hover:bg-surface" title="React">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9" /><path d="M8 14s1.5 2 4 2 4-2 4-2M9 9h.01M15 9h.01" /></svg>
                </button>
                <div v-if="pickerFor === post.id" class="absolute bottom-9 left-0 z-10 flex gap-1 rounded-full border border-line bg-surface px-2.5 py-1.5 shadow-xl">
                  <button v-for="e in EMOJIS" :key="e" @click="react(post.id, e)" class="text-xl transition hover:scale-125">{{ e }}</button>
                </div>
                <div v-if="post.canEdit || post.canDelete || canReport(post)" class="ml-auto flex gap-1">
                  <button v-if="post.canEdit" @click="openEdit(post)" class="rounded-lg px-2.5 py-1 text-[13px] font-semibold text-ink-2 hover:bg-surface-2">Edit</button>
                  <button v-if="post.canDelete" @click="removePost(post)" class="rounded-lg px-2.5 py-1 text-[13px] font-semibold text-red-500 hover:bg-red-500/10">Delete</button>
                  <button v-if="canReport(post)" @click="report(post)" title="Report to moderators" class="rounded-lg px-2.5 py-1 text-[13px] font-semibold text-ink-muted hover:bg-surface-2 hover:text-ink-2">⚑ Report</button>
                </div>
              </div>
            </div>
          </article>
        </div>
      </section>

      <div v-if="typingName" class="mt-3 text-sm italic text-ink-muted">{{ typingName }} is typing…</div>

      <div v-if="canReply" class="mt-5">
        <div class="mb-2.5 text-sm font-bold">Reply</div>
        <Editor ref="editor" placeholder="Share your thoughts… (rich text — no markdown needed)" @typing="onTyping" />
        <div class="mt-3 flex items-center">
          <span class="text-xs text-ink-muted">Rich text · drag, drop or paste images — auto-converted to WebP</span>
          <button @click="submitReply" :disabled="posting"
            class="ml-auto rounded-c bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-primary/30 hover:bg-primary-600 disabled:opacity-60">
            {{ posting ? 'Posting…' : 'Post reply' }}
          </button>
        </div>
      </div>
      <div v-else-if="!loggedIn" class="mt-5 rounded-c border border-line bg-surface p-5 text-center text-sm text-ink-2">
        <button type="button" class="font-semibold text-primary hover:underline" @click="auth.open('login')">Log in</button> to join the conversation.
      </div>
    </div>

    <!-- Edit post modal -->
    <div v-if="editing" class="fixed inset-0 z-[80] flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-black/50" @click="editing = null"></div>
      <div class="relative max-h-[88vh] w-full max-w-2xl overflow-y-auto rounded-c border border-line bg-surface p-5 shadow-2xl">
        <div class="mb-2.5 text-sm font-bold text-ink">{{ editing.isFirst ? 'Edit topic' : 'Edit post' }}</div>

        <template v-if="editing.isFirst">
          <label class="block text-xs font-semibold uppercase tracking-wide text-ink-muted">Title</label>
          <input v-model="editTitle" type="text" maxlength="160"
            class="mb-3 mt-1 w-full rounded-c border-line bg-surface-2 text-ink focus:border-primary focus:ring-primary" />

          <label class="block text-xs font-semibold uppercase tracking-wide text-ink-muted">Cover image</label>
          <div class="mb-3 mt-1.5 flex items-center gap-3">
            <div class="grid h-16 w-28 shrink-0 place-items-center overflow-hidden rounded-lg border border-line bg-surface-2">
              <img v-if="editCover" :src="editCover" alt="cover" class="h-full w-full object-cover" />
              <span v-else class="text-[11px] text-ink-muted">No cover</span>
            </div>
            <input type="file" accept="image/png,image/jpeg,image/webp" class="text-sm text-ink-2" @change="pickCover" />
            <span v-if="uploadingCover" class="text-sm text-ink-muted">Uploading…</span>
            <button v-if="editCover" type="button" class="text-sm text-ink-muted hover:text-red-500" @click="editCover = null">Remove</button>
          </div>
        </template>

        <Editor ref="editEditor" :content="editing.html" />
        <div class="mt-3 flex items-center gap-2">
          <button @click="saveEdit" class="rounded-c bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-600">Save</button>
          <button @click="editing = null" class="rounded-lg px-3 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2">Cancel</button>
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
            <p class="mt-2 font-semibold text-ink">Thanks — our moderators will take a look.</p>
          </div>
        </template>
        <template v-else>
          <h3 class="text-lg font-bold text-ink">Report this post</h3>
          <p class="mt-1 text-sm text-ink-muted">Flag this for the moderators. Tell us what's wrong (optional).</p>
          <textarea v-model="reportReason" rows="3" placeholder="Reason (optional)…"
            class="mt-3 w-full rounded-c border-line bg-surface-2 text-sm text-ink focus:border-primary focus:ring-primary"></textarea>
          <div class="mt-4 flex justify-end gap-2">
            <button @click="reportModal = null" class="rounded-lg px-4 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2">Cancel</button>
            <button @click="submitReport" class="rounded-c bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-600">Submit report</button>
          </div>
        </template>
      </div>
    </div>
  </AppLayout>
</template>
