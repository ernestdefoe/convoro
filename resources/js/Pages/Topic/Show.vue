<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Avatar from '@/Components/forum/Avatar.vue';
import Editor from '@/Components/Editor.vue';

const props = defineProps<{ topic: any; posts: any[]; canReply: boolean }>();
const page = usePage();
const loggedIn = computed(() => !!(page.props as any).auth?.user);

const EMOJIS = ['👍', '❤️', '😂', '😮', '😢', '🎉', '🔥'];
const pickerFor = ref<number | null>(null);
const editor = ref<any>(null);
const posting = ref(false);

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
    <div class="mx-auto max-w-3xl">
      <Link href="/" class="mb-3 inline-flex items-center gap-1.5 text-sm font-semibold text-ink-muted hover:text-ink-2">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6" /></svg> Back to Community
      </Link>

      <div class="overflow-hidden rounded-q border border-line bg-surface shadow-sm">
        <!-- Header -->
        <div class="border-b border-line p-6">
          <h1 class="flex flex-wrap items-center gap-3 text-2xl font-extrabold tracking-tight">
            {{ topic.title }}
            <span v-if="topic.isLive" class="inline-flex items-center gap-1.5 rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-bold text-rose-600">
              <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-rose-500"></span>LIVE
            </span>
          </h1>
          <div class="mt-3 flex flex-wrap items-center gap-2">
            <span v-if="topic.category" class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold"
              :style="{ color: topic.category.color, background: topic.category.color + '22' }">{{ topic.category.icon }} {{ topic.category.name }}</span>
            <span v-for="t in topic.tags" :key="t.slug" class="rounded-full bg-surface-2 px-2.5 py-0.5 text-xs font-semibold text-ink-2">{{ t.name }}</span>
          </div>
        </div>

        <!-- Posts -->
        <article v-for="post in posts" :key="post.id" class="flex gap-4 border-b border-line p-6 last:border-b-0">
          <div class="w-28 shrink-0 text-center">
            <Avatar :avatar="post.author" :size="44" class="mx-auto" />
            <div class="mt-2 text-sm font-bold">{{ post.author.name }}</div>
            <div v-if="post.isFirst" class="mt-1 inline-block rounded-full bg-primary-soft px-2 py-0.5 text-[11px] font-bold text-primary-700">Author</div>
          </div>
          <div class="min-w-0 flex-1">
            <div class="mb-2.5 text-xs text-ink-muted">{{ post.createdAt }}<span v-if="post.editedAt"> · edited {{ post.editedAt }}</span></div>
            <div class="prose-q text-ink" v-html="post.html"></div>

            <!-- Reactions -->
            <div class="relative mt-3.5 flex flex-wrap items-center gap-2">
              <button v-for="r in post.reactions" :key="r.emoji" @click="react(post.id, r.emoji)"
                class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[13px] font-semibold"
                :class="r.mine ? 'border-primary/40 bg-primary-soft text-primary-700' : 'border-line bg-surface-2 text-ink-2 hover:bg-surface'">
                {{ r.emoji }} {{ r.count }}
              </button>
              <button @click="pickerFor = pickerFor === post.id ? null : post.id"
                class="grid h-8 w-8 place-items-center rounded-full border border-line bg-surface-2 text-ink-muted hover:bg-surface" title="React">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9" /><path d="M8 14s1.5 2 4 2 4-2 4-2M9 9h.01M15 9h.01" /></svg>
              </button>
              <div v-if="pickerFor === post.id" class="absolute bottom-9 left-0 z-10 flex gap-1 rounded-full border border-line bg-surface px-2.5 py-1.5 shadow-xl">
                <button v-for="e in EMOJIS" :key="e" @click="react(post.id, e)" class="text-xl transition hover:scale-125">{{ e }}</button>
              </div>
              <div class="ml-auto flex gap-1">
                <button class="rounded-lg px-2.5 py-1 text-[13px] font-semibold text-ink-2 hover:bg-surface-2">Quote</button>
                <button class="rounded-lg px-2.5 py-1 text-[13px] font-semibold text-ink-2 hover:bg-surface-2">Reply</button>
              </div>
            </div>
          </div>
        </article>
      </div>

      <!-- Composer -->
      <div v-if="canReply" class="mt-5">
        <div class="mb-2.5 text-sm font-bold">Reply</div>
        <Editor ref="editor" placeholder="Share your thoughts… (rich text — no markdown needed)" />
        <div class="mt-3 flex items-center">
          <span class="text-xs text-ink-muted">Rich text · images auto-convert to WebP (coming in P2)</span>
          <button @click="submitReply" :disabled="posting"
            class="ml-auto rounded-q bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-primary/30 hover:bg-primary-600 disabled:opacity-60">
            {{ posting ? 'Posting…' : 'Post reply' }}
          </button>
        </div>
      </div>
      <div v-else-if="!loggedIn" class="mt-5 rounded-q border border-line bg-surface p-5 text-center text-sm text-ink-2">
        <Link href="/login" class="font-semibold text-primary">Log in</Link> to join the conversation.
      </div>
    </div>
  </AppLayout>
</template>
