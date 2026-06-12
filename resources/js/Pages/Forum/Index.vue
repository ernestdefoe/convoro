<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, watch, watchEffect } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import TopicCard from '@/Components/forum/TopicCard.vue';
import Avatar from '@/Components/forum/Avatar.vue';
import CategoryIcon from '@/Components/forum/CategoryIcon.vue';
import Slot from '@/Components/ext/Slot.vue';
import EmptyState from '@/Components/EmptyState.vue';
import AskBar from '@/Components/AskBar.vue';
import { useAuthModal } from '@/lib/authModal';
import { t as tr } from '@/lib/i18n';
import { convoro } from '@/lib/convoro-ext';

const pg = usePage();
const auth = useAuthModal();
const loggedIn = computed(() => !!(pg.props as any).auth?.user);
const askEnabled = computed(() => !!(pg.props as any).ask?.enabled);
function startTopic() {
  loggedIn.value ? router.visit('/new') : auth.open('register');
}

const props = defineProps<{
  view: 'feed' | 'grid';
  sort: string;
  activeCategory: string | null;
  categories: any[];
  topics: { data: any[]; next: string | null };
  stats: Record<string, number>;
  widgets?: { key: string; enabled?: boolean }[];
  widgetData?: any;
  aboutHtml?: string;
  aboutTitle?: string;
}>();

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
  if (params.view === 'feed' || params.view === 'grid') {
    document.cookie = `convoro_view=${params.view};path=/;max-age=31536000;samesite=lax`;
  }
  router.get('/', { view: props.view, sort: props.sort, category: props.activeCategory, ...params }, { preserveScroll: true, preserveState: true });
}
</script>

<template>
  <Head title="Community" />
  <AppLayout>
    <div class="grid grid-cols-1 gap-5 lg:grid-cols-[224px_1fr_268px]">
      <!-- Left sidebar -->
      <aside class="hidden lg:block">
        <button type="button" @click="startTopic" class="mb-3.5 flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-primary/30 hover:bg-primary-600">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 5v14M5 12h14" /></svg> {{ tr('Start a topic') }}
        </button>
        <div class="overflow-hidden rounded-c border border-line bg-surface shadow-sm">
          <h4 class="border-b border-line px-4 py-3 text-xs font-semibold uppercase tracking-wide text-ink-muted">{{ tr('Categories') }}</h4>
          <nav class="flex flex-col gap-0.5 p-2">
            <button @click="go({ category: null })" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-semibold"
              :class="!activeCategory ? 'bg-primary/15 text-primary' : 'text-ink-2 hover:bg-surface-2'">{{ tr('All topics') }}</button>
            <button v-for="c in categories" :key="c.slug" @click="go({ category: c.slug })"
              class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-semibold transition hover:bg-surface-2"
              :style="activeCategory === c.slug ? { background: (c.color || '') + '24' } : undefined">
              <CategoryIcon :icon="c.icon" :color="c.color" />
              <span :style="{ color: c.color }">{{ c.name }}</span>
              <span class="ml-auto rounded-full bg-surface-2 px-2 py-0.5 text-xs text-ink-muted">{{ c.count }}</span>
            </button>
          </nav>
        </div>
      </aside>

      <!-- Main -->
      <section>
        <AskBar v-if="askEnabled" class="mb-5" />
        <div class="mb-4 flex items-center gap-3">
          <h1 class="text-2xl font-extrabold tracking-tight">{{ tr('Community') }}</h1>
          <div class="ml-auto flex rounded-[10px] border border-line bg-surface p-0.5 shadow-sm">
            <button @click="go({ view: 'feed' })" class="rounded-[7px] px-3 py-1.5 text-[13px] font-semibold" :class="view === 'feed' ? 'bg-primary text-white' : 'text-ink-2'">{{ tr('Feed') }}</button>
            <button @click="go({ view: 'grid' })" class="rounded-[7px] px-3 py-1.5 text-[13px] font-semibold" :class="view === 'grid' ? 'bg-primary text-white' : 'text-ink-2'">{{ tr('Grid') }}</button>
          </div>
          <select :value="sort" @change="go({ sort: ($event.target as HTMLSelectElement).value })"
            class="rounded-lg border-line bg-surface py-1.5 text-[13px] font-semibold text-ink-2 shadow-sm focus:ring-primary">
            <option value="recent">{{ tr('Latest activity') }}</option>
            <option value="popular">{{ tr('Most viewed') }}</option>
            <option value="title">{{ tr('Title (A–Z)') }}</option>
          </select>
        </div>

        <!-- Empty state -->
        <EmptyState v-if="!topics.data.length" icon="💬" :title="tr('No topics yet')"
          :description="tr('This is the start of something. Be the first to post and get the conversation going.')">
          <button type="button" @click="startTopic" class="rounded-c bg-primary px-5 py-2.5 text-sm font-bold text-white hover:bg-primary-600">{{ tr('Start the first topic') }}</button>
        </EmptyState>

        <!-- Feed -->
        <div v-else-if="view === 'feed'" class="flex flex-col gap-3">
          <TopicCard v-for="t in topics.data" :key="t.id" :topic="t" />
        </div>

        <!-- Grid -->
        <div v-else class="grid grid-cols-1 gap-3.5 sm:grid-cols-2">
          <Link v-for="t in topics.data" :key="t.id" :href="`/t/${t.slug}`"
            class="flex flex-col overflow-hidden rounded-c border border-line bg-surface shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
            <div class="aspect-[16/8] bg-surface-2">
              <img v-if="t.cover" :src="t.cover" class="h-full w-full object-cover" loading="lazy" />
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
        :aria-label="tr('Start a topic')"
      >
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M12 5v14M5 12h14" /></svg>
        <span>{{ tr('Start a topic') }}</span>
      </button>
    </Teleport>
  </AppLayout>
</template>
