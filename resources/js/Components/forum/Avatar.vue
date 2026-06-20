<script setup lang="ts">
import { computed } from 'vue';
import { convoro } from '@/lib/convoro-ext';

// Parent-passed attrs (class like `mx-auto`/`ring-2`, style, listeners) must land
// on the AVATAR element — not the optional badge wrapper — so alignment is
// identical whether or not a staff badge is shown. (A `display:contents` wrapper
// silently drops those classes, which mis-aligned non-staff avatars.)
defineOptions({ inheritAttrs: false });

const props = withDefaults(
  defineProps<{
    avatar: {
      id?: number;
      initials: string;
      color: number;
      avatar?: string | null;
      staff?: { name: string; color: string } | null;
      online?: boolean;
    };
    size?: number;
    /** When true, show the staff badge (if any) beneath the avatar. */
    badge?: boolean;
    /** When set, overlay an online/offline presence dot on the avatar.
        'auto' uses the avatar's own `online` flag. */
    presence?: 'online' | 'offline' | 'auto' | null;
  }>(),
  { size: 40, badge: false, presence: null },
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

const live = computed(() => convoro.isLive(props.avatar.id));
const hasPresence = computed(() => props.presence != null);
// Live streamers get the relative-wrapper variant so the LIVE pill can overlay.
const useWrap = computed(() => hasPresence.value || live.value);
const isOnline = computed(() =>
  props.presence === 'online' || (props.presence === 'auto' && !!props.avatar.online),
);
const glyphStyle = computed(() => ({
  width: props.size + 'px',
  height: props.size + 'px',
  borderRadius: 'var(--c-avatar-radius, 9999px)',
}));
const dotSize = computed(() => Math.max(9, Math.round(props.size * 0.26)));
</script>

<template>
  <!-- No badge: the wrapper is transparent (display:contents) and the avatar
       below carries all passed attrs, so it behaves exactly like a bare avatar.
       Badge: a real centered column stacks the avatar + badge. -->
  <span :class="showBadge ? 'inline-flex flex-col items-center gap-1' : 'contents'">
    <!-- Presence/live variant: a relative wrapper holds the glyph + overlays. -->
    <span v-if="useWrap" v-bind="$attrs" class="relative inline-flex shrink-0">
      <img
        v-if="avatar.avatar"
        :src="avatar.avatar"
        :alt="avatar.initials"
        class="block object-cover"
        :style="glyphStyle"
      />
      <span
        v-else
        class="grid place-items-center font-bold text-white"
        :style="{ ...glyphStyle, fontSize: size * 0.38 + 'px', background: bg }"
      >{{ avatar.initials || '?' }}</span>
      <span
        v-if="hasPresence && !live"
        class="absolute bottom-0 right-0 rounded-full ring-2 ring-surface"
        :class="isOnline ? 'bg-emerald-500' : 'bg-ink-muted'"
        :style="{ width: dotSize + 'px', height: dotSize + 'px' }"
        :title="isOnline ? 'Online' : 'Offline'"
      ></span>
      <span
        v-if="live"
        class="absolute -bottom-1 left-1/2 -translate-x-1/2 rounded-full bg-red-600 px-1 py-0.5 text-[8px] font-extrabold uppercase leading-none tracking-wide text-white ring-2 ring-surface"
        title="Live now"
      >Live</span>
    </span>

    <!-- Default variant: unchanged — glyph carries the passed attrs. -->
    <img
      v-else-if="avatar.avatar"
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
      class="max-w-full truncate rounded-full px-2 py-0.5 text-[9px] font-bold uppercase leading-none tracking-wide text-white shadow-sm"
      :style="{ background: avatar.staff?.color }"
    >{{ avatar.staff?.name }}</span>
  </span>
</template>
