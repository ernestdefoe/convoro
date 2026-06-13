import { createLowlight, common } from 'lowlight';

/**
 * Shared syntax-highlighting helpers.
 *
 * The composer (CodeBlockLowlight) highlights live while you type using this
 * lowlight instance. Rendered posts store raw code (`<pre><code
 * class="language-x">…</code></pre>`) and are colorised on display by
 * highlightWithin(), which lazy-loads highlight.js only when a post actually
 * contains a code block.
 */
export const lowlight = createLowlight(common);

/** The languages we expose in the composer's language picker. */
export const CODE_LANGUAGES: { value: string; label: string }[] = [
  { value: 'plaintext', label: 'Plain text' },
  { value: 'bash', label: 'Bash / Shell' },
  { value: 'c', label: 'C' },
  { value: 'cpp', label: 'C++' },
  { value: 'csharp', label: 'C#' },
  { value: 'css', label: 'CSS' },
  { value: 'diff', label: 'Diff' },
  { value: 'go', label: 'Go' },
  { value: 'html', label: 'HTML / XML' },
  { value: 'java', label: 'Java' },
  { value: 'javascript', label: 'JavaScript' },
  { value: 'json', label: 'JSON' },
  { value: 'kotlin', label: 'Kotlin' },
  { value: 'markdown', label: 'Markdown' },
  { value: 'php', label: 'PHP' },
  { value: 'python', label: 'Python' },
  { value: 'ruby', label: 'Ruby' },
  { value: 'rust', label: 'Rust' },
  { value: 'sql', label: 'SQL' },
  { value: 'swift', label: 'Swift' },
  { value: 'typescript', label: 'TypeScript' },
  { value: 'yaml', label: 'YAML' },
];

type Hljs = { highlightElement: (el: HTMLElement) => void };
let hljs: Hljs | null = null;

/** Colorise every `pre code` inside `root` (no-op if none). */
export async function highlightWithin(root: HTMLElement | null | undefined): Promise<void> {
  if (!root) return;
  const blocks = Array.from(root.querySelectorAll<HTMLElement>('pre code'));
  if (!blocks.length) return;
  if (!hljs) {
    // Lazy chunk — only pulled in when a rendered post has code.
    hljs = (await import('highlight.js/lib/common')).default as unknown as Hljs;
  }
  for (const el of blocks) {
    if (el.dataset.highlighted) continue;
    try {
      hljs.highlightElement(el);
    } catch {
      /* unknown language — leave as plain text */
    }
  }
}
