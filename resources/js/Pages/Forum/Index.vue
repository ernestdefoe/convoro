<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch, watchEffect, onMounted, onUnmounted } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import TopicCard from '@/Components/forum/TopicCard.vue';
import Avatar from '@/Components/forum/Avatar.vue';
import CategoryIcon from '@/Components/forum/CategoryIcon.vue';
import PrismHero from '@/Components/forum/PrismHero.vue';
import Slot from '@/Components/ext/Slot.vue';
import EmptyState from '@/Components/EmptyState.vue';
import AskBar from '@/Components/AskBar.vue';
import DonateWidget from '@/Components/DonateWidget.vue';
import { useAuthModal } from '@/lib/authModal';
import { t as tr } from '@/lib/i18n';
import { convoro } from '@/lib/convoro-ext';

const pg = usePage();
const auth = useAuthModal();
const loggedIn = computed(() => !!(pg.props as any).auth?.user);
const askEnabled = computed(() => !!(pg.props as any).ask?.enabled);
const donate = computed(() => (pg.props as any).donate || {});
// The mobile tab bar (phones) carries a compose button, so on phones the FAB is
// redundant — keep it only on tablets (md–lg), where the bar is hidden.
const barHasCompose = computed(() => {
  const m = (pg.props as any).mobileNav;
  return !!m?.enabled && (m?.tabs ?? []).includes('compose');
});
function startTopic() {
  loggedIn.value ? router.visit('/new') : auth.open('register');
}

// Search moved out of the header and surfaced prominently above the discussion list.
const search = ref('');
function goSearch() {
  const q = search.value.trim();
  if (q) router.visit('/search?q=' + encodeURIComponent(q));
}

const props = defineProps<{
  view: 'feed' | 'grid' | 'category';
  sort: string;
  activeCategory: string | null;
  categories: any[];
  topics: { data: any[]; next: string | null };
  stats: Record<string, number>;
  widgets?: { key: string; enabled?: boolean }[];
  widgetData?: any;
  aboutHtml?: string;
  aboutTitle?: string;
  defaultCover?: string | null;
  hero?: any;
  tags?: any[];
  subtags?: any;
  activeTag?: string | null;
}>();

// Live badges: keep topics that were live on load, and poll for who's reading now.
const initialLive = new Set<number>(props.topics.data.filter((t: any) => t.isLive).map((t: any) => t.id));
const liveSet = ref<Set<number>>(new Set(initialLive));
const withLive = (t: any) => ({ ...t, isLive: liveSet.value.has(t.id) });
let liveTimer: any = null;
async function pollLive() {
  const ids = props.topics.data.map((t: any) => t.id);
  if (!ids.length) return;
  try {
    const r = await fetch('/api/live-topics?ids=' + ids.join(','), { headers: { Accept: 'application/json' } });
    const d = await r.json();
    liveSet.value = new Set<number>([...initialLive, ...((d.live as number[]) || [])]);
  } catch { /* ignore */ }
}
onMounted(() => { liveTimer = setInterval(pollLive, 20000); });
onUnmounted(() => { if (liveTimer) clearInterval(liveTimer); });

// ── Live topic list ───────────────────────────────────────────────────────
// New threads appear and replied-to threads bump to the top without a refresh.
// We mutate a local copy (re-synced whenever the server sends a fresh page) so
// pagination/sort/filter navigation still works. Only applied when sorted by
// recent activity (a live bump only makes sense there) and within the active
// category filter, if any.
const items = ref<any[]>([...props.topics.data]);
watch(() => props.topics.data, (d) => { items.value = [...d]; });

function onTopicActivity(e: any) {
  const card = e?.topic;
  if (!card || props.sort !== 'recent') return;
  if (props.activeCategory && card.category?.slug !== props.activeCategory) return;
  // Upsert: drop any existing entry, then insert the fresh card flagged "new".
  // Respect pinning — pinned topics stay on top, so a bumped non-pinned thread
  // goes to the top of the NON-pinned section, never above pinned ones (mirrors
  // the server's `orderByDesc(is_pinned)` then recency).
  const next = items.value.filter((t: any) => t.id !== card.id);
  const fresh = { ...card, isNew: true };
  if (fresh.isPinned) {
    next.unshift(fresh);
  } else {
    const firstNonPinned = next.findIndex((t: any) => !t.isPinned);
    next.splice(firstNonPinned === -1 ? next.length : firstNonPinned, 0, fresh);
  }
  items.value = next.slice(0, 30);
}

let forumChannel: any = null;
onMounted(() => {
  const E = (window as any).Echo;
  if (E) forumChannel = E.channel('forum').listen('.TopicListUpdated', onTopicActivity);
});
onUnmounted(() => { if (forumChannel && (window as any).Echo) (window as any).Echo.leave('forum'); });

// ── Sidebar widgets (extension-driven) ───────────────────────────────────
// Built-in and add-on widgets all register into the `forum:sidebar` slot and
// read shared page data synchronously from window.Convoro.data. We push that
// data + the admin's order/visibility layout into the runtime here so the
// framework-light widget bundles can render without their own fetches.
const sidebarLayout = computed(() => {
  const arr = Array.isArray(props.widgets) ? props.widgets : [];
  return {
    order: arr.map((w) => w.key).filter(Boolean) as string[],
    disabled: arr.filter((w) => w && w.enabled === false).map((w) => w.key),
  };
});

// Keep the host translator current so vanilla widgets stay i18n-aware.
convoro.setTranslator((k, p) => tr(k, (p ?? {}) as Record<string, string | number>));

watchEffect(() => {
  convoro.setData({
    stats: props.stats ?? {},
    online: props.widgetData?.onlineNow ?? 0,
    onlineGuests: props.widgetData?.onlineGuests ?? 0,
    onlineUsers: props.widgetData?.onlineUsers ?? [],
    newestMembers: props.widgetData?.newestMembers ?? [],
    topPosters: props.widgetData?.topPosters ?? [],
    trending: props.widgetData?.trending ?? [],
    categories: props.categories ?? [],
    aboutHtml: props.aboutHtml ?? '',
    aboutTitle: props.aboutTitle ?? '',
  });
  convoro.emit('convoro:data');
});

watch(sidebarLayout, (l) => convoro.setLayout('forum:sidebar', l), { immediate: true, deep: true });

function go(params: Record<string, string | null>) {
  // Remember the view choice so it sticks on the next visit (server reads this cookie).
  if (params.view === 'feed' || params.view === 'grid' || params.view === 'category') {
    document.cookie = `convoro_view=${params.view};path=/;max-age=31536000;samesite=lax`;
  }
  router.get('/', { view: props.view, sort: props.sort, category: props.activeCategory, tag: props.activeTag, ...params }, { preserveScroll: true, preserveState: true });
}

// A top-level tag pill stays lit when it — or one of its sub-tags — is active.
function isActiveTop(t: any) {
  return props.activeTag === t.slug || (t.children || []).some((c: any) => c.slug === props.activeTag);
}
</script>

<template>
  <Head title="Community" />
  <AppLayout>
    <PrismHero v-if="hero" :config="hero" class="mb-5" />

    <!-- Prism tag rail — primary navigation; tags (with sub-tags) replace categories -->
    <div v-if="tags && tags.length" class="mb-5">
      <div class="flex items-center gap-2 overflow-x-auto pb-1">
        <button type="button" @click="go({ tag: null, category: null })"
          class="inline-flex shrink-0 items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold transition"
          :class="!activeTag ? 'border-transparent bg-primary text-white shadow-lg shadow-primary/30' : 'border-line bg-surface text-ink-2 hover:text-ink'">
          <i class="fa-solid fa-layer-group text-[13px]" aria-hidden="true"></i> {{ tr('All') }}
        </button>
        <button v-for="t in tags" :key="t.slug" type="button" @click="go({ tag: t.slug, category: null })"
          class="inline-flex shrink-0 items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold transition"
          :class="isActiveTop(t) ? 'border-transparent text-white shadow-lg' : 'border-line bg-surface text-ink-2 hover:text-ink'"
          :style="isActiveTop(t) ? { background: t.color, boxShadow: '0 8px 24px -8px ' + t.color + 'aa' } : undefined">
          <i v-if="t.icon && t.icon.startsWith('fa-')" :class="t.icon" class="text-[13px]" aria-hidden="true"></i>
          <span v-else class="h-2 w-2 rounded-full" :style="{ background: isActiveTop(t) ? '#fff' : t.color }"></span>
          {{ t.name }}
          <span class="rounded-full px-1.5 text-xs" :class="isActiveTop(t) ? 'bg-black/20' : 'bg-surface-2'">{{ t.count }}</span>
        </button>
      </div>
      <div v-if="subtags && subtags.items.length" class="mt-2.5 flex flex-wrap items-center gap-2">
        <button type="button" @click="go({ tag: subtags.parent.slug, category: null })"
          class="rounded-full px-3 py-1 text-xs font-semibold transition"
          :class="subtags.active === subtags.parent.slug ? 'bg-primary/20 text-primary' : 'bg-surface text-ink-2 hover:text-ink'">{{ tr('All') }}</button>
        <button v-for="s in subtags.items" :key="s.slug" type="button" @click="go({ tag: s.slug, category: null })"
          class="rounded-full px-3 py-1 text-xs font-semibold transition"
          :class="subtags.active === s.slug ? 'bg-primary/20 text-primary' : 'bg-surface text-ink-2 hover:text-ink'">{{ s.name }}</button>
      </div>
    </div>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-[224px_1fr_268px]">
      <!-- Left sidebar -->
      <aside class="hidden lg:block">
        <button type="button" @click="startTopic" class="mb-3.5 flex w-full items-center justify-center gap-2 rounded-lg q-grad px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-primary/30 transition hover:opacity-90">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 5v14M5 12h14" /></svg> {{ tr('Start a topic') }}
        </button>
        <div class="overflow-hidden rounded-c border border-line bg-surface shadow-sm">
          <div class="flex items-center gap-2 border-b border-line bg-primary/10 px-4 py-3">
            <i class="fa-solid fa-layer-group text-[13px] text-primary" aria-hidden="true"></i>
            <b class="text-[13px] font-bold uppercase tracking-wide text-ink-2">{{ tr('Spaces') }}</b>
          </div>
          <nav class="flex flex-col gap-0.5 p-2">
            <button type="button" @click="go({ tag: null, category: null })" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-semibold"
              :class="!activeTag ? 'bg-primary/15 text-primary' : 'text-ink-2 hover:bg-surface-2'">
              <i class="fa-solid fa-layer-group w-4 text-center text-[12px]" aria-hidden="true"></i> {{ tr('All topics') }}
            </button>
            <template v-for="t in (tags || [])" :key="t.slug">
              <button type="button" @click="go({ tag: t.slug, category: null })"
                class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-semibold transition hover:bg-surface-2"
                :style="isActiveTop(t) ? { background: t.color + '24' } : undefined">
                <i v-if="t.icon && t.icon.startsWith('fa-')" :class="t.icon" class="w-4 text-center text-[12px]" :style="{ color: t.color }" aria-hidden="true"></i>
                <span v-else class="ml-0.5 h-2.5 w-2.5 rounded-full" :style="{ background: t.color }"></span>
                <span :style="{ color: isActiveTop(t) ? t.color : undefined }">{{ t.name }}</span>
                <span class="ml-auto rounded-full bg-surface-2 px-2 py-0.5 text-xs text-ink-muted">{{ t.count }}</span>
              </button>
              <template v-if="isActiveTop(t) && t.children && t.children.length">
                <button v-for="c in t.children" :key="c.slug" type="button" @click="go({ tag: c.slug, category: null })"
                  class="flex items-center gap-2 rounded-lg py-1.5 pl-9 pr-3 text-[13px] font-semibold transition hover:bg-surface-2"
                  :class="activeTag === c.slug ? 'text-primary' : 'text-ink-2'">
                  <span class="h-1.5 w-1.5 rounded-full" :style="{ background: c.color }"></span>
                  <span class="truncate">{{ c.name }}</span>
                  <span class="ml-auto text-xs text-ink-muted">{{ c.count }}</span>
                </button>
              </template>
            </template>
          </nav>
        </div>
        <!-- Ask Convoro lives under the categories list, on the left. -->
        <AskBar v-if="askEnabled" :compact="true" class="mt-3.5" />
        <!-- Optional Stripe donate widget, directly under Ask. -->
        <DonateWidget v-if="donate.buyButtonId" :buy-button-id="donate.buyButtonId" :publishable-key="donate.publishableKey" :heading="donate.heading" :blurb="donate.blurb" class="mt-3.5" />
      </aside>

      <!-- Main -->
      <section>
        <form class="mb-4 flex items-center gap-2.5 rounded-c border border-line bg-surface px-4 py-2.5 shadow-sm focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20" @submit.prevent="goSearch">
          <svg class="text-ink-muted" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7" /><path d="m21 21-4.3-4.3" /></svg>
          <input v-model="search" type="search" class="w-full border-0 bg-transparent p-0 text-sm text-ink placeholder:text-ink-muted focus:ring-0" :placeholder="tr('Search discussions…')" />
          <button v-if="search" type="submit" class="shrink-0 rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-600">{{ tr('Search') }}</button>
        </form>
        <div class="mb-4 flex items-center gap-3">
          <h1 class="text-2xl font-extrabold tracking-tight">{{ tr('Community') }}</h1>
          <div class="ml-auto flex rounded-[10px] border border-line bg-surface p-0.5 shadow-sm">
            <button @click="go({ view: 'feed' })" class="rounded-[7px] px-3 py-1.5 text-[13px] font-semibold" :class="view === 'feed' ? 'bg-primary text-white' : 'text-ink-2'">{{ tr('Feed') }}</button>
            <button @click="go({ view: 'grid' })" class="rounded-[7px] px-3 py-1.5 text-[13px] font-semibold" :class="view === 'grid' ? 'bg-primary text-white' : 'text-ink-2'">{{ tr('Grid') }}</button>
            <button @click="go({ view: 'category' })" class="rounded-[7px] px-3 py-1.5 text-[13px] font-semibold" :class="view === 'category' ? 'bg-primary text-white' : 'text-ink-2'">{{ tr('Categories') }}</button>
          </div>
          <select :value="sort" @change="go({ sort: ($event.target as HTMLSelectElement).value })"
            class="rounded-lg border-line bg-surface py-1.5 text-[13px] font-semibold text-ink-2 shadow-sm focus:ring-primary">
            <option value="recent">{{ tr('Latest activity') }}</option>
            <option value="popular">{{ tr('Most viewed') }}</option>
            <option value="title">{{ tr('Title (A–Z)') }}</option>
          </select>
        </div>

        <!-- Categories — a directory of category cards (independent of topics) -->
        <div v-if="view === 'category'" class="grid grid-cols-1 gap-3.5 sm:grid-cols-2">
          <Link v-for="c in categories" :key="c.slug" :href="`/?category=${c.slug}&view=feed`"
            class="flex items-start gap-3 rounded-c border border-line bg-surface p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl text-lg" :style="{ color: c.color, background: c.color + '22' }">
              <CategoryIcon :icon="c.icon" />
            </span>
            <div class="min-w-0 flex-1">
              <h3 class="text-[15px] font-bold leading-snug">{{ c.name }}</h3>
              <p v-if="c.description" class="mt-1 line-clamp-2 text-[13px] leading-relaxed text-ink-2">{{ c.description }}</p>
              <p class="mt-1.5 text-xs text-ink-muted">{{ tr('{n} topics', { n: c.count }) }}</p>
            </div>
          </Link>
          <p v-if="!categories.length" class="col-span-full py-12 text-center text-sm text-ink-muted">{{ tr('No categories yet.') }}</p>
        </div>

        <!-- Empty state -->
        <EmptyState v-else-if="!items.length" icon="💬" :title="tr('No topics yet')"
          :description="tr('This is the start of something. Be the first to post and get the conversation going.')">
          <button type="button" @click="startTopic" class="rounded-c bg-primary px-5 py-2.5 text-sm font-bold text-white hover:bg-primary-600">{{ tr('Start the first topic') }}</button>
        </EmptyState>

        <!-- Feed -->
        <div v-else-if="view === 'feed'" class="flex flex-col gap-3">
          <TopicCard v-for="t in items" :key="t.id" :topic="withLive(t)" />
        </div>

        <!-- Grid -->
        <div v-else class="grid grid-cols-1 gap-3.5 sm:grid-cols-2">
          <Link v-for="t in items" :key="t.id" :href="`/t/${t.slug}`"
            class="relative flex flex-col overflow-hidden rounded-c border bg-surface shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
            :class="t.isNew ? 'border-primary/40' : 'border-line'">
            <span v-if="t.isNew" class="absolute right-2 top-2 z-10 inline-flex items-center rounded-full bg-primary px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white shadow-md shadow-primary/30">{{ tr('New') }}</span>
            <div class="aspect-[16/8] bg-surface-2">
              <img v-if="t.cover || defaultCover" :src="t.cover || defaultCover || ''" class="h-full w-full object-cover" loading="lazy" />
            </div>
            <div class="flex flex-1 flex-col p-4">
              <span v-if="t.category" class="mb-2 inline-flex w-fit items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold"
                :style="{ color: t.category.color, background: t.category.color + '22' }"><CategoryIcon :icon="t.category.icon" /> {{ t.category.name }}</span>
              <h3 class="text-[15px] font-bold leading-snug">{{ t.title }}</h3>
              <p v-if="t.excerpt" class="mt-1.5 line-clamp-3 text-[13px] leading-relaxed text-ink-2">{{ t.excerpt }}</p>
              <div class="mt-auto flex items-center gap-2 pt-3 text-xs text-ink-muted">
                <Avatar :avatar="t.author" :size="28" /><span>{{ t.author.name }}</span>
                <span class="ml-auto">💬 {{ t.replyCount }}</span>
              </div>
            </div>
          </Link>
        </div>

        <div v-if="topics.next" class="py-6 text-center">
          <Link :href="topics.next" class="rounded-c border border-line bg-surface px-5 py-2.5 text-sm font-semibold hover:bg-surface-2">{{ tr('Load more') }}</Link>
        </div>
      </section>

      <!-- Right rail: widget extensions, ordered/toggled in the live editor -->
      <aside class="hidden lg:block">
        <div class="flex flex-col gap-4"><Slot name="forum:sidebar" /></div>
      </aside>
    </div>

    <!--
      Mobile / tablet: the left sidebar (which holds "Start a topic") is hidden
      below lg, so surface the action as a floating button. Teleported to <body>
      so its position:fixed is viewport-relative (an ancestor transform would
      otherwise become the containing block and push it off-screen).
    -->
    <Teleport to="body">
      <button
        type="button"
        @click="startTopic"
        class="fixed bottom-5 right-5 z-40 flex items-center gap-2 rounded-full bg-primary px-5 py-3.5 text-sm font-semibold text-white shadow-xl shadow-primary/40 hover:bg-primary-600 lg:hidden"
        :class="barHasCompose ? 'max-md:hidden' : ''"
        :aria-label="tr('Start a topic')"
      >
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M12 5v14M5 12h14" /></svg>
        <span>{{ tr('Start a topic') }}</span>
      </button>
    </Teleport>
  </AppLayout>
</template>
