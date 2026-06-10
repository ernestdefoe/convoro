<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import Avatar from './Avatar.vue';

defineProps<{ topic: any }>();
</script>

<template>
  <Link
    :href="`/t/${topic.slug}`"
    class="flex gap-4 rounded-c border border-line bg-surface p-4 shadow-sm transition hover:-translate-y-0.5 hover:border-primary/60 hover:shadow-md"
  >
    <Avatar :avatar="topic.author" :size="44" />
    <div class="min-w-0 flex-1">
      <div class="flex items-center gap-2">
        <svg v-if="topic.isPinned" width="14" height="14" viewBox="0 0 24 24" fill="#e8830c"><path d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z" /></svg>
        <h3 class="truncate text-base font-bold tracking-tight">{{ topic.title }}</h3>
        <span v-if="topic.isLive" class="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-bold text-rose-600">
          <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-rose-500"></span>LIVE
        </span>
      </div>
      <p class="mt-1.5 line-clamp-2 text-sm text-ink-2">{{ topic.excerpt }}</p>
      <div class="mt-2.5 flex flex-wrap items-center gap-x-3.5 gap-y-1.5 text-[13px] text-ink-muted">
        <span v-if="topic.category" class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold"
          :style="{ color: topic.category.color, background: topic.category.color + '22' }">
          <span>{{ topic.category.icon }}</span> {{ topic.category.name }}
        </span>
        <span class="font-semibold text-ink-2">{{ topic.author.name }}</span>
        <span>· {{ topic.lastActivity }}</span>
        <span v-if="topic.reactionTotal" class="flex items-center gap-1">
          <span v-for="r in topic.reactions.slice(0, 3)" :key="r.emoji">{{ r.emoji }}</span>
          <span class="font-semibold">{{ topic.reactionTotal }}</span>
        </span>
      </div>
    </div>
    <div class="hidden shrink-0 flex-col items-end justify-center gap-2 sm:flex">
      <div class="text-center"><b class="block text-[15px]">{{ topic.replyCount }}</b><span class="text-[11px] uppercase tracking-wide text-ink-muted">replies</span></div>
    </div>
  </Link>
</template>
