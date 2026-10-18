<?php

namespace App\Services\Postcodes;

use App\Contracts\PostcodeLookup;
use App\Data\PostcodeLocation;
use App\Exceptions\PostcodeLookupUnavailable;
use App\Exceptions\PostcodeNotFound;
use App\Rules\UkPostcode;

/**
 * A stand-in with no network: used in tests (POSTCODES_DRIVER=fake in
 * phpunit.xml) and when working offline.
 *
 * It honours the same contract as the real one, including both exceptions,
 * so code tested against the fake behaves the same with the real provider.
 *   - ending in "9ZZ" (e.g. RM1 9ZZ) -> PostcodeNotFound
 *   - ending in "9XX" (e.g. RM1 9XX) -> PostcodeLookupUnavailable
 *   - anything else valid            -> stable made-up coordinates around London
 * Both triggers are valid FORMATS, so they get past the UkPostcode rule.
 */
final class FakePostcodeLookup implements PostcodeLookup
{
    /**
     * How many lookups were made: lets tests check that caching works.
     */
    public int $calls = 0;

    public function lookup(string $postcode): PostcodeLocation
    {
        $this->calls++;
        $normalised = strtoupper((string) preg_replace('/\s+/', '', $postcode));

        if (str_ends_with($normalised, '9XX')) {
            throw PostcodeLookupUnavailable::because(new \RuntimeException('Fake outage'));
        }

        if (str_ends_with($normalised, '9ZZ') || ! UkPostcode::passes($normalised)) {
            throw PostcodeNotFound::for($postcode);
        }

        // Same postcode -> same coordinates, every time (deterministic tests).
        $hash = crc32($normalised);

        return new PostcodeLocation(
            postcode: substr($normalised, 0, -3).' '.substr($normalised, -3),
            latitude: round(51.3 + ($hash % 4000) / 10000, 6),
            longitude: round(-0.5 + (intdiv($hash, 4000) % 8000) / 10000, 6),
            district: 'Fakeshire',
        );
    }
}
