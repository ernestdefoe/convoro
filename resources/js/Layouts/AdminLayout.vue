<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import Toast from '@/Components/Toast.vue';
import CommandPalette from '@/Components/CommandPalette.vue';
import { useCommandPalette } from '@/lib/commandPalette';

const page = usePage();
const palette = useCommandPalette();
const current = computed(() => page.component);
const url = computed(() => (page.props as any).ziggy?.location ?? (page as any).url ?? '');

const extNav = computed(() => ((page.props as any).adminExtNav ?? []) as { id: string; name: string; href: string }[]);
const storeOwner = computed(() => !!(page.props as any).storeOwner);

const I = {
  dash: 'M3 12l9-9 9 9M5 10v10h14V10',
  members: 'M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2 M9 11a4 4 0 100-8 4 4 0 000 8 M23 21v-2a4 4 0 0 0-3-3.87 M16 3.13a4 4 0 010 7.75',
  tags: 'M3 7h18M3 12h18M3 17h10',
  pwa: 'M12 2a10 10 0 100 20 10 10 0 000-20M2 12h20M12 2a15 15 0 010 20M12 2a15 15 0 000 20',
  cog: 'M12 15a3 3 0 100-6 3 3 0 000 6z M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 11-4 0v-.09A1.65 1.65 0 009 19.4',
  mail: 'M4 4h16a2 2 0 012 2v12a2 2 0 01-2 2H4a2 2 0 01-2-2V6a2 2 0 012-2z M22 6l-10 7L2 6',
  market: 'M3 9l1-5h16l1 5M4 9v10h16V9M9 9v10M15 9v10',
  store: 'M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z',
  system: 'M12 15a3 3 0 100-6 3 3 0 000 6z M4 12h2M18 12h2M12 4v2M12 18v2',
  puzzle: 'M4 7h3a2 2 0 002-2 2 2 0 114 0 2 2 0 002 2h3v3a2 2 0 002 2 2 2 0 110 4 2 2 0 00-2 2v3h-3',
  shield: 'M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z',
  import: 'M12 3v12M8 11l4 4 4-4M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2',
  invite: 'M4 4h16v16H4zM4 7l8 6 8-6',
  ai: 'M12 2a2 2 0 012 2v1h2a2 2 0 012 2v2h1a2 2 0 010 4h-1v2a2 2 0 01-2 2h-2v1a2 2 0 01-4 0v-1H6a2 2 0 01-2-2v-2H3a2 2 0 010-4h1V7a2 2 0 012-2h2V4a2 2 0 012-2zM9 10h.01M15 10h.01M9 15h6',
  globe: 'M12 2a10 10 0 100 20 10 10 0 000-20M2 12h20M12 2a15 15 0 010 20M12 2a15 15 0 000 20',
};

// Distinct icons for extension nav entries, matched on the extension's id/name so
// each add-on gets its own glyph instead of every one sharing the puzzle piece.
// Unknown extensions fall back to the puzzle.
const EXT = {
  megaphone: 'M3 11v2a1 1 0 001 1h3l5 4V5L7 10H4a1 1 0 00-1 1z M16 9a4 4 0 010 6',
  chart: 'M3 3v18h18 M7 14l3-3 3 3 5-6',
  privacy: 'M6 11h12v9H6z M9 11V7a3 3 0 016 0v4',
  cookie: 'M12 3a9 9 0 109 9 3 3 0 01-3-3 3 3 0 01-3-3 9 9 0 00-3-3z M9 12h.01 M13 15h.01 M15 10h.01',
  social: 'M9 15l6-6 M10 6l1-1a4 4 0 115 5l-1 1 M14 18l-1 1a4 4 0 11-5-5l1-1',
  badge: 'M12 14a5 5 0 100-10 5 5 0 000 10z M9 13l-1 8 4-2.5 4 2.5-1-8',
  rss: 'M4 11a9 9 0 019 9 M4 4a16 16 0 0116 16 M6 18.5a1.5 1.5 0 100-3 1.5 1.5 0 000 3z',
  queue: 'M4 5h16v5H4z M4 14h16v5H4z M8 7.5h.01 M8 16.5h.01',
};

function extIcon(e: { id?: string; name?: string }): string {
  const s = ((e.id ?? '') + ' ' + (e.name ?? '')).toLowerCase();
  if (s.includes('announc')) return EXT.megaphone;
  if (s.includes('analytic') || s.includes('stat')) return EXT.chart;
  if (s.includes('cookie')) return EXT.cookie;
  if (s.includes('gdpr') || s.includes('privacy') || s.includes('consent')) return EXT.privacy;
  if (s.includes('social') || s.includes('login') || s.includes('oauth')) return EXT.social;
  if (s.includes('badge') || s.includes('award')) return EXT.badge;
  if (s.includes('rss') || s.includes('feed')) return EXT.rss;
  if (s.includes('horizon') || s.includes('queue')) return EXT.queue;
  return I.puzzle;
}

const groups = computed(() => [
  { label: null, items: [{ label: 'Dashboard', href: '/admin', component: 'Admin/Dashboard', icon: I.dash }] },
  { label: 'Community', items: [
    { label: 'Members', href: '/admin/members', component: 'Admin/Members', icon: I.members },
    { label: 'Categories & Tags', href: '/admin/content', component: 'Admin/Content', icon: I.tags },
    { label: 'Moderation', href: '/admin/moderation', component: 'Admin/Moderation', icon: I.shield },
    { label: 'Invites', href: '/admin/invites', component: 'Admin/Invites', icon: I.invite },
  ] },
  { label: 'Configuration', items: [
    { label: 'Settings', href: '/admin/settings', component: 'Admin/Settings', icon: I.cog },
    { label: 'Email', href: '/admin/email', component: 'Admin/Email', icon: I.mail },
    { label: 'Languages', href: '/admin/languages', component: 'Admin/Languages', icon: I.globe },
    { label: 'PWA', href: '/admin/pwa', component: 'Admin/Pwa', icon: I.pwa },
    { label: 'Single sign-on', href: '/admin/sso', component: 'Admin/Sso', icon: I.shield },
  ] },
  { label: 'Extensions', items: [
    { label: 'Marketplace', href: '/admin/marketplace', component: 'Admin/Marketplace', icon: I.market },
    // The seller/store console lives front-facing now (convoro.co/extensions/manage),
    // reached from the forum's "Extensions" nav — no admin Store section.
    ...extNav.value.map((e) => ({ label: e.name, href: e.href, component: '', icon: extIcon(e) })),
  ] },
  { label: 'System', items: [
    { label: 'AI', href: '/admin/ai', component: 'Admin/Ai', icon: I.ai },
    { label: 'Import', href: '/admin/import', component: 'Admin/Import', icon: I.import },
  ] },
]);

const banner = () => (page.props as any).updateBanner ?? null;

function active(item: { component: string; href: string }) {
  if (item.component) return current.value === item.component;
  return typeof url.value === 'string' && url.value.startsWith(item.href);
}
</script>

<template>
  <div class="flex min-h-screen bg-appbg text-ink">
    <!-- Sidebar -->
    <aside class="hidden w-[240px] shrink-0 flex-col border-r border-line bg-surface p-4 md:flex">
      <div class="mb-6 flex items-center gap-2 px-2">
        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-500 to-violet-500 font-extrabold text-white">C</span>
        <span class="text-sm font-bold text-ink">Convoro Admin</span>
      </div>

      <nav class="flex flex-col gap-1 overflow-y-auto">
        <template v-for="(group, gi) in groups" :key="gi">
          <div v-if="group.label" class="px-3 pb-1 pt-4 text-[10px] font-bold uppercase tracking-wider text-ink-muted">{{ group.label }}</div>
          <Link
            v-for="item in group.items"
            :key="item.href"
            :href="item.href"
            class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition"
            :class="active(item) ? 'bg-surface-2 text-ink' : 'text-ink-2 hover:bg-surface-2 hover:text-ink'"
          >
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path :d="item.icon" /></svg>
            {{ item.label }}
          </Link>
        </template>
      </nav>

      <Link href="/" class="mt-auto flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-ink-2 hover:bg-surface-2 hover:text-ink">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7" /></svg>
        Back to site
      </Link>
    </aside>

    <!-- Content -->
    <div class="flex-1">
      <header class="flex items-center gap-4 border-b border-line bg-surface/70 px-6 py-4">
        <div class="min-w-0 flex-1">
          <h1 class="text-lg font-bold text-ink"><slot name="title">Admin</slot></h1>
          <p v-if="$slots.subtitle" class="mt-0.5 text-sm text-ink-2"><slot name="subtitle" /></p>
        </div>
        <button type="button" @click="palette.open()"
          class="hidden items-center gap-2 rounded-lg border border-line bg-surface-2 px-3 py-1.5 text-sm text-ink-muted hover:text-ink sm:flex"
          aria-label="Open command palette">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7" /><path d="m21 21-4.3-4.3" /></svg>
          <span>Search</span>
          <kbd class="rounded border border-line bg-surface px-1.5 text-[10px] font-semibold">⌘K</kbd>
        </button>
        <ThemeToggle />
      </header>
      <main class="p-6">
        <Link v-if="banner()?.available" href="/admin/marketplace" class="mb-5 flex items-center gap-3 rounded-xl border border-indigo-500/40 bg-indigo-500/10 px-4 py-3 text-sm">
          <span class="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-500/30 text-indigo-300">↑</span>
          <span class="text-ink">Convoro <strong>{{ banner().latest }}</strong> is available.</span>
          <span class="ml-auto font-semibold text-indigo-300">Update →</span>
        </Link>
        <slot />
      </main>
    </div>
    <Toast />
    <CommandPalette />
  </div>
</template>
