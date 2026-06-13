import { Node, mergeAttributes } from '@tiptap/core';

/**
 * A spoiler: a collapsible <details> block. The <summary> is a fixed,
 * non-editable label; the editable content lives in the body. On a rendered
 * post the browser handles expand/collapse natively.
 */
declare module '@tiptap/core' {
  interface Commands<ReturnType> {
    spoiler: {
      toggleSpoiler: () => ReturnType;
    };
  }
}

export const Spoiler = Node.create({
  name: 'spoiler',
  group: 'block',
  content: 'block+',
  defining: true,

  parseHTML() {
    return [{ tag: 'details.spoiler', contentElement: 'div' }];
  },

  renderHTML({ HTMLAttributes }) {
    return [
      'details',
      mergeAttributes(HTMLAttributes, { class: 'spoiler' }),
      ['summary', { contenteditable: 'false' }, 'Spoiler'],
      ['div', {}, 0],
    ];
  },

  addCommands() {
    return {
      toggleSpoiler:
        () =>
        ({ commands }) =>
          commands.toggleWrap(this.name),
    };
  },
});
