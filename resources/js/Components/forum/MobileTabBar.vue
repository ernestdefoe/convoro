<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Avatar from '@/Components/forum/Avatar.vue';
import { useAuthModal } from '@/lib/authModal';
import { t } from '@/lib/i18n';

const page = usePage();
const auth = useAuthModal();
const user = computed(() => (page.props as any).auth?.user ?? null);
const cfg = computed(() => (page.props as any).mobileNav ?? { enabled: true, tabs: [] });
// The centre button shows the community's brand mark when one is set (favicon is
// square so it fits the circle best; fall back to the logo, then the + icon).
const brandImg = computed(() => {
  const s = (page.props as any).site ?? {};
  return s.favicon || s.logo || s.icon || '';
});
const dmUnread = computed(() => Number((page.props as any).dmUnread ?? 0));
const notifUnread = computed(() => Number((page.props as any).notifications?.unread ?? 0));

// Icons (stroke paths, 24x24).
const I: Record<string, string> = {
  home: 'M3 11l9-8 9 8M5 9v11h5v-6h4v6h5V9',
  search: 'M11 4a7 7 0 100 14 7 7 0 000-14z M21 21l-4.3-4.3',
  compose: 'M12 5v14M5 12h14',
  notifications: 'M6 9a6 6 0 1112 0c0 5 2 6 2 7H4c0-1 2-2 2-7z M10.5 21a2 2 0 003 0',
  messages: 'M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z',
  members: 'M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2 M9 11a4 4 0 100-8 4 4 0 000 8 M23 21v-2a4 4 0 00-3-3.87 M16 3.13a4 4 0 010 7.75',
  leaderboard: 'M8 21h8M12 17v4M7 4h10v4a5 5 0 01-10 0V4z M5 4H3v2a3 3 0 003 3 M19 4h2v2a3 3 0 01-3 3',
  account: 'M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2 M12 11a4 4 0 100-8 4 4 0 000 8',
};

type Tab = { key: string; label: string; href?: string; icon: string; auth?: boolean; center?: boolean; badge?: 'notif' | 'dm'; active: (c: string, u: string) => boolean };

const CATALOG: Record<string, Tab> = {
  home: { key: 'home', label: t('Home'), href: '/', icon: I.home, active: (c) => c.startsWith('Forum') },
  search: { key: 'search', label: t('Search'), href: '/search', icon: I.search, active: (c) => c.startsWith('Search') },
  compose: { key: 'compose', label: t('Post'), href: '/new', icon: I.compose, auth: true, center: true, active: (c) => c === 'Topic/Create' },
  notifications: { key: 'notifications', label: t('Alerts'), href: '/notifications', icon: I.notifications, auth: true, badge: 'notif', active: (c) => c.startsWith('Notifications') },
  messages: { key: 'messages', label: t('Messages'), href: '/messages', icon: I.messages, auth: true, badge: 'dm', active: (c) => c.startsWith('Messages') },
  members: { key: 'members', label: t('Members'), href: '/members', icon: I.members, active: (c) => c === 'Members/Index' },
  account: { key: 'account', label: t('You'), icon: I.account, active: (c) => c.startsWith('Profile') || c === 'Settings' },
};

// Resolve the configured keys to tabs, dropping auth-only tabs for guests
// (except 'account', which becomes a sign-in prompt, and 'compose').
const tabs = computed<Tab[]>(() =>
  (cfg.value.tabs ?? [])
    .map((k: string) => CATALOG[k])
    .filter((tb: Tab | undefined): tb is Tab => !!tb)
    .filter((tb: Tab) => user.value || !tb.auth || tb.key === 'account' || tb.key === 'compose'),
);

const show = computed(() => cfg.value.enabled && tabs.value.length >= 2);

function badge(tb: Tab): number {
  if (tb.badge === 'notif') return notifUnread.value;
  if (tb.badge === 'dm') return dmUnread.value;
  return 0;
}
function isActive(tb: Tab): boolean {
  return tb.active(String(page.component), String((page.props as any).ziggy?.location ?? ''));
}
function go(tb: Tab, e: Event) {
  // Auth-gated taps open the sign-in modal instead of navigating.
  if (!user.value && (tb.key === 'compose' || tb.key === 'account' || tb.auth)) {
    e.preventDefault();
    auth.open(tb.key === 'compose' ? 'register' : 'login');
    return;
  }
  if (tb.key === 'account' && user.value) {
    e.preventDefault();
    router.visit(`/u/${user.value.id}`);
  }
}
</script>

<template>
  <nav v-if="show" class="mobile-tabbar fixed inset-x-0 bottom-0 z-40 border-t border-line bg-surface/95 backdrop-blur md:hidden">
    <div class="mx-auto flex max-w-[var(--c-container)] items-stretch justify-around">
      <component
        :is="tab.href ? Link : 'button'"
        v-for="tab in tabs"
        :key="tab.key"
        :href="tab.href"
        type="button"
        class="relative flex flex-1 flex-col items-center justify-center gap-0.5 py-2 text-[11px] font-semibold"
        :class="[tab.center ? '' : (isActive(tab) ? 'text-primary' : 'text-ink-muted hover:text-ink-2')]"
        :aria-label="tab.label"
        @click="(e: Event) => go(tab, e)"
      >
        <!-- Center compose: raised accent circle, branded with the site mark if set -->
        <template v-if="tab.center">
          <span class="-mt-5 grid h-12 w-12 place-items-center overflow-hidden rounded-full shadow-lg shadow-primary/40 ring-4 ring-surface" :class="brandImg ? 'bg-white' : 'bg-primary text-white'">
            <img v-if="brandImg" :src="brandImg" alt="" class="h-8 w-8 object-contain" />
            <svg v-else width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path :d="tab.icon" /></svg>
          </span>
          <span class="-mt-1 text-ink-muted">{{ tab.label }}</span>
        </template>
        <template v-else>
          <span class="relative">
            <!-- Account tab shows the avatar when signed in. -->
            <Avatar v-if="tab.key === 'account' && user" :avatar="{ id: user.id, initials: '', color: (user.id % 6) + 1, avatar: user.avatar_path }" :size="24" />
            <svg v-else width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path :d="tab.icon" /></svg>
            <span v-if="badge(tab) > 0" class="absolute -right-2 -top-1.5 flex h-[16px] min-w-[16px] items-center justify-center rounded-full bg-primary px-1 text-[9px] font-bold text-white">{{ badge(tab) > 99 ? '99+' : badge(tab) }}</span>
          </span>
          <span>{{ tab.label }}</span>
        </template>
      </component>
    </div>
  </nav>
</template>

<style scoped>
.mobile-tabbar {
  padding-bottom: env(safe-area-inset-bottom);
}
</style>
