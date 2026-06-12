<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { t } from '@/lib/i18n';

const page = usePage();
const siteName = computed(() => ((page.props as any).site?.name as string) || 'the community');
const suggestions = computed<string[]>(() => ((page.props as any).ask?.suggestions ?? []) as string[]);

function pick(s: string) {
  q.value = s;
  ask();
}

const q = ref('');
const loading = ref(false);
const asked = ref(false);
const answer = ref('');
const citations = ref<{ n: number; title: string; url: string }[]>([]);

function csrf() {
  return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
}

async function ask() {
  const question = q.value.trim();
  if (question.length < 3 || loading.value) return;
  loading.value = true;
  asked.value = true;
  answer.value = '';
  citations.value = [];
  try {
    const r = await fetch('/api/ask', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
      body: JSON.stringify({ question }),
    });
    const d = await r.json();
    answer.value = d.answer || 'No answer.';
    citations.value = d.citations || [];
  } catch {
    answer.value = 'Something went wrong. Please try again.';
  } finally {
    loading.value = false;
  }
}

function reset() {
  asked.value = false;
  answer.value = '';
  citations.value = [];
  q.value = '';
}
</script>

<template>
  <section class="overflow-hidden rounded-c border border-line bg-surface shadow-sm">
    <div class="bg-gradient-to-r from-primary/10 via-primary/5 to-transparent p-5">
      <div class="flex items-center gap-2">
        <span class="grid h-7 w-7 place-items-center rounded-lg bg-primary text-white">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.9 4.6L18.5 9l-4.6 1.9L12 15l-1.9-4.1L5.5 9l4.6-1.4z"/><path d="M5 16l.9 2.1L8 19l-2.1.9L5 22l-.9-2.1L2 19l2.1-.9z"/></svg>
        </span>
        <h2 class="text-base font-extrabold tracking-tight text-ink">{{ t('Ask {site}', { site: siteName }) }}</h2>
      </div>
      <p class="mt-1 text-sm text-ink-muted">Get an instant answer from the community's knowledge — with links to the threads it came from.</p>

      <form class="mt-3 flex gap-2" @submit.prevent="ask">
        <input
          v-model="q"
          type="text"
          :placeholder="t('Ask anything about {site}…', { site: siteName })"
          class="flex-1 rounded-c border-line bg-surface-2 text-ink placeholder:text-ink-muted focus:border-primary focus:ring-primary"
        />
        <button
          type="submit"
          :disabled="loading || q.trim().length < 3"
          class="shrink-0 rounded-c bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-lg shadow-primary/30 hover:bg-primary-600 disabled:opacity-50"
        >
          {{ loading ? t('Thinking…') : t('Ask') }}
        </button>
      </form>

      <!-- Starter questions — the one-click "ask anything" moment. -->
      <div v-if="!asked && suggestions.length" class="mt-3 flex flex-wrap gap-2">
        <span class="self-center text-xs font-semibold text-ink-muted">{{ t('Try:') }}</span>
        <button
          v-for="(s, i) in suggestions"
          :key="i"
          type="button"
          class="rounded-full border border-line bg-surface px-3 py-1 text-xs font-medium text-ink-2 hover:border-primary/40 hover:text-primary"
          @click="pick(s)"
        >
          {{ s }}
        </button>
      </div>
    </div>

    <div v-if="asked" class="border-t border-line p-5">
      <div v-if="loading" class="flex items-center gap-2 text-sm text-ink-muted">
        <span class="h-2 w-2 animate-pulse rounded-full bg-primary"></span> Searching the community & writing an answer…
      </div>
      <template v-else>
        <div class="whitespace-pre-wrap text-[0.95rem] leading-relaxed text-ink">{{ answer }}</div>
        <div v-if="citations.length" class="mt-4">
          <div class="mb-1.5 text-xs font-bold uppercase tracking-wide text-ink-muted">{{ t('Sources') }}</div>
          <ol class="space-y-1">
            <li v-for="c in citations" :key="c.n" class="text-sm">
              <a :href="c.url" class="text-primary hover:underline">[{{ c.n }}] {{ c.title }}</a>
            </li>
          </ol>
        </div>
        <div class="mt-4 flex items-center gap-3 text-xs text-ink-muted">
          <button type="button" class="font-semibold text-primary hover:underline" @click="reset">{{ t('Ask another question') }}</button>
          <span>· AI-generated — verify important details.</span>
        </div>
      </template>
    </div>
  </section>
</template>
