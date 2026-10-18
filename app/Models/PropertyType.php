<?php

namespace App\Models;

use App\Models\Concerns\ClearsLookupCache;
use App\Support\CachedLookups;
use Database\Factories\PropertyTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PropertyType extends Model
{
    /** @use HasFactory<PropertyTypeFactory> */
    use ClearsLookupCache, HasFactory;

    protected $fillable = ['name', 'slug'];

    /**
     * @return HasMany<Property, $this>
     */
    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    /**
     * Saving or deleting a property type clears this cached dropdown list.
     */
    public static function lookupCacheKey(): string
    {
        return CachedLookups::PROPERTY_TYPES;
    }
}
