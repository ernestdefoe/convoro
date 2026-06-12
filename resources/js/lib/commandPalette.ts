import { ref } from 'vue';

// Shared open-state so any button (e.g. a header search affordance) can open
// the global command palette, not just the ⌘K shortcut.
const isOpen = ref(false);

export function useCommandPalette() {
    return {
        isOpen,
        open: () => (isOpen.value = true),
        close: () => (isOpen.value = false),
        toggle: () => (isOpen.value = !isOpen.value),
    };
}
