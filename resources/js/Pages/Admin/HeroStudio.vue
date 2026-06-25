<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import PrismHero from '@/Components/forum/PrismHero.vue';
import { t as tr } from '@/lib/i18n';

const props = defineProps<{
  home: { title: string; subtitle: string; icon: string; c1: string; c2: string; image: string | null };
  tags: { id: number; name: string; isSub: boolean; config: any }[];
}>();

const ICONS = [
  'fa-solid fa-meteor', 'fa-solid fa-gamepad', 'fa-solid fa-microchip', 'fa-solid fa-palette',
  'fa-solid fa-music', 'fa-solid fa-trophy', 'fa-solid fa-rocket', 'fa-solid fa-camera',
  'fa-solid fa-fire', 'fa-solid fa-bolt', 'fa-solid fa-heart', 'fa-solid fa-code',
  'fa-solid fa-hashtag', 'fa-solid fa-star', 'fa-solid fa-comments', 'fa-solid fa-bullhorn',
  'fa-solid fa-futbol', 'fa-solid fa-flask', 'fa-solid fa-book', 'fa-solid fa-globe',
];
const PALETTES = [
  ['#7c3aed', '#ec4899'], ['#22d3ee', '#3b82f6'], ['#f59e0b', '#f43f5e'],
  ['#34d399', '#06b6d4'], ['#fb7185', '#f97316'], ['#a78bfa', '#60a5fa'],
];

const targets = computed(() => [
  { key: 'home', label: tr('Home'), isSub: false, config: { ...props.home } },
  ...props.tags.map((t) => ({ key: String(t.id), label: t.name, isSub: t.isSub, config: { ...t.config, title: t.name } })),
]);

const selected = ref('home');
const isHome = computed(() => selected.value === 'home');
const editing = reactive<any>({ title: '', subtitle: '', icon: '', c1: '#7c3aed', c2: '#ec4899', image: null });
const saving = ref(false);
const uploading = ref(false);

function select(key: string) {
  selected.value = key;
  const tgt = targets.value.find((t) => t.key === key);
  Object.assign(editing, { title: '', subtitle: '', icon: 'fa-solid fa-hashtag', c1: '#7c3aed', c2: '#ec4899', image: null }, tgt?.config || {});
}
select('home');

const preview = computed(() => ({
  title: editing.title || tr('Untitled'),
  subtitle: editing.subtitle,
  icon: editing.icon,
  c1: editing.c1,
  c2: editing.c2,
  image: editing.image,
  stats: [{ label: tr('topics'), value: '128' }, { label: tr('members'), value: '4.2k' }],
}));

function csrf() {
  return (document.querySelector('meta[name=csrf-token]') as HTMLMetaElement)?.content || '';
}

async function onFile(e: Event) {
  const f = (e.target as HTMLInputElement).files?.[0];
  if (!f) return;
  uploading.value = true;
  const fd = new FormData();
  fd.append('file', f);
  try {
    const r = await fetch('/uploads/image', { method: 'POST', body: fd, headers: { 'X-CSRF-TOKEN': csrf(), Accept: 'application/json' } });
    const d = await r.json();
    if (d.url) editing.image = d.url;
  } finally {
    uploading.value = false;
  }
}

function save() {
  saving.value = true;
  router.post('/admin/heroes', {
    target: selected.value,
    title: editing.title,
    subtitle: editing.subtitle,
    icon: editing.icon,
    c1: editing.c1,
    c2: editing.c2,
    image: editing.image,
  }, { preserveScroll: true, preserveState: true, onFinish: () => (saving.value = false) });
}
</script>

<template>
  <Head title="Hero Studio" />
  <AdminLayout>
    <div class="mb-5 flex items-center gap-3">
      <h1 class="text-2xl font-extrabold tracking-tight">{{ tr('Hero Studio') }}</h1>
      <span class="rounded-full bg-primary/15 px-2.5 py-0.5 text-xs font-bold text-primary">{{ tr('Prism') }}</span>
      <button type="button" :disabled="saving" class="ml-auto rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-primary/30 hover:bg-primary-600 disabled:opacity-60" @click="save">
        {{ saving ? tr('Saving…') : tr('Save hero') }}
      </button>
    </div>

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-[200px_1fr]">
      <!-- Target list -->
      <aside class="rounded-c border border-line bg-surface p-2">
        <p class="px-2 pb-1.5 pt-1 text-[11px] font-bold uppercase tracking-wide text-ink-muted">{{ tr('Spaces') }}</p>
        <button v-for="t in targets" :key="t.key" type="button" @click="select(t.key)"
          class="flex w-full items-center gap-2 rounded-lg px-3 py-2 text-left text-sm font-semibold transition"
          :class="[selected === t.key ? 'bg-primary/15 text-primary' : 'text-ink-2 hover:bg-surface-2', t.isSub ? 'pl-6' : '']">
          <i :class="t.key === 'home' ? 'fa-solid fa-house' : (t.config.icon || 'fa-solid fa-hashtag')" class="w-4 text-[12px]" aria-hidden="true"></i>
          <span class="truncate">{{ t.label }}</span>
        </button>
      </aside>

      <div class="grid grid-cols-1 gap-5 xl:grid-cols-[1fr_320px]">
        <!-- Live preview -->
        <div>
          <p class="mb-2 text-[11px] font-bold uppercase tracking-wide text-ink-muted">{{ tr('Live preview') }}</p>
          <PrismHero :config="preview" />
        </div>

        <!-- Controls -->
        <div class="flex flex-col gap-5 rounded-c border border-line bg-surface p-4">
          <label class="block">
            <span class="mb-1.5 block text-[11px] font-bold uppercase tracking-wide text-ink-muted">{{ isHome ? tr('Community name') : tr('Title') }}</span>
            <input v-model="editing.title" :disabled="!isHome" type="text" class="w-full rounded-lg border-line bg-surface-2 text-sm focus:border-primary focus:ring-primary disabled:opacity-60" />
          </label>
          <label class="block">
            <span class="mb-1.5 block text-[11px] font-bold uppercase tracking-wide text-ink-muted">{{ tr('Subtitle') }}</span>
            <input v-model="editing.subtitle" type="text" class="w-full rounded-lg border-line bg-surface-2 text-sm focus:border-primary focus:ring-primary" />
          </label>

          <div>
            <span class="mb-2 block text-[11px] font-bold uppercase tracking-wide text-ink-muted">{{ tr('Icon') }} · Font Awesome</span>
            <div class="mb-2 flex items-center gap-2">
              <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg border border-line bg-surface-2 text-base text-ink">
                <i v-if="editing.icon" :class="editing.icon" aria-hidden="true"></i>
                <i v-else class="fa-regular fa-circle-question text-ink-muted" aria-hidden="true"></i>
              </span>
              <input v-model.trim="editing.icon" type="text" spellcheck="false"
                class="w-full rounded-lg border-line bg-appbg font-mono text-xs text-ink focus:border-indigo-500 focus:ring-indigo-500"
                :placeholder="tr('Type any Font Awesome class, e.g. fa-solid fa-dragon')" />
            </div>
            <span class="mb-1.5 block text-[10px] uppercase tracking-wide text-ink-muted/80">{{ tr('Quick picks') }}</span>
            <div class="grid grid-cols-8 gap-1.5">
              <button v-for="ic in ICONS" :key="ic" type="button" @click="editing.icon = ic"
                class="grid aspect-square place-items-center rounded-lg border text-sm transition"
                :class="editing.icon === ic ? 'border-primary bg-primary/15 text-primary' : 'border-line bg-surface-2 text-ink-2 hover:text-ink'">
                <i :class="ic" aria-hidden="true"></i>
              </button>
            </div>
          </div>

          <div>
            <span class="mb-2 block text-[11px] font-bold uppercase tracking-wide text-ink-muted">{{ tr('Palette') }}</span>
            <div class="mb-2 flex flex-wrap gap-1.5">
              <button v-for="p in PALETTES" :key="p[0]" type="button" @click="editing.c1 = p[0]; editing.c2 = p[1]"
                class="h-7 w-9 rounded-lg border-2 transition hover:scale-110"
                :class="editing.c1 === p[0] && editing.c2 === p[1] ? 'border-ink' : 'border-line'"
                :style="{ background: `linear-gradient(135deg, ${p[0]}, ${p[1]})` }" :aria-label="`${p[0]} ${p[1]}`"></button>
            </div>
            <div class="flex gap-2">
              <label class="flex flex-1 items-center gap-2 rounded-lg border border-line bg-surface-2 px-2 py-1.5">
                <input v-model="editing.c1" type="color" class="h-6 w-6 cursor-pointer rounded border-0 bg-transparent p-0" />
                <span class="text-xs text-ink-2">{{ editing.c1 }}</span>
              </label>
              <label class="flex flex-1 items-center gap-2 rounded-lg border border-line bg-surface-2 px-2 py-1.5">
                <input v-model="editing.c2" type="color" class="h-6 w-6 cursor-pointer rounded border-0 bg-transparent p-0" />
                <span class="text-xs text-ink-2">{{ editing.c2 }}</span>
              </label>
            </div>
          </div>

          <div>
            <span class="mb-2 block text-[11px] font-bold uppercase tracking-wide text-ink-muted">{{ tr('Background') }}</span>
            <div v-if="editing.image" class="flex items-center gap-2">
              <img :src="editing.image" alt="" class="h-12 w-20 rounded-lg object-cover" />
              <button type="button" class="rounded-lg border border-line px-3 py-1.5 text-xs font-semibold text-ink-2 hover:text-ink" @click="editing.image = null">{{ tr('Remove image') }}</button>
            </div>
            <label v-else class="flex cursor-pointer items-center justify-center gap-2 rounded-lg border border-dashed border-line bg-surface-2 px-3 py-3 text-sm text-ink-2 hover:text-ink">
              <i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i>
              <span>{{ uploading ? tr('Uploading…') : tr('Upload cover image') }}</span>
              <input type="file" accept="image/*" class="hidden" @change="onFile" />
            </label>
            <p class="mt-1.5 text-xs text-ink-muted">{{ tr('No image = animated gradient hero.') }}</p>
          </div>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
