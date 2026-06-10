<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import Avatar from '@/Components/forum/Avatar.vue';
import Editor from '@/Components/Editor.vue';

const props = defineProps<{
  profile: { id: number; name: string; bio: string | null; avatar: string | null; cover: string | null; initials: string; color: number; joined: string; isAdmin: boolean; isSelf: boolean };
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
</script>

<template>
  <Head :title="profile.name" />
  <AppLayout>
    <div class="mx-auto max-w-[920px]">
      <!-- Cover + identity -->
      <div class="overflow-hidden rounded-c border border-line bg-surface">
        <div class="h-44 w-full" :class="profile.cover ? '' : 'q-grad'">
          <img v-if="profile.cover" :src="profile.cover" alt="" class="h-44 w-full object-cover" />
        </div>
        <div class="px-6 pb-5">
          <div class="-mt-12 flex items-end justify-between">
            <div class="rounded-full ring-4 ring-surface">
              <Avatar :avatar="{ initials: profile.initials, color: profile.color, avatar: profile.avatar }" :size="96" />
            </div>
            <Link v-if="profile.isSelf" href="/profile" class="rounded-lg border border-line bg-surface px-4 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2">Edit profile</Link>
          </div>
          <div class="mt-3 flex items-center gap-2">
            <h1 class="text-2xl font-extrabold tracking-tight">{{ profile.name }}</h1>
            <span v-if="profile.isAdmin" class="rounded-full bg-primary/15 px-2 py-0.5 text-xs font-bold text-primary">Admin</span>
          </div>
          <p v-if="profile.bio" class="mt-1 text-ink-2">{{ profile.bio }}</p>
          <div class="mt-3 flex flex-wrap gap-4 text-sm text-ink-muted">
            <span>Joined {{ profile.joined }}</span>
            <span><strong class="text-ink">{{ stats.topics }}</strong> topics</span>
            <span><strong class="text-ink">{{ stats.posts }}</strong> posts</span>
          </div>
        </div>
      </div>

      <div class="mt-6 grid gap-6 md:grid-cols-[1fr_280px]">
        <!-- Wall -->
        <div>
          <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-ink-muted">Profile wall</h2>

          <div v-if="loggedIn" class="mb-4 rounded-c border border-line bg-surface p-3">
            <Editor ref="editor" placeholder="Write something…" />
            <div class="mt-2 flex justify-end">
              <button type="button" :disabled="posting" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-600 disabled:opacity-60" @click="postToWall">
                {{ posting ? 'Posting…' : 'Post' }}
              </button>
            </div>
          </div>

          <div class="space-y-3">
            <div v-for="p in wall" :key="p.id" class="rounded-c border border-line bg-surface p-4">
              <div class="flex items-center gap-2">
                <Link :href="p.author.url"><Avatar :avatar="p.author" :size="36" /></Link>
                <div class="min-w-0 flex-1">
                  <Link :href="p.author.url" class="text-sm font-bold hover:underline">{{ p.author.name }}</Link>
                  <div class="text-xs text-ink-muted">{{ p.createdAt }}</div>
                </div>
                <button v-if="p.canDelete" type="button" class="text-ink-muted hover:text-red-500" aria-label="Delete" @click="removePost(p.id)">
                  <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6" /></svg>
                </button>
              </div>
              <div class="prose-convoro mt-2 text-ink-2" v-html="p.html"></div>
            </div>
            <p v-if="!wall.length" class="rounded-c border border-dashed border-line p-6 text-center text-sm text-ink-muted">No wall posts yet.</p>
          </div>
        </div>

        <!-- Recent activity -->
        <aside>
          <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-ink-muted">Recent topics</h2>
          <div class="rounded-c border border-line bg-surface">
            <Link v-for="(t, i) in recentTopics" :key="i" :href="t.url" class="block border-b border-line/60 px-4 py-3 last:border-0 hover:bg-surface-2">
              <div class="text-sm font-semibold text-ink line-clamp-1">{{ t.title }}</div>
              <div class="text-xs text-ink-muted">{{ t.when }}</div>
            </Link>
            <p v-if="!recentTopics.length" class="px-4 py-6 text-center text-sm text-ink-muted">No topics yet.</p>
          </div>
        </aside>
      </div>
    </div>
  </AppLayout>
</template>
