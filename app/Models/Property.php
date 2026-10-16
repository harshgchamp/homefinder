<?php

namespace App\Models;

use App\Enums\ListingType;
use App\Enums\PropertyStatus;
use App\Enums\PublicationStatus;
use App\Observers\PropertyObserver;
use Carbon\CarbonImmutable;
use Database\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property int $agency_id
 * @property int $property_type_id
 * @property string $title
 * @property string $slug
 * @property string $description
 * @property ListingType $listing_type
 * @property PropertyStatus $status
 * @property int $price
 * @property int $bedrooms
 * @property int $bathrooms
 * @property bool|null $furnished
 * @property string $address_line_1
 * @property string $city
 * @property string $postcode
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read string $formatted_price
 * @property-read PublicationStatus $publication_status
 */
// Why ObservedBy: registers PropertyObserver right on the model, so anyone reading
// this class can see that saving or deleting a property has side effects.
#[ObservedBy(PropertyObserver::class)]
class Property extends Model
{
    /** @use HasFactory<PropertyFactory> */
    use HasFactory, Prunable, SoftDeletes;

    /**
     * How long a property stays in the trash before `model:prune` removes it for good.
     */
    public const PRUNE_AFTER_DAYS = 30;

    /**
     * The sort options the admin list accepts. The key is what goes in the URL
     * (?sort=price_desc), the value is [column, direction]. Only these keys are
     * allowed, so a user can never sort by an arbitrary column.
     *
     * @var array<string, array{0: string, 1: 'asc'|'desc'}>
     */
    public const SORTS = [
        'newest' => ['created_at', 'desc'],
        'oldest' => ['created_at', 'asc'],
        'price_asc' => ['price', 'asc'],
        'price_desc' => ['price', 'desc'],
        'title' => ['title', 'asc'],
    ];

    /**
     * The table associated with the model. Laravel guesses the table name from
     * the class name, but this is more explicit and avoids mistakes if the class
     * is renamed or moved.
     *
     * @var string
     */
    protected $table = 'properties';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = true;

    protected $fillable = [
        'agency_id',
        'property_type_id',
        'title',
        'slug',
        'description',
        'listing_type',
        'price',
        'bedrooms',
        'bathrooms',
        'furnished',
        'address_line_1',
        'city',
        'postcode',
        'status',
        'published_at',
    ];

    /**
     * @var array<string, mixed>
     */

    /* The $attributes property is used to define default values for model attributes. In this case, it sets the default value of the 'status' attribute to 'available'. This means that when a new Property instance is created, if the 'status' attribute is not explicitly set, it will automatically be assigned the value 'available'. This is useful for ensuring that certain attributes have a consistent initial state without requiring explicit assignment each time a new instance is created. */
    protected $attributes = [
        'status' => 'available',
    ];

    /**
     * The $casts property is used to define how certain attributes should be automatically converted to and from their database representation. In this case, it specifies that the 'listing_type' and 'status' attributes should be cast to their respective enum classes (ListingType and PropertyStatus), while 'price', 'bedrooms', and 'bathrooms' should be treated as integers, 'furnished' as a boolean, and 'published_at' as a datetime object. This ensures that when these attributes are accessed or modified, they are automatically converted to the appropriate data type, making it easier to work with them in the application.
     *
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'listing_type' => ListingType::class,
            'status' => PropertyStatus::class,
            'price' => 'integer',
            'bedrooms' => 'integer',
            'bathrooms' => 'integer',
            'furnished' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Normalise UK postcodes: "rm7 0ab" / "RM70AB" -> "RM7 0AB".
     *
     * @return Attribute<string, string>
     */
    protected function postcode(): Attribute
    {
        return Attribute::make(
            set: function (string $value): string {
                $compact = strtoupper((string) preg_replace('/\s+/', '', $value));

                return substr($compact, 0, -3).' '.substr($compact, -3);
            },
        );
    }

    /**
     * Only published listings (published_at set and not in the future).
     *
     * @param  Builder<Property>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    // ------------------------------------------------------------------
    // Accessors: computed values with no column behind them.
    // Read as $property->formatted_price and $property->publication_status.
    // ------------------------------------------------------------------

    /**
     * "£250,000" for a sale, "£1,200 pcm" for a rental.
     *
     * @return Attribute<string, never>
     */
    protected function formattedPrice(): Attribute
    {
        return Attribute::make(
            get: fn (): string => '£'.number_format($this->price)
                .($this->listing_type === ListingType::Rent ? ' pcm' : ''),
        );
    }

    /**
     * Draft / Scheduled / Published, worked out from published_at.
     *
     * @return Attribute<PublicationStatus, never>
     */
    protected function publicationStatus(): Attribute
    {
        return Attribute::make(
            get: fn (): PublicationStatus => match (true) {
                $this->published_at === null => PublicationStatus::Draft,
                $this->published_at->isFuture() => PublicationStatus::Scheduled,
                default => PublicationStatus::Published,
            },
        );
    }

    // ------------------------------------------------------------------
    // Scopes used by the admin list, the CSV export and the archive command.
    // Each small scope does one thing; filter() combines them.
    // ------------------------------------------------------------------

    /**
     * Apply every filter from the admin list in one call.
     *
     * Why one entry point: the list page and the CSV export must return exactly
     * the same rows for the same URL. Both call filter($filters), so they cannot drift apart.
     *
     * Each line uses when(): the condition is only added if that filter has a value,
     * so an empty filter form returns every property.
     *
     * @param  Builder<Property>  $query
     * @param  array{
     *     search?: string|null, listing_type?: string|null, status?: string|null,
     *     publication?: string|null, agency_id?: int|null, property_type_id?: int|null,
     *     city?: string|null, min_price?: int|null, max_price?: int|null,
     *     min_bedrooms?: int|null, features?: list<int>, trashed?: string|null,
     * }  $filters
     */
    #[Scope]
    protected function filter(Builder $query, array $filters): void
    {
        $query
            ->search($filters['search'] ?? null)
            ->when($filters['listing_type'] ?? null, fn (Builder $q, string $type) => $q->where('listing_type', $type))
            ->when($filters['status'] ?? null, fn (Builder $q, string $status) => $q->where('status', $status))
            ->when(
                PublicationStatus::tryFrom($filters['publication'] ?? ''),
                fn (Builder $q, PublicationStatus $publication) => $q->publication($publication),
            )
            ->when($filters['agency_id'] ?? null, fn (Builder $q, int $id) => $q->where('agency_id', $id))
            ->when($filters['property_type_id'] ?? null, fn (Builder $q, int $id) => $q->where('property_type_id', $id))
            ->when($filters['city'] ?? null, fn (Builder $q, string $city) => $q->where('city', $city))
            ->priceBetween($filters['min_price'] ?? null, $filters['max_price'] ?? null)
            ->when($filters['min_bedrooms'] ?? null, fn (Builder $q, int $beds) => $q->where('bedrooms', '>=', $beds))
            ->withAllFeatures($filters['features'] ?? [])
            // SoftDeletes adds a global scope "WHERE deleted_at IS NULL" to every query.
            // withTrashed() removes it; onlyTrashed() flips it to "IS NOT NULL".
            ->when(($filters['trashed'] ?? null) === 'with', fn (Builder $q) => $q->withTrashed())
            ->when(($filters['trashed'] ?? null) === 'only', fn (Builder $q) => $q->onlyTrashed());
    }

    /**
     * Free-text search over title, address, city and postcode.
     *
     * Why the closure: it wraps the ORs in brackets.
     *   Without: WHERE deleted_at IS NULL AND title LIKE ? OR city LIKE ?   (OR escapes the trash filter!)
     *   With:    WHERE deleted_at IS NULL AND (title LIKE ? OR city LIKE ?)
     *
     * @param  Builder<Property>  $query
     */
    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $query->when($term, fn (Builder $query, string $term) => $query->where(
            fn (Builder $q) => $q
                ->whereLike('title', "%{$term}%")
                ->orWhereLike('address_line_1', "%{$term}%")
                ->orWhereLike('city', "%{$term}%")
                ->orWhereLike('postcode', "%{$term}%"),
        ));
    }

    /**
     * @param  Builder<Property>  $query
     */
    #[Scope]
    protected function priceBetween(Builder $query, ?int $min, ?int $max): void
    {
        $query
            ->when($min, fn (Builder $q, int $min) => $q->where('price', '>=', $min))
            ->when($max, fn (Builder $q, int $max) => $q->where('price', '<=', $max));
    }

    /**
     * Properties that have ALL of the given features (garden AND parking), not ANY.
     *
     * Why the count: whereHas() normally means "at least 1 matching feature".
     * Asking for ">= count($ids)" matching features turns ANY into ALL,
     * in a single subquery instead of one whereHas() per feature.
     *
     * @param  Builder<Property>  $query
     * @param  list<int>  $featureIds
     */
    #[Scope]
    protected function withAllFeatures(Builder $query, array $featureIds): void
    {
        $query->when($featureIds, fn (Builder $q, array $ids) => $q->whereHas(
            'features',
            fn (Builder $features) => $features->whereIn('features.id', $ids),
            '>=',
            count($ids),
        ));
    }

    /**
     * Turn the Draft / Scheduled / Published choice into a published_at condition.
     *
     * @param  Builder<Property>  $query
     */
    #[Scope]
    protected function publication(Builder $query, PublicationStatus $status): void
    {
        match ($status) {
            PublicationStatus::Draft => $query->whereNull('published_at'),
            PublicationStatus::Scheduled => $query->where('published_at', '>', now()),
            PublicationStatus::Published => $query->published(),
        };
    }

    /**
     * Sort by one of the allowed keys in SORTS; unknown keys fall back to newest.
     * The id tie-breaker keeps pagination stable when two rows share a price or date,
     * otherwise a row can appear on page 1 and again on page 2.
     *
     * @param  Builder<Property>  $query
     */
    #[Scope]
    protected function sortBy(Builder $query, ?string $sort): void
    {
        [$column, $direction] = self::SORTS[$sort] ?? self::SORTS['newest'];

        $query->orderBy($column, $direction)->orderBy('id', $direction);
    }

    /**
     * Used by `php artisan model:prune` (scheduled in routes/console.php).
     *
     * Because this model uses SoftDeletes, pruning calls forceDelete() on each row,
     * one model at a time, so PropertyObserver still runs and removes image files.
     * (MassPrunable would be faster, but one DELETE query skips model events.)
     *
     * @return Builder<Property>
     */
    public function prunable(): Builder
    {
        return static::onlyTrashed()->where('deleted_at', '<=', now()->subDays(self::PRUNE_AFTER_DAYS));
    }

    /**
     * @return BelongsTo<Agency, $this>
     */
    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    /**
     * @return BelongsTo<PropertyType, $this>
     */
    public function propertyType(): BelongsTo
    {
        return $this->belongsTo(PropertyType::class);
    }

    /**
     * @return BelongsToMany<Feature, $this>
     */
    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class);
    }

    /**
     * All images, main image first.
     *
     * @return HasMany<PropertyImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(PropertyImage::class)
            // chaperone(): every image loaded through this relation gets its
            // `property` relation set to this parent automatically. Deleting an
            // image touches the parent ($touches in PropertyImage), which reads
            // $image->property; without this, that is one lazy-loaded query per
            // image, and shouldBeStrict() throws once 2+ images are loaded together.
            ->chaperone('property')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * Only the main image (lowest sort_order). Used on listing cards.
     *
     * @return HasOne<PropertyImage, $this>
     */
    public function mainImage(): HasOne
    {
        return $this->hasOne(PropertyImage::class)
            ->ofMany(['sort_order' => 'min', 'id' => 'min']);
    }

    /**
     * Users who saved this property.
     *
     * @return BelongsToMany<User, $this>
     */
    public function favouritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'favourites')->withTimestamps();
    }

    /**
     * @return HasMany<Enquiry, $this>
     */
    public function enquiries(): HasMany
    {
        return $this->hasMany(Enquiry::class);
    }
}
