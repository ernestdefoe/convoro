<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ConvoroLogo from '@/Components/ConvoroLogo.vue';
import Avatar from '@/Components/forum/Avatar.vue';
import NotificationBell from '@/Components/forum/NotificationBell.vue';
import PwaBanner from '@/Components/PwaBanner.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';

const page = usePage();
const user = computed(() => (page.props as any).auth?.user ?? null);
const isAdmin = computed(() => !!(page.props as any).auth?.isAdmin);
const initials = computed(() => {
  if (!user.value) return '';
  const p = String(user.value.name).trim().split(/\s+/);
  return (p[0]?.[0] ?? '') + (p.length > 1 ? p[p.length - 1][0] : '');
});
</script>

<template>
  <div class="min-h-screen bg-appbg text-ink">
    <header class="sticky top-0 z-40 border-b border-line bg-surface/85 backdrop-blur">
      <div class="mx-auto flex h-[60px] max-w-[var(--c-container)] items-center gap-5 px-6">
        <Link href="/"><ConvoroLogo :size="34" /></Link>
        <nav class="ml-2 hidden items-center gap-1 md:flex">
          <Link href="/" class="rounded-lg px-3 py-2 text-sm font-semibold text-primary-700" :class="$page.component.startsWith('Forum') ? 'bg-primary-soft' : 'text-ink-2 hover:bg-surface-2'">Community</Link>
          <a class="rounded-lg px-3 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2">Members</a>
          <a class="rounded-lg px-3 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2">Leaderboard</a>
        </nav>
        <div class="ml-auto flex items-center gap-3">
          <div class="hidden items-center gap-2 rounded-full border border-line bg-surface-2 px-4 py-2 text-ink-muted sm:flex">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7" /><path d="m21 21-4.3-4.3" /></svg>
            <input class="w-40 border-0 bg-transparent p-0 text-sm text-ink placeholder:text-ink-muted focus:ring-0" placeholder="Search…" />
          </div>
          <ThemeToggle />
          <template v-if="user">
            <Link v-if="isAdmin" href="/admin" class="hidden rounded-lg px-3 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2 sm:block">Admin</Link>
            <NotificationBell />
            <Link href="/profile" aria-label="Your profile">
              <Avatar :avatar="{ initials, color: (user.id % 6) + 1 }" :size="34" />
            </Link>
          </template>
          <template v-else>
            <Link href="/login" class="rounded-lg px-3 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2">Log in</Link>
            <Link href="/register" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-primary/30 hover:bg-primary-600">Join</Link>
          </template>
        </div>
      </div>
    </header>

    <main class="mx-auto max-w-[var(--c-container)] px-6 py-6">
      <slot />
    </main>

    <PwaBanner />
  </div>
</template>
