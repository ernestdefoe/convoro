<script setup lang="ts">
import { computed, ref, onMounted, watch } from 'vue';

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

// Stats count-up — client only (onMounted is skipped during SSR, so the server
// renders the final values and the browser animates up from 0 on mount).
const shown = ref<string[]>((props.config.stats || []).map((s) => s.value));
function parseStat(v: string) {
  const m = String(v).match(/^([\d.]+)(.*)$/);
  if (!m) return { target: 0, suffix: String(v), decimals: 0 };
  return { target: parseFloat(m[1]), suffix: m[2], decimals: (m[1].split('.')[1] || '').length };
}
function countUp() {
  const stats = props.config.stats || [];
  shown.value = stats.map(() => '0');
  stats.forEach((s, i) => {
    const { target, suffix, decimals } = parseStat(s.value);
    const dur = 1000, delay = i * 130, t0 = performance.now();
    const step = (now: number) => {
      const t = Math.min(1, Math.max(0, (now - t0 - delay) / dur));
      const eased = 1 - Math.pow(1 - t, 3);
      shown.value[i] = t < 1 ? (target * eased).toFixed(decimals) + (t > 0 ? suffix : '') : target.toFixed(decimals) + suffix;
      if (t < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
  });
}
onMounted(countUp);
watch(() => props.config.stats, countUp);
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
      <div v-if="config.stats?.length" class="ph-stats hidden shrink-0 gap-2.5 pr-1 sm:flex">
        <div v-for="(s, i) in config.stats" :key="i" class="ph-stat" :style="{ animationDelay: (0.15 + i * 0.1) + 's' }">
          <span v-if="s.icon" class="ph-stat-ico" :title="s.label" :aria-label="s.label"><i :class="s.icon" aria-hidden="true"></i></span>
          <span class="ph-stat-num">{{ shown[i] ?? s.value }}</span>
          <span v-if="!s.icon" class="ph-stat-lbl">{{ s.label }}</span>
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

/* Dynamic, "popping" stats: each chip springs in, the number counts up (JS),
   the icon badge pulses a glow, and hover lifts + brightens. */
.ph-stat {
  display: flex; flex-direction: column; align-items: center; gap: .4rem;
  min-width: 68px; padding: .65rem .95rem;
  border-radius: 1rem; border: 1px solid rgba(255, 255, 255, .2);
  background: rgba(255, 255, 255, .12);
  -webkit-backdrop-filter: blur(8px); backdrop-filter: blur(8px);
  box-shadow: 0 10px 28px -10px rgba(0, 0, 0, .4), inset 0 1px 0 rgba(255, 255, 255, .3);
  transform-origin: bottom center;
  animation: phStatIn .7s cubic-bezier(.18, .9, .25, 1.4) both;
  transition: transform .25s ease, background .25s ease, box-shadow .25s ease;
}
.ph-stat:hover {
  transform: translateY(-4px) scale(1.05);
  background: rgba(255, 255, 255, .22);
  box-shadow: 0 18px 34px -10px rgba(0, 0, 0, .45), inset 0 1px 0 rgba(255, 255, 255, .4);
}
@keyframes phStatIn { from { opacity: 0; transform: translateY(16px) scale(.8); } to { opacity: 1; transform: none; } }

.ph-stat-ico {
  display: grid; place-items: center; height: 1.95rem; width: 1.95rem;
  border-radius: 9999px; color: #fff; font-size: .82rem;
  background: rgba(255, 255, 255, .28);
  animation: phPulse 2.6s ease-in-out infinite;
}
@keyframes phPulse {
  0%, 100% { box-shadow: inset 0 1px 1px rgba(255, 255, 255, .55), 0 0 0 0 rgba(255, 255, 255, .0); transform: scale(1); }
  50% { box-shadow: inset 0 1px 1px rgba(255, 255, 255, .55), 0 0 16px 3px rgba(255, 255, 255, .3); transform: scale(1.08); }
}

.ph-stat-num {
  font-variant-numeric: tabular-nums; font-weight: 900; letter-spacing: -.02em;
  font-size: 1.7rem; line-height: 1; color: #fff;
  text-shadow: 0 2px 14px rgba(0, 0, 0, .35), 0 0 20px rgba(255, 255, 255, .3);
}
@media (min-width: 640px) { .ph-stat-num { font-size: 1.9rem; } }
.ph-stat-lbl { font-size: .7rem; font-weight: 600; color: rgba(255, 255, 255, .82); }

@media (prefers-reduced-motion: reduce) {
  .ph-blob, .ph-sheen, .ph-stat, .ph-stat-ico { animation: none; }
}
</style>
