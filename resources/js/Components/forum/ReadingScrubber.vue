<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { t } from '@/lib/i18n';

// A reading-progress scrubber. By default it tracks the whole page (e.g. a
// discussion thread or a long profile). Pass `target` to track an inner
// scroll container instead (e.g. a DM thread that scrolls within a box).
// Drag the handle to jump anywhere.
const props = defineProps<{ target?: HTMLElement | null }>();

const progress = ref(0);
const dragging = ref(false);
const track = ref<HTMLElement | null>(null);

// Only worth showing once there's a meaningful amount to scroll (skip short
// posts / brief DM threads where a progress rail is just noise).
const SHOW_THRESHOLD = 700;
const visible = ref(false);

function maxScroll() {
  return props.target
    ? Math.max(1, props.target.scrollHeight - props.target.clientHeight)
    : Math.max(1, document.documentElement.scrollHeight - window.innerHeight);
}
function curScroll() {
  return props.target ? props.target.scrollTop : window.scrollY;
}
function update() {
  const max = maxScroll();
  visible.value = max >= SHOW_THRESHOLD;
  progress.value = Math.min(1, Math.max(0, curScroll() / max));
}
function seek(clientY: number) {
  const t = track.value;
  if (!t) return;
  const r = t.getBoundingClientRect();
  const frac = Math.min(1, Math.max(0, (clientY - r.top) / r.height));
  const top = frac * maxScroll();
  if (props.target) props.target.scrollTo({ top });
  else window.scrollTo({ top });
}
function start(e: PointerEvent) {
  dragging.value = true;
  (e.target as HTMLElement).setPointerCapture?.(e.pointerId);
  seek(e.clientY);
}
function move(e: PointerEvent) { if (dragging.value) seek(e.clientY); }
function end() { dragging.value = false; }

let scrollEl: HTMLElement | Window | null = null;
function bind() {
  scrollEl = props.target ?? window;
  scrollEl.addEventListener('scroll', update, { passive: true } as any);
  window.addEventListener('resize', update);
  update();
}
function unbind() {
  scrollEl?.removeEventListener('scroll', update as any);
  window.removeEventListener('resize', update);
}

onMounted(bind);
onBeforeUnmount(unbind);
// Re-bind if the target element appears/changes after mount.
watch(() => props.target, () => { unbind(); bind(); });
</script>

<template>
  <div
    v-show="visible"
    ref="track"
    class="group fixed right-2 top-1/2 z-30 hidden h-[55vh] w-3 -translate-y-1/2 cursor-pointer md:block"
    :title="t('Reading progress — drag to jump')"
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
