<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A UK postcode in a valid FORMAT: "RM7 0AB", "rm70ab", "SW1A 1AA", "EC1A 1BB".
 *
 * Why a Rule class instead of a 'regex:...' string in each Form Request:
 *   - the pattern lives in ONE place and has a name ('new UkPostcode')
 *   - it can normalise the value before checking (spaces, lower case)
 *   - it can be unit tested on its own
 *
 * It checks the format only, not whether the postcode really exists.
 * (A postcode lookup service can do that; see the next block.)
 *
 * Usage: 'postcode' => ['required', 'string', new UkPostcode]
 */
class UkPostcode implements ValidationRule
{
    /**
     * Outward code (area + district, e.g. "SW1A", "RM7", "M1") followed by the
     * inward code (a digit + two letters, e.g. "1AA"). Letters that are never
     * used in the inward code (C I K M O V) are excluded. GIR 0AA is a special
     * case (Girobank) that exists for historical reasons.
     */
    private const PATTERN = '/^(GIR0AA|[A-PR-UWYZ][A-HK-Y]?[0-9][0-9A-HJKMNPR-Y]?[0-9][ABD-HJLNP-UW-Z]{2})$/';

    /**
     * Called by the validator. To reject the value, call $fail with a message;
     * not calling it means the value is valid.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::passes($value)) {
            // :attribute is replaced with the field name ("postcode"), or with
            // the friendly name from the Form Request's attributes().
            $fail('The :attribute must be a valid UK postcode, like SW1A 1AA.');
        }
    }

    /**
     * The check itself, usable outside validation too.
     * Spaces and letter case do not matter: "rm7 0ab" and "RM70AB" both pass.
     */
    public static function passes(string $value): bool
    {
        $compact = strtoupper((string) preg_replace('/\s+/', '', $value));

        return preg_match(self::PATTERN, $compact) === 1;
    }
}
