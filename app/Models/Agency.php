<?php

namespace App\Models;

use Database\Factories\AgencyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Agency extends Model
{
    /** @use HasFactory<AgencyFactory> */
    use HasFactory;

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
}
