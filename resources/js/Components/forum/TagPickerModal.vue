<script setup lang="ts">
import TagChip from '@/Components/forum/TagChip.vue';
import { t as tr } from '@/lib/i18n';
import { computed, ref, watch, nextTick } from 'vue';

type Tag = { id: number; name: string; icon?: string | null; color?: string | null };
type PrimaryTag = Tag & { children?: Tag[] };

const props = defineProps<{
  open: boolean;
  tags: PrimaryTag[];
  modelValue: number[];
  tagRules?: { minPrimary: number; maxPrimary: number };
}>();

const emit = defineEmits<{ (e: 'update:modelValue', v: number[]): void; (e: 'close'): void }>();

const selected = ref<number[]>([]);
const filter = ref('');
const searchEl = ref<HTMLInputElement | null>(null);

watch(
  () => props.open,
  (open) => {
    if (open) {
      selected.value = [...props.modelValue];
      filter.value = '';
      nextTick(() => searchEl.value?.focus());
    }
  }
);

const primaryIds = computed(() => new Set(props.tags.map((t) => t.id)));
const byId = computed(() => {
  const m: Record<number, Tag> = {};
  for (const t of props.tags) {
    m[t.id] = t;
    for (const c of t.children || []) m[c.id] = c;
  }
  return m;
});
const selectedTags = computed(() => selected.value.map((id) => byId.value[id]).filter(Boolean));

// Flatten to display rows: each primary followed by its children (child styling).
const rows = computed(() => {
  const q = filter.value.trim().toLowerCase();
  const out: (Tag & { child: boolean })[] = [];
  for (const t of props.tags) {
    const kids = (t.children || []).filter((c) => !q || c.name.toLowerCase().includes(q));
    const match = !q || t.name.toLowerCase().includes(q);
    if (match || kids.length) out.push({ ...t, child: false });
    for (const c of kids) out.push({ ...c, child: true });
  }
  return out;
});

const primarySelected = computed(() => selected.value.filter((id) => primaryIds.value.has(id)).length);
const min = computed(() => props.tagRules?.minPrimary || 0);
const max = computed(() => props.tagRules?.maxPrimary || 0);
const error = computed(() => {
  const n = primarySelected.value;
  if (min.value && n < min.value) return tr('Choose at least {n} primary tag(s).', { n: min.value });
  if (max.value && n > max.value) return tr('Choose at most {n} primary tag(s).', { n: max.value });
  return '';
});
const hint = computed(() => {
  if (min.value && max.value) return min.value === max.value ? tr('Pick exactly {n} primary tag(s).', { n: min.value }) : tr('Pick {min}–{max} primary tags.', { min: min.value, max: max.value });
  if (min.value) return tr('Pick at least {n} primary tag(s).', { n: min.value });
  if (max.value) return tr('Pick up to {n} primary tag(s).', { n: max.value });
  return tr('Pick the space(s) this belongs in.');
});

function isSelected(id: number) {
  return selected.value.includes(id);
}
function toggle(id: number) {
  const i = selected.value.indexOf(id);
  if (i === -1) {
    // enforce the primary maximum as the user clicks (Flarum-style)
    if (max.value && primaryIds.value.has(id) && primarySelected.value >= max.value) return;
    selected.value.push(id);
  } else {
    selected.value.splice(i, 1);
  }
}
function done() {
  if (error.value) return;
  emit('update:modelValue', [...selected.value]);
  emit('close');
}
</script>

<template>
  <Transition enter-active-class="transition duration-150 ease-out" enter-from-class="opacity-0" leave-active-class="transition duration-100 ease-in" leave-to-class="opacity-0">
    <div v-if="open" class="fixed inset-0 z-[90] flex items-center justify-center p-4" @keydown.esc="emit('close')">
      <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="emit('close')"></div>

      <div class="relative flex max-h-[80vh] w-full max-w-lg flex-col overflow-hidden rounded-c border border-line bg-surface shadow-2xl">
        <!-- header -->
        <div class="flex items-center justify-between border-b border-line px-5 py-3.5">
          <h3 class="text-base font-extrabold tracking-tight text-ink">{{ tr('Choose tags') }}</h3>
          <button type="button" class="text-ink-muted hover:text-ink" :aria-label="tr('Close')" @click="emit('close')">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12" /></svg>
          </button>
        </div>

        <!-- selected chips + search -->
        <div class="border-b border-line px-5 py-3">
          <div v-if="selectedTags.length" class="mb-2.5 flex flex-wrap gap-1.5">
            <TagChip v-for="t in selectedTags" :key="t.id" :tag="t" removable size="sm" @remove="toggle(t.id)" />
          </div>
          <input
            ref="searchEl"
            v-model="filter"
            type="text"
            :placeholder="tr('Search tags…')"
            class="w-full rounded-lg border-line bg-surface-2 text-sm text-ink placeholder:text-ink-muted focus:border-primary focus:ring-primary"
          />
        </div>

        <!-- tag list -->
        <ul class="min-h-0 flex-1 overflow-y-auto py-1.5">
          <li v-for="row in rows" :key="row.id">
            <button
              type="button"
              class="flex w-full items-center gap-3 border-l-2 px-5 py-2 text-left transition hover:bg-surface-2"
              :class="[row.child ? 'pl-9' : '', isSelected(row.id) ? 'bg-surface-2' : '']"
              :style="{ borderLeftColor: isSelected(row.id) ? (row.color || '#5b5bd6') : 'transparent' }"
              @click="toggle(row.id)"
            >
              <span class="grid h-5 w-5 shrink-0 place-items-center" :style="{ color: row.color || '#5b5bd6' }">
                <i v-if="row.icon && row.icon.includes('fa-')" :class="row.icon" aria-hidden="true"></i>
                <span v-else class="h-2.5 w-2.5 rounded-full" :style="{ background: row.color || '#5b5bd6' }"></span>
              </span>
              <span class="flex-1 text-sm font-semibold text-ink">{{ row.name }}</span>
              <svg v-if="isSelected(row.id)" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="shrink-0 text-primary" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
            </button>
          </li>
          <li v-if="!rows.length" class="px-5 py-6 text-center text-sm text-ink-muted">{{ tr('No tags found.') }}</li>
        </ul>

        <!-- footer -->
        <div class="flex items-center justify-between gap-3 border-t border-line px-5 py-3">
          <p class="text-xs" :class="error ? 'text-red-400' : 'text-ink-muted'">{{ error || hint }}</p>
          <button
            type="button"
            :disabled="!!error"
            class="rounded-c bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-600 disabled:opacity-50"
            @click="done"
          >
            {{ tr('Done') }}
          </button>
        </div>
      </div>
    </div>
  </Transition>
</template>
