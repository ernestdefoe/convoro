<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import ConvoroLogo from '@/Components/ConvoroLogo.vue';

const page = usePage();
const user = computed(() => (page.props as any).auth?.user ?? null);
const forumUrl = 'https://community.convoro.co';
</script>

<template>
  <div class="min-h-screen bg-white text-slate-900">
    <header class="sticky top-0 z-40 border-b border-slate-200/70 bg-white/80 backdrop-blur">
      <div class="mx-auto flex h-16 max-w-6xl items-center gap-6 px-6">
        <Link href="/" class="flex items-center gap-2.5 font-extrabold text-lg"><ConvoroLogo :size="32" /> Convoro</Link>
        <nav class="ml-2 hidden items-center gap-1 md:flex">
          <Link href="/" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100">Home</Link>
          <Link href="/store" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100">Store</Link>
          <a href="/docs/install.html" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100">Docs</a>
          <a :href="forumUrl" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100">Community</a>
        </nav>
        <div class="ml-auto flex items-center gap-2.5">
          <template v-if="user">
            <Link href="/account/licenses" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100">My licenses</Link>
            <a :href="forumUrl" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-indigo-600/25 hover:bg-indigo-700">Go to community</a>
          </template>
          <template v-else>
            <Link href="/login" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100">Log in</Link>
            <Link href="/register" class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-indigo-600/25 hover:bg-indigo-700">Get started</Link>
          </template>
        </div>
      </div>
    </header>

    <main><slot /></main>

    <footer class="border-t border-slate-200 py-10">
      <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-4 px-6 text-sm text-slate-500">
        <Link href="/" class="flex items-center gap-2 font-bold text-slate-700"><ConvoroLogo :size="24" /> Convoro</Link>
        <div class="ml-auto flex items-center gap-5">
          <Link href="/store" class="font-semibold text-slate-600 hover:text-slate-900">Store</Link>
          <a href="/docs/install.html" class="font-semibold text-slate-600 hover:text-slate-900">Docs</a>
          <a :href="forumUrl" class="font-semibold text-slate-600 hover:text-slate-900">Community</a>
          <span>© {{ new Date().getFullYear() }} Convoro</span>
        </div>
      </div>
    </footer>
  </div>
</template>
