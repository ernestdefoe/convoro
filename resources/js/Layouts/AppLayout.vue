<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ConvoroLogo from '@/Components/ConvoroLogo.vue';
import Avatar from '@/Components/forum/Avatar.vue';
import NotificationBell from '@/Components/forum/NotificationBell.vue';
import UserMenu from '@/Components/forum/UserMenu.vue';
import PwaBanner from '@/Components/PwaBanner.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import AuthModal from '@/Components/AuthModal.vue';
import ThemeEditor from '@/Components/ThemeEditor.vue';
import Slot from '@/Components/ext/Slot.vue';
import { useAuthModal } from '@/lib/authModal';

const auth = useAuthModal();

const page = usePage();
const user = computed(() => (page.props as any).auth?.user ?? null);
const isAdmin = computed(() => !!(page.props as any).auth?.isAdmin);
const dmUnread = computed(() => Number((page.props as any).dmUnread ?? 0));
const siteLogo = computed(() => (page.props as any).site?.logo || '');
const initials = computed(() => {
  if (!user.value) return '';
  const p = String(user.value.name).trim().split(/\s+/);
  return (p[0]?.[0] ?? '') + (p.length > 1 ? p[p.length - 1][0] : '');
});
</script>

<template>
  <div class="min-h-screen bg-appbg text-ink">
    <a href="#main" class="skip-link">Skip to content</a>
    <header class="sticky top-0 z-40 border-b border-line bg-surface/85 backdrop-blur">
      <div class="mx-auto flex h-[60px] max-w-[var(--c-container)] items-center gap-5 px-6">
        <Link href="/" class="flex items-center">
          <img v-if="siteLogo" :src="siteLogo" alt="Logo" class="h-8 w-auto max-w-[180px]" />
          <ConvoroLogo v-else :size="34" />
        </Link>
        <nav class="ml-2 hidden items-center gap-1 md:flex">
          <Link href="/" class="rounded-lg px-3 py-2 text-sm font-semibold" :class="$page.component.startsWith('Forum') ? 'bg-primary/15 text-primary' : 'text-ink-2 hover:bg-surface-2'">Community</Link>
          <Link href="/extensions" class="rounded-lg px-3 py-2 text-sm font-semibold" :class="$page.component.startsWith('Extensions') ? 'bg-primary/15 text-primary' : 'text-ink-2 hover:bg-surface-2'">Extensions</Link>
          <Link href="/members" class="rounded-lg px-3 py-2 text-sm font-semibold" :class="$page.component === 'Members/Index' ? 'bg-primary/15 text-primary' : 'text-ink-2 hover:bg-surface-2'">Members</Link>
          <Link href="/leaderboard" class="rounded-lg px-3 py-2 text-sm font-semibold" :class="$page.component === 'Members/Leaderboard' ? 'bg-primary/15 text-primary' : 'text-ink-2 hover:bg-surface-2'">Leaderboard</Link>
        </nav>
        <div class="ml-auto flex items-center gap-3">
          <div class="hidden items-center gap-2 rounded-full border border-line bg-surface-2 px-4 py-2 text-ink-muted sm:flex">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7" /><path d="m21 21-4.3-4.3" /></svg>
            <input class="w-40 border-0 bg-transparent p-0 text-sm text-ink placeholder:text-ink-muted focus:ring-0" placeholder="Search…" />
          </div>
          <ThemeToggle />
          <Slot name="header:end" />
          <template v-if="user">
            <Link href="/messages" class="relative flex h-[34px] w-[34px] items-center justify-center rounded-full border border-line bg-surface-2 text-ink-2 hover:text-ink" aria-label="Messages">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" /></svg>
              <span v-if="dmUnread > 0" class="absolute -right-1 -top-1 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-primary px-1 text-[10px] font-bold text-white">{{ dmUnread > 99 ? '99+' : dmUnread }}</span>
            </Link>
            <NotificationBell />
            <UserMenu />
          </template>
          <template v-else>
            <button type="button" class="rounded-lg px-3 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2" @click="auth.open('login')">Log in</button>
            <button type="button" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-primary/30 hover:bg-primary-600" @click="auth.open('register')">Join</button>
          </template>
        </div>
      </div>
    </header>

    <main id="main" tabindex="-1" class="mx-auto max-w-[var(--c-container)] px-6 py-6">
      <slot />
    </main>

    <Slot name="forum:footer" />

    <PwaBanner />
    <AuthModal />
    <ThemeEditor />
  </div>
</template>
