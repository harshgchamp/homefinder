<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A private admin note on a Property or an Agency (polymorphic "notable").
 *
 * @property int $id
 * @property string $notable_type
 * @property int $notable_id
 * @property int|null $user_id
 * @property string $body
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Note extends Model
{
    protected $fillable = ['body', 'user_id'];

    /**
     * The parent: a Property or an Agency.
     *
     * morphTo() reads notable_type ("property"), looks the alias up in the morph
     * map (AppServiceProvider) to get the class, then loads id = notable_id.
     * With eager loading (Note::with('notable')) it runs one query PER TYPE.
     *
     * @return MorphTo<Model, $this>
     */
    public function notable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Who wrote it. user_id, not author_id, so the second argument names the column.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
