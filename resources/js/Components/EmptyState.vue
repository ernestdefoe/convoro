<script setup lang="ts">
defineProps<{ icon?: string; title: string; description?: string }>();
</script>

<template>
  <div class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-line px-6 py-14 text-center">
    <div v-if="icon" class="mb-4 grid h-14 w-14 place-items-center rounded-2xl bg-primary/10 text-2xl">{{ icon }}</div>
    <h3 class="text-base font-bold text-ink">{{ title }}</h3>
    <p v-if="description" class="mt-1.5 max-w-sm text-sm text-ink-2">{{ description }}</p>
    <div v-if="$slots.default" class="mt-5 flex flex-wrap items-center justify-center gap-3">
      <slot />
    </div>
  </div>
</template>
