<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A tenant. Lives in the central database.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string $database
 * @property string $status
 */
class Organisation extends Model
{
    protected $fillable = ['name', 'slug', 'database', 'status'];

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }
}
