import { usePage } from '@inertiajs/vue3';

/**
 * Translate a UI string. English is the source language, so the key IS the
 * English text; missing translations fall back to it. Supports {placeholders}.
 *
 *   t('Members')                      → "Miembros" (es) or "Members" (en)
 *   t('Ask {site}', { site: 'Acme' }) → "Pregunta a Acme"
 */
export function t(key: string, replacements: Record<string, string | number> = {}): string {
  const messages = ((usePage().props as any).i18n?.messages ?? {}) as Record<string, string>;
  let out = messages[key] ?? key;
  for (const k in replacements) {
    out = out.split(`{${k}}`).join(String(replacements[k]));
  }
  return out;
}
