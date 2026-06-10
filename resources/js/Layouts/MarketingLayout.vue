<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ConvoroLogo from '@/Components/ConvoroLogo.vue';
import ThemeToggle from '@/Components/ThemeToggle.vue';

const page = usePage();
const user = computed(() => (page.props as any).auth?.user ?? null);
const forumUrl = 'https://community.convoro.co';
</script>

<template>
  <div class="min-h-screen bg-appbg text-ink">
    <header class="sticky top-0 z-40 border-b border-line bg-surface/85 backdrop-blur">
      <div class="mx-auto flex h-16 max-w-6xl items-center gap-6 px-6">
        <Link href="/" class="flex items-center"><ConvoroLogo :size="32" /></Link>
        <nav class="ml-2 hidden items-center gap-1 md:flex">
          <Link href="/" class="rounded-lg px-3 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2">Home</Link>
          <Link href="/extensions" class="rounded-lg px-3 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2">Extensions</Link>
          <a href="/docs/install.html" class="rounded-lg px-3 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2">Docs</a>
          <a :href="forumUrl" class="rounded-lg px-3 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2">Community</a>
        </nav>
        <div class="ml-auto flex items-center gap-2.5">
          <ThemeToggle />
          <template v-if="user">
            <Link href="/account/licenses" class="rounded-lg px-3 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2">My licenses</Link>
            <a :href="forumUrl" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-primary/25 hover:bg-primary-600">Go to community</a>
          </template>
          <template v-else>
            <Link href="/login" class="rounded-lg px-3 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2">Log in</Link>
            <Link href="/register" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-primary/25 hover:bg-primary-600">Get started</Link>
          </template>
        </div>
      </div>
    </header>

    <main><slot /></main>

    <footer class="border-t border-line py-10">
      <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-4 px-6 text-sm text-ink-muted">
        <Link href="/" class="flex items-center"><ConvoroLogo :size="24" /></Link>
        <div class="ml-auto flex items-center gap-5">
          <Link href="/extensions" class="font-semibold text-ink-2 hover:text-ink">Extensions</Link>
          <a href="/docs/install.html" class="font-semibold text-ink-2 hover:text-ink">Docs</a>
          <a :href="forumUrl" class="font-semibold text-ink-2 hover:text-ink">Community</a>
          <span>© {{ new Date().getFullYear() }} Convoro</span>
        </div>
      </div>
    </footer>
  </div>
</template>
