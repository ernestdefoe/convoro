<script setup lang="ts">
import { computed } from 'vue';

// Parent-passed attrs (class like `mx-auto`/`ring-2`, style, listeners) must land
// on the AVATAR element — not the optional badge wrapper — so alignment is
// identical whether or not a staff badge is shown. (A `display:contents` wrapper
// silently drops those classes, which mis-aligned non-staff avatars.)
defineOptions({ inheritAttrs: false });

const props = withDefaults(
  defineProps<{
    avatar: {
      initials: string;
      color: number;
      avatar?: string | null;
      staff?: { name: string; color: string } | null;
    };
    size?: number;
    /** When true, show the staff badge (if any) beneath the avatar. */
    badge?: boolean;
  }>(),
  { size: 40, badge: false },
);

const gradients = [
  'linear-gradient(135deg,#f472b6,#db2777)',
  'linear-gradient(135deg,#60a5fa,#2563eb)',
  'linear-gradient(135deg,#34d399,#059669)',
  'linear-gradient(135deg,#fbbf24,#d97706)',
  'linear-gradient(135deg,#a78bfa,#7c3aed)',
  'linear-gradient(135deg,#f87171,#dc2626)',
];
const bg = computed(() => gradients[(props.avatar.color - 1) % 6]);
const showBadge = computed(() => props.badge && !!props.avatar.staff);
</script>

<template>
  <!-- No badge: the wrapper is transparent (display:contents) and the avatar
       below carries all passed attrs, so it behaves exactly like a bare avatar.
       Badge: a real centered column stacks the avatar + badge. -->
  <span :class="showBadge ? 'inline-flex flex-col items-center gap-1' : 'contents'">
    <img
      v-if="avatar.avatar"
      v-bind="$attrs"
      :src="avatar.avatar"
      :alt="avatar.initials"
      class="shrink-0 object-cover"
      :style="{ width: size + 'px', height: size + 'px', borderRadius: 'var(--c-avatar-radius, 9999px)' }"
    />
    <span
      v-else
      v-bind="$attrs"
      class="grid shrink-0 place-items-center font-bold text-white"
      :style="{ width: size + 'px', height: size + 'px', fontSize: size * 0.38 + 'px', background: bg, borderRadius: 'var(--c-avatar-radius, 9999px)' }"
    >{{ avatar.initials || '?' }}</span>

    <span
      v-if="showBadge"
      class="max-w-full truncate rounded-full px-1.5 py-0.5 text-[9px] font-bold uppercase leading-none tracking-wide"
      :style="{ color: avatar.staff?.color, background: (avatar.staff?.color || '') + '22' }"
    >{{ avatar.staff?.name }}</span>
  </span>
</template>
