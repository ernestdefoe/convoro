<script setup lang="ts">
import { computed } from 'vue';
import { convoro } from '@/lib/convoro-ext';

// Small inline badge strip shown after a username: the user's group (staff) icon,
// then their primary social group, then a favorite-team logo — each optional and
// each with the name in a hover tooltip. Rendered next to the name (not on the
// avatar) so it stays off the discussion list and never crowds the avatar.
const props = withDefaults(
  defineProps<{
    author: {
      staff?: { name: string; color: string; icon?: string | null } | null;
      primaryGroup?: { name: string; slug: string; color: string; icon?: string | null } | null;
      favoriteTeamId?: string | null;
    };
    /** Icon box size in px. */
    size?: number;
  }>(),
  { size: 18 },
);

const team = computed(() => convoro.avatarBadge(props.author.favoriteTeamId));
const box = computed(() => ({ width: props.size + 'px', height: props.size + 'px' }));
const glyph = computed(() => Math.round(props.size * 0.62) + 'px');

const hasAny = computed(
  () => !!props.author.staff || !!props.author.primaryGroup || !!team.value,
);
</script>

<template>
  <span v-if="hasAny" class="inline-flex shrink-0 items-center gap-1 align-middle">
    <!-- Group (staff) badge: a colored FA icon, group name in the tooltip. -->
    <span
      v-if="author.staff"
      class="grid place-items-center"
      :style="{ ...box, color: author.staff.color }"
      :title="author.staff.name"
      :aria-label="author.staff.name"
    >
      <i :class="author.staff.icon || 'fa-solid fa-user-tag'" :style="{ fontSize: glyph }"></i>
    </span>

    <!-- Primary social group badge: its own FA icon (or a fallback), links to the group. -->
    <a
      v-if="author.primaryGroup"
      :href="`/groups/${author.primaryGroup.slug}`"
      class="grid place-items-center hover:opacity-80"
      :style="{ ...box, color: author.primaryGroup.color }"
      :title="author.primaryGroup.name"
      :aria-label="author.primaryGroup.name"
    >
      <i :class="author.primaryGroup.icon || 'fa-solid fa-users'" :style="{ fontSize: glyph }"></i>
    </a>

    <!-- Favorite team logo (resolved by the Favorite Team extension). -->
    <span
      v-if="team"
      class="grid place-items-center overflow-hidden rounded-full"
      :style="box"
      :title="team.label"
      :aria-label="team.label"
    >
      <img :src="team.logo" :alt="team.label || ''" class="h-full w-full object-contain" loading="lazy" />
    </span>
  </span>
</template>
