<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const current = computed(() => page.component);

const nav = [
  { label: 'Dashboard', href: '/admin', component: 'Admin/Dashboard', icon: 'M3 12l9-9 9 9M5 10v10h14V10' },
  { label: 'Categories & Tags', href: '/admin/content', component: 'Admin/Content', icon: 'M3 7h18M3 12h18M3 17h10' },
  { label: 'Settings', href: '/admin/settings', component: 'Admin/Settings', icon: 'M12 15a3 3 0 100-6 3 3 0 000 6z M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 11-4 0v-.09A1.65 1.65 0 009 19.4' },
  { label: 'Theme', href: '/admin/theme', component: 'Admin/Theme', icon: 'M12 3a9 9 0 100 18h.5a2.5 2.5 0 002.5-2.5 2.5 2.5 0 012.5-2.5H21a0 0 0 000 0' },
  { label: 'Accessibility', href: '/admin/accessibility', component: 'Admin/Accessibility', icon: 'M12 2a2 2 0 110 4 2 2 0 010-4z M21 9l-6-1h-6L3 9 M9 22l3-8 3 8 M12 8v6' },
];

function active(c: string) {
  return current.value === c;
}
</script>

<template>
  <div class="flex min-h-screen bg-[#0f1120] text-slate-200">
    <!-- Sidebar -->
    <aside class="hidden w-[240px] shrink-0 flex-col border-r border-white/5 bg-[#14172a] p-4 md:flex">
      <div class="mb-6 flex items-center gap-2 px-2">
        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-indigo-500 to-violet-500 font-extrabold text-white">C</span>
        <span class="text-sm font-bold text-white">Convoro Admin</span>
      </div>

      <nav class="flex flex-col gap-1">
        <Link
          v-for="item in nav"
          :key="item.href"
          :href="item.href"
          class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition"
          :class="active(item.component) ? 'bg-white/10 text-white' : 'text-slate-400 hover:bg-white/5 hover:text-white'"
        >
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path :d="item.icon" /></svg>
          {{ item.label }}
        </Link>
      </nav>

      <Link href="/" class="mt-auto flex items-center gap-2 rounded-lg px-3 py-2 text-sm text-slate-400 hover:bg-white/5 hover:text-white">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7" /></svg>
        Back to site
      </Link>
    </aside>

    <!-- Content -->
    <div class="flex-1">
      <header class="border-b border-white/5 bg-[#14172a]/60 px-6 py-4">
        <h1 class="text-lg font-bold text-white"><slot name="title">Admin</slot></h1>
        <p v-if="$slots.subtitle" class="mt-0.5 text-sm text-slate-400"><slot name="subtitle" /></p>
      </header>
      <main class="p-6">
        <slot />
      </main>
    </div>
  </div>
</template>
