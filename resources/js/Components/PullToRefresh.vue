<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';

// Pull-to-refresh for touch devices (the installed PWA has no native one).
// Drag down from the very top of the page past the threshold to reload the
// current page's data via Inertia.
const THRESHOLD = 70;
const MAX = 120;

const pull = ref(0);
const refreshing = ref(false);
let startY = 0;
let tracking = false;

function onStart(e: TouchEvent) {
  if (refreshing.value || window.scrollY > 0 || e.touches.length !== 1) {
    tracking = false;
    return;
  }
  startY = e.touches[0].clientY;
  tracking = true;
}

function onMove(e: TouchEvent) {
  if (!tracking) return;
  // If the page has scrolled, abandon the gesture.
  if (window.scrollY > 0) {
    tracking = false;
    pull.value = 0;
    return;
  }
  const dy = e.touches[0].clientY - startY;
  if (dy <= 0) {
    pull.value = 0;
    return;
  }
  // Rubber-band resistance; once engaged, stop the page from also scrolling.
  pull.value = Math.min(MAX, dy * 0.5);
  if (pull.value > 4 && e.cancelable) e.preventDefault();
}

function onEnd() {
  if (!tracking) return;
  tracking = false;
  if (pull.value >= THRESHOLD) {
    refreshing.value = true;
    pull.value = THRESHOLD;
    router.reload({
      onFinish: () => {
        refreshing.value = false;
        pull.value = 0;
      },
    });
  } else {
    pull.value = 0;
  }
}

onMounted(() => {
  window.addEventListener('touchstart', onStart, { passive: true });
  window.addEventListener('touchmove', onMove, { passive: false });
  window.addEventListener('touchend', onEnd, { passive: true });
  window.addEventListener('touchcancel', onEnd, { passive: true });
});
onBeforeUnmount(() => {
  window.removeEventListener('touchstart', onStart);
  window.removeEventListener('touchmove', onMove);
  window.removeEventListener('touchend', onEnd);
  window.removeEventListener('touchcancel', onEnd);
});
</script>

<template>
  <div
    v-show="pull > 0 || refreshing"
    class="pointer-events-none fixed inset-x-0 top-0 z-50 flex justify-center md:hidden"
    :style="{ transform: `translateY(${(refreshing ? THRESHOLD : pull) - 42}px)`, transition: tracking ? 'none' : 'transform 0.2s ease' }"
  >
    <div class="grid h-9 w-9 place-items-center rounded-full bg-surface shadow-lg ring-1 ring-line">
      <svg
        class="h-5 w-5 text-primary"
        :class="refreshing ? 'animate-spin' : ''"
        :style="refreshing ? {} : { transform: `rotate(${Math.min(360, pull * 3)}deg)`, opacity: String(Math.min(1, pull / THRESHOLD)) }"
        viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"
      >
        <path d="M21 12a9 9 0 1 1-2.64-6.36" />
        <path d="M21 3v6h-6" />
      </svg>
    </div>
  </div>
</template>
