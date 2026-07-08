<script setup lang="ts">
import { computed } from 'vue';

// A single tag rendered as a colored pill with its FontAwesome icon (or a color
// dot fallback). Shared by the composer's selected-tags row and the tag picker.
const props = withDefaults(
  defineProps<{
    tag: { name: string; color?: string | null; icon?: string | null };
    removable?: boolean;
    size?: 'sm' | 'md';
  }>(),
  { removable: false, size: 'md' }
);

defineEmits<{ (e: 'remove'): void }>();

const color = computed(() => props.tag.color || '#5b5bd6');
const hasIcon = computed(() => !!props.tag.icon && props.tag.icon.includes('fa-'));
</script>

<template>
  <span
    class="inline-flex items-center gap-1.5 rounded-full font-semibold text-white"
    :class="size === 'sm' ? 'px-2.5 py-0.5 text-[11px]' : 'px-3 py-1 text-xs'"
    :style="{ background: color }"
  >
    <i v-if="hasIcon" :class="tag.icon" aria-hidden="true"></i>
    <span v-else class="h-2 w-2 rounded-full bg-white/80"></span>
    {{ tag.name }}
    <button
      v-if="removable"
      type="button"
      class="-mr-1 ml-0.5 grid h-4 w-4 place-items-center rounded-full text-white/80 hover:bg-white/20 hover:text-white"
      :aria-label="'Remove ' + tag.name"
      @click.stop="$emit('remove')"
    >
      <svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12" /></svg>
    </button>
  </span>
</template>
