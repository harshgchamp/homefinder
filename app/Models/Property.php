<?php

namespace App\Models;

use App\Enums\ListingType;
use App\Enums\PropertyStatus;
use Database\Factories\PropertyFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Property extends Model
{
    /** @use HasFactory<PropertyFactory> */
    use HasFactory, SoftDeletes;

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
