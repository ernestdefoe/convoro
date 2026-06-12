<?php

namespace App\Rules;

use App\Support\Username as UsernameSupport;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects display names that have no visible substance once sanitized — e.g. a
 * lone combining mark, pure punctuation, control characters, or the Unicode
 * replacement char — so a name can never render as a bare "diamond".
 */
class Username implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! UsernameSupport::isValid($value)) {
            $fail(__('Please enter a display name with at least one letter or number.'));
        }
    }
}
