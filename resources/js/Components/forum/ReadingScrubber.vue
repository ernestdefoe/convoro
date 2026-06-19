<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, computed, watch } from 'vue';
import { t } from '@/lib/i18n';

// A reading-progress scrubber for a discussion thread. Tracks the page (or an
// inner `target` container) and renders a vertical rail with a dot per reply,
// a "N replies" label up top and the current position below. Drag to jump.
const props = defineProps<{ target?: HTMLElement | null; count?: number }>();

const progress = ref(0);
const dragging = ref(false);
const track = ref<HTMLElement | null>(null);
const root = ref<HTMLElement | null>(null);

// Only worth showing once there's a meaningful amount to scroll.
const SHOW_THRESHOLD = 700;
const visible = ref(false);
// Pixels to nudge the (vertically-centered) scrubber up so it never overlaps
// the page footer — stays fully visible, just shifts above it near the bottom.
const liftPx = ref(0);

const total = computed(() => Math.max(0, props.count ?? 0));
// One dot per reply, evenly spaced top→bottom (skip when there are too many).
const dots = computed<number[]>(() => {
  const n = total.value;
  if (n < 2 || n > 40) return [];
  return Array.from({ length: n }, (_, i) => i / (n - 1));
});
// Which reply the handle is currently sitting on (1-based).
const current = computed(() => {
  if (total.value < 1) return 0;
  return Math.min(total.value, Math.max(1, Math.round(progress.value * (total.value - 1)) + 1));
});

function maxScroll() {
  return props.target
    ? Math.max(1, props.target.scrollHeight - props.target.clientHeight)
    : Math.max(1, document.documentElement.scrollHeight - window.innerHeight);
}
function curScroll() {
  return props.target ? props.target.scrollTop : window.scrollY;
}
// Keep the (vertically-centered) scrubber from overlapping the page footer:
// when the footer scrolls into view, shift the whole scrubber up so its bottom
// edge stays just above the footer. It stays fully visible the whole way down.
let footerEl: HTMLElement | null = null;
function footerLift() {
  if (!footerEl) footerEl = document.querySelector('footer');
  const el = root.value;
  if (!footerEl || !el) return 0;
  const fr = footerEl.getBoundingClientRect();
  if (fr.height <= 0) return 0;
  const vh = window.innerHeight;
  const h = el.offsetHeight;
  const centeredBottom = vh / 2 + h / 2;     // bottom edge with translateY(-50%)
  const limit = fr.top - 16;                  // 16px gap above the footer
  if (centeredBottom <= limit) return 0;
  // Don't push it off the top of the viewport either.
  const maxLift = vh / 2 - h / 2 - 8;
  return Math.max(0, Math.min(centeredBottom - limit, maxLift));
}
function update() {
  const max = maxScroll();
  visible.value = max >= SHOW_THRESHOLD;
  progress.value = Math.min(1, Math.max(0, curScroll() / max));
  liftPx.value = footerLift();
}
function seek(clientY: number) {
  const el = track.value;
  if (!el) return;
  const r = el.getBoundingClientRect();
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
watch(() => props.target, () => { unbind(); bind(); });
</script>

<template>
  <!-- Anchored just past the right edge of the post column (max-w-3xl =
       24rem half-width, viewport-centered) so it sits beside the posts rather
       than out by the browser scrollbar. -->
  <div
    ref="root"
    v-show="visible"
    :style="{ transform: `translateY(calc(-50% - ${liftPx}px))` }"
    class="group fixed left-[calc(50%+24.5rem)] top-1/2 z-30 hidden select-none flex-col items-center rounded-[20px] bg-surface-2/80 px-2.5 py-4 shadow-sm ring-1 ring-line backdrop-blur-sm transition-transform duration-150 xl:flex"
  >
    <!-- Top label: reply count -->
    <div v-if="total" class="mb-2.5 text-center text-[11px] font-semibold leading-tight text-ink-muted">
      <div class="text-ink-2">{{ total }}</div>
      <div>{{ t('replies') }}</div>
    </div>

    <!-- Rail (the draggable hit area) -->
    <div
      ref="track"
      class="relative h-[52vh] w-4 cursor-pointer"
      :title="t('Reading progress — drag to jump')"
      @pointerdown="start" @pointermove="move" @pointerup="end" @pointerleave="end"
    >
      <!-- Base track -->
      <div class="absolute left-1/2 top-0 h-full w-px -translate-x-1/2 rounded-full bg-line"></div>
      <!-- Filled portion (top → handle) -->
      <div class="absolute left-1/2 top-0 w-[3px] -translate-x-1/2 rounded-full bg-primary" :style="{ height: progress * 100 + '%' }"></div>
      <!-- Per-reply dots: light nodes sitting on the rail, ringed in the rail's
           colour (primary once passed) so they read against both segments. -->
      <div
        v-for="(d, i) in dots" :key="i"
        class="absolute left-1/2 h-2 w-2 -translate-x-1/2 -translate-y-1/2 rounded-full bg-surface ring-2 transition-colors"
        :class="d <= progress + 0.001 ? 'ring-primary' : 'ring-line'"
        :style="{ top: d * 100 + '%' }"
      ></div>
      <!-- Handle -->
      <div class="absolute left-1/2 -translate-x-1/2 -translate-y-1/2" :style="{ top: progress * 100 + '%' }">
        <div class="h-3.5 w-3.5 rounded-full bg-primary shadow ring-2 ring-surface transition-transform group-hover:scale-110"></div>
      </div>
    </div>

    <!-- Bottom label: current / total -->
    <div v-if="total" class="mt-2.5 text-[11px] font-bold tabular-nums text-ink-2">{{ current }}/{{ total }}</div>
  </div>
</template>
