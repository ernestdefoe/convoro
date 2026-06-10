import { reactive, markRaw } from 'vue';

// A slot entry is a way to render something into a named slot. We keep it
// framework-light so prebuilt extension bundles don't have to share Convoro's
// exact Vue instance: an extension can hand us a plain mount callback (gets the
// DOM element) or a static HTML string. A raw Vue component is also accepted
// for first-party extensions built against the same Vue.
export interface SlotEntry {
  id: string;
  ext?: string;
  order?: number;
  mount?: (el: HTMLElement, ctx: SlotContext) => void | (() => void);
  html?: string;
  component?: unknown;
}

export interface SlotContext {
  name: string;
  props: Record<string, unknown>;
}

type Listener = (payload?: unknown) => void;

class ConvoroRuntime {
  /** Stable API version so extensions can feature-detect. */
  readonly version = 1;

  /** name -> entries (reactive so late-loading extensions update live slots). */
  readonly slots = reactive<Record<string, SlotEntry[]>>({});

  private events: Record<string, Listener[]> = {};
  private seq = 0;

  /** Register a renderer for a named slot. Returns an unregister function. */
  registerSlot(name: string, entry: Omit<SlotEntry, 'id'>): () => void {
    const id = `e${++this.seq}`;
    const full: SlotEntry = { id, ...entry };
    if (full.component) full.component = markRaw(full.component as object);
    const list = this.slots[name] ?? (this.slots[name] = []);
    list.push(full);
    list.sort((a, b) => (a.order ?? 0) - (b.order ?? 0));
    return () => {
      const arr = this.slots[name];
      if (arr) this.slots[name] = arr.filter((e) => e.id !== id);
    };
  }

  slotEntries(name: string): SlotEntry[] {
    return this.slots[name] ?? [];
  }

  // Tiny event bus for cross-extension / core hooks.
  on(event: string, cb: Listener): () => void {
    (this.events[event] ??= []).push(cb);
    return () => {
      this.events[event] = (this.events[event] ?? []).filter((f) => f !== cb);
    };
  }

  emit(event: string, payload?: unknown): void {
    (this.events[event] ?? []).forEach((cb) => {
      try {
        cb(payload);
      } catch (e) {
        console.error(`[Convoro] listener for "${event}" threw`, e);
      }
    });
  }
}

export const convoro = new ConvoroRuntime();

declare global {
  interface Window {
    Convoro: ConvoroRuntime;
  }
}

/** Expose the runtime and load enabled extensions' prebuilt bundles. */
export function bootExtensions(assets: { id: string; url: string }[]): void {
  window.Convoro = convoro;
  (assets ?? []).forEach((a) => {
    // @vite-ignore — these are runtime URLs served from storage, not build inputs.
    import(/* @vite-ignore */ a.url).catch((e) =>
      console.error(`[Convoro] failed to load extension "${a.id}"`, e),
    );
  });
}
