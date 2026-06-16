<script setup lang="ts">
import { computed } from 'vue';
import { coverGradient as gradientFor, coverBg as coverBgFor, stripIconBg } from '@/lib/accent';

// The shared cover artwork used by every product surface (marketplace cards,
// directory cards, installed-extension cards). A designed accent cover (or a
// genuine custom screenshot) with the icon rendered as a uniform white
// silhouette on the accent tile.
const props = defineProps<{
  name: string;
  type?: string | null;
  image?: string | null;      // a real custom screenshot (callers pass realScreenshot())
  icon?: string | null;       // inline SVG
  accentKey?: string | null;
}>();

const key = computed(() => String(props.accentKey ?? props.name ?? ''));
const coverGradient = computed(() => gradientFor(key.value));
const coverBg = computed(() => coverBgFor(key.value));
const glyph = computed(() => stripIconBg(props.icon));
</script>

<template>
  <div class="relative">
    <img v-if="image" :src="image" :alt="name" loading="lazy" class="aspect-[2/1] w-full object-cover" />
    <div v-else class="relative flex aspect-[2/1] w-full flex-col items-center justify-center overflow-hidden px-4 text-center" :style="{ background: coverBg }">
      <span class="bg-clip-text text-2xl font-black tracking-tight text-transparent drop-shadow-sm sm:text-3xl" :style="{ backgroundImage: coverGradient, WebkitBackgroundClip: 'text' }">{{ name }}</span>
      <span v-if="type" class="mt-1 text-[11px] font-semibold uppercase tracking-[0.15em] text-white/55">{{ type }}</span>
    </div>
    <span class="absolute left-3 top-3 grid h-11 w-11 place-items-center overflow-hidden rounded-xl shadow-md ring-1 ring-black/10" :style="{ backgroundImage: coverGradient }">
      <span v-if="glyph" v-html="glyph" class="grid h-6 w-6 place-items-center [&>svg]:h-full [&>svg]:w-full [&>svg]:[filter:brightness(0)_invert(1)]"></span>
      <span v-else class="text-lg font-black text-white">{{ (name || '?').charAt(0).toUpperCase() }}</span>
    </span>
  </div>
</template>
