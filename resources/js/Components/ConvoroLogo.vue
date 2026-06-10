<script setup lang="ts">
withDefaults(defineProps<{ size?: number; showWord?: boolean }>(), {
  size: 36,
  showWord: true,
});
</script>

<template>
  <span class="inline-flex items-center gap-2.5 font-extrabold tracking-tight text-ink">
    <span
      class="q-grad grid place-items-center rounded-[9px] shadow-lg shadow-primary/40"
      :style="{ width: size + 'px', height: size + 'px' }"
    >
      <svg viewBox="0 0 24 24" fill="#fff" :width="size * 0.55" :height="size * 0.55">
        <circle cx="12" cy="6" r="2.7" />
        <circle cx="6.4" cy="15.6" r="2.7" />
        <circle cx="17.6" cy="15.6" r="2.7" />
      </svg>
    </span>
    <span v-if="showWord" class="text-[1.25rem]">Convoro</span>
  </span>
</template>
