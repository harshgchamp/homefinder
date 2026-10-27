<?php

namespace App\Models;

use App\Models\Concerns\ClearsLookupCache;
use App\Models\Concerns\HasNotes;
use App\Support\CachedLookups;
use Database\Factories\AgencyFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Notifications\Notifiable;

class Agency extends Model
{
    /**
     * Notifiable: lets an agency receive notifications ($agency->notify(...)).
     * Any model can be notifiable, not only users. For the mail channel Laravel
     * sends to the model's "email" attribute.
     *
     * @use HasFactory<AgencyFactory>
     */
    use ClearsLookupCache, HasFactory, HasNotes, Notifiable;

    /**
     * The sort options the admin list accepts: URL key => [column, direction].
     * properties_count only exists when the query used withCount('properties').
     *
     * @var array<string, array{0: string, 1: 'asc'|'desc'}>
     */
    public const SORTS = [
        'name' => ['name', 'asc'],
        'newest' => ['created_at', 'desc'],
        'most_properties' => ['properties_count', 'desc'],
    ];

    protected $fillable = ['name', 'slug', 'email', 'phone', 'logo_path', 'is_active'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Property, $this>
     */
    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }

    /**
     * Every enquiry on any of this agency's properties.
     *
     * hasManyThrough(Final, Intermediate): agencies -> properties -> enquiries,
     * built as one JOIN using properties.agency_id and enquiries.property_id.
     * Note: Property uses SoftDeletes, so enquiries on trashed properties are left out.
     *
     * @return HasManyThrough<Enquiry, Property, $this>
     */
    public function enquiries(): HasManyThrough
    {
        return $this->hasManyThrough(Enquiry::class, Property::class);
    }

    /**
     * Name or email contains the term. The inner where() groups the OR, so it
     * becomes (name LIKE ? OR email LIKE ?) and cannot cancel other filters.
     *
     * @param  Builder<Agency>  $query
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $query->when($term, fn (Builder $query, string $term) => $query->where(
            fn (Builder $query) => $query->whereLike('name', "%{$term}%")->orWhereLike('email', "%{$term}%"),
        ));
    }

    /**
     * 'active' / 'inactive' from the filter; null means both.
     *
     * @param  Builder<Agency>  $query
     */
    #[Scope]
    protected function status(Builder $query, ?string $status): void
    {
        $query->when($status, fn (Builder $query, string $status) => $query->where('is_active', $status === 'active'));
    }

    /**
     * Sort by one of SORTS; unknown keys fall back to name. The id tie-breaker
     * keeps pagination stable when two rows share a value.
     *
     * @param  Builder<Agency>  $query
     */
    #[Scope]
    protected function sortBy(Builder $query, ?string $sort): void
    {
        [$column, $direction] = self::SORTS[$sort] ?? self::SORTS['name'];

        $query->orderBy($column, $direction)->orderBy('id', $direction);
    }

    /**
     * Saving or deleting an agency clears this cached dropdown list.
     */
    public static function lookupCacheKey(): string
    {
        return CachedLookups::AGENCIES;
    }
}
