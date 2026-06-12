<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Display-name hygiene. A name like a lone COMBINING ENCLOSING DIAMOND (U+20DF)
 * is a non-empty string, so it slips past `required|string`, yet it has no base
 * glyph and renders as a bare diamond. This centralizes sanitization, a
 * validity test, and a guaranteed-renderable display fallback so a broken name
 * can never reach the UI — on input, on save, and at render time.
 */
class Username
{
    /** Strip dangerous/invisible code points, collapse whitespace, NFC-normalize. */
    public static function sanitize(?string $value): string
    {
        $v = (string) $value;

        // The Unicode replacement char + zero-width / bidi-control characters.
        $v = preg_replace('/[\x{FFFD}\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2060}\x{FEFF}]/u', '', $v) ?? $v;
        // ASCII C0 + DEL + C1 control characters.
        $v = preg_replace('/[\x{0000}-\x{001F}\x{007F}-\x{009F}]/u', '', $v) ?? $v;
        // Collapse any whitespace run to a single space, then trim.
        $v = preg_replace('/\s+/u', ' ', $v) ?? $v;
        $v = trim($v);

        // Canonical composition keeps combining marks attached to their base.
        if ($v !== '' && class_exists(\Normalizer::class)) {
            $n = \Normalizer::normalize($v, \Normalizer::FORM_C);
            if (is_string($n)) {
                $v = $n;
            }
        }

        return $v;
    }

    /**
     * A name is usable only if, once sanitized, it has visible substance:
     * at least one letter or number, and a base glyph (not a leading mark).
     */
    public static function isValid(?string $value): bool
    {
        $v = self::sanitize($value);

        if ($v === '') {
            return false;
        }
        if (! preg_match('/[\p{L}\p{N}]/u', $v)) {
            return false; // pure punctuation / symbols / combining marks
        }
        if (preg_match('/^\p{M}/u', $v)) {
            return false; // starts with a combining mark (no base to enclose)
        }

        return true;
    }

    /** A guaranteed-renderable name: the cleaned name, or "Member {id}". */
    public static function display(?string $value, int|string|null $id = null): string
    {
        if (self::isValid($value)) {
            return self::sanitize($value);
        }

        return $id !== null ? __('Member :id', ['id' => $id]) : __('Member');
    }

    /** Two-letter initials derived from a guaranteed-safe display name. */
    public static function initials(?string $value, int|string|null $id = null): string
    {
        $name = self::display($value, $id);
        $parts = preg_split('/\s+/u', $name) ?: [];
        $first = $parts[0] ?? '';
        $last = count($parts) > 1 ? (string) end($parts) : '';
        $initials = Str::upper(mb_substr($first, 0, 1).mb_substr($last, 0, 1));

        return $initials !== '' ? $initials : '?';
    }
}
