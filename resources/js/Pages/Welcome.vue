<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import ConvoroLogo from '@/Components/ConvoroLogo.vue';
import { t } from '@/lib/i18n';

defineProps<{ canLogin?: boolean; canRegister?: boolean }>();

const features = [
  { icon: '💬', title: t('WYSIWYG composer'), desc: t('A true rich editor powered by TipTap — no Markdown, ever.') },
  { icon: '⚡', title: t('Live threads'), desc: t('Realtime posts, presence and typing indicators out of the box.') },
  { icon: '🎨', title: t('Live theme editor'), desc: t('Redesign your community in the browser, in real time.') },
  { icon: '🧩', title: t('Extension manager'), desc: t('Browse a marketplace and install with one click.') },
  { icon: '🖼️', title: t('Smart media'), desc: t('Uploads auto-compress and convert to WebP.') },
  { icon: '📱', title: t('PWA + push'), desc: t('Installable app with web push notifications.') },
];
</script>

<template>
  <Head :title="t('Welcome')" />

  <div class="min-h-screen bg-appbg text-ink">
    <header class="border-b border-line bg-surface/80 backdrop-blur">
      <div class="mx-auto flex h-16 max-w-6xl items-center px-6">
        <ConvoroLogo />
        <nav class="ml-auto flex items-center gap-3">
          <template v-if="canLogin">
            <Link href="/login" class="rounded-lg px-4 py-2 text-sm font-semibold text-ink-2 hover:bg-surface-2">{{ t('Log in') }}</Link>
            <Link
              v-if="canRegister"
              href="/register"
              class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white shadow-lg shadow-primary/40 hover:bg-primary-600"
            >{{ t('Get started') }}</Link>
          </template>
        </nav>
      </div>
    </header>

    <main class="mx-auto max-w-6xl px-6">
      <section class="py-20 text-center">
        <span class="inline-flex items-center gap-2 rounded-full border border-line bg-surface px-4 py-1.5 text-sm font-semibold text-primary-700">
          <span class="h-2 w-2 rounded-full bg-primary"></span> {{ t('Convoro 1.0 — in development') }}
        </span>
        <h1 class="mx-auto mt-7 max-w-3xl text-5xl font-extrabold leading-tight tracking-tight">
          {{ t('The modern community platform your members will actually love.') }}
        </h1>
        <p class="mx-auto mt-5 max-w-2xl text-lg text-ink-2">
          {{ t('The proven features of leading forum software — a polished, fast, fully-themeable forum with realtime threads, a true WYSIWYG editor and one-click extensions.') }}
        </p>
        <div class="mt-9 flex items-center justify-center gap-3">
          <Link href="/register" class="rounded-c bg-primary px-6 py-3 font-semibold text-white shadow-lg shadow-primary/40 hover:bg-primary-600">
            {{ t('Start your community') }}
          </Link>
          <Link href="/login" class="rounded-c border border-line bg-surface px-6 py-3 font-semibold text-ink hover:bg-surface-2">
            {{ t('Live demo') }}
          </Link>
        </div>
      </section>

      <section class="grid grid-cols-1 gap-4 pb-24 sm:grid-cols-2 lg:grid-cols-3">
        <div
          v-for="f in features"
          :key="f.title"
          class="rounded-c border border-line bg-surface p-6 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md"
        >
          <div class="text-2xl">{{ f.icon }}</div>
          <h3 class="mt-3 text-base font-bold">{{ f.title }}</h3>
          <p class="mt-1.5 text-sm text-ink-2">{{ f.desc }}</p>
        </div>
      </section>
    </main>

    <footer class="border-t border-line py-8 text-center text-sm text-ink-muted">
      Convoro — built independently. © 2026
    </footer>
  </div>
</template>
