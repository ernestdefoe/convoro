import { reactive } from 'vue';

export type ToastType = 'success' | 'error' | 'info';
export interface ToastItem { id: number; message: string; type: ToastType }

const state = reactive<{ items: ToastItem[] }>({ items: [] });
let seq = 0;

export function useToasts() {
    return state;
}

/** Show a transient toast. Returns its id. */
export function toast(message: string, type: ToastType = 'success', ttl = 2600): number {
    const id = ++seq;
    state.items.push({ id, message, type });
    if (ttl > 0) setTimeout(() => dismissToast(id), ttl);
    return id;
}

export function dismissToast(id: number): void {
    const i = state.items.findIndex((t) => t.id === id);
    if (i !== -1) state.items.splice(i, 1);
}
