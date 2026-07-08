<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, defineAsyncComponent, onBeforeUnmount, onMounted, ref, watchEffect } from 'vue';
import ConvoroLogo from '@/Components/ConvoroLogo.vue';
import Avatar from '@/Components/forum/Avatar.vue';
import NotificationBell from '@/Components/forum/NotificationBell.vue';
import UserMenu from '@/Components/forum/UserMenu.vue';
import MobileTabBar from '@/Components/forum/MobileTabBar.vue';
import PullToRefresh from '@/Components/PullToRefresh.vue';
import PwaBanner from '@/Components/PwaBanner.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import AuthModal from '@/Components/AuthModal.vue';
// Admin-only live theme builder — lazy so guests never download its chunk.
const ThemeEditor = defineAsyncComponent(() => import('@/Components/ThemeEditor.vue'));
import Toast from '@/Components/Toast.vue';
import CommandPalette from '@/Components/CommandPalette.vue';
import Slot from '@/Components/ext/Slot.vue';
import { convoro } from '@/lib/convoro-ext';
import { useAuthModal } from '@/lib/authModal';
import { useComposer } from '@/lib/composer';
import ComposerSheet from '@/Components/ComposerSheet.vue';
import { t } from '@/lib/i18n';

const hasFooter = computed(() => convoro.slotEntries('forum:footer').length > 0);

const auth = useAuthModal();
const composer = useComposer();

const page = usePage();
const user = computed(() => (page.props as any).auth?.user ?? null);
const isAdmin = computed(() => !!(page.props as any).auth?.isAdmin);
const activeTenant = computed(() => (page.props as any).activeTenant ?? null);
const storeOwner = computed(() => !!(page.props as any).storeOwner);
// Server-rendered extension nav links (instant, no async-bundle flash).
const extNav = computed(() => ((page.props as any).extNav ?? []) as { label: string; href: string; auth: boolean }[]);
const visibleExtNav = computed(() => extNav.value.filter((n) => !n.auth || !!user.value));
function navActive(href: string) {
  const url = String((page as any).url ?? '').split(/[?#]/)[0];
  return url === href || url.startsWith(href + '/');
}
// Once the nav gets busy, keep the first few extension links inline and tuck the
// rest into a "More" dropdown so the bar doesn't sprawl as extensions are added.
const useMoreNav = computed(() => visibleExtNav.value.length > 2);
const inlineExtNav = computed(() => (useMoreNav.value ? [] : visibleExtNav.value));
const moreExtNav = computed(() => (useMoreNav.value ? visibleExtNav.value : []));
const moreActive = computed(() => moreExtNav.value.some((n) => navActive(n.href)));
const moreOpen = ref(false);
const moreRoot = ref<HTMLElement | null>(null);
function onMoreDoc(e: MouseEvent) {
  if (moreOpen.value && moreRoot.value && !moreRoot.value.contains(e.target as Node)) moreOpen.value = false;
}
onMounted(() => document.addEventListener('click', onMoreDoc));
onBeforeUnmount(() => document.removeEventListener('click', onMoreDoc));
const dmUnread = computed(() => Number((page.props as any).dmUnread ?? 0));
const notifUnread = computed(() => Number((page.props as any).notifications?.unread ?? 0));
const mobileBar = computed(() => !!(page.props as any).mobileNav?.enabled && ((page.props as any).mobileNav?.tabs?.length ?? 0) >= 2);
function logout() {
  mobileOpen.value = false;
  router.post('/logout');
}
const siteLogo = computed(() => (page.props as any).site?.logo || '');
const siteLogoDark = computed(() => (page.props as any).site?.logoDark || '');
const initials = computed(() => {
  if (!user.value) return '';
  const p = String(user.value.name).trim().split(/\s+/);
  return (p[0]?.[0] ?? '') + (p.length > 1 ? p[p.length - 1][0] : '');
});

// Mobile nav
const mobileOpen = ref(false);
const locales = computed<Record<string, string>>(() => (page.props as any).i18n?.locales ?? {});
const currentLocale = computed(() => (page.props as any).i18n?.locale ?? 'en');
function setLocale(e: Event) {
  router.post('/locale', { locale: (e.target as HTMLSelectElement).value }, { preserveScroll: true });
}
// Auto-translate posts into the member's language (per-user preference).
const autoTranslate = computed(() => !!(page.props as any).auth?.user?.auto_translate);
function toggleAutoTranslate(e: Event) {
  router.post('/preferences/auto-translate', { auto: (e.target as HTMLInputElement).checked }, { preserveScroll: true });
}
// Apply text direction for right-to-left languages (e.g. Arabic).
watchEffect(() => {
  if (typeof document !== 'undefined') {
    document.documentElement.dir = (page.props as any).i18n?.rtl ? 'rtl' : 'ltr';
    // Footer wave on/off (admin theme option). The live theme editor overrides
    // this attribute directly for an instant preview.
    document.documentElement.dataset.footerWave = (page.props as any).site?.theme?.footerWave === false ? 'off' : 'on';
  }
});
function startTopic() {
  mobileOpen.value = false;
  user.value ? composer.open() : auth.open('register');
}

// Open the composer when arriving via ?compose= (the old /new route redirects
// here, preserving ?draft as compose=draft:<id>), then strip the param so a
// refresh doesn't reopen it.
onMounted(() => {
  if (typeof window === 'undefined') return;
  const params = new URLSearchParams(window.location.search);
  const c = params.get('compose');
  if (c && user.value) {
    composer.open({ draftId: c.startsWith('draft:') ? Number(c.slice(6)) || null : null });
    params.delete('compose');
    const qs = params.toString();
    window.history.replaceState({}, '', window.location.pathname + (qs ? '?' + qs : '') + window.location.hash);
  }
});
function goMobile(href: string) {
  mobileOpen.value = false;
  router.visit(href);
}
</script>

<template>
  <div class="min-h-screen bg-appbg text-ink">
    <!-- POC — you've stepped into one of your hosted communities -->
    <!-- Hosting-panel banner only — demos have no panel to exit to. -->
    <div v-if="activeTenant && activeTenant.kind !== 'demo'" class="flex flex-wrap items-center justify-center gap-x-3 gap-y-1 bg-primary px-4 py-1.5 text-center text-sm font-medium text-white">
      <span>{{ t('You’re inside your hosted community') }}: <b>{{ activeTenant.name }}</b> — {{ t('its own database, you’re the admin.') }}</span>
      <button type="button" class="rounded bg-white/20 px-2 py-0.5 text-xs font-bold hover:bg-white/30" @click="router.post('/panel/exit')">{{ t('Exit to panel') }}</button>
    </div>
    <a href="#main" class="skip-link">{{ t('Skip to content') }}</a>
    <header class="q-header sticky top-0 z-40 border-b border-line backdrop-blur">
      <div class="mx-auto flex h-[60px] max-w-[var(--c-container)] items-center gap-5 px-6">
        <Link href="/" class="flex items-center">
          <template v-if="siteLogo || siteLogoDark">
            <img :src="siteLogo || siteLogoDark" :alt="t('Logo')"class="block h-8 w-auto max-w-[180px] dark:hidden" />
            <img :src="siteLogoDark || siteLogo" :alt="t('Logo')"class="hidden h-8 w-auto max-w-[180px] dark:block" />
          </template>
          <ConvoroLogo v-else :size="34" />
        </Link>
        <nav class="ml-2 hidden items-center gap-1 md:flex">
          <Link href="/" class="rounded-lg px-3 py-2 text-sm font-semibold" :class="$page.component.startsWith('Forum') ? 'bg-primary/15 text-primary' : 'text-ink-2 hover:bg-surface-2'">{{ t('Community') }}</Link>
          <Link v-if="storeOwner" href="/extensions" class="rounded-lg px-3 py-2 text-sm font-semibold" :class="$page.component.startsWith('Extensions') ? 'bg-primary/15 text-primary' : 'text-ink-2 hover:bg-surface-2'">{{ t('Extensions') }}</Link>
          <Link href="/members" class="rounded-lg px-3 py-2 text-sm font-semibold" :class="$page.component === 'Members/Index' ? 'bg-primary/15 text-primary' : 'text-ink-2 hover:bg-surface-2'">{{ t('Members') }}</Link>
          <Link href="/groups" class="rounded-lg px-3 py-2 text-sm font-semibold" :class="$page.component.startsWith('Groups') ? 'bg-primary/15 text-primary' : 'text-ink-2 hover:bg-surface-2'">{{ t('Groups') }}</Link>
          <Link v-for="n in inlineExtNav" :key="n.href" :href="n.href" class="rounded-lg px-3 py-2 text-sm font-semibold" :class="navActive(n.href) ? 'bg-primary/15 text-primary' : 'text-ink-2 hover:bg-surface-2'">{{ t(n.label) }}</Link>
          <div v-if="moreExtNav.length" ref="moreRoot" class="relative">
            <button type="button" @click="moreOpen = !moreOpen" class="flex items-center gap-1 rounded-lg px-3 py-2 text-sm font-semibold" :class="moreActive || moreOpen ? 'bg-primary/15 text-primary' : 'text-ink-2 hover:bg-surface-2'">
              {{ t('More') }}
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="transition" :class="moreOpen ? 'rotate-180' : ''"><path d="m6 9 6 6 6-6" /></svg>
            </button>
            <div v-if="moreOpen" class="absolute left-0 top-[46px] z-50 w-52 overflow-hidden rounded-c border border-line bg-surface py-1 shadow-2xl shadow-black/15">
              <Link v-for="n in moreExtNav" :key="n.href" :href="n.href" @click="moreOpen = false" class="block px-4 py-2.5 text-sm font-medium" :class="navActive(n.href) ? 'bg-primary/10 text-primary' : 'text-ink-2 hover:bg-surface-2 hover:text-ink'">{{ t(n.label) }}</Link>
            </div>
          </div>
          <Slot name="header:nav" />
        </nav>
        <div class="ml-auto flex items-center gap-3">
          <select v-if="Object.keys(locales).length > 1" :value="currentLocale" @change="setLocale" :aria-label="t('Language')"
            class="hidden rounded-lg border-line bg-surface-2 py-1.5 pl-2 pr-7 text-xs font-semibold text-ink-2 focus:border-primary focus:ring-primary sm:block">
            <option v-for="(label, code) in locales" :key="code" :value="code">{{ label }}</option>
          </select>
          <label v-if="user" :title="t('Automatically translate posts into your language')"
            class="hidden cursor-pointer items-center gap-1.5 rounded-lg bg-surface-2 px-2 py-1.5 text-xs font-semibold text-ink-2 sm:flex">
            <input type="checkbox" :checked="autoTranslate" @change="toggleAutoTranslate" class="h-3.5 w-3.5 rounded border-line text-primary focus:ring-primary" />
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 8 6 6M4 14l6-6 2-3M2 5h12M7 2h1M22 22l-5-10-5 10M14 18h6" /></svg>
            <span>{{ t('Auto-translate') }}</span>
          </label>
          <ThemeToggle />
          <Slot name="header:end" />
          <template v-if="user">
            <!-- On mobile the bottom tab bar owns Messages, Alerts and the account
                 menu, so this top cluster is hidden there to drop the duplication
                 (its essentials move into the hamburger menu below). -->
            <div class="items-center gap-3" :class="mobileBar ? 'hidden md:flex' : 'flex'">
              <Link href="/messages" class="relative flex h-[34px] w-[34px] items-center justify-center rounded-full border border-line bg-surface-2 text-ink-2 hover:text-ink" :aria-label="t('Messages')">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" /></svg>
                <span v-if="dmUnread > 0" class="absolute -right-1 -top-1 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-primary px-1 text-[10px] font-bold text-white">{{ dmUnread > 99 ? '99+' : dmUnread }}</span>
              </Link>
              <NotificationBell />
              <UserMenu />
            </div>
          </template>
          <template v-else>
            <button type="button" class="hidden rounded-lg px-3 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2 sm:block" @click="auth.open('login')">{{ t('Log in') }}</button>
            <button type="button" class="hidden rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-primary/30 hover:bg-primary-600 sm:block" @click="auth.open('register')">{{ t('Join') }}</button>
          </template>
          <!-- Mobile menu toggle -->
          <button type="button" class="grid h-[34px] w-[34px] place-items-center rounded-full border border-line bg-surface-2 text-ink-2 hover:text-ink md:hidden" :aria-expanded="mobileOpen" :aria-label="t('Menu')" @click="mobileOpen = !mobileOpen">
            <svg v-if="!mobileOpen" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 6h18M3 12h18M3 18h18" /></svg>
            <svg v-else width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12" /></svg>
          </button>
        </div>
      </div>

      <!-- Mobile menu panel -->
      <Transition name="mnav">
        <div v-if="mobileOpen" class="border-t border-line bg-surface md:hidden">
          <nav class="mx-auto max-w-[var(--c-container)] space-y-1 px-4 py-3">
            <button v-if="!mobileBar" type="button" @click="startTopic" class="mb-2 flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-primary/30 hover:bg-primary-600">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 5v14M5 12h14" /></svg> {{ t('Start a discussion') }}
            </button>
            <button type="button" @click="goMobile('/')" class="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-semibold text-ink-2 hover:bg-surface-2">{{ t('Community') }}</button>
            <button v-if="storeOwner" type="button" @click="goMobile('/extensions')" class="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-semibold text-ink-2 hover:bg-surface-2">{{ t('Extensions') }}</button>
            <button type="button" @click="goMobile('/members')" class="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-semibold text-ink-2 hover:bg-surface-2">{{ t('Members') }}</button>
            <button type="button" @click="goMobile('/groups')" class="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-semibold text-ink-2 hover:bg-surface-2">{{ t('Groups') }}</button>
            <button v-for="n in visibleExtNav" :key="n.href" type="button" @click="goMobile(n.href)" class="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-semibold text-ink-2 hover:bg-surface-2">{{ t(n.label) }}</button>

            <!-- Account essentials — only when the top cluster is hidden (bottom bar
                 active), so settings / log-out stay reachable on mobile. -->
            <div v-if="user && mobileBar" class="!mt-3 space-y-1 border-t border-line pt-3">
              <button type="button" @click="goMobile('/notifications')" class="flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-left text-sm font-semibold text-ink-2 hover:bg-surface-2">
                <span>{{ t('Notifications') }}</span>
                <span v-if="notifUnread > 0" class="rounded-full bg-primary px-1.5 text-[10px] font-bold text-white">{{ notifUnread > 99 ? '99+' : notifUnread }}</span>
              </button>
              <button type="button" @click="goMobile('/messages')" class="flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-left text-sm font-semibold text-ink-2 hover:bg-surface-2">
                <span>{{ t('Messages') }}</span>
                <span v-if="dmUnread > 0" class="rounded-full bg-primary px-1.5 text-[10px] font-bold text-white">{{ dmUnread > 99 ? '99+' : dmUnread }}</span>
              </button>
              <button type="button" @click="goMobile(`/u/${user.id}`)" class="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-semibold text-ink-2 hover:bg-surface-2">{{ t('Your profile') }}</button>
              <button type="button" @click="goMobile('/profile')" class="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-semibold text-ink-2 hover:bg-surface-2">{{ t('Settings') }}</button>
              <a v-if="isAdmin" href="/admin" target="_blank" rel="noopener" class="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-semibold text-ink-2 hover:bg-surface-2">{{ t('Admin') }} ↗</a>
              <button type="button" @click="logout" class="block w-full rounded-lg px-3 py-2.5 text-left text-sm font-semibold text-red-500 hover:bg-surface-2">{{ t('Log out') }}</button>
            </div>

            <!-- Language + content auto-translate (the header controls are desktop-only). -->
            <div v-if="Object.keys(locales).length > 1 || user" class="!mt-3 space-y-2.5 border-t border-line pt-3">
              <label v-if="Object.keys(locales).length > 1" class="block">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-ink-muted">{{ t('Language') }}</span>
                <select :value="currentLocale" @change="setLocale" class="w-full rounded-lg border-line bg-surface-2 py-2 text-sm font-medium text-ink-2 focus:border-primary focus:ring-primary">
                  <option v-for="(label, code) in locales" :key="code" :value="code">{{ label }}</option>
                </select>
              </label>
              <label v-if="user" class="flex cursor-pointer items-center gap-2.5 rounded-lg bg-surface-2 px-3 py-2.5">
                <input type="checkbox" :checked="autoTranslate" @change="toggleAutoTranslate" class="h-4 w-4 rounded border-line text-primary focus:ring-primary" />
                <span class="text-sm font-medium text-ink-2">{{ t('Auto-translate posts into my language') }}</span>
              </label>
            </div>
            <template v-if="!user">
              <div class="!mt-3 flex gap-2 border-t border-line pt-3">
                <button type="button" class="flex-1 rounded-lg border border-line px-3 py-2.5 text-sm font-semibold text-ink-2 hover:bg-surface-2" @click="mobileOpen = false; auth.open('login')">{{ t('Log in') }}</button>
                <button type="button" class="flex-1 rounded-lg bg-primary px-3 py-2.5 text-sm font-semibold text-white hover:bg-primary-600" @click="mobileOpen = false; auth.open('register')">{{ t('Join') }}</button>
              </div>
            </template>
          </nav>
        </div>
      </Transition>
    </header>

    <main id="main" tabindex="-1" class="q-main mx-auto max-w-[var(--c-container)] px-6 py-6">
      <slot />
      <!-- Clear the fixed mobile tab bar so it never covers content. -->
      <div v-if="mobileBar" class="h-16 md:hidden" aria-hidden="true"></div>
    </main>

    <footer v-show="hasFooter" class="relative isolate mt-16">
      <!-- Prism wavy divider + aurora glow (replaces the old top border line) -->
      <div class="footer-waves pointer-events-none absolute inset-x-0 top-0 -z-10" aria-hidden="true">
        <svg class="wave wave-back" viewBox="0 0 2880 140" preserveAspectRatio="none">
          <defs><linearGradient id="prismWaveB" x1="0" x2="1" y1="0" y2="0"><stop offset="0" stop-color="#7c3aed" /><stop offset=".5" stop-color="#a855f7" /><stop offset="1" stop-color="#ec4899" /></linearGradient></defs>
          <path fill="url(#prismWaveB)" d="M0,70 C360,20 360,120 720,70 C1080,20 1080,120 1440,70 C1800,20 1800,120 2160,70 C2520,20 2520,120 2880,70 L2880,140 L0,140 Z" />
        </svg>
        <svg class="wave wave-front" viewBox="0 0 2880 140" preserveAspectRatio="none">
          <defs><linearGradient id="prismWaveF" x1="0" x2="1" y1="0" y2="0"><stop offset="0" stop-color="#ec4899" /><stop offset=".5" stop-color="#a855f7" /><stop offset="1" stop-color="#7c3aed" /></linearGradient></defs>
          <path fill="url(#prismWaveF)" d="M0,84 C360,132 360,36 720,84 C1080,132 1080,36 1440,84 C1800,132 1800,36 2160,84 C2520,132 2520,36 2880,84 L2880,140 L0,140 Z" />
        </svg>
      </div>
      <div class="footer-glow pointer-events-none absolute inset-0 -z-10" aria-hidden="true"></div>

      <div class="relative mx-auto flex max-w-[var(--c-container)] flex-wrap items-center justify-center gap-x-5 gap-y-2 px-6 pb-9 pt-24 text-sm text-ink-muted">
        <Slot name="forum:footer" />
      </div>
    </footer>

    <MobileTabBar />
    <PullToRefresh />
    <PwaBanner />
    <AuthModal />
    <ComposerSheet v-if="user" />
    <ThemeEditor v-if="isAdmin" />
    <Toast />
    <CommandPalette />
  </div>
</template>

<style scoped>
.mnav-enter-active, .mnav-leave-active { transition: opacity 0.18s ease, transform 0.18s ease; }
.mnav-enter-from, .mnav-leave-to { opacity: 0; transform: translateY(-6px); }

/* Wavy Prism footer */
.footer-waves { height: 5.5rem; overflow: hidden; }
[data-footer-wave='off'] .footer-waves,
[data-footer-wave='off'] .footer-glow { display: none; }
.wave {
  position: absolute; left: 0; top: 0; display: block;
  width: 200%; height: 5.5rem; will-change: transform;
  -webkit-mask: linear-gradient(to bottom, #000, #000 42%, transparent 96%);
  mask: linear-gradient(to bottom, #000, #000 42%, transparent 96%);
}
.wave-back { opacity: .14; animation: footerWave 23s linear infinite; }
.wave-front { opacity: .22; animation: footerWaveRev 17s linear infinite; }
[data-theme='dark'] .wave-back { opacity: .22; }
[data-theme='dark'] .wave-front { opacity: .32; }
@keyframes footerWave { from { transform: translateX(0); } to { transform: translateX(-50%); } }
@keyframes footerWaveRev { from { transform: translateX(-50%); } to { transform: translateX(0); } }
.footer-glow { background: radial-gradient(115% 80% at 50% 118%, rgb(168 85 247 / .14), rgb(236 72 153 / .07) 45%, transparent 72%); }
[data-theme='dark'] .footer-glow { background: radial-gradient(115% 85% at 50% 122%, rgb(168 85 247 / .22), rgb(236 72 153 / .12) 45%, transparent 74%); }
@media (prefers-reduced-motion: reduce) { .wave { animation: none; } }
</style>
