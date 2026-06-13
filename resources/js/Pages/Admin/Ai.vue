<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, reactive } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';

const props = defineProps<{
  config: { provider: string; model: string; base_url: string; has_key: boolean; ask_enabled: boolean; ask_knowledge: boolean; ask_strict: boolean; knowledge_count: number; embed_has_key: boolean; embed_model: string; autoanswer_enabled: boolean; autoanswer_keywords: string; bot_name: string; moderation_enabled: boolean; translate_posts: boolean; price_in: number; price_out: number; monthly_budget: number };
  index: { ready: boolean; count: number; running: boolean; percent: number; status: string | null; builtAt: string | null };
  usage: { spentCents: number; budgetCents: number; overBudget: boolean; calls: number; tokens: number; byFeature: { feature: string; calls: number; tokens: number; cents: number }[] };
}>();

const page = usePage();
const status = computed(() => (page.props as any).flash?.status as string | undefined);

const form = reactive({
  provider: props.config.provider || 'anthropic',
  model: props.config.model || '',
  base_url: props.config.base_url || '',
  api_key: '',
  ask_enabled: props.config.ask_enabled,
  ask_knowledge: props.config.ask_knowledge,
  ask_strict: props.config.ask_strict,
  embed_model: props.config.embed_model || '',
  embed_key: '',
  autoanswer_enabled: props.config.autoanswer_enabled,
  autoanswer_keywords: props.config.autoanswer_keywords || '',
  bot_name: props.config.bot_name || 'Convoro Assistant',
  moderation_enabled: props.config.moderation_enabled,
  translate_posts: props.config.translate_posts,
  price_in: props.config.price_in ?? 0,
  price_out: props.config.price_out ?? 0,
  monthly_budget: props.config.monthly_budget ?? 0,
});

function save() {
  router.post('/admin/ai', { ...form }, { preserveScroll: true, onSuccess: () => { form.api_key = ''; form.embed_key = ''; } });
}

const money = (cents: number) => '$' + (cents / 100).toFixed(2);
const budgetPct = computed(() => props.usage.budgetCents > 0 ? Math.min(100, Math.round(props.usage.spentCents / props.usage.budgetCents * 100)) : 0);

const idx = reactive({ ...props.index });
let timer: number | null = null;
function reindexKnowledge() {
  router.post('/admin/ai/knowledge/reindex', {}, { preserveScroll: true });
}

function buildIndex() {
  router.post('/admin/ai/index/build', {}, { preserveScroll: true, onSuccess: () => { idx.running = true; poll(); } });
}
async function poll() {
  if (timer) return;
  const tick = async () => {
    try {
      const r = await fetch('/admin/ai/index/status', { headers: { Accept: 'application/json' } });
      Object.assign(idx, await r.json());
      if (!idx.running) stop();
    } catch { /* ignore */ }
  };
  await tick();
  timer = window.setInterval(tick, 2500);
}
function stop() { if (timer) { clearInterval(timer); timer = null; } }
onBeforeUnmount(stop);
if (props.index.running) poll();

const field = 'mt-1 w-full rounded-lg border-line bg-appbg text-ink placeholder:text-ink-muted focus:border-primary focus:ring-primary text-sm';
</script>

<template>
  <Head :title="t('Admin · AI')" />
  <AdminLayout>
    <template #title>{{ t('AI') }}</template>
    <template #subtitle>{{ t('The assistant that powers “Ask Convoro” and AI features') }}</template>

    <div v-if="status" class="mb-5 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-300">{{ status }}</div>

    <div class="grid gap-6 lg:grid-cols-2">
      <section class="rounded-2xl border border-line bg-surface p-5">
        <h3 class="mb-1 text-sm font-bold text-ink">{{ t('Language model') }}</h3>
        <p class="mb-4 text-xs text-ink-muted">{{ t('Connect any Anthropic or OpenAI-compatible provider. One key powers Ask Convoro and every AI feature.') }}</p>

        <label class="text-xs font-semibold text-ink-2">{{ t('Provider') }}</label>
        <select v-model="form.provider" :class="field">
          <option value="anthropic">{{ t('Anthropic (Claude)') }}</option>
          <option value="openai">{{ t('OpenAI-compatible (OpenAI, OpenRouter, Together, local…)') }}</option>
        </select>

        <label class="mt-3 block text-xs font-semibold text-ink-2">{{ t('Model') }}</label>
        <input v-model="form.model" :class="field" :placeholder="form.provider === 'anthropic' ? 'claude-3-5-haiku-latest' : 'gpt-4o-mini'" />

        <template v-if="form.provider === 'openai'">
          <label class="mt-3 block text-xs font-semibold text-ink-2">{{ t('Base URL') }}</label>
          <input v-model="form.base_url" :class="field" placeholder="https://api.openai.com/v1" />
        </template>

        <label class="mt-3 block text-xs font-semibold text-ink-2">
          {{ t('API key') }} <span v-if="config.has_key" class="font-normal text-emerald-400">{{ t('· a key is saved') }}</span>
        </label>
        <input v-model="form.api_key" type="password" :class="field" :placeholder="config.has_key ? '•••••••• (leave blank to keep)' : t('Paste your API key')" />

        <button class="mt-4 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-600" @click="save">{{ t('Save AI settings') }}</button>
      </section>

      <section class="rounded-2xl border border-line bg-surface p-5">
        <h3 class="mb-1 text-sm font-bold text-ink">{{ t('Ask Convoro') }}</h3>
        <p class="mb-4 text-xs text-ink-muted">{{ t("A search-and-answer bar on the community home. Members ask a question and get an instant answer synthesized from your community's threads, with citations.") }}</p>

        <label class="flex items-center gap-2 text-sm text-ink-2">
          <input v-model="form.ask_enabled" type="checkbox" class="rounded border-line text-primary focus:ring-primary" @change="save" />
          {{ t('Show the “Ask Convoro” bar on the community page') }}
        </label>

        <p v-if="!config.has_key" class="mt-4 rounded-lg border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-sm text-amber-300">
          {{ t('Add a language-model key (left) first — Ask needs it to write answers.') }}
        </p>

        <!-- Semantic search (Voyage embeddings) -->
        <div class="mt-5 border-t border-line pt-4">
          <h4 class="text-sm font-bold text-ink">{{ t('Semantic search') }} <span class="font-normal text-ink-muted">{{ t('— Voyage AI embeddings') }}</span></h4>
          <p class="mt-1 text-xs text-ink-muted">{{ t('Optional but recommended. With embeddings, Ask understands meaning (not just keywords) and finds the right threads even when the wording differs. Without it, Ask falls back to full-text search.') }}</p>

          <label class="mt-3 block text-xs font-semibold text-ink-2">
            {{ t('Voyage API key') }} <span v-if="config.embed_has_key" class="font-normal text-emerald-400">{{ t('· saved') }}</span>
          </label>
          <input v-model="form.embed_key" type="password" :class="field" :placeholder="config.embed_has_key ? '•••••••• (leave blank to keep)' : 'pa-…'" />
          <label class="mt-3 block text-xs font-semibold text-ink-2">{{ t('Embedding model') }}</label>
          <input v-model="form.embed_model" :class="field" placeholder="voyage-3.5-lite" />
          <button class="mt-3 rounded-lg bg-surface-2 px-3 py-2 text-sm font-semibold text-ink hover:bg-appbg" @click="save">{{ t('Save embeddings') }}</button>

          <div class="mt-4 rounded-xl bg-appbg p-3">
            <div class="flex items-center justify-between text-sm">
              <span class="text-ink-2">{{ t('Index:') }} <b class="text-ink">{{ idx.count }}</b> {{ t('posts') }}<span v-if="idx.ready" class="ml-1 text-emerald-400">{{ t('· active') }}</span></span>
              <button :disabled="!config.embed_has_key || idx.running" class="rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-600 disabled:opacity-50" @click="buildIndex">
                {{ idx.running ? t('Building…') : t('Rebuild index') }}
              </button>
            </div>
            <div v-if="idx.running" class="mt-2">
              <div class="h-1.5 w-full overflow-hidden rounded-full bg-surface-2"><div class="h-full bg-primary transition-all" :style="{ width: idx.percent + '%' }"></div></div>
              <p class="mt-1 text-xs text-ink-muted">{{ idx.status }}</p>
            </div>
            <p v-else-if="idx.builtAt" class="mt-1 text-xs text-ink-muted">{{ t('Last built {date}. New posts are indexed automatically.', { date: idx.builtAt }) }}</p>
          </div>
        </div>

        <!-- Grounded answers (docs + changelog knowledge base) -->
        <div class="mt-5 border-t border-line pt-4">
          <h4 class="text-sm font-bold text-ink">{{ t('Grounded answers') }} <span class="font-normal text-ink-muted">{{ t('— answer from your documentation') }}</span></h4>
          <p class="mt-1 text-xs text-ink-muted">{{ t('Index your built-in docs and changelog so Ask answers support questions from how the software actually works — with citations — instead of paraphrasing forum posts.') }}</p>

          <label class="mt-3 flex items-center gap-2 text-sm text-ink-2">
            <input v-model="form.ask_knowledge" type="checkbox" class="rounded border-line text-primary focus:ring-primary" @change="save" />
            {{ t('Ground answers in the documentation & changelog') }}
          </label>
          <label class="mt-2 flex items-center gap-2 text-sm text-ink-2">
            <input v-model="form.ask_strict" type="checkbox" class="rounded border-line text-primary focus:ring-primary" @change="save" />
            {{ t('Strict support mode — only answer from the knowledge base; don’t guess') }}
          </label>

          <div class="mt-3 flex items-center justify-between rounded-xl bg-appbg p-3 text-sm">
            <span class="text-ink-2">{{ t('Knowledge:') }} <b class="text-ink">{{ config.knowledge_count }}</b> {{ t('sources') }}</span>
            <button :disabled="!config.embed_has_key" class="rounded-lg bg-primary px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-600 disabled:opacity-50" @click="reindexKnowledge">{{ t('Index knowledge') }}</button>
          </div>
        </div>
      </section>

      <!-- Auto-answer -->
      <section class="rounded-2xl border border-line bg-surface p-5 lg:col-span-2">
        <h3 class="mb-1 text-sm font-bold text-ink">{{ t('Auto-answer new topics') }}</h3>
        <p class="mb-4 text-xs text-ink-muted">{{ t('When a member starts a topic and no one has replied, the assistant posts a citation-backed draft answer that the community can confirm or improve. Great for keeping a young community feeling alive — every question gets a response.') }}</p>

        <label class="flex items-center gap-2 text-sm text-ink-2">
          <input v-model="form.autoanswer_enabled" type="checkbox" class="rounded border-line text-primary focus:ring-primary" @change="save" />
          {{ t('Let the assistant draft an answer on unanswered topics') }}
        </label>

        <label class="mt-3 block text-xs font-semibold text-ink-2">{{ t('Only answer topics mentioning…') }} <span class="font-normal text-ink-muted">{{ t('(optional keywords)') }}</span></label>
        <textarea v-model="form.autoanswer_keywords" rows="2" :class="field" :placeholder="t('e.g. how do I, error, not working, setup, billing')" @change="save"></textarea>
        <p class="mt-1 text-xs text-ink-muted">{{ t('Comma- or line-separated. The assistant only replies to topics that mention at least one of these — so it doesn’t respond on every topic. Leave empty to answer all unanswered topics.') }}</p>

        <label class="mt-3 block text-xs font-semibold text-ink-2">{{ t('Assistant display name') }}</label>
        <input v-model="form.bot_name" :class="field" placeholder="Convoro Assistant" @change="save" />

        <p v-if="!config.has_key" class="mt-3 text-xs text-amber-300">{{ t('Connect a language model first.') }}</p>
        <p v-else class="mt-3 text-xs text-ink-muted">{{ t('Answers are clearly labeled as AI and posted by your assistant account. Members can react, reply, or replace them like any post.') }}</p>
      </section>

      <!-- Usage & budget -->
      <section class="rounded-2xl border border-line bg-surface p-5 lg:col-span-2">
        <h3 class="mb-1 text-sm font-bold text-ink">{{ t('Usage & budget') }}</h3>
        <p class="mb-4 text-xs text-ink-muted">{{ t('Track this month’s AI spend across every feature and cap it. Set your model’s prices (per million tokens) so estimates are accurate; when the monthly cap is reached, AI features pause until next month.') }}</p>

        <div class="grid gap-4 sm:grid-cols-3">
          <div class="rounded-xl bg-appbg p-4">
            <div class="text-xs text-ink-muted">{{ t('Spent this month') }}</div>
            <div class="mt-1 text-2xl font-bold text-ink">{{ money(usage.spentCents) }}</div>
            <div v-if="usage.budgetCents > 0" class="mt-2">
              <div class="h-1.5 w-full overflow-hidden rounded-full bg-surface-2"><div class="h-full transition-all" :class="usage.overBudget ? 'bg-rose-500' : 'bg-primary'" :style="{ width: budgetPct + '%' }"></div></div>
              <div class="mt-1 text-[11px] text-ink-muted">{{ t('of :cap cap', { cap: money(usage.budgetCents) }) }}<span v-if="usage.overBudget" class="ml-1 font-semibold text-rose-400">· {{ t('reached — AI paused') }}</span></div>
            </div>
          </div>
          <div class="rounded-xl bg-appbg p-4">
            <div class="text-xs text-ink-muted">{{ t('Calls') }}</div>
            <div class="mt-1 text-2xl font-bold text-ink">{{ usage.calls.toLocaleString() }}</div>
          </div>
          <div class="rounded-xl bg-appbg p-4">
            <div class="text-xs text-ink-muted">{{ t('Tokens') }}</div>
            <div class="mt-1 text-2xl font-bold text-ink">{{ usage.tokens.toLocaleString() }}</div>
          </div>
        </div>

        <div v-if="usage.byFeature.length" class="mt-4 overflow-hidden rounded-xl border border-line">
          <table class="w-full text-sm">
            <thead class="bg-appbg text-left text-xs text-ink-muted"><tr><th class="px-4 py-2 font-semibold">{{ t('Feature') }}</th><th class="px-4 py-2 font-semibold">{{ t('Calls') }}</th><th class="px-4 py-2 font-semibold">{{ t('Tokens') }}</th><th class="px-4 py-2 text-right font-semibold">{{ t('Cost') }}</th></tr></thead>
            <tbody class="divide-y divide-line">
              <tr v-for="f in usage.byFeature" :key="f.feature">
                <td class="px-4 py-2 font-semibold text-ink">{{ f.feature }}</td>
                <td class="px-4 py-2 text-ink-2">{{ f.calls.toLocaleString() }}</td>
                <td class="px-4 py-2 text-ink-2">{{ f.tokens.toLocaleString() }}</td>
                <td class="px-4 py-2 text-right text-ink-2">{{ money(f.cents) }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="mt-4 grid gap-3 sm:grid-cols-3">
          <div>
            <label class="block text-xs font-semibold text-ink-2">{{ t('Input price (per 1M tokens, USD)') }}</label>
            <input v-model.number="form.price_in" type="number" step="0.01" min="0" :class="field" @change="save" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-ink-2">{{ t('Output price (per 1M tokens, USD)') }}</label>
            <input v-model.number="form.price_out" type="number" step="0.01" min="0" :class="field" @change="save" />
          </div>
          <div>
            <label class="block text-xs font-semibold text-ink-2">{{ t('Monthly budget (USD, 0 = none)') }}</label>
            <input v-model.number="form.monthly_budget" type="number" step="1" min="0" :class="field" @change="save" />
          </div>
        </div>
      </section>

      <!-- Moderation copilot -->
      <section class="rounded-2xl border border-line bg-surface p-5">
        <h3 class="mb-1 text-sm font-bold text-ink">{{ t('Moderation copilot') }}</h3>
        <p class="mb-4 text-xs text-ink-muted">{{ t('Every new post is screened by AI for spam, toxicity and exposed personal data. Risky posts are flagged in the moderation queue; very high-risk posts are held (hidden) until a moderator approves them.') }}</p>
        <label class="flex items-center gap-2 text-sm text-ink-2">
          <input v-model="form.moderation_enabled" type="checkbox" class="rounded border-line text-primary focus:ring-primary" @change="save" />
          {{ t('Screen new posts with the AI moderation copilot') }}
        </label>
        <p v-if="!config.has_key" class="mt-3 text-xs text-amber-300">{{ t('Connect a language model first.') }}</p>
      </section>

      <!-- Post translation -->
      <section class="rounded-2xl border border-line bg-surface p-5">
        <h3 class="mb-1 text-sm font-bold text-ink">{{ t('Post translation') }}</h3>
        <p class="mb-4 text-xs text-ink-muted">{{ t('Let members read any post in their own language. A “Translate” link appears on posts written in another language, and members can opt into automatic translation from the header.') }}</p>
        <label class="flex items-center gap-2 text-sm text-ink-2">
          <input v-model="form.translate_posts" type="checkbox" class="rounded border-line text-primary focus:ring-primary" @change="save" />
          {{ t('Enable AI post translation') }}
        </label>
        <p v-if="!config.has_key" class="mt-3 text-xs text-amber-300">{{ t('Connect a language model first.') }}</p>
      </section>
    </div>
  </AdminLayout>
</template>
