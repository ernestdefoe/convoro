import { Extension } from '@tiptap/core';
import Suggestion from '@tiptap/suggestion';
import { VueRenderer } from '@tiptap/vue-3';
import MentionList from '@/Components/MentionList.vue';

function place(el: HTMLElement, clientRect: (() => DOMRect | null) | null | undefined) {
  const rect = clientRect?.();
  if (!rect) return;
  el.style.left = `${rect.left + window.scrollX}px`;
  el.style.top = `${rect.bottom + window.scrollY + 6}px`;
}

/**
 * `@` typeahead that inserts the user's handle as plain text (e.g. "@riley.west "),
 * which the server-side Mentions parser already understands — no custom node, so
 * it survives HTML sanitization cleanly.
 */
export const MentionText = Extension.create({
  name: 'mentionText',

  addProseMirrorPlugins() {
    return [
      Suggestion({
        editor: this.editor,
        char: '@',
        allowSpaces: false,

        items: async ({ query }) => {
          try {
            const res = await fetch(`/users/search?q=${encodeURIComponent(query)}`, {
              headers: { Accept: 'application/json' },
              credentials: 'same-origin',
            });
            return res.ok ? await res.json() : [];
          } catch {
            return [];
          }
        },

        command: ({ editor, range, props }) => {
          editor.chain().focus().insertContentAt(range, `@${props.id} `).run();
        },

        render: () => {
          let component: VueRenderer | null = null;
          let popup: HTMLElement | null = null;

          return {
            onStart: (props: any) => {
              component = new VueRenderer(MentionList, { props, editor: props.editor });
              popup = document.createElement('div');
              popup.style.position = 'absolute';
              popup.style.zIndex = '70';
              document.body.appendChild(popup);
              popup.appendChild(component.element as Node);
              place(popup, props.clientRect);
            },
            onUpdate: (props: any) => {
              component?.updateProps(props);
              place(popup as HTMLElement, props.clientRect);
            },
            onKeyDown: (props: any) => {
              if (props.event.key === 'Escape') return true;
              return (component?.ref as any)?.onKeyDown?.(props) ?? false;
            },
            onExit: () => {
              popup?.remove();
              popup = null;
              component?.destroy();
              component = null;
            },
          };
        },
      }),
    ];
  },
});
