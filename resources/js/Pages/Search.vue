<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import TopicCard from '@/Components/forum/TopicCard.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { t as tr } from '@/lib/i18n';

const props = defineProps<{
  q: string;
  results: any[];
  mode: 'semantic' | 'text';
}>();

const term = ref(props.q);
function search() {
  router.get('/search', { q: term.value.trim() }, { preserveState: true });
}
</script>

<template>
  <Head :title="q ? tr('Search: {q}', { q }) : tr('Search')" />
  <AppLayout>
    <div class="mx-auto max-w-3xl">
      <h1 class="mb-4 text-2xl font-extrabold tracking-tight">{{ tr('Search') }}</h1>

      <form class="flex gap-2" @submit.prevent="search">
        <input
          v-model="term"
          type="search"
          autofocus
          :placeholder="tr('Search the community…')"
          class="flex-1 rounded-c border-line bg-surface-2 text-ink placeholder:text-ink-muted focus:border-primary focus:ring-primary"
        />
        <button type="submit" class="rounded-c bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-600">{{ tr('Search') }}</button>
      </form>

      <p v-if="q" class="mt-3 text-sm text-ink-muted">
        {{ results.length === 1 ? tr('{n} result for “{q}”', { n: results.length, q }) : tr('{n} results for “{q}”', { n: results.length, q }) }}
        <span v-if="mode === 'semantic'" class="ml-1 rounded-full bg-primary/10 px-2 py-0.5 text-xs font-bold text-primary">{{ tr('✦ semantic') }}</span>
      </p>

      <div v-if="results.length" class="mt-5 space-y-3">
        <TopicCard v-for="t in results" :key="t.id" :topic="t" />
      </div>

      <EmptyState
        v-else-if="q"
        class="mt-10"
        :title="tr('No matching discussions')"
        :body="tr('Nothing came up for “{q}”. Try different words — or start a new topic and let the community (and the assistant) help.', { q })"
      />
      <p v-else class="mt-10 text-center text-ink-muted">{{ tr('Type a question or a few keywords to search every discussion.') }}</p>
    </div>
  </AppLayout>
</template>
