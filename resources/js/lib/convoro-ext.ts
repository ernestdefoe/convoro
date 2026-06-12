import { reactive, markRaw } from 'vue';

// A slot entry is a way to render something into a named slot. We keep it
// framework-light so prebuilt extension bundles don't have to share Convoro's
// exact Vue instance: an extension can hand us a plain mount callback (gets the
// DOM element) or a static HTML string. A raw Vue component is also accepted
// for first-party extensions built against the same Vue.
export interface SlotEntry {
  id: string;
  ext?: string;
  /** Human label shown in the live editor's widget manager. */
  label?: string;
  order?: number;
  mount?: (el: HTMLElement, ctx: SlotContext) => void | (() => void);
  html?: string;
  component?: unknown;
}

export interface SlotContext {
  name: string;
  props: Record<string, unknown>;
}

/** Admin-defined ordering + visibility for a slot (e.g. the forum sidebar). */
export interface SlotLayout {
  /** Widget keys (entry.ext) in display order. Unknown entries append after. */
  order: string[];
  /** Widget keys hidden from the live slot. */
  disabled: string[];
}

/** A widget as surfaced to the editor: identity, label and on/off state. */
export interface RegisteredWidget {
  key: string;
  label: string;
  ext?: string;
  enabled: boolean;
}

type Listener = (payload?: unknown) => void;

class ConvoroRuntime {
  /** Stable API version so extensions can feature-detect. */
  readonly version = 1;

  /** name -> entries (reactive so late-loading extensions update live slots). */
  readonly slots = reactive<Record<string, SlotEntry[]>>({});

  /** name -> admin layout (order + disabled). Reactive for live preview. */
  readonly layouts = reactive<Record<string, SlotLayout>>({});

  /**
   * Page-provided data shared with framework-light extensions (widgets read
   * this synchronously instead of fetching). Reactive so a SPA navigation that
   * refreshes the data re-renders dependent widgets.
   */
  readonly data = reactive<Record<string, unknown>>({});

  private events: Record<string, Listener[]> = {};
  private seq = 0;
  private translator: (k: string, p?: Record<string, string | number>) => string = (k) => k;

  /** Install the host app's translator so framework-light widgets stay i18n-aware. */
  setTranslator(fn: (k: string, p?: Record<string, string | number>) => string): void {
    this.translator = fn;
  }

  /** Translate a key via the host app (falls back to the key itself). */
  t(key: string, params: Record<string, string | number> = {}): string {
    return this.translator(key, params);
  }

  /** Stable identity for a slot entry (extension id, falling back to its id). */
  private keyOf(e: SlotEntry): string {
    return e.ext ?? e.id;
  }

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

  /**
   * Entries to render for a slot. When an admin layout exists (e.g. the forum
   * sidebar), disabled widgets are dropped and the rest are ordered by the
   * layout — unknown/newly-installed widgets fall to the end in registration
   * order. Slots without a layout render in their natural registration order.
   */
  slotEntries(name: string): SlotEntry[] {
    const list = this.slots[name] ?? [];
    const layout = this.layouts[name];
    if (!layout) return list;
    const order = layout.order ?? [];
    const disabled = new Set(layout.disabled ?? []);
    const rank = (e: SlotEntry) => {
      const i = order.indexOf(this.keyOf(e));
      return i === -1 ? order.length : i;
    };
    return list
      .filter((e) => !disabled.has(this.keyOf(e)))
      .slice()
      .sort((a, b) => rank(a) - rank(b) || (a.order ?? 0) - (b.order ?? 0));
  }

  /** Install an admin ordering/visibility layout for a slot (live preview). */
  setLayout(name: string, layout: Partial<SlotLayout>): void {
    this.layouts[name] = {
      order: layout.order ?? [],
      disabled: layout.disabled ?? [],
    };
  }

  /** Merge page data that framework-light extensions read synchronously. */
  setData(data: Record<string, unknown>): void {
    Object.assign(this.data, data ?? {});
  }

  /**
   * Every widget registered for a slot — including disabled ones — annotated
   * with its on/off state and label, in layout order. Powers the live editor's
   * widget manager so newly-installed extensions appear automatically.
   */
  registeredWidgets(name: string): RegisteredWidget[] {
    const list = this.slots[name] ?? [];
    const layout = this.layouts[name];
    const order = layout?.order ?? [];
    const disabled = new Set(layout?.disabled ?? []);
    const rank = (e: SlotEntry) => {
      const i = order.indexOf(this.keyOf(e));
      return i === -1 ? order.length : i;
    };
    return list
      .slice()
      .sort((a, b) => rank(a) - rank(b) || (a.order ?? 0) - (b.order ?? 0))
      .map((e) => {
        const key = this.keyOf(e);
        return { key, ext: e.ext, label: e.label ?? e.ext ?? e.id, enabled: !disabled.has(key) };
      });
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
