<?php

namespace App\Services\Postcodes;

use App\Contracts\PostcodeLookup;
use App\Data\PostcodeLocation;
use Illuminate\Support\Facades\Cache;

/**
 * A DECORATOR: it implements the same interface and wraps another
 * PostcodeLookup, adding caching around it without changing it.
 *
 *   CachedPostcodeLookup  ->  PostcodesIoLookup   (or FakePostcodeLookup)
 *
 * Postcodes almost never move, so each one is looked up once a week at most.
 * Fewer calls to a third party: faster pages, and kinder to its rate limits.
 *
 * Added in AppServiceProvider with $this->app->extend(...), so neither the real
 * lookup nor any caller knows the cache exists.
 */
final class CachedPostcodeLookup implements PostcodeLookup
{
    public const TTL_SECONDS = 7 * 24 * 60 * 60;

    public function __construct(private readonly PostcodeLookup $inner) {}

    public function lookup(string $postcode): PostcodeLocation
    {
        $key = 'postcodes:'.strtoupper((string) preg_replace('/\s+/', '', $postcode));

        // Only SUCCESSFUL answers are cached: if the inner lookup throws
        // (not found / unavailable), remember() stores nothing and rethrows.
        // So an outage is never cached as "not found".
        $data = Cache::remember($key, self::TTL_SECONDS, fn (): array => $this->inner->lookup($postcode)->toArray());

        /** @var array{postcode: string, latitude: float, longitude: float, district?: string|null} $data */
        return PostcodeLocation::fromArray($data);
    }
}
