<script setup lang="ts">
// Lightweight shimmer placeholder. Use the `class` attr to size it, e.g.
// <Skeleton class="h-4 w-32" /> or <Skeleton class="h-40 w-full rounded-2xl" />.
withDefaults(defineProps<{ rounded?: string }>(), { rounded: 'rounded-lg' });
</script>

<template>
  <div class="cv-skeleton bg-surface-2" :class="rounded" aria-hidden="true"></div>
</template>

<style scoped>
.cv-skeleton { position: relative; overflow: hidden; }
.cv-skeleton::after {
  content: '';
  position: absolute;
  inset: 0;
  transform: translateX(-100%);
  background: linear-gradient(90deg, transparent, rgb(var(--c-text) / 0.06), transparent);
  animation: cv-shimmer 1.4s infinite;
}
@keyframes cv-shimmer { 100% { transform: translateX(100%); } }
@media (prefers-reduced-motion: reduce) { .cv-skeleton::after { animation: none; } }
</style>
