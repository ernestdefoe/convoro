<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';

defineProps<{
  stats: { users: number; topics: number; posts: number; reactions: number };
  newUsers: { name: string; joined: string }[];
}>();

const cards = (s: any) => [
  { label: 'Members', value: s.users },
  { label: 'Topics', value: s.topics },
  { label: 'Posts', value: s.posts },
  { label: 'Reactions', value: s.reactions },
];
</script>

<template>
  <Head title="Admin · Dashboard" />
  <AdminLayout>
    <template #title>Dashboard</template>
    <template #subtitle>An overview of your community</template>

    <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
      <div v-for="c in cards(stats)" :key="c.label" class="rounded-2xl border border-white/5 bg-[#14172a] p-5">
        <div class="text-3xl font-extrabold text-white">{{ c.value.toLocaleString() }}</div>
        <div class="mt-1 text-sm text-slate-400">{{ c.label }}</div>
      </div>
    </div>

    <div class="mt-6 rounded-2xl border border-white/5 bg-[#14172a] p-5">
      <h2 class="mb-3 text-sm font-bold text-white">Newest members</h2>
      <ul class="divide-y divide-white/5">
        <li v-for="(u, i) in newUsers" :key="i" class="flex items-center justify-between py-2.5 text-sm">
          <span class="text-slate-200">{{ u.name }}</span>
          <span class="text-slate-500">{{ u.joined }}</span>
        </li>
        <li v-if="!newUsers.length" class="py-2.5 text-sm text-slate-500">No members yet.</li>
      </ul>
    </div>
  </AdminLayout>
</template>
