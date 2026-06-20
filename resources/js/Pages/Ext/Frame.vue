<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { onMounted, onBeforeUnmount } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';

// A generic shell for server-rendered extension pages: the extension provides
// plain body HTML plus optional page-scoped CSS/JS, and we render it inside the
// real forum layout so the header, footer and theme are identical everywhere.
const props = defineProps<{ title: string; bodyHtml: string; css?: string; js?: string; embed?: boolean }>();

let styleEl: HTMLStyleElement | null = null;
let scriptEl: HTMLScriptElement | null = null;
let themeObserver: MutationObserver | null = null;

onMounted(() => {
  if (props.css) {
    styleEl = document.createElement('style');
    styleEl.setAttribute('data-ext-frame', '');
    styleEl.textContent = props.css;
    document.head.appendChild(styleEl);
  }
  if (props.js) {
    // v-html never runs <script>; inject the page JS so existing handlers bind.
    scriptEl = document.createElement('script');
    scriptEl.textContent = props.js;
    document.body.appendChild(scriptEl);
  }
  // When embedded in an admin pane (iframe), follow the parent's light/dark theme
  // live — otherwise the embed keeps its load-time theme until a refresh.
  if (props.embed && window.parent && window.parent !== window) {
    try {
      const parentHtml = window.parent.document.documentElement;
      const sync = () => { document.documentElement.dataset.theme = parentHtml.dataset.theme || 'light'; };
      sync();
      themeObserver = new MutationObserver(sync);
      themeObserver.observe(parentHtml, { attributes: true, attributeFilter: ['data-theme'] });
    } catch { /* cross-origin parent — leave the theme as loaded */ }
  }
});

onBeforeUnmount(() => {
  styleEl?.remove();
  scriptEl?.remove();
  themeObserver?.disconnect();
});
</script>

<template>
  <Head :title="title" />
  <div v-if="embed" class="ext-frame ext-embed p-4" v-html="bodyHtml"></div>
  <AppLayout v-else>
    <div class="ext-frame" v-html="bodyHtml"></div>
  </AppLayout>
</template>
