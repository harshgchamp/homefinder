<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\FeatureFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read bool $is_new
 */
class Feature extends Model
{
    /** @use HasFactory<FeatureFactory> */
    use HasFactory;

    /**
     * How long a feature counts as "new" in the admin list.
     */
    public const NEW_FOR_DAYS = 7;

    /**
     * Mass-assignable columns (an allow-list). The alternative, $guarded, is a
     * block-list; an allow-list is safer because a new column is protected by default.
     *
     * @var list<string>
     */
    protected $fillable = ['name', 'slug'];

    /**
     * Mutator: tidy the name on every write, whichever code path sets it
     * (form, seeder, tinker). "  pets   allowed " is stored as "Pets allowed".
     *
     * @return Attribute<string, string>
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn (string $value): string => Str::ucfirst(Str::squish($value)),
        );
    }

    /**
     * Accessor: a computed value with no column behind it, read as $feature->is_new.
     *
     * @return Attribute<bool, never>
     */
    protected function isNew(): Attribute
    {
        return Attribute::make(
            get: fn (): bool => $this->created_at?->greaterThanOrEqualTo(now()->subDays(self::NEW_FOR_DAYS)) ?? false,
        );
    }

    /**
     * Local scope: filter by a search term. Does nothing when the term is empty,
     * so the controller can call it unconditionally.
     *
     * @param  Builder<Feature>  $query
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $query->when($term, fn (Builder $query, string $term) => $query->whereLike('name', "%{$term}%"));
    }

    /**
     * @return BelongsToMany<Property, $this>
     */
    public function properties(): BelongsToMany
    {
        return $this->belongsToMany(Property::class);
    }
}
