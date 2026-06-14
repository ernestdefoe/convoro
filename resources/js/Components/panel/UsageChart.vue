<script setup lang="ts">
import { computed, ref } from 'vue';

type Point = { t: string; value: number };
const props = withDefaults(defineProps<{
  points: Point[];
  label: string;
  kind?: 'bytes' | 'number' | 'ms' | 'percent';
  color?: string;
  height?: number;
}>(), { kind: 'number', color: '#5b5bd6', height: 150 });

const W = 600;
const H = computed(() => props.height);
const PAD = { l: 8, r: 8, t: 12, b: 4 };

const vals = computed(() => props.points.map((p) => p.value));
const maxV = computed(() => Math.max(1, ...vals.value));
const minV = computed(() => Math.min(0, ...vals.value));

function x(i: number) {
  const n = props.points.length;
  if (n <= 1) return PAD.l;
  return PAD.l + (i / (n - 1)) * (W - PAD.l - PAD.r);
}
function y(v: number) {
  const span = maxV.value - minV.value || 1;
  return PAD.t + (1 - (v - minV.value) / span) * (H.value - PAD.t - PAD.b);
}

const linePath = computed(() => props.points.map((p, i) => `${i === 0 ? 'M' : 'L'}${x(i).toFixed(1)},${y(p.value).toFixed(1)}`).join(' '));
const areaPath = computed(() => {
  if (!props.points.length) return '';
  const top = props.points.map((p, i) => `${i === 0 ? 'M' : 'L'}${x(i).toFixed(1)},${y(p.value).toFixed(1)}`).join(' ');
  return `${top} L${x(props.points.length - 1).toFixed(1)},${(H.value - PAD.b).toFixed(1)} L${x(0).toFixed(1)},${(H.value - PAD.b).toFixed(1)} Z`;
});

function fmt(v: number): string {
  if (props.kind === 'bytes') {
    const u = ['B', 'KB', 'MB', 'GB', 'TB'];
    let i = 0; let n = v;
    while (n >= 1024 && i < u.length - 1) { n /= 1024; i++; }
    return `${n.toFixed(n < 10 && i > 0 ? 1 : 0)} ${u[i]}`;
  }
  if (props.kind === 'ms') return `${Math.round(v)} ms`;
  if (props.kind === 'percent') return `${v.toFixed(0)}%`;
  if (v >= 1000) return `${(v / 1000).toFixed(v < 10000 ? 1 : 0)}k`;
  return `${Math.round(v)}`;
}

const last = computed(() => (vals.value.length ? vals.value[vals.value.length - 1] : 0));
const uid = Math.round(x(0) * 1000) + props.label.length; // stable-ish gradient id per chart

const hover = ref<number | null>(null);
function onMove(e: MouseEvent) {
  const svg = (e.currentTarget as SVGElement).getBoundingClientRect();
  const rel = (e.clientX - svg.left) / svg.width;
  hover.value = Math.max(0, Math.min(props.points.length - 1, Math.round(rel * (props.points.length - 1))));
}
const hoverLabel = computed(() => {
  if (hover.value == null || !props.points[hover.value]) return null;
  const p = props.points[hover.value];
  const d = new Date(p.t);
  return { value: fmt(p.value), when: d.toLocaleString(undefined, { month: 'short', day: 'numeric', hour: 'numeric' }), x: x(hover.value), y: y(p.value) };
});
</script>

<template>
  <div>
    <div class="mb-1 flex items-baseline justify-between">
      <span class="text-xs font-semibold text-ink-muted">{{ label }}</span>
      <span class="text-sm font-bold text-ink">{{ fmt(last) }}<span class="ml-1 text-xs font-normal text-ink-muted">now</span></span>
    </div>
    <svg :viewBox="`0 0 ${W} ${H}`" class="w-full" preserveAspectRatio="none" :style="{ height: H + 'px' }" @mousemove="onMove" @mouseleave="hover = null">
      <defs>
        <linearGradient :id="`g${uid}`" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" :stop-color="color" stop-opacity="0.28" />
          <stop offset="100%" :stop-color="color" stop-opacity="0" />
        </linearGradient>
      </defs>
      <!-- gridlines -->
      <line v-for="g in [0.25, 0.5, 0.75]" :key="g" :x1="PAD.l" :x2="W - PAD.r" :y1="PAD.t + g * (H - PAD.t - PAD.b)" :y2="PAD.t + g * (H - PAD.t - PAD.b)" stroke="currentColor" class="text-line" stroke-width="1" stroke-dasharray="3 4" opacity="0.5" />
      <path v-if="points.length" :d="areaPath" :fill="`url(#g${uid})`" />
      <path v-if="points.length" :d="linePath" :stroke="color" stroke-width="2" fill="none" vector-effect="non-scaling-stroke" stroke-linejoin="round" />
      <g v-if="hoverLabel">
        <line :x1="hoverLabel.x" :x2="hoverLabel.x" :y1="PAD.t" :y2="H - PAD.b" :stroke="color" stroke-width="1" opacity="0.4" />
        <circle :cx="hoverLabel.x" :cy="hoverLabel.y" r="3.5" :fill="color" />
      </g>
      <text v-if="!points.length" :x="W / 2" :y="H / 2" text-anchor="middle" class="fill-current text-ink-muted" font-size="13">no data yet</text>
    </svg>
    <div v-if="hoverLabel" class="mt-0.5 text-right text-[11px] text-ink-muted">{{ hoverLabel.value }} · {{ hoverLabel.when }}</div>
  </div>
</template>
