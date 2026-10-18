<?php

namespace App\Support;

use App\Models\Agency;
use App\Models\Feature;
use App\Models\PropertyType;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Small lists used in many dropdowns (agencies, property types, features).
 *
 * They are read on almost every admin page but change rarely, which is the
 * classic case for caching: read from Redis, not the database, until one of
 * them changes. The models clear their own key when saved or deleted
 * (see App\Models\Concerns\ClearsLookupCache), so the cache is never stale for long.
 */
final class CachedLookups
{
    public const AGENCIES = 'lookups:agencies';

    public const PROPERTY_TYPES = 'lookups:property-types';

    public const FEATURES = 'lookups:features';

    /**
     * Safety net: even if a clear were ever missed (e.g. a mass update, which
     * fires no model events), the list refreshes itself after a day.
     */
    public const TTL_SECONDS = 86_400;

    /**
     * Active agencies only: inactive ones cannot get new listings.
     *
     * @return list<array{value: int, label: string}>
     */
    public static function agencies(): array
    {
        return self::remember(self::AGENCIES, fn () => Agency::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']));
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    public static function propertyTypes(): array
    {
        return self::remember(self::PROPERTY_TYPES, fn () => PropertyType::query()->orderBy('name')->get(['id', 'name']));
    }

    /**
     * @return list<array{value: int, label: string}>
     */
    public static function features(): array
    {
        return self::remember(self::FEATURES, fn () => Feature::query()->orderBy('name')->get(['id', 'name']));
    }

    /**
     * Clear all lists, e.g. after seeding (seeders run without model events).
     */
    public static function forgetAll(): void
    {
        Cache::deleteMultiple([self::AGENCIES, self::PROPERTY_TYPES, self::FEATURES]);
    }

    /**
     * Cache::remember(): return the cached value if the key exists; otherwise run
     * the closure, store its result for TTL seconds, and return it.
     *
     * Plain arrays are cached, not Eloquent models: smaller in Redis, and nothing
     * breaks if the model class changes later.
     *
     * @template TModel of Agency|PropertyType|Feature
     *
     * @param  Closure(): Collection<int, TModel>  $query
     * @return list<array{value: int, label: string}>
     */
    private static function remember(string $key, Closure $query): array
    {
        return Cache::remember($key, self::TTL_SECONDS, fn (): array => array_values(
            $query()->map(fn (Agency|PropertyType|Feature $model): array => [
                'value' => $model->id,
                'label' => $model->name,
            ])->all(),
        ));
    }
}
