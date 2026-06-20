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

const live = computed(() => convoro.isLive(props.avatar.id));
const hasPresence = computed(() => props.presence != null);
// Staff badges now show wherever the user is staff (not just when `badge` is set),
// mirroring the LIVE badge. A live staff member gets the LIVE pill stacked under
// the staff pill. The `badge` prop is kept for back-compat but no longer gates it.
const showStaff = computed(() => !!props.avatar.staff);
// Staff + LIVE render as overlay pills sitting on the avatar's bottom edge (with
// a white ring), so we need the relative wrapper whenever any overlay applies.
const hasOverlay = computed(() => hasPresence.value || showStaff.value || live.value);
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
  <!-- Overlay variant: a relative wrapper holds the glyph plus a presence dot
       (top-right) and/or the staff + LIVE pills, which sit on the avatar's bottom
       edge with a white ring — like badges pinned to the circle. -->
  <span v-if="hasOverlay" v-bind="$attrs" class="relative inline-flex shrink-0">
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

    <!-- Online/offline presence dot — top-right. -->
    <span
      v-if="hasPresence"
      class="absolute right-0 top-0 rounded-full ring-2 ring-surface"
      :class="isOnline ? 'bg-emerald-500' : 'bg-ink-muted'"
      :style="{ width: dotSize + 'px', height: dotSize + 'px' }"
      :title="isOnline ? 'Online' : 'Offline'"
    ></span>

    <!-- Staff (top) + LIVE (under) pills, centered on the avatar's bottom edge. -->
    <span
      v-if="showStaff || live"
      class="pointer-events-none absolute bottom-0 left-1/2 flex -translate-x-1/2 translate-y-1/2 flex-col items-center gap-0.5"
    >
      <span
        v-if="showStaff"
        class="whitespace-nowrap rounded-full px-1.5 py-0.5 text-[8px] font-extrabold uppercase leading-none tracking-wide text-white shadow-sm ring-2 ring-surface"
        :style="{ background: avatar.staff?.color }"
      >{{ avatar.staff?.name }}</span>
      <span
        v-if="live"
        class="rounded-full bg-red-600 px-1 py-0.5 text-[8px] font-extrabold uppercase leading-none tracking-wide text-white shadow-sm ring-2 ring-surface"
        title="Live now"
      >Live</span>
    </span>
  </span>

  <!-- Bare variants: the glyph carries the passed attrs. -->
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
</template>
