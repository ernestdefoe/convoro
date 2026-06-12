<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';

type Pair = { label: string; fg: string; bg: string; min: number };

// Token pairs audited against the LIVE theme (read from computed CSS vars).
const PAIRS: Pair[] = [
  { label: t('Body text on background'), fg: '--c-text', bg: '--c-bg', min: 4.5 },
  { label: t('Body text on cards'), fg: '--c-text', bg: '--c-surface', min: 4.5 },
  { label: t('Secondary text on cards'), fg: '--c-text-2', bg: '--c-surface', min: 4.5 },
  { label: t('Muted text on background'), fg: '--c-muted', bg: '--c-bg', min: 3 },
  { label: t('White text on brand buttons'), fg: '#fff', bg: '--c-primary', min: 4.5 },
  { label: t('Links/badges (brand on soft)'), fg: '--c-primary-700', bg: '--c-primary-soft', min: 4.5 },
];

const guidelines = [
  { label: t('Keyboard navigation with visible focus rings'), status: 'pass' },
  { label: t('Semantic landmarks (header, nav, main)'), status: 'pass' },
  { label: t('Skip-to-content link'), status: 'pass' },
  { label: t('All form fields have labels'), status: 'pass' },
  { label: t('Light and dark themes available'), status: 'pass' },
  { label: t('Respects “reduce motion” preference'), status: 'pass' },
  { label: t('Alt text captured on uploaded images'), status: 'todo' },
  { label: t('Full screen-reader audit (ARIA roles)'), status: 'partial' },
];

const results = ref<{ label: string; ratio: number; min: number; ok: boolean }[]>([]);

function rgb(token: string): number[] {
  if (token === '#fff') return [255, 255, 255];
  const v = getComputedStyle(document.documentElement).getPropertyValue(token).trim();
  const parts = v.split(/\s+/).map(Number);
  return parts.length === 3 && parts.every((n) => !Number.isNaN(n)) ? parts : [0, 0, 0];
}
function luminance(c: number[]): number {
  const a = c.map((v) => {
    const s = v / 255;
    return s <= 0.03928 ? s / 12.92 : Math.pow((s + 0.055) / 1.055, 2.4);
  });
  return 0.2126 * a[0] + 0.7152 * a[1] + 0.0722 * a[2];
}
function ratio(fg: string, bg: string): number {
  const l1 = luminance(rgb(fg));
  const l2 = luminance(rgb(bg));
  const [hi, lo] = l1 > l2 ? [l1, l2] : [l2, l1];
  return (hi + 0.05) / (lo + 0.05);
}

onMounted(() => {
  results.value = PAIRS.map((p) => {
    const r = ratio(p.fg, p.bg);
    return { label: p.label, ratio: Math.round(r * 100) / 100, min: p.min, ok: r >= p.min };
  });
});

const score = computed(() => {
  const contrastPass = results.value.filter((r) => r.ok).length;
  const contrastTotal = results.value.length || 1;
  const guidePass = guidelines.filter((g) => g.status === 'pass').length;
  const guidePartial = guidelines.filter((g) => g.status === 'partial').length;
  const passed = contrastPass + guidePass + guidePartial * 0.5;
  const total = contrastTotal + guidelines.length;
  return Math.round((passed / total) * 100);
});

const grade = computed(() => (score.value >= 90 ? t('Excellent') : score.value >= 75 ? t('Good') : score.value >= 50 ? t('Fair') : t('Needs work')));
const ring = computed(() => `conic-gradient(rgb(16 185 129) ${score.value * 3.6}deg, rgba(255,255,255,0.08) 0deg)`);

const statusStyle = (s: string) =>
  s === 'pass' ? 'bg-emerald-500/20 text-emerald-400' : s === 'partial' ? 'bg-amber-500/20 text-amber-400' : 'bg-slate-500/20 text-ink-2';
const statusLabel = (s: string) => (s === 'pass' ? t('Pass') : s === 'partial' ? t('Partial') : t('To do'));
</script>

<template>
  <Head :title="t('Admin · Accessibility')" />
  <AdminLayout>
    <template #title>{{ t('Accessibility') }}</template>
    <template #subtitle>{{ t('WCAG / ADA compliance for your live theme') }}</template>

    <div class="grid gap-6 lg:grid-cols-[300px_1fr]">
      <!-- Score -->
      <div class="rounded-2xl border border-line bg-surface p-6 text-center">
        <div class="relative mx-auto h-40 w-40">
          <div class="h-40 w-40 rounded-full" :style="{ background: ring }"></div>
          <div class="absolute inset-2 flex flex-col items-center justify-center rounded-full bg-surface">
            <span class="text-4xl font-extrabold text-ink">{{ score }}%</span>
            <span class="text-sm text-ink-2">{{ grade }}</span>
          </div>
        </div>
        <p class="mt-4 text-sm text-ink-2">{{ t('Compliance score across contrast + best practices. Adjust theme colors to improve contrast.') }}</p>
      </div>

      <div class="space-y-6">
        <!-- Contrast audit -->
        <section class="rounded-2xl border border-line bg-surface p-5">
          <h3 class="mb-3 text-sm font-bold text-ink">{{ t('Color contrast (live theme)') }}</h3>
          <ul class="divide-y divide-line">
            <li v-for="r in results" :key="r.label" class="flex items-center justify-between py-2.5 text-sm">
              <span class="text-ink-2">{{ r.label }}</span>
              <span class="flex items-center gap-3">
                <span class="font-mono text-ink-2">{{ r.ratio }}:1</span>
                <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="r.ok ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400'">
                  {{ r.ok ? t('AA') : t('Fail') }}
                </span>
              </span>
            </li>
          </ul>
        </section>

        <!-- Best practices -->
        <section class="rounded-2xl border border-line bg-surface p-5">
          <h3 class="mb-3 text-sm font-bold text-ink">{{ t('Best practices') }}</h3>
          <ul class="divide-y divide-line">
            <li v-for="g in guidelines" :key="g.label" class="flex items-center justify-between py-2.5 text-sm">
              <span class="text-ink-2">{{ g.label }}</span>
              <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="statusStyle(g.status)">{{ statusLabel(g.status) }}</span>
            </li>
          </ul>
        </section>
      </div>
    </div>
  </AdminLayout>
</template>
