<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Avatar from '@/Components/forum/Avatar.vue';
import { t as tr } from '@/lib/i18n';

const props = defineProps<{
  q: string;
  sort: string;
  members: { data: any[]; next: string | null };
  total: number;
}>();

const query = ref(props.q);
let t: any = null;
function search() {
  clearTimeout(t);
  t = setTimeout(() => router.get('/members', { q: query.value, sort: props.sort }, { preserveState: true, replace: true }), 300);
}
function setSort(e: Event) {
  router.get('/members', { q: query.value, sort: (e.target as HTMLSelectElement).value }, { preserveState: true });
}
</script>

<template>
  <Head :title="tr('Members')" />
  <AppLayout>
    <div class="mx-auto max-w-[var(--c-container)]">
      <div class="mb-5 flex flex-wrap items-center gap-3">
        <div>
          <h1 class="text-2xl font-extrabold tracking-tight">{{ tr('Members') }}</h1>
          <p class="text-sm text-ink-muted">{{ total }} {{ total === 1 ? tr('member') : tr('members') }}</p>
        </div>
        <div class="ml-auto flex items-center gap-2 rounded-full border border-line bg-surface px-4 py-2">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-ink-muted"><circle cx="11" cy="11" r="7" /><path d="m21 21-4.3-4.3" /></svg>
          <input v-model="query" @input="search" type="search" :placeholder="tr('Find a member…')" class="w-44 border-0 bg-transparent p-0 text-sm text-ink placeholder:text-ink-muted focus:ring-0" />
        </div>
        <select :value="sort" @change="setSort" class="rounded-lg border-line bg-surface py-1.5 text-[13px] font-semibold text-ink-2 shadow-sm focus:ring-primary">
          <option value="newest">{{ tr('Newest') }}</option>
          <option value="name">{{ tr('Name (A–Z)') }}</option>
          <option value="posts">{{ tr('Most posts') }}</option>
        </select>
      </div>

      <div v-if="!members.data.length" class="rounded-c border border-dashed border-line bg-surface p-12 text-center text-ink-muted">
        {{ tr('No members found.') }}
      </div>

      <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
        <Link v-for="m in members.data" :key="m.id" :href="m.url"
          class="flex flex-col items-center rounded-c border border-line bg-surface p-6 text-center shadow-sm transition hover:-translate-y-0.5 hover:shadow-md">
          <Avatar :avatar="m" :size="88" />
          <div class="mt-3 flex items-center gap-1.5">
            <span class="font-bold">{{ m.name }}</span>
            <span v-if="m.isAdmin" class="rounded bg-primary/15 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-primary">{{ tr('Admin') }}</span>
          </div>
          <p v-if="m.bio" class="mt-1 line-clamp-2 text-xs text-ink-2">{{ m.bio }}</p>
          <div class="mt-3 flex items-center gap-3 text-xs text-ink-muted">
            <span>{{ tr('{n} posts', { n: m.posts }) }}</span>
            <span>·</span>
            <span>{{ tr('Joined {date}', { date: m.joined }) }}</span>
          </div>
        </Link>
      </div>

      <div v-if="members.next" class="py-6 text-center">
        <Link :href="members.next" class="rounded-c border border-line bg-surface px-5 py-2.5 text-sm font-semibold hover:bg-surface-2">{{ tr('Load more') }}</Link>
      </div>
    </div>
  </AppLayout>
</template>
