<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Editor from '@/Components/Editor.vue';
import { uploadImage } from '@/lib/upload';
import { t } from '@/lib/i18n';

const props = defineProps<{
  group: any;
  members: any[];
  discussions: { data: any[]; next: string | null };
  pendingRequests: any[];
}>();

const page = usePage();
const loggedIn = computed(() => !!(page.props as any).auth?.user);

const tab = ref<'discussions' | 'members' | 'about'>('discussions');
const discussions = ref<any[]>([...props.discussions.data]);
const next = ref<string | null>(props.discussions.next);
watch(() => props.discussions, (d) => { discussions.value = [...d.data]; next.value = d.next; });

function loadMore() {
  if (!next.value) return;
  router.get(next.value, {}, { preserveState: true, preserveScroll: true, only: ['discussions'], onSuccess: () => discussions.value.push(...props.discussions.data) });
}

const g = computed(() => props.group);
function tile() { return { background: g.value.color || 'rgb(var(--c-primary))' }; }
function initials(n: string) { return (n || '?').trim().slice(0, 2).toUpperCase(); }

// -- membership actions --
function act(path: string, data: any = {}) {
  if (!loggedIn.value) { router.visit('/login'); return; }
  router.post(`/groups/${g.value.slug}/${path}`, data, { preserveScroll: true });
}
function join() { act('join'); }
function leave() { if (confirm(t('Leave this group?'))) act('leave'); }

// -- new discussion --
const composer = ref<any>(null);
const showComposer = ref(false);
const dTitle = ref('');
const posting = ref(false);
const withPoll = ref(false);
const poll = ref<{ question: string; options: string[]; multiple: boolean; closes_days: number | null }>({ question: '', options: ['', ''], multiple: false, closes_days: null });

function postDiscussion() {
  if (posting.value || !dTitle.value.trim() || !composer.value || composer.value.isEmpty()) return;
  posting.value = true;
  const payload: any = { title: dTitle.value, body_html: composer.value.getHTML(), body_json: composer.value.getJSON() };
  if (withPoll.value && poll.value.question.trim() && poll.value.options.filter((o) => o.trim()).length >= 2) {
    payload.poll = { question: poll.value.question, options: poll.value.options.filter((o) => o.trim()), multiple: poll.value.multiple, closes_days: poll.value.closes_days };
  }
  router.post(`/groups/${g.value.slug}/discussions`, payload, { onFinish: () => (posting.value = false) });
}

// -- moderation --
function kick(id: number) { if (confirm(t('Remove this member?'))) router.delete(`/groups/${g.value.slug}/members/${id}`, { preserveScroll: true }); }
function ban(id: number) { if (confirm(t('Ban this member?'))) router.post(`/groups/${g.value.slug}/members/${id}/ban`, {}, { preserveScroll: true }); }
function setRole(id: number, role: string) { router.put(`/groups/${g.value.slug}/members/${id}/role`, { role }, { preserveScroll: true }); }
function approve(id: number) { router.post(`/groups/${g.value.slug}/requests/${id}/approve`, {}, { preserveScroll: true }); }
function reject(id: number) { router.delete(`/groups/${g.value.slug}/requests/${id}`, { preserveScroll: true }); }

const inviteName = ref('');
function invite() { if (!inviteName.value.trim()) return; act('invite', { user: inviteName.value }); inviteName.value = ''; }

function setPrimaryBadge() { router.post('/groups/primary', { group_id: g.value.id }, { preserveScroll: true }); }

// -- analytics (lazy modal) --
const showStats = ref(false);
const stats = ref<any>(null);
async function openStats() {
  showStats.value = true;
  if (stats.value) return;
  try {
    const r = await fetch(`/groups/${g.value.slug}/analytics`, { headers: { Accept: 'application/json' } });
    if (r.ok) stats.value = await r.json();
  } catch { /* ignore */ }
}
const maxSeries = computed(() => Math.max(1, ...((stats.value?.series ?? []).map((d: any) => Math.max(d.joins, d.posts)))));

// -- edit modal --
const showEdit = ref(false);
const edit = ref<any>({});
const uploadingAvatar = ref(false);
const uploadingCover = ref(false);
async function pickImage(e: Event, field: 'avatar' | 'cover') {
  const file = (e.target as HTMLInputElement).files?.[0];
  (e.target as HTMLInputElement).value = '';
  if (!file) return;
  const flag = field === 'avatar' ? uploadingAvatar : uploadingCover;
  flag.value = true;
  try {
    const { url } = await uploadImage(file);
    edit.value[field] = url;
  } catch {
    alert(t('Image upload failed.'));
  } finally {
    flag.value = false;
  }
}
function openEdit() { edit.value = { name: g.value.name, description: g.value.description, color: g.value.color, is_private: g.value.isPrivate, membership_type: g.value.membershipType, avatar: g.value.avatar, cover: g.value.cover }; showEdit.value = true; }
function saveEdit() { router.put(`/groups/${g.value.slug}`, edit.value, { onSuccess: () => (showEdit.value = false), preserveScroll: true }); }
function destroyGroup() { if (confirm(t('Delete this group and all its discussions? This cannot be undone.'))) router.delete(`/groups/${g.value.slug}`); }

function timeAgo(iso: string | null) {
  if (!iso) return '';
  const s = Math.floor((Date.now() - new Date(iso).getTime()) / 1000);
  if (s < 60) return t('just now');
  if (s < 3600) return Math.floor(s / 60) + 'm';
  if (s < 86400) return Math.floor(s / 3600) + 'h';
  return Math.floor(s / 86400) + 'd';
}
</script>

<template>
  <Head :title="g.name" />
  <AppLayout>
    <div class="mx-auto max-w-[var(--c-container)] px-4 py-6">
      <Link href="/groups" class="mb-3 inline-flex items-center gap-1.5 text-sm font-semibold text-ink-2 hover:text-primary">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6" /></svg>
        {{ t('All groups') }}
      </Link>
      <!-- Header -->
      <div class="overflow-hidden rounded-[var(--c-radius)] border border-line bg-surface">
        <div class="h-32 w-full sm:h-40" :style="g.cover ? { backgroundImage: `url(${g.cover})`, backgroundSize: 'cover', backgroundPosition: 'center' } : tile()"></div>
        <div class="p-5">
          <!-- Only the avatar overlaps the banner; the name row sits clear below it. -->
          <div class="-mt-14 flex">
            <div class="grid h-20 w-20 shrink-0 place-items-center rounded-2xl border-4 border-surface text-2xl font-extrabold text-white shadow" :style="tile()">
              <img v-if="g.avatar" :src="g.avatar" alt="" class="h-full w-full rounded-xl object-cover" />
              <span v-else>{{ initials(g.name) }}</span>
            </div>
          </div>
          <div class="mt-3 flex flex-wrap items-start gap-4">
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-2xl font-extrabold text-ink">{{ g.name }}</h1>
                <span v-if="g.isPrivate" class="inline-flex items-center gap-1 rounded-full bg-surface-2 px-2 py-0.5 text-xs font-bold text-ink-muted">
                  <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" /></svg>
                  {{ t('Private') }}
                </span>
              </div>
              <p class="text-sm text-ink-2">{{ g.memberCount }} {{ g.memberCount === 1 ? t('member') : t('members') }} · {{ g.topicCount }} {{ t('discussions') }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
              <button v-if="!g.isMember && g.membershipType !== 'invite' && !g.isPending" type="button" @click="join" class="rounded-lg bg-primary px-4 py-2 text-sm font-bold text-white hover:bg-primary-600">
                {{ g.membershipType === 'approval' ? t('Request to join') : t('Join group') }}
              </button>
              <span v-else-if="g.isPending" class="rounded-lg bg-surface-2 px-4 py-2 text-sm font-bold text-ink-muted">{{ t('Request pending') }}</span>
              <template v-if="g.isMember">
                <button type="button" @click="setPrimaryBadge" :title="t('Show this group as your profile badge')" class="rounded-lg border border-line bg-surface px-3 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2">★</button>
                <button v-if="!g.isOwner" type="button" @click="leave" class="rounded-lg border border-line bg-surface px-4 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2">{{ t('Leave') }}</button>
              </template>
              <button v-if="g.canModerate" type="button" @click="openStats" class="rounded-lg border border-line bg-surface px-3 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2" :title="t('Analytics')">📊</button>
              <button v-if="g.canManage" type="button" @click="openEdit" class="rounded-lg border border-line bg-surface px-3 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2" :title="t('Edit group')">⚙</button>
            </div>
          </div>
          <p v-if="g.description" class="mt-4 whitespace-pre-line text-sm text-ink-2">{{ g.description }}</p>
        </div>
      </div>

      <!-- Pending requests (moderators) -->
      <div v-if="g.canModerate && pendingRequests.length" class="mt-4 rounded-[var(--c-radius)] border border-line bg-surface p-4">
        <h3 class="mb-2 text-sm font-bold text-ink">{{ t('Join requests') }} ({{ pendingRequests.length }})</h3>
        <div v-for="r in pendingRequests" :key="r.id" class="flex items-center gap-3 py-1.5">
          <span class="text-sm font-semibold text-ink">{{ r.user.name }}</span>
          <span v-if="r.message" class="truncate text-xs text-ink-muted">“{{ r.message }}”</span>
          <div class="ml-auto flex gap-2">
            <button type="button" @click="approve(r.id)" class="rounded-lg bg-primary/15 px-3 py-1 text-xs font-bold text-primary">{{ t('Approve') }}</button>
            <button type="button" @click="reject(r.id)" class="rounded-lg bg-surface-2 px-3 py-1 text-xs font-bold text-ink-2">{{ t('Decline') }}</button>
          </div>
        </div>
      </div>

      <!-- Tabs -->
      <div class="mt-5 flex gap-1 border-b border-line">
        <button v-for="x in (['discussions','members','about'] as const)" :key="x" type="button" @click="tab = x"
          class="-mb-px border-b-2 px-4 py-2.5 text-sm font-semibold capitalize" :class="tab === x ? 'border-primary text-primary' : 'border-transparent text-ink-2 hover:text-ink'">
          {{ t(x.charAt(0).toUpperCase() + x.slice(1)) }}
        </button>
      </div>

      <!-- Discussions -->
      <div v-show="tab === 'discussions'" class="mt-4">
        <div v-if="g.canPost" class="mb-4 rounded-[var(--c-radius)] border border-line bg-surface p-4">
          <button v-if="!showComposer" type="button" @click="showComposer = true" class="w-full rounded-lg bg-surface-2 px-4 py-3 text-left text-sm font-semibold text-ink-muted hover:bg-primary/10">
            {{ t('Start a discussion in this group…') }}
          </button>
          <div v-else class="space-y-3">
            <input v-model="dTitle" type="text" maxlength="160" :placeholder="t('Discussion title')" class="w-full rounded-lg border-line bg-surface-2 px-3 py-2 text-sm font-semibold text-ink focus:border-primary focus:ring-primary" />
            <Editor ref="composer" :placeholder="t('Write something…')" />
            <div>
              <label class="flex items-center gap-2 text-sm font-semibold text-ink-2">
                <input v-model="withPoll" type="checkbox" class="h-4 w-4 rounded border-line text-primary focus:ring-primary" /> {{ t('Add a poll') }}
              </label>
              <div v-if="withPoll" class="mt-2 space-y-2 rounded-lg bg-surface-2 p-3">
                <input v-model="poll.question" type="text" maxlength="200" :placeholder="t('Poll question')" class="w-full rounded-lg border-line bg-surface px-3 py-2 text-sm text-ink focus:border-primary focus:ring-primary" />
                <input v-for="(o, i) in poll.options" :key="i" v-model="poll.options[i]" type="text" maxlength="120" :placeholder="t('Option') + ' ' + (i + 1)" class="w-full rounded-lg border-line bg-surface px-3 py-1.5 text-sm text-ink focus:border-primary focus:ring-primary" />
                <div class="flex items-center gap-3">
                  <button type="button" @click="poll.options.length < 10 && poll.options.push('')" class="text-xs font-bold text-primary">+ {{ t('Add option') }}</button>
                  <label class="flex items-center gap-1.5 text-xs font-semibold text-ink-2"><input v-model="poll.multiple" type="checkbox" class="h-3.5 w-3.5 rounded border-line text-primary" /> {{ t('Allow multiple') }}</label>
                </div>
              </div>
            </div>
            <div class="flex justify-end gap-2">
              <button type="button" @click="showComposer = false" class="rounded-lg px-4 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2">{{ t('Cancel') }}</button>
              <button type="button" :disabled="posting || !dTitle.trim()" @click="postDiscussion" class="rounded-lg bg-primary px-4 py-2 text-sm font-bold text-white hover:bg-primary-600 disabled:opacity-50">{{ t('Post discussion') }}</button>
            </div>
          </div>
        </div>

        <div v-if="discussions.length" class="space-y-2">
          <Link v-for="d in discussions" :key="d.id" :href="`/groups/${g.slug}/d/${d.id}`" class="block rounded-[var(--c-radius)] border border-line bg-surface p-4 hover:border-primary/40">
            <div class="flex items-center gap-2">
              <span v-if="d.isPinned" class="text-primary" :title="t('Pinned')">📌</span>
              <span v-if="d.isLocked" class="text-ink-muted" :title="t('Locked')">🔒</span>
              <span class="font-bold text-ink">{{ d.title }}</span>
            </div>
            <p v-if="d.excerpt" class="mt-1 line-clamp-1 text-sm text-ink-2">{{ d.excerpt }}</p>
            <div class="mt-2 text-xs text-ink-muted">
              <span v-if="d.author">{{ d.author.name }}</span> · {{ d.replyCount }} {{ t('replies') }} · {{ timeAgo(d.lastPostAt) }}
            </div>
          </Link>
          <div v-if="next" class="pt-2 text-center">
            <button type="button" @click="loadMore" class="rounded-lg border border-line bg-surface px-5 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2">{{ t('Load more') }}</button>
          </div>
        </div>
        <p v-else class="rounded-[var(--c-radius)] border border-dashed border-line bg-surface px-6 py-12 text-center text-sm text-ink-2">{{ t('No discussions yet.') }}</p>
      </div>

      <!-- Members -->
      <div v-show="tab === 'members'" class="mt-4">
        <div v-if="g.canModerate" class="mb-3 flex gap-2">
          <input v-model="inviteName" type="text" :placeholder="t('Invite by username…')" class="flex-1 rounded-lg border-line bg-surface px-3 py-2 text-sm text-ink focus:border-primary focus:ring-primary" />
          <button type="button" @click="invite" class="rounded-lg bg-primary px-4 py-2 text-sm font-bold text-white hover:bg-primary-600">{{ t('Add') }}</button>
        </div>
        <div class="divide-y divide-line rounded-[var(--c-radius)] border border-line bg-surface">
          <div v-for="m in members" :key="m.id" class="flex items-center gap-3 p-3">
            <Link :href="m.user.url" class="grid h-9 w-9 place-items-center rounded-full text-sm font-bold text-white" :class="`av-g${m.user.color}`">
              <img v-if="m.user.avatar" :src="m.user.avatar" alt="" class="h-full w-full rounded-full object-cover" />
              <span v-else>{{ m.user.initials }}</span>
            </Link>
            <Link :href="m.user.url" class="font-semibold text-ink hover:text-primary">{{ m.user.name }}</Link>
            <span v-if="m.role !== 'member'" class="rounded-full bg-primary/15 px-2 py-0.5 text-xs font-bold capitalize text-primary">{{ t(m.role.charAt(0).toUpperCase() + m.role.slice(1)) }}</span>
            <div v-if="g.canManage && m.role !== 'owner'" class="ml-auto flex items-center gap-1.5">
              <select :value="m.role" @change="setRole(m.id, ($event.target as HTMLSelectElement).value)" class="rounded-lg border-line bg-surface-2 py-1 pl-2 pr-6 text-xs font-semibold text-ink-2">
                <option value="member">{{ t('Member') }}</option>
                <option value="moderator">{{ t('Moderator') }}</option>
              </select>
              <button type="button" @click="ban(m.id)" class="rounded-lg bg-surface-2 px-2 py-1 text-xs font-bold text-rose-500 hover:bg-rose-500/10">{{ t('Ban') }}</button>
              <button type="button" @click="kick(m.id)" class="rounded-lg bg-surface-2 px-2 py-1 text-xs font-bold text-ink-2 hover:bg-surface-2">{{ t('Remove') }}</button>
            </div>
            <div v-else-if="g.canModerate && m.role !== 'owner'" class="ml-auto flex items-center gap-1.5">
              <button type="button" @click="kick(m.id)" class="rounded-lg bg-surface-2 px-2 py-1 text-xs font-bold text-ink-2">{{ t('Remove') }}</button>
            </div>
          </div>
        </div>
      </div>

      <!-- About -->
      <div v-show="tab === 'about'" class="mt-4 rounded-[var(--c-radius)] border border-line bg-surface p-5">
        <p v-if="g.description" class="whitespace-pre-line text-sm text-ink-2">{{ g.description }}</p>
        <p v-else class="text-sm text-ink-muted">{{ t('This group has no description.') }}</p>
        <dl class="mt-4 grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
          <div><dt class="text-xs font-semibold uppercase text-ink-muted">{{ t('Owner') }}</dt><dd class="font-semibold text-ink">{{ g.owner?.name }}</dd></div>
          <div><dt class="text-xs font-semibold uppercase text-ink-muted">{{ t('Visibility') }}</dt><dd class="font-semibold text-ink">{{ g.isPrivate ? t('Private') : t('Public') }}</dd></div>
          <div><dt class="text-xs font-semibold uppercase text-ink-muted">{{ t('Joining') }}</dt><dd class="font-semibold capitalize text-ink">{{ t(g.membershipType) }}</dd></div>
        </dl>
        <a v-if="!g.isPrivate" :href="`/groups/${g.slug}/feed.rss`" class="mt-4 inline-block text-xs font-bold text-primary">{{ t('RSS feed') }}</a>
      </div>
    </div>

    <!-- Edit modal -->
    <Teleport to="body">
      <div v-if="showEdit" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showEdit = false">
        <div class="w-full max-w-lg rounded-[var(--c-radius)] bg-surface p-6 shadow-xl">
          <h2 class="mb-4 text-lg font-extrabold text-ink">{{ t('Group settings') }}</h2>
          <div class="space-y-4">
            <input v-model="edit.name" type="text" maxlength="120" class="w-full rounded-lg border-line bg-surface-2 px-3 py-2 text-sm text-ink focus:border-primary focus:ring-primary" />
            <textarea v-model="edit.description" rows="3" maxlength="2000" class="w-full rounded-lg border-line bg-surface-2 px-3 py-2 text-sm text-ink focus:border-primary focus:ring-primary"></textarea>
            <div>
              <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-ink-muted">{{ t('Icon & banner') }}</span>
              <div class="flex items-center gap-3">
                <label class="grid h-14 w-14 shrink-0 cursor-pointer place-items-center overflow-hidden rounded-xl border border-line text-white" :style="{ background: edit.color }" :title="t('Group icon')">
                  <img v-if="edit.avatar" :src="edit.avatar" alt="" class="h-full w-full object-cover" />
                  <span v-else class="text-lg">{{ uploadingAvatar ? '…' : '+' }}</span>
                  <input type="file" accept="image/*" class="hidden" @change="(e) => pickImage(e, 'avatar')" />
                </label>
                <label class="flex h-14 flex-1 cursor-pointer items-center justify-center overflow-hidden rounded-xl border border-dashed border-line bg-surface-2 text-xs font-semibold text-ink-muted" :style="edit.cover ? { backgroundImage: `url(${edit.cover})`, backgroundSize: 'cover', backgroundPosition: 'center' } : {}" :title="t('Banner image')">
                  <span v-if="!edit.cover" class="px-2 text-center">{{ uploadingCover ? t('Uploading…') : t('Upload banner') }}</span>
                  <input type="file" accept="image/*" class="hidden" @change="(e) => pickImage(e, 'cover')" />
                </label>
                <button v-if="edit.avatar || edit.cover" type="button" class="shrink-0 text-xs font-bold text-ink-muted hover:text-rose-500" @click="edit.avatar = ''; edit.cover = ''" :title="t('Clear images')">✕</button>
              </div>
            </div>
            <div class="flex items-center gap-4">
              <input v-model="edit.color" type="color" class="h-8 w-10 cursor-pointer rounded border-line" />
              <select v-model="edit.membership_type" class="flex-1 rounded-lg border-line bg-surface-2 px-3 py-2 text-sm text-ink focus:border-primary focus:ring-primary">
                <option value="open">{{ t('Anyone (open)') }}</option>
                <option value="approval">{{ t('Request to join (approval)') }}</option>
                <option value="invite">{{ t('Invite only') }}</option>
              </select>
            </div>
            <label class="flex items-center gap-2"><input v-model="edit.is_private" type="checkbox" class="h-4 w-4 rounded border-line text-primary focus:ring-primary" /><span class="text-sm font-semibold text-ink-2">{{ t('Private') }}</span></label>
          </div>
          <div class="mt-6 flex items-center justify-between">
            <button type="button" @click="destroyGroup" class="text-sm font-bold text-rose-500 hover:underline">{{ t('Delete group') }}</button>
            <div class="flex gap-2">
              <button type="button" @click="showEdit = false" class="rounded-lg px-4 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2">{{ t('Cancel') }}</button>
              <button type="button" @click="saveEdit" class="rounded-lg bg-primary px-4 py-2 text-sm font-bold text-white hover:bg-primary-600">{{ t('Save') }}</button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Analytics modal -->
    <Teleport to="body">
      <div v-if="showStats" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" @click.self="showStats = false">
        <div class="w-full max-w-xl rounded-[var(--c-radius)] bg-surface p-6 shadow-xl">
          <h2 class="mb-4 text-lg font-extrabold text-ink">{{ t('Group analytics') }}</h2>
          <div v-if="stats">
            <div class="mb-4 grid grid-cols-3 gap-3 text-center">
              <div class="rounded-lg bg-surface-2 p-3"><div class="text-2xl font-extrabold text-ink">{{ stats.totals.members }}</div><div class="text-xs text-ink-muted">{{ t('Members') }}</div></div>
              <div class="rounded-lg bg-surface-2 p-3"><div class="text-2xl font-extrabold text-ink">{{ stats.totals.discussions }}</div><div class="text-xs text-ink-muted">{{ t('Discussions') }}</div></div>
              <div class="rounded-lg bg-surface-2 p-3"><div class="text-2xl font-extrabold text-ink">{{ stats.totals.posts }}</div><div class="text-xs text-ink-muted">{{ t('Posts') }}</div></div>
            </div>
            <div class="mb-2 text-xs font-semibold uppercase text-ink-muted">{{ t('Last 30 days') }}</div>
            <div class="flex h-24 items-end gap-0.5">
              <div v-for="(d, i) in stats.series" :key="i" class="flex-1" :title="`${d.date}: +${d.joins} ${t('joins')}, ${d.posts} ${t('posts')}`">
                <div class="w-full rounded-t bg-primary" :style="{ height: (d.posts / maxSeries * 80) + 'px' }"></div>
                <div class="w-full rounded-b bg-accent/60" :style="{ height: (d.joins / maxSeries * 80) + 'px' }"></div>
              </div>
            </div>
            <div v-if="stats.top?.length" class="mt-4">
              <div class="mb-1 text-xs font-semibold uppercase text-ink-muted">{{ t('Top discussions') }}</div>
              <Link v-for="x in stats.top" :key="x.id" :href="x.url" class="flex items-center justify-between py-1 text-sm text-ink hover:text-primary"><span class="truncate">{{ x.title }}</span><span class="ml-2 shrink-0 text-xs text-ink-muted">{{ x.replies }} · {{ x.views }}👁</span></Link>
            </div>
          </div>
          <p v-else class="py-8 text-center text-sm text-ink-muted">{{ t('Loading…') }}</p>
          <div class="mt-5 text-right"><button type="button" @click="showStats = false" class="rounded-lg px-4 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2">{{ t('Close') }}</button></div>
        </div>
      </div>
    </Teleport>
  </AppLayout>
</template>
