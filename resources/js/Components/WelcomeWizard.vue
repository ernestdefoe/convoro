<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { t as tr } from '@/lib/i18n';

type Step = { label: string; detail: string; href: string; done: boolean };

const props = defineProps<{ steps: Step[]; done: number; total: number }>();
const emit = defineEmits<{ (e: 'close'): void }>();

// View index: -1 = welcome screen, 0..n-1 = a setup step, n = success screen.
const idx = ref(-1);
const n = computed(() => props.steps.length);
const current = computed<Step | null>(() => (idx.value >= 0 && idx.value < n.value ? props.steps[idx.value] : null));
const pct = computed(() => (props.total ? Math.round((props.done / props.total) * 100) : 0));
const stepNo = computed(() => Math.min(idx.value + 1, n.value));

function firstIncomplete(): number {
  const i = props.steps.findIndex((s) => !s.done);
  return i === -1 ? n.value : i;
}
function begin() { idx.value = firstIncomplete(); }
function next() {
  if (idx.value < 0) { begin(); return; }
  let i = idx.value + 1;
  while (i < n.value && props.steps[i].done) i++;
  idx.value = Math.min(i, n.value);
}
function back() {
  if (idx.value <= 0) { idx.value = -1; return; }
  let i = idx.value - 1;
  while (i > 0 && props.steps[i].done) i--;
  idx.value = i;
}
function goTo(href: string) { window.location.href = href; }
function dismiss() {
  router.post('/admin/onboarding/dismiss', {}, { preserveScroll: true, preserveState: true });
  emit('close');
}

// Returning mid-setup admins skip the welcome splash.
onMounted(() => { if (props.done > 0 && props.done < props.total) begin(); });
</script>

<template>
  <div class="fixed inset-0 z-[90] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="dismiss"></div>
    <div class="relative w-full max-w-lg overflow-hidden rounded-2xl border border-line bg-surface shadow-2xl">
      <!-- Header -->
      <div class="flex items-center gap-3 border-b border-line px-6 py-4">
        <div class="grid h-8 w-8 place-items-center rounded-lg bg-primary text-white">✦</div>
        <div class="flex-1">
          <div class="text-sm font-bold text-ink">{{ tr('Set up your community') }}</div>
          <div class="text-xs text-ink-muted">
            <span v-if="idx < 0">{{ tr('A quick guided tour') }}</span>
            <span v-else-if="idx < n">{{ tr('Step {n} of {total}', { n: stepNo, total: n }) }}</span>
            <span v-else>{{ tr('All done') }}</span>
          </div>
        </div>
        <button class="rounded-lg p-1.5 text-ink-muted hover:bg-surface-2 hover:text-ink" :aria-label="tr('Close')" @click="dismiss">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6L6 18M6 6l12 12" /></svg>
        </button>
      </div>

      <!-- Progress -->
      <div class="h-1 w-full bg-surface-2"><div class="h-full bg-primary transition-all duration-500" :style="{ width: pct + '%' }"></div></div>

      <!-- Welcome -->
      <div v-if="idx < 0" class="px-6 py-8 text-center">
        <div class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-primary/10 text-3xl">👋</div>
        <h2 class="mt-4 text-xl font-extrabold text-ink">{{ tr('Welcome to Convoro') }}</h2>
        <p class="mx-auto mt-2 max-w-sm text-sm text-ink-2">{{ tr('Let’s get your community ready in a few quick steps — branding, a place to post, email, and your first members. You can leave and pick up where you left off anytime.') }}</p>
        <p class="mt-4 text-xs font-semibold text-ink-muted">{{ tr('{done} of {total} already done', { done: done, total: total }) }}</p>
      </div>

      <!-- A setup step -->
      <div v-else-if="current" class="px-6 py-7">
        <div class="flex items-start gap-4">
          <div class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-primary/10 text-xl">{{ ['🎨','🗂️','✉️','🧩','📱','👥'][idx] ?? '✨' }}</div>
          <div class="min-w-0">
            <h2 class="text-lg font-bold text-ink">{{ current.label }}</h2>
            <p class="mt-1 text-sm text-ink-2">{{ current.detail }}</p>
            <p v-if="current.done" class="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-500">
              <svg width="14" height="14" viewBox="0 0 20 20" fill="currentColor"><path d="M16.7 5.3a1 1 0 010 1.4l-7.5 7.5a1 1 0 01-1.4 0L3.3 9.7a1 1 0 111.4-1.4l3.3 3.3 6.8-6.8a1 1 0 011.9.5z" /></svg>
              {{ tr('Already done') }}
            </p>
          </div>
        </div>
        <div class="mt-5 flex flex-wrap gap-2">
          <button class="rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-primary/25 hover:bg-primary-600" @click="goTo(current.href)">
            {{ current.done ? tr('Review it →') : tr('Take me there →') }}
          </button>
          <button class="rounded-lg px-3 py-2.5 text-sm font-semibold text-ink-2 hover:bg-surface-2" @click="next">{{ tr('Skip for now') }}</button>
        </div>

        <!-- mini step list -->
        <div class="mt-6 flex flex-wrap gap-1.5">
          <span v-for="(s, i) in steps" :key="s.label" class="h-1.5 rounded-full transition-all"
            :class="[i === idx ? 'w-6 bg-primary' : 'w-1.5', s.done ? 'bg-emerald-500' : (i === idx ? 'bg-primary' : 'bg-surface-2')]"></span>
        </div>
      </div>

      <!-- Success -->
      <div v-else class="px-6 py-8 text-center">
        <div class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-emerald-500/15 text-3xl">🎉</div>
        <h2 class="mt-4 text-xl font-extrabold text-ink">{{ done >= total ? tr('You’re all set!') : tr('Nicely done') }}</h2>
        <p class="mx-auto mt-2 max-w-sm text-sm text-ink-2">{{ done >= total ? tr('Your community is ready. You can always tweak everything from the admin.') : tr('You can finish the remaining steps any time from the dashboard.') }}</p>
      </div>

      <!-- Footer -->
      <div class="flex items-center gap-2 border-t border-line px-6 py-4">
        <button v-if="idx >= 0" class="rounded-lg px-3 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2" @click="back">{{ tr('Back') }}</button>
        <button class="ml-auto rounded-lg px-3 py-2 text-sm text-ink-muted hover:text-ink" @click="dismiss">{{ tr('I’ll finish later') }}</button>
        <button v-if="idx < 0" class="rounded-lg bg-primary px-5 py-2 text-sm font-semibold text-white hover:bg-primary-600" @click="begin">{{ tr('Let’s go →') }}</button>
        <button v-else-if="idx < n" class="rounded-lg bg-primary px-5 py-2 text-sm font-semibold text-white hover:bg-primary-600" @click="next">{{ tr('Next') }}</button>
        <button v-else class="rounded-lg bg-primary px-5 py-2 text-sm font-semibold text-white hover:bg-primary-600" @click="dismiss">{{ tr('Finish') }}</button>
      </div>
    </div>
  </div>
</template>
