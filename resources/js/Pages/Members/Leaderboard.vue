<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Avatar from '@/Components/forum/Avatar.vue';

defineProps<{ members: any[] }>();

const medal = (i: number) => (i === 0 ? '🥇' : i === 1 ? '🥈' : i === 2 ? '🥉' : '');
</script>

<template>
  <Head title="Leaderboard" />
  <AppLayout>
    <div class="mx-auto max-w-3xl">
      <div class="mb-5">
        <h1 class="text-2xl font-extrabold tracking-tight">Leaderboard</h1>
        <p class="text-sm text-ink-muted">Top members by reputation — posts, topics started, and reactions received.</p>
      </div>

      <div v-if="!members.length" class="rounded-c border border-dashed border-line bg-surface p-12 text-center text-ink-muted">
        No activity yet.
      </div>

      <div v-else class="overflow-hidden rounded-c border border-line bg-surface shadow-sm">
        <Link v-for="(m, i) in members" :key="m.id" :href="m.url"
          class="flex items-center gap-4 border-b border-line px-5 py-3.5 last:border-0 hover:bg-surface-2"
          :class="i < 3 ? 'bg-primary/[0.04]' : ''">
          <div class="w-8 shrink-0 text-center text-lg font-extrabold" :class="i < 3 ? '' : 'text-ink-muted'">
            <span v-if="medal(i)">{{ medal(i) }}</span>
            <span v-else>{{ i + 1 }}</span>
          </div>
          <Avatar :avatar="m" :size="44" />
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-1.5">
              <span class="truncate font-bold">{{ m.name }}</span>
              <span v-if="m.isAdmin" class="rounded bg-primary/15 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-primary">Admin</span>
            </div>
            <div class="text-xs text-ink-muted">{{ m.posts }} posts · {{ m.topics }} topics · {{ m.reactionsReceived }} reactions</div>
          </div>
          <div class="shrink-0 text-right">
            <div class="text-lg font-extrabold tracking-tight text-primary">{{ m.score }}</div>
            <div class="text-[11px] uppercase tracking-wide text-ink-muted">points</div>
          </div>
        </Link>
      </div>
    </div>
  </AppLayout>
</template>
