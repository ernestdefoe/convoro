<script setup lang="ts">
withDefaults(defineProps<{ size?: number; showWord?: boolean }>(), {
  size: 36,
  showWord: true,
});
</script>

<template>
  <span class="inline-flex items-center gap-2.5 font-extrabold tracking-tight text-ink">
    <!-- Brand mark: indigo→violet tile with a white speech bubble + typing dots. -->
    <span
      class="q-grad grid shrink-0 place-items-center rounded-[28%] shadow-lg shadow-primary/40"
      :style="{ width: size + 'px', height: size + 'px' }"
    >
      <svg viewBox="0 0 24 24" fill="none" :width="size * 0.64" :height="size * 0.64" aria-hidden="true">
        <!-- Rounded speech bubble with a tail at the bottom-left -->
        <path
          d="M5 4.5 h14 a3 3 0 0 1 3 3 v6 a3 3 0 0 1 -3 3 h-8.4 l-4.6 3.5 v-3.5 h-1 a3 3 0 0 1 -3 -3 v-6 a3 3 0 0 1 3 -3 z"
          fill="#ffffff"
        />
        <!-- Typing-indicator dots -->
        <circle cx="8.6" cy="10.5" r="1.35" fill="#4f46e5" />
        <circle cx="12" cy="10.5" r="1.35" fill="#4f46e5" />
        <circle cx="15.4" cy="10.5" r="1.35" fill="#4f46e5" />
      </svg>
    </span>
    <span v-if="showWord" class="text-[1.25rem]">Convoro</span>
  </span>
</template>
