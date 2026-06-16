<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { t } from '@/lib/i18n';

const props = defineProps<{
  groups: { data: any[]; next: string | null };
  q: string;
  canCreate: boolean;
}>();

const page = usePage();
const loggedIn = computed(() => !!(page.props as any).auth?.user);

const items = ref<any[]>([...props.groups.data]);
const next = ref<string | null>(props.groups.next);
watch(() => props.groups, (g) => { items.value = [...g.data]; next.value = g.next; });

const search = ref(props.q ?? '');
let timer: any = null;
watch(search, (v) => {
  clearTimeout(timer);
  timer = setTimeout(() => router.get('/groups', { q: v }, { preserveState: true, preserveScroll: true, replace: true, only: ['groups', 'q'] }), 300);
});

function loadMore() {
  if (!next.value) return;
  router.get(next.value, {}, {
    preserveState: true, preserveScroll: true, only: ['groups'],
    onSuccess: () => { items.value.push(...props.groups.data); },
  });
}

// -- create modal --
const showCreate = ref(false);
const posting = ref(false);
const form = ref({ name: '', description: '', color: '#5B5BD6', is_private: false, membership_type: 'open' });
function create() {
  if (posting.value || !form.value.name.trim()) return;
  posting.value = true;
  router.post('/groups', form.value, {
    onSuccess: () => { showCreate.value = false; form.value = { name: '', description: '', color: '#5B5BD6', is_private: false, membership_type: 'open' }; },
    onFinish: () => (posting.value = false),
  });
}

function join(g: any) {
  if (!loggedIn.value) { router.visit('/login'); return; }
  router.post(`/groups/${g.slug}/join`, {}, { preserveScroll: true });
}

function tile(g: any) {
  return { background: g.color || 'rgb(var(--c-primary))' };
}
function initials(name: string) {
  return (name || '?').trim().slice(0, 2).toUpperCase();
}
</script>

<template>
  <Head :title="t('Groups')" />
  <AppLayout>
    <div class="mx-auto max-w-[var(--c-container)] px-4 py-6">
      <div class="mb-5 flex flex-wrap items-center gap-3">
        <div>
          <h1 class="text-2xl font-extrabold text-ink">{{ t('Groups') }}</h1>
          <p class="text-sm text-ink-2">{{ t('Find a community within the community.') }}</p>
        </div>
        <button v-if="canCreate" type="button" @click="showCreate = true"
          class="ml-auto inline-flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-bold text-white shadow-lg shadow-primary/30 hover:bg-primary-600">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 5v14M5 12h14" /></svg>
          {{ t('New group') }}
        </button>
      </div>

      <div class="mb-5">
        <input v-model="search" type="search" :placeholder="t('Search groups…')"
          class="w-full max-w-md rounded-lg border-line bg-surface px-4 py-2.5 text-sm text-ink focus:border-primary focus:ring-primary" />
      </div>

      <div v-if="items.length" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <div v-for="g in items" :key="g.id" class="overflow-hidden rounded-[var(--c-radius)] border border-line bg-surface">
          <Link :href="`/groups/${g.slug}`" class="block">
            <div class="h-20 w-full" :style="g.cover ? { backgroundImage: `url(${g.cover})`, backgroundSize: 'cover', backgroundPosition: 'center' } : tile(g)"></div>
          </Link>
          <div class="p-4">
            <div class="-mt-9 mb-2 flex items-end gap-3">
              <div class="grid h-12 w-12 shrink-0 place-items-center rounded-xl border-2 border-surface text-base font-extrabold text-white shadow" :style="tile(g)">
                <img v-if="g.avatar" :src="g.avatar" alt="" class="h-full w-full rounded-[10px] object-cover" />
                <span v-else>{{ initials(g.name) }}</span>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <Link :href="`/groups/${g.slug}`" class="truncate text-base font-bold text-ink hover:text-primary">{{ g.name }}</Link>
              <span v-if="g.isPrivate" :title="t('Private')" class="text-ink-muted">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" /></svg>
              </span>
            </div>
            <p v-if="g.description" class="mt-1 line-clamp-2 text-sm text-ink-2">{{ g.description }}</p>
            <div class="mt-3 flex items-center justify-between">
              <span class="text-xs font-semibold text-ink-muted">{{ g.memberCount }} {{ g.memberCount === 1 ? t('member') : t('members') }}</span>
              <Link v-if="g.isMember" :href="`/groups/${g.slug}`" class="rounded-lg bg-primary/15 px-3 py-1.5 text-xs font-bold text-primary">{{ t('Open') }}</Link>
              <span v-else-if="g.isPending" class="rounded-lg bg-surface-2 px-3 py-1.5 text-xs font-bold text-ink-muted">{{ t('Requested') }}</span>
              <button v-else-if="g.membershipType !== 'invite'" type="button" @click="join(g)" class="rounded-lg bg-surface-2 px-3 py-1.5 text-xs font-bold text-ink-2 hover:bg-primary/15 hover:text-primary">
                {{ g.membershipType === 'approval' ? t('Request') : t('Join') }}
              </button>
              <Link v-else :href="`/groups/${g.slug}`" class="rounded-lg bg-surface-2 px-3 py-1.5 text-xs font-bold text-ink-2">{{ t('View') }}</Link>
            </div>
          </div>
        </div>
      </div>

      <div v-else class="rounded-[var(--c-radius)] border border-dashed border-line bg-surface px-6 py-16 text-center">
        <p class="text-sm font-semibold text-ink-2">{{ t('No groups yet.') }}</p>
        <button v-if="canCreate" type="button" @click="showCreate = true" class="mt-3 rounded-lg bg-primary px-4 py-2 text-sm font-bold text-white hover:bg-primary-600">{{ t('Create the first one') }}</button>
      </div>

      <div v-if="next" class="mt-6 text-center">
        <button type="button" @click="loadMore" class="rounded-lg border border-line bg-surface px-5 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2">{{ t('Load more') }}</button>
      </div>
    </div>

    <!-- Create modal -->
    <Teleport to="body">
      <div v-if="showCreate" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showCreate = false">
        <div class="w-full max-w-lg rounded-[var(--c-radius)] bg-surface p-6 shadow-xl">
          <h2 class="mb-4 text-lg font-extrabold text-ink">{{ t('Create a group') }}</h2>
          <div class="space-y-4">
            <label class="block">
              <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-ink-muted">{{ t('Name') }}</span>
              <input v-model="form.name" type="text" maxlength="120" class="w-full rounded-lg border-line bg-surface-2 px-3 py-2 text-sm text-ink focus:border-primary focus:ring-primary" />
            </label>
            <label class="block">
              <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-ink-muted">{{ t('Description') }}</span>
              <textarea v-model="form.description" rows="3" maxlength="2000" class="w-full rounded-lg border-line bg-surface-2 px-3 py-2 text-sm text-ink focus:border-primary focus:ring-primary"></textarea>
            </label>
            <div class="flex flex-wrap items-center gap-4">
              <label class="flex items-center gap-2">
                <span class="text-xs font-semibold uppercase tracking-wide text-ink-muted">{{ t('Color') }}</span>
                <input v-model="form.color" type="color" class="h-8 w-10 cursor-pointer rounded border-line bg-surface-2" />
              </label>
              <label class="block flex-1">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-ink-muted">{{ t('Who can join') }}</span>
                <select v-model="form.membership_type" class="w-full rounded-lg border-line bg-surface-2 px-3 py-2 text-sm text-ink focus:border-primary focus:ring-primary">
                  <option value="open">{{ t('Anyone (open)') }}</option>
                  <option value="approval">{{ t('Request to join (approval)') }}</option>
                  <option value="invite">{{ t('Invite only') }}</option>
                </select>
              </label>
            </div>
            <label class="flex items-center gap-2">
              <input v-model="form.is_private" type="checkbox" class="h-4 w-4 rounded border-line text-primary focus:ring-primary" />
              <span class="text-sm font-semibold text-ink-2">{{ t('Private — only members can see discussions') }}</span>
            </label>
          </div>
          <div class="mt-6 flex justify-end gap-2">
            <button type="button" @click="showCreate = false" class="rounded-lg px-4 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2">{{ t('Cancel') }}</button>
            <button type="button" :disabled="posting || !form.name.trim()" @click="create" class="rounded-lg bg-primary px-4 py-2 text-sm font-bold text-white hover:bg-primary-600 disabled:opacity-50">{{ t('Create group') }}</button>
          </div>
        </div>
      </div>
    </Teleport>
  </AppLayout>
</template>
