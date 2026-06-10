<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps<{ title: string; html: string; byline?: string }>();

const open = ref(false);
const scroller = ref<HTMLElement | null>(null);
const track = ref<HTMLElement | null>(null);
const progress = ref(0); // 0..1
let dragging = false;

const readingTime = computed(() => {
  const text = props.html.replace(/<[^>]+>/g, ' ');
  const words = (text.trim().match(/\S+/g) || []).length;
  return Math.max(1, Math.round(words / 200));
});

function onScroll() {
  const el = scroller.value;
  if (!el) return;
  const max = el.scrollHeight - el.clientHeight;
  progress.value = max > 0 ? el.scrollTop / max : 0;
}

function seek(clientY: number) {
  const el = scroller.value;
  const t = track.value;
  if (!el || !t) return;
  const rect = t.getBoundingClientRect();
  const frac = Math.min(1, Math.max(0, (clientY - rect.top) / rect.height));
  el.scrollTop = frac * (el.scrollHeight - el.clientHeight);
}
function startDrag(e: PointerEvent) {
  dragging = true;
  (e.target as HTMLElement).setPointerCapture?.(e.pointerId);
  seek(e.clientY);
}
function moveDrag(e: PointerEvent) { if (dragging) seek(e.clientY); }
function endDrag() { dragging = false; }

function onKey(e: KeyboardEvent) { if (e.key === 'Escape') open.value = false; }

watch(open, (v) => {
  document.body.style.overflow = v ? 'hidden' : '';
  if (v) {
    progress.value = 0;
    document.addEventListener('keydown', onKey);
  } else {
    document.removeEventListener('keydown', onKey);
  }
});
onBeforeUnmount(() => { document.body.style.overflow = ''; document.removeEventListener('keydown', onKey); });
</script>

<template>
  <button
    type="button"
    class="inline-flex items-center gap-1.5 rounded-lg border border-line bg-surface-2 px-3 py-1.5 text-sm font-semibold text-ink-2 hover:text-ink"
    title="Reader mode"
    @click="open = true"
  >
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z" /><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z" /></svg>
    Reader
  </button>

  <Teleport to="body">
    <Transition enter-active-class="transition duration-200" enter-from-class="opacity-0" leave-active-class="transition duration-150" leave-to-class="opacity-0">
      <div v-if="open" class="fixed inset-0 z-[90] bg-appbg">
        <!-- top thin progress bar -->
        <div class="absolute inset-x-0 top-0 z-10 h-1 bg-line">
          <div class="h-full bg-primary" :style="{ width: progress * 100 + '%' }"></div>
        </div>

        <!-- close -->
        <button type="button" class="absolute right-4 top-4 z-20 flex h-9 w-9 items-center justify-center rounded-full border border-line bg-surface text-ink-2 hover:text-ink" aria-label="Close reader" @click="open = false">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12" /></svg>
        </button>

        <!-- draggable scrubber (right rail) -->
        <div
          ref="track"
          class="absolute right-3 top-1/2 z-20 hidden h-[60vh] w-2 -translate-y-1/2 cursor-pointer rounded-full bg-line sm:block"
          @pointerdown="startDrag" @pointermove="moveDrag" @pointerup="endDrag" @pointerleave="endDrag"
        >
          <div class="w-full rounded-full bg-primary/30" :style="{ height: progress * 100 + '%' }"></div>
          <div class="absolute left-1/2 h-4 w-4 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-primary bg-surface shadow" :style="{ top: progress * 100 + '%' }"></div>
        </div>

        <!-- reading column -->
        <div ref="scroller" class="h-full overflow-y-auto px-5 py-16" @scroll="onScroll">
          <article class="mx-auto max-w-[680px]">
            <h1 class="text-3xl font-extrabold leading-tight tracking-tight text-ink sm:text-4xl">{{ title }}</h1>
            <div class="mt-3 text-sm text-ink-muted">
              <span v-if="byline">{{ byline }} · </span>{{ readingTime }} min read
            </div>
            <div class="prose-q reader-body mt-8 text-ink" v-html="html"></div>
            <div class="h-24"></div>
          </article>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.reader-body { font-size: 1.18rem; line-height: 1.8; }
.reader-body :deep(p) { margin: 0 0 1.1em; }
.reader-body :deep(h2) { font-size: 1.5em; margin: 1.4em 0 0.5em; }
.reader-body :deep(img) { border-radius: 12px; margin: 1.2em 0; }
</style>
