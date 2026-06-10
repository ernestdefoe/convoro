<script setup lang="ts">
import { computed, ref, watch, onBeforeUnmount } from 'vue';
import { convoro, type SlotEntry } from '@/lib/convoro-ext';

// Renders everything extensions have registered for a named slot. Reactive, so
// extensions that finish loading after the page mounts still appear.
const props = defineProps<{ name: string; ctx?: Record<string, unknown> }>();

const entries = computed<SlotEntry[]>(() => convoro.slotEntries(props.name));

const mounted: Record<string, HTMLElement> = {};
const cleanups: Record<string, (() => void) | void> = {};

function applyHost(entry: SlotEntry, el: HTMLElement | null) {
  if (!el || mounted[entry.id] === el) return;
  mounted[entry.id] = el;
  if (entry.html != null) {
    el.innerHTML = entry.html;
  } else if (entry.mount) {
    cleanups[entry.id] = entry.mount(el, { name: props.name, props: props.ctx ?? {} });
  }
}

// Tear down entries that were unregistered.
watch(entries, (list) => {
  const ids = new Set(list.map((e) => e.id));
  Object.keys(cleanups).forEach((id) => {
    if (!ids.has(id)) {
      try { cleanups[id]?.(); } catch { /* ignore */ }
      delete cleanups[id];
      delete mounted[id];
    }
  });
});

onBeforeUnmount(() => {
  Object.values(cleanups).forEach((fn) => { try { fn?.(); } catch { /* ignore */ } });
});
</script>

<template>
  <template v-for="entry in entries" :key="entry.id">
    <component :is="entry.component" v-if="entry.component" v-bind="ctx ?? {}" />
    <div v-else :data-convoro-ext="entry.ext || ''" :ref="(el) => applyHost(entry, el as HTMLElement | null)" />
  </template>
</template>
