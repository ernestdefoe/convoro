<script setup lang="ts">
import Avatar from '@/Components/forum/Avatar.vue';
import { ref, watch } from 'vue';
import { t } from '@/lib/i18n';

const props = defineProps<{ items: any[]; command: (item: any) => void }>();

const selected = ref(0);
watch(() => props.items, () => { selected.value = 0; });

function select(i: number) {
  const item = props.items[i];
  if (item) props.command(item);
}

function onKeyDown({ event }: { event: KeyboardEvent }): boolean {
  if (!props.items.length) return false;
  if (event.key === 'ArrowDown') { selected.value = (selected.value + 1) % props.items.length; return true; }
  if (event.key === 'ArrowUp') { selected.value = (selected.value - 1 + props.items.length) % props.items.length; return true; }
  if (event.key === 'Enter' || event.key === 'Tab') { select(selected.value); return true; }
  return false;
}

defineExpose({ onKeyDown });
</script>

<template>
  <div class="w-64 overflow-hidden rounded-c border border-line bg-surface py-1 shadow-2xl shadow-black/20">
    <button
      v-for="(item, i) in items"
      :key="item.id"
      type="button"
      class="flex w-full items-center gap-2.5 px-3 py-2 text-left"
      :class="i === selected ? 'bg-primary/15' : 'hover:bg-surface-2'"
      @mousedown.prevent="select(i)"
      @mouseenter="selected = i"
    >
      <Avatar :avatar="item" :size="28" />
      <span class="truncate text-sm font-semibold text-ink">{{ item.name }}</span>
      <span class="ml-auto truncate text-xs text-ink-muted">@{{ item.id }}</span>
    </button>
    <div v-if="!items.length" class="px-3 py-2 text-sm text-ink-muted">{{ t('No matching members') }}</div>
  </div>
</template>
