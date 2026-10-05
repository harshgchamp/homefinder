<?php

namespace App\Models;

use App\Enums\ListingType;
use App\Enums\PropertyStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Property extends Model
{
    use SoftDeletes;

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
    protected $attributes = [
        'status' => 'available',
    ];

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
