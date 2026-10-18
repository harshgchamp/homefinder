<?php

namespace App\Contracts;

use App\Data\PostcodeLocation;
use App\Exceptions\PostcodeLookupUnavailable;
use App\Exceptions\PostcodeNotFound;

/**
 * "Find where a UK postcode is." The app depends on THIS interface, never on
 * a specific provider.
 *
 * Which class actually answers is decided in ONE place, the service container
 * binding in AppServiceProvider::register():
 *   - PostcodesIoLookup    the real api.postcodes.io (production, local)
 *   - FakePostcodeLookup   no network (tests, offline work)
 * wrapped in CachedPostcodeLookup so each postcode is looked up once a week.
 *
 * Swapping provider later (e.g. Google, Ordnance Survey) = one new class and
 * one changed line in the provider. No controller, job or request changes.
 */
interface PostcodeLookup
{
    /**
     * @throws PostcodeNotFound when the postcode does not exist (a user error)
     * @throws PostcodeLookupUnavailable when the provider cannot answer (our problem; retry later)
     */
    public function lookup(string $postcode): PostcodeLocation;
}
