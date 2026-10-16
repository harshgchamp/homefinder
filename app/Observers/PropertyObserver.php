<?php

namespace App\Observers;

use App\Models\Property;
use App\Models\PropertyImage;
use Illuminate\Support\Str;

/**
 * Reacts to Property model events. Registered with #[ObservedBy] on the model.
 *
 * Reminder: observers only run for model calls such as $property->save() or
 * $property->delete(). A mass query like Property::whereKey($ids)->delete()
 * is a single SQL statement and runs NO observer methods.
 */
class PropertyObserver
{
    /**
     * Before the INSERT: give every property a unique slug if none was set.
     *
     * The slug is deliberately NOT changed when the title is edited later (no
     * "updating" method here). The slug is the public URL; changing it would
     * break bookmarks, shared links and search engine results.
     */
    public function creating(Property $property): void
    {
        if (blank($property->slug)) {
            $property->slug = $this->uniqueSlug($property->title);
        }
    }

    /**
     * Before a permanent delete (forceDelete or model:prune).
     *
     * The database cascade (property_images.property_id ON DELETE CASCADE)
     * deletes the image ROWS, but the database cannot delete FILES on disk.
     * So delete each image through the model first: that fires
     * PropertyImageObserver::deleted(), which removes the file.
     *
     * Not "deleting": a soft delete must keep the images so the property can be restored.
     */
    public function forceDeleting(Property $property): void
    {
        // images()->get() runs a fresh query. Reading $property->images would be
        // lazy loading, which Model::shouldBeStrict() forbids outside production.
        $property->images()->get()->each(fn (PropertyImage $image) => $image->delete());
    }

    /**
     * "3 bed house" -> "3-bed-house", then "3-bed-house-2", "-3"... until free.
     *
     * withTrashed(): the unique index on properties.slug also covers soft-deleted rows,
     * so a slug used by a property in the trash is still taken.
     */
    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title);
        $slug = $base;
        $suffix = 2;

        while (Property::withTrashed()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
