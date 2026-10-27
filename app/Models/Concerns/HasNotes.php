<?php

namespace App\Models\Concerns;

use App\Models\Note;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Gives a model private admin notes. Used by Property and Agency, so the
 * relation and the clean-up rule are written once.
 *
 * @mixin Model
 */
trait HasNotes
{
    /**
     * Laravel convention: a static method named boot{TraitName} runs once for
     * every model class that uses the trait, like the model's own booted().
     */
    protected static function bootHasNotes(): void
    {
        // Polymorphic = no foreign key = no ON DELETE CASCADE in the database.
        // Without this, deleting a property would leave its notes behind (orphans).
        // self: inside a trait it means the model using it (Property / Agency),
        // so the closure knows $model has notes().
        static::deleted(function (self $model): void {
            // SoftDeletes: a soft delete only fills deleted_at. Keep the notes, so
            // restoring the property brings them back. Only a force delete removes them.
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                return;
            }

            // One DELETE query for all of them (no Note model events are needed).
            $model->notes()->delete();
        });
    }

    /**
     * morphMany(Note, 'notable'): WHERE notable_type = 'property' AND notable_id = 74.
     * 'notable' is the column prefix from the migration's morphs('notable').
     *
     * @return MorphMany<Note, $this>
     */
    public function notes(): MorphMany
    {
        // Newest first, with id as a tie-breaker for notes saved in the same second.
        return $this->morphMany(Note::class, 'notable')->latest()->latest('id');
    }
}
