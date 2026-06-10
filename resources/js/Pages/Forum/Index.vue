<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import TopicCard from '@/Components/forum/TopicCard.vue';
import Avatar from '@/Components/forum/Avatar.vue';
import CategoryIcon from '@/Components/forum/CategoryIcon.vue';
import { useAuthModal } from '@/lib/authModal';

const pg = usePage();
const auth = useAuthModal();
const loggedIn = computed(() => !!(pg.props as any).auth?.user);
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
}>();

function go(params: Record<string, string | null>) {
  router.get('/', { view: props.view, sort: props.sort, category: props.activeCategory, ...params }, { preserveScroll: true, preserveState: true });
}
const fmt = (n: number) => (n >= 1000 ? (n / 1000).toFixed(1).replace(/\.0$/, '') + 'k' : String(n));
</script>

<template>
  <Head title="Community" />
  <AppLayout>
    <div class="grid grid-cols-1 gap-5 lg:grid-cols-[224px_1fr_268px]">
      <!-- Left sidebar -->
      <aside class="hidden lg:block">
        <button type="button" @click="startTopic" class="mb-3.5 flex w-full items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-primary/30 hover:bg-primary-600">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 5v14M5 12h14" /></svg> Start a topic
        </button>
        <div class="overflow-hidden rounded-c border border-line bg-surface shadow-sm">
          <h4 class="border-b border-line px-4 py-3 text-xs font-semibold uppercase tracking-wide text-ink-muted">Categories</h4>
          <nav class="flex flex-col gap-0.5 p-2">
            <button @click="go({ category: null })" class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-semibold"
              :class="!activeCategory ? 'bg-primary/15 text-primary' : 'text-ink-2 hover:bg-surface-2'">All topics</button>
            <button v-for="c in categories" :key="c.slug" @click="go({ category: c.slug })"
              class="flex items-center gap-2.5 rounded-lg px-3 py-2 text-sm font-semibold"
              :class="activeCategory === c.slug ? 'bg-primary/15 text-primary' : 'text-ink-2 hover:bg-surface-2'">
              <CategoryIcon :icon="c.icon" /> {{ c.name }}
              <span class="ml-auto rounded-full bg-surface-2 px-2 py-0.5 text-xs text-ink-muted">{{ c.count }}</span>
            </button>
          </nav>
        </div>
      </aside>

      <!-- Main -->
      <section>
        <div class="mb-4 flex items-center gap-3">
          <h1 class="text-2xl font-extrabold tracking-tight">Community</h1>
          <div class="ml-auto flex rounded-[10px] border border-line bg-surface p-0.5 shadow-sm">
            <button @click="go({ view: 'feed' })" class="rounded-[7px] px-3 py-1.5 text-[13px] font-semibold" :class="view === 'feed' ? 'bg-primary text-white' : 'text-ink-2'">Feed</button>
            <button @click="go({ view: 'grid' })" class="rounded-[7px] px-3 py-1.5 text-[13px] font-semibold" :class="view === 'grid' ? 'bg-primary text-white' : 'text-ink-2'">Grid</button>
          </div>
          <select :value="sort" @change="go({ sort: ($event.target as HTMLSelectElement).value })"
            class="rounded-lg border-line bg-surface py-1.5 text-[13px] font-semibold text-ink-2 shadow-sm focus:ring-primary">
            <option value="recent">Latest activity</option>
            <option value="popular">Most viewed</option>
            <option value="title">Title (A–Z)</option>
          </select>
        </div>

        <!-- Feed -->
        <div v-if="view === 'feed'" class="flex flex-col gap-3">
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
                <Avatar :avatar="t.author" :size="24" /><span>{{ t.author.name }}</span>
                <span class="ml-auto">💬 {{ t.replyCount }}</span>
              </div>
            </div>
          </Link>
        </div>

        <div v-if="topics.next" class="py-6 text-center">
          <Link :href="topics.next" class="rounded-c border border-line bg-surface px-5 py-2.5 text-sm font-semibold hover:bg-surface-2">Load more</Link>
        </div>
      </section>

      <!-- Right rail -->
      <aside class="hidden lg:block">
        <div class="overflow-hidden rounded-c border border-line bg-surface shadow-sm">
          <h4 class="border-b border-line px-4 py-3 text-xs font-semibold uppercase tracking-wide text-ink-muted">Community stats</h4>
          <div class="grid grid-cols-2 gap-2.5 p-4">
            <div class="rounded-[10px] border border-line bg-surface-2 p-3"><b class="block text-lg tracking-tight">{{ fmt(stats.members) }}</b><span class="text-[11px] text-ink-muted">Members</span></div>
            <div class="rounded-[10px] border border-line bg-surface-2 p-3"><b class="block text-lg tracking-tight">{{ fmt(stats.topics) }}</b><span class="text-[11px] text-ink-muted">Topics</span></div>
            <div class="rounded-[10px] border border-line bg-surface-2 p-3"><b class="block text-lg tracking-tight">{{ fmt(stats.posts) }}</b><span class="text-[11px] text-ink-muted">Posts</span></div>
            <div class="rounded-[10px] border border-line bg-surface-2 p-3"><b class="block text-lg tracking-tight">{{ fmt(stats.reactions) }}</b><span class="text-[11px] text-ink-muted">Reactions</span></div>
          </div>
        </div>
      </aside>
    </div>
  </AppLayout>
</template>
