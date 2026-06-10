<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';

// A reading-progress scrubber for the whole page (e.g. a discussion thread).
// Shows how far you've read and can be dragged to jump anywhere in the thread.
const progress = ref(0);
const dragging = ref(false);
const track = ref<HTMLElement | null>(null);

function docMax() {
  return Math.max(1, document.documentElement.scrollHeight - window.innerHeight);
}
function onScroll() {
  progress.value = Math.min(1, Math.max(0, window.scrollY / docMax()));
}
function seek(clientY: number) {
  const t = track.value;
  if (!t) return;
  const r = t.getBoundingClientRect();
  const frac = Math.min(1, Math.max(0, (clientY - r.top) / r.height));
  window.scrollTo({ top: frac * docMax() });
}
function start(e: PointerEvent) {
  dragging.value = true;
  (e.target as HTMLElement).setPointerCapture?.(e.pointerId);
  seek(e.clientY);
}
function move(e: PointerEvent) { if (dragging.value) seek(e.clientY); }
function end() { dragging.value = false; }

onMounted(() => {
  window.addEventListener('scroll', onScroll, { passive: true });
  window.addEventListener('resize', onScroll);
  onScroll();
});
onBeforeUnmount(() => {
  window.removeEventListener('scroll', onScroll);
  window.removeEventListener('resize', onScroll);
});
</script>

<template>
  <div
    ref="track"
    class="group fixed right-2 top-1/2 z-30 hidden h-[55vh] w-3 -translate-y-1/2 cursor-pointer md:block"
    title="Reading progress — drag to jump"
    @pointerdown="start" @pointermove="move" @pointerup="end" @pointerleave="end"
  >
    <div class="absolute left-1/2 h-full w-1 -translate-x-1/2 rounded-full bg-line"></div>
    <div class="absolute left-1/2 top-0 w-1 -translate-x-1/2 rounded-full bg-primary" :style="{ height: progress * 100 + '%' }"></div>
    <div class="absolute left-1/2 -translate-x-1/2 -translate-y-1/2" :style="{ top: progress * 100 + '%' }">
      <div class="h-3.5 w-3.5 rounded-full border-2 border-primary bg-surface shadow"></div>
      <div
        class="absolute right-5 top-1/2 -translate-y-1/2 whitespace-nowrap rounded bg-ink px-1.5 py-0.5 text-[10px] font-bold text-surface transition-opacity"
        :class="dragging ? 'opacity-100' : 'opacity-0 group-hover:opacity-100'"
      >{{ Math.round(progress * 100) }}%</div>
    </div>
  </div>
</template>
