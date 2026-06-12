<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { reactive, ref, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import TopicCard from '@/Components/forum/TopicCard.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { t as tr } from '@/lib/i18n';

const props = defineProps<{
  q: string;
  results: any[];
  mode: 'semantic' | 'text';
  filters: { category: string; author: string; when: string; sort: string };
  categories: { name: string; slug: string }[];
}>();

const term = ref(props.q);
const f = reactive({ ...props.filters });

function run() {
  const params: Record<string, string> = { q: term.value.trim() };
  if (f.category) params.category = f.category;
  if (f.author.trim()) params.author = f.author.trim();
  if (f.when && f.when !== 'all') params.when = f.when;
  if (f.sort && f.sort !== 'relevance') params.sort = f.sort;
  router.get('/search', params, { preserveState: true, preserveScroll: true });
}

function reset() {
  f.category = ''; f.author = ''; f.when = 'all'; f.sort = 'relevance';
  run();
}

const activeFilters = computed(() => (f.category ? 1 : 0) + (f.author.trim() ? 1 : 0) + (f.when !== 'all' ? 1 : 0));
const sel = 'rounded-lg border-line bg-surface-2 text-sm text-ink focus:border-primary focus:ring-primary';
</script>

<template>
  <Head :title="q ? tr('Search: {q}', { q }) : tr('Search')" />
  <AppLayout>
    <div class="mx-auto max-w-3xl">
      <h1 class="mb-4 text-2xl font-extrabold tracking-tight">{{ tr('Search') }}</h1>

      <form class="flex gap-2" @submit.prevent="run">
        <input
          v-model="term"
          type="search"
          autofocus
          :placeholder="tr('Search the community…')"
          class="flex-1 rounded-c border-line bg-surface-2 text-ink placeholder:text-ink-muted focus:border-primary focus:ring-primary"
        />
        <button type="submit" class="rounded-c bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-600">{{ tr('Search') }}</button>
      </form>

      <!-- Filters -->
      <div class="mt-3 flex flex-wrap items-center gap-2">
        <select v-model="f.category" :class="sel" @change="run">
          <option value="">{{ tr('All categories') }}</option>
          <option v-for="c in categories" :key="c.slug" :value="c.slug">{{ c.name }}</option>
        </select>
        <input v-model="f.author" type="text" :placeholder="tr('Author')" :class="sel" class="w-36" @keyup.enter="run" />
        <select v-model="f.when" :class="sel" @change="run">
          <option value="all">{{ tr('Any time') }}</option>
          <option value="week">{{ tr('Past week') }}</option>
          <option value="month">{{ tr('Past month') }}</option>
          <option value="year">{{ tr('Past year') }}</option>
        </select>
        <select v-model="f.sort" :class="sel" @change="run">
          <option value="relevance">{{ tr('Relevance') }}</option>
          <option value="recent">{{ tr('Newest') }}</option>
          <option value="replies">{{ tr('Most replies') }}</option>
        </select>
        <button v-if="activeFilters || f.sort !== 'relevance'" type="button" class="text-sm font-semibold text-ink-2 hover:text-ink" @click="reset">{{ tr('Clear') }}</button>
      </div>

      <p v-if="q || activeFilters" class="mt-3 text-sm text-ink-muted">
        {{ results.length === 1 ? tr('{n} result', { n: results.length }) : tr('{n} results', { n: results.length }) }}
        <span v-if="q"> {{ tr('for “{q}”', { q }) }}</span>
        <span v-if="q && mode === 'semantic'" class="ml-1 rounded-full bg-primary/10 px-2 py-0.5 text-xs font-bold text-primary">{{ tr('✦ semantic') }}</span>
      </p>

      <div v-if="results.length" class="mt-5 space-y-3">
        <TopicCard v-for="t in results" :key="t.id" :topic="t" />
      </div>

      <EmptyState
        v-else-if="q || activeFilters"
        class="mt-10"
        :title="tr('No matching discussions')"
        :body="tr('Try different words or relax your filters — or start a new topic and let the community (and the assistant) help.')"
      />
      <p v-else class="mt-10 text-center text-ink-muted">{{ tr('Type a question or a few keywords — or filter by category, author and date — to search every discussion.') }}</p>
    </div>
  </AppLayout>
</template>
