<script setup lang="ts">
// Renders a Font Awesome icon when the value is an FA class (e.g. "fa-solid fa-bullhorn"),
// otherwise falls back to plain text (legacy emoji icons).
defineProps<{ icon?: string | null }>();
</script>

<template>
  <i v-if="icon && icon.startsWith('fa')" :class="icon" aria-hidden="true"></i>
  <span v-else-if="icon">{{ icon }}</span>
</template>
