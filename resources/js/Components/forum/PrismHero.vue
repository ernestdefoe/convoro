<script setup lang="ts">
import { computed } from 'vue';

// Prism hero — the customizable, animated banner that fronts the home page and
// each space (category today, tag tomorrow). Driven entirely by a `config`
// object so it stays fully data-driven: palette, Font Awesome icon, cover image,
// title/subtitle and stats all come from the server. No per-space CSS.
const props = defineProps<{
  config: {
    title: string;
    subtitle?: string | null;
    icon?: string | null;          // Font Awesome class, e.g. 'fa-solid fa-meteor'
    c1?: string | null;            // palette primary
    c2?: string | null;            // palette secondary (derived if absent)
    image?: string | null;         // optional cover image url
    stats?: { label: string; value: string; icon?: string }[];
  };
}>();

function mix(a: string, b: string, t: number): string {
  const pa = a.replace('#', ''), pb = b.replace('#', '');
  const ai = [0, 2, 4].map((i) => parseInt(pa.substr(i, 2), 16));
  const bi = [0, 2, 4].map((i) => parseInt(pb.substr(i, 2), 16));
  const ci = ai.map((v, i) => Math.round(v + (bi[i] - v) * t));
  return '#' + ci.map((v) => v.toString(16).padStart(2, '0')).join('');
}

const c1 = computed(() => props.config.c1 || '#7c3aed');
const c2 = computed(() => props.config.c2 || mix(c1.value, '#8b5cf6', 0.45));
const grad = computed(() => `linear-gradient(135deg, ${c1.value}, ${c2.value})`);
const icon = computed(() => props.config.icon || 'fa-solid fa-meteor');
</script>

<template>
  <div class="prism-hero relative overflow-hidden rounded-c border border-white/10" :style="{ background: config.image ? '#0b0a14' : grad }">
    <img v-if="config.image" :src="config.image" alt="" class="absolute inset-0 h-full w-full object-cover" />
    <span class="ph-blob" :style="{ background: `radial-gradient(circle, ${c1}, transparent 70%)`, left: '-50px', top: '-80px' }" aria-hidden="true"></span>
    <span class="ph-blob" :style="{ background: `radial-gradient(circle, ${c2}, transparent 70%)`, right: '-40px', top: '-50px', animationDuration: '11s', animationDelay: '-3s' }" aria-hidden="true"></span>
    <span class="ph-sheen" aria-hidden="true"></span>
    <span class="absolute inset-0" style="background: radial-gradient(130% 130% at 82% 0, rgba(255,255,255,.22), transparent 55%)" aria-hidden="true"></span>
    <span class="absolute inset-0" style="background: linear-gradient(to top, rgba(0,0,0,.38), transparent 62%)" aria-hidden="true"></span>

    <div class="relative z-10 flex items-center gap-4 p-6 sm:gap-5 sm:p-8">
      <div class="grid h-16 w-16 shrink-0 place-items-center rounded-2xl text-2xl text-white shadow-lg" style="background: rgba(255,255,255,.18); backdrop-filter: blur(8px)">
        <i :class="icon" aria-hidden="true"></i>
      </div>
      <div class="min-w-0 flex-1">
        <h1 class="truncate text-3xl font-extrabold tracking-tight text-white drop-shadow-sm sm:text-4xl">{{ config.title }}</h1>
        <p v-if="config.subtitle" class="mt-1 line-clamp-1 text-white/80">{{ config.subtitle }}</p>
      </div>
      <div v-if="config.stats?.length" class="hidden shrink-0 gap-5 pr-1 sm:flex">
        <div v-for="(s, i) in config.stats" :key="i" class="flex flex-col items-center gap-1.5">
          <div class="text-xl font-extrabold leading-none text-white sm:text-2xl">{{ s.value }}</div>
          <i v-if="s.icon" :class="s.icon" class="text-[13px] text-white/70" :title="s.label" :aria-label="s.label"></i>
          <span v-else class="text-xs text-white/70">{{ s.label }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.prism-hero { min-height: 148px; }
.ph-blob {
  position: absolute; width: 240px; height: 240px; border-radius: 50%;
  filter: blur(42px); opacity: .8; pointer-events: none;
  animation: phDrift 9s ease-in-out infinite;
}
.ph-sheen {
  position: absolute; width: 540px; height: 540px; left: 50%; top: 50%;
  border-radius: 50%; pointer-events: none;
  background: conic-gradient(from 0deg, transparent, rgba(255, 255, 255, .07), transparent 40%);
  animation: phSpin 20s linear infinite;
}
@keyframes phDrift { 0%, 100% { transform: translate(0, 0) scale(1); } 50% { transform: translate(28px, -22px) scale(1.18); } }
@keyframes phSpin { to { transform: translate(-50%, -50%) rotate(360deg); } }
@media (prefers-reduced-motion: reduce) { .ph-blob, .ph-sheen { animation: none; } }
</style>
