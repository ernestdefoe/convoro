<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Avatar from '@/Components/forum/Avatar.vue';
import Slot from '@/Components/ext/Slot.vue';
import Editor from '@/Components/Editor.vue';
import { t as tr } from '@/lib/i18n';

const props = defineProps<{
  profile: { id: number; name: string; bio: string | null; avatar: string | null; cover: string | null; initials: string; color: number; staff: { name: string; color: string } | null; joined: string; isAdmin: boolean; verified: boolean; isSelf: boolean; fediHandle: string | null; fediUrl: string | null; trust: { level: number; label: string } | null; primaryGroup?: { name: string; slug: string; color: string } | null };
  stats: { topics: number; posts: number };
  recentTopics: { title: string; url: string; when: string }[];
  wall: { id: number; html: string; author: any; createdAt: string; canDelete: boolean }[];
}>();

const page = usePage();
const loggedIn = computed(() => !!(page.props as any).auth?.user);

const editor = ref<any>(null);
const posting = ref(false);

function postToWall() {
  if (!editor.value || editor.value.isEmpty()) return;
  posting.value = true;
  router.post(`/u/${props.profile.id}/wall`, { body_html: editor.value.getHTML() }, {
    preserveScroll: true,
    onSuccess: () => editor.value?.clear(),
    onFinish: () => (posting.value = false),
  });
}

function removePost(id: number) {
  router.delete(`/profile-posts/${id}`, { preserveScroll: true });
}

function message() {
  router.post('/messages', { user_id: props.profile.id });
}

// ---- Live wall (Reverb public channel) ----
// Render from a reactive copy so realtime arrivals + Inertia reloads both reflect.
const livePosts = ref([...props.wall]);
watch(() => props.wall, (w) => (livePosts.value = [...w]));
const meId = computed(() => (page.props as any).auth?.user?.id ?? null);

let channel: any = null;
const Echo = () => (window as any).Echo;
onMounted(() => {
  if (!Echo()) return;
  channel = Echo()
    .channel(`profile.${props.profile.id}`)
    .listen('.ProfilePostCreated', (e: any) => {
      const post = e.post;
      if (!post || livePosts.value.some((p) => p.id === post.id)) return;
      // canDelete is viewer-specific, so recompute it here rather than trust the payload.
      post.canDelete = !!meId.value && (meId.value === post.author?.id || meId.value === props.profile.id);
      livePosts.value.unshift(post);
    });
});
onBeforeUnmount(() => {
  if (channel && Echo()) Echo().leave(`profile.${props.profile.id}`);
});
</script>

<template>
  <Head :title="profile.name" />
  <AppLayout>
    <div class="mx-auto max-w-[920px]">
      <!-- Cover + identity: a frosted-glass card pinned over the cover image -->
      <div class="relative">
        <div class="relative h-64 w-full overflow-hidden rounded-c border border-line sm:h-80" :class="profile.cover ? '' : 'q-grad'">
          <img v-if="profile.cover" :src="profile.cover" alt="" class="h-full w-full object-cover" />
          <!-- legibility wash under the card -->
          <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/55 via-black/5 to-transparent"></div>
        </div>

        <div class="absolute inset-x-2 bottom-2 z-10 rounded-c border border-white/15 bg-black/30 p-4 shadow-2xl backdrop-blur-2xl sm:inset-x-4 sm:bottom-4 sm:p-5">
          <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
            <!-- Avatar (pokes slightly above the card) -->
            <div class="-mt-12 flex w-fit shrink-0 ring-4 ring-white/70 sm:-mt-10" :style="{ borderRadius: 'var(--c-avatar-radius, 9999px)' }">
              <Avatar :avatar="{ id: profile.id, initials: profile.initials, color: profile.color, avatar: profile.avatar, staff: profile.staff }" :size="92" />
            </div>

            <!-- Name + stats -->
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                <h1 class="truncate text-2xl font-extrabold tracking-tight text-white drop-shadow-sm sm:text-3xl">{{ profile.name }}</h1>
                <!-- Verified check: only shown once the email is confirmed (a grey
                     check for unverified users read as "verified" with a contradicting
                     tooltip). -->
                <span
                  v-if="profile.verified"
                  class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-emerald-500 text-white shadow"
                  :title="tr('Email verified')"
                  :aria-label="tr('Email verified')"
                >
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
                </span>
                <span v-if="profile.isAdmin" class="shrink-0 rounded-full bg-white/15 px-2 py-0.5 text-xs font-bold uppercase tracking-wide text-white">{{ tr('Admin') }}</span>
                <span v-else-if="profile.trust && profile.trust.level >= 1" class="shrink-0 rounded-full bg-white/15 px-2 py-0.5 text-xs font-bold text-white">{{ profile.trust.level >= 4 ? '★ ' : '' }}{{ tr(profile.trust.label) }}</span>
                <a v-if="profile.primaryGroup" :href="`/groups/${profile.primaryGroup.slug}`" class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-bold text-white" :style="{ background: profile.primaryGroup.color + 'cc' }">{{ profile.primaryGroup.name }}</a>
              </div>
              <div class="mt-1.5 flex flex-wrap items-center gap-x-5 gap-y-1 text-sm text-white/75">
                <!-- Follower count is filled in by the convoro-follow extension
                     (its source of truth); stays hidden if that ext is absent. -->
                <span data-follow-stat class="hidden"></span>
                <span><strong class="font-bold text-white">{{ stats.topics }}</strong> {{ tr('topics') }}</span>
                <span><strong class="font-bold text-white">{{ stats.posts }}</strong> {{ tr('posts') }}</span>
                <span class="hidden sm:inline">{{ tr('Joined {joined}', { joined: profile.joined }) }}</span>
              </div>
            </div>

            <!-- Actions -->
            <div class="flex shrink-0 items-center gap-2">
              <Link v-if="profile.isSelf" href="/profile" class="rounded-xl bg-white/90 px-4 py-2.5 text-sm font-semibold text-slate-900 transition hover:bg-white">{{ tr('Edit profile') }}</Link>
              <button v-else-if="loggedIn" type="button" class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-lg transition hover:bg-primary-600" @click="message">{{ tr('Message') }}</button>
              <!-- Follow button (convoro-follow extension) renders here, in the header. -->
              <Slot name="profile:actions" :ctx="{ userId: profile.id }" />
              <a v-if="profile.fediHandle" :href="profile.fediUrl || undefined" target="_blank" rel="noopener me"
                 class="grid h-[42px] w-[42px] shrink-0 place-items-center rounded-xl bg-white/90 text-slate-900 transition hover:bg-white"
                 :title="tr('Follow on the fediverse') + ' — ' + profile.fediHandle" :aria-label="tr('Follow on the fediverse')">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" /><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" /></svg>
              </a>
            </div>
          </div>
        </div>
      </div>
      <p v-if="profile.bio" class="mt-4 px-1 text-ink-2">{{ profile.bio }}</p>

      <!-- Extension slot (e.g. Member Badges) -->
      <Slot name="profile:below" :ctx="{ userId: profile.id }" />

      <div class="mt-6 grid gap-6 md:grid-cols-[1fr_280px]">
        <!-- Wall -->
        <div>
          <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-ink-muted">{{ tr('Profile wall') }}</h2>

          <div v-if="loggedIn" class="mb-4 rounded-c border border-line bg-surface p-3">
            <Editor ref="editor" :placeholder="tr('Write something…')" />
            <div class="mt-2 flex justify-end">
              <button type="button" :disabled="posting" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-600 disabled:opacity-60" @click="postToWall">
                {{ posting ? tr('Posting…') : tr('Post') }}
              </button>
            </div>
          </div>

          <div class="space-y-3">
            <div v-for="p in livePosts" :key="p.id" class="rounded-c border border-line bg-surface p-4">
              <div class="flex items-center gap-2">
                <Link :href="p.author.url"><Avatar :avatar="p.author" :size="36" /></Link>
                <div class="min-w-0 flex-1">
                  <Link :href="p.author.url" class="text-sm font-bold hover:underline">{{ p.author.name }}</Link>
                  <div class="text-xs text-ink-muted">{{ p.createdAt }}</div>
                </div>
                <button v-if="p.canDelete" type="button" class="text-ink-muted hover:text-red-500" :aria-label="tr('Delete')" @click="removePost(p.id)">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6" /></svg>
                </button>
              </div>
              <div class="prose-convoro mt-2 text-ink-2" v-html="p.html"></div>
            </div>
            <p v-if="!livePosts.length" class="rounded-c border border-dashed border-line p-6 text-center text-sm text-ink-muted">{{ tr('No wall posts yet.') }}</p>
          </div>
        </div>

        <!-- Recent activity -->
        <aside>
          <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-ink-muted">{{ tr('Recent topics') }}</h2>
          <div class="rounded-c border border-line bg-surface">
            <Link v-for="(t, i) in recentTopics" :key="i" :href="t.url" class="block border-b border-line/60 px-4 py-3 last:border-0 hover:bg-surface-2">
              <div class="text-sm font-semibold text-ink line-clamp-1">{{ t.title }}</div>
              <div class="text-xs text-ink-muted">{{ t.when }}</div>
            </Link>
            <p v-if="!recentTopics.length" class="px-4 py-6 text-center text-sm text-ink-muted">{{ tr('No topics yet.') }}</p>
          </div>
        </aside>
      </div>
    </div>
  </AppLayout>
</template>
