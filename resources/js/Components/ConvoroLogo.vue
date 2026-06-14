<script setup lang="ts">
withDefaults(defineProps<{ size?: number; showWord?: boolean }>(), {
  size: 36,
  showWord: true,
});
</script>

<template>
  <span class="inline-flex items-center gap-2.5 font-extrabold tracking-tight text-ink">
    <!-- Brand mark: an indigo→violet speech bubble with a pointed tail and three
         white "typing" dots. Matches the Convoro logo artwork. The gradient uses
         userSpaceOnUse so it stays continuous across the bubble + tail. -->
    <svg
      :width="size * 1.04"
      :height="size"
      viewBox="0 0 40 40"
      fill="none"
      aria-hidden="true"
      class="shrink-0"
    >
      <defs>
        <linearGradient id="convoro-bubble" gradientUnits="userSpaceOnUse" x1="2" y1="2" x2="36" y2="38">
          <stop offset="0" stop-color="#6E5DEA" />
          <stop offset="1" stop-color="#4632C6" />
        </linearGradient>
      </defs>
      <!-- Rounded bubble body -->
      <rect x="2" y="3" width="36" height="24" rx="8.5" fill="url(#convoro-bubble)" />
      <!-- Pointed tail, bottom-left -->
      <path d="M11 25.5 H20 L8.5 38 Z" fill="url(#convoro-bubble)" />
      <!-- Typing-indicator dots -->
      <circle cx="13" cy="15" r="2.8" fill="#ffffff" />
      <circle cx="20" cy="15" r="2.8" fill="#ffffff" />
      <circle cx="27" cy="15" r="2.8" fill="#ffffff" />
    </svg>
    <span v-if="showWord" class="text-[1.25rem]">Convoro</span>
  </span>
</template>
