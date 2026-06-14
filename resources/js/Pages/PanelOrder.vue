<script setup lang="ts">
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { t as tr } from '@/lib/i18n';

const plans = [
  { key: 'starter', name: 'Starter', price: 19, icon: '🌱', blurb: 'For a new or small community finding its feet.',
    features: ['1 community', 'Up to 500 members', '5 GB storage', 'Custom domain + SSL', 'Community support'], cta: 'Choose Starter', featured: false },
  { key: 'pro', name: 'Pro', price: 49, icon: '🚀', blurb: 'For active communities that want the full experience.',
    features: ['3 communities', 'Up to 5,000 members', '50 GB storage', 'Realtime, push & digests', 'All first-party extensions', 'Priority support'], cta: 'Start free trial', featured: true },
  { key: 'scale', name: 'Scale', price: 149, icon: '🏢', blurb: 'For large or multi-brand communities and teams.',
    features: ['10 communities', 'Unlimited members', '250 GB storage', 'Single sign-on (SSO)', 'Dedicated resources + SLA', 'Onboarding & migration'], cta: 'Contact sales', featured: false },
];
</script>

<template>
  <Head :title="tr('Convoro hosting')" />
  <AppLayout>
    <div class="mx-auto max-w-3xl">
      <h1 class="text-2xl font-black tracking-tight">{{ tr('Convoro hosting') }}</h1>
      <p class="mt-1 max-w-[46ch] text-ink-2">{{ tr('Launch a fully-managed community in about a minute — no server to run, and your Convoro account is the admin from day one.') }}</p>
      <p class="mt-1 text-sm text-ink-muted">🗓 {{ tr('Billed monthly · save 20% with annual') }}</p>

      <div class="mt-6 grid gap-3 sm:grid-cols-3">
        <div v-for="p in plans" :key="p.key" class="flex flex-col rounded-c border bg-surface p-5"
          :class="p.featured ? 'border-2 border-primary' : 'border-line'" style="position:relative">
          <span v-if="p.featured" class="absolute -top-2.5 left-5 rounded bg-primary px-2.5 py-0.5 text-[11px] font-semibold text-white">{{ tr('Most popular') }}</span>
          <div class="text-2xl">{{ p.icon }}</div>
          <p class="mt-2 font-semibold text-ink">{{ p.name }}</p>
          <div class="mb-2 flex items-baseline gap-1"><span class="text-2xl font-extrabold">${{ p.price }}</span><span class="text-sm text-ink-2">/mo</span></div>
          <p class="mb-3 text-sm text-ink-2">{{ p.blurb }}</p>
          <ul class="mb-4 flex flex-col gap-1.5 text-sm text-ink-2">
            <li v-for="f in p.features" :key="f">✓ {{ f }}</li>
          </ul>
          <Link href="/panel" class="mt-auto rounded-lg px-4 py-2 text-center text-sm font-semibold"
            :class="p.featured ? 'bg-primary text-white hover:bg-primary-600' : 'border border-line text-ink-2 hover:bg-surface-2'">{{ p.cta }}</Link>
        </div>
      </div>

      <p class="mt-5 text-center text-xs leading-relaxed text-ink-muted">{{ tr('Every plan includes daily backups, automatic updates, the extension marketplace, and one Convoro account across all your communities and the support forum.') }}</p>
    </div>
  </AppLayout>
</template>
