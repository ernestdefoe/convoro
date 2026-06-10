<script setup lang="ts">
import ConvoroLogo from '@/Components/ConvoroLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
  <div class="flex min-h-screen flex-col items-center justify-center bg-appbg px-6 py-12 text-ink">
    <Link href="/" class="mb-7">
      <ConvoroLogo :size="40" />
    </Link>

    <div class="w-full overflow-hidden rounded-c border border-line bg-surface px-7 py-8 shadow-xl sm:max-w-md">
      <slot />
    </div>

    <p class="mt-6 text-sm text-ink-muted">© {{ new Date().getFullYear() }} Convoro</p>
  </div>
</template>
