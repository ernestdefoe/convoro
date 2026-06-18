<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ConvoroLogo from '@/Components/ConvoroLogo.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';
import AuthModal from '@/Components/AuthModal.vue';
import { useAuthModal } from '@/lib/authModal';

const page = usePage();
const user = computed(() => (page.props as any).auth?.user ?? null);
const version = computed(() => (page.props as any).appVersion ?? null);
const forumUrl = 'https://community.convoro.co';
const auth = useAuthModal();
</script>

<template>
  <div class="min-h-screen bg-appbg text-ink">
    <header class="sticky top-0 z-40 border-b border-line bg-surface/85 backdrop-blur">
      <div class="mx-auto flex h-16 max-w-6xl items-center gap-6 px-6">
        <Link href="/" class="flex items-center"><ConvoroLogo :size="32" /></Link>
        <nav class="ml-2 hidden items-center gap-1 md:flex">
          <Link href="/" class="rounded-lg px-3 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2">Home</Link>
          <Link href="/extensions" class="rounded-lg px-3 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2">Extensions</Link>
          <Link href="/compare" class="rounded-lg px-3 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2">Compare</Link>
          <Link href="/convorocp" class="rounded-lg px-3 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2">ConvoroCP</Link>
          <a href="/docs" class="rounded-lg px-3 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2">Docs</a>
          <a :href="forumUrl" class="rounded-lg px-3 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2">Community</a>
        </nav>
        <div class="ml-auto flex items-center gap-2.5">
          <ThemeToggle />
          <template v-if="user">
            <Link href="/account/licenses" class="rounded-lg px-3 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2">My licenses</Link>
            <a :href="forumUrl" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-primary/25 hover:bg-primary-600">Go to community</a>
          </template>
          <template v-else>
            <button type="button" @click="auth.open('login')" class="rounded-lg px-3 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2">Log in</button>
            <button type="button" @click="auth.open('register')" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-primary/25 hover:bg-primary-600">Get started</button>
          </template>
        </div>
      </div>
    </header>

    <main><slot /></main>

    <footer class="border-t border-line py-10">
      <div class="mx-auto max-w-6xl px-6">
        <div class="flex flex-wrap items-center gap-x-6 gap-y-3 text-sm text-ink-muted">
          <Link href="/" class="flex items-center"><ConvoroLogo :size="24" /></Link>
          <Link href="/extensions" class="font-semibold text-ink-2 hover:text-ink">Extensions</Link>
          <Link href="/compare" class="font-semibold text-ink-2 hover:text-ink">Compare</Link>
          <Link href="/convorocp" class="font-semibold text-ink-2 hover:text-ink">ConvoroCP</Link>
          <a href="/docs" class="font-semibold text-ink-2 hover:text-ink">Docs</a>
          <Link href="/changelog" class="font-semibold text-ink-2 hover:text-ink">Changelog</Link>
          <Link href="/roadmap" class="font-semibold text-ink-2 hover:text-ink">Roadmap</Link>
          <Link href="/about" class="font-semibold text-ink-2 hover:text-ink">About</Link>
          <Link href="/security" class="font-semibold text-ink-2 hover:text-ink">Security</Link>
          <a :href="forumUrl" class="font-semibold text-ink-2 hover:text-ink">Community</a>
          <Link href="/status" class="ml-auto inline-flex items-center gap-1.5 font-semibold text-ink-2 hover:text-ink">
            <span class="h-2 w-2 rounded-full bg-emerald-500"></span> Status
          </Link>
        </div>
        <div class="mt-6 flex flex-wrap items-center gap-3 border-t border-line pt-6 text-xs text-ink-muted">
          <span>© {{ new Date().getFullYear() }} Convoro</span>
          <Link v-if="version" href="/changelog" class="rounded-full bg-surface-2 px-2 py-0.5 font-mono font-semibold text-ink-2 hover:text-ink">v{{ version }}</Link>
          <!-- Launchpadly — Convoro (text) -->
          <a href="https://launchpadly.co/startup/convoro" target="_blank" rel="noopener noreferrer" data-launchpadly-badge="convoro" class="ml-auto font-semibold text-ink-2 hover:text-ink">
            Proudly listed on Launchpadly Startup Directory
          </a>
        </div>
      </div>
    </footer>

    <AuthModal />
  </div>
</template>
