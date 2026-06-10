<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import Avatar from '@/Components/forum/Avatar.vue';
import Editor from '@/Components/Editor.vue';
import ReadingScrubber from '@/Components/forum/ReadingScrubber.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

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
        <Link href="/messages" class="text-ink-muted hover:text-ink" aria-label="Back to messages">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m15 18-6-6 6-6" /></svg>
        </Link>
        <Avatar :avatar="conversation.partner" :size="38" />
        <h1 class="text-lg font-bold">{{ conversation.title }}</h1>
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
        <p v-if="!live.length" class="py-10 text-center text-sm text-ink-muted">Say hello 👋</p>
      </div>

      <div class="mt-3">
        <Editor ref="editor" placeholder="Write a message…" />
        <div class="mt-2 flex justify-end">
          <button type="button" :disabled="posting" class="rounded-c bg-primary px-5 py-2 text-sm font-semibold text-white hover:bg-primary-600 disabled:opacity-60" @click="send">
            {{ posting ? 'Sending…' : 'Send' }}
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
.prose-onprimary :deep(a) { color: #fff; text-decoration: underline; }
</style>
