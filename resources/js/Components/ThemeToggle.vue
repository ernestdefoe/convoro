<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { t } from '@/lib/i18n';
import { setTheme } from '@/lib/theme';

const mode = ref<'light' | 'dark'>('light');

onMounted(() => {
  mode.value = document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light';
});

function toggle() {
  mode.value = mode.value === 'dark' ? 'light' : 'dark';
  setTheme(mode.value);
}
</script>

<template>
  <button
    type="button"
    class="flex h-[34px] w-[34px] items-center justify-center rounded-full border border-line bg-surface-2 text-ink-2 hover:text-ink"
    :aria-label="mode === 'dark' ? t('Switch to light mode') : t('Switch to dark mode')"
    :title="mode === 'dark' ? t('Light mode') : t('Dark mode')"
    @click="toggle"
  >
    <!-- sun (shown in dark mode → click for light) -->
    <svg v-if="mode === 'dark'" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <circle cx="12" cy="12" r="4" /><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M6.3 17.7l-1.4 1.4M19.1 4.9l-1.4 1.4" />
    </svg>
    <!-- moon (shown in light mode → click for dark) -->
    <svg v-else width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z" />
    </svg>
  </button>
</template>
