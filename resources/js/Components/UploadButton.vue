<script setup lang="ts">
import { t } from '@/lib/i18n';

// A consistent, on-brand upload button (replaces the raw browser file input).
// Emits the chosen File; the parent handles the actual upload.
defineProps<{ label?: string; accept?: string; uploading?: boolean }>();
const emit = defineEmits<{ (e: 'file', f: File): void }>();

function pick(e: Event) {
  const el = e.target as HTMLInputElement;
  const f = el.files?.[0];
  if (f) emit('file', f);
  el.value = ''; // allow re-selecting the same file
}
</script>

<template>
  <label
    class="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-line bg-surface-2 px-3.5 py-2 text-sm font-semibold text-ink-2 transition hover:border-primary/50 hover:bg-appbg hover:text-ink"
    :class="uploading ? 'pointer-events-none opacity-70' : ''"
  >
    <svg v-if="uploading" class="animate-spin" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round">
      <path d="M21 12a9 9 0 1 1-6.2-8.5" />
    </svg>
    <svg v-else width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
      <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12" />
    </svg>
    <span>{{ uploading ? t('Uploading…') : (label || t('Choose image')) }}</span>
    <input type="file" :accept="accept || 'image/png,image/jpeg,image/webp'" class="sr-only" @change="pick" />
  </label>
</template>
