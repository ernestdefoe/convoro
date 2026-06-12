<script setup lang="ts">
// Renders a Font Awesome icon when the value is an FA class (e.g. "fa-solid fa-bullhorn"),
// otherwise falls back to plain text (legacy emoji icons). When `color` is given
// the icon is tinted to the category's color.
defineProps<{ icon?: string | null; color?: string | null }>();
</script>

<template>
  <i v-if="icon && icon.startsWith('fa')" :class="icon" :style="color ? { color } : undefined" aria-hidden="true"></i>
  <span v-else-if="icon" :style="color ? { color } : undefined">{{ icon }}</span>
</template>
