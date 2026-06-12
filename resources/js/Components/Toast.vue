<script setup lang="ts">
import { onMounted, onBeforeUnmount, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { useToasts, toast, dismissToast } from '@/lib/toast';

const toasts = useToasts();
const page = usePage();

// Embedded extension admin pages (same-origin iframes) postMessage on save.
function onMessage(e: MessageEvent) {
    if (e.origin !== window.location.origin) return;
    const d = e.data;
    if (d && d.type === 'convoro:toast' && typeof d.message === 'string') {
        toast(d.message, d.kind === 'error' ? 'error' : d.kind === 'info' ? 'info' : 'success');
    }
}

// Server-side flashed status (e.g. redirect-with-status saves) becomes a toast.
watch(
    () => (page.props as any).flash?.status,
    (status) => { if (status && typeof status === 'string') toast(status, 'success'); },
);

onMounted(() => window.addEventListener('message', onMessage));
onBeforeUnmount(() => window.removeEventListener('message', onMessage));
</script>

<template>
    <Teleport to="body">
        <div class="pointer-events-none fixed bottom-4 right-4 z-[9999] flex flex-col gap-2">
            <TransitionGroup name="toast">
                <div
                    v-for="t in toasts.items"
                    :key="t.id"
                    class="pointer-events-auto flex min-w-[240px] max-w-sm items-center gap-3 rounded-xl border bg-surface px-4 py-3 shadow-lg shadow-black/10"
                    :class="t.type === 'error' ? 'border-red-500/40' : t.type === 'info' ? 'border-indigo-500/40' : 'border-emerald-500/40'"
                    role="status"
                    @click="dismissToast(t.id)"
                >
                    <span
                        class="grid h-6 w-6 shrink-0 place-items-center rounded-full text-white"
                        :class="t.type === 'error' ? 'bg-red-500' : t.type === 'info' ? 'bg-indigo-500' : 'bg-emerald-500'"
                    >
                        <svg v-if="t.type === 'success'" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5" /></svg>
                        <svg v-else-if="t.type === 'error'" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M18 6 6 18M6 6l12 12" /></svg>
                        <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M12 16v-4M12 8h.01" /></svg>
                    </span>
                    <span class="text-sm font-medium text-ink">{{ t.message }}</span>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>

<style scoped>
.toast-enter-active,
.toast-leave-active { transition: all 0.25s ease; }
.toast-enter-from { opacity: 0; transform: translateY(8px) scale(0.97); }
.toast-leave-to { opacity: 0; transform: translateX(12px); }
.toast-move { transition: transform 0.25s ease; }
</style>
