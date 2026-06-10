<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

const props = defineProps<{
  products: any[];
  stripe: { key: string; secretSet: boolean; webhookSet: boolean; webhookUrl: string };
}>();

// Stripe keys
const stripe = useForm({ key: props.stripe.key ?? '', secret: '', webhook_secret: '' });
function saveStripe() { stripe.post('/admin/store/stripe', { preserveScroll: true }); }

// Link a GitHub repo (registry)
const repo = ref('');
const linking = ref(false);
function linkRepo() {
  if (!repo.value.trim()) return;
  linking.value = true;
  router.post('/admin/store/link', { repo: repo.value.trim() }, {
    preserveScroll: true,
    onSuccess: () => (repo.value = ''),
    onFinish: () => (linking.value = false),
  });
}
function refreshRepo(p: any) {
  router.post(`/admin/store/products/${p.id}/refresh`, {}, { preserveScroll: true });
}

// Product editor
const blank = () => ({ id: null, name: '', type: 'extension', tagline: '', description: '', package: '', version: '1.0.0', price_dollars: '19', image: '', published: true, featured: false });
const editing = ref<any>(null);
function openNew() { editing.value = blank(); }
function openEdit(p: any) { editing.value = { ...p, price_dollars: (p.price_cents / 100).toString() }; }
function close() { editing.value = null; }

function saveProduct() {
  const e = editing.value;
  const payload = {
    name: e.name, type: e.type, tagline: e.tagline, description: e.description,
    package: e.package, version: e.version, image: e.image,
    price_cents: Math.round(parseFloat(e.price_dollars || '0') * 100),
    published: e.published, featured: e.featured,
  };
  const opts = { preserveScroll: true, onSuccess: () => close() };
  if (e.id) router.put(`/admin/store/products/${e.id}`, payload, opts);
  else router.post('/admin/store/products', payload, opts);
}
function destroyProduct(p: any) {
  if (confirm(`Delete “${p.name}”? Existing licenses are kept but the product is removed.`)) {
    router.delete(`/admin/store/products/${p.id}`, { preserveScroll: true });
  }
}
function uploadFile(p: any, e: Event) {
  const file = (e.target as HTMLInputElement).files?.[0];
  if (!file) return;
  router.post(`/admin/store/products/${p.id}/file`, { file }, { forceFormData: true, preserveScroll: true });
}
</script>

<template>
  <Head title="Admin · Store" />
  <AdminLayout>
    <template #title>Store</template>
    <template #subtitle>Sell premium extensions &amp; themes from convoro.co</template>

    <!-- Stripe -->
    <section class="mb-6 max-w-2xl rounded-2xl border border-white/5 bg-[#14172a] p-6">
      <h2 class="text-sm font-bold uppercase tracking-wide text-slate-400">Stripe</h2>
      <p class="mt-1 text-xs text-slate-500">Until keys are set, the store is browsable but checkout is disabled. After saving, add a webhook in Stripe pointing to <code class="text-slate-300">{{ props.stripe.webhookUrl }}</code> (event: <code class="text-slate-300">checkout.session.completed</code>).</p>
      <div class="mt-4 space-y-3">
        <div>
          <label class="block text-sm font-medium text-slate-300">Publishable key</label>
          <input v-model="stripe.key" type="text" placeholder="pk_live_…" class="mt-1 w-full rounded-lg border-white/10 bg-[#0f1120] font-mono text-sm text-slate-100 focus:border-indigo-500 focus:ring-indigo-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-300">Secret key <span v-if="props.stripe.secretSet" class="text-emerald-400">(set)</span></label>
          <input v-model="stripe.secret" type="password" :placeholder="props.stripe.secretSet ? '•••• leave blank to keep' : 'sk_live_…'" class="mt-1 w-full rounded-lg border-white/10 bg-[#0f1120] font-mono text-sm text-slate-100 focus:border-indigo-500 focus:ring-indigo-500" />
        </div>
        <div>
          <label class="block text-sm font-medium text-slate-300">Webhook signing secret <span v-if="props.stripe.webhookSet" class="text-emerald-400">(set)</span></label>
          <input v-model="stripe.webhook_secret" type="password" :placeholder="props.stripe.webhookSet ? '•••• leave blank to keep' : 'whsec_…'" class="mt-1 w-full rounded-lg border-white/10 bg-[#0f1120] font-mono text-sm text-slate-100 focus:border-indigo-500 focus:ring-indigo-500" />
        </div>
        <button @click="saveStripe" :disabled="stripe.processing" class="rounded-lg bg-indigo-500 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-600">Save Stripe settings</button>
      </div>
    </section>

    <!-- Link a GitHub repo (registry) -->
    <section class="mb-6 max-w-2xl rounded-2xl border border-white/5 bg-[#14172a] p-6">
      <h2 class="text-sm font-bold uppercase tracking-wide text-slate-400">Publish from GitHub</h2>
      <p class="mt-1 text-xs text-slate-500">Link a public GitHub repo with an <code>extension.json</code> at its root — Convoro reads the manifest and lists it in the catalog. Updates pull from the latest release (or the default branch). No zips.</p>
      <div class="mt-3 flex flex-wrap items-center gap-3">
        <input v-model="repo" type="text" placeholder="owner/repository" class="w-72 rounded-lg border-white/10 bg-[#0f1120] font-mono text-sm text-slate-100 focus:border-indigo-500 focus:ring-indigo-500" />
        <button type="button" :disabled="linking || !repo.trim()" @click="linkRepo" class="rounded-lg bg-indigo-500 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-600 disabled:opacity-60">
          {{ linking ? 'Linking…' : 'Link repo' }}
        </button>
      </div>
    </section>

    <!-- Products -->
    <section class="rounded-2xl border border-white/5 bg-[#14172a] p-6">
      <div class="mb-4 flex items-center">
        <h2 class="text-sm font-bold uppercase tracking-wide text-slate-400">Products</h2>
        <button @click="openNew" class="ml-auto rounded-lg bg-indigo-500 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-600">+ New product</button>
      </div>

      <div v-if="!products.length" class="rounded-xl border border-dashed border-white/10 p-8 text-center text-sm text-slate-400">No products yet.</div>

      <div v-else class="space-y-2.5">
        <div v-for="p in products" :key="p.id" class="flex flex-wrap items-center gap-3 rounded-xl border border-white/5 bg-[#0f1120] p-4">
          <div class="grid h-10 w-10 place-items-center rounded-lg bg-indigo-500/15 text-lg">{{ p.type === 'theme' ? '🎨' : '🧩' }}</div>
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
              <span class="font-semibold text-white">{{ p.name }}</span>
              <span v-if="!p.published" class="rounded bg-white/10 px-1.5 py-0.5 text-[11px] text-slate-400">Draft</span>
              <span v-if="p.featured" class="rounded bg-amber-500/15 px-1.5 py-0.5 text-[11px] text-amber-300">Featured</span>
              <span v-if="p.source === 'github'" class="rounded bg-sky-500/15 px-1.5 py-0.5 text-[11px] text-sky-300">GitHub</span>
              <span v-if="p.hasDownload" class="rounded bg-emerald-500/15 px-1.5 py-0.5 text-[11px] text-emerald-300">{{ p.source === 'github' ? 'Linked ✓' : 'File ✓' }}</span>
            </div>
            <div class="text-xs text-slate-500">${{ (p.price_cents/100).toFixed(2) }} · {{ p.sales }} sold · {{ p.source === 'github' ? p.repo : (p.package || 'no package id') }}</div>
          </div>
          <button v-if="p.source === 'github'" @click="refreshRepo(p)" class="rounded-lg border border-white/10 px-2.5 py-1.5 text-xs font-semibold text-slate-300 hover:bg-white/5">Refresh</button>
          <label v-else class="cursor-pointer rounded-lg border border-white/10 px-2.5 py-1.5 text-xs font-semibold text-slate-300 hover:bg-white/5">
            Upload zip<input type="file" accept=".zip" class="hidden" @change="(e) => uploadFile(p, e)" />
          </label>
          <button @click="openEdit(p)" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-300 hover:bg-white/5">Edit</button>
          <button @click="destroyProduct(p)" class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-400 hover:bg-red-500/10 hover:text-red-400">Delete</button>
        </div>
      </div>
    </section>

    <!-- Editor modal -->
    <div v-if="editing" class="fixed inset-0 z-[80] flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-black/60" @click="close" />
      <div class="relative max-h-[88vh] w-full max-w-lg overflow-y-auto rounded-2xl border border-white/10 bg-[#14172a] p-6 shadow-2xl">
        <h3 class="mb-4 font-bold text-white">{{ editing.id ? 'Edit product' : 'New product' }}</h3>
        <div class="space-y-3">
          <div><label class="block text-sm text-slate-300">Name</label><input v-model="editing.name" class="mt-1 w-full rounded-lg border-white/10 bg-[#0f1120] text-slate-100 focus:border-indigo-500 focus:ring-indigo-500" /></div>
          <div class="flex gap-3">
            <div class="flex-1"><label class="block text-sm text-slate-300">Type</label>
              <select v-model="editing.type" class="mt-1 w-full rounded-lg border-white/10 bg-[#0f1120] text-slate-100 focus:border-indigo-500 focus:ring-indigo-500"><option value="extension">Extension</option><option value="theme">Theme</option></select></div>
            <div class="w-28"><label class="block text-sm text-slate-300">Price (USD)</label><input v-model="editing.price_dollars" type="number" min="0" step="1" class="mt-1 w-full rounded-lg border-white/10 bg-[#0f1120] text-slate-100 focus:border-indigo-500 focus:ring-indigo-500" /></div>
            <div class="w-24"><label class="block text-sm text-slate-300">Version</label><input v-model="editing.version" class="mt-1 w-full rounded-lg border-white/10 bg-[#0f1120] text-slate-100 focus:border-indigo-500 focus:ring-indigo-500" /></div>
          </div>
          <div><label class="block text-sm text-slate-300">Package id <span class="text-slate-500">(the extension id the license unlocks)</span></label><input v-model="editing.package" placeholder="convoro-group-messages" class="mt-1 w-full rounded-lg border-white/10 bg-[#0f1120] font-mono text-sm text-slate-100 focus:border-indigo-500 focus:ring-indigo-500" /></div>
          <div><label class="block text-sm text-slate-300">Tagline</label><input v-model="editing.tagline" class="mt-1 w-full rounded-lg border-white/10 bg-[#0f1120] text-slate-100 focus:border-indigo-500 focus:ring-indigo-500" /></div>
          <div><label class="block text-sm text-slate-300">Description</label><textarea v-model="editing.description" rows="4" class="mt-1 w-full rounded-lg border-white/10 bg-[#0f1120] text-slate-100 focus:border-indigo-500 focus:ring-indigo-500"></textarea></div>
          <div class="flex gap-5">
            <label class="flex items-center gap-2 text-sm text-slate-300"><input type="checkbox" v-model="editing.published" class="rounded border-white/10 bg-[#0f1120] text-indigo-500" /> Published</label>
            <label class="flex items-center gap-2 text-sm text-slate-300"><input type="checkbox" v-model="editing.featured" class="rounded border-white/10 bg-[#0f1120] text-indigo-500" /> Featured</label>
          </div>
        </div>
        <div class="mt-5 flex gap-2">
          <button @click="saveProduct" class="rounded-lg bg-indigo-500 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-600">Save</button>
          <button @click="close" class="rounded-lg px-3 py-2 text-sm font-semibold text-slate-400 hover:bg-white/5">Cancel</button>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>
