<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import Avatar from './Avatar.vue';
import { t as tr } from '@/lib/i18n';

// Magazine/bento tile with neon-glass touches: glassy card, tag-colored glow on
// hover, and a bold gradient "featured" variant for the lead topic.
const props = defineProps<{ topic: any; featured?: boolean }>();
const c1 = computed(() => props.topic.tags?.[0]?.color || '#7c3aed');
const c2 = computed(() => props.topic.tags?.[1]?.color || '#ec4899');
</script>

<template>
  <Link :href="`/t/${topic.slug}`"
    class="tile group relative flex flex-col overflow-hidden rounded-c border border-line bg-surface p-4 sm:p-5"
    :class="featured ? 'tile-feat' : ''"
    :style="{ '--tg': c1, '--glow': c1 + '55' }">
    <template v-if="featured">
      <div class="pointer-events-none absolute inset-0" :style="{ background: `linear-gradient(120deg, ${c1}, ${c2})` }"></div>
      <div class="pointer-events-none absolute inset-0" style="background: radial-gradient(120% 130% at 85% 0, rgba(255,255,255,.24), transparent 55%)"></div>
      <div class="pointer-events-none absolute inset-0" style="background: linear-gradient(to top, rgba(0,0,0,.45), transparent 62%)"></div>
    </template>

    <div class="relative z-10 flex flex-1 flex-col">
      <div class="mb-2.5 flex flex-wrap items-center gap-1.5">
        <span v-for="tg in (topic.tags || []).slice(0, 2)" :key="tg.slug"
          class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold"
          :style="featured ? { color: '#fff', background: 'rgba(255,255,255,.22)' } : { color: tg.color, background: tg.color + '22' }">{{ tg.name }}</span>
        <span v-if="topic.isLive" class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-bold text-white" :class="featured ? 'bg-rose-600/80' : 'bg-rose-500/90'">
          <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-white"></span>{{ tr('LIVE') }}
        </span>
        <span v-else-if="topic.isNew" class="rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white" :class="featured ? 'bg-white/25' : 'bg-primary'">{{ tr('New') }}</span>
        <span v-if="featured" class="ml-auto inline-flex items-center gap-1.5 rounded-full bg-black/25 px-2.5 py-0.5 text-[11px] font-bold text-white"><i class="fa-solid fa-fire" aria-hidden="true"></i> {{ tr('Featured') }}</span>
      </div>

      <h3 class="font-bold tracking-tight" :class="featured ? 'text-2xl text-white drop-shadow-sm sm:text-[27px]' : 'text-[17px]'" style="line-height:1.16">{{ topic.title }}</h3>
      <p v-if="topic.excerpt" class="mt-2 line-clamp-2 text-sm" :class="featured ? 'max-w-[85%] text-white/85' : 'text-ink-2'">{{ topic.excerpt }}</p>

      <div class="mt-auto flex items-center gap-2.5 pt-4" :class="featured ? 'text-white/80' : 'text-ink-muted'">
        <Avatar :avatar="topic.author" :size="featured ? 30 : 26" />
        <span class="truncate text-[13px] font-semibold" :class="featured ? 'text-white' : 'text-ink-2'">{{ topic.author.name }}</span>
        <span class="shrink-0 text-xs">· {{ topic.lastActivity }}</span>
        <span class="ml-auto flex shrink-0 items-center gap-3.5 text-xs font-semibold">
          <span><i class="fa-solid fa-comment" aria-hidden="true"></i> {{ topic.replyCount }}</span>
          <span v-if="topic.reactionTotal"><i class="fa-solid fa-fire" aria-hidden="true"></i> {{ topic.reactionTotal }}</span>
          <span class="hidden sm:inline"><i class="fa-solid fa-eye" aria-hidden="true"></i> {{ topic.viewCount }}</span>
        </span>
      </div>
    </div>
  </Link>
</template>

<style scoped>
.tile { transition: transform .22s ease, box-shadow .22s ease, border-color .22s ease; }
.tile:not(.tile-feat) { box-shadow: inset 4px 0 0 0 var(--tg); }
.tile:not(.tile-feat):hover { transform: translateY(-3px); border-color: var(--tg); box-shadow: inset 4px 0 0 0 var(--tg), 0 16px 50px -14px var(--glow), 0 0 0 1px var(--glow); }
.tile-feat { min-height: 188px; border-color: transparent; box-shadow: 0 18px 60px -16px var(--glow); }
.tile-feat:hover { transform: translateY(-3px); box-shadow: 0 26px 72px -14px var(--glow); }
@media (prefers-reduced-motion: reduce) { .tile, .tile:hover, .tile-feat:hover { transform: none; } }
</style>
