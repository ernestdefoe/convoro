<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useCommandPalette } from '@/lib/commandPalette';
import { t } from '@/lib/i18n';
import { setTheme } from '@/lib/theme';

interface Cmd { label: string; group: string; keywords?: string; href?: string; action?: () => void }

const page = usePage();
const isAdmin = computed(() => !!(page.props as any).auth?.isAdmin);

const { isOpen: open } = useCommandPalette();
const query = ref('');
const active = ref(0);
const inputEl = ref<HTMLInputElement | null>(null);

function toggleTheme() {
  setTheme(document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark');
}

const commands = computed<Cmd[]>(() => {
  const c: Cmd[] = [
    { label: t('Home'), group: t('Go to'), href: '/', keywords: 'forum index feed' },
    { label: t('Extensions & themes'), group: t('Go to'), href: '/extensions', keywords: 'marketplace directory store addons' },
    { label: t('Members'), group: t('Go to'), href: '/members', keywords: 'users people' },
    { label: t('Messages'), group: t('Go to'), href: '/messages', keywords: 'dm inbox chat' },
    { label: t('Notifications'), group: t('Go to'), href: '/notifications', keywords: 'alerts' },
    { label: t('Profile settings'), group: t('Account'), href: '/profile', keywords: 'avatar bio account me' },
    { label: t('Notification preferences'), group: t('Account'), href: '/settings', keywords: 'email digest preferences' },
    { label: t('My licenses'), group: t('Account'), href: '/account/licenses', keywords: 'purchases premium keys' },
  ];

  if (isAdmin.value) {
    c.push(
      { label: t('Admin dashboard'), group: t('Admin'), href: '/admin', keywords: 'stats overview' },
      { label: t('Members'), group: t('Admin'), href: '/admin/members', keywords: 'users manage' },
      { label: t('Categories & tags'), group: t('Admin'), href: '/admin/content', keywords: 'taxonomy' },
      { label: t('Moderation'), group: t('Admin'), href: '/admin/moderation', keywords: 'spam flags ban' },
      { label: t('Marketplace'), group: t('Admin'), href: '/admin/marketplace', keywords: 'extensions install' },
      { label: t('Email'), group: t('Admin'), href: '/admin/email', keywords: 'mail smtp' },
      { label: t('PWA'), group: t('Admin'), href: '/admin/pwa', keywords: 'app icon push' },
      { label: t('Settings'), group: t('Admin'), href: '/admin/settings', keywords: 'config' },
      { label: t('System'), group: t('Admin'), href: '/admin/system', keywords: 'maintenance updates version' },
    );
  }

  c.push(
    { label: t('Changelog'), group: t('Company'), href: 'https://convoro.co/changelog', keywords: 'releases whats new' },
    { label: t('Roadmap'), group: t('Company'), href: 'https://convoro.co/roadmap', keywords: 'plans coming soon' },
    { label: t('System status'), group: t('Company'), href: 'https://convoro.co/status', keywords: 'uptime health' },
    { label: t('Toggle light / dark mode'), group: t('Actions'), action: toggleTheme, keywords: 'theme appearance dark light' },
  );
  return c;
});

const filtered = computed<Cmd[]>(() => {
  const q = query.value.trim().toLowerCase();
  if (!q) return commands.value;
  return commands.value.filter((c) => (c.label + ' ' + c.group + ' ' + (c.keywords || '')).toLowerCase().includes(q));
});

// Group the filtered flat list for display while keeping a flat index for keys.
const groups = computed(() => {
  const out: { group: string; items: { cmd: Cmd; index: number }[] }[] = [];
  filtered.value.forEach((cmd, index) => {
    let g = out.find((o) => o.group === cmd.group);
    if (!g) { g = { group: cmd.group, items: [] }; out.push(g); }
    g.items.push({ cmd, index });
  });
  return out;
});

function hide() { open.value = false; }

// Focus + reset whenever the palette opens (via ⌘K or any external button).
watch(open, (v) => {
  if (v) {
    query.value = '';
    active.value = 0;
    nextTick(() => inputEl.value?.focus());
  }
});
function run(cmd: Cmd) {
  hide();
  if (cmd.action) { cmd.action(); return; }
  if (!cmd.href) return;
  if (/^https?:\/\//.test(cmd.href)) window.location.href = cmd.href;
  else router.visit(cmd.href);
}

function onKeydown(e: KeyboardEvent) {
  if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
    e.preventDefault();
    open.value = !open.value;
    return;
  }
  if (!open.value) return;
  if (e.key === 'Escape') { hide(); }
  else if (e.key === 'ArrowDown') { e.preventDefault(); active.value = Math.min(active.value + 1, filtered.value.length - 1); }
  else if (e.key === 'ArrowUp') { e.preventDefault(); active.value = Math.max(active.value - 1, 0); }
  else if (e.key === 'Enter') { e.preventDefault(); const c = filtered.value[active.value]; if (c) run(c); }
}

watch(query, () => { active.value = 0; });

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));
</script>

<template>
  <Teleport to="body">
    <Transition name="cp-fade">
      <div v-if="open" class="fixed inset-0 z-[10000] flex items-start justify-center px-4 pt-[12vh]" @click.self="hide">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm"></div>
        <div class="relative w-full max-w-xl overflow-hidden rounded-2xl border border-line bg-surface shadow-2xl">
          <div class="flex items-center gap-3 border-b border-line px-4">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-ink-muted"><circle cx="11" cy="11" r="7" /><path d="m21 21-4.3-4.3" /></svg>
            <input ref="inputEl" v-model="query" type="text" :placeholder="t('Search or jump to…')"
              class="w-full border-0 bg-transparent py-4 text-ink placeholder:text-ink-muted focus:outline-none focus:ring-0" />
            <kbd class="hidden rounded border border-line bg-surface-2 px-1.5 py-0.5 text-[10px] font-semibold text-ink-muted sm:block">ESC</kbd>
          </div>

          <div class="max-h-[55vh] overflow-y-auto p-2">
            <div v-if="!filtered.length" class="px-3 py-8 text-center text-sm text-ink-muted">{{ t('No matches for “{query}”.', { query }) }}</div>
            <template v-for="g in groups" :key="g.group">
              <div class="px-3 pb-1 pt-3 text-[10px] font-bold uppercase tracking-wider text-ink-muted">{{ g.group }}</div>
              <button v-for="it in g.items" :key="it.index" type="button"
                class="flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm"
                :class="it.index === active ? 'bg-primary/10 text-primary' : 'text-ink-2 hover:bg-surface-2'"
                @mouseenter="active = it.index" @click="run(it.cmd)">
                <span class="flex-1 font-medium">{{ it.cmd.label }}</span>
                <svg v-if="it.index === active" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18l6-6-6-6" /></svg>
              </button>
            </template>
          </div>

          <div class="flex items-center gap-3 border-t border-line px-4 py-2 text-[11px] text-ink-muted">
            <span><kbd class="font-mono">↑↓</kbd> {{ t('navigate') }}</span>
            <span><kbd class="font-mono">↵</kbd> {{ t('open') }}</span>
            <span class="ml-auto"><kbd class="font-mono">⌘K</kbd> {{ t('anytime') }}</span>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.cp-fade-enter-active, .cp-fade-leave-active { transition: opacity 0.15s ease; }
.cp-fade-enter-from, .cp-fade-leave-to { opacity: 0; }
</style>
