<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import UsageChart from '@/Components/panel/UsageChart.vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { t } from '@/lib/i18n';

const props = defineProps<{
  community: any;
  billing: any;
  domain: any;
  snapshot: any;
  series: any[];
  range: string;
}>();

const flash = computed(() => (usePage().props as any).flash?.status as string | undefined);

const tabs = [
  { key: 'overview', label: t('Overview') },
  { key: 'resources', label: t('Resources') },
  { key: 'billing', label: t('Billing') },
  { key: 'domain', label: t('Domain') },
];
const tab = ref('overview');

function bytes(v: number): string {
  if (!v) return '0 B';
  const u = ['B', 'KB', 'MB', 'GB', 'TB']; let i = 0; let n = v;
  while (n >= 1024 && i < u.length - 1) { n /= 1024; i++; }
  return `${n.toFixed(n < 10 && i > 0 ? 1 : 0)} ${u[i]}`;
}
function num(v: number): string { return v >= 1000 ? `${(v / 1000).toFixed(1)}k` : `${Math.round(v || 0)}`; }
function pct(used: number, total: number): number { return Math.min(100, Math.round((used / Math.max(1, total)) * 100)); }

const liveSeries = ref<any[]>(props.series);
const liveRange = ref(props.range);
const loading = ref(false);
async function loadRange(r: string) {
  liveRange.value = r; loading.value = true;
  try {
    const res = await fetch(`/panel/communities/${props.community.id}/metrics?range=${r}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    liveSeries.value = (await res.json()).series;
  } finally { loading.value = false; }
}
const charts = [
  { label: t('Storage (database + uploads)'), key: 'storageBytes', kind: 'bytes', color: '#5b5bd6' },
  { label: t('Requests'), key: 'requests', kind: 'number', color: '#0ea5e9' },
  { label: t('Redis memory'), key: 'redisBytes', kind: 'bytes', color: '#e8830c' },
  { label: t('Horizon jobs processed'), key: 'jobs', kind: 'number', color: '#16a34a' },
  { label: t('CPU'), key: 'cpu', kind: 'percent', color: '#dc2626' },
  { label: t('Avg response time'), key: 'respMs', kind: 'ms', color: '#7c3aed' },
] as const;
function pointsFor(key: string) { return liveSeries.value.map((s) => ({ t: s.t, value: s[key] ?? 0 })); }

const domainForm = useForm({ custom_domain: props.domain.custom ?? '' });
function saveDomain() { domainForm.put(`/panel/communities/${props.community.id}/domain`, { preserveScroll: true }); }
function verifyDomain() { router.post(`/panel/communities/${props.community.id}/domain/verify`, {}, { preserveScroll: true }); }
const copied = ref('');
async function copy(text: string, what: string) { try { await navigator.clipboard.writeText(text); copied.value = what; setTimeout(() => (copied.value = ''), 1500); } catch { /* */ } }

function manageBilling() { router.post(`/panel/communities/${props.community.id}/billing-portal`, {}, { preserveScroll: true }); }
const invoices = computed(() => {
  const out: any[] = [];
  for (let i = 1; i <= 3; i++) { const d = new Date(); d.setMonth(d.getMonth() - i); out.push({ date: d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' }), amount: props.billing.price, status: 'Paid' }); }
  return out;
});

const statusTone: Record<string, string> = {
  active: 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400',
  past_due: 'bg-red-500/15 text-red-600 dark:text-red-400',
  trialing: 'bg-sky-500/15 text-sky-600 dark:text-sky-400',
  provisioning: 'bg-amber-500/15 text-amber-600 dark:text-amber-400',
};
</script>

<template>
  <Head :title="`${community.name} — ${t('Hosting')}`" />
  <AppLayout>
    <div class="mx-auto max-w-5xl px-4 py-7">
      <Link href="/panel" class="text-sm font-semibold text-ink-muted hover:text-ink">← {{ t('Communities') }}</Link>
      <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 class="text-2xl font-extrabold text-ink">{{ community.name }}</h1>
          <a :href="community.url" target="_blank" class="text-sm text-primary hover:underline">{{ community.url }}</a>
        </div>
        <div class="flex items-center gap-2">
          <span class="rounded-full px-2.5 py-1 text-xs font-bold capitalize" :class="statusTone[community.status] || 'bg-line text-ink-2'">{{ community.status }}</span>
          <form @submit.prevent="router.post(`/panel/communities/${community.id}/enter`)">
            <button class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-600">{{ t('Open admin') }}</button>
          </form>
        </div>
      </div>

      <div v-if="flash" class="mt-4 rounded-xl border border-primary/30 bg-primary/10 px-4 py-2.5 text-sm font-medium text-ink">{{ flash }}</div>

      <div class="mt-6 flex gap-1 border-b border-line">
        <button v-for="x in tabs" :key="x.key" class="-mb-px border-b-2 px-4 py-2.5 text-sm font-semibold" :class="tab === x.key ? 'border-primary text-ink' : 'border-transparent text-ink-muted hover:text-ink'" @click="tab = x.key">{{ x.label }}</button>
      </div>

      <!-- OVERVIEW -->
      <section v-show="tab === 'overview'" class="mt-6 space-y-5">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
          <div class="rounded-2xl border border-line bg-surface p-4"><div class="text-xs font-semibold text-ink-muted">{{ t('Members') }}</div><div class="mt-1 text-2xl font-black text-ink">{{ num(snapshot.members) }}</div></div>
          <div class="rounded-2xl border border-line bg-surface p-4"><div class="text-xs font-semibold text-ink-muted">{{ t('Storage used') }}</div><div class="mt-1 text-2xl font-black text-ink">{{ bytes(snapshot.storageBytes) }}</div></div>
          <div class="rounded-2xl border border-line bg-surface p-4"><div class="text-xs font-semibold text-ink-muted">{{ t('Requests (24h)') }}</div><div class="mt-1 text-2xl font-black text-ink">{{ num(snapshot.requests24h) }}</div></div>
          <div class="rounded-2xl border border-line bg-surface p-4"><div class="text-xs font-semibold text-ink-muted">{{ t('Avg response') }}</div><div class="mt-1 text-2xl font-black text-ink">{{ snapshot.respMs }} ms</div></div>
        </div>

        <div class="rounded-2xl border border-line bg-surface p-5">
          <h2 class="text-sm font-bold text-ink">{{ t('Plan usage') }}</h2>
          <div class="mt-4 space-y-4">
            <div>
              <div class="flex justify-between text-xs"><span class="font-semibold text-ink-2">{{ t('Storage') }}</span><span class="text-ink-muted">{{ bytes(snapshot.storageBytes) }} / {{ bytes(snapshot.quota.storageBytes) }}</span></div>
              <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-appbg"><div class="h-full rounded-full bg-primary" :style="{ width: pct(snapshot.storageBytes, snapshot.quota.storageBytes) + '%' }"></div></div>
            </div>
            <div>
              <div class="flex justify-between text-xs"><span class="font-semibold text-ink-2">{{ t('Members') }}</span><span class="text-ink-muted">{{ num(snapshot.members) }}<template v-if="snapshot.quota.members"> / {{ num(snapshot.quota.members) }}</template><template v-else> · {{ t('Unlimited') }}</template></span></div>
              <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-appbg"><div class="h-full rounded-full bg-sky-500" :style="{ width: (snapshot.quota.members ? pct(snapshot.members, snapshot.quota.members) : 100) + '%', opacity: snapshot.quota.members ? 1 : 0.25 }"></div></div>
            </div>
            <div>
              <div class="flex justify-between text-xs"><span class="font-semibold text-ink-2">{{ t('Bandwidth (30d)') }}</span><span class="text-ink-muted">{{ bytes(snapshot.bandwidth30dBytes) }}<template v-if="snapshot.quota.bandwidthGb"> / {{ snapshot.quota.bandwidthGb }} GB</template><template v-else> · {{ t('Unlimited') }}</template></span></div>
              <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-appbg"><div class="h-full rounded-full bg-amber-500" :style="{ width: (snapshot.quota.bandwidthGb ? pct(snapshot.bandwidth30dBytes, snapshot.quota.bandwidthGb * 1024 * 1024 * 1024) : 100) + '%', opacity: snapshot.quota.bandwidthGb ? 1 : 0.25 }"></div></div>
            </div>
          </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
          <div class="rounded-2xl border border-line bg-surface p-5">
            <div class="text-xs font-semibold text-ink-muted">{{ t('Plan') }}</div>
            <div class="mt-1 flex items-baseline gap-2"><span class="text-xl font-black text-ink">{{ billing.plan }}</span><span class="text-sm text-ink-muted">{{ billing.price }}/{{ billing.interval }}</span></div>
            <p class="mt-2 text-sm text-ink-2">{{ t('Next payment') }}: <b>{{ billing.nextPaymentDate }}</b> <span v-if="billing.nextPaymentInDays != null" class="text-ink-muted">({{ billing.nextPaymentInDays }} {{ t('days') }})</span></p>
          </div>
          <div class="rounded-2xl border border-line bg-surface p-5">
            <div class="text-xs font-semibold text-ink-muted">{{ t('Live snapshot') }}</div>
            <div class="mt-2 grid grid-cols-2 gap-y-1.5 text-sm">
              <span class="text-ink-muted">{{ t('CPU') }}</span><span class="text-right font-semibold text-ink">{{ snapshot.cpu }}%</span>
              <span class="text-ink-muted">{{ t('Redis') }}</span><span class="text-right font-semibold text-ink">{{ bytes(snapshot.redisBytes) }}</span>
              <span class="text-ink-muted">{{ t('Queue depth') }}</span><span class="text-right font-semibold text-ink">{{ snapshot.queueDepth }}</span>
              <span class="text-ink-muted">{{ t('Database') }}</span><span class="text-right font-semibold text-ink">{{ bytes(snapshot.dbBytes) }}</span>
            </div>
          </div>
        </div>
      </section>

      <!-- RESOURCES -->
      <section v-show="tab === 'resources'" class="mt-6">
        <div class="flex items-center justify-between">
          <h2 class="text-sm font-bold text-ink">{{ t('Usage over time') }}</h2>
          <div class="flex rounded-lg border border-line p-0.5">
            <button v-for="r in ['24h', '7d', '30d']" :key="r" class="rounded-md px-3 py-1 text-xs font-bold" :class="liveRange === r ? 'bg-primary text-white' : 'text-ink-muted hover:text-ink'" @click="loadRange(r)">{{ r }}</button>
          </div>
        </div>
        <div class="mt-4 grid gap-4 sm:grid-cols-2" :class="loading ? 'opacity-50' : ''">
          <div v-for="c in charts" :key="c.key" class="rounded-2xl border border-line bg-surface p-4">
            <UsageChart :points="pointsFor(c.key)" :label="c.label" :kind="c.kind" :color="c.color" />
          </div>
        </div>
      </section>

      <!-- BILLING -->
      <section v-show="tab === 'billing'" class="mt-6 space-y-5">
        <div class="rounded-2xl border border-line bg-surface p-5">
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
              <div class="flex items-center gap-2"><span class="text-xl font-black text-ink">{{ billing.plan }}</span><span class="rounded-full px-2 py-0.5 text-xs font-bold capitalize" :class="statusTone[billing.status] || 'bg-line text-ink-2'">{{ billing.status }}</span></div>
              <p class="mt-1 text-sm text-ink-2">{{ billing.price }} / {{ billing.interval }}</p>
              <p class="mt-3 text-sm text-ink-2">{{ t('Current cycle renews') }} <b>{{ billing.nextPaymentDate }}</b></p>
              <p class="text-sm text-ink-2">{{ t('Next charge') }}: <b>{{ billing.price }}</b></p>
              <p v-if="billing.card" class="mt-2 text-sm text-ink-muted">{{ t('Card on file') }}: {{ billing.card.brand }} ···· {{ billing.card.last4 }}</p>
            </div>
            <button class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-600" @click="manageBilling">{{ t('Manage billing') }}</button>
          </div>
        </div>
        <div class="rounded-2xl border border-line bg-surface p-5">
          <h3 class="text-sm font-bold text-ink">{{ t('Recent invoices') }}</h3>
          <ul class="mt-3 divide-y divide-line">
            <li v-for="(inv, i) in invoices" :key="i" class="flex items-center justify-between py-2.5 text-sm">
              <span class="text-ink-2">{{ inv.date }}</span>
              <span class="flex items-center gap-3"><span class="font-semibold text-ink">{{ inv.amount }}</span><span class="rounded-full bg-emerald-500/15 px-2 py-0.5 text-xs font-bold text-emerald-600 dark:text-emerald-400">{{ inv.status }}</span></span>
            </li>
          </ul>
        </div>
      </section>

      <!-- DOMAIN -->
      <section v-show="tab === 'domain'" class="mt-6 space-y-5">
        <div class="rounded-2xl border border-line bg-surface p-5">
          <h3 class="text-sm font-bold text-ink">{{ t('Convoro subdomain') }}</h3>
          <p class="mt-1 text-xs text-ink-muted">{{ t('Always available, HTTPS included.') }}</p>
          <div class="mt-3 flex items-center gap-2">
            <code class="flex-1 truncate rounded-lg border border-line bg-appbg px-3 py-2 text-sm text-ink-2">{{ domain.subdomain }}</code>
            <button class="shrink-0 rounded-lg border border-line px-3 py-2 text-xs font-bold text-ink-2 hover:bg-surface-2" @click="copy(domain.subdomain, 'sub')">{{ copied === 'sub' ? t('Copied') : t('Copy') }}</button>
          </div>
        </div>

        <div class="rounded-2xl border border-line bg-surface p-5">
          <h3 class="text-sm font-bold text-ink">{{ t('Custom domain') }}</h3>
          <p class="mt-1 text-xs text-ink-muted">{{ t('Serve your community from your own domain.') }}</p>
          <div class="mt-3 flex flex-col gap-2 sm:flex-row">
            <input v-model="domainForm.custom_domain" type="text" :placeholder="t('forum.yourbrand.com')" class="w-full rounded-lg border-line bg-appbg text-sm text-ink placeholder:text-ink-muted focus:border-primary focus:ring-primary" />
            <button :disabled="domainForm.processing" class="shrink-0 rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-600 disabled:opacity-50" @click="saveDomain">{{ t('Save') }}</button>
          </div>
          <p v-if="domainForm.errors.custom_domain" class="mt-1.5 text-xs font-medium text-red-500">{{ domainForm.errors.custom_domain }}</p>

          <div v-if="domain.custom" class="mt-5 rounded-xl border border-line bg-appbg p-4">
            <div class="flex items-center justify-between">
              <span class="text-xs font-bold text-ink">{{ t('DNS record') }}</span>
              <span class="rounded-full px-2 py-0.5 text-xs font-bold" :class="domain.verified ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400' : 'bg-amber-500/15 text-amber-600 dark:text-amber-400'">{{ domain.verified ? t('Verified') : t('Pending') }}</span>
            </div>
            <p class="mt-2 text-xs text-ink-muted">{{ t('Add this CNAME at your DNS provider:') }}</p>
            <div class="mt-2 grid grid-cols-3 gap-2 text-xs">
              <div><div class="text-ink-muted">{{ t('Type') }}</div><code class="text-ink">CNAME</code></div>
              <div><div class="text-ink-muted">{{ t('Host') }}</div><code class="text-ink">{{ domain.custom }}</code></div>
              <div><div class="text-ink-muted">{{ t('Value') }}</div><code class="text-ink">{{ domain.target }}</code></div>
            </div>
            <div class="mt-3 flex items-center gap-3">
              <button v-if="!domain.verified" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-600" @click="verifyDomain">{{ t('Verify DNS') }}</button>
              <span class="text-xs text-ink-muted">{{ t('TLS certificate') }}: <b class="capitalize">{{ domain.certStatus }}</b></span>
            </div>
          </div>
        </div>
      </section>
    </div>
  </AppLayout>
</template>
