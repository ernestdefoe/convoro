<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';

interface SettingField { key: string; label: string; type?: string; default?: unknown; help?: string; options?: { value: string; label: string }[] }
interface Ext { id: string; name: string; version: string; description: string; author: string; type: string; premium: boolean; enabled: boolean; removable: boolean; settings: SettingField[]; values: Record<string, unknown>; adminUrl: string | null }

const props = defineProps<{
  update: { current: string; latest: string; available: boolean; url: string | null; checkedAt: string | null; enabled: boolean };
  extensions: Ext[];
  composer: { available: boolean; task: { state: string; action?: string; package?: string; output?: string; finishedAt?: string | null } };
  catalog: { slug: string; name: string; type: string; tagline: string; version: string; price: string; free: boolean; download_url: string | null }[];
}>();

const installingSlug = ref<string | null>(null);
function installCatalog(item: { slug: string; free: boolean }) {
  installingSlug.value = item.slug;
  router.post('/admin/marketplace/catalog/install', { slug: item.slug }, {
    preserveScroll: true,
    onFinish: () => (installingSlug.value = null),
  });
}

const composerPkg = ref('');
function composerInstall() {
  if (!composerPkg.value.trim()) return;
  router.post('/admin/marketplace/composer', { action: 'require', package: composerPkg.value.trim() }, {
    preserveScroll: true,
    onSuccess: () => (composerPkg.value = ''),
  });
}

const page = usePage();
const result = computed(() => (page.props as any).flash?.extResult ?? null);

// Filter + search over installed items.
const filter = ref<'all' | 'extension' | 'theme'>('all');
const query = ref('');
const counts = computed(() => ({
  all: props.extensions.length,
  extension: props.extensions.filter((e) => e.type === 'extension').length,
  theme: props.extensions.filter((e) => e.type === 'theme').length,
}));
const pills = [
  { key: 'all' as const, label: 'All' },
  { key: 'extension' as const, label: 'Extensions' },
  { key: 'theme' as const, label: 'Themes' },
];
const visibleExtensions = computed(() => {
  const q = query.value.trim().toLowerCase();
  return props.extensions.filter((e) => {
    if (filter.value !== 'all' && e.type !== filter.value) return false;
    if (!q) return true;
    return [e.name, e.description, e.author, e.id].some((v) => (v || '').toLowerCase().includes(q));
  });
});

function checkUpdates() {
  router.post('/admin/system/check-updates', {}, { preserveScroll: true });
}


const licenseKey = ref('');
const redeeming = ref(false);
function redeemLicense() {
  if (!licenseKey.value.trim()) return;
  redeeming.value = true;
  router.post('/admin/marketplace/license', { key: licenseKey.value.trim() }, {
    preserveScroll: true,
    onSuccess: () => (licenseKey.value = ''),
    onFinish: () => (redeeming.value = false),
  });
}

function toggle(ext: { id: string; enabled: boolean }) {
  const url = ext.enabled ? '/admin/marketplace/disable' : '/admin/marketplace/enable';
  router.post(url, { id: ext.id }, { preserveScroll: true });
}

function uninstall(ext: { id: string; name: string }) {
  if (!confirm(`Remove “${ext.name}”? This deletes the extension and rolls back its database tables.`)) return;
  router.post('/admin/marketplace/uninstall', { id: ext.id }, { preserveScroll: true });
}
</script>

<template>
  <Head title="Admin · Marketplace" />
  <AdminLayout>
    <template #title>Marketplace</template>
    <template #subtitle>Extensions, themes &amp; software updates</template>

    <!-- Software update status -->
    <section class="mb-6 rounded-2xl border border-white/5 bg-[#14172a] p-5">
      <div class="flex flex-wrap items-center gap-4">
        <div>
          <div class="text-sm text-slate-400">Convoro</div>
          <div class="text-xl font-extrabold text-white">{{ update.current }}</div>
        </div>
        <div v-if="update.available" class="rounded-xl border border-indigo-500/40 bg-indigo-500/10 px-4 py-2 text-sm text-indigo-200">
          Version <strong>{{ update.latest }}</strong> is available.
          <a v-if="update.url" :href="update.url" target="_blank" class="ml-1 font-semibold underline">Details</a>
        </div>
        <div v-else class="rounded-xl bg-emerald-500/10 px-4 py-2 text-sm text-emerald-300">You’re on the latest version.</div>
        <button class="ml-auto rounded-lg bg-indigo-500 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-600" @click="checkUpdates">Check for updates</button>
      </div>
    </section>

    <!-- Result flash -->
    <p v-if="result" class="mb-4 rounded-lg px-4 py-2 text-sm" :class="result.ok ? 'bg-emerald-500/10 text-emerald-300' : 'bg-red-500/10 text-red-300'">{{ result.message }}</p>

    <!-- Install options -->
    <section class="mb-6 rounded-2xl border border-white/5 bg-[#14172a] p-5">
      <h2 class="text-sm font-bold uppercase tracking-wide text-slate-400">Add an extension</h2>
      <p class="mt-1 text-xs text-slate-500">Install from the <strong>catalog</strong> below (extensions are published from GitHub), redeem a premium <strong>license key</strong>, or — on Composer-capable hosts — pull a package directly.</p>

      <!-- Redeem a premium license key from the Convoro store -->
      <div class="mt-5">
        <h3 class="text-xs font-bold uppercase tracking-wide text-slate-400">Have a license key?</h3>
        <p class="mt-1 text-xs text-slate-500">Bought a premium extension or theme from the Convoro store? Paste your key to install it here.</p>
        <div class="mt-3 flex flex-wrap items-center gap-3">
          <input v-model="licenseKey" type="text" placeholder="CONV-XXXX-XXXX-XXXX-XXXX" :disabled="redeeming"
            class="w-72 rounded-lg border-white/10 bg-[#0f1120] font-mono text-sm uppercase text-slate-100 focus:border-indigo-500 focus:ring-indigo-500" />
          <button type="button" :disabled="redeeming || !licenseKey.trim()" @click="redeemLicense"
            class="rounded-lg bg-indigo-500 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-600 disabled:opacity-60">
            {{ redeeming ? 'Redeeming…' : 'Redeem & install' }}
          </button>
        </div>
      </div>

      <!-- Composer install path (only when Composer is available) -->
      <div class="mt-5 border-t border-white/5 pt-5">
        <h3 class="text-xs font-bold uppercase tracking-wide text-slate-400">Install via Composer</h3>
        <template v-if="composer.available">
          <p class="mt-1 text-xs text-slate-500">Have Composer (no SSH needed)? Pull a package straight from Packagist — dependencies resolved automatically.</p>
          <div class="mt-3 flex flex-wrap items-center gap-3">
            <input v-model="composerPkg" type="text" placeholder="vendor/package" :disabled="composer.task.state === 'running'"
              class="w-72 rounded-lg border-white/10 bg-[#0f1120] font-mono text-sm text-slate-100 focus:border-indigo-500 focus:ring-indigo-500" />
            <button type="button" :disabled="composer.task.state === 'running' || !composerPkg.trim()" @click="composerInstall"
              class="rounded-lg bg-indigo-500 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-600 disabled:opacity-60">
              {{ composer.task.state === 'running' ? 'Working…' : 'Install' }}
            </button>
          </div>
          <p v-if="composer.task && composer.task.state !== 'idle'" class="mt-3 text-sm"
            :class="composer.task.state === 'failed' ? 'text-red-300' : composer.task.state === 'done' ? 'text-emerald-300' : 'text-slate-400'">
            <span class="font-semibold">{{ composer.task.action }} {{ composer.task.package }}</span> —
            {{ composer.task.state === 'running' ? 'in progress…' : composer.task.state }}
          </p>
        </template>
        <p v-else class="mt-1 text-xs text-slate-500">Composer isn’t available on this server — use the zip upload above instead. (Both work without a terminal.)</p>
      </div>
    </section>

    <!-- Installed extensions -->
    <section class="rounded-2xl border border-white/5 bg-[#14172a] p-5">
      <div class="mb-4 flex flex-wrap items-center gap-3">
        <h2 class="text-sm font-bold uppercase tracking-wide text-slate-400">Installed</h2>

        <!-- Type pills -->
        <div class="flex items-center gap-1 rounded-lg bg-[#0f1120] p-1">
          <button v-for="p in pills" :key="p.key" type="button" @click="filter = p.key"
            class="rounded-md px-3 py-1 text-xs font-semibold transition"
            :class="filter === p.key ? 'bg-indigo-500 text-white' : 'text-slate-400 hover:text-slate-200'">
            {{ p.label }}
            <span class="ml-1 opacity-70">{{ counts[p.key] }}</span>
          </button>
        </div>

        <!-- Search -->
        <div class="ml-auto flex items-center gap-2 rounded-lg border border-white/10 bg-[#0f1120] px-3 py-1.5">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="text-slate-500"><circle cx="11" cy="11" r="7" /><path d="m21 21-4.3-4.3" /></svg>
          <input v-model="query" type="search" placeholder="Search…" class="w-44 border-0 bg-transparent p-0 text-sm text-slate-100 placeholder:text-slate-500 focus:ring-0" />
        </div>
      </div>

      <div v-if="!extensions.length" class="rounded-xl border border-dashed border-white/10 p-8 text-center text-sm text-slate-400">
        No extensions installed yet. Upload one above to get started.
      </div>
      <div v-else-if="!visibleExtensions.length" class="rounded-xl border border-dashed border-white/10 p-8 text-center text-sm text-slate-400">
        Nothing matches your filter.
      </div>

      <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <div v-for="ext in visibleExtensions" :key="ext.id"
          class="flex flex-col rounded-2xl border bg-[#0f1120] p-5 transition"
          :class="ext.enabled ? 'border-indigo-500/30' : 'border-white/5'">
          <!-- Header: icon + name + version -->
          <div class="flex items-start gap-3">
            <div class="grid h-11 w-11 shrink-0 place-items-center rounded-xl text-lg font-bold"
              :class="ext.type === 'theme' ? 'bg-fuchsia-500/15 text-fuchsia-300' : 'bg-indigo-500/15 text-indigo-300'">
              {{ ext.type === 'theme' ? '🎨' : '🧩' }}
            </div>
            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-1.5">
                <span class="truncate font-bold text-white">{{ ext.name }}</span>
                <span class="shrink-0 rounded bg-white/5 px-1.5 py-0.5 text-[11px] text-slate-400">v{{ ext.version }}</span>
              </div>
              <p v-if="ext.author" class="mt-0.5 truncate text-[11px] text-slate-500">by {{ ext.author }}</p>
            </div>
          </div>

          <!-- Description -->
          <p class="mt-3 line-clamp-3 flex-1 text-sm text-slate-400">{{ ext.description }}</p>

          <!-- Badges -->
          <div class="mt-3 flex flex-wrap items-center gap-1.5">
            <span class="rounded bg-sky-500/15 px-1.5 py-0.5 text-[11px] font-semibold capitalize text-sky-300">{{ ext.type }}</span>
            <span v-if="ext.premium" class="rounded bg-amber-500/15 px-1.5 py-0.5 text-[11px] font-semibold text-amber-300">Premium</span>
            <span v-if="ext.enabled" class="rounded bg-emerald-500/15 px-1.5 py-0.5 text-[11px] font-semibold text-emerald-300">Enabled</span>
            <span v-if="!ext.removable" class="rounded bg-white/5 px-1.5 py-0.5 text-[11px] text-slate-500" title="Ships with Convoro">Bundled</span>
          </div>

          <!-- Footer actions -->
          <div class="mt-4 flex items-center gap-3 border-t border-white/5 pt-3">
            <button type="button" @click.stop="toggle(ext)"
              class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full transition"
              :class="ext.enabled ? 'bg-indigo-500' : 'bg-white/15'"
              :aria-pressed="ext.enabled" :aria-label="(ext.enabled ? 'Disable ' : 'Enable ') + ext.name">
              <span class="inline-block h-4 w-4 transform rounded-full bg-white transition" :class="ext.enabled ? 'translate-x-6' : 'translate-x-1'" />
            </button>
            <span class="text-xs font-semibold" :class="ext.enabled ? 'text-emerald-300' : 'text-slate-500'">{{ ext.enabled ? 'Enabled' : 'Disabled' }}</span>

            <Link v-if="ext.settings.length || ext.adminUrl" :href="ext.adminUrl || `/admin/extensions/${ext.id}`"
              class="ml-auto inline-flex items-center gap-1 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-indigo-300 hover:bg-indigo-500/10">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 1 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4" /></svg>
              Settings
            </Link>
            <button v-if="ext.removable" type="button" @click.stop="uninstall(ext)"
              :class="(ext.settings.length || ext.adminUrl) ? '' : 'ml-auto'"
              class="rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-400 hover:bg-red-500/10 hover:text-red-400">Remove</button>
          </div>
        </div>
      </div>
    </section>

    <!-- Catalog from the Convoro store -->
    <section class="mt-6 rounded-2xl border border-white/5 bg-[#14172a] p-5">
      <h2 class="mb-1 text-sm font-bold uppercase tracking-wide text-slate-400">Browse the catalog</h2>
      <p class="mb-4 text-xs text-slate-500">Extensions &amp; themes from the Convoro store. Free items install in one click; premium items unlock with a license key.</p>

      <div v-if="!catalog.length" class="rounded-xl border border-dashed border-white/10 p-8 text-center text-sm text-slate-400">
        Nothing new in the catalog right now — everything available is already installed.
      </div>

      <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <div v-for="item in catalog" :key="item.slug" class="flex flex-col rounded-2xl border border-white/5 bg-[#0f1120] p-5">
          <div class="flex items-start gap-3">
            <div class="grid h-11 w-11 shrink-0 place-items-center rounded-xl text-lg"
              :class="item.type === 'theme' ? 'bg-fuchsia-500/15 text-fuchsia-300' : 'bg-indigo-500/15 text-indigo-300'">
              {{ item.type === 'theme' ? '🎨' : '🧩' }}
            </div>
            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-1.5">
                <span class="truncate font-bold text-white">{{ item.name }}</span>
                <span class="rounded bg-white/5 px-1.5 py-0.5 text-[11px] text-slate-400">v{{ item.version }}</span>
              </div>
              <span class="text-[11px] font-semibold uppercase tracking-wide" :class="item.free ? 'text-emerald-300' : 'text-amber-300'">{{ item.price }}</span>
            </div>
          </div>
          <p class="mt-3 line-clamp-2 flex-1 text-sm text-slate-400">{{ item.tagline }}</p>
          <div class="mt-4 border-t border-white/5 pt-3">
            <button v-if="item.free" type="button" :disabled="installingSlug === item.slug" @click="installCatalog(item)"
              class="w-full rounded-lg bg-indigo-500 px-3 py-2 text-sm font-semibold text-white hover:bg-indigo-600 disabled:opacity-60">
              {{ installingSlug === item.slug ? 'Installing…' : 'Download &amp; install' }}
            </button>
            <a v-else :href="`https://convoro.co/extensions/${item.slug}`" target="_blank" rel="noopener"
              class="block rounded-lg border border-white/10 px-3 py-2 text-center text-sm font-semibold text-amber-300 hover:bg-white/5">
              Buy on convoro.co — then redeem your key above
            </a>
          </div>
        </div>
      </div>
    </section>

  </AdminLayout>
</template>
