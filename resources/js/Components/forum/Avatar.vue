<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(defineProps<{ avatar: { initials: string; color: number; avatar?: string | null }; size?: number }>(), {
  size: 40,
});

const gradients = [
  'linear-gradient(135deg,#f472b6,#db2777)',
  'linear-gradient(135deg,#60a5fa,#2563eb)',
  'linear-gradient(135deg,#34d399,#059669)',
  'linear-gradient(135deg,#fbbf24,#d97706)',
  'linear-gradient(135deg,#a78bfa,#7c3aed)',
  'linear-gradient(135deg,#f87171,#dc2626)',
];
const bg = computed(() => gradients[(props.avatar.color - 1) % 6]);
</script>

<template>
  <img
    v-if="avatar.avatar"
    :src="avatar.avatar"
    :alt="avatar.initials"
    class="shrink-0 rounded-full object-cover"
    :style="{ width: size + 'px', height: size + 'px' }"
  />
  <span
    v-else
    class="grid shrink-0 place-items-center rounded-full font-bold text-white"
    :style="{ width: size + 'px', height: size + 'px', fontSize: size * 0.38 + 'px', background: bg }"
  >{{ avatar.initials }}</span>
</template>
