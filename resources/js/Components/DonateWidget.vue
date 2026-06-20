<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue';

// A sidebar donate card backed by a Stripe "buy button". The buy-button id and
// publishable key are PUBLIC values (safe in the page); both come from admin
// settings, so this only renders where an admin has configured it.
const props = defineProps<{
  buyButtonId: string;
  publishableKey: string;
  heading?: string;
  blurb?: string;
}>();

// Stripe's buy button has a ~288px minimum width — wider than the narrow forum
// sidebar. So we render it into a fixed-width "stage" (where Stripe is happy)
// and scale the whole stage down to fit the available column.
const STAGE_W = 300;
const host = ref<HTMLElement | null>(null);
const stage = ref<HTMLElement | null>(null);
let ro: ResizeObserver | null = null;

function fit() {
  if (!host.value || !stage.value) return;
  const avail = host.value.clientWidth;
  if (avail <= 0) return;
  const s = Math.min(1, avail / STAGE_W);
  stage.value.style.transform = `scale(${s})`;
  host.value.style.height = Math.ceil(stage.value.offsetHeight * s) + 'px';
}

onMounted(() => {
  if (!props.buyButtonId || !props.publishableKey || !stage.value) return;

  // Load Stripe's buy-button script once.
  if (!document.querySelector('script[data-stripe-buy-button]')) {
    const s = document.createElement('script');
    s.async = true;
    s.src = 'https://js.stripe.com/v3/buy-button.js';
    s.setAttribute('data-stripe-buy-button', '');
    document.head.appendChild(s);
  }

  // Create the custom element imperatively (Vue's compiler would mangle the
  // unknown <stripe-buy-button> tag during SSR).
  if (!stage.value.querySelector('stripe-buy-button')) {
    const btn = document.createElement('stripe-buy-button');
    btn.setAttribute('buy-button-id', props.buyButtonId);
    btn.setAttribute('publishable-key', props.publishableKey);
    stage.value.appendChild(btn);
  }

  // Re-fit as the column resizes and as Stripe finishes rendering (its iframe
  // loads async, growing the stage — the ResizeObserver on the stage catches it).
  ro = new ResizeObserver(fit);
  ro.observe(host.value!);
  ro.observe(stage.value);
  [300, 1000, 2500].forEach((d) => setTimeout(fit, d));
});

onBeforeUnmount(() => { ro?.disconnect(); ro = null; });
</script>

<template>
  <section class="overflow-hidden rounded-c border border-line bg-surface shadow-sm">
    <div class="bg-gradient-to-r from-primary/10 via-primary/5 to-transparent p-4">
      <div class="flex items-center gap-2">
        <span class="grid h-7 w-7 place-items-center rounded-lg bg-primary text-white">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.29 1.51 4.04 3 5.5l7 7Z" /></svg>
        </span>
        <h2 class="text-base font-extrabold tracking-tight text-ink">{{ heading || 'Donate' }}</h2>
      </div>
      <p v-if="blurb" class="mt-1 text-sm text-ink-muted">{{ blurb }}</p>
      <div ref="host" class="mt-3 overflow-hidden">
        <div ref="stage" :style="{ width: STAGE_W + 'px', transformOrigin: 'top left' }"></div>
      </div>
    </div>
  </section>
</template>
