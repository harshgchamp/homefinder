<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Cache invalidation for the lookup lists in App\Support\CachedLookups.
 * A model using this trait clears its cached list whenever one of its rows
 * is saved (created or updated) or deleted.
 *
 * Usage: `use ClearsLookupCache;` plus a lookupCacheKey() method.
 */
trait ClearsLookupCache
{
    /**
     * The cache key holding this model's list.
     */
    abstract public static function lookupCacheKey(): string;

    /**
     * Laravel calls a static method named boot{TraitName}() once, when the model
     * class boots. That is how a trait can register model events by itself.
     */
    protected static function bootClearsLookupCache(): void
    {
        $forget = function (): void {
            // afterCommit: inside a transaction, wait for COMMIT. Clearing earlier
            // would let another request re-cache the OLD list before the new row is
            // visible. Outside a transaction this runs straight away.
            DB::afterCommit(fn () => Cache::forget(static::lookupCacheKey()));
        };

        static::saved($forget);
        static::deleted($forget);
    }
}
