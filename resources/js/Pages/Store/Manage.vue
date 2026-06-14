<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, reactive, ref } from 'vue';
import MarketingLayout from '@/Layouts/MarketingLayout.vue';
import { t } from '@/lib/i18n';

interface Item {
  slug: string; name: string; type: string; tagline?: string; description?: string;
  free?: boolean; price?: number; published: boolean; status?: string;
  // owner-only fields
  id?: number; price_cents?: number; package?: string; version?: string; image?: string;
  featured?: boolean; submitter?: string; source?: string; repo?: string; hasDownload?: boolean; sales?: number;
  pinnedVersion?: string | null; synced?: string | null;
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  review?: any;
}
interface Stripe { key: string; secretSet: boolean; webhookSet: boolean; webhookUrl: string; canConnect: boolean; connectedAccount: string | null }

interface ReviewAi { enabled: boolean; configured: boolean; provider: string; model: string; base_url: string }
const props = defineProps<{ products: Item[]; isOwner?: boolean; checkoutEnabled: boolean; stripe?: Stripe | null; registryWebhookUrl?: string | null; reviewAi?: ReviewAi | null }>();

const status = computed(() => (usePage().props as any).flash?.status as string | undefined);
const error = computed(() => (usePage().props as any).flash?.storeError as string | undefined);

// ── Author self-service (per-listing pricing/copy) ──────────────────────────
const forms = reactive<Record<string, { pricing: 'free' | 'premium'; price: number; tagline: string; description: string; saving: boolean }>>(
  props.isOwner ? {} : Object.fromEntries(props.products.map((p) => [p.slug, {
    pricing: p.free ? 'free' : 'premium',
    price: p.free ? 9 : (p.price ?? 9),
    tagline: p.tagline ?? '',
    description: p.description ?? '',
    saving: false,
  }])),
);
function save(p: Item) {
  const f = forms[p.slug];
  f.saving = true;
  router.post(`/extensions/${p.slug}/manage`, {
    pricing: f.pricing, price: f.price, tagline: f.tagline, description: f.description,
  }, { preserveScroll: true, onFinish: () => (f.saving = false) });
}

// ── Owner console (Stripe + GitHub + products) ──────────────────────────────
const showAdvancedKeys = ref(false);
const stripeForm = useForm({ key: props.stripe?.key ?? '', secret: '', webhook_secret: '' });
function saveStripe() { stripeForm.post('/extensions/manage/stripe', { preserveScroll: true }); }

const repo = ref('');
const repoPin = ref('');
const linking = ref(false);
function linkRepo() {
  if (!repo.value.trim()) return;
  linking.value = true;
  router.post('/extensions/manage/link', { repo: repo.value.trim(), pin: repoPin.value.trim() || null }, {
    preserveScroll: true, onSuccess: () => { repo.value = ''; repoPin.value = ''; }, onFinish: () => (linking.value = false),
  });
}
function refreshRepo(p: Item) { router.post(`/extensions/manage/products/${p.id}/refresh`, {}, { preserveScroll: true }); }
function uploadFile(p: Item, e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0];
  if (!file) return;
  router.post(`/extensions/manage/products/${p.id}/file`, { file }, { forceFormData: true, preserveScroll: true });
}

const blank = () => ({ id: null, name: '', type: 'extension', tagline: '', description: '', package: '', version: '1.0.0', price_dollars: '19', published: true, featured: false, pinned_version: '', source: 'manual' });
const editing = ref<any>(null);
function openNew() { editing.value = blank(); }
function openEdit(p: Item) { editing.value = { ...p, price_dollars: ((p.price_cents ?? 0) / 100).toString(), pinned_version: p.pinnedVersion ?? '' }; }
function closeEdit() { editing.value = null; }
function saveProduct() {
  const e = editing.value;
  const payload = {
    name: e.name, type: e.type, tagline: e.tagline, description: e.description,
    package: e.package, version: e.version,
    price_cents: Math.round(parseFloat(e.price_dollars || '0') * 100),
    published: e.published, featured: e.featured, pinned_version: e.pinned_version || null,
  };
  const opts = { preserveScroll: true, onSuccess: () => closeEdit() };
  if (e.id) router.put(`/extensions/manage/products/${e.id}`, payload, opts);
  else router.post('/extensions/manage/products', payload, opts);
}
function destroyProduct(p: Item) {
  if (confirm(t('Delete “{name}”? Existing licenses are kept but the product is removed.', { name: p.name }))) {
    router.delete(`/extensions/manage/products/${p.id}`, { preserveScroll: true });
  }
}
function generateCovers() { router.post('/extensions/manage/covers', {}, { preserveScroll: true }); }
function reviewProduct(p: Item) { router.post(`/extensions/manage/products/${p.id}/review`, {}, { preserveScroll: true }); }

// AI code-review settings (shares the ai.* keys with the AI Helper).
const reviewForm = useForm({
  enabled: props.reviewAi?.enabled ?? true,
  provider: props.reviewAi?.provider ?? 'anthropic',
  model: props.reviewAi?.model ?? '',
  base_url: props.reviewAi?.base_url ?? '',
  api_key: '',
});
function saveReview() { reviewForm.post('/extensions/manage/review-settings', { preserveScroll: true }); }

function reviewBadge(r: any): { t: string; c: string } | null {
  if (!r) return null;
  if (r.status === 'queued' || r.status === 'reviewing') return { t: t('⏳ reviewing'), c: 'bg-surface text-ink-muted' };
  if (r.rating === 'safe') return { t: t('✓ safe'), c: 'bg-emerald-500/15 text-emerald-500' };
  if (r.rating === 'caution') return { t: t('⚠ caution'), c: 'bg-amber-500/15 text-amber-500' };
  if (r.rating === 'unsafe') return { t: t('⛔ unsafe'), c: 'bg-red-500/15 text-red-400' };
  if (r.status === 'error') return { t: t('review error'), c: 'bg-red-500/15 text-red-400' };
  return null;
}
</script>

<template>
  <Head :title="`${isOwner ? t('Store console') : t('Manage your extensions')} — Convoro`" />
  <MarketingLayout>
    <section class="mx-auto max-w-3xl px-6 py-16">
      <Link href="/extensions" class="mb-6 inline-flex items-center gap-1.5 text-sm font-semibold text-ink-muted hover:text-ink">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7" /></svg>
        {{ t('Back to the directory') }}
      </Link>

      <h1 class="text-4xl font-black tracking-tight">{{ isOwner ? t('Store console') : t('Your extensions') }}</h1>
      <p class="mt-3 text-lg text-ink-2">{{ isOwner ? t('Payments, GitHub publishing, and every listing in the directory.') : t('Edit pricing and details for the listings you publish. Changes go live immediately.') }}</p>

      <div v-if="status" class="mt-6 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm font-medium text-emerald-700 dark:text-emerald-300">{{ status }}</div>
      <div v-if="error" class="mt-6 rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm font-medium text-red-600 dark:text-red-300">{{ error }}</div>

      <!-- ════════════ OWNER CONSOLE ════════════ -->
      <template v-if="isOwner && stripe">
        <!-- Stripe -->
        <section class="mt-8 rounded-2xl border border-line bg-surface p-6">
          <h2 class="text-sm font-bold uppercase tracking-wide text-ink-2">{{ t('Payments (Stripe)') }}</h2>
          <div v-if="stripe.connectedAccount" class="mt-3 flex flex-wrap items-center gap-3 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4">
            <span class="grid h-9 w-9 place-items-center rounded-full bg-emerald-500/20 text-emerald-500">✓</span>
            <div class="min-w-0">
              <div class="text-sm font-semibold text-emerald-600 dark:text-emerald-200">{{ t('Stripe connected') }}</div>
              <div class="font-mono text-xs text-emerald-500/80">{{ stripe.connectedAccount }}</div>
            </div>
            <a :href="`https://dashboard.stripe.com/${stripe.connectedAccount}`" target="_blank" rel="noopener" class="ml-auto text-sm font-semibold text-emerald-500 hover:underline">{{ t('Open dashboard') }}</a>
            <button type="button" @click="router.post('/extensions/manage/stripe/disconnect', {}, { preserveScroll: true })" class="rounded-lg px-3 py-1.5 text-xs font-semibold text-ink-2 hover:bg-red-500/10 hover:text-red-400">{{ t('Disconnect') }}</button>
          </div>
          <template v-else>
            <p class="mt-1 text-xs text-ink-muted">{{ t('Connect your Stripe account to take payments — no copy-pasting API keys.') }}</p>
            <a v-if="stripe.canConnect" href="/extensions/manage/stripe/connect"
              class="mt-3 inline-flex items-center gap-2 rounded-lg bg-[#635bff] px-5 py-2.5 text-sm font-bold text-white hover:bg-[#5249e0]">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M13.5 9.6c0-.6.5-.8 1.2-.8 1.1 0 2.5.3 3.6.9V6.3c-1.2-.5-2.4-.7-3.6-.7-2.9 0-4.9 1.5-4.9 4.1 0 4 5.4 3.3 5.4 5 0 .6-.6.9-1.4.9-1.2 0-2.8-.5-4-1.2v3.4c1.4.6 2.7.8 4 .8 3 0 5-1.5 5-4.1 0-4.3-5.3-3.5-5.3-5.1z"/></svg>
              {{ t('Connect with Stripe') }}
            </a>
            <p v-else class="mt-3 rounded-lg bg-amber-500/10 px-3 py-2 text-xs text-amber-600 dark:text-amber-300">
              {{ t('To enable “Connect with Stripe”, set') }} <code>STRIPE_CONNECT_CLIENT_ID</code> {{ t('and') }} <code>STRIPE_SECRET</code> {{ t('in the server’s') }} <code>.env</code>{{ t(', then reload.') }}
            </p>
          </template>
          <div class="mt-5 border-t border-line pt-4">
            <p class="text-xs text-ink-muted">{{ t('Add a webhook in Stripe →') }} <code class="text-ink-2">{{ stripe.webhookUrl }}</code> {{ t('(event') }} <code class="text-ink-2">checkout.session.completed</code>{{ t(').') }}</p>
            <button type="button" class="mt-2 text-xs font-semibold text-ink-2 hover:text-ink" @click="showAdvancedKeys = !showAdvancedKeys">
              {{ showAdvancedKeys ? t('Hide') : t('Advanced: enter keys manually') }}
            </button>
            <div v-if="showAdvancedKeys" class="mt-3 space-y-3">
              <div>
                <label class="block text-sm font-medium text-ink-2">{{ t('Publishable key') }}</label>
                <input v-model="stripeForm.key" type="text" placeholder="pk_live_…" class="mt-1 w-full rounded-lg border-line bg-surface-2 font-mono text-sm text-ink focus:border-primary focus:ring-primary" />
              </div>
              <div>
                <label class="block text-sm font-medium text-ink-2">{{ t('Secret key') }} <span v-if="stripe.secretSet" class="text-emerald-500">{{ t('(set)') }}</span></label>
                <input v-model="stripeForm.secret" type="password" :placeholder="stripe.secretSet ? t('•••• leave blank to keep') : 'sk_live_…'" class="mt-1 w-full rounded-lg border-line bg-surface-2 font-mono text-sm text-ink focus:border-primary focus:ring-primary" />
              </div>
              <div>
                <label class="block text-sm font-medium text-ink-2">{{ t('Webhook signing secret') }} <span v-if="stripe.webhookSet" class="text-emerald-500">{{ t('(set)') }}</span></label>
                <input v-model="stripeForm.webhook_secret" type="password" :placeholder="stripe.webhookSet ? t('•••• leave blank to keep') : 'whsec_…'" class="mt-1 w-full rounded-lg border-line bg-surface-2 font-mono text-sm text-ink focus:border-primary focus:ring-primary" />
              </div>
              <button @click="saveStripe" :disabled="stripeForm.processing" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-600">{{ t('Save keys') }}</button>
            </div>
          </div>
        </section>

        <!-- Publish from GitHub -->
        <section class="mt-6 rounded-2xl border border-line bg-surface p-6">
          <h2 class="text-sm font-bold uppercase tracking-wide text-ink-2">{{ t('Publish from GitHub') }}</h2>
          <p class="mt-1 text-xs text-ink-muted">{{ t('Link a public GitHub repo with an') }} <code>extension.json</code> {{ t('at its root — Convoro reads the manifest and lists it. Leave the tag blank to track the latest release (auto-updates); set a tag to pin a fixed version.') }}</p>
          <div class="mt-3 flex flex-wrap items-center gap-3">
            <input v-model="repo" type="text" :placeholder="t('owner/repository')" class="w-64 rounded-lg border-line bg-surface-2 font-mono text-sm text-ink focus:border-primary focus:ring-primary" />
            <input v-model="repoPin" type="text" :placeholder="t('tag (optional, e.g. v1.2.0)')" class="w-44 rounded-lg border-line bg-surface-2 font-mono text-sm text-ink focus:border-primary focus:ring-primary" />
            <button type="button" :disabled="linking || !repo.trim()" @click="linkRepo" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-600 disabled:opacity-60">
              {{ linking ? t('Linking…') : t('Link repo') }}
            </button>
          </div>
          <p v-if="registryWebhookUrl" class="mt-3 border-t border-line pt-3 text-xs text-ink-muted">
            <strong class="text-ink-2">{{ t('Auto-refresh:') }}</strong> {{ t('add a GitHub webhook (event') }} <code>release</code>) →
            <code class="text-ink-2 select-all">{{ registryWebhookUrl }}</code> {{ t('to publish new releases instantly. Unpinned items also re-check hourly.') }}
          </p>
          <p class="mt-3 text-xs text-ink-muted">
            <strong class="text-ink-2">{{ t('Private repos:') }}</strong>
            {{ t('install the') }} <strong class="text-ink-2">{{ t('Social Login') }}</strong> {{ t('extension, enable GitHub with “Request access to private repositories,” then sign in with GitHub to let the registry read your private repositories.') }}
          </p>
        </section>

        <!-- AI code review -->
        <section v-if="reviewAi" class="mt-6 rounded-2xl border border-line bg-surface p-6">
          <div class="flex items-center gap-2">
            <h2 class="text-sm font-bold uppercase tracking-wide text-ink-2">{{ t('AI code review') }}</h2>
            <span v-if="reviewAi.configured" class="rounded-full bg-emerald-500/15 px-2 py-0.5 text-[11px] font-semibold text-emerald-500">{{ t('active') }}</span>
            <span v-else class="rounded-full bg-amber-500/15 px-2 py-0.5 text-[11px] font-semibold text-amber-500">{{ t('needs API key') }}</span>
          </div>
          <p class="mt-1 text-xs text-ink-muted">{{ t('Every GitHub extension is auto-audited for security on link + on each new release; the verdict shows on its listing. Uses your configured AI provider (Admin → AI).') }}</p>
          <div class="mt-4 space-y-3">
            <label class="flex items-center gap-2 text-sm text-ink-2"><input type="checkbox" v-model="reviewForm.enabled" class="rounded border-line bg-surface-2 text-primary" /> {{ t('Enable automatic reviews') }}</label>
            <div class="flex flex-wrap gap-3">
              <select v-model="reviewForm.provider" class="rounded-lg border-line bg-surface-2 text-sm text-ink focus:border-primary focus:ring-primary"><option value="anthropic">{{ t('Anthropic') }}</option><option value="openai">{{ t('OpenAI-compatible') }}</option></select>
              <input v-model="reviewForm.model" type="text" :placeholder="t('model (optional)')" class="w-48 rounded-lg border-line bg-surface-2 font-mono text-sm text-ink focus:border-primary focus:ring-primary" />
            </div>
            <input v-if="reviewForm.provider === 'openai'" v-model="reviewForm.base_url" type="text" placeholder="https://api.openai.com/v1 (or compatible)" class="w-full rounded-lg border-line bg-surface-2 font-mono text-sm text-ink focus:border-primary focus:ring-primary" />
            <input v-model="reviewForm.api_key" type="password" :placeholder="reviewAi.configured ? t('•••• leave blank to keep') : t('API key')" class="w-full rounded-lg border-line bg-surface-2 font-mono text-sm text-ink focus:border-primary focus:ring-primary" />
            <button @click="saveReview" :disabled="reviewForm.processing" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-600">{{ t('Save review settings') }}</button>
          </div>
        </section>

        <!-- Products -->
        <section class="mt-6 rounded-2xl border border-line bg-surface p-6">
          <div class="mb-4 flex items-center">
            <h2 class="text-sm font-bold uppercase tracking-wide text-ink-2">{{ t('Products') }}</h2>
            <button @click="generateCovers" class="ml-auto rounded-lg border border-line px-3 py-1.5 text-sm font-semibold text-ink-2 hover:bg-surface-2">{{ t('Generate covers') }}</button>
            <button @click="openNew" class="ml-3 rounded-lg bg-primary px-3 py-1.5 text-sm font-semibold text-white hover:bg-primary-600">{{ t('+ New product') }}</button>
          </div>
          <div v-if="!products.length" class="rounded-xl border border-dashed border-line p-8 text-center text-sm text-ink-2">{{ t('No products yet.') }}</div>
          <div v-else class="space-y-2.5">
            <div v-for="p in products" :key="p.id" class="flex flex-wrap items-center gap-3 rounded-xl border border-line bg-surface-2 p-4">
              <img v-if="p.image" :src="p.image" :alt="p.name" class="h-10 w-16 rounded object-cover" />
              <div v-else class="grid h-10 w-10 place-items-center rounded-lg bg-primary/15 text-lg font-extrabold text-primary">{{ (p.name || '?').charAt(0).toUpperCase() }}</div>
              <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                  <span class="font-semibold text-ink">{{ p.name }}</span>
                  <span v-if="p.status === 'pending'" class="rounded bg-amber-500/15 px-1.5 py-0.5 text-[11px] text-amber-500">{{ t('Submitted · review') }}</span>
                  <span v-else-if="!p.published" class="rounded bg-surface px-1.5 py-0.5 text-[11px] text-ink-2">{{ t('Draft') }}</span>
                  <span v-if="p.featured" class="rounded bg-amber-500/15 px-1.5 py-0.5 text-[11px] text-amber-500">{{ t('Featured') }}</span>
                  <span v-if="p.source === 'github'" class="rounded bg-sky-500/15 px-1.5 py-0.5 text-[11px] text-sky-500">{{ t('GitHub') }}</span>
                  <span v-if="p.version" class="rounded bg-surface px-1.5 py-0.5 text-[11px] text-ink-2">v{{ p.version }}</span>
                  <span v-if="p.pinnedVersion" class="rounded bg-violet-500/15 px-1.5 py-0.5 text-[11px] text-violet-400" :title="t('Pinned to {version}', { version: p.pinnedVersion })">📌 {{ p.pinnedVersion }}</span>
                  <span v-else-if="p.source === 'github'" class="rounded bg-emerald-500/15 px-1.5 py-0.5 text-[11px] text-emerald-500">{{ t('latest') }}</span>
                  <span v-if="p.hasDownload" class="rounded bg-emerald-500/15 px-1.5 py-0.5 text-[11px] text-emerald-500">{{ p.source === 'github' ? t('Linked ✓') : t('File ✓') }}</span>
                  <span v-if="reviewBadge(p.review)" class="rounded px-1.5 py-0.5 text-[11px] font-semibold" :class="reviewBadge(p.review)!.c">{{ reviewBadge(p.review)!.t }}</span>
                </div>
                <div class="text-xs text-ink-muted">${{ ((p.price_cents ?? 0)/100).toFixed(2) }} · {{ t('{count} sold', { count: p.sales ?? 0 }) }} · {{ p.source === 'github' ? p.repo : (p.package || t('no package id')) }}<span v-if="p.synced"> · {{ t('synced {date}', { date: p.synced }) }}</span><span v-if="p.submitter"> · {{ t('by {name}', { name: p.submitter }) }}</span></div>
              </div>
              <button v-if="p.source === 'github'" @click="refreshRepo(p)" class="rounded-lg border border-line px-2.5 py-1.5 text-xs font-semibold text-ink-2 hover:bg-surface">{{ t('Refresh') }}</button>
              <label v-else class="cursor-pointer rounded-lg border border-line px-2.5 py-1.5 text-xs font-semibold text-ink-2 hover:bg-surface">
                {{ t('Upload zip') }}<input type="file" accept=".zip" class="hidden" @change="(e) => uploadFile(p, e)" />
              </label>
              <button v-if="reviewAi?.configured && p.hasDownload" @click="reviewProduct(p)" class="rounded-lg border border-line px-2.5 py-1.5 text-xs font-semibold text-ink-2 hover:bg-surface">{{ t('Review') }}</button>
              <button @click="openEdit(p)" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-ink-2 hover:bg-surface">{{ t('Edit') }}</button>
              <button @click="destroyProduct(p)" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-ink-2 hover:bg-red-500/10 hover:text-red-400">{{ t('Delete') }}</button>
            </div>
          </div>
        </section>

        <!-- Editor modal -->
        <div v-if="editing" class="fixed inset-0 z-[80] flex items-center justify-center p-4">
          <div class="absolute inset-0 bg-black/60" @click="closeEdit" />
          <div class="relative max-h-[88vh] w-full max-w-lg overflow-y-auto rounded-2xl border border-line bg-surface p-6 shadow-2xl">
            <h3 class="mb-4 font-bold text-ink">{{ editing.id ? t('Edit product') : t('New product') }}</h3>
            <div class="space-y-3">
              <div><label class="block text-sm text-ink-2">{{ t('Name') }}</label><input v-model="editing.name" class="mt-1 w-full rounded-lg border-line bg-surface-2 text-ink focus:border-primary focus:ring-primary" /></div>
              <div class="flex gap-3">
                <div class="flex-1"><label class="block text-sm text-ink-2">{{ t('Type') }}</label>
                  <select v-model="editing.type" class="mt-1 w-full rounded-lg border-line bg-surface-2 text-ink focus:border-primary focus:ring-primary"><option value="extension">{{ t('Extension') }}</option><option value="theme">{{ t('Theme') }}</option></select></div>
                <div class="w-28"><label class="block text-sm text-ink-2">{{ t('Price (USD)') }}</label><input v-model="editing.price_dollars" type="number" min="0" step="1" class="mt-1 w-full rounded-lg border-line bg-surface-2 text-ink focus:border-primary focus:ring-primary" /></div>
                <div class="w-24"><label class="block text-sm text-ink-2">{{ t('Version') }}</label><input v-model="editing.version" class="mt-1 w-full rounded-lg border-line bg-surface-2 text-ink focus:border-primary focus:ring-primary" /></div>
              </div>
              <div><label class="block text-sm text-ink-2">{{ t('Package id') }} <span class="text-ink-muted">{{ t('(the extension id the license unlocks)') }}</span></label><input v-model="editing.package" placeholder="convoro-group-messages" class="mt-1 w-full rounded-lg border-line bg-surface-2 font-mono text-sm text-ink focus:border-primary focus:ring-primary" /></div>
              <div v-if="editing.source === 'github'">
                <label class="block text-sm text-ink-2">{{ t('Pin to release tag') }} <span class="text-ink-muted">{{ t('(blank = track latest, auto-updates)') }}</span></label>
                <input v-model="editing.pinned_version" placeholder="v1.2.0" class="mt-1 w-full rounded-lg border-line bg-surface-2 font-mono text-sm text-ink focus:border-primary focus:ring-primary" />
              </div>
              <div><label class="block text-sm text-ink-2">{{ t('Tagline') }}</label><input v-model="editing.tagline" class="mt-1 w-full rounded-lg border-line bg-surface-2 text-ink focus:border-primary focus:ring-primary" /></div>
              <div><label class="block text-sm text-ink-2">{{ t('Description') }}</label><textarea v-model="editing.description" rows="4" class="mt-1 w-full rounded-lg border-line bg-surface-2 text-ink focus:border-primary focus:ring-primary"></textarea></div>
              <div class="flex gap-5">
                <label class="flex items-center gap-2 text-sm text-ink-2"><input type="checkbox" v-model="editing.published" class="rounded border-line bg-surface-2 text-primary" /> {{ t('Published') }}</label>
                <label class="flex items-center gap-2 text-sm text-ink-2"><input type="checkbox" v-model="editing.featured" class="rounded border-line bg-surface-2 text-primary" /> {{ t('Featured') }}</label>
              </div>
            </div>
            <div class="mt-5 flex gap-2">
              <button @click="saveProduct" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-600">{{ t('Save') }}</button>
              <button @click="closeEdit" class="rounded-lg px-3 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2">{{ t('Cancel') }}</button>
            </div>
          </div>
        </div>
      </template>

      <!-- ════════════ AUTHOR SELF-SERVICE ════════════ -->
      <template v-else>
        <div v-if="!products.length" class="mt-10 rounded-2xl border border-dashed border-line p-16 text-center text-ink-muted">
          {{ t('You haven\'t published any extensions yet.') }}
          <Link href="/extensions/submit" class="font-semibold text-primary hover:underline">{{ t('Submit one →') }}</Link>
        </div>
        <div v-else class="mt-8 space-y-5">
          <div v-for="p in products" :key="p.slug" class="rounded-2xl border border-line bg-surface p-6">
            <div class="flex items-center gap-3">
              <div class="grid h-11 w-11 place-items-center rounded-xl bg-primary/10 text-xl font-extrabold text-primary">{{ (p.name || '?').charAt(0).toUpperCase() }}</div>
              <div class="min-w-0 flex-1">
                <h3 class="truncate font-bold">{{ p.name }}</h3>
                <span class="text-xs font-semibold uppercase tracking-wide" :class="p.published ? 'text-emerald-500' : 'text-amber-500'">
                  {{ p.status === 'pending' ? t('Pending review') : (p.published ? t('Live') : t('Draft')) }}
                </span>
              </div>
              <a :href="`/extensions/${p.slug}`" class="text-sm font-semibold text-primary hover:underline">{{ t('View →') }}</a>
            </div>
            <div class="mt-5 grid gap-4">
              <div>
                <label class="block text-sm font-bold">{{ t('Tagline') }}</label>
                <input v-model="forms[p.slug].tagline" maxlength="200" class="mt-1.5 w-full rounded-lg border-line bg-surface-2 text-sm focus:border-primary focus:ring-primary" />
              </div>
              <div>
                <label class="block text-sm font-bold">{{ t('Description') }}</label>
                <textarea v-model="forms[p.slug].description" rows="3" class="mt-1.5 w-full rounded-lg border-line bg-surface-2 text-sm focus:border-primary focus:ring-primary"></textarea>
              </div>
              <div>
                <label class="block text-sm font-bold">{{ t('Pricing') }}</label>
                <div class="mt-2 flex flex-wrap items-center gap-3">
                  <div class="flex rounded-xl border border-line p-0.5">
                    <button type="button" @click="forms[p.slug].pricing = 'free'"
                      class="rounded-lg px-4 py-1.5 text-sm font-semibold" :class="forms[p.slug].pricing === 'free' ? 'bg-primary text-white' : 'text-ink-2'">{{ t('Free') }}</button>
                    <button type="button" @click="forms[p.slug].pricing = 'premium'"
                      class="rounded-lg px-4 py-1.5 text-sm font-semibold" :class="forms[p.slug].pricing === 'premium' ? 'bg-primary text-white' : 'text-ink-2'">{{ t('Premium') }}</button>
                  </div>
                  <div v-if="forms[p.slug].pricing === 'premium'" class="flex items-center gap-1.5">
                    <span class="text-ink-muted">$</span>
                    <input v-model.number="forms[p.slug].price" type="number" min="1" max="9999" step="1"
                      class="w-28 rounded-lg border-line bg-surface-2 text-sm focus:border-primary focus:ring-primary" />
                    <span class="text-sm text-ink-muted">{{ t('one-time') }}</span>
                  </div>
                </div>
                <p v-if="forms[p.slug].pricing === 'premium' && !checkoutEnabled" class="mt-2 rounded-lg bg-amber-500/10 px-3 py-2 text-xs text-amber-600 dark:text-amber-300">
                  {{ t('Payments aren\'t live on this store yet — buyers can\'t check out until the store owner connects Stripe.') }}
                </p>
              </div>
            </div>
            <div class="mt-4 border-t border-line pt-4">
              <button type="button" :disabled="forms[p.slug].saving" @click="save(p)"
                class="rounded-c bg-primary px-5 py-2.5 text-sm font-bold text-white hover:bg-primary-600 disabled:opacity-60">
                {{ forms[p.slug].saving ? t('Saving…') : t('Save changes') }}
              </button>
            </div>
          </div>
        </div>
      </template>
    </section>
  </MarketingLayout>
</template>
