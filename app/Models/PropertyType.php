<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PropertyType extends Model
{
    protected $fillable = ['name', 'slug'];

    /**
     * @return HasMany<Property, $this>
     */
    public function properties(): HasMany
    {
        return $this->hasMany(Property::class);
    }
}
