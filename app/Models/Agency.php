<?php

namespace App\Models;

use App\Models\Concerns\ClearsLookupCache;
use App\Support\CachedLookups;
use Database\Factories\AgencyFactory;
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
    use ClearsLookupCache, HasFactory, Notifiable;

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
     * Saving or deleting an agency clears this cached dropdown list.
     */
    public static function lookupCacheKey(): string
    {
        return CachedLookups::AGENCIES;
    }
}
