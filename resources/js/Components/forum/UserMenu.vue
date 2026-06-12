<script setup lang="ts">
import Avatar from '@/Components/forum/Avatar.vue';
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { t } from '@/lib/i18n';

const page = usePage();
const user = computed(() => (page.props as any).auth?.user ?? null);
const isAdmin = computed(() => !!(page.props as any).auth?.isAdmin);

const initials = computed(() => {
  if (!user.value) return '';
  const p = String(user.value.name).trim().split(/\s+/);
  return (p[0]?.[0] ?? '') + (p.length > 1 ? p[p.length - 1][0] : '');
});

const open = ref(false);
const root = ref<HTMLElement | null>(null);

function onDoc(e: MouseEvent) {
  if (open.value && root.value && !root.value.contains(e.target as Node)) open.value = false;
}
onMounted(() => document.addEventListener('click', onDoc));
onBeforeUnmount(() => document.removeEventListener('click', onDoc));

function logout() {
  open.value = false;
  router.post('/logout');
}
</script>

<template>
  <div v-if="user" ref="root" class="relative">
    <button type="button" class="block rounded-full focus:outline-none focus:ring-2 focus:ring-primary" :aria-label="t('Account menu')" @click="open = !open">
      <Avatar :avatar="{ initials, color: (user.id % 6) + 1, avatar: user.avatar_path }" :size="34" />
    </button>

    <div v-if="open" class="absolute right-0 top-[44px] z-50 w-56 overflow-hidden rounded-c border border-line bg-surface py-1 shadow-2xl shadow-black/15">
      <div class="border-b border-line px-4 py-3">
        <div class="truncate text-sm font-bold text-ink">{{ user.name }}</div>
        <div class="truncate text-xs text-ink-muted">{{ user.email }}</div>
      </div>

      <Link :href="`/u/${user.id}`" class="block px-4 py-2.5 text-sm font-medium text-ink-2 hover:bg-surface-2 hover:text-ink" @click="open = false">{{ t('Your profile') }}</Link>
      <Link href="/profile" class="block px-4 py-2.5 text-sm font-medium text-ink-2 hover:bg-surface-2 hover:text-ink" @click="open = false">{{ t('Settings') }}</Link>
      <a v-if="isAdmin" href="/admin" target="_blank" rel="noopener" class="block px-4 py-2.5 text-sm font-medium text-ink-2 hover:bg-surface-2 hover:text-ink">{{ t('Admin') }} <span class="text-ink-muted">↗</span></a>

      <div class="my-1 border-t border-line"></div>
      <button type="button" class="block w-full px-4 py-2.5 text-left text-sm font-medium text-red-500 hover:bg-surface-2" @click="logout">{{ t('Log out') }}</button>
    </div>
  </div>
</template>
